<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'telephone',
        'photo',
        'latitude',
        'longitude',
        'actif',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'latitude'          => 'double',
        'longitude'         => 'double',
    ];
   
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }
    
    /**
     * Relations
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function client()
    {
        return $this->hasOne(Client::class);
    }

    public function livreur()
    {
        return $this->hasOne(Livreur::class);
    }


    public function avis()
    {
        return $this->hasMany(Avis::class);
    }

    public function commentaires()
    {
        return $this->hasMany(Commentaire::class);
    }

    public function demandeAdhesion()
    {
        return $this->hasMany(DemandeAdhesion::class);
    }

     public function gestionnaire()
{
    return $this->hasOne(Gestionnaire::class);
}

}
