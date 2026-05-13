<?php
// app/Models/ConfigurationGain.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigurationGain extends Model
{
    protected $table = 'configurations_gains';

    protected $fillable = [
        'nom',
        'description',
        'regles_json',
        'bareme_navette_json',
        'date_debut',
        'date_fin',
        'active',
        'created_by'
    ];

    protected $casts = [
        'regles_json' => 'array',
        'bareme_navette_json' => 'array',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'active' => 'boolean',
    ];

    /**
     * Récupérer la configuration active
     */
    public static function getActive()
    {
        return self::where('active', true)
            ->where('date_debut', '<=', now())
            ->where(function ($q) {
                $q->whereNull('date_fin')
                    ->orWhere('date_fin', '>=', now());
            })
            ->first();
    }

    /**
     * Calculer les frais navette selon le montant
     */
    public function calculerFraisNavette(float $montant): float
    {
        $bareme = $this->bareme_navette_json ?? [];

        foreach ($bareme as $tranche) {
            if ($montant >= $tranche['min'] && $montant <= $tranche['max']) {
                return $tranche['frais'];
            }
        }

        // Par défaut, si aucune tranche ne correspond
        return 0;
    }

    /**
     * Obtenir la répartition des gains selon le montant
     */
    public function getRepartitionPourMontant(float $montant): array
    {
        $regles = $this->regles_json ?? [];
        $tranches = $regles['tranches'] ?? [];

        foreach ($tranches as $tranche) {
            if ($montant >= $tranche['min'] && $montant <= $tranche['max']) {
                return [
                    'societe_mere' => $tranche['societe_mere'] / 100,
                    'hub' => $tranche['hub'] / 100,
                    'point_relais' => $tranche['point_relais'] / 100,
                    'partenaire1' => $tranche['partenaire1'] / 100,
                    'partenaire2' => $tranche['partenaire2'] / 100,
                ];
            }
        }

        // Par défaut, 100% société mère
        return [
            'societe_mere' => 1.0,
            'hub' => 0,
            'point_relais' => 0,
            'partenaire1' => 0,
            'partenaire2' => 0,
        ];
    }
}
