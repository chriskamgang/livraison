<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'status', 'items', 'notes',
        'delivery_address', 'delivery_latitude', 'delivery_longitude',
        'estimated_total', 'actual_total', 'service_fee', 'delivery_fee',
        'payment_method', 'payment_status',
        'driver_id', 'photo_proof', 'validated_by_client',
        'estimated_delivery_time', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'photo_proof' => 'array',
            'estimated_total' => 'decimal:2',
            'actual_total' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'validated_by_client' => 'boolean',
            'delivered_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
}
