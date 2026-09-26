<?php

namespace App\Services;

use App\Models\User;
use App\Models\Shipment;

class RiderAssignmentService
{
    public static function findBestRiderForShipment(Shipment $shipment): ?User
    {
        $logisticId = $shipment->logistic_id;
        $deliveryZone = $shipment->delivery_zone;
        $sortingArea = $shipment->sorting_area;
        $hubId = $shipment->hub_id; // Use shipment's hub_id (based on pickup location)

        $zoneMatchQuery = User::whereHas('roles', function ($query) {
            $query->where('name', 'rider');
        })
            ->where('logistic_id', $logisticId)
            ->where('availability_status', 'available')
            ->whereColumn('current_load', '<', 'max_capacity');

        if ($hubId) {
            $zoneMatchQuery->where('hub_id', $hubId);
        }

        if ($deliveryZone) {
            $zoneMatchQuery->where('assigned_zone', $deliveryZone);
        } elseif ($sortingArea) {
            $zoneMatchQuery->where('assigned_zone', $sortingArea);
        }

        $zoneMatch = $zoneMatchQuery->first();

        if ($zoneMatch) {
            return $zoneMatch;
        }

        $fallbackQuery = User::whereHas('roles', function ($query) {
            $query->where('name', 'rider');
        })
            ->where('logistic_id', $logisticId)
            ->where('availability_status', 'available')
            ->whereColumn('current_load', '<', 'max_capacity');

        if ($hubId) {
            $fallbackQuery->where('hub_id', $hubId);
        }

        return $fallbackQuery->first();
    }

    public static function assignRiderToShipment(Shipment $shipment, User $rider): void
    {
        $shipment->loadMissing('sellerOrder.order');

        $sellerOrder = $shipment->sellerOrder;
        $order = $sellerOrder?->order;

        if (!$sellerOrder || !$order) {
            throw new \RuntimeException(
                "Shipment #{$shipment->id} is not linked to a valid SellerOrder."
            );
        }

        $shipmentStatus = $shipment->status === 'at_sorting_center'
            ? 'staged'
            : 'assigned';

        $shipment->update([
            'rider_id' => $rider->id,
            'status' => $shipmentStatus,
            'assigned_at' => now(),
        ]);

        /*
         * Temporary parent-order synchronization.
         *
         * Shipment ownership now comes exclusively from:
         * Shipment -> SellerOrder -> Order.
         *
         * The legacy rider/status fields on orders are still updated because
         * other parts of the application may still read them during the
         * migration. They can be removed in a later cleanup once those reads
         * have also been transitioned.
         */
        $order->update([
            'rider_id' => $rider->id,
            'status' => 'assigned_to_rider',
            'delivery_status' => 'assigned_to_rider',
            'assigned_at' => now(),
        ]);

        $newLoad = $rider->current_load + 1;

        $rider->update([
            'current_load' => $newLoad,
            'availability_status' => $newLoad >= $rider->max_capacity
                ? 'busy'
                : 'available',
        ]);
    }
}
