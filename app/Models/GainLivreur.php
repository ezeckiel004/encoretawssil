<?php
// app/Models/GainLivreur.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GainLivreur extends Model
{
    use HasFactory;

    protected $table = 'gains_livreurs';

    protected $fillable = [
        'livreur_id',
        'livraison_id',
        'navette_id',
        'date',
        'montant_brut',
        'frais_navette',
        'frais_hub',
        'frais_point_relais',
        'commission_partenaire1',
        'commission_partenaire2',
        'montant_societe_mere',
        'montant_net_livreur',
        'periode',
        'statut_paiement',
        'date_paiement',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'date_paiement' => 'date',
        'montant_brut' => 'decimal:2',
        'frais_navette' => 'decimal:2',
        'frais_hub' => 'decimal:2',
        'frais_point_relais' => 'decimal:2',
        'commission_partenaire1' => 'decimal:2',
        'commission_partenaire2' => 'decimal:2',
        'montant_societe_mere' => 'decimal:2',
        'montant_net_livreur' => 'decimal:2',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
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

    public function navette()
    {
        return $this->belongsTo(Navette::class);
    }

    /**
     * Scopes
     */
    public function scopeByPeriode($query, $debut, $fin)
    {
        return $query->whereBetween('date', [$debut, $fin]);
    }

    public function scopeByMois($query, $mois)
    {
        return $query->where('periode', $mois);
    }

    public function scopeByLivreur($query, $livreurId)
    {
        return $query->where('livreur_id', $livreurId);
    }

    public function scopeNonPayes($query)
    {
        return $query->where('statut_paiement', 'en_attente');
    }

    public function scopePayes($query)
    {
        return $query->where('statut_paiement', 'paye');
    }

    /**
     * Accesseurs
     */
    public function getTotalDeductionsAttribute(): float
    {
        return $this->frais_navette +
            $this->frais_hub +
            $this->frais_point_relais +
            $this->commission_partenaire1 +
            $this->commission_partenaire2;
    }
}
