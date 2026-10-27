<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\PickupRequest;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScannerController extends Controller
{
    /**
     * Scan a shipment waybill QR from the Rider mobile application.
     *
     * POST /api/rider/scan
     *
     * Expected body:
     *
     * {
     *     "qr_payload": "{\"t\":\"TRACKING\",\"q\":\"TOKEN\",\"so\":123}"
     * }
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

        // ============================================================
        // READ / NORMALIZE QR PAYLOAD
        // ============================================================

        $rawPayload = trim(
            (string) $validated['qr_payload']
        );

        $parsedPayload = $this->parseQrPayload(
            $rawPayload
        );

        if (!$parsedPayload['success']) {
            return response()->json([
                'success' => false,
                'message' => $parsedPayload['message'],
            ], 422);
        }

        $trackingNumber =
            $parsedPayload['tracking_number'];

        $qrToken =
            $parsedPayload['qr_token'];

        $sellerOrderId =
            $parsedPayload['seller_order_id'];

        // ============================================================
        // LOCATE SHIPMENT
        // ============================================================

        $shipment = Shipment::query()
            ->where(
                'tracking_number',
                $trackingNumber
            )
            ->where(
                'seller_order_id',
                $sellerOrderId
            )
            ->where(
                'qr_token',
                $qrToken
            )
            ->first();

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Shipment was not found or the QR code is invalid.',
            ], 404);
        }

        try {
            $result = DB::transaction(
                function () use (
                    $shipment,
                    $rider,
                    $trackingNumber,
                    $qrToken,
                    $sellerOrderId
                ) {
                    // ====================================================
                    // LOCK SHIPMENT
                    // ====================================================

                    $locked = Shipment::query()
                        ->whereKey(
                            $shipment->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                    $locked->load([
                        'sellerOrder.order.user',
                        'sellerOrder.seller',
                        'logistic',
                        'hub',
                    ]);

                    $sellerOrder =
                        $locked->sellerOrder;

                    $order =
                        $sellerOrder?->order;

                    if (
                        !$sellerOrder ||
                        !$order
                    ) {
                        return [
                            'success' => false,

                            'status_code' => 422,

                            'message' =>
                                'Shipment is not linked to a valid order.',
                        ];
                    }

                    // ====================================================
                    // VERIFY QR BELONGS TO THIS SHIPMENT
                    // ====================================================

                    if (
                        !hash_equals(
                            (string) $locked->tracking_number,
                            $trackingNumber
                        ) ||
                        (int) $locked->seller_order_id !==
                            (int) $sellerOrderId
                    ) {
                        return [
                            'success' => false,

                            'status_code' => 422,

                            'message' =>
                                'QR code does not belong to this shipment.',
                        ];
                    }

                    if (
                        !$locked->qr_token ||
                        !hash_equals(
                            strtoupper(
                                (string) $locked->qr_token
                            ),
                            strtoupper(
                                $qrToken
                            )
                        )
                    ) {
                        return [
                            'success' => false,

                            'status_code' => 422,

                            'message' =>
                                'Invalid QR token for this shipment.',
                        ];
                    }

                    // ====================================================
                    // DETERMINE RIDER SCAN TYPE
                    // ====================================================

                    $isPickupRider =
                        $locked->pickup_rider_id !== null &&
                        (int) $locked->pickup_rider_id ===
                            (int) $rider->id;

                    $isFinalMileRider =
                        $locked->rider_id !== null &&
                        (int) $locked->rider_id ===
                            (int) $rider->id;

                    if (
                        !$isPickupRider &&
                        !$isFinalMileRider
                    ) {
                        return [
                            'success' => false,

                            'status_code' => 403,

                            'message' =>
                                'This parcel is not assigned to you.',
                        ];
                    }

                    // ====================================================
                    // SELLER PICKUP SCAN
                    // ====================================================

                    if (
                        $isPickupRider &&
                        !$locked->received_by_sorting_center
                    ) {
                        return $this
                            ->processSellerPickup(
                                shipment: $locked,
                                rider: $rider,
                                sellerOrder: $sellerOrder,
                                order: $order,
                            );
                    }

                    // ====================================================
                    // FINAL-MILE HUB PICKUP SCAN
                    // ====================================================

                    if ($isFinalMileRider) {
                        return $this
                            ->processHubPickup(
                                shipment: $locked,
                                rider: $rider,
                                order: $order,
                            );
                    }

                    return [
                        'success' => false,

                        'status_code' => 422,

                        'message' =>
                            'This shipment cannot be scanned at its current stage.',
                    ];
                }
            );

            if (
                !isset($result['success']) ||
                $result['success'] !== true
            ) {
                return response()->json(
                    [
                        'success' => false,

                        'message' =>
                            $result['message'] ??
                            'Unable to scan shipment.',
                    ],
                    $result['status_code'] ?? 422
                );
            }

            // ============================================================
            // CREATE NOTIFICATIONS
            // ============================================================

            $this->createScanNotifications(
                $result,
                $rider
            );

            return response()->json([
                'success' => true,

                'message' =>
                    $result['message'],

                'scan_type' =>
                    $result['scan_type'],

                'shipment' =>
                    $result['shipment'],
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to process the scanned waybill.',
            ], 500);
        }
    }

    // ============================================================
    // PARSE QR PAYLOAD
    // ============================================================

    private function parseQrPayload(
        string $rawPayload
    ): array {
        if ($rawPayload === '') {
            return [
                'success' => false,
                'message' => 'QR code is empty.',
            ];
        }

        // ------------------------------------------------------------
        // 1. ORIGINAL BILIHUB JSON QR
        //
        // Shipment.php:
        //
        // {
        //     "t": "TRACKING",
        //     "q": "QR_TOKEN",
        //     "so": 123
        // }
        // ------------------------------------------------------------

        $decoded = json_decode(
            $rawPayload,
            true
        );

        if (is_array($decoded)) {
            return $this
                ->normalizeParsedQrData(
                    $decoded
                );
        }

        // ------------------------------------------------------------
        // 2. WEBSITE URL QR
        //
        // Examples:
        //
        // https://site.com/track?t=TRACKING&q=TOKEN&so=123
        //
        // or
        //
        // https://site.com/track?
        // tracking_number=TRACKING
        // &qr_token=TOKEN
        // &seller_order_id=123
        // ------------------------------------------------------------

        if (
            filter_var(
                $rawPayload,
                FILTER_VALIDATE_URL
            )
        ) {
            $queryString = parse_url(
                $rawPayload,
                PHP_URL_QUERY
            );

            $query = [];

            if (
                is_string($queryString) &&
                $queryString !== ''
            ) {
                parse_str(
                    $queryString,
                    $query
                );
            }

            if (!empty($query)) {
                $normalized = [
                    't' =>
                        $query['t'] ??
                        $query['tracking_number'] ??
                        $query['tracking'] ??
                        $query['tracking_code'] ??
                        null,

                    'q' =>
                        $query['q'] ??
                        $query['qr_token'] ??
                        $query['token'] ??
                        null,

                    'so' =>
                        $query['so'] ??
                        $query['seller_order_id'] ??
                        null,
                ];

                $result =
                    $this->normalizeParsedQrData(
                        $normalized
                    );

                if ($result['success']) {
                    return $result;
                }
            }

            // --------------------------------------------------------
            // URL PATH SUPPORT
            //
            // Example:
            //
            // /track/TRACKING/TOKEN/123
            // --------------------------------------------------------

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
                            trim(
                                $path,
                                '/'
                            )
                        ),
                        fn ($segment) =>
                            trim((string) $segment) !== ''
                    )
                );

                $trackIndex = array_search(
                    'track',
                    array_map(
                        'strtolower',
                        $segments
                    ),
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
                    $result =
                        $this->normalizeParsedQrData([
                            't' =>
                                urldecode(
                                    $segments[
                                        $trackIndex + 1
                                    ]
                                ),

                            'q' =>
                                urldecode(
                                    $segments[
                                        $trackIndex + 2
                                    ]
                                ),

                            'so' =>
                                urldecode(
                                    $segments[
                                        $trackIndex + 3
                                    ]
                                ),
                        ]);

                    if ($result['success']) {
                        return $result;
                    }
                }
            }

            return [
                'success' => false,

                'message' =>
                    'The website QR is missing the secure BiliHub '
                    . 'tracking number, QR token, or seller order ID.',
            ];
        }

        // ------------------------------------------------------------
        // 3. QUERY STRING STYLE WITHOUT FULL URL
        //
        // t=TRACKING&q=TOKEN&so=123
        // ------------------------------------------------------------

        if (
            str_contains(
                $rawPayload,
                '='
            ) &&
            str_contains(
                $rawPayload,
                '&'
            )
        ) {
            $query = [];

            parse_str(
                ltrim(
                    $rawPayload,
                    '?'
                ),
                $query
            );

            if (!empty($query)) {
                $result =
                    $this->normalizeParsedQrData([
                        't' =>
                            $query['t'] ??
                            $query['tracking_number'] ??
                            $query['tracking'] ??
                            $query['tracking_code'] ??
                            null,

                        'q' =>
                            $query['q'] ??
                            $query['qr_token'] ??
                            $query['token'] ??
                            null,

                        'so' =>
                            $query['so'] ??
                            $query['seller_order_id'] ??
                            null,
                    ]);

                if ($result['success']) {
                    return $result;
                }
            }
        }

        // ------------------------------------------------------------
        // TRACKING NUMBER ONLY IS NOT ACCEPTED
        //
        // Important:
        //
        // A Rider scan changes the shipment state.
        // Because of that, we require the secure qr_token and
        // seller_order_id instead of trusting only a tracking number.
        // ------------------------------------------------------------

        return [
            'success' => false,

            'message' =>
                'Invalid BiliHub QR code. Please scan the original '
                . 'waybill QR generated by BiliHub.',
        ];
    }

    // ============================================================
    // NORMALIZE PARSED QR DATA
    // ============================================================

    private function normalizeParsedQrData(
        array $data
    ): array {
        $trackingNumber = trim(
            (string) (
                $data['t'] ??
                $data['tracking_number'] ??
                $data['tracking'] ??
                $data['tracking_code'] ??
                ''
            )
        );

        $qrToken = trim(
            (string) (
                $data['q'] ??
                $data['qr_token'] ??
                $data['token'] ??
                ''
            )
        );

        $sellerOrderId = (int) (
            $data['so'] ??
            $data['seller_order_id'] ??
            0
        );

        if (
            $trackingNumber === '' ||
            $qrToken === '' ||
            $sellerOrderId <= 0
        ) {
            return [
                'success' => false,

                'message' =>
                    'Incomplete BiliHub waybill QR code.',
            ];
        }

        return [
            'success' => true,

            'tracking_number' =>
                $trackingNumber,

            'qr_token' =>
                $qrToken,

            'seller_order_id' =>
                $sellerOrderId,
        ];
    }

    // ============================================================
    // SELLER PICKUP
    // ============================================================

    private function processSellerPickup(
        Shipment $shipment,
        $rider,
        $sellerOrder,
        $order
    ): array {
        // ========================================================
        // ALREADY RECEIVED BY SORTING CENTER
        // ========================================================

        if (
            $shipment->received_by_sorting_center
        ) {
            return [
                'success' => false,

                'status_code' => 422,

                'message' =>
                    'This parcel has already been received by the sorting center.',
            ];
        }

        // ========================================================
        // VERIFY PICKUP ASSIGNMENT
        // ========================================================

        if (
            $shipment->pickup_rider_id === null ||
            (int) $shipment->pickup_rider_id !==
                (int) $rider->id
        ) {
            return [
                'success' => false,

                'status_code' => 403,

                'message' =>
                    'This parcel is not assigned to you for seller pickup.',
            ];
        }

        // ========================================================
        // PICKUP REQUEST
        // ========================================================

        $hasPickupRequests =
            PickupRequest::query()
                ->where(
                    'shipment_id',
                    $shipment->id
                )
                ->exists();

        if ($hasPickupRequests) {
            $hasAcceptedOffer =
                PickupRequest::query()
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
                return [
                    'success' => false,

                    'status_code' => 403,

                    'message' =>
                        'You must accept the pickup request before scanning this parcel.',
                ];
            }
        }

        // ========================================================
        // SHIPMENT STATUS
        // ========================================================

        if (
            $shipment->status !== 'assigned'
        ) {
            return [
                'success' => false,

                'status_code' => 422,

                'message' =>
                    'This parcel is not awaiting seller pickup.',
            ];
        }

        // ========================================================
        // UPDATE
        // ========================================================

        $shipment->update([
            'status' =>
                'in_transit',

            'scanned_at_seller' =>
                now(),

            'scanned_by_rider_id' =>
                $rider->id,

            'seller_scan_confirmed_at' =>
                now(),

            'picked_up_at' =>
                now(),
        ]);

        return [
            'success' => true,

            'scan_type' =>
                'seller_pickup',

            'message' =>
                'QR verified. Parcel picked up from the seller successfully.',

            'buyer_id' =>
                $order->user_id,

            'order_id' =>
                $order->id,

            'order_number' =>
                $order->order_number,

            'seller_order_id' =>
                $sellerOrder->id,

            'logistic_owner_id' =>
                $shipment->logistic
                    ?->owner_user_id,

            'shipment' =>
                $this->shipmentResponse(
                    $shipment,
                    $rider,
                    $order
                ),
        ];
    }

    // ============================================================
    // HUB / FINAL-MILE PICKUP
    // ============================================================

    private function processHubPickup(
        Shipment $shipment,
        $rider,
        $order
    ): array {
        // ========================================================
        // VERIFY FINAL-MILE RIDER
        // ========================================================

        if (
            $shipment->rider_id === null ||
            (int) $shipment->rider_id !==
                (int) $rider->id
        ) {
            return [
                'success' => false,

                'status_code' => 403,

                'message' =>
                    'This parcel is not assigned to you for final-mile delivery.',
            ];
        }

        // ========================================================
        // ALREADY PICKED UP
        // ========================================================

        if (
            $shipment->hub_picked_up_at !== null
        ) {
            return [
                'success' => false,

                'status_code' => 422,

                'message' =>
                    'Hub pickup has already been confirmed.',
            ];
        }

        // ========================================================
        // HUB STATE
        // ========================================================

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
            return [
                'success' => false,

                'status_code' => 422,

                'message' =>
                    'Parcel must be received, staged, and assigned before hub pickup.',
            ];
        }

        // ========================================================
        // LOGISTICS / HUB
        // ========================================================

        if (
            !$shipment->hub_id ||
            !$shipment->logistic_id
        ) {
            return [
                'success' => false,

                'status_code' => 422,

                'message' =>
                    'Parcel has no valid logistics hub assignment.',
            ];
        }

        $hub = $shipment->hub;

        if (
            !$hub ||
            (int) $hub->logistic_id !==
                (int) $shipment->logistic_id
        ) {
            return [
                'success' => false,

                'status_code' => 422,

                'message' =>
                    'Parcel hub does not belong to its logistics provider.',
            ];
        }

        // ========================================================
        // RIDER LOGISTICS COMPANY
        // ========================================================

        if (
            !$rider->logistic_id ||
            (int) $rider->logistic_id !==
                (int) $shipment->logistic_id
        ) {
            return [
                'success' => false,

                'status_code' => 403,

                'message' =>
                    'Rider does not belong to this logistics company.',
            ];
        }

        // ========================================================
        // RIDER HUB
        // ========================================================

        if (
            $rider->hub_id !== null &&
            (int) $rider->hub_id !==
                (int) $shipment->hub_id
        ) {
            return [
                'success' => false,

                'status_code' => 403,

                'message' =>
                    'Rider is assigned to a different hub.',
            ];
        }

        // ========================================================
        // DELIVERY ZONE
        // ========================================================

        if (
            $rider->delivery_zone !== null &&
            trim(
                (string) $rider->delivery_zone
            ) !== ''
        ) {
            if (
                mb_strtolower(
                    trim(
                        (string) $rider->delivery_zone
                    )
                ) !==
                mb_strtolower(
                    trim(
                        (string) $shipment->delivery_zone
                    )
                )
            ) {
                return [
                    'success' => false,

                    'status_code' => 403,

                    'message' =>
                        'Rider delivery zone does not match this parcel.',
                ];
            }
        }

        // ========================================================
        // UPDATE
        // ========================================================

        $shipment->status =
            'picked_up';

        $shipment->hub_picked_up_at =
            now();

        $shipment->hub_pickup_rider_id =
            $rider->id;

        $shipment->save();

        return [
            'success' => true,

            'scan_type' =>
                'hub_pickup',

            'message' =>
                'Hub QR verified. Parcel picked up for delivery.',

            'buyer_id' =>
                $order->user_id,

            'order_id' =>
                $order->id,

            'order_number' =>
                $order->order_number,

            'shipment' =>
                $this->shipmentResponse(
                    $shipment,
                    $rider,
                    $order
                ),
        ];
    }

    // ============================================================
    // MOBILE SHIPMENT RESPONSE
    // ============================================================

    private function shipmentResponse(
        Shipment $shipment,
        $rider,
        $order
    ): array {
        $shipment->refresh();

        $shipment->loadMissing([
            'sellerOrder.order.user',
            'sellerOrder.seller',
        ]);

        $customer =
            $shipment
                ->sellerOrder
                ?->order
                ?->user;

        return [
            'id' =>
                $shipment->id,

            'shipment_id' =>
                $shipment->id,

            'seller_order_id' =>
                $shipment->seller_order_id,

            'tracking_number' =>
                $shipment->tracking_number,

            'customer_name' =>
                $customer?->name ??
                'Customer',

            'customer_phone' =>
                $customer?->mobile_number ??
                $customer?->phone ??
                '',

            'delivery_address' =>
                $shipment->delivery_address ??
                '',

            'rider_id' =>
                (string) $rider->id,

            'rider_name' =>
                $rider->name ??
                'Rider',

            'status' =>
                $shipment->status,

            'order_date' =>
                $order?->created_at
                    ?->toISOString(),

            'latitude' =>
                $shipment->latitude !== null
                    ? (float) $shipment->latitude
                    : null,

            'longitude' =>
                $shipment->longitude !== null
                    ? (float) $shipment->longitude
                    : null,

            'picked_up_at' =>
                $shipment->picked_up_at
                    ?->toISOString(),

            'hub_picked_up_at' =>
                $shipment->hub_picked_up_at
                    ?->toISOString(),

            'created_at' =>
                $shipment->created_at
                    ?->toISOString(),

            'updated_at' =>
                $shipment->updated_at
                    ?->toISOString(),

            'delivery_earnings' =>
                null,
        ];
    }

    // ============================================================
    // NOTIFICATIONS
    // ============================================================

    private function createScanNotifications(
        array $result,
        $rider
    ): void {
        $buyerId =
            $result['buyer_id'] ??
            null;

        $orderId =
            $result['order_id'] ??
            null;

        $orderNumber =
            $result['order_number'] ??
            null;

        $shipment =
            $result['shipment'] ??
            [];

        $trackingNumber =
            $shipment['tracking_number'] ??
            '';

        if (
            $result['scan_type'] ===
            'seller_pickup'
        ) {
            if ($buyerId) {
                Notification::create([
                    'user_id' =>
                        $buyerId,

                    'title' =>
                        'Rider Picked Up Your Parcel',

                    'type' =>
                        'delivery',

                    'message' =>
                        'Parcel #' .
                        ($result['seller_order_id'] ?? '') .
                        ' from order ' .
                        ($orderNumber ?? '') .
                        ' has been picked up from the seller.',

                    'link' =>
                        $orderId
                            ? route(
                                'orders.show',
                                $orderId
                            )
                            : null,
                ]);
            }

            $logisticOwnerId =
                $result['logistic_owner_id'] ??
                null;

            if ($logisticOwnerId) {
                Notification::create([
                    'user_id' =>
                        $logisticOwnerId,

                    'title' =>
                        'Parcel Picked Up by Rider',

                    'type' =>
                        'shipment',

                    'message' =>
                        'Rider ' .
                        $rider->name .
                        ' picked up shipment ' .
                        $trackingNumber .
                        ' from the seller.',

                    'link' =>
                        null,
                ]);
            }

            return;
        }

        if (
            $result['scan_type'] ===
                'hub_pickup' &&
            $buyerId
        ) {
            Notification::create([
                'user_id' =>
                    $buyerId,

                'title' =>
                    'Parcel Picked Up for Delivery',

                'type' =>
                    'delivery',

                'message' =>
                    'Shipment ' .
                    $trackingNumber .
                    ' from order ' .
                    ($orderNumber ?? '') .
                    ' has been picked up from the sorting center for delivery.',

                'link' =>
                    $orderId
                        ? route(
                            'orders.show',
                            $orderId
                        )
                        : null,
            ]);
        }
    }
}