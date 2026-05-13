<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Bordereau extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'photo_reception',
        'signed_by',
        'photo_reception_url',
        'commentaire',
        'note',
        'client_id',
    ];

    protected $casts = [
        'note' => 'integer',
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

    /**
     * Relations
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function livraisons()
    {
        return $this->hasMany(Livraison::class);
    }
}
