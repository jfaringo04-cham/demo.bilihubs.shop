<?php

namespace Tests\Unit;

use App\Models\Seller;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SellerVacationTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_vacation_is_off_by_default(): void
    {
        $seller = new Seller([
            'is_on_vacation' => false,
        ]);

        $this->assertFalse($seller->isOnVacation());
    }

    public function test_vacation_without_end_date_stays_active(): void
    {
        Carbon::setTestNow('2026-11-04 12:00:00');

        $seller = new Seller([
            'is_on_vacation' => true,
            'vacation_ends_on' => null,
        ]);

        $this->assertTrue($seller->isOnVacation());
    }

    public function test_vacation_stays_active_on_last_day(): void
    {
        Carbon::setTestNow('2026-11-04 12:00:00');

        $seller = new Seller([
            'is_on_vacation' => true,
            'vacation_ends_on' => '2026-11-04',
        ]);

        $this->assertTrue($seller->isOnVacation());
    }

    public function test_vacation_automatically_expires(): void
    {
        Carbon::setTestNow('2026-11-05 08:00:00');

        $seller = new Seller([
            'is_on_vacation' => true,
            'vacation_ends_on' => '2026-11-04',
        ]);

        $this->assertFalse($seller->isOnVacation());
    }
}