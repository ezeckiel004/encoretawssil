<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CashDelivery extends Model
{
    use HasFactory;

    protected $table = 'cash_deliveries';

    protected $fillable = [
        'id',
        'expediteur_id',
        'destinataire_id',
        'montant',
        'motif',
        'status',
        'date_envoi',
        'date_reponse',
        'reference'
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_envoi' => 'datetime',
        'date_reponse' => 'datetime',
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
                $model->reference = 'COD-' . strtoupper(Str::random(8));
            }
        });
    }

    // ==================== RELATIONS ====================

    public function expediteur()
    {
        return $this->belongsTo(Gestionnaire::class, 'expediteur_id');
    }

    public function destinataire()
    {
        return $this->belongsTo(Gestionnaire::class, 'destinataire_id');
    }

    // ==================== SCOPES ====================

    public function scopeEnAttente($query)
    {
        return $query->where('status', 'en_attente');
    }

    public function scopeAcceptes($query)
    {
        return $query->where('status', 'accepte');
    }

    public function scopeRefuses($query)
    {
        return $query->where('status', 'refuse');
    }

    public function scopeAnnules($query)
    {
        return $query->where('status', 'annule');
    }

    // ==================== MÉTHODES ====================

    public function accepter()
    {
        $this->update([
            'status' => 'accepte',
            'date_reponse' => now()
        ]);
    }

    public function refuser()
    {
        $this->update([
            'status' => 'refuse',
            'date_reponse' => now()
        ]);
    }

    public function annuler()
    {
        $this->update([
            'status' => 'annule',
            'date_reponse' => now()
        ]);
    }

    public function getStatutLibelleAttribute()
    {
        return [
            'en_attente' => 'En attente',
            'accepte' => 'Accepté',
            'refuse' => 'Refusé',
            'annule' => 'Annulé'
        ][$this->status] ?? $this->status;
    }

    public function getStatutCouleurAttribute()
    {
        return [
            'en_attente' => 'yellow',
            'accepte' => 'green',
            'refuse' => 'red',
            'annule' => 'gray'
        ][$this->status] ?? 'gray';
    }
}
