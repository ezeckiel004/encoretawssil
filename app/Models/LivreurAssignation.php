<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LivreurAssignation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'livreur_id',
        'gestionnaire_id',
        'wilaya_cible',
        'date_debut',
        'date_fin',
        'status',
        'motif',
        'created_by'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'status' => 'string',
        'wilaya_cible' => 'string',
    ];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Boot function to generate UUID for new records
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // ==================== RELATIONS ====================

    /**
     * Relation avec le livreur
     */
    public function livreur()
    {
        return $this->belongsTo(Livreur::class);
    }

    /**
     * Relation avec le gestionnaire
     */
    public function gestionnaire()
    {
        return $this->belongsTo(Gestionnaire::class);
    }

    /**
     * Relation avec l'utilisateur qui a créé l'assignation
     */
    public function createur()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Vérifier si l'assignation est active
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->date_fin && $this->date_fin < now()) {
            return false;
        }

        return true;
    }

    /**
     * Vérifier si l'assignation est expirée
     */
    public function isExpired(): bool
    {
        return $this->date_fin && $this->date_fin < now();
    }

    /**
     * Terminer l'assignation
     */
    public function terminer()
    {
        $this->update([
            'status' => 'terminee',
            'date_fin' => now()
        ]);
    }

    /**
     * Annuler l'assignation
     */
    public function annuler()
    {
        $this->update([
            'status' => 'annulee'
        ]);
    }

    /**
     * Prolonger l'assignation
     */
    public function prolonger($newDateFin)
    {
        $this->update([
            'date_fin' => $newDateFin
        ]);
    }

    /**
     * Obtenir la durée de l'assignation en jours
     */
    public function getDureeAttribute(): int
    {
        $fin = $this->date_fin ?? now();
        return $this->date_debut->diffInDays($fin);
    }

    /**
     * Obtenir le statut avec libellé
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'active' => 'Active',
            'terminee' => 'Terminée',
            'annulee' => 'Annulée'
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Vérifier si l'assignation peut être modifiée
     */
    public function isModifiable(): bool
    {
        return $this->status === 'active';
    }

    // ==================== SCOPES ====================

    /**
     * Scope pour les assignations actives
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where(function($q) {
                         $q->whereNull('date_fin')
                           ->orWhere('date_fin', '>=', now());
                     });
    }

    /**
     * Scope pour les assignations expirées
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
                     ->where('date_fin', '<', now());
    }

    /**
     * Scope pour les assignations terminées
     */
    public function scopeTerminees($query)
    {
        return $query->where('status', 'terminee');
    }

    /**
     * Scope pour les assignations annulées
     */
    public function scopeAnnulees($query)
    {
        return $query->where('status', 'annulee');
    }

    /**
     * Scope pour filtrer par wilaya cible
     */
    public function scopeByWilayaCible($query, $wilayaId)
    {
        return $query->where('wilaya_cible', $wilayaId);
    }
}
