<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\CodRemittance;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CodRemittanceController extends Controller
{
    public function index(Request $request)
    {
        $riderId = $request->user()->id;

        $availablePayments = Payment::query()
            ->with('shipment:id,logistic_id,rider_id,status')
            ->where('collected_by', $riderId)
            ->where('method', 'cod')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereNotNull('shipment_id')
            ->whereHas('shipment', function ($query) use ($riderId) {
                $query->where('rider_id', $riderId)
                    ->where('status', 'delivered')
                    ->whereNotNull('logistic_id');
            })
            ->whereDoesntHave('remittanceItems', function ($query) {
                $query->whereHas('remittance', function ($remittanceQuery) {
                    $remittanceQuery->whereIn('status', [
                        'pending',
                        'confirmed',
                    ]);
                });
            })
            ->orderBy('id')
            ->get();

        $deadlineStatuses = $availablePayments->mapWithKeys(
            fn (Payment $payment) => [
                $payment->id =>
                    \App\Services\CodRemittanceDeadlineService::forUnsubmitted(
                        $payment
                    ),
            ]
        );

        $overduePayments = $availablePayments->filter(
            fn (Payment $payment) =>
                $deadlineStatuses[$payment->id]['overdue']
        );

        $overdueCount = $overduePayments->count();

        $overdueTotalMinor = (int) $overduePayments
            ->sum('amount_minor');

        $remittances = CodRemittance::query()
            ->with('payments')
            ->where('rider_user_id', $riderId)
            ->latest()
            ->paginate(15);

        // Temporary JSON response while the Blade UI is being built.
        return view('rider.cod-remittances', [
            'availablePayments' => $availablePayments,
            'deadlineStatuses' => $deadlineStatuses,
            'overdueCount' => $overdueCount,
            'overdueTotalMinor' => $overdueTotalMinor,
            'remittances' => $remittances,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_ids' => ['required', 'array', 'min:1', 'max:100'],
            'payment_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:payments,id',
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $riderId = $request->user()->id;

        $paymentIds = collect($validated['payment_ids'])
            ->map(fn($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $remittance = DB::transaction(function () use (
            $paymentIds,
            $riderId,
            $validated
        ) {
            // Lock payment rows in a consistent order.
            // Concurrent submissions for the same payments must wait.
            $payments = Payment::query()
                ->whereIn('id', $paymentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($payments->count() !== count($paymentIds)) {
                throw ValidationException::withMessages([
                    'payment_ids' => 'Some payments were not found.',
                ]);
            }

            $logisticId = null;
            $totalMinor = 0;

            foreach ($payments as $payment) {
                $shipment = $payment->shipment;

                if (
                    $payment->collected_by != $riderId ||
                    $payment->method !== 'cod' ||
                    $payment->status !== 'paid' ||
                    !$payment->paid_at ||
                    !$shipment ||
                    $shipment->rider_id != $riderId ||
                    $shipment->status !== 'delivered' ||
                    !$shipment->logistic_id ||
                    (int) $payment->amount_minor <= 0
                ) {
                    throw ValidationException::withMessages([
                        'payment_ids' =>
                        'One or more payments are not eligible for remittance.',
                    ]);
                }

                if ($logisticId === null) {
                    $logisticId = (int) $shipment->logistic_id;
                }

                if ((int) $shipment->logistic_id !== $logisticId) {
                    throw ValidationException::withMessages([
                        'payment_ids' =>
                        'Payments must belong to the same logistics company.',
                    ]);
                }

                $alreadySubmitted = DB::table('cod_remittance_payments')
                    ->join(
                        'cod_remittances',
                        'cod_remittances.id',
                        '=',
                        'cod_remittance_payments.cod_remittance_id'
                    )
                    ->where('cod_remittance_payments.payment_id', $payment->id)
                    ->whereIn('cod_remittances.status', [
                        'pending',
                        'confirmed',
                    ])
                    ->exists();

                if ($alreadySubmitted) {
                    throw ValidationException::withMessages([
                        'payment_ids' =>
                        'A payment is already pending or confirmed in another remittance.',
                    ]);
                }

                $totalMinor += (int) $payment->amount_minor;
            }

            $remittance = CodRemittance::create([
                'reference' => 'COD-' . strtoupper((string) Str::ulid()),
                'rider_user_id' => $riderId,
                'logistic_id' => $logisticId,
                'total_minor' => $totalMinor,
                'status' => 'pending',
                'submitted_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($payments as $payment) {
                $remittance->payments()->create([
                    'payment_id' => $payment->id,
                    'amount_minor' => (int) $payment->amount_minor,
                ]);
            }

            return $remittance;
        }, 3);

        return response()->json([
            'message' => 'COD remittance submitted for logistics confirmation.',
            'remittance' => $remittance->load('payments'),
        ], 201);
    }
}
