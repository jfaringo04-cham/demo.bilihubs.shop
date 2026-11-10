<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\VoucherRedemptionService;

class OrderVoucherRedemptionObserver
{
    /**
     * Complete or release a reserved voucher when payment status changes.
     *
     * COD collection updates the parent Order model's payment_status to
     * 'paid' after all shipment payments are collected. Partial payments keep
     * the voucher reserved; full payment redeems it. The cancellation flow
     * reverses it before collection when an order is cancelled.
     */
    public function updated(Order $order): void
    {
        if (!$order->wasChanged('payment_status')) {
            return;
        }

        $status = strtolower((string) $order->payment_status);
        $redemptions = app(VoucherRedemptionService::class);

        if ($status === 'paid') {
            $redemptions->redeemForOrder((int) $order->id);
            return;
        }

        if (in_array($status, ['failed', 'cancelled', 'voided'], true)) {
            $redemptions->reverseForOrder((int) $order->id);
        }
    }
}
