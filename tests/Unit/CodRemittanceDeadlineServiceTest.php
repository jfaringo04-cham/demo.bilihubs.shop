<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Services\CodRemittanceDeadlineService;
use Carbon\Carbon;
use Tests\TestCase;

class CodRemittanceDeadlineServiceTest extends TestCase
{
    public function test_unsubmitted_cod_is_within_deadline(): void
    {
        $payment = new Payment([
            'paid_at' => '2026-11-06 10:00:00',
        ]);

        $result = CodRemittanceDeadlineService::forUnsubmitted(
            $payment,
            Carbon::parse('2026-11-07 09:59:59')
        );

        $this->assertFalse($result['overdue']);
        $this->assertSame(
            'awaiting_remittance',
            $result['status']
        );
        $this->assertSame(
            '2026-11-07 10:00:00',
            $result['due_at']->format('Y-m-d H:i:s')
        );
    }

    public function test_unsubmitted_cod_is_overdue_at_deadline(): void
    {
        $payment = new Payment([
            'paid_at' => '2026-11-06 10:00:00',
        ]);

        $result = CodRemittanceDeadlineService::forUnsubmitted(
            $payment,
            Carbon::parse('2026-11-07 10:00:00')
        );

        $this->assertTrue($result['overdue']);
        $this->assertSame('overdue', $result['status']);
    }

    public function test_on_time_submission_is_accepted(): void
    {
        $payment = new Payment([
            'paid_at' => '2026-11-06 10:00:00',
        ]);

        $this->assertTrue(
            CodRemittanceDeadlineService::wasSubmittedOnTime(
                $payment,
                Carbon::parse('2026-11-07 10:00:00')
            )
        );
    }

    public function test_late_submission_is_detected(): void
    {
        $payment = new Payment([
            'paid_at' => '2026-11-06 10:00:00',
        ]);

        $this->assertFalse(
            CodRemittanceDeadlineService::wasSubmittedOnTime(
                $payment,
                Carbon::parse('2026-11-07 10:00:01')
            )
        );

        $this->assertSame(
            '2026-11-06 10:00:00',
            $payment->paid_at->format('Y-m-d H:i:s')
        );
    }
}
