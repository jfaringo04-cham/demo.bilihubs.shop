<?php

namespace App\Http\Controllers\Api\Logistic;

use App\Http\Controllers\Controller;
use App\Models\LogisticAdminRemittance;
use App\Services\CodCustodyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CodAdminRemittanceController extends Controller
{
    /**
     * Show the authenticated logistics company's
     * COD custody wallet and Admin transfer history.
     *
     * This is NOT the logistics earnings wallet.
     */
    public function index(
        Request $request,
        CodCustodyService $service
    ): JsonResponse {
        $logistic = $request->user()?->ownedLogistic;

        abort_unless($logistic, 403);

        $wallet = $service->walletForLogistic(
            (int) $logistic->id
        );

        $perPage = min(
            max((int) $request->query('per_page', 20), 1),
            50
        );

        $remittances = LogisticAdminRemittance::query()
            ->where('logistic_id', $logistic->id)
            ->latest()
            ->paginate($perPage);

        $transactions = $wallet->transactions()
            ->latest()
            ->take(20)
            ->get();

        $hasPendingTransfer =
            LogisticAdminRemittance::query()
                ->where('logistic_id', $logistic->id)
                ->where('status', 'pending')
                ->exists();

        return response()->json([
            'success' => true,
            'message' =>
                'Logistics COD custody wallet retrieved.',
            'data' => [
                'logistic_id' => (int) $logistic->id,

                'wallet' => [
                    'id' => (int) $wallet->id,

                    'available_balance_minor' =>
                        (int) $wallet->available_balance_minor,

                    'available_balance' =>
                        (int) $wallet->available_balance_minor
                        / 100,

                    'pending_balance_minor' =>
                        (int) $wallet->pending_balance_minor,

                    'pending_balance' =>
                        (int) $wallet->pending_balance_minor
                        / 100,

                    'has_pending_transfer' =>
                        $hasPendingTransfer,
                ],

                'remittances' => $remittances
                    ->getCollection()
                    ->map(function (
                        LogisticAdminRemittance $remittance
                    ) {
                        return [
                            'id' => (int) $remittance->id,
                            'reference' =>
                                $remittance->reference,
                            'amount_minor' =>
                                (int) $remittance->amount_minor,
                            'amount' =>
                                (int) $remittance->amount_minor
                                / 100,
                            'status' => $remittance->status,
                            'transfer_method' =>
                                $remittance->transfer_method,
                            'transfer_reference' =>
                                $remittance->transfer_reference,
                            'notes' => $remittance->notes,
                            'rejection_reason' =>
                                $remittance->rejection_reason,
                            'submitted_at' =>
                                $remittance->submitted_at
                                    ?->toISOString(),
                            'confirmed_at' =>
                                $remittance->confirmed_at
                                    ?->toISOString(),
                            'rejected_at' =>
                                $remittance->rejected_at
                                    ?->toISOString(),
                        ];
                    })
                    ->values(),

                'transactions' => $transactions
                    ->map(function ($transaction) {
                        return [
                            'id' => (int) $transaction->id,
                            'type' => $transaction->type,
                            'reference' =>
                                $transaction->reference,
                            'description' =>
                                $transaction->description,
                            'amount_minor' =>
                                (int) $transaction->amount_minor,
                            'amount' =>
                                (int) $transaction->amount_minor
                                / 100,
                            'balance_after_minor' =>
                                (int) $transaction
                                    ->balance_after_minor,
                            'created_at' =>
                                $transaction->created_at
                                    ?->toISOString(),
                        ];
                    })
                    ->values(),

                'pagination' => [
                    'current_page' =>
                        $remittances->currentPage(),
                    'last_page' =>
                        $remittances->lastPage(),
                    'per_page' =>
                        $remittances->perPage(),
                    'total' =>
                        $remittances->total(),
                ],
            ],
        ]);
    }

    /**
     * Submit a COD cash transfer to Admin.
     *
     * Reuses the same CodCustodyService
     * called by the Logistics Web controller.
     */
    public function store(
        Request $request,
        CodCustodyService $service
    ): JsonResponse {
        $logistic = $request->user()?->ownedLogistic;

        abort_unless($logistic, 403);

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'decimal:0,2',
            ],

            'transfer_method' => [
                'required',
                'in:cash,bank_transfer,gcash,maya,other',
            ],

            'transfer_reference' => [
                'nullable',
                'string',
                'max:150',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $amountMinor = (int) round(
            ((float) $validated['amount']) * 100
        );

        $remittance = $service->submitToAdmin(
            (int) $logistic->id,
            (int) $request->user()->id,
            $amountMinor,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' =>
                'COD transfer submitted to Admin for confirmation.',
            'data' => [
                'id' => (int) $remittance->id,
                'reference' => $remittance->reference,
                'amount_minor' =>
                    (int) $remittance->amount_minor,
                'amount' =>
                    (int) $remittance->amount_minor / 100,
                'status' => $remittance->status,
                'transfer_method' =>
                    $remittance->transfer_method,
                'submitted_at' =>
                    $remittance->submitted_at
                        ?->toISOString(),
            ],
        ], 201);
    }
}
