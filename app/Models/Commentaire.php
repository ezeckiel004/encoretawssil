<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Commentaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'message',
        'livreur',
        'livreur_id',
        'livraison_id',
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
    public function livreur()
    {
        return $this->belongsTo(Livreur::class);
    }

    public function livraison()
    {
        return $this->belongsTo(Livraison::class);
    }
}
