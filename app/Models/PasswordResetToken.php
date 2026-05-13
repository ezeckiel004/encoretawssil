<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetToken extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'email';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'password_reset_tokens';

    protected $fillable = [
        'email',
        'token',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Vérifie si le token est valide (pas plus de 30 minutes)
     */
    public function isValid(): bool
    {
        if (!$this->created_at) {
            return false;
        }
        
        return $this->created_at->addMinutes(30)->isFuture();
    }

    /**
     * Scope pour les tokens valides
     */
    public function scopeValid($query)
    {
        return $query->whereRaw('DATE_ADD(created_at, INTERVAL 30 MINUTE) > NOW()');
    }
}
