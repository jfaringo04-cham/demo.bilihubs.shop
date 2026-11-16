<?php

namespace App\Http\Controllers\Api\Logistic;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Logistic\CodRemittanceController as WebCodRemittanceController;
use App\Models\CodRemittance;
use App\Models\LogisticCodWalletTransaction;
use App\Services\CodCustodyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CodRemittanceController extends Controller
{
    /**
     * ============================================================
     * RIDER TO LOGISTICS COD REMITTANCES
     * GET /api/logistic/cod-remittances
     * ============================================================
     */
    public function index(Request $request): JsonResponse
    {
        $logistic = $request->user()?->ownedLogistic;

        abort_unless($logistic, 403);

        $baseQuery = CodRemittance::query()
            ->where('logistic_id', $logistic->id);

        $stats = [
            'pending_total_minor' => (int) (clone $baseQuery)
                ->where('status', 'pending')
                ->sum('total_minor'),

            'pending_count' => (clone $baseQuery)
                ->where('status', 'pending')
                ->count(),

            'confirmed_total_minor' => (int) (clone $baseQuery)
                ->where('status', 'confirmed')
                ->sum('total_minor'),

            'confirmed_count' => (clone $baseQuery)
                ->where('status', 'confirmed')
                ->count(),

            'rejected_count' => (clone $baseQuery)
                ->where('status', 'rejected')
                ->count(),
        ];

        $perPage = min(
            max((int) $request->query('per_page', 20), 1),
            50
        );

        $remittances = (clone $baseQuery)
            ->with(['rider', 'payments.payment'])
            ->latest()
            ->paginate($perPage);

        $items = $remittances
            ->getCollection()
            ->map(function (CodRemittance $remittance) {
                $totalMinor = (int) $remittance->total_minor;

                $payments = $remittance->payments
                    ->map(function ($item) {
                        $amountMinor = (int) $item->amount_minor;

                        return [
                            'id' => (int) $item->id,
                            'payment_id' => (int) $item->payment_id,

                            'amount_minor' => $amountMinor,
                            'amount' => $amountMinor / 100,

                            'shipment_id' =>
                                $item->payment?->shipment_id,
                        ];
                    })
                    ->values();

                return [
                    'id' => (int) $remittance->id,
                    'reference' => $remittance->reference,

                    'rider_user_id' =>
                        (int) $remittance->rider_user_id,

                    'rider_name' =>
                        $remittance->rider?->name,

                    'logistic_id' =>
                        (int) $remittance->logistic_id,

                    'total_minor' => $totalMinor,
                    'amount_minor' => $totalMinor,

                    'total' => $totalMinor / 100,
                    'amount' => $totalMinor / 100,

                    'payments_count' => $payments->count(),

                    'status' => $remittance->status,
                    'notes' => $remittance->notes,

                    'rejection_reason' =>
                        $remittance->rejection_reason,

                    'submitted_at' =>
                        $remittance->submitted_at?->toISOString(),

                    'confirmed_at' =>
                        $remittance->confirmed_at?->toISOString(),

                    'rejected_at' =>
                        $remittance->rejected_at?->toISOString(),

                    'payments' => $payments,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'COD remittances retrieved successfully.',

            'data' => [
                'stats' => $stats,
                'remittances' => $items,

                'pagination' => [
                    'current_page' => $remittances->currentPage(),
                    'last_page' => $remittances->lastPage(),
                    'per_page' => $remittances->perPage(),
                    'total' => $remittances->total(),
                ],
            ],
        ]);
    }

    /**
     * ============================================================
     * CONFIRM RIDER COD REMITTANCE
     * POST /api/logistic/cod-remittances/{remittance}/confirm
     * ============================================================
     *
     * The actual confirmation and custody credit are
     * still handled by the existing Web controller.
     *
     * The API verifies:
     * 1. Logistics ownership
     * 2. Web controller response
     * 3. Updated remittance status
     * 4. COD custody credit transaction
     */
    public function confirm(
        Request $request,
        CodRemittance $remittance,
        CodCustodyService $custodyService
    ): JsonResponse {
        $logistic = $request->user()?->ownedLogistic;

        abort_unless(
            $logistic &&
            (int) $remittance->logistic_id === (int) $logistic->id,
            403
        );

        // Only a pending remittance may be confirmed.
        $remittance->refresh();

        if ($remittance->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Only pending COD remittances can be confirmed.',
                'data' => [
                    'remittance_id' => (int) $remittance->id,
                    'status' => $remittance->status,
                ],
            ], 422);
        }

        // ========================================================
        // USE EXISTING WEB CONFIRMATION LOGIC
        // ========================================================

        $response = app(WebCodRemittanceController::class)
            ->confirm(
                $request,
                $remittance,
                $custodyService
            );

        $statusCode = $response->getStatusCode();

        // Do not convert Web errors into a successful API response.
        if ($statusCode < 200 || $statusCode >= 400) {
            return response()->json([
                'success' => false,
                'message' =>
                    'COD confirmation was not completed. '
                    . 'Please refresh the remittance status.',
            ], $statusCode >= 400 ? $statusCode : 409);
        }

        // ========================================================
        // VERIFY ACTUAL DATABASE RESULT
        // ========================================================

        $remittance->refresh();

        if ($remittance->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' =>
                    'COD confirmation could not be verified. '
                    . 'Please refresh before trying again.',
                'data' => [
                    'remittance_id' => (int) $remittance->id,
                    'status' => $remittance->status,
                ],
            ], 409);
        }

        // ========================================================
        // VERIFY LOGISTICS COD CUSTODY CREDIT
        // ========================================================

        $credit = LogisticCodWalletTransaction::query()
            ->where('cod_remittance_id', $remittance->id)
            ->where('type', CodCustodyService::LOGISTIC_CREDIT)
            ->first();

        if (!$credit) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Remittance is confirmed, but its COD custody '
                    . 'credit record could not be verified. '
                    . 'Do not confirm again. Please contact Admin.',
                'data' => [
                    'remittance_id' => (int) $remittance->id,
                    'status' => $remittance->status,
                ],
            ], 409);
        }

        // ========================================================
        // SUCCESS
        // ========================================================

        return response()->json([
            'success' => true,
            'message' =>
                'COD cash receipt confirmed successfully.',

            'data' => [
                'id' => (int) $remittance->id,
                'reference' => $remittance->reference,

                'rider_user_id' =>
                    (int) $remittance->rider_user_id,

                'logistic_id' =>
                    (int) $remittance->logistic_id,

                'amount_minor' =>
                    (int) $remittance->total_minor,

                'amount' =>
                    (int) $remittance->total_minor / 100,

                'status' => $remittance->status,

                'confirmed_at' =>
                    $remittance->confirmed_at?->toISOString(),

                'custody_transaction_id' => (int) $credit->id,
            ],
        ]);
    }

    /**
     * ============================================================
     * REJECT RIDER COD REMITTANCE
     * POST /api/logistic/cod-remittances/{remittance}/reject
     * ============================================================
     *
     * Delegates to the Web controller, then verifies
     * the actual remittance status in the database.
     */
    public function reject(
        Request $request,
        CodRemittance $remittance
    ): JsonResponse {
        $logistic = $request->user()?->ownedLogistic;

        abort_unless(
            $logistic &&
            (int) $remittance->logistic_id === (int) $logistic->id,
            403
        );

        $remittance->refresh();

        if ($remittance->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Only pending COD remittances can be rejected.',
                'data' => [
                    'remittance_id' => (int) $remittance->id,
                    'status' => $remittance->status,
                ],
            ], 422);
        }

        // ========================================================
        // VALIDATE REJECTION REASON
        // ========================================================

        $reason = trim((string) (
            $request->input('rejection_reason')
            ?? $request->input('reason')
            ?? ''
        ));

        if ($reason === '') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Please provide a reason for rejecting the COD remittance.',
                'errors' => [
                    'rejection_reason' => [
                        'Rejection reason is required.',
                    ],
                ],
            ], 422);
        }

        if (mb_strlen($reason) > 500) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Rejection reason must not exceed 500 characters.',
            ], 422);
        }

        // Support either rejection_reason or reason
        // when calling the existing Web controller.
        $request->merge([
            'rejection_reason' => $reason,
            'reason' => $reason,
        ]);

        // ========================================================
        // USE EXISTING WEB REJECTION LOGIC
        // ========================================================

        $response = app(WebCodRemittanceController::class)
            ->reject($request, $remittance);

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 400) {
            return response()->json([
                'success' => false,
                'message' =>
                    'COD rejection was not completed. '
                    . 'Please refresh the remittance status.',
            ], $statusCode >= 400 ? $statusCode : 409);
        }

        // ========================================================
        // VERIFY ACTUAL DATABASE RESULT
        // ========================================================

        $remittance->refresh();

        if ($remittance->status !== 'rejected') {
            return response()->json([
                'success' => false,
                'message' =>
                    'COD rejection could not be verified. '
                    . 'Please refresh before trying again.',
                'data' => [
                    'remittance_id' => (int) $remittance->id,
                    'status' => $remittance->status,
                ],
            ], 409);
        }

        // ========================================================
        // SUCCESS
        // ========================================================

        return response()->json([
            'success' => true,
            'message' => 'COD remittance rejected successfully.',

            'data' => [
                'id' => (int) $remittance->id,

                'reference' => $remittance->reference,

                'status' => $remittance->status,

                'rejection_reason' =>
                    $remittance->rejection_reason,

                'rejected_at' =>
                    $remittance->rejected_at?->toISOString(),
            ],
        ]);
    }
}
