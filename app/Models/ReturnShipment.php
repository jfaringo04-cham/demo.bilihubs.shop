<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnShipment extends Model
{
    protected $fillable = [
        'seller_order_id', 'logistic_id', 'rider_id', 'tracking_number',
        'pickup_address', 'delivery_address', 'picked_up_at', 'delivered_at', 'handover_photo',
        'seller_shipping_fee_minor', 'seller_shipping_fee_posted_at',
        'rider_payout_minor', 'wallet_settled_at',
    ];

    protected $casts = [
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'seller_shipping_fee_minor' => 'integer',
        'seller_shipping_fee_posted_at' => 'datetime',
        'rider_payout_minor' => 'integer',
        'wallet_settled_at' => 'datetime',
    ];

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function quoteSellerShippingFee(SellerOrder $sellerOrder): int
    {
        return max(0, (int) $sellerOrder->shipping_minor);
    }

    public function canVerifyPickup(): bool
    {
        return $this->rider_id !== null && !$this->picked_up_at && !$this->delivered_at;
    }

    public function pickupCode(): string
    {
        return strtoupper(substr(hash_hmac(
            'sha256',
            'return-pickup:' . $this->id . ':' . $this->tracking_number . ':' . $this->rider_id,
            config('app.key')
        ), 0, 24));
    }

    public function getPickupAddressLabelAttribute(): string
    {
        $address = json_decode($this->pickup_address, true);

        if (!is_array($address)) {
            return $this->pickup_address;
        }

        $formatted = $address['address'] ?? null;
        if (is_string($formatted) && trim($formatted) !== '') {
            return $formatted;
        }

        $parts = array_intersect_key($address, array_flip([
            'address_line1', 'address_line2', 'barangay', 'city', 'province', 'postal_code', 'country',
        ]));

        return implode(', ', array_filter($parts, fn ($part) => is_string($part) && trim($part) !== ''))
            ?: 'Pickup address unavailable';
    }

    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            $this->delivered_at !== null => 'Delivered to seller',
            $this->picked_up_at !== null => 'Picked up',
            $this->rider_id !== null => 'Rider assigned',
            default => 'Awaiting pickup',
        };
    }
}
