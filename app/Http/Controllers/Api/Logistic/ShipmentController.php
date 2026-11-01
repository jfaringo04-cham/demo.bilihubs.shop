<?php

namespace App\Http\Controllers\Api\Logistic;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\User;
use App\Services\OrderFlowSyncService;
use App\Services\RiderAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ShipmentController extends Controller
{
    /**
     * GET /api/logistic/shipments
     */
    public function index(Request $request): JsonResponse
    {
        $logistic = $request->user()?->ownedLogistic;

        if (!$logistic) {
            return response()->json([
                'success' => false,
                'message' => 'No logistics company found for this account.',
            ], 404);
        }

        $query = Shipment::query()
            ->where('logistic_id', $logistic->id)
            ->with([
                'sellerOrder.order.user',
                'sellerOrder.seller',
                'rider',
                'hub',
            ]);

        if ($request->filled('status')) {
            $status = trim((string) $request->input('status'));

            $query->where('status', $status);
        }

        if ($request->filled('sorting_status')) {
            $sortingStatus = trim(
                (string) $request->input('sorting_status')
            );

            $query->where('sorting_status', $sortingStatus);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($query) use ($search) {
                $query->where(
                    'tracking_number',
                    'like',
                    '%' . $search . '%'
                )
                    ->orWhereHas(
                        'sellerOrder.order',
                        function ($orderQuery) use ($search) {
                            $orderQuery->where(
                                'order_number',
                                'like',
                                '%' . $search . '%'
                            );
                        }
                    )
                    ->orWhereHas(
                        'sellerOrder.order.user',
                        function ($userQuery) use ($search) {
                            $userQuery
                                ->where(
                                    'name',
                                    'like',
                                    '%' . $search . '%'
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    '%' . $search . '%'
                                );
                        }
                    );
            });
        }

        $perPage = (int) $request->input('per_page', 20);

        if ($perPage < 1) {
            $perPage = 20;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $shipments = $query
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,

            'data' => collect($shipments->items())
                ->map(fn (Shipment $shipment) => $this->shipmentData($shipment))
                ->values(),

            'meta' => [
                'current_page' => $shipments->currentPage(),
                'last_page' => $shipments->lastPage(),
                'per_page' => $shipments->perPage(),
                'total' => $shipments->total(),
            ],
        ]);
    }

    /**
     * GET /api/logistic/shipments/{shipment}
     */
    public function show(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        $logistic = $request->user()?->ownedLogistic;

        if (
            !$logistic ||
            (int) $shipment->logistic_id !== (int) $logistic->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Shipment does not belong to your logistics company.',
            ], 403);
        }

        $shipment->load([
            'sellerOrder.order.user',
            'sellerOrder.seller',
            'sellerOrder.items.product',
            'rider',
            'hub',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->shipmentData($shipment),
        ]);
    }

    /**
     * POST /api/logistic/shipments/{shipment}/receive
     */
    public function receive(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        $logistic = $request->user()?->ownedLogistic;

        if (
            !$logistic ||
            (int) $shipment->logistic_id !== (int) $logistic->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Shipment does not belong to your logistics company.',
            ], 403);
        }

        $isFailedReturn =
            $shipment->status === 'failed_return_at_sorting_center';

        if (!$isFailedReturn) {
            if (
                $shipment->status !== 'at_sorting_center' ||
                !$shipment->pickup_rider_id ||
                !$shipment->picked_up_at ||
                !$shipment->at_sorting_center_at
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'The pickup rider must collect and deliver the parcel '
                        . 'to the sorting center before it can be received.',
                ], 422);
            }
        }

        try {
            DB::transaction(function () use (
                $shipment,
                $logistic
            ) {
                $lockedShipment = Shipment::query()
                    ->whereKey($shipment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $lockedShipment->logistic_id
                    !== (int) $logistic->id
                ) {
                    throw new RuntimeException(
                        'Shipment logistics ownership has changed.'
                    );
                }

                if ($lockedShipment->received_by_sorting_center) {
                    throw new RuntimeException(
                        'This shipment has already been received.'
                    );
                }

                $lockedShipment->loadMissing([
                    'sellerOrder.order',
                    'sellerOrder.seller',
                    'hub',
                ]);

                $sellerOrder = $lockedShipment->sellerOrder;
                $order = $sellerOrder?->order;

                if (!$sellerOrder || !$order) {
                    throw new RuntimeException(
                        'Shipment is not linked to a valid seller order.'
                    );
                }

                $isFailedReturn =
                    $lockedShipment->status
                    === 'failed_return_at_sorting_center';

                if (
                    !$isFailedReturn &&
                    !in_array(
                        $lockedShipment->status,
                        [
                            'in_transit',
                            'at_sorting_center',
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Shipment must be in transit or handed over to '
                        . 'the sorting center before receipt.'
                    );
                }

                $riderIdToRelease = $isFailedReturn
                    ? $lockedShipment->rider_id
                    : $lockedShipment->pickup_rider_id;

                if ($isFailedReturn && !$riderIdToRelease) {
                    throw new RuntimeException(
                        'Failed-return parcel has no assigned delivery rider.'
                    );
                }

                if ($riderIdToRelease) {
                    $rider = User::query()
                        ->whereKey($riderIdToRelease)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $newLoad = max(
                        0,
                        (int) $rider->current_load - 1
                    );

                    $capacity = $rider->max_capacity;

                    $rider->update([
                        'current_load' => $newLoad,

                        'availability_status' =>
                            $capacity === null ||
                            $newLoad < (int) $capacity
                                ? 'available'
                                : 'busy',
                    ]);

                    if (!$isFailedReturn) {
                        $rider->increment(
                            'daily_pickups_completed'
                        );
                    }
                }

                $lockedShipment->update([
                    'sorting_status' => 'received',
                    'received_at' => now(),
                    'at_sorting_center_at' => now(),
                    'received_by_sorting_center' => true,
                    'rider_id' => null,

                    'status' => $isFailedReturn
                        ? 'failed_return_at_sorting_center'
                        : 'at_sorting_center',

                    ...(
                        $isFailedReturn
                            ? [
                                'failed_return_received_at'
                                    => now(),
                            ]
                            : []
                    ),
                ]);
            });

            $shipment->refresh();

            app(OrderFlowSyncService::class)
                ->syncShipment($shipment);

            $this->sendReceiveNotifications($shipment);

            $shipment->load([
                'sellerOrder.order.user',
                'sellerOrder.seller',
                'rider',
                'hub',
            ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'Shipment received successfully.',
                'data' => $this->shipmentData($shipment),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to receive shipment.',
            ], 500);
        }
    }

    /**
     * POST /api/logistic/shipments/{shipment}/scan
     *
     * Required:
     * delivery_zone
     * delivery_type = standard | same_day | express
     */
    public function scan(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        $logistic = $request->user()?->ownedLogistic;

        if (
            !$logistic ||
            (int) $shipment->logistic_id !== (int) $logistic->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Shipment does not belong to your logistics company.',
            ], 403);
        }

        $validated = $request->validate([
            'delivery_zone' => [
                'required',
                'string',
                'max:255',

                Rule::exists(
                    'users',
                    'assigned_zone'
                )->where(function ($query) use ($logistic) {
                    $query
                        ->where(
                            'logistic_id',
                            $logistic->id
                        )
                        ->where(
                            'logistic_status',
                            'approved'
                        )
                        ->where(
                            'status',
                            User::STATUS_ACTIVE
                        );
                }),
            ],

            'delivery_type' => [
                'required',
                Rule::in([
                    'standard',
                    'same_day',
                    'express',
                ]),
            ],
        ]);

        try {
            DB::transaction(function () use (
                $shipment,
                $logistic,
                $validated
            ) {
                $lockedShipment = Shipment::query()
                    ->whereKey($shipment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $lockedShipment->logistic_id
                    !== (int) $logistic->id
                ) {
                    throw new RuntimeException(
                        'Shipment ownership changed.'
                    );
                }

                if (
                    !$lockedShipment
                        ->received_by_sorting_center ||
                    $lockedShipment->sorting_status
                    !== 'received'
                ) {
                    throw new RuntimeException(
                        'Shipment must be received before scanning.'
                    );
                }

                if ($lockedShipment->rider_id !== null) {
                    throw new RuntimeException(
                        'Shipment still has an assigned rider.'
                    );
                }

                $lockedShipment->update([
                    'sorting_status' => 'scanned',
                    'scanned_at' => now(),

                    'delivery_zone' =>
                        $validated['delivery_zone'],

                    'delivery_type' =>
                        $validated['delivery_type'],
                ]);
            });

            $shipment->refresh();

            return response()->json([
                'success' => true,
                'message' =>
                    'Shipment scanned successfully.',
                'data' => $this->shipmentData($shipment),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to scan shipment.',
            ], 500);
        }
    }

    /**
     * POST /api/logistic/shipments/{shipment}/sort
     *
     * Required:
     * sorting_area
     * rack_number
     */
    public function sort(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        $logistic = $request->user()?->ownedLogistic;

        if (
            !$logistic ||
            (int) $shipment->logistic_id !== (int) $logistic->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Shipment does not belong to your logistics company.',
            ], 403);
        }

        $validated = $request->validate([
            'sorting_area' => [
                'required',
                'string',
                'max:255',
            ],

            'rack_number' => [
                'required',
                'string',
                'max:50',
            ],
        ]);

        try {
            DB::transaction(function () use (
                $shipment,
                $logistic,
                $validated
            ) {
                $lockedShipment = Shipment::query()
                    ->whereKey($shipment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $lockedShipment->logistic_id
                    !== (int) $logistic->id
                ) {
                    throw new RuntimeException(
                        'Shipment ownership changed.'
                    );
                }

                if (
                    !$lockedShipment
                        ->received_by_sorting_center ||
                    $lockedShipment->sorting_status
                    !== 'scanned'
                ) {
                    throw new RuntimeException(
                        'Shipment must be scanned before sorting.'
                    );
                }

                $lockedShipment->update([
                    'sorting_status' => 'sorted',

                    'sorting_area' =>
                        $validated['sorting_area'],

                    'rack_number' =>
                        $validated['rack_number'],

                    'sorted_at' => now(),
                ]);
            });

            $shipment->refresh();

            return response()->json([
                'success' => true,
                'message' =>
                    'Shipment sorted successfully.',
                'data' => $this->shipmentData($shipment),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to sort shipment.',
            ], 500);
        }
    }

    /**
     * POST /api/logistic/shipments/{shipment}/stage
     */
    public function stage(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        $logistic = $request->user()?->ownedLogistic;

        if (
            !$logistic ||
            (int) $shipment->logistic_id !== (int) $logistic->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Shipment does not belong to your logistics company.',
            ], 403);
        }

        try {
            DB::transaction(function () use (
                $shipment,
                $logistic
            ) {
                $lockedShipment = Shipment::query()
                    ->whereKey($shipment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $lockedShipment->logistic_id
                    !== (int) $logistic->id
                ) {
                    throw new RuntimeException(
                        'Shipment ownership changed.'
                    );
                }

                if (
                    !$lockedShipment
                        ->received_by_sorting_center ||
                    $lockedShipment->sorting_status
                    !== 'sorted'
                ) {
                    throw new RuntimeException(
                        'Shipment must be sorted before staging.'
                    );
                }

                if ($lockedShipment->rider_id !== null) {
                    throw new RuntimeException(
                        'Cannot stage a shipment with an active rider.'
                    );
                }

                $lockedShipment->update([
                    'sorting_status' => 'staged',
                    'staged_at' => now(),
                ]);
            });

            $shipment->refresh();

            return response()->json([
                'success' => true,
                'message' =>
                    'Shipment staged for final-mile delivery.',
                'data' => $this->shipmentData($shipment),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to stage shipment.',
            ], 500);
        }
    }

    /**
     * POST /api/logistic/shipments/{shipment}/assign
     *
     * Required:
     * rider_id
     */
    public function assignRider(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        $logistic = $request->user()?->ownedLogistic;

        if (
            !$logistic ||
            (int) $shipment->logistic_id !== (int) $logistic->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Shipment does not belong to your logistics company.',
            ], 403);
        }

        $validated = $request->validate([
            'rider_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        if ($shipment->sorting_status !== 'staged') {
            return response()->json([
                'success' => false,
                'message' =>
                    'This shipment must be sorted and staged before '
                    . 'assigning a delivery rider.',
            ], 422);
        }

        if ($shipment->rider_id !== null) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This shipment already has a delivery rider assigned.',
            ], 422);
        }

        $shipment->loadMissing([
            'sellerOrder.order',
        ]);

        $order = $shipment->sellerOrder?->order;

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Shipment is not linked to a valid order.',
            ], 422);
        }

        if ($shipment->failed_return_received_at !== null) {
            $sellerOrder = $shipment->sellerOrder;

            if (
                !$sellerOrder ||
                $sellerOrder->reschedule_status !== 'approved'
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'This seller order requires an approved '
                        . 'reschedule before rider assignment.',
                ], 422);
            }

            if (
                $shipment->status
                !== 'failed_return_at_sorting_center'
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'The failed-return shipment is not in the '
                        . 'expected sorting-center state.',
                ], 422);
            }
        }

        $rider = User::find(
            $validated['rider_id']
        );

        if (!$rider) {
            return response()->json([
                'success' => false,
                'message' => 'Rider not found.',
            ], 404);
        }

        try {
            RiderAssignmentService::assignRiderToShipment(
                $shipment,
                $rider
            );

            $shipment->refresh();

            $this->createNotification(
                $rider->id,
                'New Delivery Assignment',
                'You have been assigned a new delivery. Tracking: '
                . $shipment->tracking_number,
                'shipment',
                null
            );

            $shipment->load([
                'sellerOrder.order.user',
                'sellerOrder.seller',
                'rider',
                'hub',
            ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'Rider ' . $rider->name
                    . ' assigned successfully.',
                'data' => $this->shipmentData($shipment),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to assign rider.',
            ], 500);
        }
    }

    private function shipmentData(
        Shipment $shipment
    ): array {
        $shipment->loadMissing([
            'sellerOrder.order.user',
            'sellerOrder.seller',
            'rider',
            'hub',
        ]);

        $sellerOrder = $shipment->sellerOrder;
        $order = $sellerOrder?->order;
        $customer = $order?->user;

        return [
            'id' => $shipment->id,
            'shipment_id' => $shipment->id,

            'tracking_number' =>
                $shipment->tracking_number,

            'tracking_code' =>
                $shipment->tracking_code,

            'status' => $shipment->status,

            'sorting_status' =>
                $shipment->sorting_status,

            'customer_name' =>
                $customer?->name,

            'customer_phone' =>
                $customer?->phone,

            'customer_email' =>
                $customer?->email,

            'delivery_address' =>
                $shipment->delivery_address,

            'pickup_address' =>
                $shipment->pickup_address,

            'delivery_zone' =>
                $shipment->delivery_zone,

            'delivery_type' =>
                $shipment->delivery_type,

            'sorting_area' =>
                $shipment->sorting_area,

            'rack_number' =>
                $shipment->rack_number,

            'order_number' =>
                $order?->order_number,

            'seller_order_id' =>
                $shipment->seller_order_id,

            'seller_name' =>
                $sellerOrder?->seller?->shop_name
                ?? $sellerOrder?->seller?->name,

            'hub_id' =>
                $shipment->hub_id,

            'hub_name' =>
                $shipment->hub?->name,

            'rider_id' =>
                $shipment->rider_id,

            'rider_name' =>
                $shipment->rider?->name,

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

            'received_by_sorting_center' =>
                (bool) $shipment
                    ->received_by_sorting_center,

            'received_at' =>
                optional($shipment->received_at)
                    ->toIso8601String(),

            'scanned_at' =>
                optional($shipment->scanned_at)
                    ->toIso8601String(),

            'sorted_at' =>
                optional($shipment->sorted_at)
                    ->toIso8601String(),

            'staged_at' =>
                optional($shipment->staged_at)
                    ->toIso8601String(),

            'picked_up_at' =>
                optional($shipment->picked_up_at)
                    ->toIso8601String(),

            'at_sorting_center_at' =>
                optional(
                    $shipment->at_sorting_center_at
                )->toIso8601String(),

            'delivered_at' =>
                optional($shipment->delivered_at)
                    ->toIso8601String(),

            'created_at' =>
                optional($shipment->created_at)
                    ->toIso8601String(),

            'updated_at' =>
                optional($shipment->updated_at)
                    ->toIso8601String(),
        ];
    }

    private function sendReceiveNotifications(
        Shipment $shipment
    ): void {
        $shipment->loadMissing([
            'sellerOrder.order',
            'sellerOrder.seller',
            'hub',
        ]);

        $sellerOrder = $shipment->sellerOrder;
        $order = $sellerOrder?->order;

        if (!$sellerOrder || !$order) {
            return;
        }

        $hubName = $shipment->hub?->name
            ?? 'Sorting Center';

        if ($sellerOrder->seller?->user_id) {
            $this->createNotification(
                $sellerOrder->seller->user_id,
                'Parcel Received at Sorting Center',
                'Parcel #' . $sellerOrder->id
                . ' from order '
                . $order->order_number
                . ' has arrived at '
                . $hubName
                . '.',
                'shipment',
                null
            );
        }

        if ($order->user_id) {
            $this->createNotification(
                $order->user_id,
                'Parcel Received at Sorting Center',
                'Parcel #' . $sellerOrder->id
                . ' from order '
                . $order->order_number
                . ' has arrived at '
                . $hubName
                . '.',
                'delivery',
                null
            );
        }
    }

    private function createNotification(
        int $userId,
        string $title,
        string $message,
        string $type = 'order',
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
}