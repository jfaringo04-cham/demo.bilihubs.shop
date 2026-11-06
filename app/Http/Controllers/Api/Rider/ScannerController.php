<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\PickupRequest;
use App\Models\Shipment;
use App\Services\OrderFlowSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScannerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SCAN BILIHUB WAYBILL QR
    |--------------------------------------------------------------------------
    |
    | POST /api/rider/scan
    |
    | Body:
    | {
    |   "qr_payload": "{\"t\":\"TRACKING\",\"q\":\"TOKEN\",\"so\":123}"
    | }
    |
    | Seller pickup:
    |   assigned -> in_transit
    |
    | Final-mile hub pickup:
    |   assigned/staged/at_sorting_center -> picked_up
    |
    | A scan is a status-changing operation.
    | All authorization is enforced on the server.
    |
    */

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_payload' => [
                'required',
                'string',
                'max:4096',
            ],
        ]);

        $rider = $request->user();

        if (!$rider) {
            return $this->error(
                'Unauthenticated.',
                401
            );
        }

        if (!$rider->isRider()) {
            return $this->error(
                'This account is not a rider account.',
                403
            );
        }

        $parsed = $this->parseQrPayload(
            trim($validated['qr_payload'])
        );

        if (!$parsed['success']) {
            return $this->error(
                $parsed['message'],
                422
            );
        }

        $tracking = $parsed['tracking_number'];
        $token = $parsed['qr_token'];
        $sellerOrderId = $parsed['seller_order_id'];

        try {
            /*
            |--------------------------------------------------------------
            | IDENTIFY THE EXACT SHIPMENT
            |--------------------------------------------------------------
            */

            $shipment = Shipment::query()
                ->where('tracking_number', $tracking)
                ->where('seller_order_id', $sellerOrderId)
                ->where('qr_token', $token)
                ->first();

            if (!$shipment) {
                return $this->error(
                    'Shipment not found or invalid BiliHub QR code.',
                    404
                );
            }

            /*
            |--------------------------------------------------------------
            | LOCK + VERIFY + UPDATE
            |--------------------------------------------------------------
            */

            $result = DB::transaction(function () use (
                $shipment,
                $rider,
                $tracking,
                $token,
                $sellerOrderId
            ) {
                $locked = Shipment::query()
                    ->whereKey($shipment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $locked->load([
                    'sellerOrder.order.user',
                    'sellerOrder.seller',
                    'logistic',
                    'hub',
                    'rider',
                ]);

                $sellerOrder = $locked->sellerOrder;
                $order = $sellerOrder?->order;

                if (!$sellerOrder || !$order) {
                    return $this->failure(
                        'Shipment is not linked to a valid order.',
                        422
                    );
                }

                /*
                |----------------------------------------------------------
                | VERIFY QR CONTENT AGAIN UNDER LOCK
                |----------------------------------------------------------
                */

                if (
                    !hash_equals(
                        (string) $locked->tracking_number,
                        $tracking
                    ) ||
                    (int) $locked->seller_order_id !==
                        (int) $sellerOrderId ||
                    !$locked->qr_token ||
                    !hash_equals(
                        strtoupper((string) $locked->qr_token),
                        strtoupper($token)
                    )
                ) {
                    return $this->failure(
                        'The QR code does not match this shipment.',
                        422
                    );
                }

                /*
                |----------------------------------------------------------
                | IDENTIFY RIDER ASSIGNMENT
                |----------------------------------------------------------
                */

                $isPickupRider =
                    $locked->pickup_rider_id !== null &&
                    (int) $locked->pickup_rider_id ===
                        (int) $rider->id;

                $isFinalMileRider =
                    $locked->rider_id !== null &&
                    (int) $locked->rider_id ===
                        (int) $rider->id;

                if (!$isPickupRider && !$isFinalMileRider) {
                    return $this->failure(
                        'This parcel is not assigned to you.',
                        403
                    );
                }

                if ($this->isFinalized($locked)) {
                    return $this->failure(
                        'This shipment is already finalized.',
                        422
                    );
                }

                /*
                |----------------------------------------------------------
                | SELLER PICKUP
                |----------------------------------------------------------
                |
                | Even if the same rider later handles final-mile,
                | first pickup is processed before Logistics receives
                | the shipment.
                |
                */

                if (
                    $isPickupRider &&
                    !$locked->received_by_sorting_center
                ) {
                    return $this->processSellerPickup(
                        shipment: $locked,
                        rider: $rider,
                        sellerOrder: $sellerOrder,
                        order: $order
                    );
                }

                /*
                |----------------------------------------------------------
                | FINAL-MILE HUB PICKUP
                |----------------------------------------------------------
                */

                if ($isFinalMileRider) {
                    return $this->processHubPickup(
                        shipment: $locked,
                        rider: $rider,
                        order: $order
                    );
                }

                return $this->failure(
                    'This shipment cannot be scanned at its current stage.',
                    422
                );
            });

            if (($result['success'] ?? false) !== true) {
                return $this->error(
                    $result['message'] ??
                        'Unable to process the scanned waybill.',
                    $result['status_code'] ?? 422
                );
            }

            /*
            |--------------------------------------------------------------
            | NOTIFICATIONS
            |--------------------------------------------------------------
            |
            | Shipment update is committed before notifications.
            | Notification failure should not turn a successful
            | pickup into an apparent failed scan.
            |
            */

            try {
                $this->createScanNotifications(
                    $result,
                    $rider
                );
            } catch (\Throwable $notificationError) {
                report($notificationError);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'scan_type' => $result['scan_type'],
                'shipment' => $result['shipment'],
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                'Unable to process the scanned waybill.',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PARSE SECURE BILIHUB QR
    |--------------------------------------------------------------------------
    |
    | Supported:
    |
    | JSON:
    | {"t":"TRACKING","q":"TOKEN","so":123}
    |
    | URL:
    | https://site.com/track?t=TRACKING&q=TOKEN&so=123
    |
    | URL Path:
    | https://site.com/track/TRACKING/TOKEN/123
    |
    | Query String:
    | t=TRACKING&q=TOKEN&so=123
    |
    | Tracking number alone is NOT accepted.
    |
    */

    private function parseQrPayload(
        string $rawPayload
    ): array {
        if ($rawPayload === '') {
            return $this->failure(
                'QR code is empty.',
                422
            );
        }

        /*
        |--------------------------------------------------------------
        | JSON QR
        |--------------------------------------------------------------
        */

        $decoded = json_decode(
            $rawPayload,
            true
        );

        if (is_array($decoded)) {
            return $this->normalizeParsedQrData($decoded);
        }

        /*
        |--------------------------------------------------------------
        | WEBSITE URL QR
        |--------------------------------------------------------------
        */

        if (
            filter_var(
                $rawPayload,
                FILTER_VALIDATE_URL
            )
        ) {
            $scheme = strtolower(
                (string) parse_url(
                    $rawPayload,
                    PHP_URL_SCHEME
                )
            );

            if (!in_array(
                $scheme,
                ['http', 'https'],
                true
            )) {
                return $this->failure(
                    'Invalid QR URL scheme.',
                    422
                );
            }

            $queryString = parse_url(
                $rawPayload,
                PHP_URL_QUERY
            );

            if (
                is_string($queryString) &&
                $queryString !== ''
            ) {
                parse_str(
                    $queryString,
                    $query
                );

                $result = $this->normalizeParsedQrData(
                    $query
                );

                if ($result['success']) {
                    return $result;
                }
            }

            /*
            |----------------------------------------------------------
            | URL PATH QR
            |----------------------------------------------------------
            */

            $path = parse_url(
                $rawPayload,
                PHP_URL_PATH
            );

            if (
                is_string($path) &&
                trim($path) !== ''
            ) {
                $segments = array_values(
                    array_filter(
                        explode(
                            '/',
                            trim($path, '/')
                        ),
                        fn ($segment) =>
                            trim((string) $segment) !== ''
                    )
                );

                $lowerSegments = array_map(
                    'strtolower',
                    $segments
                );

                $trackIndex = array_search(
                    'track',
                    $lowerSegments,
                    true
                );

                if (
                    $trackIndex !== false &&
                    isset(
                        $segments[$trackIndex + 1],
                        $segments[$trackIndex + 2],
                        $segments[$trackIndex + 3]
                    )
                ) {
                    return $this->normalizeParsedQrData([
                        't' => urldecode(
                            $segments[$trackIndex + 1]
                        ),
                        'q' => urldecode(
                            $segments[$trackIndex + 2]
                        ),
                        'so' => urldecode(
                            $segments[$trackIndex + 3]
                        ),
                    ]);
                }
            }

            return $this->failure(
                'Website QR is missing tracking number, QR token, or seller order ID.',
                422
            );
        }

        /*
        |--------------------------------------------------------------
        | QUERY STRING QR
        |--------------------------------------------------------------
        */

        if (
            str_contains($rawPayload, '=') &&
            str_contains($rawPayload, '&')
        ) {
            parse_str(
                ltrim($rawPayload, '?'),
                $query
            );

            $result = $this->normalizeParsedQrData(
                $query
            );

            if ($result['success']) {
                return $result;
            }
        }

        return $this->failure(
            'Invalid BiliHub QR code. Scan the original secure waybill QR.',
            422
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE QR DATA
    |--------------------------------------------------------------------------
    */

    private function normalizeParsedQrData(
        array $data
    ): array {
        $tracking = trim((string) (
            $data['t'] ??
            $data['tracking_number'] ??
            $data['tracking'] ??
            $data['tracking_code'] ??
            ''
        ));

        $token = trim((string) (
            $data['q'] ??
            $data['qr_token'] ??
            $data['token'] ??
            ''
        ));

        $sellerOrderId = filter_var(
            $data['so'] ??
                $data['seller_order_id'] ??
                null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if (
            $tracking === '' ||
            $token === '' ||
            $sellerOrderId === false
        ) {
            return $this->failure(
                'Incomplete BiliHub waybill QR code.',
                422
            );
        }

        if (
            strlen($tracking) > 150 ||
            strlen($token) > 255
        ) {
            return $this->failure(
                'Invalid BiliHub waybill QR fields.',
                422
            );
        }

        return [
            'success' => true,
            'tracking_number' => $tracking,
            'qr_token' => $token,
            'seller_order_id' => (int) $sellerOrderId,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SELLER PICKUP SCAN
    |--------------------------------------------------------------------------
    |
    | Requires:
    | - Assigned pickup rider
    | - Accepted pickup offer, when requests exist
    | - Status = assigned
    | - Parcel not received by Logistics
    |
    | Effect:
    | - status = in_transit
    | - picked_up_at
    | - seller_scan_confirmed_at
    | - scanned_at_seller
    |
    */

    private function processSellerPickup(
        Shipment $shipment,
        $rider,
        $sellerOrder,
        $order
    ): array {
        if ($shipment->received_by_sorting_center) {
            return $this->failure(
                'Parcel already received by the sorting center.',
                422
            );
        }

        if (
            (int) ($shipment->pickup_rider_id ?? 0)
                !== (int) $rider->id
        ) {
            return $this->failure(
                'This parcel is not assigned to you for seller pickup.',
                403
            );
        }

        /*
        |--------------------------------------------------------------
        | ACCEPTED PICKUP OFFER
        |--------------------------------------------------------------
        */

        $hasPickupRequests = PickupRequest::query()
            ->where(
                'shipment_id',
                $shipment->id
            )
            ->exists();

        if ($hasPickupRequests) {
            $hasAcceptedOffer = PickupRequest::query()
                ->where(
                    'shipment_id',
                    $shipment->id
                )
                ->where(
                    'rider_id',
                    $rider->id
                )
                ->where(
                    'status',
                    'accepted'
                )
                ->exists();

            if (!$hasAcceptedOffer) {
                return $this->failure(
                    'Accept the pickup offer before scanning this parcel.',
                    403
                );
            }
        }

        if ($shipment->status !== 'assigned') {
            return $this->failure(
                'This parcel is not awaiting seller pickup.',
                422
            );
        }

        /*
        |--------------------------------------------------------------
        | UPDATE SHIPMENT
        |--------------------------------------------------------------
        */

        $shipment->update([
            'status' => 'in_transit',
            'scanned_at_seller' => now(),
            'scanned_by_rider_id' => $rider->id,
            'seller_scan_confirmed_at' => now(),
            'picked_up_at' =>
                $shipment->picked_up_at ?? now(),
        ]);

        /*
        |--------------------------------------------------------------
        | SYNC BUYER ORDER TRACKING
        |--------------------------------------------------------------
        */

        app(OrderFlowSyncService::class)
            ->syncShipment($shipment);

        return [
            'success' => true,
            'scan_type' => 'seller_pickup',
            'message' =>
                'Seller QR confirmed. Parcel picked up successfully and is now in transit.',
            'buyer_id' => $order->user_id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'seller_order_id' => $sellerOrder->id,
            'logistic_owner_id' =>
                $shipment->logistic?->owner_user_id,
            'shipment' => $this->shipmentResponse(
                $shipment,
                $rider,
                $order
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL-MILE HUB PICKUP SCAN
    |--------------------------------------------------------------------------
    |
    | Requires:
    | - Assigned final-mile rider
    | - Received by sorting center
    | - Sorting status staged
    | - Valid hub and logistics
    | - Matching rider logistics/hub/zone
    |
    | Effect:
    | - status = picked_up
    | - hub_picked_up_at
    | - hub_pickup_rider_id
    |
    */

    private function processHubPickup(
        Shipment $shipment,
        $rider,
        $order
    ): array {
        if (
            (int) ($shipment->rider_id ?? 0)
                !== (int) $rider->id
        ) {
            return $this->failure(
                'This parcel is not assigned to you for final-mile delivery.',
                403
            );
        }

        if ($shipment->hub_picked_up_at !== null) {
            return $this->failure(
                'Hub pickup has already been confirmed.',
                422
            );
        }

        /*
        |--------------------------------------------------------------
        | LOGISTICS PROCESSING REQUIREMENTS
        |--------------------------------------------------------------
        */

        if (
            !$shipment->received_by_sorting_center ||
            $shipment->sorting_status !== 'staged' ||
            $shipment->staged_at === null ||
            !in_array(
                $shipment->status,
                [
                    'assigned',
                    'staged',
                    'at_sorting_center',
                ],
                true
            )
        ) {
            return $this->failure(
                'Parcel must be received, staged, and assigned before hub pickup.',
                422
            );
        }

        /*
        |--------------------------------------------------------------
        | VALID HUB
        |--------------------------------------------------------------
        */

        if (
            !$shipment->hub_id ||
            !$shipment->logistic_id
        ) {
            return $this->failure(
                'Parcel has no valid logistics hub assignment.',
                422
            );
        }

        $hub = $shipment->hub;

        if (
            !$hub ||
            (int) $hub->logistic_id !==
                (int) $shipment->logistic_id
        ) {
            return $this->failure(
                'Parcel hub does not belong to its logistics provider.',
                422
            );
        }

        /*
        |--------------------------------------------------------------
        | RIDER LOGISTICS COMPANY
        |--------------------------------------------------------------
        */

        if (
            !$rider->logistic_id ||
            (int) $rider->logistic_id !==
                (int) $shipment->logistic_id
        ) {
            return $this->failure(
                'Rider does not belong to this logistics company.',
                403
            );
        }

        /*
        |--------------------------------------------------------------
        | RIDER HUB
        |--------------------------------------------------------------
        */

        if (
            $rider->hub_id !== null &&
            (int) $rider->hub_id !==
                (int) $shipment->hub_id
        ) {
            return $this->failure(
                'Rider is assigned to a different hub.',
                403
            );
        }

        /*
        |--------------------------------------------------------------
        | DELIVERY ZONE
        |--------------------------------------------------------------
        */

        $riderZone = trim(
            (string) ($rider->delivery_zone ?? '')
        );

        $shipmentZone = trim(
            (string) ($shipment->delivery_zone ?? '')
        );

        if (
            $riderZone !== '' &&
            mb_strtolower($riderZone) !==
                mb_strtolower($shipmentZone)
        ) {
            return $this->failure(
                'Rider delivery zone does not match this parcel.',
                403
            );
        }

        /*
        |--------------------------------------------------------------
        | UPDATE HUB PICKUP
        |--------------------------------------------------------------
        */

        $shipment->update([
            'status' => 'picked_up',
            'hub_picked_up_at' => now(),
            'hub_pickup_rider_id' => $rider->id,
        ]);

        /*
        |--------------------------------------------------------------
        | SYNC BUYER TRACKING
        |--------------------------------------------------------------
        */

        app(OrderFlowSyncService::class)
            ->syncShipment($shipment);

        return [
            'success' => true,
            'scan_type' => 'hub_pickup',
            'message' =>
                'Hub QR confirmed. Parcel collected for final-mile delivery.',
            'buyer_id' => $order->user_id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'shipment' => $this->shipmentResponse(
                $shipment,
                $rider,
                $order
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE FOR FLUTTER RIDER SCANNER
    |--------------------------------------------------------------------------
    */

    private function shipmentResponse(
        Shipment $shipment,
        $rider,
        $order
    ): array {
        $shipment->refresh();

        $shipment->loadMissing([
            'sellerOrder.order.user',
            'sellerOrder.seller',
            'rider',
            'hub',
        ]);

        $customer = $shipment
            ->sellerOrder
            ?->order
            ?->user;

        $isPickupRider =
            (int) ($shipment->pickup_rider_id ?? 0)
                === (int) $rider->id;

        $isFinalMileRider =
            (int) ($shipment->rider_id ?? 0)
                === (int) $rider->id;

        $assignmentType = match (true) {
            $isPickupRider && $isFinalMileRider =>
                'pickup_and_delivery',

            $isPickupRider =>
                'seller_pickup',

            $isFinalMileRider =>
                'final_mile',

            default =>
                'unknown',
        };

        return [
            /*
            |--------------------------------------------------------------
            | IDENTIFICATION
            |--------------------------------------------------------------
            */

            'id' => $shipment->id,
            'shipment_id' => $shipment->id,
            'seller_order_id' =>
                $shipment->seller_order_id,

            'tracking_number' =>
                $shipment->tracking_number,

            'assignment_type' => $assignmentType,

            'is_pickup_rider' => $isPickupRider,
            'is_final_mile_rider' => $isFinalMileRider,

            'pickup_rider_id' =>
                $shipment->pickup_rider_id,

            'rider_id' => $shipment->rider_id,

            'rider_name' =>
                $shipment->rider?->name ??
                ($isFinalMileRider ? $rider->name : ''),

            /*
            |--------------------------------------------------------------
            | CUSTOMER
            |--------------------------------------------------------------
            */

            'customer_name' =>
                $customer?->name ?? 'Customer',

            'customer_phone' =>
                $customer?->mobile_number ??
                $customer?->phone ??
                '',

            'delivery_address' =>
                $shipment->delivery_address ?? '',

            'pickup_address' =>
                $shipment->pickup_address ?? '',

            /*
            |--------------------------------------------------------------
            | SHIPMENT STATUS
            |--------------------------------------------------------------
            */

            'status' => $shipment->status,

            'buyer_order_status' =>
                $order?->status,

            'buyer_delivery_status' =>
                $order?->delivery_status,

            /*
            |--------------------------------------------------------------
            | SELLER QR
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
                $isPickupRider &&
                $shipment->seller_scan_confirmed_at !== null &&
                (int) ($shipment->scanned_by_rider_id ?? 0)
                    === (int) $rider->id,

            /*
            |--------------------------------------------------------------
            | HUB QR
            |--------------------------------------------------------------
            */

            'hub_picked_up_at' =>
                $shipment->hub_picked_up_at
                    ?->toISOString(),

            'hub_pickup_rider_id' =>
                $shipment->hub_pickup_rider_id,

            'hub_pickup_scan_confirmed' =>
                $isFinalMileRider &&
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
                $shipment->staged_at?->toISOString(),

            'at_sorting_center_at' =>
                $shipment->at_sorting_center_at
                    ?->toISOString(),

            /*
            |--------------------------------------------------------------
            | HUB
            |--------------------------------------------------------------
            */

            'hub_id' => $shipment->hub_id,
            'current_hub_id' => $shipment->hub_id,

            'current_hub_name' =>
                $shipment->hub?->name ?? '',

            'logistic_id' => $shipment->logistic_id,

            'delivery_zone' =>
                $shipment->delivery_zone ?? '',

            /*
            |--------------------------------------------------------------
            | ROUTING
            |--------------------------------------------------------------
            */

            'routing_status' =>
                $shipment->routing_status ?? '',

            'linehaul_status' =>
                $shipment->linehaul_status ?? '',

            /*
            |--------------------------------------------------------------
            | PROOF
            |--------------------------------------------------------------
            */

            'has_proof_of_delivery' =>
                !empty($shipment->proof_of_delivery),

            'delivered_to' =>
                $shipment->delivered_to,

            'delivery_notes' =>
                $shipment->notes,

            /*
            |--------------------------------------------------------------
            | DATES
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

            'delivery_earnings' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | BUYER AND LOGISTICS NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    private function createScanNotifications(
        array $result,
        $rider
    ): void {
        $buyerId = $result['buyer_id'] ?? null;
        $orderId = $result['order_id'] ?? null;
        $orderNumber =
            $result['order_number'] ?? '';

        $shipment = $result['shipment'] ?? [];

        $tracking =
            $shipment['tracking_number'] ?? '';

        /*
        |--------------------------------------------------------------
        | SELLER PICKUP NOTIFICATIONS
        |--------------------------------------------------------------
        */

        if (
            $result['scan_type'] === 'seller_pickup'
        ) {
            if ($buyerId) {
                Notification::create([
                    'user_id' => $buyerId,
                    'title' =>
                        'Rider Picked Up Your Parcel',
                    'type' => 'delivery',
                    'message' =>
                        'Parcel #' .
                        ($result['seller_order_id'] ?? '') .
                        ' from order ' .
                        $orderNumber .
                        ' has been picked up from the seller.',
                    'link' => $orderId
                        ? route('orders.show', $orderId)
                        : null,
                ]);
            }

            $logisticOwnerId =
                $result['logistic_owner_id'] ??
                null;

            if ($logisticOwnerId) {
                Notification::create([
                    'user_id' => $logisticOwnerId,
                    'title' =>
                        'Parcel Picked Up by Rider',
                    'type' => 'shipment',
                    'message' =>
                        'Rider ' .
                        $rider->name .
                        ' picked up shipment ' .
                        $tracking .
                        ' from the seller.',
                    'link' => null,
                ]);
            }

            return;
        }

        /*
        |--------------------------------------------------------------
        | FINAL-MILE HUB PICKUP NOTIFICATION
        |--------------------------------------------------------------
        */

        if (
            $result['scan_type'] === 'hub_pickup' &&
            $buyerId
        ) {
            Notification::create([
                'user_id' => $buyerId,
                'title' =>
                    'Parcel Picked Up for Delivery',
                'type' => 'delivery',
                'message' =>
                    'Shipment ' .
                    $tracking .
                    ' from order ' .
                    $orderNumber .
                    ' has been picked up from the sorting center for delivery.',
                'link' => $orderId
                    ? route('orders.show', $orderId)
                    : null,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function isFinalized(
        Shipment $shipment
    ): bool {
        return in_array(
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
        );
    }

    private function failure(
        string $message,
        int $statusCode
    ): array {
        return [
            'success' => false,
            'status_code' => $statusCode,
            'message' => $message,
        ];
    }

    private function error(
        string $message,
        int $statusCode
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $statusCode);
    }
}
