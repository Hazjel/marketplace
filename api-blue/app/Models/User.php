<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Traits\UUID;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, UUID;

    /**
     * Default preferensi notifikasi (dipakai saat kolom masih null).
     */
    public const DEFAULT_NOTIFICATION_PREFS = [
        'order_updates' => true,
        'review_reminders' => true,
        'promotions' => false,
        'price_drops' => true,
        'newsletter' => false,
        'new_messages' => true,
    ];

    /**
     * Default preferensi privasi.
     */
    public const DEFAULT_PRIVACY_PREFS = [
        'profile_visible' => true,
        'show_online_status' => true,
        'show_purchase_history' => false,
    ];

    public function getProfilePictureAttribute($value)
    {
        if ($value) {
            // Check if it is an external URL (Google, etc.)
            if (str_starts_with($value, 'http')) {
                return $value;
            }

            return asset('storage/'.$value);
        }

        return null;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'profile_picture',
        'name',
        'username', // Added
        'email',
        'password',
        'last_seen_at',
        'notification_prefs',
        'privacy_prefs',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen_at' => 'datetime',
            'notification_prefs' => 'array',
            'privacy_prefs' => 'array',
        ];
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', '%'.$search.'%')->orWhere(
                'email',
                'like',
                '%'.$search.'%',
            );
        });
    }

    public function store()
    {
        return $this->hasOne(Store::class);
    }

    public function buyer()
    {
        return $this->hasOne(Buyer::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function followingStores()
    {
        return $this->belongsToMany(
            Store::class,
            'store_followers',
            'user_id',
            'store_id',
        )->withTimestamps();
    }

    /**
     * Override agar link reset password mengarah ke frontend (Vue),
     * bukan ke APP_URL backend Laravel.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
