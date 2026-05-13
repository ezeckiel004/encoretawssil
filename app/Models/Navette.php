<?php
// app/Models/Navette.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Navette extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'heure_depart',
        'heure_arrivee',
        'wilaya_depart_id',
        'wilaya_arrivee_id',
        'wilayas_transit',
        'hub_id',
        'vehicule_immatriculation',
        'capacite_max',
        'status',
        'date_depart',
        'date_arrivee_prevue',
        'date_arrivee_reelle',
        'prix_base',
        'prix_par_livraison',
        'distance_km',
        'carburant_estime',
        'peages_estimes',
        'created_by',
        'notes'
    ];

    protected $casts = [
        'heure_depart' => 'datetime',
        'heure_arrivee' => 'datetime',
        'date_depart' => 'datetime',
        'date_arrivee_prevue' => 'datetime',
        'date_arrivee_reelle' => 'datetime',
        'prix_base' => 'decimal:2',
        'prix_par_livraison' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'carburant_estime' => 'decimal:2',
        'peages_estimes' => 'decimal:2',
        'wilayas_transit' => 'array',
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
            if (empty($model->reference)) {
                $model->reference = 'NAV-' . strtoupper(Str::random(8));
            }
        });
    }

    /**
     * Relations
     */
    public function wilayaDepart()
    {
        return $this->belongsTo(Wilaya::class, 'wilaya_depart_id', 'code');
    }

    public function wilayasTransit()
    {
        return $this->belongsToMany(Wilaya::class, 'navette_wilaya_transit', 'navette_id', 'wilaya_code')
            ->withPivot('ordre')
            ->orderBy('pivot_ordre');
    }

    public function wilayaArrivee()
    {
        return $this->belongsTo(Wilaya::class, 'wilaya_arrivee_id', 'code');
    }

    public function hub()
    {
        return $this->belongsTo(Hub::class, 'hub_id');
    }

    public function livraisons()
    {
        return $this->belongsToMany(Livraison::class, 'navette_livraison')
            ->withPivot('ordre_chargement', 'date_prise_en_charge', 'date_livraison', 'qr_code_scan', 'incident_notes')
            ->withTimestamps();
    }

    public function createur()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relation avec les gains des gestionnaires et hubs
     */
    public function gains()
    {
        return $this->hasMany(GestionnaireGain::class, 'navette_id');
    }

    /**
     * NOUVELLES RELATIONS POUR LA GESTION DES ACTEURS
     */

    /**
     * Relation avec les acteurs de la navette (gestionnaires et hubs)
     */
    public function acteurs()
    {
        return $this->hasMany(NavetteActeur::class);
    }

    /**
     * Relation avec les hubs via la table pivot
     */
    public function hubs()
    {
        return $this->belongsToMany(Hub::class, 'navette_acteurs', 'navette_id', 'acteur_id')
            ->wherePivot('type', 'hub')
            ->withPivot('part_pourcentage');
    }

    /**
     * Relation avec les gestionnaires via la table pivot
     */
    public function gestionnaires()
    {
        return $this->belongsToMany(Gestionnaire::class, 'navette_acteurs', 'navette_id', 'acteur_id')
            ->wherePivot('type', 'gestionnaire')
            ->withPivot('wilaya_code', 'part_pourcentage');
    }

    /**
     * Accesseurs
     */
    public function getNbLivraisonsAttribute(): int
    {
        return $this->livraisons()->count();
    }

    public function getPoidsTotalAttribute(): float
    {
        return $this->livraisons()
            ->join('demande_livraisons', 'livraisons.demande_livraisons_id', '=', 'demande_livraisons.id')
            ->join('colis', 'demande_livraisons.colis_id', '=', 'colis.id')
            ->sum('colis.poids');
    }

    public function getValeurTotaleAttribute(): float
    {
        return $this->livraisons()
            ->join('demande_livraisons', 'livraisons.demande_livraisons_id', '=', 'demande_livraisons.id')
            ->join('colis', 'demande_livraisons.colis_id', '=', 'colis.id')
            ->sum('colis.colis_prix');
    }

    public function getTauxRemplissageAttribute(): float
    {
        if ($this->capacite_max <= 0) return 0;
        return round(($this->nb_livraisons / $this->capacite_max) * 100, 2);
    }

    public function getDureeReelleAttribute(): ?string
    {
        if (!$this->date_depart || !$this->date_arrivee_reelle) {
            return null;
        }

        $duree = $this->date_depart->diffInMinutes($this->date_arrivee_reelle);
        $heures = floor($duree / 60);
        $minutes = $duree % 60;

        return $heures . 'h ' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Récupérer la répartition formatée des acteurs
     */
    public function getRepartitionAttribute()
    {
        $repartition = [];

        foreach ($this->acteurs as $acteur) {
            if ($acteur->type === 'gestionnaire') {
                $gestionnaire = $acteur->gestionnaire;
                if ($gestionnaire && $gestionnaire->user) {
                    $repartition[] = [
                        'type' => 'gestionnaire',
                        'id' => $acteur->acteur_id,
                        'nom' => $gestionnaire->user->nom . ' ' . $gestionnaire->user->prenom,
                        'email' => $gestionnaire->user->email,
                        'wilaya' => $acteur->wilaya_code,
                        'part' => (float) $acteur->part_pourcentage,
                        'acteur_id' => $acteur->acteur_id
                    ];
                } else {
                    // Fallback si le gestionnaire n'existe plus
                    $repartition[] = [
                        'type' => 'gestionnaire',
                        'id' => $acteur->acteur_id,
                        'nom' => "Gestionnaire (Wilaya {$acteur->wilaya_code})",
                        'wilaya' => $acteur->wilaya_code,
                        'part' => (float) $acteur->part_pourcentage,
                        'acteur_id' => $acteur->acteur_id
                    ];
                }
            } elseif ($acteur->type === 'hub') {
                $hub = $acteur->hub;
                if ($hub) {
                    $repartition[] = [
                        'type' => 'hub',
                        'id' => $acteur->acteur_id,
                        'nom' => $hub->nom,
                        'email' => $hub->email,
                        'part' => (float) $acteur->part_pourcentage,
                        'acteur_id' => $acteur->acteur_id
                    ];
                } else {
                    // Fallback si le hub n'existe plus
                    $repartition[] = [
                        'type' => 'hub',
                        'id' => $acteur->acteur_id,
                        'nom' => "Hub (ID: {$acteur->acteur_id})",
                        'part' => (float) $acteur->part_pourcentage,
                        'acteur_id' => $acteur->acteur_id
                    ];
                }
            }
        }

        return $repartition;
    }

    /**
     * Récupérer le nombre total d'acteurs
     */
    public function getNbActeursAttribute()
    {
        return $this->acteurs()->count();
    }

    /**
     * Calculer et enregistrer la répartition équitable des parts
     */
    public function calculerRepartitionParts()
    {
        // Récupérer tous les acteurs uniques
        $acteurs = [];

        // 1. Ajouter la wilaya de départ
        if ($this->wilaya_depart_id) {
            $key = "gestionnaire_{$this->wilaya_depart_id}";
            if (!isset($acteurs[$key])) {
                $acteurs[$key] = [
                    'type' => 'gestionnaire',
                    'wilaya_code' => $this->wilaya_depart_id,
                    'description' => "Gestionnaire wilaya départ ({$this->wilaya_depart_id})"
                ];
            }
        }

        // 2. Ajouter les wilayas de transit
        if ($this->wilayas_transit && is_array($this->wilayas_transit)) {
            foreach ($this->wilayas_transit as $wilayaCode) {
                $code = is_array($wilayaCode) ? ($wilayaCode['code'] ?? $wilayaCode) : $wilayaCode;
                $key = "gestionnaire_{$code}";
                if (!isset($acteurs[$key])) {
                    $acteurs[$key] = [
                        'type' => 'gestionnaire',
                        'wilaya_code' => $code,
                        'description' => "Gestionnaire wilaya transit ({$code})"
                    ];
                }
            }
        }

        // 3. Ajouter la wilaya d'arrivée (si différente du départ)
        if ($this->wilaya_arrivee_id && $this->wilaya_arrivee_id != $this->wilaya_depart_id) {
            $key = "gestionnaire_{$this->wilaya_arrivee_id}";
            if (!isset($acteurs[$key])) {
                $acteurs[$key] = [
                    'type' => 'gestionnaire',
                    'wilaya_code' => $this->wilaya_arrivee_id,
                    'description' => "Gestionnaire wilaya arrivée ({$this->wilaya_arrivee_id})"
                ];
            }
        }

        // 4. Ajouter le hub s'il existe
        if ($this->hub_id) {
            $key = "hub_{$this->hub_id}";
            if (!isset($acteurs[$key])) {
                $acteurs[$key] = [
                    'type' => 'hub',
                    'hub_id' => $this->hub_id,
                    'description' => "Hub: " . ($this->hub->nom ?? $this->hub_id)
                ];
            }
        }

        // 5. Vérifier qu'il y a au moins un acteur
        $nbActeurs = count($acteurs);
        if ($nbActeurs === 0) {
            \Log::warning("Aucun acteur trouvé pour la navette {$this->id}");
            return null;
        }

        // 6. Calculer la part équitable
        $partPourcentage = round(100 / $nbActeurs, 2);

        // 7. Supprimer les anciens acteurs
        $this->acteurs()->delete();

        // 8. Créer les nouveaux acteurs
        foreach ($acteurs as $acteur) {
            if ($acteur['type'] === 'gestionnaire') {
                $gestionnaire = Gestionnaire::where('wilaya_id', $acteur['wilaya_code'])
                    ->where('status', 'active')
                    ->first();

                if ($gestionnaire) {
                    $this->acteurs()->create([
                        'type' => 'gestionnaire',
                        'acteur_id' => $gestionnaire->id,
                        'wilaya_code' => $acteur['wilaya_code'],
                        'part_pourcentage' => $partPourcentage
                    ]);
                } else {
                    \Log::warning("Aucun gestionnaire actif trouvé pour la wilaya {$acteur['wilaya_code']} (navette {$this->id})");
                }
            } elseif ($acteur['type'] === 'hub') {
                $this->acteurs()->create([
                    'type' => 'hub',
                    'acteur_id' => $acteur['hub_id'],
                    'part_pourcentage' => $partPourcentage
                ]);
            }
        }

        // 9. Recharger la relation
        $this->load('acteurs');

        \Log::info("Répartition calculée pour la navette {$this->id}: {$nbActeurs} acteurs, part de {$partPourcentage}% chacun");

        return $acteurs;
    }

    /**
     * Vérifier si la navette a une répartition valide
     */
    public function hasValidRepartition(): bool
    {
        if ($this->acteurs()->count() === 0) {
            return false;
        }

        $totalParts = $this->acteurs()->sum('part_pourcentage');
        return abs($totalParts - 100) <= 0.1;
    }

    /**
     * Scopes
     */
    public function scopeByWilayaDepart($query, $wilayaId)
    {
        return $query->where('wilaya_depart_id', $wilayaId);
    }

    public function scopeByWilayaArrivee($query, $wilayaId)
    {
        return $query->where('wilaya_arrivee_id', $wilayaId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeEnCours($query)
    {
        return $query->where('status', 'en_cours');
    }

    public function scopePlanifiees($query)
    {
        return $query->where('status', 'planifiee');
    }

    public function scopeTerminees($query)
    {
        return $query->where('status', 'terminee');
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('date_depart', $date);
    }

    public function scopeByPeriode($query, $debut, $fin)
    {
        return $query->whereBetween('date_depart', [$debut, $fin]);
    }

    public function scopeWithActeurs($query)
    {
        return $query->with(['acteurs', 'acteurs.gestionnaire.user', 'acteurs.hub']);
    }

    public function scopeTermineesAvecGains($query)
    {
        return $query->where('status', 'terminee')
            ->with(['gains', 'acteurs']);
    }
}
