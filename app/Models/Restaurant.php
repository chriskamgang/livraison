<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'slug', 'description',
        'logo', 'cover_image', 'phone', 'email',
        'address', 'latitude', 'longitude', 'city',
        'opening_hours', 'delivery_fee', 'delivery_time_min',
        'delivery_time_max', 'minimum_order',
        'rating', 'ratings_count',
        'is_active', 'is_featured', 'is_open',
    ];

    protected $casts = [
        'opening_hours' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_open' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    // Relations
    public function category() { return $this->belongsTo(RestaurantCategory::class); }
    public function menuCategories() { return $this->hasMany(MenuCategory::class); }
    public function menuItems() { return $this->hasMany(MenuItem::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function ratings() { return $this->hasMany(Rating::class); }
    public function coupons() { return $this->hasMany(Coupon::class); }

    // Scopes
    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeFeatured($query) { return $query->where('is_featured', true); }
    public function scopeOpen($query) { return $query->where('is_open', true); }
}
