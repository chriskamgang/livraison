<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuCategory extends Model
{
    protected $fillable = ['restaurant_id', 'name', 'description', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function restaurant() { return $this->belongsTo(Restaurant::class); }
    public function items() { return $this->hasMany(MenuItem::class); }
    public function scopeActive($query) { return $query->where('is_active', true)->orderBy('sort_order'); }
}
