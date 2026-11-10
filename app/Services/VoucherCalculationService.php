<?php

namespace App\Services;

use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherCalculationService
{
    /**
     * Preview a single voucher against checkout CartItem models.
     * All money values are integer centavos; no database writes occur.
     *
     * @return array{voucher_id:int,code:string,owner_type:string,funded_by:string,eligible_subtotal_minor:int,discount_minor:int,seller_discount_minor:int,platform_discount_minor:int,items:array<int,array{product_id:int,seller_id:int,eligible_subtotal_minor:int,discount_minor:int}>}
     */
    public function calculate(string $code, Collection $cartItems, int $userId): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            throw ValidationException::withMessages(['voucher_code' => 'Enter a voucher code.']);
        }

        $voucher = Voucher::query()
            ->with('products:id')
            ->where('code', $code)
            ->first();

        if (!$voucher || !$voucher->is_enabled || !$voucher->starts_at || !$voucher->ends_at ||
            $voucher->starts_at->isFuture() || !$voucher->ends_at->isFuture()) {
            throw ValidationException::withMessages(['voucher_code' => 'This voucher is invalid or not currently available.']);
        }

        // Claiming is required for every buyer-facing entry point. Keeping
        // this rule here prevents web/mobile checkout paths from diverging.
        $hasClaim = DB::table('buyer_voucher_claims')
            ->where('voucher_id', $voucher->id)
            ->where('user_id', $userId)
            ->exists();

        if (!$hasClaim) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Please claim this voucher before using it.',
            ]);
        }

        if (!in_array($voucher->owner_type, ['seller', 'platform'], true)) {
            throw ValidationException::withMessages(['voucher_code' => 'Unsupported voucher owner.']);
        }

        if ($voucher->owner_type === 'seller' && (!$voucher->seller_id || $voucher->seller?->status !== 'approved')) {
            throw ValidationException::withMessages(['voucher_code' => 'This seller voucher is unavailable.']);
        }

        if ($voucher->usage_limit !== null && $voucher->usage_count >= $voucher->usage_limit) {
            throw ValidationException::withMessages(['voucher_code' => 'This voucher has reached its usage limit.']);
        }

        // Active reservations, including uncollected COD orders, consume the buyer's limit.
        $usedByBuyer = $voucher->redemptions()
            ->where('user_id', $userId)
            ->whereIn('status', \App\Models\VoucherRedemption::ACTIVE_STATUSES)
            ->count();
        if ($voucher->per_user_limit !== null && $usedByBuyer >= $voucher->per_user_limit) {
            throw ValidationException::withMessages(['voucher_code' => 'You have reached your usage limit for this voucher.']);
        }

        $selectedIds = $voucher->product_scope === 'selected'
            ? $voucher->products->pluck('id')->map(fn ($id) => (int) $id)->all()
            : null;

        if (!in_array($voucher->product_scope, ['all', 'selected'], true)) {
            throw ValidationException::withMessages(['voucher_code' => 'Invalid voucher product scope.']);
        }

        $eligible = [];
        foreach ($cartItems as $index => $item) {
            $product = $item->product;
            if (!$product) {
                continue;
            }
            $sellerId = (int) $product->seller_id;
            $productId = (int) $product->id;
            if ($voucher->owner_type === 'seller' && $sellerId !== (int) $voucher->seller_id) {
                continue;
            }
            if ($selectedIds !== null && !in_array($productId, $selectedIds, true)) {
                continue;
            }
            $quantity = (int) $item->quantity;
            $priceMinor = (int) ($item->variation
                ? $item->variation->effective_price_minor
                : $product->effective_price_minor);
            if ($quantity <= 0 || $priceMinor < 0) {
                throw ValidationException::withMessages(['voucher_code' => 'Invalid checkout item quantity or price.']);
            }
            $amount = $priceMinor * $quantity;
            if ($amount > 0) {
                $eligible[] = [
                    'cart_index' => (int) $index,
                    'product_id' => $productId,
                    'seller_id' => $sellerId,
                    'eligible_subtotal_minor' => $amount,
                    'discount_minor' => 0,
                ];
            }
        }

        $subtotal = array_sum(array_column($eligible, 'eligible_subtotal_minor'));
        if ($subtotal <= 0) {
            throw ValidationException::withMessages(['voucher_code' => 'No eligible products for this voucher.']);
        }
        if ($subtotal < (int) $voucher->minimum_spend_minor) {
            throw ValidationException::withMessages(['voucher_code' => 'The eligible subtotal does not meet the voucher minimum spend.']);
        }

        if ($voucher->discount_type === 'fixed') {
            $discount = min($subtotal, (int) $voucher->discount_value_minor);
        } elseif ($voucher->discount_type === 'percentage') {
            $discount = intdiv($subtotal * (int) $voucher->discount_percent_bps + 5000, 10000);
            if ($voucher->maximum_discount_minor !== null) {
                $discount = min($discount, (int) $voucher->maximum_discount_minor);
            }
            $discount = min($subtotal, $discount);
        } else {
            throw ValidationException::withMessages(['voucher_code' => 'Unsupported voucher discount type.']);
        }
        if ($discount <= 0) {
            throw ValidationException::withMessages(['voucher_code' => 'This voucher provides no discount for these items.']);
        }

        // Largest-remainder allocation keeps the total exact without ever
        // assigning more discount to an item than that item's eligible value.
        $allocated = 0;
        $remainders = [];

        foreach ($eligible as $index => &$row) {
            $numerator = $discount * $row['eligible_subtotal_minor'];
            $baseShare = intdiv($numerator, $subtotal);

            $row['discount_minor'] = $baseShare;
            $remainders[$index] = $numerator % $subtotal;
            $allocated += $baseShare;
        }
        unset($row);

        $remaining = $discount - $allocated;
        $remainderOrder = array_keys($eligible);

        usort($remainderOrder, function (int $left, int $right) use ($remainders): int {
            $byRemainder = $remainders[$right] <=> $remainders[$left];

            return $byRemainder !== 0
                ? $byRemainder
                : ($left <=> $right);
        });

        foreach ($remainderOrder as $index) {
            if ($remaining <= 0) {
                break;
            }

            if (
                $remainders[$index] > 0 &&
                $eligible[$index]['discount_minor'] < $eligible[$index]['eligible_subtotal_minor']
            ) {
                $eligible[$index]['discount_minor']++;
                $remaining--;
            }
        }

        if ($remaining !== 0) {
            throw new \LogicException('Voucher discount could not be allocated safely.');
        }

        $isSellerFunded = $voucher->owner_type === 'seller';
        return [
            'voucher_id' => (int) $voucher->id,
            'code' => $voucher->code,
            'owner_type' => $voucher->owner_type,
            'funded_by' => $isSellerFunded ? 'seller' : 'platform',
            'eligible_subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'seller_discount_minor' => $isSellerFunded ? $discount : 0,
            'platform_discount_minor' => $isSellerFunded ? 0 : $discount,
            'items' => $eligible,
        ];
    }
}
