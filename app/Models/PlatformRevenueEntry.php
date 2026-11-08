<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformRevenueEntry extends Model
{
    protected $fillable = [
        'revenue_type',
        'source_type',
        'source_id',
        'entry_type',
        'order_id',
        'seller_order_id',
        'amount_minor',
        'currency',
        'recognized_at',
        'reversal_of_entry_id',
        'metadata',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'order_id' => 'integer',
        'seller_order_id' => 'integer',
        'amount_minor' => 'integer',
        'reversal_of_entry_id' => 'integer',
        'recognized_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'reversal_of_entry_id'
        );
    }
}
