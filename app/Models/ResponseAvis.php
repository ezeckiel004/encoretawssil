<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResponseAvis extends Model
{
    use HasFactory;

    protected $table = 'responses_avis';

    protected $fillable = [
        'message',
        'avis_id',
        'admin_id',
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
    public function avis()
    {
        return $this->belongsTo(Avis::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}