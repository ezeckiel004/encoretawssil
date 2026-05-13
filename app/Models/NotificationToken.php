<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class NotificationToken extends Model
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $fillable = ['user_id', 'token'];

     /**
     * Get the FCM token for the user.
     *
     * @param  mixed  $notification
     * @return string|null
     */
    public function routeNotificationFor($notification)
    {
        return $this->token; 
    }

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

}
