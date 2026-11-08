<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SellerOrder;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CodCollectionController extends Controller
{
    public function show(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        $rider = $request->user();

        abort_unless($rider && $rider->isRider(), 403);

        abort_unless(
            (int) $shipment->rider_id === (int) $rider->id,
            403,
            'Shipment is not assigned to you.'
        );

        $sellerOrder = SellerOrder::query()
            ->findOrFail($shipment->seller_order_id);

        $order = Order::query()
            ->findOrFail($sellerOrder->order_id);

        $payment = Payment::query()
            ->where('shipment_id', $shipment->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'payment_method' => $order->payment_method,
                'is_cod' => $order->payment_method === 'cod',
                'shipment_status' => $shipment->status,
                'amount_minor' => (int) $sellerOrder->total_minor,
                'amount' => number_format(
                    (int) $sellerOrder->total_minor / 100,
                    2,
                    '.',
                    ''
                ),
                'cod_collected' => $payment !== null
                    && $payment->method === 'cod'
                    && $payment->status === 'paid'
                    && (int) $payment->amount_minor
                        === (int) $sellerOrder->total_minor,
                'payment_id' => $payment?->id,
                'collected_at' => $payment?->paid_at,
            ],
        ]);
    }

    public function collect(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        $rider = $request->user();

        abort_unless($rider && $rider->isRider(), 403);

        $validated = $request->validate([
            'amount_collected' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
        ]);

        // Convert pesos to centavos without floats.
        $amount = (string) $validated['amount_collected'];

        [$pesos, $centavos] = array_pad(
            explode('.', $amount, 2),
            2,
            '0'
        );

        $amountMinor = ((int) $pesos * 100)
            + (int) str_pad($centavos, 2, '0');

        $result = DB::transaction(function () use (
            $shipment,
            $rider,
            $amountMinor
        ) {
            $lockedShipment = Shipment::query()
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $lockedShipment->rider_id
                    === (int) $rider->id,
                403,
                'Shipment is not assigned to you.'
            );

            if (
                $lockedShipment->status !== 'out_for_delivery'
                || $lockedShipment->hub_picked_up_at === null
                || (int) ($lockedShipment->hub_pickup_rider_id ?? 0)
                    !== (int) $rider->id
            ) {
                throw ValidationException::withMessages([
                    'shipment' =>
                        'Complete the hub QR pickup and start delivery before collecting COD.',
                ]);
            }

            $sellerOrder = SellerOrder::query()
                ->whereKey($lockedShipment->seller_order_id)
                ->firstOrFail();

            $order = Order::query()
                ->whereKey($sellerOrder->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->payment_method !== 'cod') {
                throw ValidationException::withMessages([
                    'payment_method' =>
                        'This shipment is not Cash on Delivery.',
                ]);
            }

            $existingPayment = Payment::query()
                ->where('shipment_id', $lockedShipment->id)
                ->first();

            if ($existingPayment) {
                throw ValidationException::withMessages([
                    'shipment' =>
                        'A payment is already recorded for this shipment.',
                ]);
            }

            if (
                $order->payment_status === 'paid'
                && (int) $order->amount_collected_minor
                    === (int) $order->total_minor
            ) {
                throw ValidationException::withMessages([
                    'payment' =>
                        'This order has already been fully paid.',
                ]);
            }

            if (!in_array(
                $order->payment_status,
                ['unpaid', 'partially_paid'],
                true
            )) {
                throw ValidationException::withMessages([
                    'payment_status' =>
                        'Order is not eligible for COD collection.',
                ]);
            }

            $expectedMinor = (int) $sellerOrder->total_minor;

            if (
                $expectedMinor <= 0
                || $amountMinor !== $expectedMinor
            ) {
                throw ValidationException::withMessages([
                    'amount_collected' =>
                        'Collected amount must match the exact shipment total.',
                ]);
            }

            $alreadyCollectedMinor =
                (int) ($order->amount_collected_minor ?? 0);

            $newCollectedMinor =
                $alreadyCollectedMinor + $amountMinor;

            if (
                $newCollectedMinor > (int) $order->total_minor
            ) {
                throw ValidationException::withMessages([
                    'amount_collected' =>
                        'Collection exceeds the parent order total.',
                ]);
            }

            $collectedAt = now();

            $payment = Payment::create([
                'order_id' => $order->id,
                'shipment_id' => $lockedShipment->id,
                'collected_by' => $rider->id,
                'method' => 'cod',
                'status' => 'paid',
                'amount_minor' => $amountMinor,
                'currency' => 'PHP',
                'paid_at' => $collectedAt,
            ]);

            // Recognize the stored Platform Service Fee after
            // the COD payment has been successfully recorded.
            app(\App\Services\PlatformRevenueService::class)
                ->recognizeCodPayment($payment);

            $fullyPaid =
                $newCollectedMinor === (int) $order->total_minor;

            // Preserve the existing web accounting convention:
            // rider attribution belongs to payments.collected_by.
            $order->update([
                'amount_collected_minor' => $newCollectedMinor,
                'payment_status' => $fullyPaid
                    ? 'paid'
                    : ($newCollectedMinor > 0 ? 'partially_paid' : 'unpaid'),
                'collected_at' => $fullyPaid
                    ? $collectedAt
                    : null,
                'collected_by' => null,
            ]);

            return [
                'payment_id' => $payment->id,
                'shipment_id' => $lockedShipment->id,
                'order_id' => $order->id,
                'amount_minor' => $amountMinor,
                'amount' => number_format(
                    $amountMinor / 100,
                    2,
                    '.',
                    ''
                ),
                'collected_by' => $rider->id,
                'collected_at' => $collectedAt->toISOString(),
                'cod_collected' => true,
            ];
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Shipment COD collected successfully.',
            'data' => $result,
        ]);
    }
}
