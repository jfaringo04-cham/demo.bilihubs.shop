<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class VoucherRedemptionService
{
    /**
     * Record one voucher allocation for an order.
     *
     * COD orders reserve the voucher until the rider records full collection.
     * The observer redeems it when the parent order becomes fully paid. Active
     * reservations count against global and per-buyer usage limits.
     */


    public function recordForOrder(
        int $orderId,
        ?int $sellerOrderId,
        int $voucherId,
        int $userId,
        array $voucherResult,
        bool $reserveUntilPaid
    ): VoucherRedemption {
        return DB::transaction(function () use (
            $orderId,
            $sellerOrderId,
            $voucherId,
            $userId,
            $voucherResult,
            $reserveUntilPaid
        ) {
            // 1. Fast idempotency check.
            $existing = VoucherRedemption::query()
                ->where('order_id', $orderId)
                ->first();

            if ($existing) {
                if (
                    (int) $existing->voucher_id === $voucherId &&
                    (int) $existing->user_id === $userId &&
                    in_array(
                        $existing->status,
                        VoucherRedemption::ACTIVE_STATUSES,
                        true
                    )
                ) {
                    return $existing;
                }

                throw new RuntimeException(
                    'This order already has a voucher redemption record.'
                );
            }

            // 2. Check global usage limit before checking order existence.
            // This also provides the expected validation behavior.
            $voucherCheck = Voucher::query()->findOrFail($voucherId);

            if (
                $voucherCheck->usage_limit !== null &&
                (int) $voucherCheck->usage_count >=
                (int) $voucherCheck->usage_limit
            ) {
                // Recheck idempotency in case a concurrent request
                // completed the same order while this transaction was running.
                $existing = VoucherRedemption::query()
                    ->where('order_id', $orderId)
                    ->first();

                if ($existing) {
                    if (
                        (int) $existing->voucher_id === $voucherId &&
                        (int) $existing->user_id === $userId &&
                        in_array(
                            $existing->status,
                            VoucherRedemption::ACTIVE_STATUSES,
                            true
                        )
                    ) {
                        return $existing;
                    }

                    throw new RuntimeException(
                        'This order already has a voucher redemption record.'
                    );
                }

                throw ValidationException::withMessages([
                    'voucher_code' =>
                    'This voucher has reached its usage limit. Please choose another voucher.',
                ]);
            }

            // 3. Lock the parent order to serialize concurrent
            // voucher recording requests for the same order.
            $order = DB::table('orders')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new RuntimeException(
                    'The order associated with this voucher could not be found.'
                );
            }

            // 4. Recheck idempotency after acquiring the order lock.
            $existing = VoucherRedemption::query()
                ->where('order_id', $orderId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (
                    (int) $existing->voucher_id === $voucherId &&
                    (int) $existing->user_id === $userId &&
                    in_array(
                        $existing->status,
                        VoucherRedemption::ACTIVE_STATUSES,
                        true
                    )
                ) {
                    return $existing;
                }

                throw new RuntimeException(
                    'This order already has a voucher redemption record.'
                );
            }

            // 5. Lock the voucher. This serializes usage-limit checks
            // across different orders using the same voucher.
            $voucher = Voucher::query()
                ->whereKey($voucherId)
                ->lockForUpdate()
                ->firstOrFail();

            // Authoritative global usage-limit check.
            if (
                $voucher->usage_limit !== null &&
                (int) $voucher->usage_count >=
                (int) $voucher->usage_limit
            ) {
                throw ValidationException::withMessages([
                    'voucher_code' =>
                    'This voucher has reached its usage limit. Please choose another voucher.',
                ]);
            }

            // 6. Check active usage by this buyer.
            $activeBuyerUses = VoucherRedemption::query()
                ->where('voucher_id', $voucherId)
                ->where('user_id', $userId)
                ->whereIn(
                    'status',
                    VoucherRedemption::ACTIVE_STATUSES
                )
                ->count();

            if (
                $voucher->per_user_limit !== null &&
                $activeBuyerUses >= (int) $voucher->per_user_limit
            ) {
                throw ValidationException::withMessages([
                    'voucher_code' =>
                    'You have reached your usage limit for this voucher.',
                ]);
            }

            // 7. Create the redemption.
            $status = $reserveUntilPaid
                ? VoucherRedemption::STATUS_RESERVED
                : VoucherRedemption::STATUS_REDEEMED;

            $redemption = VoucherRedemption::create([
                'voucher_id' => $voucherId,
                'user_id' => $userId,
                'order_id' => $orderId,
                'seller_order_id' => $sellerOrderId,
                'voucher_code_snapshot' =>
                (string) ($voucherResult['code'] ?? $voucher->code),
                'funded_by' =>
                (string) ($voucherResult['funded_by'] ?? $voucher->owner_type),
                'discount_minor' =>
                (int) ($voucherResult['discount_minor'] ?? 0),
                'status' => $status,
                'redeemed_at' =>
                $status === VoucherRedemption::STATUS_REDEEMED
                    ? now()
                    : null,
            ]);

            // 8. Increment global usage exactly once.
            $voucher->usage_count = (int) $voucher->usage_count + 1;
            $voucher->save();

            return $redemption;
        }, 3);
    }


    /**
     * Redeem reserved vouchers after the parent order is fully paid.
     */
    public function redeemForOrder(int $orderId): int
    {
        return DB::transaction(function () use ($orderId) {
            $redemptions = VoucherRedemption::query()
                ->where('order_id', $orderId)
                ->where(
                    'status',
                    VoucherRedemption::STATUS_RESERVED
                )
                ->lockForUpdate()
                ->get();

            foreach ($redemptions as $redemption) {
                $redemption->update([
                    'status' => VoucherRedemption::STATUS_REDEEMED,
                    'redeemed_at' => now(),
                    'reversed_at' => null,
                ]);
            }

            return $redemptions->count();
        }, 3);
    }



    /**
     * Reverse active reservations/redemptions once after cancellation or
     * confirmed payment failure. Repeated calls are harmless.
     */
    public function reverseForOrder(int $orderId): int
    {
        return DB::transaction(function () use ($orderId) {
            $redemptions = VoucherRedemption::query()
                ->where('order_id', $orderId)
                ->whereIn('status', VoucherRedemption::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->get();

            $reversed = 0;

            foreach ($redemptions as $redemption) {
                $voucher = Voucher::query()
                    ->whereKey($redemption->voucher_id)
                    ->lockForUpdate()
                    ->first();

                if (!$voucher) {
                    throw new RuntimeException(
                        'The voucher associated with this order could not be found.'
                    );
                }

                // Change the redemption state while holding both row locks.
                $redemption->update([
                    'status' => VoucherRedemption::STATUS_REVERSED,
                    'reversed_at' => now(),
                ]);

                $voucher->usage_count = max(0, (int) $voucher->usage_count - 1);
                $voucher->save();
                $reversed++;
            }

            return $reversed;
        }, 3);
    }
}
