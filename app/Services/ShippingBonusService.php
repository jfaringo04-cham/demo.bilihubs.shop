<?php

namespace App\Services;

use App\Models\SellerOrder;
use App\Models\Shipment;
use Carbon\CarbonImmutable;

/**
 * Allocate only the amount above the PHP 50 base shipping fee.
 * This is a payout rule, not a customer charge or a wallet transaction.
 */
final class ShippingBonusService
{
    public static function extraMinor(int $shippingMinor): int
    {
        return max(0, $shippingMinor - (int) config('shipping.base_fee_minor', 5000));
    }

    /**
     * Only seller orders placed on/after the new shipping-policy activation
     * may receive the 50/50 distance bonus. Protect legacy orders still
     * awaiting buyer confirmation and wallet credit.
     */
    public static function extraForOrderMinor(?SellerOrder $order): int
    {
        if (! $order || ! $order->created_at) {
            return 0; // Missing provenance must never opt into a new payout rule.
        }

        $effectiveAt = (string) config('shipping.bonus_effective_at', '');
        if ($effectiveAt === '') {
            return 0; // Explicit policy cutoff required.
        }

        if (CarbonImmutable::parse($order->created_at)->lt(CarbonImmutable::parse($effectiveAt))) {
            return 0;
        }

        return self::extraMinor((int) ($order->shipping_minor ?? 0));
    }

    public static function linehaulExtraForOrderMinor(?SellerOrder $order, ?Shipment $shipment): int
    {
        $effectiveAt = config('shipping.linehaul_bonus_effective_at');

        if (!$effectiveAt || !$order?->created_at
            || CarbonImmutable::parse($order->created_at)->lt(CarbonImmutable::parse($effectiveAt))
            || !$shipment?->linehaul_rider_id || (float) $shipment->estimated_distance_km <= 0) {
            return 0;
        }

        $shipping = app(ShippingFeeService::class);
        $distanceExtra = $shipping->calculateFeeMinor((float) $shipment->estimated_distance_km)
            - $shipping->baseFeeMinor();

        return min(self::extraForOrderMinor($order), $distanceExtra);
    }

    public static function deliveryExtraForOrderMinor(?SellerOrder $order, ?Shipment $shipment = null): int
    {
        return intdiv(self::extraForOrderMinor($order) - self::linehaulExtraForOrderMinor($order, $shipment), 2);
    }

    public static function logisticsExtraForOrderMinor(?SellerOrder $order, ?Shipment $shipment = null): int
    {
        $extra = self::extraForOrderMinor($order) - self::linehaulExtraForOrderMinor($order, $shipment);

        return $extra - intdiv($extra, 2);
    }

    public static function deliveryExtraMinor(int $shippingMinor): int
    {
        return intdiv(self::extraMinor($shippingMinor), 2);
    }

    public static function logisticsExtraMinor(int $shippingMinor): int
    {
        return self::extraMinor($shippingMinor) - self::deliveryExtraMinor($shippingMinor);
    }
}
