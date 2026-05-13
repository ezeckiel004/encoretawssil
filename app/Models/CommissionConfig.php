<?php
// app/Models/CommissionConfig.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionConfig extends Model
{
    use HasFactory;

    protected $table = 'commission_configs';

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];

    public static function getValue(string $key): ?float
    {
        $config = self::where('key', $key)->first();
        return $config ? (float) $config->value : null;
    }

    public static function setValue(string $key, float $value, ?string $description = null): self
    {
        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'description' => $description,
            ]
        );
    }

    public static function getAllCommissionConfigs(): array
    {
        $configs = self::whereIn('key', [
            'commission_depart_default',
            'commission_arrivee_default'
        ])->get();

        return [
            'depart' => $configs->where('key', 'commission_depart_default')->first()?->value ?? 25,
            'arrivee' => $configs->where('key', 'commission_arrivee_default')->first()?->value ?? 25,
            'admin' => 100 - (
                ($configs->where('key', 'commission_depart_default')->first()?->value ?? 25) +
                ($configs->where('key', 'commission_arrivee_default')->first()?->value ?? 25)
            )
        ];
    }
}
