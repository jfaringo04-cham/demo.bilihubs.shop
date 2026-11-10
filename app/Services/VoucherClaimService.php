<?php

namespace App\Services;

use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherClaimService
{
    /**
     * Claim a voucher for a buyer exactly once.
     *
     * The voucher row lock serializes claims against the global usage limit.
     * The unique database key on (voucher_id, user_id) remains the final guard
     * against duplicate claims.
     */
    public function claim(int $userId, int $voucherId): Voucher
    {
        return DB::transaction(function () use ($userId, $voucherId) {
            $voucher = Voucher::query()
                ->whereKey($voucherId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$voucher->is_enabled) {
                throw ValidationException::withMessages([
                    'voucher' => 'This voucher is currently disabled.',
                ]);
            }

            if (
                !$voucher->starts_at ||
                !$voucher->ends_at ||
                $voucher->starts_at->isFuture() ||
                !$voucher->ends_at->isFuture()
            ) {
                throw ValidationException::withMessages([
                    'voucher' => 'This voucher is not currently available.',
                ]);
            }

            if (
                $voucher->usage_limit !== null &&
                (int) $voucher->usage_count >= (int) $voucher->usage_limit
            ) {
                throw ValidationException::withMessages([
                    'voucher' => 'This voucher has reached its usage limit.',
                ]);
            }

            if (!in_array($voucher->owner_type, ['platform', 'seller'], true)) {
                throw ValidationException::withMessages([
                    'voucher' => 'Unsupported voucher owner.',
                ]);
            }

            if (
                $voucher->owner_type === 'seller' &&
                (
                    !$voucher->seller_id ||
                    $voucher->seller?->status !== 'approved'
                )
            ) {
                throw ValidationException::withMessages([
                    'voucher' => 'This seller voucher is unavailable.',
                ]);
            }

            $alreadyClaimed = DB::table('buyer_voucher_claims')
                ->where('voucher_id', $voucher->id)
                ->where('user_id', $userId)
                ->exists();

            if ($alreadyClaimed) {
                throw ValidationException::withMessages([
                    'voucher' => 'You have already claimed this voucher.',
                ]);
            }

            DB::table('buyer_voucher_claims')->insert([
                'voucher_id' => $voucher->id,
                'user_id' => $userId,
                'claimed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $voucher->fresh();
        }, 3);
    }
}
