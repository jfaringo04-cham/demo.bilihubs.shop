<?php

namespace App\Http\Controllers\Api\Logistic;

use App\Http\Controllers\Controller;
use App\Services\LogisticWalletService;
use App\Services\ReturnShippingSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(
        Request $request,
        LogisticWalletService $walletService
    ): JsonResponse {
        $logistic = $request->user()?->ownedLogistic;

        if (!$logistic) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized logistics account.',
            ], 403);
        }

        // Use exactly the same wallet as Logistics Web.
        $wallet = $walletService->walletForLogistic(
            (int) $logistic->id
        );

        // Same earning transaction types as the Web Wallet.
        $earningTypes = [
            LogisticWalletService::TYPE_SHIPMENT_CREDIT,
            ReturnShippingSettlementService::CREDIT_TYPE,
        ];

        $totalEarnedMinor = (int) $wallet->transactions()
            ->whereIn('type', $earningTypes)
            ->sum('amount_minor');

        $todayEarnedMinor = (int) $wallet->transactions()
            ->whereIn('type', $earningTypes)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('amount_minor');

        $totalWithdrawnMinor = (int) $wallet->withdrawals()
            ->where('status', 'paid')
            ->sum('amount_minor');

        $hasPendingWithdrawal = $wallet->withdrawals()
            ->where('status', 'pending')
            ->exists();

        $minimumWithdrawalMinor = (int) config(
            'logistic_wallet.minimum_withdrawal_minor',
            10000
        );

        $shipmentCreditMinor = (int) config(
            'rider_wallet.logistics_earning_minor',
            1000
        );

        $perPage = min(
            max((int) $request->query('per_page', 20), 1),
            50
        );

        $transactions = $wallet->transactions()
            ->with('shipment')
            ->latest()
            ->paginate($perPage);

        $withdrawals = $wallet->withdrawals()
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Logistics wallet retrieved successfully.',
            'data' => [
                'logistic_id' => (int) $logistic->id,

                'wallet' => [
                    'id' => (int) $wallet->id,

                    'available_balance_minor' =>
                        (int) $wallet->available_balance_minor,

                    'available_balance' =>
                        (int) $wallet->available_balance_minor / 100,

                    'total_earned_minor' => $totalEarnedMinor,
                    'total_earned' => $totalEarnedMinor / 100,

                    'today_earned_minor' => $todayEarnedMinor,
                    'today_earned' => $todayEarnedMinor / 100,

                    'total_withdrawn_minor' => $totalWithdrawnMinor,
                    'total_withdrawn' => $totalWithdrawnMinor / 100,

                    'has_pending_withdrawal' =>
                        $hasPendingWithdrawal,

                    'minimum_withdrawal_minor' =>
                        $minimumWithdrawalMinor,

                    'minimum_withdrawal' =>
                        $minimumWithdrawalMinor / 100,

                    'shipment_credit_minor' =>
                        $shipmentCreditMinor,

                    'shipment_credit' =>
                        $shipmentCreditMinor / 100,
                ],

                'transactions' => $transactions->getCollection()
                    ->map(function ($transaction) {
                        return [
                            'id' => (int) $transaction->id,
                            'shipment_id' => $transaction->shipment_id,
                            'tracking_number' =>
                                $transaction->shipment?->tracking_number,
                            'type' => $transaction->type,

                            'amount_minor' =>
                                (int) $transaction->amount_minor,

                            'amount' =>
                                (int) $transaction->amount_minor / 100,

                            'balance_after_minor' =>
                                (int) $transaction->balance_after_minor,

                            'balance_after' =>
                                (int) $transaction->balance_after_minor / 100,

                            'reference' => $transaction->reference,
                            'description' => $transaction->description,
                            'created_at' =>
                                $transaction->created_at?->toISOString(),
                        ];
                    })
                    ->values(),

                'withdrawals' => $withdrawals
                    ->map(function ($withdrawal) {
                        return [
                            'id' => (int) $withdrawal->id,
                            'amount_minor' =>
                                (int) $withdrawal->amount_minor,
                            'amount' =>
                                (int) $withdrawal->amount_minor / 100,
                            'status' => $withdrawal->status,
                            'payment_method' =>
                                $withdrawal->payment_method,
                            'created_at' =>
                                $withdrawal->created_at?->toISOString(),
                        ];
                    })
                    ->values(),

                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ],
        ]);
    }
}
