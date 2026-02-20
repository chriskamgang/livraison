<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryLocation extends Model
{
    protected $fillable = [
        'delivery_id', 'driver_id',
        'latitude', 'longitude', 'accuracy',
        'speed', 'heading', 'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function delivery() { return $this->belongsTo(Delivery::class); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
}
