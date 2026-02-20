<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = [
        'restaurant_id', 'menu_category_id', 'name', 'description', 'image',
        'price', 'discount_price', 'options',
        'is_available', 'is_featured', 'preparation_time',
        'calories', 'is_vegetarian', 'is_spicy', 'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'is_vegetarian' => 'boolean',
        'is_spicy' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function restaurant() { return $this->belongsTo(Restaurant::class); }
    public function category() { return $this->belongsTo(MenuCategory::class, 'menu_category_id'); }
    public function orderItems() { return $this->hasMany(OrderItem::class); }

    public function getEffectivePriceAttribute(): float
    {
        return $this->discount_price ?? $this->price;
    }

    public function scopeAvailable($query) { return $query->where('is_available', true); }
}
