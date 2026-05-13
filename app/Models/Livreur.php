<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Livreur extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'demande_adhesions_id',
        'type',           // 'distributeur' or 'ramasseur'
        'desactiver',
        'wilaya_id',      // Code wilaya du livreur (01 à 58)
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'desactiver' => 'boolean',
        'wilaya_id' => 'string',
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
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec la demande d'adhésion
     */
    public function demandeAdhesion()
    {
        return $this->belongsTo(DemandeAdhesion::class, 'demande_adhesions_id');
    }

    /**
     * Relation avec les livraisons en tant que distributeur
     */
    public function livraisonsDistribution()
    {
        return $this->hasMany(Livraison::class, 'livreur_distributeur_id');
    }

    /**
     * Relation avec les livraisons en tant que ramasseur
     */
    public function livraisonsRamassage()
    {
        return $this->hasMany(Livraison::class, 'livreur_ramasseur_id');
    }

    /**
     * Relation avec les commentaires
     */
    public function commentaires()
    {
        return $this->hasMany(Commentaire::class);
    }

    // ==================== RELATIONS D'ASSIGNATION ====================

    /**
     * Relation avec les assignations de ce livreur
     */
    public function assignations()
    {
        return $this->hasMany(LivreurAssignation::class);
    }

    /**
     * Relation avec les assignations actives
     */
    public function assignationsActives()
    {
        return $this->hasMany(LivreurAssignation::class)
                    ->where('status', 'active')
                    ->where(function($q) {
                        $q->whereNull('date_fin')
                          ->orWhere('date_fin', '>=', now());
                    });
    }

    /**
     * Gestionnaires auxquels ce livreur est assigné (invité)
     */
    public function gestionnairesAssignes()
    {
        return $this->belongsToMany(
            Gestionnaire::class,
            'livreur_assignations',
            'livreur_id',
            'gestionnaire_id'
        )->withPivot('id', 'wilaya_cible', 'date_debut', 'date_fin', 'status', 'motif')
         ->wherePivot('status', 'active')
         ->wherePivot(function($q) {
             $q->whereNull('date_fin')
               ->orWhere('date_fin', '>=', now());
         });
    }

    // ==================== MÉTHODES D'ASSIGNATION ====================

    /**
     * Vérifier si le livreur est assigné à une wilaya spécifique
     */
    public function isAssignedToWilaya($wilayaId): bool
    {
        return $this->assignationsActives()
                    ->where('wilaya_cible', $wilayaId)
                    ->exists();
    }

    /**
     * Vérifier si le livreur est assigné à un gestionnaire spécifique
     */
    public function isAssignedToGestionnaire($gestionnaireId): bool
    {
        return $this->assignationsActives()
                    ->where('gestionnaire_id', $gestionnaireId)
                    ->exists();
    }

    /**
     * Obtenir toutes les wilayas où le livreur est disponible
     * (sa wilaya native + les assignations actives)
     */
    public function getWilayasDisponiblesAttribute(): array
    {
        $wilayas = [];

        // Wilaya native
        if ($this->wilaya_id) {
            $wilayas[] = $this->wilaya_id;
        }

        // Wilayas d'assignation actives
        $assignations = $this->assignationsActives()->get();
        foreach ($assignations as $assignation) {
            $wilayas[] = $assignation->wilaya_cible;
        }

        return array_unique($wilayas);
    }

    /**
     * Vérifier si le livreur est disponible pour un gestionnaire spécifique
     */
    public function isDisponiblePourGestionnaire($gestionnaireId, $wilayaGestionnaire = null): bool
    {
        // Si on a pas la wilaya du gestionnaire, on la récupère
        if (!$wilayaGestionnaire) {
            $gestionnaire = Gestionnaire::find($gestionnaireId);
            if (!$gestionnaire) {
                return false;
            }
            $wilayaGestionnaire = $gestionnaire->wilaya_id;
        }

        // Le livreur est-il natif de cette wilaya ?
        if ($this->wilaya_id === $wilayaGestionnaire) {
            return true;
        }

        // Le livreur a-t-il une assignation active pour ce gestionnaire ?
        return $this->assignationsActives()
                    ->where('gestionnaire_id', $gestionnaireId)
                    ->where('wilaya_cible', $wilayaGestionnaire)
                    ->exists();
    }

    /**
     * Récupérer toutes les assignations actives du livreur
     */
    public function getAssignationsActives()
    {
        return $this->assignationsActives()->with('gestionnaire.user')->get();
    }

    /**
     * Récupérer les gestionnaires auxquels ce livreur est assigné
     */
    public function getGestionnairesAssignes()
    {
        return $this->gestionnairesAssignes()->with('user')->get();
    }

    // ==================== MÉTHODES DE STATISTIQUES ====================

    /**
     * Obtenir le nombre total de livraisons (distribution + ramassage)
     */
    public function getTotalLivraisonsAttribute(): int
    {
        return $this->livraisonsDistribution()->count() + $this->livraisonsRamassage()->count();
    }

    /**
     * Obtenir le nombre de livraisons terminées
     */
    public function getLivraisonsTermineesAttribute(): int
    {
        return $this->livraisonsDistribution()->where('status', 'livre')->count() +
               $this->livraisonsRamassage()->where('status', 'livre')->count();
    }

    /**
     * Obtenir le nombre de livraisons en cours
     */
    public function getLivraisonsEnCoursAttribute(): int
    {
        $statusEnCours = ['en_attente', 'prise_en_charge_ramassage', 'ramasse', 'en_transit', 'prise_en_charge_livraison'];

        return $this->livraisonsDistribution()->whereIn('status', $statusEnCours)->count() +
               $this->livraisonsRamassage()->whereIn('status', $statusEnCours)->count();
    }

    /**
     * Obtenir le taux de réussite
     */
    public function getTauxReussiteAttribute(): float
    {
        $total = $this->total_livraisons;
        if ($total === 0) {
            return 0;
        }

        return round(($this->livraisons_terminees / $total) * 100, 2);
    }

    /**
     * Obtenir les statistiques complètes du livreur
     */
    public function getStatistiques(): array
    {
        return [
            'total_livraisons' => $this->total_livraisons,
            'livraisons_terminees' => $this->livraisons_terminees,
            'livraisons_en_cours' => $this->livraisons_en_cours,
            'taux_reussite' => $this->taux_reussite,
            'wilaya_native' => $this->wilaya_id,
            'wilayas_disponibles' => $this->wilayas_disponibles,
            'est_assigne' => $this->assignationsActives()->count() > 0,
            'nombre_assignations' => $this->assignationsActives()->count(),
        ];
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Vérifier si le livreur est disponible (non désactivé)
     */
    public function isDisponible(): bool
    {
        return !$this->desactiver;
    }

    /**
     * Activer le livreur
     */
    public function activer()
    {
        $this->update(['desactiver' => false]);
    }

    /**
     * Désactiver le livreur
     */
    public function desactiver()
    {
        $this->update(['desactiver' => true]);
    }

    /**
     * Obtenir le nom complet du livreur via l'utilisateur associé
     */
    public function getNomCompletAttribute(): string
    {
        return $this->user ? $this->user->prenom . ' ' . $this->user->nom : 'Livreur inconnu';
    }

    /**
     * Obtenir le nom de la wilaya native
     */
    public function getWilayaNomAttribute(): string
    {
        $wilayas = $this->getWilayaList();
        return $wilayas[$this->wilaya_id] ?? $this->wilaya_id;
    }

    /**
     * Liste des wilayas
     */
    private function getWilayaList(): array
    {
        return [
            '01' => 'Adrar', '02' => 'Chlef', '03' => 'Laghouat', '04' => 'Oum El Bouaghi',
            '05' => 'Batna', '06' => 'Béjaïa', '07' => 'Biskra', '08' => 'Béchar',
            '09' => 'Blida', '10' => 'Bouira', '11' => 'Tamanrasset', '12' => 'Tébessa',
            '13' => 'Tlemcen', '14' => 'Tiaret', '15' => 'Tizi Ouzou', '16' => 'Alger',
            '17' => 'Djelfa', '18' => 'Jijel', '19' => 'Sétif', '20' => 'Saïda',
            '21' => 'Skikda', '22' => 'Sidi Bel Abbès', '23' => 'Annaba', '24' => 'Guelma',
            '25' => 'Constantine', '26' => 'Médéa', '27' => 'Mostaganem', '28' => 'M\'Sila',
            '29' => 'Mascara', '30' => 'Ouargla', '31' => 'Oran', '32' => 'El Bayadh',
            '33' => 'Illizi', '34' => 'Bordj Bou Arréridj', '35' => 'Boumerdès',
            '36' => 'El Tarf', '37' => 'Tindouf', '38' => 'Tissemsilt', '39' => 'El Oued',
            '40' => 'Khenchela', '41' => 'Souk Ahras', '42' => 'Tipaza', '43' => 'Mila',
            '44' => 'Aïn Defla', '45' => 'Naâma', '46' => 'Aïn Témouchent', '47' => 'Ghardaïa',
            '48' => 'Relizane', '49' => 'Timimoun', '50' => 'Bordj Badji Mokhtar',
            '51' => 'Ouled Djellal', '52' => 'Béni Abbès', '53' => 'In Salah',
            '54' => 'In Guezzam', '55' => 'Touggourt', '56' => 'Djanet',
            '57' => 'El M\'Ghair', '58' => 'El Meniaa'
        ];
    }

    /**
     * Vérifier si le livreur est de type distributeur
     */
    public function isDistributeur(): bool
    {
        return $this->type === 'distributeur';
    }

    /**
     * Vérifier si le livreur est de type ramasseur
     */
    public function isRamasseur(): bool
    {
        return $this->type === 'ramasseur';
    }

    /**
     * Vérifier si le livreur est polyvalent (les deux types)
     * Note: Dans votre système actuel, un livreur a un seul type
     * Mais cette méthode peut être utile pour une future évolution
     */
    public function isPolyvalent(): bool
    {
        // Actuellement, un livreur ne peut être qu'un type
        // Mais on garde cette méthode pour la flexibilité future
        return false;
    }

    // ==================== SCOPES ====================

    /**
     * Scope pour filtrer par wilaya
     */
    public function scopeByWilaya($query, $wilayaId)
    {
        return $query->where('wilaya_id', $wilayaId);
    }

    /**
     * Scope pour les livreurs actifs
     */
    public function scopeActif($query)
    {
        return $query->where('desactiver', false);
    }

    /**
     * Scope pour les livreurs inactifs
     */
    public function scopeInactif($query)
    {
        return $query->where('desactiver', true);
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour les distributeurs
     */
    public function scopeDistributeurs($query)
    {
        return $query->where('type', 'distributeur');
    }

    /**
     * Scope pour les ramasseurs
     */
    public function scopeRamasseurs($query)
    {
        return $query->where('type', 'ramasseur');
    }

    /**
     * Scope pour les livreurs disponibles pour un gestionnaire
     * (natif de sa wilaya OU assigné à ce gestionnaire)
     */
    public function scopeDisponiblesPourGestionnaire($query, $gestionnaireId, $wilayaGestionnaire)
    {
        return $query->where(function($q) use ($wilayaGestionnaire, $gestionnaireId) {
            // Livreurs natifs de la wilaya
            $q->where('wilaya_id', $wilayaGestionnaire)
              // OU livreurs assignés à ce gestionnaire
              ->orWhereHas('assignationsActives', function($subq) use ($gestionnaireId, $wilayaGestionnaire) {
                  $subq->where('gestionnaire_id', $gestionnaireId)
                       ->where('wilaya_cible', $wilayaGestionnaire);
              });
        })->where('desactiver', false);
    }
}
