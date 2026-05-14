<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptionGroupItem extends Model
{
    protected $fillable = [
        'option_group_id', 'name', 'price_adjustment',
        'is_default', 'is_available', 'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_available' => 'boolean',
        'price_adjustment' => 'decimal:2',
    ];

    public function group() { return $this->belongsTo(OptionGroup::class, 'option_group_id'); }
}
