<?php

namespace App\Services;

use App\Models\Notification;
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
     * marked the order ready, but a rider later becomes available or is
     * assigned to the correct origin hub.
     */
    public static function retryPendingForHub(
        int $logisticId,
        int $hubId
    ): int {
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
                        $fallback
                            ->whereNull('origin_hub_id')
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

    public static function offerNext(
        Shipment $shipment
    ): ?PickupRequest {
        return DB::transaction(function () use ($shipment) {
            $shipment = Shipment::query()
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Expire unanswered offers even if the shipment is no longer
             * eligible.
             */
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

            if (
                !$sellerOrder ||
                $sellerOrder->status !== 'ready_for_pickup'
            ) {
                return null;
            }

            /*
             * Do not create another offer while an active one still exists.
             */
            $active = PickupRequest::query()
                ->where('shipment_id', $shipment->id)
                ->where('status', 'offered')
                ->where('expires_at', '>', now())
                ->first();

            if ($active) {
                return $active;
            }

            $pickupHubId =
                $shipment->origin_hub_id ?: $shipment->hub_id;

            /*
             * Pickup dispatch is hub-based.
             *
             * A rider is eligible when they:
             * - have the rider role
             * - are active and logistics-approved
             * - belong to the same logistics company
             * - belong to the shipment origin hub
             * - are available
             * - still have capacity
             *
             * Exact barangay assigned_zone matching is intentionally not
             * required because a hub may cover multiple barangays.
             */
            $baseRiderQuery = User::query()
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'rider');
                })
                ->where('status', User::STATUS_ACTIVE)
                ->where('logistic_status', 'approved')
                ->where('logistic_id', $shipment->logistic_id)
                ->where('hub_id', $pickupHubId)
                ->where('availability_status', 'available')
                ->where('max_capacity', '>', 0)
                ->whereColumn(
                    'current_load',
                    '<',
                    'max_capacity'
                );

            /*
             * Riders already offered this shipment in the current history.
             */
            $previousRiderIds = PickupRequest::query()
                ->where('shipment_id', $shipment->id)
                ->pluck('rider_id');

            /*
             * First pass:
             * try a rider that has not received this shipment before.
             */
            $rider = (clone $baseRiderQuery)
                ->whereNotIn('id', $previousRiderIds)
                ->orderBy('current_load')
                ->orderBy('id')
                ->first();

            /*
             * Everyone has already been tried.
             * Start a new rotation cycle instead of stopping permanently.
             */
            if (!$rider) {
                $rider = (clone $baseRiderQuery)
                    ->orderBy('current_load')
                    ->orderBy('id')
                    ->first();
            }

            if (!$rider) {
                // No eligible rider currently available.
                return null;
            }

            $offer = PickupRequest::create([
                'shipment_id' => $shipment->id,
                'rider_id' => $rider->id,
                'status' => 'offered',
                'offered_at' => now(),
                'expires_at' => now()->addMinutes(10),
            ]);

            /*
             * One notification per shipment per rider.
             *
             * If this shipment is offered to the same rider again later,
             * update the existing notification instead of creating another
             * duplicate notification.
             */
            $trackingNumber =
                $shipment->tracking_number
                ?: 'Shipment #' . $shipment->id;

            $notificationMessage =
                'Pickup ' . $trackingNumber
                . ' is waiting for your response. '
                . 'Please accept or decline within 10 minutes.';

            $notification = Notification::query()
                ->where('user_id', $rider->id)
                ->where('title', 'New Seller Pickup Request')
                ->where(
                    'message',
                    $notificationMessage
                )
                ->first();

            if ($notification) {
                $notification->update([
                    'type' => 'order',
                    'link' => route('rider.pickups'),
                    'updated_at' => now(),
                ]);
            } else {
                Notification::create([
                    'user_id' => $rider->id,
                    'title' => 'New Seller Pickup Request',
                    'message' => $notificationMessage,
                    'type' => 'order',
                    'link' => route('rider.pickups'),
                ]);
            }

            return $offer;
        });
    }
}
