<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = [
        'order_id', 'user_id', 'restaurant_id', 'driver_id',
        'type', 'rating', 'comment',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function restaurant() { return $this->belongsTo(Restaurant::class); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
}
