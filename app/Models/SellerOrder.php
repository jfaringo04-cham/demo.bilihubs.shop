<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SellerOrder extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'logistic_id',
        'status',

        'discount_minor',
        'seller_discount_minor',
        'platform_discount_minor',

        // Return request
        'return_status',
        'return_reason',
        'return_requested_at',

        // Reschedule request
        'reschedule_status',
        'reschedule_reason',
        'reschedule_requested_at',
        'rescheduled_at',

        // Money in centavos
        'service_fee_minor',
        'subtotal_minor',
        'shipping_minor',
        'total_minor',
    ];

    protected $casts = [
        'return_requested_at' => 'datetime',
        'reschedule_requested_at' => 'datetime',
        'rescheduled_at' => 'datetime',

        'subtotal_minor' => 'integer',
        'shipping_minor' => 'integer',
        'total_minor' => 'integer',

        'discount_minor' => 'integer',
        'seller_discount_minor' => 'integer',
        'platform_discount_minor' => 'integer',
        'service_fee_minor' => 'integer',
    ];

    /**
     * Parent buyer checkout/order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Shop/seller that owns this portion of the order.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Logistics provider assigned to this seller order.
     */
    public function logistic(): BelongsTo
    {
        return $this->belongsTo(Logistic::class, 'logistic_id');
    }

    /**
     * Items belonging to this seller order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Shipment assigned to this seller order.
     */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class, 'seller_order_id');
    }
}
