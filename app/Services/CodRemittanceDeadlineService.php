<?php

namespace App\Services;

use App\Models\Payment;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class CodRemittanceDeadlineService
{
    public const DEADLINE_HOURS = 24;

    public static function forUnsubmitted(
        Payment $payment,
        ?CarbonInterface $referenceTime = null
    ): array {
        if (!$payment->paid_at) {
            throw new InvalidArgumentException(
                'COD payment has no collection timestamp.'
            );
        }

        $now = $referenceTime ?? now();

        $dueAt = $payment->paid_at
            ->copy()
            ->addHours(self::DEADLINE_HOURS);

        $overdue = $now->greaterThanOrEqualTo($dueAt);

        return [
            'due_at' => $dueAt,
            'overdue' => $overdue,
            'status' => $overdue
                ? 'overdue'
                : 'awaiting_remittance',
        ];
    }

    public static function wasSubmittedOnTime(
        Payment $payment,
        CarbonInterface $submittedAt
    ): bool {
        if (!$payment->paid_at) {
            throw new InvalidArgumentException(
                'COD payment has no collection timestamp.'
            );
        }

        return $submittedAt->lessThanOrEqualTo(
            $payment->paid_at
                ->copy()
                ->addHours(self::DEADLINE_HOURS)
        );
    }
}
