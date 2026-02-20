<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number', 'user_id', 'restaurant_id', 'address_id', 'coupon_id',
        'status', 'subtotal', 'delivery_fee', 'discount_amount', 'total',
        'payment_method', 'payment_status',
        'special_instructions', 'cancellation_reason',
        'estimated_delivery_at', 'delivered_at',
    ];

    protected $casts = [
        'estimated_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relations
    public function user() { return $this->belongsTo(User::class); }
    public function restaurant() { return $this->belongsTo(Restaurant::class); }
    public function address() { return $this->belongsTo(Address::class); }
    public function coupon() { return $this->belongsTo(Coupon::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function delivery() { return $this->hasOne(Delivery::class); }
    public function payment() { return $this->hasOne(Payment::class); }
    public function ratings() { return $this->hasMany(Rating::class); }

    // Helpers
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isDelivered(): bool { return $this->status === 'delivered'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    // Génère un numéro de commande unique
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($order) {
            $order->order_number = 'RD-' . strtoupper(uniqid());
        });
    }
}
