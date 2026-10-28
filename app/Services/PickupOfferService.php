<?php

namespace App\Services;

use App\Models\PickupRequest;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PickupOfferService
{
    /**
     * Expire unanswered pickup offers and immediately offer each shipment
     * to the next eligible rider.
     *
     * This is safe to call from the scheduler and from normal web requests.
     */
    public static function processExpiredOffers(?int $shipmentId = null): int
    {
        $query = PickupRequest::query()
            ->where('status', 'offered')
            ->where('expires_at', '<=', now());

        if ($shipmentId !== null) {
            $query->where('shipment_id', $shipmentId);
        }

        $shipmentIds = $query
            ->orderBy('id')
            ->pluck('shipment_id')
            ->unique()
            ->values();

        foreach ($shipmentIds as $expiredShipmentId) {
            $shipment = Shipment::find($expiredShipmentId);

            if ($shipment) {
                self::offerNext($shipment);
            }
        }

        return $shipmentIds->count();
    }


    /**
     * Retry pickup-offer generation for ready-for-pickup shipments in a hub.
     *
     * This covers cases where no eligible rider existed when the seller first
     * marked the order ready, but a rider later comes online or is assigned to
     * the correct barangay hub.
     */
    public static function retryPendingForHub(int $logisticId, int $hubId): int
    {
        $shipments = Shipment::query()
            ->where('logistic_id', $logisticId)
            ->where('status', 'pending')
            ->whereNull('pickup_rider_id')
            ->whereNull('rider_id')
            ->where('received_by_sorting_center', false)
            ->whereNull('picked_up_at')
            ->whereHas('sellerOrder', function ($query) {
                $query->where('status', 'ready_for_pickup');
            })
            ->where(function ($query) use ($hubId) {
                $query->where('origin_hub_id', $hubId)
                    ->orWhere(function ($fallback) use ($hubId) {
                        $fallback->whereNull('origin_hub_id')
                            ->where('hub_id', $hubId);
                    });
            })
            ->orderBy('id')
            ->get();

        $offered = 0;

        foreach ($shipments as $shipment) {
            if (self::offerNext($shipment)) {
                $offered++;
            }
        }

        return $offered;
    }


    public static function offerNext(Shipment $shipment): ?PickupRequest
    {
        return DB::transaction(function () use ($shipment) {
            $shipment = Shipment::query()
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Expire unanswered offers even if the shipment is no longer eligible.
            PickupRequest::query()
                ->where('shipment_id', $shipment->id)
                ->where('status', 'offered')
                ->where('expires_at', '<=', now())
                ->update([
                    'status' => 'expired',
                    'responded_at' => now(),
                ]);

            if (
                $shipment->status !== 'pending' ||
                !$shipment->seller_order_id ||
                !$shipment->logistic_id ||
                !($shipment->origin_hub_id ?: $shipment->hub_id) ||
                $shipment->pickup_rider_id !== null ||
                $shipment->rider_id !== null ||
                $shipment->received_by_sorting_center ||
                $shipment->picked_up_at !== null
            ) {
                return null;
            }

            $sellerOrder = $shipment->sellerOrder;

            if (!$sellerOrder || $sellerOrder->status !== 'ready_for_pickup') {
                return null;
            }

            // Seller location, never buyer delivery zone.
            $pickupZone = SellerPickupZoneService::resolve($sellerOrder);

            if (!$pickupZone) {
                return null;
            }

            $active = PickupRequest::query()
                ->where('shipment_id', $shipment->id)
                ->where('status', 'offered')
                ->where('expires_at', '>', now())
                ->first();

            if ($active) {
                return $active;
            }

            $previousRiderIds = PickupRequest::query()
                ->where('shipment_id', $shipment->id)
                ->pluck('rider_id');

            $rider = User::query()
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'rider');
                })
                ->where('status', User::STATUS_ACTIVE)
                ->where('logistic_status', 'approved')
                ->where('logistic_id', $shipment->logistic_id)
                ->where('hub_id', $shipment->origin_hub_id ?: $shipment->hub_id)
                ->where('availability_status', 'available')
                ->where('max_capacity', '>', 0)
                ->whereColumn('current_load', '<', 'max_capacity')
                ->whereNotIn('id', $previousRiderIds)
                ->whereRaw(
                    'LOWER(TRIM(assigned_zone)) = ?',
                    [$pickupZone]
                )
                ->orderBy('current_load')
                ->orderBy('id')
                ->first();

            if (!$rider) {
                // No eligible rider: logistics must review dispatch.
                return null;
            }


            $offer = PickupRequest::create([
                'shipment_id' => $shipment->id,
                'rider_id' => $rider->id,
                'status' => 'offered',
                'offered_at' => now(),
                'expires_at' => now()->addMinutes(10),
            ]);

            \App\Models\Notification::create([
                'user_id' => $rider->id,
                'title' => 'New Seller Pickup Request',
                'message' => 'You have a new pickup request in '
                    . $pickupZone
                    . '. Please accept or decline within 10 minutes.',
                'type' => 'order',
                'link' => route('rider.pickups'),
            ]);

            return $offer;
        });
    }
}
