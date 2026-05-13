<?php
// app/Models/GestionnaireGain.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GestionnaireGain extends Model
{
    use HasFactory;

    protected $fillable = [
        'gestionnaire_id',
        'hub_id',
        'livraison_id',
        'navette_id',
        'wilaya_type',
        'montant_commission',
        'pourcentage_applique',
        'date_calcul',
        'status',
        'date_demande',
        'date_paiement',
        'note_admin'
    ];

    protected $casts = [
        'montant_commission' => 'decimal:2',
        'pourcentage_applique' => 'decimal:2',
        'date_calcul' => 'datetime',
        'date_demande' => 'datetime',
        'date_paiement' => 'datetime'
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

    /**
     * Relation avec le gestionnaire (peut être null si c'est un hub)
     */
    public function gestionnaire()
    {
        return $this->belongsTo(Gestionnaire::class);
    }

    /**
     * Relation avec le hub (peut être null si c'est un gestionnaire)
     */
    public function hub()
    {
        return $this->belongsTo(Hub::class);
    }

    /**
     * Relation avec la livraison
     */
    public function livraison()
    {
        return $this->belongsTo(Livraison::class);
    }

    /**
     * Relation avec la navette
     */
    public function navette()
    {
        return $this->belongsTo(Navette::class);
    }

    /**
     * Scope pour les gains en attente
     */
    public function scopeEnAttente($query)
    {
        return $query->where('status', 'en_attente');
    }

    /**
     * Scope pour les gains avec demande envoyée
     */
    public function scopeDemandeEnvoyee($query)
    {
        return $query->where('status', 'demande_envoyee');
    }

    /**
     * Scope pour les gains non payés (en attente ou demande envoyée)
     */
    public function scopeNonPayes($query)
    {
        return $query->whereIn('status', ['en_attente', 'demande_envoyee']);
    }

    /**
     * Scope pour les gains payés
     */
    public function scopePayes($query)
    {
        return $query->where('status', 'paye');
    }

    /**
     * Scope pour les gains annulés
     */
    public function scopeAnnules($query)
    {
        return $query->where('status', 'annule');
    }

    /**
     * Scope pour les gains d'un gestionnaire spécifique
     */
    public function scopePourGestionnaire($query, $gestionnaireId)
    {
        return $query->where('gestionnaire_id', $gestionnaireId);
    }

    /**
     * Scope pour les gains d'un hub spécifique
     */
    public function scopePourHub($query, $hubId)
    {
        return $query->where('hub_id', $hubId);
    }

    /**
     * Scope pour les gains d'une navette spécifique
     */
    public function scopePourNavette($query, $navetteId)
    {
        return $query->where('navette_id', $navetteId);
    }

    /**
     * Scope pour les gains par type de wilaya
     */
    public function scopeParType($query, $type)
    {
        return $query->where('wilaya_type', $type);
    }

    /**
     * Scope pour les gains par période
     */
    public function scopeParPeriode($query, $debut, $fin)
    {
        return $query->whereBetween('date_calcul', [$debut, $fin]);
    }

    /**
     * Accesseur pour savoir si le gain appartient à un gestionnaire
     */
    public function getEstGestionnaireAttribute()
    {
        return !is_null($this->gestionnaire_id);
    }

    /**
     * Accesseur pour savoir si le gain appartient à un hub
     */
    public function getEstHubAttribute()
    {
        return !is_null($this->hub_id);
    }

    /**
     * Accesseur pour obtenir le bénéficiaire (nom du gestionnaire ou du hub)
     */
    public function getBeneficiaireNomAttribute()
    {
        if ($this->gestionnaire_id && $this->gestionnaire && $this->gestionnaire->user) {
            return $this->gestionnaire->user->prenom . ' ' . $this->gestionnaire->user->nom;
        }

        if ($this->hub_id && $this->hub) {
            return $this->hub->nom;
        }

        return 'Inconnu';
    }

    /**
     * Accesseur pour obtenir le type de bénéficiaire
     */
    public function getBeneficiaireTypeAttribute()
    {
        if ($this->gestionnaire_id) {
            return 'gestionnaire';
        }

        if ($this->hub_id) {
            return 'hub';
        }

        return 'inconnu';
    }

    /**
     * Accesseur pour obtenir le libellé du statut
     */
    public function getStatutLibelleAttribute()
    {
        $libelles = [
            'en_attente' => 'En attente',
            'demande_envoyee' => 'Demande envoyée',
            'paye' => 'Payé',
            'annule' => 'Annulé'
        ];

        return $libelles[$this->status] ?? $this->status;
    }

    /**
     * Accesseur pour obtenir la classe CSS du statut
     */
    public function getStatutCouleurAttribute()
    {
        $couleurs = [
            'en_attente' => 'bg-yellow-100 text-yellow-800',
            'demande_envoyee' => 'bg-blue-100 text-blue-800',
            'paye' => 'bg-green-100 text-green-800',
            'annule' => 'bg-red-100 text-red-800'
        ];

        return $couleurs[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Accesseur pour obtenir l'icône du statut
     */
    public function getStatutIconeAttribute()
    {
        $icones = [
            'en_attente' => 'fa-clock',
            'demande_envoyee' => 'fa-paper-plane',
            'paye' => 'fa-check-circle',
            'annule' => 'fa-times-circle'
        ];

        return $icones[$this->status] ?? 'fa-question-circle';
    }

    /**
     * Méthode pour marquer le gain comme payé
     */
    public function marquerPaye($note = null)
    {
        $this->update([
            'status' => 'paye',
            'date_paiement' => now(),
            'note_admin' => $note ?? $this->note_admin
        ]);
    }

    /**
     * Méthode pour marquer le gain comme annulé
     */
    public function marquerAnnule($note = null)
    {
        $this->update([
            'status' => 'annule',
            'note_admin' => $note ?? $this->note_admin
        ]);
    }

    /**
     * Méthode pour envoyer une demande de paiement
     */
    public function envoyerDemandePaiement()
    {
        $this->update([
            'status' => 'demande_envoyee',
            'date_demande' => now()
        ]);
    }

    /**
     * Vérifier si le gain peut être payé
     */
    public function getEstPayableAttribute()
    {
        return in_array($this->status, ['en_attente', 'demande_envoyee']);
    }

    /**
     * Vérifier si le gain peut être annulé
     */
    public function getEstAnnulableAttribute()
    {
        return in_array($this->status, ['en_attente', 'demande_envoyee']);
    }
}
