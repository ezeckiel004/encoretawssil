<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DemandeLivraison extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'destinataire_id',
        'colis_id',
        'prix',
        'type_livraison',
        'prestation',

        'wilaya_depot',
        'commune_depot',
        'addresse_depot',
        'wilaya',
        'commune',
        'addresse_delivery',
        'info_additionnel',
        'lat_depot',
        'lng_depot',
        'lat_delivery',
        'lng_delivery',
        'depose_au_depot',
        'livraison_gratuite',
    ];

    protected $casts = [
        'prix'    => 'double',
        'lat_depot'    => 'double',
        'lng_depot'    => 'double',
        'lat_delivery' => 'double',
        'lng_delivery' => 'double',
        'livraison_gratuite' => 'boolean',
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

    public function destinataire()
    {
        return $this->belongsTo(Client::class, 'destinataire_id');
    }

    public function colis()
    {
        return $this->belongsTo(Colis::class);
    }

    public function livraison()
    {
        return $this->hasOne(Livraison::class, 'demande_livraisons_id');
    }
}
