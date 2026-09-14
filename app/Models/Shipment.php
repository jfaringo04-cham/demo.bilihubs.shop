<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = [
        'logistic_id',
        'hub_id',
        'rider_id',
        'order_id',
        'tracking_number',
        'qr_token',
        'status',
        'courier',
        'pickup_address',
        'delivery_address',
        'picked_up_at',
        'delivered_at',
        'at_sorting_center_at',
        'notes',
        'latitude',
        'longitude',
        'rider_latitude',
        'rider_longitude',
        'sorting_area',
        'delivery_zone',
        'delivery_type',
        'sorting_status',
        'rack_number',
        'received_at',
        'scanned_at',
        'scanned_at_seller',
        'scanned_by_rider_id',
        'seller_scan_confirmed_at',
        'sorted_at',
        'staged_at',
        'received_by_sorting_center',
    ];

    protected $casts = [
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'at_sorting_center_at' => 'datetime',
        'scanned_at_seller' => 'datetime',
        'seller_scan_confirmed_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'rider_latitude' => 'decimal:7',
        'rider_longitude' => 'decimal:7',
    ];

    protected static function booted()
    {
        static::creating(function (Shipment $shipment) {
            if (empty($shipment->qr_token)) {
                $shipment->qr_token = self::generateQrToken();
            }
        });
    }

    public static function generateQrToken(): string
    {
        return strtoupper(bin2hex(random_bytes(8))) . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function getQrPayloadAttribute(): string
    {
        return json_encode([
            't' => $this->tracking_number,
            'q' => $this->qr_token,
            'o' => $this->order_id,
        ]);
    }

    public function logistic(): BelongsTo
    {
        return $this->belongsTo(Logistic::class);
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function messages()
    {
        return $this->hasMany(ShipmentMessage::class);
    }

    public function timelineSteps(): array
    {
        $steps = [
            ['label' => 'Created', 'timestamp' => $this->created_at, 'class' => 'primary'],
        ];

        if ($this->sorting_status === 'received' || $this->sorting_status === 'scanned' || $this->sorting_status === 'sorted' || $this->sorting_status === 'staged') {
            $steps[] = ['label' => 'Received at Hub', 'timestamp' => $this->received_at, 'class' => 'info'];
        }

        if ($this->sorting_status === 'scanned' || $this->sorting_status === 'sorted' || $this->sorting_status === 'staged') {
            $steps[] = ['label' => 'Scanned & Tagged', 'timestamp' => $this->scanned_at, 'class' => 'primary'];
        }

        if ($this->sorting_status === 'sorted' || $this->sorting_status === 'staged') {
            $steps[] = ['label' => 'Sorted', 'timestamp' => $this->sorted_at, 'class' => 'secondary'];
        }

        if ($this->sorting_status === 'staged') {
            $steps[] = ['label' => 'Staged for Pickup', 'timestamp' => $this->staged_at, 'class' => 'info'];
        }

        if ($this->status === 'assigned' || $this->status === 'picked_up' || $this->status === 'in_transit' || $this->status === 'at_sorting_center' || $this->status === 'delivered') {
            $steps[] = ['label' => 'Rider Assigned', 'timestamp' => $this->created_at, 'class' => 'info'];
        }

        if ($this->status === 'picked_up' || $this->status === 'in_transit' || $this->status === 'at_sorting_center' || $this->status === 'delivered') {
            $steps[] = ['label' => 'Picked Up', 'timestamp' => $this->picked_up_at, 'class' => 'warning'];
        }

        if ($this->status === 'at_sorting_center' || $this->status === 'in_transit' || $this->status === 'delivered') {
            $steps[] = ['label' => 'At Sorting Center', 'timestamp' => $this->at_sorting_center_at, 'class' => 'info'];
        }

        if ($this->status === 'in_transit' || $this->status === 'delivered') {
            $steps[] = ['label' => 'In Transit', 'timestamp' => null, 'class' => 'primary'];
        }

        if ($this->status === 'delivered') {
            $steps[] = ['label' => 'Delivered', 'timestamp' => $this->delivered_at, 'class' => 'success'];
        }

        if ($this->status === 'cancelled') {
            $steps[] = ['label' => 'Cancelled', 'timestamp' => null, 'class' => 'danger'];
        }

        if ($this->status === 'delayed') {
            $steps[] = ['label' => 'Delayed/Failed', 'timestamp' => null, 'class' => 'danger'];
        }

        return $steps;
    }

    public function isDelayed(): bool
    {
        return $this->status === 'delayed';
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'assigned' => 'info',
            'picked_up' => 'primary',
            'in_transit' => 'primary',
            'at_sorting_center' => 'info',
            'sorted' => 'success',
            'staged' => 'info',
            'delivered' => 'success',
            'cancelled' => 'danger',
            'delayed', 'delivery_failed' => 'danger',
            default => 'secondary',
        };
    }
}
