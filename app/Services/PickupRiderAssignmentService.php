<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PickupRiderAssignmentService
{
    public static function assign(Shipment $shipment, User $rider): void
    {
        DB::transaction(function () use ($shipment, $rider) {
            $lockedShipment = Shipment::query()
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !$lockedShipment->seller_order_id ||
                !$lockedShipment->logistic_id
            ) {
                throw new RuntimeException(
                    'Shipment must belong to a seller order and logistics company.'
                );
            }

            if (
                $lockedShipment->pickup_rider_id !== null ||
                $lockedShipment->rider_id !== null
            ) {
                throw new RuntimeException(
                    'This shipment already has an assigned rider.'
                );
            }

            if (
                $lockedShipment->status !== 'pending' ||
                $lockedShipment->received_by_sorting_center ||
                $lockedShipment->picked_up_at !== null
            ) {
                throw new RuntimeException(
                    'Only pending seller pickup shipments can be assigned.'
                );
            }

            $lockedRider = User::query()
                ->whereKey($rider->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedRider->roles()
                ->where('name', 'rider')
                ->exists()) {
                throw new RuntimeException(
                    'Selected user is not a rider.'
                );
            }

            if (
                $lockedRider->status !== User::STATUS_ACTIVE ||
                $lockedRider->logistic_status !== 'approved' ||
                (int) $lockedRider->logistic_id !==
                (int) $lockedShipment->logistic_id
            ) {
                throw new RuntimeException(
                    'Rider must be active, approved, and belong to this logistics company.'
                );
            }

            $pickupHubId = $lockedShipment->origin_hub_id
                ?: $lockedShipment->hub_id;

            if (
                !$pickupHubId ||
                (int) $lockedRider->hub_id !== (int) $pickupHubId
            ) {
                throw new RuntimeException(
                    'Pickup rider must belong to the shipment origin hub.'
                );
            }

            if ($lockedRider->availability_status !== 'available') {
                throw new RuntimeException(
                    'Rider is unavailable.'
                );
            }

            $currentLoad = (int) $lockedRider->current_load;
            $maxCapacity = (int) $lockedRider->max_capacity;

            if ($maxCapacity <= 0 || $currentLoad >= $maxCapacity) {
                throw new RuntimeException(
                    'Rider has reached maximum capacity.'
                );
            }

            $lockedShipment->update([
                'pickup_rider_id' => $lockedRider->id,
                'status' => 'assigned',
            ]);

            $newLoad = $currentLoad + 1;

            $lockedRider->update([
                'current_load' => $newLoad,
                'availability_status' => $newLoad >= $maxCapacity
                    ? 'busy'
                    : 'available',
            ]);
        });
    }
}
