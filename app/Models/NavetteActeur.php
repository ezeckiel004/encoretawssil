<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavetteActeur extends Model
{
    protected $fillable = [
        'navette_id',
        'type',
        'acteur_id',
        'wilaya_code',
        'part_pourcentage'
    ];

    protected $casts = [
        'part_pourcentage' => 'decimal:2'
    ];

    /**
     * Relation avec la navette
     */
    public function navette()
    {
        return $this->belongsTo(Navette::class);
    }

    /**
     * Relation avec le gestionnaire
     */
    public function gestionnaire()
    {
        return $this->belongsTo(Gestionnaire::class, 'acteur_id');
    }

    /**
     * Relation avec le hub
     */
    public function hub()
    {
        return $this->belongsTo(Hub::class, 'acteur_id');
    }
}
