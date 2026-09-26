<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'order_number', 'status',
        'subtotal_minor',
        'tax_minor',
        'shipping_minor',
        'total_minor',
        'shipping_address', 'notes', 'ordered_at', 'shipped_at', 'delivered_at',
        'return_status', 'return_reason', 'rider_id', 'delivery_status', 'proof_type',
        'proof_data', 'delivery_notes', 'assigned_at', 'failed_at', 'failure_reason',
        'payment_method', 'payment_status',
        'amount_collected_minor',
        'collected_at', 'collected_by',
        'customer_latitude', 'customer_longitude', 'delivery_zone', 'ready_for_pickup',
        'picked_up_at', 'confirmed_received_at', 'reschedule_reason', 'reschedule_requested_at',
        'rescheduled_at'
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'collected_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'failed_at' => 'datetime',
        'confirmed_received_at' => 'datetime',
        'reschedule_requested_at' => 'datetime',
        'rescheduled_at' => 'datetime',

        // Milestone 4 integer centavo fields.
        'subtotal_minor' => 'integer',
        'tax_minor' => 'integer',
        'shipping_minor' => 'integer',
        'total_minor' => 'integer',
        'amount_collected_minor' => 'integer',

        'customer_latitude' => 'decimal:7',
        'customer_longitude' => 'decimal:7',
        'ready_for_pickup' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Order items are reached through seller_orders.
     *
     * orders.id -> seller_orders.order_id
     * seller_orders.id -> order_items.seller_order_id
     */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrderItem::class,
            SellerOrder::class,
            'order_id',
            'seller_order_id',
            'id',
            'id'
        );
    }

    /**
     * Seller-specific portions/parcels of this buyer order.
     */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /**
     * Payment records for this order.
     *
     * Legacy payment fields on orders are intentionally retained
     * during the Milestone 4 transition.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    /**
     * Legacy order-level shipment relationship.
     * Keep temporarily until shipments are transitioned to seller orders.
     */
    public function shipment()
    {
        return $this->hasOne(Shipment::class);
    }

    public function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'placed' => 'warning',
            'confirmed' => 'info',
            'preparing' => 'primary',
            'ready_for_pickup' => 'primary',
            'picked_up' => 'primary',
            'at_sorting_center' => 'info',
            'sorted' => 'success',
            'assigned_to_rider' => 'warning',
            'out_for_delivery' => 'primary',
            'delivered' => 'success',
            'completed' => 'success',
            'delivery_failed' => 'danger',
            'returned' => 'secondary',
            'cancelled' => 'danger',
            'reschedule_requested' => 'warning',
            'rescheduled' => 'info',
            'return_requested' => 'warning',
            default => 'secondary',
        };
    }
}

