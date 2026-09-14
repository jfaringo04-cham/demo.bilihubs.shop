<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'order_number', 'status', 'subtotal', 'tax', 'shipping',
        'total', 'shipping_address', 'notes', 'ordered_at', 'shipped_at', 'delivered_at',
        'return_status', 'return_reason', 'rider_id', 'delivery_status', 'proof_type',
        'proof_data', 'delivery_notes', 'assigned_at', 'failed_at', 'failure_reason',
        'payment_method', 'payment_status', 'amount_collected', 'collected_at', 'collected_by',
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
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_collected' => 'decimal:2',
        'customer_latitude' => 'decimal:7',
        'customer_longitude' => 'decimal:7',
        'ready_for_pickup' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

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
