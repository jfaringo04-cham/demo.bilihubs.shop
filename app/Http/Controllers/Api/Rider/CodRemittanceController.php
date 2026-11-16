<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Rider\CodRemittanceController as WebCodRemittanceController;
use App\Models\CodRemittance;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CodRemittanceController extends Controller
{
    /**
     * ============================================================
     * RIDER COD REMITTANCE - MOBILE DASHBOARD
     * GET /api/rider/cod-remittances
     * ============================================================
     *
     * Uses the same eligibility rules as the Web controller.
     *
     * Only payments that are:
     * - Collected by the authenticated Rider
     * - COD and paid
     * - Attached to a delivered shipment
     * - Assigned to that Rider
     * - Attached to a Logistics company
     * - Not pending or confirmed in another remittance
     *
     * are returned.
     */
    public function index(Request $request): JsonResponse
    {
        $rider = $request->user();

        abort_unless($rider, 401);

        $riderId = (int) $rider->id;

        // ========================================================
        // ELIGIBLE COD PAYMENTS
        // SAME CONDITIONS AS WEB
        // ========================================================

        $eligiblePayments = Payment::query()
            ->with('shipment')
            ->where('collected_by', $riderId)
            ->where('method', 'cod')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereNotNull('shipment_id')
            ->whereHas('shipment', function ($query) use ($riderId) {
                $query
                    ->where('rider_id', $riderId)
                    ->where('status', 'delivered')
                    ->whereNotNull('logistic_id');
            })
            ->whereDoesntHave('remittanceItems', function ($query) {
                $query->whereHas(
                    'remittance',
                    function ($remittanceQuery) {
                        $remittanceQuery->whereIn(
                            'status',
                            ['pending', 'confirmed']
                        );
                    }
                );
            })
            ->orderBy('id')
            ->get();

        // ========================================================
        // FORMAT ELIGIBLE PAYMENTS FOR FLUTTER
        // ========================================================

        $payments = $eligiblePayments
            ->map(function (Payment $payment) {
                $shipment = $payment->shipment;

                $amountMinor = (int) $payment->amount_minor;

                return [
                    'id' => (int) $payment->id,

                    'payment_id' => (int) $payment->id,

                    'shipment_id' => (int) $payment->shipment_id,

                    'logistic_id' => (int) $shipment->logistic_id,

                    'tracking_number' =>
                        $shipment->tracking_number
                            ?? $shipment->tracking_code
                            ?? null,

                    'payment_reference' =>
                        'Payment #' . $payment->id,

                    'amount_minor' => $amountMinor,

                    // Pesos, preserving centavos.
                    'amount' => $amountMinor / 100,

                    'method' => $payment->method,

                    'status' => $payment->status,

                    'paid_at' => $payment->paid_at
                        ?->toISOString(),

                    'collected_at' => $payment->paid_at
                        ?->toISOString(),
                ];
            })
            ->values();

        // ========================================================
        // RIDER REMITTANCE HISTORY
        // ========================================================

        $historyQuery = CodRemittance::query()
            ->where('rider_user_id', $riderId);

        $perPage = min(
            max(
                (int) $request->query('per_page', 15),
                1
            ),
            50
        );

        $history = (clone $historyQuery)
            ->with('payments')
            ->latest()
            ->paginate($perPage);

        $remittances = $history
            ->getCollection()
            ->map(function (CodRemittance $remittance) {
                $totalMinor = (int) $remittance->total_minor;

                $paymentItems = $remittance->payments
                    ->map(function ($item) {
                        $amountMinor = (int) $item->amount_minor;

                        return [
                            'payment_id' => (int) $item->payment_id,

                            'amount_minor' => $amountMinor,

                            'amount' => $amountMinor / 100,
                        ];
                    })
                    ->values();

                return [
                    'id' => (int) $remittance->id,

                    'reference' => $remittance->reference,

                    'logistic_id' => (int) $remittance->logistic_id,

                    'total_minor' => $totalMinor,

                    'amount_minor' => $totalMinor,

                    'total' => $totalMinor / 100,

                    'amount' => $totalMinor / 100,

                    'payments_count' => $paymentItems->count(),

                    'status' => $remittance->status,

                    'notes' => $remittance->notes,

                    'rejection_reason' =>
                        $remittance->rejection_reason,

                    'submitted_at' => $remittance->submitted_at
                        ?->toISOString(),

                    'confirmed_at' => $remittance->confirmed_at
                        ?->toISOString(),

                    'rejected_at' => $remittance->rejected_at
                        ?->toISOString(),

                    'payments' => $paymentItems,
                ];
            })
            ->values();

        // ========================================================
        // SUMMARY
        // ========================================================

        $eligibleTotalMinor = (int) $eligiblePayments
            ->sum('amount_minor');

        $pendingCount = (clone $historyQuery)
            ->where('status', 'pending')
            ->count();

        $confirmedCount = (clone $historyQuery)
            ->where('status', 'confirmed')
            ->count();

        $rejectedCount = (clone $historyQuery)
            ->where('status', 'rejected')
            ->count();

        // ========================================================
        // JSON RESPONSE FOR FLUTTER
        // ========================================================

        return response()->json([
            'success' => true,

            'message' => 'Rider COD remittances retrieved successfully.',

            'data' => [
                'eligible_payments' => $payments,

                'summary' => [
                    'eligible_count' => $eligiblePayments->count(),

                    'eligible_total_minor' => $eligibleTotalMinor,

                    'eligible_total' => $eligibleTotalMinor / 100,

                    'pending_count' => $pendingCount,

                    'confirmed_count' => $confirmedCount,

                    'rejected_count' => $rejectedCount,
                ],

                'remittances' => $remittances,

                'pagination' => [
                    'current_page' => $history->currentPage(),

                    'last_page' => $history->lastPage(),

                    'per_page' => $history->perPage(),

                    'total' => $history->total(),
                ],
            ],
        ]);
    }

    /**
     * ============================================================
     * RIDER -> LOGISTICS COD REMITTANCE
     * POST /api/rider/cod-remittances
     * ============================================================
     *
     * Reuse the actual Web submission implementation.
     *
     * This preserves:
     * - Rider ownership checks
     * - Same Logistics validation
     * - COD collection eligibility
     * - Row locking
     * - Duplicate prevention
     * - Database transactions
     * - Remittance reference generation
     *
     * This API controller only normalizes the response
     * to the JSON format expected by Flutter.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user(), 401);

        // Use the SAME business logic as Laravel Web.
        $response = app(
            WebCodRemittanceController::class
        )->store($request);

        // The existing Web controller returns JsonResponse.
        // Do not report success for any unexpected response.
        if (!($response instanceof JsonResponse)) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to verify the remittance response. '
                    . 'Please refresh your remittance history.',
            ], 500);
        }

        $statusCode = $response->getStatusCode();

        $payload = $response->getData(true);

        if (!is_array($payload)) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Invalid remittance response. '
                    . 'Please refresh your remittance history.',
            ], 500);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return $response;
        }

        // Ensure the Web controller really returned
        // a persisted remittance before reporting success.
        $remittance = $payload['remittance'] ?? null;

        if (
            !is_array($remittance) ||
            empty($remittance['id'])
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Remittance submission could not be verified. '
                    . 'Please refresh your history before retrying.',
            ], 500);
        }

        // Keep the original Web response structure and
        // make it compatible with the Flutter API client.
        return response()->json([
            'success' => true,

            'message' =>
                $payload['message']
                    ?? 'COD remittance submitted for Logistics confirmation.',

            'remittance' => $remittance,

            'data' => [
                'remittance' => $remittance,
            ],
        ], $statusCode);
    }
}
