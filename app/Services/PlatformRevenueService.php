<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Payment;
use App\Models\PlatformRevenueEntry;
use App\Models\SellerOrder;
use Illuminate\Support\Facades\DB;

class PlatformRevenueService
{
    /**
     * Recognize the platform service fee from a confirmed COD payment.
     *
     * Only COD payments are supported until an actual gateway
     * successful-payment callback is implemented.
     */
    public function recognizeCodPayment(
        Payment $payment
    ): ?PlatformRevenueEntry {
        return DB::transaction(function () use ($payment) {

            $payment = Payment::query()
                ->lockForUpdate()
                ->with('shipment.sellerOrder')
                ->findOrFail($payment->id);

            // Only confirmed COD payments can recognize service fees.
            if (
                $payment->method !== 'cod' ||
                $payment->status !== 'paid' ||
                !$payment->paid_at
            ) {
                return null;
            }

            $sellerOrder = $payment->shipment?->sellerOrder;

            if (!$sellerOrder) {
                return null;
            }

            // Ensure the payment and shipment belong to the same order.
            if (
                (int) $sellerOrder->order_id !==
                (int) $payment->order_id
            ) {
                return null;
            }

            // Ensure the COD payment covers the complete seller shipment.
            if (
                (int) $payment->amount_minor !==
                (int) $sellerOrder->total_minor
            ) {
                return null;
            }

            // Use the already stored service fee.
            // Do not recalculate the 3% fee here.
            $amountMinor = (int) (
                $sellerOrder->service_fee_minor ?? 0
            );

            if ($amountMinor <= 0) {
                return null;
            }

            PlatformRevenueEntry::query()->firstOrCreate(
                [
                    'revenue_type' => 'service_fee',
                    'source_type' => 'payment',
                    'source_id' => $payment->id,
                    'entry_type' => 'earned',
                ],
                [
                    'order_id' => $payment->order_id,
                    'seller_order_id' => $sellerOrder->id,
                    'amount_minor' => $amountMinor,
                    'currency' => $payment->currency ?: 'PHP',
                    'recognized_at' => $payment->paid_at,
                    'metadata' => [
                        'payment_method' => $payment->method,
                        'shipment_id' => $payment->shipment_id,
                        'recognition_source' =>
                        'confirmed_cod_collection',
                    ],
                ]
            );

            return PlatformRevenueEntry::query()
                ->where('revenue_type', 'service_fee')
                ->where('source_type', 'payment')
                ->where('source_id', $payment->id)
                ->where('entry_type', 'earned')
                ->first();
        });
    }

    /**
     * Recognize an existing seller commission.
     *
     * The commission amount comes directly from the existing
     * Commission record. No commission recalculation happens here.
     */
    public function recognizeCommission(
        Commission $commission
    ): ?PlatformRevenueEntry {
        return DB::transaction(function () use ($commission) {

            $commission = Commission::query()
                ->lockForUpdate()
                ->findOrFail($commission->id);

            $amountMinor = (int) $commission->amount_minor;

            if ($amountMinor <= 0) {
                return null;
            }

            // Find the seller order and its shipment belonging to this commission's seller.
            $sellerOrder = SellerOrder::query()
                ->with('shipment')
                ->where('order_id', $commission->order_id)
                ->whereHas('seller', function ($query) use ($commission) {
                    $query->where('user_id', $commission->seller_id);
                })
                ->first();

            if (!$sellerOrder) {
                return null;
            }

            // Do not recognize commission unless this seller order
            // has actually reached delivered status.
            if ($sellerOrder->status !== 'delivered') {
                return null;
            }

            PlatformRevenueEntry::query()->firstOrCreate(
                [
                    'revenue_type' => 'seller_commission',
                    'source_type' => 'commission',
                    'source_id' => $commission->id,
                    'entry_type' => 'earned',
                ],
                [
                    'order_id' => $commission->order_id,
                    'seller_order_id' => $sellerOrder->id,
                    'amount_minor' => $amountMinor,
                    'currency' => 'PHP',

                    // Recognition happens at delivery, not when
                    // the commission record was originally created.
                    'recognized_at' =>
                    $sellerOrder->shipment?->delivered_at ?? now(),

                    'metadata' => [
                        'seller_id' => $commission->seller_id,
                        'commission_rate' => $commission->rate,
                        'recognition_source' =>
                        'seller_commission_record',
                    ],
                ]
            );

            return PlatformRevenueEntry::query()
                ->where('revenue_type', 'seller_commission')
                ->where('source_type', 'commission')
                ->where('source_id', $commission->id)
                ->where('entry_type', 'earned')
                ->first();
        });
    }
}
