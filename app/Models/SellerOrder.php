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
        'subtotal_minor',
        'shipping_minor',
        'total_minor',
    ];

    protected $casts = [

        // Milestone 4 integer centavo fields.
        'subtotal_minor' => 'integer',
        'shipping_minor' => 'integer',
        'total_minor' => 'integer',
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

