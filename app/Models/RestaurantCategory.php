<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantCategory extends Model
{
    protected $fillable = ['name', 'slug', 'icon', 'color', 'is_active', 'sort_order'];
    protected $casts = ['is_active' => 'boolean'];

    public function restaurants() { return $this->hasMany(Restaurant::class, 'category_id'); }
    public function scopeActive($query) { return $query->where('is_active', true)->orderBy('sort_order'); }
}
