<?php

namespace App\Services;

use App\Models\PickupRequest;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PickupAcceptanceService
{
    public static function accept(PickupRequest $request, User $rider): void
    {
        DB::transaction(function () use ($request, $rider) {
            // Consistent locking order: shipment, request, rider.
            $shipment = Shipment::query()
                ->whereKey($request->shipment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $offer = PickupRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (int) $offer->shipment_id !== (int) $shipment->id ||
                (int) $offer->rider_id !== (int) $rider->id
            ) {
                throw new RuntimeException('This pickup offer does not belong to you.');
            }

            if (
                $offer->status !== 'offered' ||
                !$offer->expires_at ||
                $offer->expires_at->isPast()
            ) {
                throw new RuntimeException('This pickup offer is no longer valid.');
            }

            if (
                $shipment->status !== 'pending' ||
                !$shipment->seller_order_id ||
                !$shipment->logistic_id ||
                $shipment->pickup_rider_id !== null ||
                $shipment->rider_id !== null ||
                $shipment->received_by_sorting_center ||
                $shipment->picked_up_at !== null
            ) {
                throw new RuntimeException('Shipment is not available for seller pickup.');
            }

            $lockedRider = User::query()
                ->whereKey($rider->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !$lockedRider->roles()->where('name', 'rider')->exists() ||
                $lockedRider->status !== User::STATUS_ACTIVE ||
                $lockedRider->logistic_status !== 'approved' ||
                (int) $lockedRider->logistic_id !== (int) $shipment->logistic_id ||
                $lockedRider->availability_status !== 'available'
            ) {
                throw new RuntimeException('Rider is not eligible for this pickup.');
            }

            $pickupHubId = $shipment->origin_hub_id ?: $shipment->hub_id;

            if (
                !$pickupHubId ||
                (int) $lockedRider->hub_id !== (int) $pickupHubId
            ) {
                throw new RuntimeException(
                    'Rider must belong to the shipment pickup hub.'
                );
            }

            $sellerOrder = $shipment->sellerOrder;

            if (!$sellerOrder || $sellerOrder->status !== 'ready_for_pickup') {
                throw new RuntimeException(
                    'Seller order is no longer ready for pickup.'
                );
            }

            $pickupZone = SellerPickupZoneService::resolve($sellerOrder);

            if (
                !$pickupZone ||
                SellerPickupZoneService::normalize($lockedRider->assigned_zone) !== $pickupZone
            ) {
                throw new RuntimeException(
                    'Rider is not assigned to the seller pickup zone.'
                );
            }

            $currentLoad = (int) $lockedRider->current_load;
            $capacity = (int) $lockedRider->max_capacity;

            if ($capacity <= 0 || $currentLoad >= $capacity) {
                throw new RuntimeException('Rider has reached maximum capacity.');
            }

            // Pickup assignment only. Final-mile rider_id stays null.
            $shipment->update([
                'pickup_rider_id' => $lockedRider->id,
                'status' => 'assigned',
            ]);

            $offer->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            $newLoad = $currentLoad + 1;

            $lockedRider->update([
                'current_load' => $newLoad,
                'availability_status' => $newLoad >= $capacity
                    ? 'busy'
                    : 'available',
            ]);
        });
    }

    public static function decline(PickupRequest $request, User $rider): void
    {
        DB::transaction(function () use ($request, $rider) {
            $shipment = Shipment::query()
                ->whereKey($request->shipment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $offer = PickupRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $offer->rider_id !== (int) $rider->id) {
                throw new RuntimeException('This pickup offer does not belong to you.');
            }

            if (
                $offer->status !== 'offered' ||
                !$offer->expires_at ||
                $offer->expires_at->isPast()
            ) {
                throw new RuntimeException(
                    'This pickup offer is no longer active.'
                );
            }

            $offer->update([
                'status' => 'declined',
                'responded_at' => now(),
            ]);
        });

        // Offer to another eligible rider after the decline commits.
        PickupOfferService::offerNext(
            Shipment::findOrFail($request->shipment_id)
        );
    }
}
