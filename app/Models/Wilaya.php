<?php
// app/Models/Wilaya.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wilaya extends Model
{
    use HasFactory;

    protected $table = 'wilayas';

    protected $fillable = [
        'code',
        'nom',
        'nom_arabe',
        'region'
    ];

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'code';

    /**
     * Relations
     */
    public function communes()
    {
        return $this->hasMany(Commune::class, 'wilaya_code', 'code');
    }

    public function livreurs()
    {
        return $this->hasMany(Livreur::class, 'wilaya_id', 'code');
    }

    public function navettesDepart()
    {
        return $this->hasMany(Navette::class, 'wilaya_depart_id', 'code');
    }

    public function navettesArrivee()
    {
        return $this->hasMany(Navette::class, 'wilaya_arrivee_id', 'code');
    }

    public function navettesTransit()
    {
        return $this->hasMany(Navette::class, 'wilaya_transit_id', 'code');
    }
}
