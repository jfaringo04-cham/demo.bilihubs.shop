<?php

namespace App\Services;

use App\Models\SellerOrder;

class SellerPickupZoneService
{
    public static function resolve(SellerOrder $sellerOrder): ?string
    {
        $sellerOrder->loadMissing([
            'seller.owner',
            'seller.pickupAddress',
        ]);

        $address = $sellerOrder->seller?->pickupAddress;

        // Barangay is now the primary pickup zone.
        if ($address && filled($address->barangay)) {
            return self::normalize($address->barangay);
        }

        $owner = $sellerOrder->seller?->owner;

        $barangay = $owner?->barangay_name ?: $owner?->barangay;

        if (filled($barangay)) {
            return self::normalize($barangay);
        }

        // Backward compatibility for existing data that has no barangay yet.
        if ($address && filled($address->city)) {
            return self::normalize($address->city);
        }

        $municipality = $owner?->municipality_name ?: $owner?->municipality;

        return filled($municipality)
            ? self::normalize($municipality)
            : null;
    }

    public static function normalize(?string $zone): ?string
    {
        if (blank($zone)) {
            return null;
        }

        return mb_strtolower(trim($zone));
    }
}
