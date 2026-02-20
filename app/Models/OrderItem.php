<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'menu_item_id', 'item_name', 'item_price',
        'quantity', 'subtotal', 'options', 'special_instructions',
    ];

    protected $casts = [
        'options' => 'array',
        'item_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function menuItem() { return $this->belongsTo(MenuItem::class); }
}
