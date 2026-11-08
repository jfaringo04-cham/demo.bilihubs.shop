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
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | RIDER ASSIGNED SHIPMENTS
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        try {
            $rider = $this->authenticatedRider($request);

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
                ->map(
                    fn (Shipment $shipment) =>
                        $this->formatShipment($shipment, $rider)
                )
                ->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'total' => $data->count(),
                ],
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                'Unable to load rider deliveries.',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SINGLE RIDER SHIPMENT
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        try {
            $rider = $this->authenticatedRider($request);

            abort_unless(
                $this->isShipmentAssignedToRider(
                    $shipment,
                    (int) $rider->id
                ),
                403,
                'This shipment is not assigned to you.'
            );

            $this->loadShipmentRelations($shipment);

            return response()->json([
                'success' => true,
                'data' => $this->formatShipment(
                    $shipment,
                    $rider
                ),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                'Unable to load rider delivery.',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD PROOF OF DELIVERY
    |--------------------------------------------------------------------------
    |
    | POST /api/rider/orders/{shipment}/proof
    |
    | Requirements:
    | - Authenticated final-mile rider
    | - Successful hub QR pickup
    | - Shipment out for delivery
    | - Valid proof image
    |
    */

    public function uploadProof(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        try {
            $rider = $this->authenticatedRider($request);

            abort_unless(
                $this->isFinalMileRider(
                    $shipment,
                    (int) $rider->id
                ),
                403,
                'This shipment is not assigned to you for final delivery.'
            );

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

            // Validate before uploading to private storage.
            $this->validateProofEligibility(
                $shipment,
                $rider
            );

            $proofPath = $request
                ->file('proof_of_delivery')
                ->store(
                    'proof-of-delivery',
                    'supabase_private'
                );

            if (!$proofPath) {
                return $this->error(
                    'Proof of delivery upload failed.',
                    500
                );
            }

            // Recheck the assignment and status under lock.
            // If it changed, the new proof is not applied.
            try {
                DB::transaction(function () use (
                    $shipment,
                    $rider,
                    $validated,
                    $proofPath
                ) {
                    $locked = Shipment::query()
                        ->whereKey($shipment->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->validateProofEligibility(
                        $locked,
                        $rider
                    );

                    if (!empty($locked->proof_of_delivery)) {
                        throw ValidationException::withMessages([
                            'proof_of_delivery' =>
                                'Proof of delivery has already been uploaded.',
                        ]);
                    }

                    $locked->proof_of_delivery = $proofPath;

                    if (array_key_exists(
                        'delivery_signature',
                        $validated
                    )) {
                        $locked->delivery_signature =
                            $validated['delivery_signature'];
                    }

                    if (array_key_exists(
                        'delivered_to',
                        $validated
                    )) {
                        $locked->delivered_to =
                            $validated['delivered_to'];
                    }

                    if (array_key_exists(
                        'delivery_notes',
                        $validated
                    )) {
                        $locked->notes =
                            $validated['delivery_notes'];
                    }

                    $locked->save();
                });
            } catch (\Throwable $e) {
                // Prevent orphaned proof files if the
                // database transaction is rejected.
                try {
                    \Illuminate\Support\Facades\Storage::disk(
                        'supabase_private'
                    )->delete($proofPath);
                } catch (\Throwable $cleanupError) {
                    report($cleanupError);
                }

                throw $e;
            }

            $shipment->refresh();
            $this->loadShipmentRelations($shipment);

            return response()->json([
                'success' => true,
                'message' =>
                    'Delivery proof uploaded successfully.',
                'data' => $this->formatShipment(
                    $shipment,
                    $rider
                ),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                'Unable to upload delivery proof.',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE FINAL-MILE DELIVERY STATUS
    |--------------------------------------------------------------------------
    |
    | PUT /api/rider/orders/{shipment}/status
    |
    | Allowed sequence:
    |
    | Hub QR scan -> picked_up
    | picked_up -> out_for_delivery
    | out_for_delivery -> delivered
    | out_for_delivery -> delivery_failed
    |
    | An ordinary status request cannot replace
    | the seller or hub QR scan.
    |
    */

    public function updateStatus(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        try {
            $rider = $this->authenticatedRider($request);

            abort_unless(
                $this->isFinalMileRider(
                    $shipment,
                    (int) $rider->id
                ),
                403,
                'This shipment is not assigned to you for final delivery.'
            );

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

            // Do not allow this endpoint to simulate pickup.
            if ($newStatus === 'in_transit') {
                throw ValidationException::withMessages([
                    'status' =>
                        'Pickup must be confirmed using the official BiliHub QR scanner.',
                ]);
            }

            DB::transaction(function () use (
                $shipment,
                $rider,
                $validated,
                $newStatus
            ) {
                $locked = Shipment::query()
                    ->whereKey($shipment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless(
                    $this->isFinalMileRider(
                        $locked,
                        (int) $rider->id
                    ),
                    403,
                    'This shipment is not assigned to you for final delivery.'
                );

                $this->validateNotFinalized($locked);

                /*
                |----------------------------------------------------------
                | REQUIRE CONFIRMED HUB QR PICKUP
                |----------------------------------------------------------
                */

                if (
                    $locked->hub_picked_up_at === null ||
                    (int) ($locked->hub_pickup_rider_id ?? 0)
                        !== (int) $rider->id
                ) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Scan and confirm this parcel at the assigned hub before starting or completing delivery.',
                    ]);
                }

                /*
                |----------------------------------------------------------
                | REQUIRE LOGISTICS RECEIVING + STAGING
                |----------------------------------------------------------
                */

                if (
                    !$locked->received_by_sorting_center ||
                    $locked->sorting_status !== 'staged' ||
                    $locked->staged_at === null
                ) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Shipment must complete sorting center processing before delivery.',
                    ]);
                }

                /*
                |----------------------------------------------------------
                | VALIDATE STATUS TRANSITION
                |----------------------------------------------------------
                */

                if ($newStatus === 'out_for_delivery') {
                    if ($locked->status !== 'picked_up') {
                        throw ValidationException::withMessages([
                            'status' =>
                                'Shipment must be picked up and scanned at the hub before going out for delivery.',
                        ]);
                    }
                } elseif (
                    in_array(
                        $newStatus,
                        ['delivered', 'delivery_failed'],
                        true
                    )
                ) {
                    if ($locked->status !== 'out_for_delivery') {
                        throw ValidationException::withMessages([
                            'status' =>
                                'Shipment must be out for delivery first.',
                        ]);
                    }
                }

                /*
                |----------------------------------------------------------
                | LOCK ASSOCIATED ORDER RECORDS
                |----------------------------------------------------------
                */

                $sellerOrder = SellerOrder::query()
                    ->whereKey($locked->seller_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $sellerOrder->loadMissing('seller');

                $order = Order::query()
                    ->whereKey($sellerOrder->order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $tracking = $locked->tracking_number
                    ?: 'Shipment #' . $locked->id;

                /*
                |----------------------------------------------------------
                | OUT FOR DELIVERY
                |----------------------------------------------------------
                */

                if ($newStatus === 'out_for_delivery') {
                    $locked->update([
                        'status' => 'out_for_delivery',
                        'notes' =>
                            $validated['delivery_notes']
                            ?? $locked->notes,
                    ]);

                    app(OrderFlowSyncService::class)
                        ->syncShipment($locked);

                    $this->createNotification(
                        userId: (int) $order->user_id,
                        title: 'Parcel Out for Delivery',
                        message:
                            "Your parcel {$tracking} is on its way.",
                        type: 'delivery',
                        link: route('orders.show', $order)
                    );

                    return;
                }

                /*
                |----------------------------------------------------------
                | DELIVERED — REQUIRE PROOF
                |----------------------------------------------------------
                */

                if ($newStatus === 'delivered') {
                    if (empty($locked->proof_of_delivery)) {
                        throw ValidationException::withMessages([
                            'status' =>
                                'Upload proof of delivery before marking this shipment as delivered.',
                        ]);
                    }

                    /*
                    |------------------------------------------------------
                    | COD PAYMENT VALIDATION
                    |------------------------------------------------------
                    |
                    | Keep the existing per-shipment and legacy
                    | payment checks to protect COD settlement.
                    |
                    */

                    if ($order->payment_method === 'cod') {
                        $expectedMinor =
                            (int) $sellerOrder->total_minor;

                        $shipmentPayment = Payment::query()
                            ->where(
                                'shipment_id',
                                $locked->id
                            )
                            ->where(
                                'order_id',
                                $order->id
                            )
                            ->where('method', 'cod')
                            ->where('status', 'paid')
                            ->first();

                        $shipmentPaid =
                            $shipmentPayment &&
                            $expectedMinor > 0 &&
                            (int) $shipmentPayment->amount_minor
                                === $expectedMinor;

                        $legacyFullyPaid =
                            !$shipmentPayment &&
                            $order->payment_status === 'paid' &&
                            (int) $order->amount_collected_minor
                                === (int) $order->total_minor &&
                            $order->collected_at !== null &&
                            $order->collected_by !== null &&
                            !Payment::query()
                                ->where(
                                    'order_id',
                                    $order->id
                                )
                                ->whereNotNull('shipment_id')
                                ->exists();

                        if (!$shipmentPaid && !$legacyFullyPaid) {
                            throw ValidationException::withMessages([
                                'status' =>
                                    'COD for this shipment must be collected before marking it as delivered.',
                            ]);
                        }
                    }

                    $locked->update([
                        'status' => 'delivered',
                        'sorting_status' => 'staged',
                        'delivered_at' => now(),
                        'notes' =>
                            $validated['delivery_notes']
                            ?? $locked->notes,
                    ]);

                    app(OrderFlowSyncService::class)
                        ->syncShipment($locked);

                    $sellerOrder->update([
                        'status' => 'delivered',
                    ]);
                }

                /*
                |----------------------------------------------------------
                | DELIVERY FAILED
                |----------------------------------------------------------
                */

                if ($newStatus === 'delivery_failed') {
                    $reason = trim(
                        (string) (
                            $validated['failure_reason']
                            ?? $validated['delivery_notes']
                            ?? ''
                        )
                    );

                    if ($reason === '') {
                        throw ValidationException::withMessages([
                            'failure_reason' =>
                                'Failure reason is required when delivery fails.',
                        ]);
                    }

                    $locked->update([
                        'status' => 'delivery_failed',
                        'notes' => $reason,
                    ]);

                    app(OrderFlowSyncService::class)
                        ->syncShipment($locked);

                    $sellerOrder->update([
                        'status' => 'delivery_failed',
                    ]);
                }

                /*
                |----------------------------------------------------------
                | RIDER WORKLOAD
                |----------------------------------------------------------
                */

                $lockedRider = User::query()
                    ->whereKey($rider->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($newStatus === 'delivered') {
                    if ((int) $lockedRider->current_load > 0) {
                        $lockedRider->decrement('current_load');
                    }

                    $lockedRider->refresh();

                    if (
                        $lockedRider->max_capacity !== null &&
                        (int) $lockedRider->current_load <
                            (int) $lockedRider->max_capacity
                    ) {
                        $lockedRider->update([
                            'availability_status' => 'available',
                        ]);
                    }

                    $lockedRider->increment(
                        'daily_deliveries_completed'
                    );
                }

                // Failed deliveries retain their rider
                // custody/load until the return workflow
                // releases the shipment.

                /*
                |----------------------------------------------------------
                | PARENT BUYER ORDER
                |----------------------------------------------------------
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
                |----------------------------------------------------------
                | DELIVERED NOTIFICATIONS & COMMISSION
                |----------------------------------------------------------
                */

                if ($newStatus === 'delivered') {

                    // Generate the existing seller commission record.
                    app(CommissionService::class)
                        ->generateForOrder($order);

                    // Get only the seller associated with this shipment.
                    $sellerUserId = $sellerOrder->seller?->user_id;

                    // Recognize only this seller's commission.
                    // Do not recognize commissions belonging to other
                    // seller orders under the same parent order.
                    if ($sellerUserId) {

                        $commission = \App\Models\Commission::query()
                            ->where('order_id', $order->id)
                            ->where('seller_id', $sellerUserId)
                            ->first();

                        if ($commission) {
                            app(\App\Services\PlatformRevenueService::class)
                                ->recognizeCommission($commission);
                        }
                    }

                    // Notify the buyer.
                    $this->createNotification(
                        userId: (int) $order->user_id,
                        title: 'Parcel Delivered',
                        message: "Your parcel {$tracking} has been delivered.",
                        type: 'delivery',
                        link: route('orders.show', $order)
                    );

                    // Notify the seller.
                    if ($sellerUserId) {
                        $this->createNotification(
                            userId: (int) $sellerUserId,
                            title: 'Parcel Delivered to Customer',
                            message: "Parcel {$tracking} has been delivered successfully.",
                            type: 'shipment'
                        );
                    }
                }

                /*
                |----------------------------------------------------------
                | FAILED DELIVERY NOTIFICATION
                |----------------------------------------------------------
                */

                if ($newStatus === 'delivery_failed') {
                    $reason =
                        $validated['failure_reason']
                        ?? $validated['delivery_notes']
                        ?? 'Delivery failed.';

                    $this->createNotification(
                        userId: (int) $order->user_id,
                        title: 'Parcel Delivery Failed',
                        message:
                            "Delivery failed for parcel {$tracking}. Reason: {$reason}",
                        type: 'delivery',
                        link: route('orders.show', $order)
                    );
                }

                /*
                |----------------------------------------------------------
                | LOGISTICS NOTIFICATION
                |----------------------------------------------------------
                */

                if (
                    in_array(
                        $newStatus,
                        ['delivered', 'delivery_failed'],
                        true
                    )
                ) {
                    $locked->loadMissing('logistic.owner');

                    $logisticOwner =
                        $locked->logistic?->owner;

                    if ($logisticOwner) {
                        $title = $newStatus === 'delivered'
                            ? 'Parcel Delivery Completed'
                            : 'Parcel Delivery Failed';

                        $this->createNotification(
                            userId: (int) $logisticOwner->id,
                            title: $title,
                            message:
                                "Rider {$lockedRider->name} updated parcel {$tracking} to {$newStatus}.",
                            type: 'shipment'
                        );
                    }
                }
            });

            $shipment->refresh();
            $this->loadShipmentRelations($shipment);

            return response()->json([
                'success' => true,
                'message' => match ($newStatus) {
                    'out_for_delivery' =>
                        'Shipment marked as out for delivery.',
                    'delivered' =>
                        'Shipment delivered successfully.',
                    'delivery_failed' =>
                        'Delivery marked as failed.',
                    default =>
                        'Shipment status updated.',
                },
                'data' => $this->formatShipment(
                    $shipment,
                    $rider
                ),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                'Unable to update shipment status.',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SELLER PICKUP — LEGACY MANUAL ENDPOINT
    |--------------------------------------------------------------------------
    |
    | POST /api/rider/pickups/{shipment}/picked-up
    |
    | This endpoint is retained for route compatibility.
    | It does NOT change shipment status.
    |
    | The actual seller pickup must use:
    |
    | POST /api/rider/scan
    |
    | Required QR:
    | tracking_number + qr_token + seller_order_id
    |
    */

    public function markSellerPickupInTransit(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        try {
            $rider = $this->authenticatedRider($request);

            abort_unless(
                $this->isSellerPickupRider(
                    $shipment,
                    (int) $rider->id
                ),
                403,
                'This shipment is not assigned to you for seller pickup.'
            );

            return $this->error(
                'Seller pickup must be confirmed by scanning the original BiliHub waybill QR at the seller location. Use the Rider Scanner.',
                422
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                'Unable to validate seller pickup.',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELIVER SELLER PICKUP TO SORTING CENTER
    |--------------------------------------------------------------------------
    |
    | POST /api/rider/pickups/{shipment}/deliver-to-sorting-center
    |
    | Preconditions:
    | - Authenticated pickup rider
    | - Successful seller QR scan recorded
    | - Shipment currently in transit
    |
    | Logistics must still confirm receiving.
    |
    */

    public function deliverSellerPickupToSortingCenter(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        try {
            $rider = $this->authenticatedRider($request);

            abort_unless(
                $this->isSellerPickupRider(
                    $shipment,
                    (int) $rider->id
                ),
                403,
                'This shipment is not assigned to you for seller pickup.'
            );

            $validated = $request->validate([
                'notes' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ]);

            DB::transaction(function () use (
                $shipment,
                $rider,
                $validated
            ) {
                $locked = Shipment::query()
                    ->whereKey($shipment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless(
                    $this->isSellerPickupRider(
                        $locked,
                        (int) $rider->id
                    ),
                    403,
                    'This shipment is not assigned to you for seller pickup.'
                );

                if ($locked->received_by_sorting_center) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'This parcel has already been received by the sorting center.',
                    ]);
                }

                if ($locked->status === 'at_sorting_center') {
                    throw ValidationException::withMessages([
                        'status' =>
                            'This parcel is already awaiting Logistics confirmation.',
                    ]);
                }

                if ($locked->status !== 'in_transit') {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Only in-transit seller pickups can be handed over to the sorting center.',
                    ]);
                }

                /*
                |----------------------------------------------------------
                | REQUIRE SELLER QR CONFIRMATION
                |----------------------------------------------------------
                */

                if (
                    $locked->seller_scan_confirmed_at === null ||
                    $locked->scanned_at_seller === null ||
                    (int) ($locked->scanned_by_rider_id ?? 0)
                        !== (int) $rider->id
                ) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Scan the original seller waybill QR before delivering this parcel to the sorting center.',
                    ]);
                }

                $sellerOrder = SellerOrder::query()
                    ->whereKey($locked->seller_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                Order::query()
                    ->whereKey($sellerOrder->order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                |----------------------------------------------------------
                | ASSIGN HUB IF MISSING
                |----------------------------------------------------------
                */

                if (!$locked->hub_id) {
                    $hub =
                        \App\Services\HubAssignmentService::
                        findBestHubForSellerOrder($sellerOrder);

                    if (!$hub) {
                        throw ValidationException::withMessages([
                            'status' =>
                                'No sorting center is available for this parcel.',
                        ]);
                    }

                    $locked->hub_id = $hub->id;
                    $locked->logistic_id = $hub->logistic_id;
                }

                $newNotes = trim(
                    (string) ($validated['notes'] ?? '')
                );

                $existingNotes = trim(
                    (string) ($locked->notes ?? '')
                );

                /*
                |----------------------------------------------------------
                | ARRIVED — NOT YET RECEIVED
                |----------------------------------------------------------
                */

                $locked->update([
                    'status' => 'at_sorting_center',
                    'at_sorting_center_at' => now(),
                    'received_by_sorting_center' => false,
                    'hub_id' => $locked->hub_id,
                    'logistic_id' => $locked->logistic_id,
                    'notes' => implode(
                        "\n",
                        array_filter([
                            $newNotes,
                            $existingNotes,
                        ])
                    ),
                ]);

                app(OrderFlowSyncService::class)
                    ->syncShipment($locked);

                /*
                |----------------------------------------------------------
                | NOTIFY LOGISTICS
                |----------------------------------------------------------
                */

                $locked->loadMissing([
                    'logistic.owner',
                    'hub',
                ]);

                $logisticOwner =
                    $locked->logistic?->owner;

                if ($logisticOwner) {
                    $tracking =
                        $locked->tracking_number
                        ?: 'Shipment #' . $locked->id;

                    $hubName =
                        $locked->hub?->name
                        ?? 'Sorting Center';

                    $this->createNotification(
                        userId: (int) $logisticOwner->id,
                        title:
                            'Parcel Awaiting Sorting Center Confirmation',
                        message:
                            "Rider {$rider->name} delivered shipment {$tracking} to {$hubName}. Awaiting physical receipt confirmation.",
                        type: 'shipment'
                    );
                }
            });

            $shipment->refresh();
            $this->loadShipmentRelations($shipment);

            return response()->json([
                'success' => true,
                'message' =>
                    'Parcel arrived at the sorting center. Awaiting Logistics confirmation.',
                'data' => $this->formatShipment(
                    $shipment,
                    $rider
                ),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                'Unable to hand over parcel to the sorting center.',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SHARED AUTHORIZATION
    |--------------------------------------------------------------------------
    */

    private function authenticatedRider(Request $request): User
    {
        $rider = $request->user();

        abort_unless(
            $rider,
            401,
            'Unauthenticated.'
        );

        abort_unless(
            $rider->isRider(),
            403,
            'This account is not a rider account.'
        );

        return $rider;
    }

    private function isShipmentAssignedToRider(
        Shipment $shipment,
        int $riderId
    ): bool {
        return $this->isFinalMileRider(
            $shipment,
            $riderId
        ) || $this->isSellerPickupRider(
            $shipment,
            $riderId
        );
    }

    private function isSellerPickupRider(
        Shipment $shipment,
        int $riderId
    ): bool {
        return (int) ($shipment->pickup_rider_id ?? 0)
            === $riderId;
    }

    private function isFinalMileRider(
        Shipment $shipment,
        int $riderId
    ): bool {
        return (int) ($shipment->rider_id ?? 0)
            === $riderId;
    }

    private function validateNotFinalized(
        Shipment $shipment
    ): void {
        if (
            in_array(
                $shipment->status,
                [
                    'delivered',
                    'completed',
                    'delivery_failed',
                    'failed',
                    'cancelled',
                    'canceled',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'This shipment is already finalized.',
            ]);
        }
    }

    private function validateProofEligibility(
        Shipment $shipment,
        User $rider
    ): void {
        abort_unless(
            $this->isFinalMileRider(
                $shipment,
                (int) $rider->id
            ),
            403,
            'This shipment is not assigned to you for final delivery.'
        );

        if (
            $shipment->status !== 'out_for_delivery' ||
            $shipment->hub_picked_up_at === null ||
            (int) ($shipment->hub_pickup_rider_id ?? 0)
                !== (int) $rider->id ||
            !$shipment->received_by_sorting_center ||
            $shipment->sorting_status !== 'staged' ||
            $shipment->staged_at === null
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'Complete the hub QR pickup and start final delivery before uploading proof.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ASSIGNMENT TYPE FOR FLUTTER
    |--------------------------------------------------------------------------
    */

    private function assignmentType(
        Shipment $shipment,
        int $riderId
    ): string {
        $isFinalMile =
            $this->isFinalMileRider($shipment, $riderId);

        $isPickup =
            $this->isSellerPickupRider($shipment, $riderId);

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

    /*
    |--------------------------------------------------------------------------
    | FORMAT SHIPMENT RESPONSE FOR FLUTTER
    |--------------------------------------------------------------------------
    |
    | IMPORTANT: id is Shipment ID, not Order ID.
    |
    */

    private function formatShipment(
        Shipment $shipment,
        User $rider
    ): array {
        $sellerOrder = $shipment->sellerOrder;
        $order = $sellerOrder?->order;
        $customer = $order?->user;

        $assignmentType = $this->assignmentType(
            $shipment,
            (int) $rider->id
        );

        $isFinalMile = $this->isFinalMileRider(
            $shipment,
            (int) $rider->id
        );

        $isPickup = $this->isSellerPickupRider(
            $shipment,
            (int) $rider->id
        );

        return [
            'id' => $shipment->id,
            'shipment_id' => $shipment->id,
            'seller_order_id' =>
                $shipment->seller_order_id,

            'tracking_number' =>
                $shipment->tracking_number ?? '',

            'assignment_type' => $assignmentType,
            'is_final_mile_rider' => $isFinalMile,
            'is_pickup_rider' => $isPickup,

            'pickup_rider_id' =>
                $shipment->pickup_rider_id,

            'rider_id' => $shipment->rider_id,

            'rider_name' =>
                $shipment->rider?->name
                ?? ($isFinalMile ? $rider->name : ''),

            'customer_name' =>
                $customer?->name ?? 'Customer',

            'customer_phone' =>
                $customer?->mobile_number
                ?? $customer?->phone
                ?? '',

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
            |--------------------------------------------------------------
            | SELLER QR SCAN
            |--------------------------------------------------------------
            */

            'seller_scan_confirmed_at' =>
                $shipment->seller_scan_confirmed_at
                    ?->toISOString(),

            'scanned_at_seller' =>
                $shipment->scanned_at_seller
                    ?->toISOString(),

            'scanned_by_rider_id' =>
                $shipment->scanned_by_rider_id,

            'seller_pickup_scan_confirmed' =>
                $isPickup &&
                $shipment->seller_scan_confirmed_at !== null &&
                $shipment->scanned_at_seller !== null &&
                (int) ($shipment->scanned_by_rider_id ?? 0)
                    === (int) $rider->id,

            /*
            |--------------------------------------------------------------
            | HUB QR SCAN
            |--------------------------------------------------------------
            */

            'hub_picked_up_at' =>
                $shipment->hub_picked_up_at
                    ?->toISOString(),

            'hub_pickup_rider_id' =>
                $shipment->hub_pickup_rider_id,

            'hub_pickup_scan_confirmed' =>
                $isFinalMile &&
                $shipment->hub_picked_up_at !== null &&
                (int) ($shipment->hub_pickup_rider_id ?? 0)
                    === (int) $rider->id,

            /*
            |--------------------------------------------------------------
            | SORTING CENTER
            |--------------------------------------------------------------
            */

            'received_by_sorting_center' =>
                (bool) (
                    $shipment->received_by_sorting_center
                    ?? false
                ),

            'sorting_status' =>
                $shipment->sorting_status,

            'staged_at' =>
                $shipment->staged_at
                    ?->toISOString(),

            'at_sorting_center_at' =>
                $shipment->at_sorting_center_at
                    ?->toISOString(),

            /*
            |--------------------------------------------------------------
            | PROOF
            |--------------------------------------------------------------
            */

            'has_proof_of_delivery' =>
                !empty($shipment->proof_of_delivery),

            'proof_of_delivery' =>
                $shipment->proof_of_delivery,

            'delivery_signature' =>
                $shipment->delivery_signature,

            'delivered_to' =>
                $shipment->delivered_to,

            'delivery_notes' =>
                $shipment->notes,

            /*
            |--------------------------------------------------------------
            | LOCATION
            |--------------------------------------------------------------
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
            |--------------------------------------------------------------
            | DATE FIELDS
            |--------------------------------------------------------------
            */

            'order_date' =>
                $order?->created_at?->toISOString(),

            'created_at' =>
                $shipment->created_at?->toISOString(),

            'updated_at' =>
                $shipment->updated_at?->toISOString(),

            'picked_up_at' =>
                $shipment->picked_up_at?->toISOString(),

            'delivered_at' =>
                $shipment->delivered_at?->toISOString(),

            'delivery_earnings' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SHARED RESPONSE / NOTIFICATION HELPERS
    |--------------------------------------------------------------------------
    */

    private function loadShipmentRelations(
        Shipment $shipment
    ): void {
        $shipment->load([
            'sellerOrder.order.user',
            'sellerOrder.seller',
            'logistic',
            'hub',
            'rider',
        ]);
    }

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

    private function error(
        string $message,
        int $status
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
