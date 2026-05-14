<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptionGroup extends Model
{
    protected $fillable = [
        'restaurant_id', 'name', 'type', 'is_required',
        'min_selections', 'max_selections', 'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function restaurant() { return $this->belongsTo(Restaurant::class); }
    public function items() { return $this->hasMany(OptionGroupItem::class)->orderBy('sort_order'); }
    public function menuItems() { return $this->belongsToMany(MenuItem::class, 'menu_item_option_group')->withPivot('sort_order'); }
}
