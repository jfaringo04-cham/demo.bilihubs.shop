<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'order_total_minor',
        'rate',
        'amount_minor',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'order_total_minor' => 'integer',

        'rate' => 'decimal:2',
        'amount_minor' => 'integer',

        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}

