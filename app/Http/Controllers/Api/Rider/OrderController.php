<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Get all shipments assigned to the authenticated rider.
     *
     * GET /api/rider/orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $rider = $request->user();

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
            | ASSIGNED SHIPMENTS
            |--------------------------------------------------------------------------
            |
            | rider_id:
            |   Final-mile delivery rider.
            |
            | pickup_rider_id:
            |   Seller pickup rider.
            |
            | Do NOT use whereHas('sellerOrder.order') here.
            | A shipment should still appear to the assigned rider even if some
            | related order information is temporarily missing.
            |
            */

            $shipments = Shipment::query()
                ->where(function ($query) use ($rider) {
                    $query
                        ->where('rider_id', $rider->id)
                        ->orWhere('pickup_rider_id', $rider->id);
                })
                ->with([
                    'sellerOrder.order.user',
                    'sellerOrder.seller',
                    'logistic',
                    'hub',
                    'rider',
                ])
                ->latest('updated_at')
                ->get();

            $data = $shipments
                ->map(function (Shipment $shipment) use ($rider) {
                    return $this->formatShipment(
                        shipment: $shipment,
                        rider: $rider,
                    );
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'total' => $data->count(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load rider deliveries.',
            ], 500);
        }
    }

    /**
     * Get one shipment assigned to the authenticated rider.
     *
     * GET /api/rider/orders/{shipment}
     */
    public function show(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        try {
            $rider = $request->user();

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

            if (!$this->isShipmentAssignedToRider(
                shipment: $shipment,
                riderId: (int) $rider->id,
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'This shipment is not assigned to you.',
                ], 403);
            }

            $shipment->load([
                'sellerOrder.order.user',
                'sellerOrder.seller',
                'logistic',
                'hub',
                'rider',
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->formatShipment(
                    shipment: $shipment,
                    rider: $rider,
                ),
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load rider delivery.',
            ], 500);
        }
    }

    /**
     * Upload proof of delivery.
     *
     * POST /api/rider/orders/{shipment}/proof
     */
    public function uploadProof(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        try {
            $rider = $request->user();

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
            | FINAL-MILE OWNERSHIP ONLY
            |--------------------------------------------------------------------------
            |
            | A seller pickup rider must not upload final proof of delivery.
            |
            */

            if (!$this->isFinalMileRider(
                shipment: $shipment,
                riderId: (int) $rider->id,
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'This shipment is not assigned to you for final delivery.',
                ], 403);
            }

            $validated = $request->validate([
                'proof_of_delivery' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png',
                    'max:5120',
                ],

                'delivery_signature' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'delivered_to' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'delivery_notes' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ]);

            $proofPath = $request
                ->file('proof_of_delivery')
                ->store(
                    'proof-of-delivery',
                    'supabase_private'
                );

            if (!$proofPath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proof of delivery upload failed.',
                ], 500);
            }

            $shipment->proof_of_delivery = $proofPath;

            if (array_key_exists(
                'delivery_signature',
                $validated
            )) {
                $shipment->delivery_signature =
                    $validated['delivery_signature'];
            }

            if (array_key_exists(
                'delivered_to',
                $validated
            )) {
                $shipment->delivered_to =
                    $validated['delivered_to'];
            }

            if (array_key_exists(
                'delivery_notes',
                $validated
            )) {
                $shipment->notes =
                    $validated['delivery_notes'];
            }

            $shipment->save();
            $shipment->refresh();

            $shipment->load([
                'sellerOrder.order.user',
                'sellerOrder.seller',
                'logistic',
                'hub',
                'rider',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery proof uploaded successfully.',
                'data' => $this->formatShipment(
                    shipment: $shipment,
                    rider: $rider,
                ),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to upload delivery proof.',
            ], 500);
        }
    }

    /**
     * Update delivery status.
     *
     * PUT /api/rider/orders/{shipment}/status
     */
    public function updateStatus(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        try {
            $rider = $request->user();

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
            | FINAL-MILE OWNERSHIP ONLY
            |--------------------------------------------------------------------------
            */

            if (!$this->isFinalMileRider(
                shipment: $shipment,
                riderId: (int) $rider->id,
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'This shipment is not assigned to you for final delivery.',
                ], 403);
            }

            $validated = $request->validate([
                'status' => [
                    'required',
                    'string',
                    'in:in_transit,out_for_delivery,delivered,failed',
                ],
            ]);

            $newStatus = $validated['status'];

            if ($shipment->status === 'delivered') {
                return response()->json([
                    'success' => false,
                    'message' => 'This shipment has already been delivered.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | DELIVERY PROOF REQUIRED
            |--------------------------------------------------------------------------
            */

            if (
                $newStatus === 'delivered'
                && empty($shipment->proof_of_delivery)
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Upload proof of delivery before marking this shipment as delivered.',
                    'requires_proof' => true,
                ], 422);
            }

            DB::beginTransaction();

            try {
                $shipment->status = $newStatus;

                if ($newStatus === 'delivered') {
                    $shipment->delivered_at = now();
                }

                if ($newStatus !== 'delivered') {
                    $shipment->delivered_at = null;
                }

                $shipment->save();

                /*
                |--------------------------------------------------------------------------
                | SYNC SELLER ORDER / BUYER ORDER
                |--------------------------------------------------------------------------
                */

                if ($newStatus === 'delivered') {
                    $sellerOrder =
                        $shipment->sellerOrder;

                    if ($sellerOrder) {
                        $sellerOrder->status =
                            'delivered';

                        $sellerOrder->save();

                        $order =
                            $sellerOrder->order;

                        if ($order) {
                            $order->load([
                                'sellerOrders.shipment',
                            ]);

                            $sellerOrders =
                                $order->sellerOrders;

                            $allDelivered =
                                $sellerOrders->isNotEmpty()
                                && $sellerOrders->every(
                                    function ($item) {
                                        return
                                            $item->shipment
                                            && $item->shipment->status
                                                === 'delivered';
                                    }
                                );

                            if ($allDelivered) {
                                $order->status =
                                    'delivered';

                                $order->delivery_status =
                                    'delivered';

                                $order->delivered_at =
                                    now();

                                $order->save();
                            } else {
                                $hasDelivered =
                                    $sellerOrders->contains(
                                        function ($item) {
                                            return
                                                $item->shipment
                                                && $item->shipment->status
                                                    === 'delivered';
                                        }
                                    );

                                if ($hasDelivered) {
                                    $order->delivery_status =
                                        'partially_delivered';

                                    $order->save();
                                }
                            }
                        }
                    }
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();

                throw $e;
            }

            $shipment->refresh();

            $shipment->load([
                'sellerOrder.order.user',
                'sellerOrder.seller',
                'logistic',
                'hub',
                'rider',
            ]);

            return response()->json([
                'success' => true,

                'message' => match ($newStatus) {
                    'in_transit' =>
                        'Shipment marked as in transit.',

                    'out_for_delivery' =>
                        'Shipment marked as out for delivery.',

                    'delivered' =>
                        'Shipment delivered successfully.',

                    'failed' =>
                        'Delivery marked as failed.',

                    default =>
                        'Shipment status updated successfully.',
                },

                'data' => $this->formatShipment(
                    shipment: $shipment,
                    rider: $rider,
                ),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update shipment status.',
            ], 500);
        }
    }

    /**
     * Check whether the authenticated rider has any assignment
     * on this shipment.
     *
     * Used by list/details.
     */
    private function isShipmentAssignedToRider(
        Shipment $shipment,
        int $riderId
    ): bool {
        return
            (int) ($shipment->rider_id ?? 0) === $riderId
            || (int) ($shipment->pickup_rider_id ?? 0) === $riderId;
    }

    /**
     * Check whether the authenticated rider is the final-mile rider.
     *
     * Used by proof/status operations.
     */
    private function isFinalMileRider(
        Shipment $shipment,
        int $riderId
    ): bool {
        return
            (int) ($shipment->rider_id ?? 0) === $riderId;
    }

    /**
     * Determine how the rider is assigned to the shipment.
     */
    private function assignmentType(
        Shipment $shipment,
        int $riderId
    ): string {
        $isFinalMile =
            (int) ($shipment->rider_id ?? 0)
            === $riderId;

        $isPickup =
            (int) ($shipment->pickup_rider_id ?? 0)
            === $riderId;

        if ($isFinalMile && $isPickup) {
            return 'pickup_and_delivery';
        }

        if ($isFinalMile) {
            return 'final_mile';
        }

        if ($isPickup) {
            return 'seller_pickup';
        }

        return 'unknown';
    }

    /**
     * Format shipment data for the Rider mobile application.
     *
     * IMPORTANT:
     * id = Shipment ID.
     */
    private function formatShipment(
        Shipment $shipment,
        $rider
    ): array {
        $sellerOrder =
            $shipment->sellerOrder;

        $order =
            $sellerOrder?->order;

        $customer =
            $order?->user;

        $assignmentType =
            $this->assignmentType(
                shipment: $shipment,
                riderId: (int) $rider->id,
            );

        return [
            /*
            |--------------------------------------------------------------------------
            | SHIPMENT IDENTIFICATION
            |--------------------------------------------------------------------------
            */

            'id' =>
                $shipment->id,

            'shipment_id' =>
                $shipment->id,

            'seller_order_id' =>
                $shipment->seller_order_id,

            'tracking_number' =>
                $shipment->tracking_number ?? '',

            /*
            |--------------------------------------------------------------------------
            | ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            'assignment_type' =>
                $assignmentType,

            'is_final_mile_rider' =>
                (int) ($shipment->rider_id ?? 0)
                === (int) $rider->id,

            'is_pickup_rider' =>
                (int) ($shipment->pickup_rider_id ?? 0)
                === (int) $rider->id,

            'pickup_rider_id' =>
                $shipment->pickup_rider_id,

            /*
            |--------------------------------------------------------------------------
            | CUSTOMER
            |--------------------------------------------------------------------------
            */

            'customer_name' =>
                $customer?->name ?? 'Customer',

            'customer_phone' =>
                $customer?->mobile_number
                ?? $customer?->phone
                ?? '',

            /*
            |--------------------------------------------------------------------------
            | DELIVERY
            |--------------------------------------------------------------------------
            */

            'delivery_address' =>
                $shipment->delivery_address ?? '',

            'pickup_address' =>
                $shipment->pickup_address ?? '',

            'status' =>
                $shipment->status ?? '',

            /*
            |--------------------------------------------------------------------------
            | PROOF
            |--------------------------------------------------------------------------
            */

            'has_proof_of_delivery' =>
                !empty(
                    $shipment->proof_of_delivery
                ),

            'proof_of_delivery' =>
                $shipment->proof_of_delivery,

            'delivery_signature' =>
                $shipment->delivery_signature,

            'delivered_to' =>
                $shipment->delivered_to,

            'delivery_notes' =>
                $shipment->notes,

            /*
            |--------------------------------------------------------------------------
            | RIDER
            |--------------------------------------------------------------------------
            */

            'rider_id' =>
                $shipment->rider_id,

            'rider_name' =>
                $shipment->rider?->name
                ?? (
                    (int) ($shipment->rider_id ?? 0)
                    === (int) $rider->id
                        ? ($rider->name ?? 'Rider')
                        : ''
                ),

            /*
            |--------------------------------------------------------------------------
            | LOCATION
            |--------------------------------------------------------------------------
            */

            'latitude' =>
                $shipment->latitude !== null
                    ? (float) $shipment->latitude
                    : null,

            'longitude' =>
                $shipment->longitude !== null
                    ? (float) $shipment->longitude
                    : null,

            'rider_latitude' =>
                $shipment->rider_latitude !== null
                    ? (float) $shipment->rider_latitude
                    : null,

            'rider_longitude' =>
                $shipment->rider_longitude !== null
                    ? (float) $shipment->rider_longitude
                    : null,

            /*
            |--------------------------------------------------------------------------
            | DATES
            |--------------------------------------------------------------------------
            */

            'order_date' =>
                $order?->created_at?->toISOString(),

            'created_at' =>
                $shipment->created_at?->toISOString(),

            'updated_at' =>
                $shipment->updated_at?->toISOString(),

            'picked_up_at' =>
                $shipment->picked_up_at?->toISOString(),

            'hub_picked_up_at' =>
                $shipment->hub_picked_up_at?->toISOString(),

            'delivered_at' =>
                $shipment->delivered_at?->toISOString(),

            /*
            |--------------------------------------------------------------------------
            | EARNINGS
            |--------------------------------------------------------------------------
            */

            'delivery_earnings' => null,
        ];
    }
}