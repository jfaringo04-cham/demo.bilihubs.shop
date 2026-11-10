<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Voucher;
use App\Services\VoucherCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class VoucherController extends Controller
{
    /**
     * Display active vouchers that the buyer has not claimed yet.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        $vouchers = Voucher::query()
            ->currentlyActive()
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->whereDoesntHave('redemptions', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->whereNotIn('id', function ($query) use ($userId) {
                $query->select('voucher_id')
                    ->from('buyer_voucher_claims')
                    ->where('user_id', $userId);
            })
            ->where(function ($query) {
                $query->where('owner_type', 'platform')
                    ->orWhere(function ($sellerQuery) {
                        $sellerQuery->where('owner_type', 'seller')
                            ->whereHas('seller', function ($seller) {
                                $seller->where('status', 'approved');
                            });
                    });
            })
            ->with('seller')
            ->orderBy('ends_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $vouchers->map(function ($voucher) {
                return $this->formatVoucher($voucher);
            })->values(),
        ]);
    }

    /**
     * Display vouchers claimed by the authenticated buyer.
     */
    public function claimed(Request $request)
    {
        $userId = Auth::id();

        $claims = DB::table('buyer_voucher_claims')
            ->join(
                'vouchers',
                'buyer_voucher_claims.voucher_id',
                '=',
                'vouchers.id'
            )
            ->where('buyer_voucher_claims.user_id', $userId)
            ->select([
                'vouchers.*',
                'buyer_voucher_claims.claimed_at',
            ])
            ->orderByDesc('buyer_voucher_claims.claimed_at')
            ->get();

        $data = $claims->map(function ($voucher) use ($userId) {
            $usedCount = DB::table('voucher_redemptions')
                ->where('voucher_id', $voucher->id)
                ->where('user_id', $userId)
                ->where('status', \App\Models\VoucherRedemption::STATUS_REDEEMED)
                ->count();

            $reservedCount = DB::table('voucher_redemptions')
                ->where('voucher_id', $voucher->id)
                ->where('user_id', $userId)
                ->where('status', \App\Models\VoucherRedemption::STATUS_RESERVED)
                ->count();

            $activeUseCount = $usedCount + $reservedCount;

            $isExpired = now()->greaterThanOrEqualTo($voucher->ends_at);
            $isScheduled = now()->lessThan($voucher->starts_at);

            $isExhausted = $voucher->usage_limit !== null
                && $voucher->usage_count >= $voucher->usage_limit;

            $perUserLimit = $voucher->per_user_limit;

            $isUsed = $perUserLimit !== null
                && $activeUseCount >= (int) $perUserLimit;

            if ($isExpired) {
                $status = 'expired';
            } elseif (!$voucher->is_enabled) {
                $status = 'paused';
            } elseif ($isScheduled) {
                $status = 'scheduled';
            } elseif ($isExhausted) {
                $status = 'exhausted';
            } elseif ($isUsed) {
                $status = 'used';
            } else {
                // Keep the existing mobile API status vocabulary.
                $status = 'claimed';
            }

            return [
                'id' => (int) $voucher->id,
                'name' => $voucher->name,
                'code' => $voucher->code,
                'description' => $voucher->description,
                'owner_type' => $voucher->owner_type,
                'seller_id' => $voucher->seller_id,
                'discount_type' => $voucher->discount_type,
                'discount_value_minor' => $voucher->discount_value_minor,
                'discount_percent_bps' => $voucher->discount_percent_bps,
                'minimum_spend_minor' => $voucher->minimum_spend_minor,
                'maximum_discount_minor' => $voucher->maximum_discount_minor,
                'product_scope' => $voucher->product_scope,
                'starts_at' => $voucher->starts_at,
                'ends_at' => $voucher->ends_at,
                'claimed_at' => $voucher->claimed_at,
                'usage_count' => (int) $voucher->usage_count,
                'usage_limit' => $voucher->usage_limit,
                'per_user_limit' => $perUserLimit,
                'used_by_buyer' => $usedCount,
                'reserved_by_buyer' => $reservedCount,
                'remaining_uses' => $perUserLimit === null
                    ? null
                    : max(0, (int) $perUserLimit - $activeUseCount),
                'status' => $status,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data->values(),
        ]);
    }

    /**
     * Claim an active voucher for the authenticated buyer.
     */
    public function claim(
        Request $request,
        Voucher $voucher,
        \App\Services\VoucherClaimService $claimService
    ) {
        $userId = (int) Auth::id();

        try {
            $claimedVoucher = $claimService->claim($userId, (int) $voucher->id);

            return response()->json([
                'success' => true,
                'message' => 'Voucher successfully claimed.',
                'data' => $this->formatVoucher($claimedVoucher),
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to claim voucher.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to claim voucher right now.',
            ], 500);
        }
    }

    /**
     * Preview a voucher against the actual selected checkout items.
     *
     * The mobile application must send:
     * voucher_code
     * items:
     *   - product_id
     *   - variant_id (optional)
     *   - size_id (optional)
     *   - quantity
     */
    public function preview(
        Request $request,
        VoucherCalculationService $calculationService
    ) {
        $validated = $request->validate([
            'voucher_code' => ['required', 'string', 'max:80'],

            'items' => ['required', 'array', 'min:1'],

            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'items.*.variant_id' => [
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],

            'items.*.size_id' => [
                'nullable',
                'integer',
                'exists:sizes,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $userId = (int) Auth::id();

        $voucherCode = strtoupper(
            trim($validated['voucher_code'])
        );

        // Verify that this buyer has claimed the voucher.
        $hasClaim = DB::table('buyer_voucher_claims')
            ->where('user_id', $userId)
            ->whereIn('voucher_id', function ($query) use ($voucherCode) {
                $query->select('id')
                    ->from('vouchers')
                    ->where('code', $voucherCode);
            })
            ->exists();

        if (!$hasClaim) {
            return response()->json([
                'success' => false,
                'message' => 'Please claim this voucher before using it.',
            ], 422);
        }

        // Build the exact selected checkout items.
        $voucherCartItems = collect();

        try {
            foreach ($validated['items'] as $item) {

                $product = Product::with([
                    'variations.sizes',
                    'seller',
                ])->findOrFail($item['product_id']);

                // Check product availability.
                if (
                    $product->status !== 'published' ||
                    $product->compliance_status !== 'approved'
                ) {
                    throw ValidationException::withMessages([
                        'items' => "Product {$product->name} is not available.",
                    ]);
                }

                if (!$product->seller_id) {
                    throw ValidationException::withMessages([
                        'items' => "Product {$product->name} has no seller shop.",
                    ]);
                }

                $variation = null;

                // Validate selected variation.
                if (!empty($item['variant_id'])) {
                    $variation = $product->variations()
                        ->findOrFail($item['variant_id']);

                } elseif (!empty($item['size_id'])) {

                    $variation = $product->variations()
                        ->whereHas('sizes', function ($query) use ($item) {
                            $query->where('sizes.id', $item['size_id']);
                        })
                        ->first();
                }

                // Validate that the selected size belongs to the product.
                if (!empty($item['size_id'])) {
                    $product->sizes()->findOrFail($item['size_id']);
                }

                // Create the structure required by VoucherCalculationService.
                $voucherCartItems->push((object) [
                    'product' => $product,
                    'variation' => $variation,
                    'quantity' => (int) $item['quantity'],
                ]);
            }

            // Calculate the voucher using selected items only.
            $result = $calculationService->calculate(
                $voucherCode,
                $voucherCartItems,
                $userId
            );

            return response()->json([
                'success' => true,
                'message' => 'Voucher preview calculated successfully.',
                'data' => $result,
            ]);

        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher cannot be applied.',
                'errors' => $exception->errors(),
            ], 422);

        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'One of the selected products or variations was not found.',
            ], 422);

        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to calculate voucher preview right now.',
            ], 500);
        }
    }

    /**
     * Format voucher details for the mobile app.
     */
    private function formatVoucher(Voucher $voucher): array
    {
        return [
            'id' => (int) $voucher->id,
            'name' => $voucher->name,
            'code' => $voucher->code,
            'description' => $voucher->description,
            'owner_type' => $voucher->owner_type,
            'seller_id' => $voucher->seller_id,
            'discount_type' => $voucher->discount_type,
            'discount_value_minor' => $voucher->discount_value_minor,
            'discount_percent_bps' => $voucher->discount_percent_bps,
            'minimum_spend_minor' => $voucher->minimum_spend_minor,
            'maximum_discount_minor' => $voucher->maximum_discount_minor,
            'product_scope' => $voucher->product_scope,
            'usage_limit' => $voucher->usage_limit,
            'usage_count' => $voucher->usage_count,
            'per_user_limit' => $voucher->per_user_limit,
            'starts_at' => $voucher->starts_at,
            'ends_at' => $voucher->ends_at,
            'status' => $voucher->display_status,
        ];
    }
}