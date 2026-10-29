<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\OrderFlowSyncService;
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
     * Mobile uses a separate proof-upload endpoint before the
     * shipment is finally marked as delivered.
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

            if (!$this->isFinalMileRider(
                shipment: $shipment,
                riderId: (int) $rider->id,
            )) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'This shipment is not assigned to you for final delivery.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | DELIVERY SEQUENCE
            |--------------------------------------------------------------------------
            |
            | The web Rider flow requires the parcel to be out for delivery
            | before it can be completed. Mobile follows the same rule.
            |
            */

            if ($shipment->status !== 'out_for_delivery') {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Shipment must be out for delivery before uploading delivery proof.',
                ], 422);
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
     * Update final-mile shipment delivery status.
     *
     * PUT /api/rider/orders/{shipment}/status
     */
    public function updateStatus(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        try {
            $authenticatedRider = $request->user();

            if (!$authenticatedRider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            if (!$authenticatedRider->isRider()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account is not a rider account.',
                ], 403);
            }

            if (!$this->isFinalMileRider(
                shipment: $shipment,
                riderId: (int) $authenticatedRider->id,
            )) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'This shipment is not assigned to you for final delivery.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | MOBILE STATUS CONTRACT
            |--------------------------------------------------------------------------
            |
            | "failed" is accepted temporarily for compatibility with an older
            | Flutter build, but it is normalized to "delivery_failed".
            |
            */

            $validated = $request->validate([
                'status' => [
                    'required',
                    'string',
                    'in:in_transit,out_for_delivery,delivered,delivery_failed,failed',
                ],

                'delivery_notes' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'failure_reason' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ]);

            $newStatus = $validated['status'];

            if ($newStatus === 'failed') {
                $newStatus = 'delivery_failed';
            }

            DB::transaction(function () use (
                $shipment,
                $authenticatedRider,
                $validated,
                $newStatus
            ) {
                /*
                |--------------------------------------------------------------------------
                | LOCK CURRENT SHIPMENT
                |--------------------------------------------------------------------------
                */

                $lockedShipment = Shipment::query()
                    ->whereKey($shipment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) ($lockedShipment->rider_id ?? 0)
                    !== (int) $authenticatedRider->id
                ) {
                    abort(
                        403,
                        'This shipment is not assigned to you for final delivery.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | FINALIZED SHIPMENT
                |--------------------------------------------------------------------------
                */

                if (in_array(
                    $lockedShipment->status,
                    [
                        'delivered',
                        'delivery_failed',
                        'cancelled',
                    ],
                    true
                )) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'status' =>
                            'This shipment is already finalized.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | IN TRANSIT
                |--------------------------------------------------------------------------
                |
                | Kept for mobile compatibility.
                |
                */

                if ($newStatus === 'in_transit') {
                    if (!in_array(
                        $lockedShipment->status,
                        [
                            'assigned',
                            'picked_up',
                            'in_transit',
                        ],
                        true
                    )) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'status' =>
                                'Shipment cannot be marked as in transit from its current status.',
                        ]);
                    }

                    if ($lockedShipment->status === 'in_transit') {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'status' =>
                                'Shipment is already in transit.',
                        ]);
                    }

                    $lockedShipment->update([
                        'status' => 'in_transit',
                        'notes' =>
                            $validated['delivery_notes']
                            ?? $lockedShipment->notes,
                    ]);

                    app(OrderFlowSyncService::class)
                        ->syncShipment($lockedShipment);

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | SORTING CENTER VALIDATION
                |--------------------------------------------------------------------------
                |
                | Matches the working Rider web flow.
                |
                */

                if (!in_array(
                    $lockedShipment->status,
                    [
                        'picked_up',
                        'in_transit',
                        'out_for_delivery',
                    ],
                    true
                )) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'status' =>
                            'Shipment is not ready for final delivery.',
                    ]);
                }

                if (
                    !$lockedShipment->received_by_sorting_center
                    || $lockedShipment->sorting_status !== 'staged'
                    || $lockedShipment->staged_at === null
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'status' =>
                            'Shipment must complete sorting center processing before delivery.',
                    ]);
                }

                if (
                    in_array(
                        $newStatus,
                        [
                            'delivered',
                            'delivery_failed',
                        ],
                        true
                    )
                    && $lockedShipment->status !== 'out_for_delivery'
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'status' =>
                            'Shipment must be out for delivery first.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | LOCK SELLER ORDER + BUYER ORDER
                |--------------------------------------------------------------------------
                */

                $sellerOrder = SellerOrder::query()
                    ->whereKey($lockedShipment->seller_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $sellerOrder->loadMissing('seller');

                $order = Order::query()
                    ->whereKey($sellerOrder->order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $tracking =
                    $lockedShipment->tracking_number
                    ?: ('Shipment #' . $lockedShipment->id);

                /*
                |--------------------------------------------------------------------------
                | OUT FOR DELIVERY
                |--------------------------------------------------------------------------
                */

                if ($newStatus === 'out_for_delivery') {
                    if ($lockedShipment->status === 'out_for_delivery') {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'status' =>
                                'Shipment is already out for delivery.',
                        ]);
                    }

                    $lockedShipment->update([
                        'status' => 'out_for_delivery',
                        'notes' =>
                            $validated['delivery_notes']
                            ?? $lockedShipment->notes,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | BUYER TRACKING SYNC
                    |--------------------------------------------------------------------------
                    |
                    | This was missing from the old mobile API.
                    |
                    */

                    app(OrderFlowSyncService::class)
                        ->syncShipment($lockedShipment);

                    $this->createNotification(
                        userId: (int) $order->user_id,
                        title: 'Parcel Out for Delivery',
                        message: "Your parcel {$tracking} is on its way.",
                        type: 'delivery',
                        link: route('orders.show', $order),
                    );

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | DELIVERED
                |--------------------------------------------------------------------------
                */

                if ($newStatus === 'delivered') {
                    /*
                    |--------------------------------------------------------------------------
                    | PROOF REQUIRED
                    |--------------------------------------------------------------------------
                    */

                    if (empty($lockedShipment->proof_of_delivery)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'status' =>
                                'Upload proof of delivery before marking this shipment as delivered.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | COD PAYMENT VALIDATION
                    |--------------------------------------------------------------------------
                    |
                    | Same rule as Rider web.
                    |
                    */

                    if ($order->payment_method === 'cod') {
                        $expectedMinor =
                            (int) $sellerOrder->total_minor;

                        $shipmentPayment = Payment::query()
                            ->where(
                                'shipment_id',
                                $lockedShipment->id
                            )
                            ->where(
                                'order_id',
                                $order->id
                            )
                            ->where('method', 'cod')
                            ->where('status', 'paid')
                            ->first();

                        $shipmentPaid =
                            $shipmentPayment
                            && $expectedMinor > 0
                            && (int) $shipmentPayment->amount_minor
                                === $expectedMinor;

                        /*
                        |--------------------------------------------------------------------------
                        | LEGACY FULLY PAID COD SUPPORT
                        |--------------------------------------------------------------------------
                        */

                        $legacyFullyPaid =
                            !$shipmentPayment
                            && $order->payment_status === 'paid'
                            && (int) $order->amount_collected_minor
                                === (int) $order->total_minor
                            && $order->collected_at !== null
                            && $order->collected_by !== null
                            && !Payment::query()
                                ->where('order_id', $order->id)
                                ->whereNotNull('shipment_id')
                                ->exists();

                        if (!$shipmentPaid && !$legacyFullyPaid) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'status' =>
                                    'COD for this shipment must be collected before marking it as delivered.',
                            ]);
                        }
                    }

                    $lockedShipment->update([
                        'status' => 'delivered',
                        'sorting_status' => 'staged',
                        'delivered_at' => now(),
                        'notes' =>
                            $validated['delivery_notes']
                            ?? $lockedShipment->notes,
                    ]);

                    app(OrderFlowSyncService::class)
                        ->syncShipment($lockedShipment);

                    $sellerOrder->update([
                        'status' => 'delivered',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | DELIVERY FAILED
                |--------------------------------------------------------------------------
                */

                if ($newStatus === 'delivery_failed') {
                    $failureReason =
                        trim(
                            (string) (
                                $validated['failure_reason']
                                ?? $validated['delivery_notes']
                                ?? ''
                            )
                        );

                    if ($failureReason === '') {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'failure_reason' =>
                                'Failure reason is required when delivery fails.',
                        ]);
                    }

                    $lockedShipment->update([
                        'status' => 'delivery_failed',
                        'notes' => $failureReason,
                    ]);

                    app(OrderFlowSyncService::class)
                        ->syncShipment($lockedShipment);

                    $sellerOrder->update([
                        'status' => 'delivery_failed',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | RIDER LOAD / AVAILABILITY
                |--------------------------------------------------------------------------
                |
                | Delivered parcels free one rider capacity slot.
                | Failed parcels remain assigned until returned to logistics.
                |
                */

                $rider = User::query()
                    ->whereKey($authenticatedRider->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($newStatus === 'delivered') {
                    if ((int) $rider->current_load > 0) {
                        $rider->decrement('current_load');
                    }

                    $rider->refresh();

                    if (
                        $rider->max_capacity !== null
                        && (int) $rider->current_load
                            < (int) $rider->max_capacity
                    ) {
                        $rider->update([
                            'availability_status' =>
                                'available',
                        ]);
                    }

                    $rider->increment(
                        'daily_deliveries_completed'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | PARENT BUYER ORDER
                |--------------------------------------------------------------------------
                */

                $hasUndelivered = $order
                    ->sellerOrders()
                    ->where('status', '!=', 'delivered')
                    ->exists();

                $hasDelivered = $order
                    ->sellerOrders()
                    ->where('status', 'delivered')
                    ->exists();

                if (!$hasUndelivered) {
                    $order->update([
                        'status' => 'delivered',
                        'delivery_status' => 'delivered',
                        'delivered_at' => now(),
                    ]);
                } elseif ($hasDelivered) {
                    $order->update([
                        'delivery_status' =>
                            'partially_delivered',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | DELIVERED SIDE EFFECTS
                |--------------------------------------------------------------------------
                */

                if ($newStatus === 'delivered') {
                    app(CommissionService::class)
                        ->generateForOrder($order);

                    $this->createNotification(
                        userId: (int) $order->user_id,
                        title: 'Parcel Delivered',
                        message:
                            "Your parcel {$tracking} has been delivered.",
                        type: 'delivery',
                        link: route('orders.show', $order),
                    );

                    $sellerUserId =
                        $sellerOrder->seller?->user_id;

                    if ($sellerUserId) {
                        $this->createNotification(
                            userId: (int) $sellerUserId,
                            title:
                                'Parcel Delivered to Customer',
                            message:
                                "Parcel {$tracking} has been delivered successfully.",
                            type: 'shipment',
                        );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | DELIVERY FAILED NOTIFICATION
                |--------------------------------------------------------------------------
                */

                if ($newStatus === 'delivery_failed') {
                    $failureReason =
                        $validated['failure_reason']
                        ?? $validated['delivery_notes']
                        ?? 'Delivery failed.';

                    $this->createNotification(
                        userId: (int) $order->user_id,
                        title: 'Parcel Delivery Failed',
                        message:
                            "Delivery failed for parcel {$tracking}. Reason: {$failureReason}",
                        type: 'delivery',
                        link: route('orders.show', $order),
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | LOGISTICS NOTIFICATION
                |--------------------------------------------------------------------------
                */

                if (in_array(
                    $newStatus,
                    [
                        'delivered',
                        'delivery_failed',
                    ],
                    true
                )) {
                    $lockedShipment->loadMissing(
                        'logistic.owner'
                    );

                    $logisticOwner =
                        $lockedShipment->logistic?->owner;

                    if ($logisticOwner) {
                        $title =
                            $newStatus === 'delivered'
                                ? 'Parcel Delivery Completed'
                                : 'Parcel Delivery Failed';

                        $this->createNotification(
                            userId: (int) $logisticOwner->id,
                            title: $title,
                            message:
                                "Rider {$rider->name} updated parcel {$tracking} to {$newStatus}.",
                            type: 'shipment',
                        );
                    }
                }
            });

            /*
            |--------------------------------------------------------------------------
            | FRESH RESPONSE DATA
            |--------------------------------------------------------------------------
            */

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

                    'delivery_failed' =>
                        'Delivery marked as failed.',

                    default =>
                        'Shipment status updated successfully.',
                },

                'data' => $this->formatShipment(
                    shipment: $shipment,
                    rider: $authenticatedRider,
                ),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to update shipment status.',
            ], 500);
        }
    }

    /**
     * Create a notification using the same notification model
     * used by the Rider web application.
     */
    private function createNotification(
        int $userId,
        string $title,
        string $message,
        string $type = 'delivery',
        ?string $link = null
    ): void {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
        ]);
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
            || (int) ($shipment->pickup_rider_id ?? 0)
                === $riderId;
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
            (int) ($shipment->rider_id ?? 0)
            === $riderId;
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

            'buyer_order_status' =>
                $order?->status,

            'buyer_delivery_status' =>
                $order?->delivery_status,

            /*
            |--------------------------------------------------------------------------
            | SORTING CENTER
            |--------------------------------------------------------------------------
            */

            'received_by_sorting_center' =>
                (bool) (
                    $shipment->received_by_sorting_center
                    ?? false
                ),

            'sorting_status' =>
                $shipment->sorting_status,

            'staged_at' =>
                $shipment->staged_at?->toISOString(),

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