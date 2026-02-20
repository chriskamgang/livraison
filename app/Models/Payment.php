<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'user_id', 'transaction_id', 'reference', 'transaction_reference',
        'amount', 'currency', 'method', 'payment_method', 'status',
        'phone_number', 'phone', 'gateway_response', 'provider_response',
        'paid_at', 'completed_at', 'failure_reason',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'provider_response' => 'array',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function user() { return $this->belongsTo(User::class); }

    public function isPaid(): bool { return $this->status === 'completed'; }
}
