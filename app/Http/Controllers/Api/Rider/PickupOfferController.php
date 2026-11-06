<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\PickupRequest;
use App\Services\PickupAcceptanceService;
use App\Services\PickupOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PickupOfferController extends Controller
{
    /**
     * Display active pickup offers for the authenticated rider.
     *
     * These are offers only, not yet accepted assignments.
     */
    public function index(Request $request): JsonResponse
    {
        $rider = $request->user();

        if (!$rider || !$rider->isRider()) {
            return response()->json([
                'success' => false,
                'message' => 'Only riders can view pickup offers.',
            ], 403);
        }

        // Process expired offers belonging to this rider.
        // The service handles expiration and offer rotation.
        $expiredShipmentIds = PickupRequest::query()
            ->where('rider_id', $rider->id)
            ->where('status', 'offered')
            ->where('expires_at', '<=', now())
            ->pluck('shipment_id')
            ->unique();

        foreach ($expiredShipmentIds as $shipmentId) {
            PickupOfferService::processExpiredOffers(
                (int) $shipmentId
            );
        }

        $offers = PickupRequest::query()
            ->with([
                'shipment.sellerOrder.seller',
                'shipment.sellerOrder.order',
            ])
            ->where('rider_id', $rider->id)
            ->where('status', 'offered')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->whereHas('shipment', function ($query) {
                $query
                    ->where('status', 'pending')
                    ->whereNull('pickup_rider_id')
                    ->whereNull('rider_id')
                    ->where('received_by_sorting_center', false)
                    ->whereNull('picked_up_at')
                    ->whereHas('sellerOrder', function ($sellerOrder) {
                        $sellerOrder->where(
                            'status',
                            'ready_for_pickup'
                        );
                    });
            })
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get();

        $data = $offers->map(function (PickupRequest $offer) {
            $shipment = $offer->shipment;
            $sellerOrder = $shipment?->sellerOrder;
            $seller = $sellerOrder?->seller;

            return [
                'id' => $offer->id,
                'offer_id' => $offer->id,

                'shipment_id' => $offer->shipment_id,
                'tracking_number' =>
                    $shipment?->tracking_number,

                'seller_name' => $seller?->name
                    ?: 'Seller',

                'pickup_address' =>
                    $shipment?->pickup_address,

                'status' => $offer->status,

                'offered_at' => $offer->offered_at
                    ?->toIso8601String(),

                'expires_at' => $offer->expires_at
                    ?->toIso8601String(),

                'seconds_remaining' => (int) max(
                    0,
                    now()->diffInSeconds(
                        $offer->expires_at,
                        false
                    )
                ),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Active pickup offers retrieved successfully.',
            'data' => $data,
            'meta' => [
                'total' => $data->count(),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Accept a pickup offer.
     *
     * The existing web service performs the final checks,
     * locks the relevant records, assigns the pickup rider,
     * and updates the rider's current load.
     */
    public function accept(
        Request $request,
        PickupRequest $pickupRequest
    ): JsonResponse {
        $rider = $request->user();

        if (
            !$rider ||
            !$rider->isRider() ||
            (int) $pickupRequest->rider_id !== (int) $rider->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This pickup offer does not belong to you.',
            ], 403);
        }

        try {
            PickupAcceptanceService::accept(
                $pickupRequest,
                $rider
            );

            // Reload records after the transaction commits.
            $pickupRequest->refresh();

            $shipment = $pickupRequest->shipment;

            return response()->json([
                'success' => true,
                'message' =>
                    'Pickup accepted successfully. '
                    . 'You are now assigned to collect this parcel.',
                'data' => [
                    'offer_id' => $pickupRequest->id,
                    'shipment_id' =>
                        $pickupRequest->shipment_id,
                    'tracking_number' =>
                        $shipment?->tracking_number,
                    'pickup_rider_id' => $rider->id,
                    'offer_status' => 'accepted',
                    'shipment_status' => 'assigned',
                ],
            ]);
        } catch (RuntimeException $e) {
            // Match the expiration behavior used by
            // the existing Laravel web controller.
            if (
                $pickupRequest->expires_at &&
                $pickupRequest->expires_at->isPast()
            ) {
                PickupOfferService::processExpiredOffers(
                    (int) $pickupRequest->shipment_id
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'This pickup offer has expired. '
                        . 'It may be offered to another eligible rider.',
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * Decline a pickup offer.
     *
     * The existing service changes the offer to declined
     * and attempts to offer the shipment to another rider.
     */
    public function decline(
        Request $request,
        PickupRequest $pickupRequest
    ): JsonResponse {
        $rider = $request->user();

        if (
            !$rider ||
            !$rider->isRider() ||
            (int) $pickupRequest->rider_id !== (int) $rider->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This pickup offer does not belong to you.',
            ], 403);
        }

        try {
            PickupAcceptanceService::decline(
                $pickupRequest,
                $rider
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Pickup declined successfully. '
                    . 'The system will try another eligible rider.',
                'data' => [
                    'offer_id' => $pickupRequest->id,
                    'shipment_id' =>
                        $pickupRequest->shipment_id,
                    'offer_status' => 'declined',
                ],
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
