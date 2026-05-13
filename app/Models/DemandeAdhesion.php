<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DemandeAdhesion extends Model
{
    use HasFactory;

    protected $fillable = [
        'message',
        'drivers_license',
        'drivers_license_url',

        'vehicule',
        'vehicule_url',
        'vehicule_type',
        'id_card_type',
        'id_card_number',
        'id_card_image',
        'id_card_image_url',
        'id_card_expiry_date',
        // a remplacer par le client_id
        'user_id',
        'date',
        'info',
        'status',
    ];

    protected $casts = [
        'date'   => 'date',
        'status' => 'string',
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
        return $this->hasOne(Livreur::class, 'demande_adhesions_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
