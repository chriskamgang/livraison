<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'driver_id', 'status',
        'restaurant_latitude', 'restaurant_longitude',
        'delivery_latitude', 'delivery_longitude',
        'pickup_address', 'delivery_address', 'delivery_address_details',
        'distance_km', 'estimated_minutes',
        'delivery_proof_photo', 'notes',
        'assigned_at', 'picked_up_at', 'delivered_at',
        'driver_earnings',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'restaurant_latitude' => 'decimal:8',
        'restaurant_longitude' => 'decimal:8',
    ];

    // Relations
    public function order() { return $this->belongsTo(Order::class); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
    public function locations() { return $this->hasMany(DeliveryLocation::class); }

    // Dernière position connue du livreur
    public function lastLocation() { return $this->hasOne(DeliveryLocation::class)->latestOfMany('recorded_at'); }

    // Scopes
    public function scopeActive($query) {
        return $query->whereNotIn('status', ['delivered', 'failed']);
    }
}
