<?php

namespace App\Http\Controllers\Logistic;

use App\Http\Controllers\Controller;
use App\Models\CodRemittance;
use App\Models\SellerOrder;
use App\Services\SellerSettlementService;
use App\Services\CodCustodyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CodRemittanceController extends Controller
{
    /**
     * Display COD cash remittances belonging to
     * the authenticated logistics company.
     */
    public function index(Request $request)
    {
        $logistic = $request->user()->ownedLogistic;

        abort_unless($logistic, 403);

        $baseQuery = CodRemittance::query()
            ->where('logistic_id', $logistic->id);

        $stats = [
            'pending_total_minor' => (clone $baseQuery)
                ->where('status', 'pending')
                ->sum('total_minor'),

            'pending_count' => (clone $baseQuery)
                ->where('status', 'pending')
                ->count(),

            'confirmed_total_minor' => (clone $baseQuery)
                ->where('status', 'confirmed')
                ->sum('total_minor'),

            'confirmed_count' => (clone $baseQuery)
                ->where('status', 'confirmed')
                ->count(),

            'rejected_count' => (clone $baseQuery)
                ->where('status', 'rejected')
                ->count(),
        ];

        $remittances = (clone $baseQuery)
            ->with(['rider', 'payments.payment'])
            ->latest()
            ->paginate(20);

        return view('logistic.cod-remittances', [
            'logistic' => $logistic,
            'remittances' => $remittances,
            'stats' => $stats,
        ]);
    }

    /**
     * Confirm physical receipt of COD cash.
     *
     * Settlement processing runs after the
     * confirmation transaction commits.
     */
    public function confirm(
        Request $request,
        CodRemittance $remittance,
        CodCustodyService $custodyService
    ) {
        $logistic = $request->user()->ownedLogistic;

        abort_unless(
            $logistic &&
                (int) $remittance->logistic_id === (int) $logistic->id,
            403
        );

        DB::transaction(function () use (
            $remittance,
            $request,
            $logistic,
            $custodyService
        ) {
            $locked = CodRemittance::query()
                ->whereKey($remittance->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $locked->logistic_id === (int) $logistic->id,
                403
            );

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'remittance' =>
                    'Only pending remittances can be confirmed.',
                ]);
            }

            $locked->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => $request->user()->id,
            ]);

            $custodyService->creditConfirmedRiderRemittance(
                $locked->fresh()
            );

            // Run only after successful database commit.
            DB::afterCommit(function () use ($locked) {
                try {
                    $this->createSettlementsForRemittance(
                        (int) $locked->id
                    );
                } catch (Throwable $exception) {
                    Log::error(
                        'Automatic seller settlement processing failed.',
                        [
                            'cod_remittance_id' => $locked->id,
                            'error' => $exception->getMessage(),
                        ]
                    );
                }
            });
        }, 3);

        return response()->json([
            'message' => 'COD cash receipt confirmed successfully.',
        ]);
    }

    /**
     * Evaluate seller orders associated with
     * the confirmed COD remittance.
     *
     * Does not approve or pay settlements.
     */
    private function createSettlementsForRemittance(
        int $remittanceId
    ): void {
        $remittance = CodRemittance::query()
            ->findOrFail($remittanceId);

        if ($remittance->status !== 'confirmed') {
            return;
        }

        // Find seller orders through their shipment payments.
        $sellerOrderIds = SellerOrder::query()
            ->whereHas('shipment', function ($query) use ($remittanceId) {
                $query->whereIn(
                    'id',
                    DB::table('payments')
                        ->join(
                            'cod_remittance_payments as crp',
                            'crp.payment_id',
                            '=',
                            'payments.id'
                        )
                        ->where(
                            'crp.cod_remittance_id',
                            $remittanceId
                        )
                        ->whereNotNull('payments.shipment_id')
                        ->select('payments.shipment_id')
                );
            })
            ->pluck('id');

        $service = app(SellerSettlementService::class);

        foreach ($sellerOrderIds as $sellerOrderId) {
            try {
                $result = $service->createIfEligible(
                    (int) $sellerOrderId
                );

                Log::info(
                    'Seller settlement eligibility processed.',
                    [
                        'cod_remittance_id' => $remittanceId,
                        'seller_order_id' => $sellerOrderId,
                        'created' => $result['created'],
                        'eligible' => $result['eligible'],
                        'settlement_id' =>
                        $result['settlement_id'],
                        'reasons' => $result['reasons'],
                    ]
                );
            } catch (Throwable $exception) {
                Log::error(
                    'Seller settlement creation failed.',
                    [
                        'cod_remittance_id' => $remittanceId,
                        'seller_order_id' => $sellerOrderId,
                        'error' => $exception->getMessage(),
                    ]
                );
            }
        }
    }

    /**
     * Reject a pending COD remittance.
     */
    public function reject(
        Request $request,
        CodRemittance $remittance
    ) {
        $validated = $request->validate([
            'rejection_reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $logistic = $request->user()->ownedLogistic;

        abort_unless(
            $logistic &&
                (int) $remittance->logistic_id === (int) $logistic->id,
            403
        );

        DB::transaction(function () use (
            $remittance,
            $request,
            $validated,
            $logistic
        ) {
            $locked = CodRemittance::query()
                ->whereKey($remittance->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $locked->logistic_id === (int) $logistic->id,
                403
            );

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'remittance' =>
                    'Only pending remittances can be rejected.',
                ]);
            }

            $locked->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => $request->user()->id,
                'rejection_reason' => $validated['rejection_reason'],
            ]);
        }, 3);

        return response()->json([
            'message' => 'COD remittance rejected.',
        ]);
    }
}
