<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CodePromo extends Model
{
    use HasFactory;

    protected $table = 'codes_promo';

    protected $fillable = [
        'code',
        'description',
        'type',
        'valeur',
        'min_commande',
        'max_utilisations',
        'utilisations_actuelles',
        'date_debut',
        'date_fin',
        'gestionnaire_id',
        'status',
    ];

    protected $casts = [
        'valeur' => 'float',
        'min_commande' => 'float',
        'utilisations_actuelles' => 'integer',
        'max_utilisations' => 'integer',
        'date_debut' => 'date',
        'date_fin' => 'date',
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
            
            // Générer un code unique si non fourni
            if (empty($model->code)) {
                $model->code = self::generateUniqueCode();
            }
        });
    }

    /**
     * Relations
     */
    public function gestionnaire()
    {
        return $this->belongsTo(Gestionnaire::class);
    }

    public function livreurs()
    {
        return $this->belongsToMany(Livreur::class, 'code_promo_livreur')
                    ->withPivot('utilisations')
                    ->withTimestamps();
    }

    /**
     * Vérifier si le code promo est valide
     */
    public function isValid(): bool
    {
        // Vérifier le statut
        if ($this->status !== 'actif') {
            return false;
        }

        // Vérifier les dates
        $now = Carbon::now();
        
        if ($this->date_debut && $now->lt($this->date_debut)) {
            return false;
        }
        
        if ($this->date_fin && $now->gt($this->date_fin)) {
            return false;
        }

        // Vérifier le nombre d'utilisations
        if ($this->max_utilisations && $this->utilisations_actuelles >= $this->max_utilisations) {
            return false;
        }

        return true;
    }

    /**
     * Vérifier si le code promo est applicable à un livreur
     */
    public function isApplicableToLivreur($livreurId): bool
    {
        return $this->livreurs()
                    ->where('livreur_id', $livreurId)
                    ->exists();
    }

    /**
     * Incrémenter le compteur d'utilisations
     */
    public function incrementUtilisations(): void
    {
        $this->increment('utilisations_actuelles');
        
        // Mettre à jour le statut si limite atteinte
        if ($this->max_utilisations && $this->utilisations_actuelles >= $this->max_utilisations) {
            $this->update(['status' => 'inactif']);
        }
    }

    /**
     * Générer un code promo unique
     */
    private static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Scopes
     */
    public function scopeActif($query)
    {
        return $query->where('status', 'actif');
    }

    public function scopeByGestionnaire($query, $gestionnaireId)
    {
        return $query->where('gestionnaire_id', $gestionnaireId);
    }

    public function scopeValable($query)
    {
        $now = Carbon::now();
        
        return $query->where('status', 'actif')
                     ->where(function ($q) use ($now) {
                         $q->whereNull('date_debut')
                           ->orWhere('date_debut', '<=', $now);
                     })
                     ->where(function ($q) use ($now) {
                         $q->whereNull('date_fin')
                           ->orWhere('date_fin', '>=', $now);
                     })
                     ->where(function ($q) {
                         $q->whereNull('max_utilisations')
                           ->orWhereRaw('utilisations_actuelles < max_utilisations');
                     });
    }
}