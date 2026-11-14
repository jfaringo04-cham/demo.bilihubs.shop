<?php

namespace Tests\Feature;

use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Services\ShippingFeeService;
use App\Services\ShippingBonusService;
use Tests\TestCase;

class ShippingFeeBlocksTest extends TestCase
{
    public function test_distance_shipping_uses_four_pesos_per_started_three_km_block(): void
    {
        config()->set('shipping.base_fee_minor', 5000);
        config()->set('shipping.included_distance_km', 3);
        config()->set('shipping.additional_block_km', 3);
        config()->set('shipping.additional_per_block_minor', 400);

        $calculator = new ShippingFeeService();

        foreach ([
            [null, 5000], [0.0, 5000], [3.0, 5000],
            [3.1, 5400], [6.0, 5400], [6.01, 5800],
            [9.0, 5800], [9.01, 6200], [12.0, 6200],
        ] as [$distance, $expected]) {
            $this->assertSame($expected, $calculator->calculateFeeMinor($distance));
        }
    }

    public function test_four_peso_shipping_bonus_splits_two_pesos_each(): void
    {
        config()->set('shipping.base_fee_minor', 5000);

        $this->assertSame(0, ShippingBonusService::deliveryExtraMinor(5000));
        $this->assertSame(200, ShippingBonusService::deliveryExtraMinor(5400));
        $this->assertSame(200, ShippingBonusService::logisticsExtraMinor(5400));
        $this->assertSame(400, ShippingBonusService::deliveryExtraMinor(5800));
        $this->assertSame(400, ShippingBonusService::logisticsExtraMinor(5800));
    }

    public function test_long_linehaul_bonus_applies_only_to_eligible_new_orders(): void
    {
        config([
            'shipping.base_fee_minor' => 5000,
            'shipping.bonus_effective_at' => '2026-11-10T00:00:00+08:00',
            'shipping.linehaul_bonus_effective_at' => '2026-11-11T00:00:00+08:00',
            'shipping.long_linehaul_bonus_effective_at' => '2026-11-15T00:00:00+08:00',
        ]);

        $order = new SellerOrder();
        $order->shipping_minor = 8600;
        $shipment = new Shipment();

        foreach ([
            ['2026-11-14T23:59:59+08:00', 30, 7, [1200, 1200, 1200]],
            ['2026-11-15T00:00:00+08:00', 20, 7, [2160, 720, 720]],
            ['2026-11-15T00:00:00+08:00', 19.99, 7, [1200, 1200, 1200]],
            ['2026-11-15T00:00:00+08:00', null, 7, [1200, 1200, 1200]],
            ['2026-11-15T00:00:00+08:00', 30, null, [0, 1800, 1800]],
        ] as [$placedAt, $distance, $riderId, $expected]) {
            $order->created_at = \Carbon\CarbonImmutable::parse($placedAt);
            $shipment->estimated_distance_km = $distance;
            $shipment->linehaul_rider_id = $riderId;

            $this->assertSame($expected, [
                ShippingBonusService::linehaulExtraForOrderMinor($order, $shipment),
                ShippingBonusService::deliveryExtraForOrderMinor($order, $shipment),
                ShippingBonusService::logisticsExtraForOrderMinor($order, $shipment),
            ]);
        }
    }
}
