<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\RiderWalletTransaction;
use App\Services\RiderWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    /**
     * Mobile Rider Earnings API
     *
     * GET /api/rider/earnings
     *
     * Earnings are based ONLY on actual pickup/final-delivery wallet credits.
     *
     * No earnings are calculated or hardcoded here.
     */
    public function index(
        Request $request,
        RiderWalletService $walletService
    ): JsonResponse {
        try {
            $rider = $request->user();

            /*
            |--------------------------------------------------------------------------
            | AUTHENTICATION
            |--------------------------------------------------------------------------
            */

            if (!$rider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            if (!$rider->isRider()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account is not a rider account.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | RIDER WALLET
            |--------------------------------------------------------------------------
            |
            | RiderWalletService is the official wallet/earnings source.
            |
            */

            $wallet = $walletService->walletForRider(
                (int) $rider->id
            );

            /*
            |--------------------------------------------------------------------------
            | DATE RANGES
            |--------------------------------------------------------------------------
            */

            $startOfToday = now()->startOfDay();

            $startOfWeek = now()->startOfWeek();

            $startOfMonth = now()->startOfMonth();

            /*
            |--------------------------------------------------------------------------
            | DELIVERY CREDIT QUERY
            |--------------------------------------------------------------------------
            |
            | Only actual credited rider earnings are included.
            |
            | A delivery does NOT automatically become an earning just
            | because the shipment status is "delivered".
            |
            | RiderWalletService creates pickup/final credits only after
            | the order is confirmed received according to the official
            | BiliHub wallet rules.
            |
            */

            $deliveryCredits = RiderWalletTransaction::query()
                ->where('rider_wallet_id', $wallet->id)
                ->whereIn('type', $walletService->earningTransactionTypes());

            /*
            |--------------------------------------------------------------------------
            | TODAY EARNINGS
            |--------------------------------------------------------------------------
            */

            $todayEarningsMinor = (int) (
                clone $deliveryCredits
            )
                ->where(
                    'created_at',
                    '>=',
                    $startOfToday
                )
                ->sum('amount_minor');

            /*
            |--------------------------------------------------------------------------
            | WEEK EARNINGS
            |--------------------------------------------------------------------------
            */

            $weekEarningsMinor = (int) (
                clone $deliveryCredits
            )
                ->where(
                    'created_at',
                    '>=',
                    $startOfWeek
                )
                ->sum('amount_minor');

            /*
            |--------------------------------------------------------------------------
            | MONTH EARNINGS
            |--------------------------------------------------------------------------
            */

            $monthEarningsMinor = (int) (
                clone $deliveryCredits
            )
                ->where(
                    'created_at',
                    '>=',
                    $startOfMonth
                )
                ->sum('amount_minor');

            /*
            |--------------------------------------------------------------------------
            | TOTAL EARNINGS
            |--------------------------------------------------------------------------
            */

            $totalEarningsMinor = (int) (
                clone $deliveryCredits
            )->sum('amount_minor');

            /*
            |--------------------------------------------------------------------------
            | DELIVERY COUNTS
            |--------------------------------------------------------------------------
            */

            $todayDeliveries = (clone $deliveryCredits)
                ->where('created_at', '>=', $startOfToday)
                ->whereNotNull('shipment_id')
                ->distinct()
                ->count('shipment_id');

            $weekDeliveries = (clone $deliveryCredits)
                ->where('created_at', '>=', $startOfWeek)
                ->whereNotNull('shipment_id')
                ->distinct()
                ->count('shipment_id');

            $monthDeliveries = (clone $deliveryCredits)
                ->where('created_at', '>=', $startOfMonth)
                ->whereNotNull('shipment_id')
                ->distinct()
                ->count('shipment_id');

            $totalDeliveries = (clone $deliveryCredits)
                ->whereNotNull('shipment_id')
                ->distinct()
                ->count('shipment_id');

            /*
            |--------------------------------------------------------------------------
            | AVERAGE EARNING
            |--------------------------------------------------------------------------
            |
            | Average earning per shipment credited to this rider.
            |
            */

            $averageEarningMinor = 0;

            if ($totalDeliveries > 0) {
                $averageEarningMinor = (int) round(
                    $totalEarningsMinor
                    / $totalDeliveries
                );
            }

            /*
            |--------------------------------------------------------------------------
            | RECENT DELIVERY EARNINGS
            |--------------------------------------------------------------------------
            */

            $recentTransactions = (
                clone $deliveryCredits
            )
                ->with([
                    'shipment.sellerOrder.order.user',
                    'shipment.sellerOrder.seller',
                ])
                ->latest()
                ->take(10)
                ->get();

            $recentDeliveries = $recentTransactions
                ->map(function (
                    RiderWalletTransaction $transaction
                ) {
                    $shipment = $transaction->shipment;

                    $sellerOrder =
                        $shipment?->sellerOrder;

                    $order =
                        $sellerOrder?->order;

                    $customer =
                        $order?->user;

                    return [
                        /*
                        |--------------------------------------------------------------------------
                        | TRANSACTION
                        |--------------------------------------------------------------------------
                        */

                        'transaction_id' =>
                            $transaction->id,

                        'reference' =>
                            $transaction->reference,

                        /*
                        |--------------------------------------------------------------------------
                        | SHIPMENT
                        |--------------------------------------------------------------------------
                        */

                        'shipment_id' =>
                            $shipment?->id,

                        'tracking_number' =>
                            $shipment?->tracking_number
                            ?? '',

                        'status' =>
                            $shipment?->status
                            ?? 'delivered',

                        /*
                        |--------------------------------------------------------------------------
                        | CUSTOMER
                        |--------------------------------------------------------------------------
                        */

                        'customer_name' =>
                            $customer?->name
                            ?? 'Customer',

                        /*
                        |--------------------------------------------------------------------------
                        | EARNING
                        |--------------------------------------------------------------------------
                        |
                        | Database stores money in minor units.
                        | Flutter receives peso values.
                        |
                        */

                        'earnings' =>
                            (int) $transaction->amount_minor
                            / 100,

                        'earning_type' => $transaction->type,

                        'earning_role' =>
                            data_get($transaction->meta, 'rider_role')
                            ?? ($transaction->type === 'pickup_credit'
                                ? 'pickup'
                                : 'final_delivery'),

                        /*
                        |--------------------------------------------------------------------------
                        | DATES
                        |--------------------------------------------------------------------------
                        */

                        'delivered_at' =>
                            $shipment?->delivered_at
                                ?->toISOString(),

                        'credited_at' =>
                            $transaction->created_at
                                ?->toISOString(),
                    ];
                })
                ->values();

            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'data' => [
                    /*
                    |--------------------------------------------------------------------------
                    | EARNINGS
                    |--------------------------------------------------------------------------
                    */

                    'today_earnings' =>
                        $todayEarningsMinor / 100,

                    'week_earnings' =>
                        $weekEarningsMinor / 100,

                    'month_earnings' =>
                        $monthEarningsMinor / 100,

                    'total_earnings' =>
                        $totalEarningsMinor / 100,

                    'average_earning' =>
                        $averageEarningMinor / 100,

                    /*
                    |--------------------------------------------------------------------------
                    | DELIVERY COUNTS
                    |--------------------------------------------------------------------------
                    */

                    'today_deliveries' =>
                        $todayDeliveries,

                    'week_deliveries' =>
                        $weekDeliveries,

                    'month_deliveries' =>
                        $monthDeliveries,

                    'total_deliveries' =>
                        $totalDeliveries,

                    /*
                    |--------------------------------------------------------------------------
                    | WALLET BALANCE
                    |--------------------------------------------------------------------------
                    */

                    'available_balance' =>
                        (int) $wallet->available_balance_minor
                        / 100,

                    'pending_balance' =>
                        (int) $wallet->pending_balance_minor
                        / 100,

                    /*
                    |--------------------------------------------------------------------------
                    | RECENT DELIVERY EARNINGS
                    |--------------------------------------------------------------------------
                    */

                    'recent_deliveries' =>
                        $recentDeliveries,
                ],
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to load rider earnings.',
            ], 500);
        }
    }
}