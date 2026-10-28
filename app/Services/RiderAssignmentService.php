<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RiderAssignmentService
{
    /**
     * Find an available final-mile rider from the same
     * logistics company, sorting hub, and delivery zone.
     */
    public static function findBestRiderForShipment(Shipment $shipment): ?User
    {
        if (
            $shipment->logistic_id === null ||
            ($shipment->destination_hub_id === null && $shipment->hub_id === null) ||
            $shipment->rider_id !== null ||
            !$shipment->delivery_zone ||
            $shipment->sorting_status !== 'staged' ||
            !$shipment->received_by_sorting_center
        ) {
            return null;
        }

        return User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('status', User::STATUS_ACTIVE)
            ->where('logistic_status', 'approved')
            ->where('logistic_id', $shipment->logistic_id)
            ->where('hub_id', $shipment->destination_hub_id ?: $shipment->hub_id)
            ->where('availability_status', 'available')
            ->where('max_capacity', '>', 0)
            ->whereColumn('current_load', '<', 'max_capacity')
            ->whereRaw(
                'LOWER(TRIM(assigned_zone)) = ?',
                [mb_strtolower(trim((string) $shipment->delivery_zone))]
            )
            ->orderBy('current_load')
            ->orderBy('id')
            ->first();
    }

    /**
     * Assign a final-mile rider exactly once per shipment.
     * Pickup rider assignment remains separate.
     */
    public static function assignRiderToShipment(
        Shipment $shipment,
        User $rider
    ): void {
        DB::transaction(function () use ($shipment, $rider) {

            $lockedShipment = Shipment::query()
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedShipment->rider_id !== null) {
                throw new RuntimeException(
                    'This shipment already has an assigned rider.'
                );
            }

            if (
                $lockedShipment->status === 'failed_return_at_sorting_center' &&
                (
                    $lockedShipment->failed_return_received_at === null ||
                    !$lockedShipment->received_by_sorting_center
                )
            ) {
                throw new RuntimeException(
                    'Logistics must confirm receipt of the failed parcel before rider reassignment.'
                );
            }

            if (
                $lockedShipment->sorting_status !== 'staged' ||
                !$lockedShipment->received_by_sorting_center
            ) {
                throw new RuntimeException(
                    'Shipment must be received and staged at the sorting center before rider assignment.'
                );
            }

            if ($lockedShipment->logistic_id === null) {
                throw new RuntimeException(
                    'This shipment does not have a logistics company.'
                );
            }

            if (in_array($lockedShipment->status, [
                'delivered',
                'cancelled',
                'canceled',
                'delivery_failed',
            ], true)) {
                throw new RuntimeException(
                    'This shipment cannot be assigned in its current status.'
                );
            }

            $lockedShipment->loadMissing('sellerOrder.order');

            if (!$lockedShipment->sellerOrder?->order) {
                throw new RuntimeException(
                    'Shipment is not linked to a valid seller order.'
                );
            }

            $isFailedReturn =
                $lockedShipment->status === 'failed_return_at_sorting_center' ||
                $lockedShipment->failed_returned_to_sorting_center_at !== null ||
                $lockedShipment->failed_return_received_at !== null;

            if ($isFailedReturn) {
                if (
                    $lockedShipment->status !== 'failed_return_at_sorting_center' ||
                    $lockedShipment->failed_return_received_at === null ||
                    !$lockedShipment->received_by_sorting_center
                ) {
                    throw new RuntimeException(
                        'Logistics must confirm receipt of the failed parcel before rider reassignment.'
                    );
                }

                if (
                    $lockedShipment->sellerOrder->reschedule_status
                    !== 'approved'
                ) {
                    throw new RuntimeException(
                        'This failed-return shipment requires an approved seller-specific reschedule.'
                    );
                }
            }

            $lockedRider = User::query()
                ->whereKey($rider->id)
                ->lockForUpdate()
                ->firstOrFail();

            $isApprovedRider = $lockedRider->roles()
                ->where('name', 'rider')
                ->exists();

            if (
                !$isApprovedRider ||
                $lockedRider->status !== User::STATUS_ACTIVE ||
                $lockedRider->logistic_status !== 'approved'
            ) {
                throw new RuntimeException(
                    'Only active and approved riders can be assigned to shipments.'
                );
            }

            if (
                $lockedRider->logistic_id === null ||
                (int) $lockedRider->logistic_id
                !== (int) $lockedShipment->logistic_id
            ) {
                throw new RuntimeException(
                    'Rider does not belong to this logistics company.'
                );
            }

            // Final-mile rider must belong to the destination barangay hub.
            $destinationHubId = $lockedShipment->destination_hub_id
                ?: $lockedShipment->hub_id;

            if (
                !$destinationHubId ||
                (int) $lockedRider->hub_id !== (int) $destinationHubId
            ) {
                throw new RuntimeException(
                    'Delivery rider must belong to the shipment destination hub.'
                );
            }

            // Keep legacy delivery-zone validation only when both values exist.
            // Hub membership is the primary dispatch boundary now.
            $shipmentZone = mb_strtolower(
                trim((string) $lockedShipment->delivery_zone)
            );

            $riderZone = mb_strtolower(
                trim((string) $lockedRider->assigned_zone)
            );

            if (
                $shipmentZone !== '' &&
                $riderZone !== '' &&
                $shipmentZone !== $riderZone
            ) {
                throw new RuntimeException(
                    'Delivery rider is assigned to a different delivery zone.'
                );
            }

            if ($lockedRider->availability_status !== 'available') {
                throw new RuntimeException(
                    'Rider is currently unavailable.'
                );
            }

            $currentLoad = (int) $lockedRider->current_load;
            $maxCapacity = (int) $lockedRider->max_capacity;

            if (
                $maxCapacity <= 0 ||
                $currentLoad >= $maxCapacity
            ) {
                throw new RuntimeException(
                    'Rider has reached maximum capacity.'
                );
            }

            $shipmentStatus = in_array(
                $lockedShipment->status,
                [
                    'at_sorting_center',
                    'failed_return_at_sorting_center',
                ],
                true
            ) ? 'staged' : 'assigned';

            // Assign final-mile rider only.
            $lockedShipment->update([
                'rider_id' => $lockedRider->id,
                'status' => $shipmentStatus,
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
