<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Gestionnaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'wilaya_id',
        'status'
    ];

    protected $casts = [
        'status' => 'string',
        'wilaya_id' => 'string',
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

    // ==================== RELATIONS ====================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gains()
    {
        return $this->hasMany(GestionnaireGain::class);
    }

    public function gainsEnAttente()
    {
        return $this->hasMany(GestionnaireGain::class)->where('status', 'en_attente');
    }

    public function gainsPayes()
    {
        return $this->hasMany(GestionnaireGain::class)->where('status', 'paye');
    }

    // ==================== RELATIONS D'ASSIGNATION ====================

    public function livreurAssignations()
    {
        return $this->hasMany(LivreurAssignation::class);
    }

    public function livreurAssignationsActives()
    {
        return $this->hasMany(LivreurAssignation::class)
                    ->where('status', 'active')
                    ->where(function($q) {
                        $q->whereNull('date_fin')
                          ->orWhere('date_fin', '>=', now());
                    });
    }

    /**
     * Livreurs assignés à ce gestionnaire (invités)
     * CORRECTION : Utilisation de wherePivot correctement
     */
    public function livreursAssignes()
    {
        return $this->belongsToMany(
            Livreur::class,
            'livreur_assignations',
            'gestionnaire_id',
            'livreur_id'
        )->withPivot('id', 'wilaya_cible', 'date_debut', 'date_fin', 'status', 'motif')
         ->wherePivot('status', 'active')
         ->where(function($query) {
             $query->whereNull('livreur_assignations.date_fin')
                   ->orWhere('livreur_assignations.date_fin', '>=', now());
         });
    }

    // ==================== MÉTHODES POUR LES LIVREURS ====================

    public function getLivreursDisponiblesAttribute()
    {
        return $this->getLivreursDisponibles();
    }

    public function getLivreursDisponibles()
    {
        // Livreurs natifs de la wilaya
        $livreursNatifs = Livreur::where('wilaya_id', $this->wilaya_id)
                                  ->where('desactiver', false)
                                  ->get();

        // Livreurs assignés à ce gestionnaire
        $livreursAssignes = $this->livreursAssignes()
                                 ->where('desactiver', false)
                                 ->get();

        // Fusionner et supprimer les doublons
        return $livreursNatifs->merge($livreursAssignes)->unique('id');
    }

    public function countLivreursDisponibles()
    {
        $countNatifs = Livreur::where('wilaya_id', $this->wilaya_id)
                              ->where('desactiver', false)
                              ->count();

        $countAssignes = $this->livreursAssignes()
                              ->where('desactiver', false)
                              ->count();

        return $countNatifs + $countAssignes;
    }

    public function isLivreurDisponible($livreurId)
    {
        $livreur = Livreur::find($livreurId);

        if (!$livreur) {
            return false;
        }

        if ($livreur->wilaya_id === $this->wilaya_id) {
            return true;
        }

        return $this->livreursAssignes()
                    ->where('livreurs.id', $livreurId)
                    ->exists();
    }

    public function getLivreursNatifs()
    {
        return Livreur::where('wilaya_id', $this->wilaya_id)
                      ->where('desactiver', false)
                      ->get();
    }

    public function getLivreursInvites()
    {
        return $this->livreursAssignes()
                    ->where('desactiver', false)
                    ->get();
    }

    // ==================== MÉTHODES POUR LES GAINS ====================

    public function getTotalGains($dateDebut = null, $dateFin = null)
    {
        $query = $this->gains();

        if ($dateDebut) {
            $query->where('date_calcul', '>=', $dateDebut);
        }

        if ($dateFin) {
            $query->where('date_calcul', '<=', $dateFin);
        }

        return $query->sum('montant_commission');
    }

    public function getGainsParMois($annee = null)
    {
        $annee = $annee ?? date('Y');

        return $this->gains()
                    ->whereYear('date_calcul', $annee)
                    ->selectRaw('MONTH(date_calcul) as mois, SUM(montant_commission) as total')
                    ->groupBy('mois')
                    ->orderBy('mois')
                    ->get()
                    ->pluck('total', 'mois')
                    ->toArray();
    }

    public function getStatistiquesGains()
    {
        return [
            'total_gains' => $this->gains()->sum('montant_commission'),
            'gains_en_attente' => $this->gainsEnAttente()->sum('montant_commission'),
            'gains_payes' => $this->gainsPayes()->sum('montant_commission'),
            'nombre_livraisons' => $this->gains()->count(),
            'moyenne_par_livraison' => $this->gains()->avg('montant_commission') ?? 0,
        ];
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    public function isActif(): bool
    {
        return $this->status === 'active';
    }

    public function activer()
    {
        $this->update(['status' => 'active']);
    }

    public function desactiver()
    {
        $this->update(['status' => 'inactive']);
    }

    public function getNomCompletAttribute(): string
    {
        return $this->user ? $this->user->prenom . ' ' . $this->user->nom : 'Gestionnaire inconnu';
    }

    public function getWilayaNomAttribute(): string
    {
        $wilayas = $this->getWilayaList();
        return $wilayas[$this->wilaya_id] ?? $this->wilaya_id;
    }

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

    // ==================== SCOPES ====================

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActif($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactif($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeByWilaya($query, $wilayaId)
    {
        return $query->where('wilaya_id', $wilayaId);
    }
}
