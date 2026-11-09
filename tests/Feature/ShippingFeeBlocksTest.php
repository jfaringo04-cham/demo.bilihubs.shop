<?php

namespace Tests\Feature;

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
}
