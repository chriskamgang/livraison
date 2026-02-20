<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name', 'first_name', 'last_name',
        'email', 'phone', 'avatar',
        'role', 'status',
        'vehicle_type', 'vehicle_number', 'license_number',
        'id_card_front', 'id_card_back',
        'is_online', 'is_verified',
        'current_latitude', 'current_longitude',
        'rating', 'ratings_count', 'wallet_balance',
        'fcm_token', 'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_online' => 'boolean',
            'is_verified' => 'boolean',
            'current_latitude' => 'decimal:8',
            'current_longitude' => 'decimal:8',
        ];
    }

    // Scopes
    public function scopeDrivers($query) { return $query->where('role', 'driver'); }
    public function scopeClients($query) { return $query->where('role', 'client'); }
    public function scopeOnline($query) { return $query->where('is_online', true); }

    // Relations
    public function orders() { return $this->hasMany(Order::class); }
    public function addresses() { return $this->hasMany(Address::class); }
    public function deliveries() { return $this->hasMany(Delivery::class, 'driver_id'); }
    public function ratings() { return $this->hasMany(Rating::class); }
    public function driverRatings() { return $this->hasMany(Rating::class, 'driver_id'); }
    public function notifications_custom() { return $this->hasMany(CustomNotification::class); }

    // Helpers
    public function isDriver(): bool { return $this->role === 'driver'; }
    public function isClient(): bool { return $this->role === 'client'; }
    public function isAdmin(): bool { return in_array($this->role, ['admin', 'super_admin']); }

    // Filament: seuls les admins peuvent accéder au panel
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->status === 'active';
    }
}
