<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class notificationHistorique extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'type',
        'seen',
        'user_id',
        'read_at',
    ];

    protected $casts = [
        'seen'    => 'boolean',
        'read_at' => 'datetime',
    ];

   
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }
}
