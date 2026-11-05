<?php

namespace App\Services;

use App\Models\PickupRequest;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;

class PickupExceptionService
{
    /**
     * Read-only pickup status classification.
     * This service never changes shipments, orders, or rider assignments.
     *
     * @return array{code:string,label:string,message:string,severity:string}
     */
    public static function forSellerOrder(SellerOrder $sellerOrder): array
    {
        $sellerOrder->loadMissing('shipment');

        if (in_array($sellerOrder->status, [
            'cancelled', 'canceled', 'returned',
        ], true)) {
            return self::result(
                'not_applicable',
                'No Pickup Required',
                'This seller order is no longer awaiting pickup.',
                'secondary'
            );
        }

        $shipment = $sellerOrder->shipment;

        if ($shipment && $shipment->picked_up_at !== null) {
            return self::result(
                'picked_up',
                'Pickup Confirmed',
                'The parcel has been handed over to the pickup rider.',
                'success'
            );
        }

        if (!$shipment) {
            if (!in_array($sellerOrder->status, [
                'confirmed', 'preparing', 'ready_for_pickup',
            ], true)) {
                return self::result(
                    'preparing',
                    'Order Preparation',
                    'The seller order is not yet ready for pickup.',
                    'secondary'
                );
            }

            $route = HubAssignmentService::findRoutingHubsForSellerOrder(
                $sellerOrder
            );

            if (!$route['origin'] || !$route['destination']) {
                return self::result(
                    'no_valid_hub',
                    'Routing Assistance Required',
                    'No valid origin or destination hub could be resolved. Logistics assistance is required.',
                    'danger'
                );
            }

            return self::result(
                'not_ready',
                'Awaiting Seller Preparation',
                'A logistics route is available. Complete preparation before requesting pickup.',
                'secondary'
            );
        }

        return self::forShipment($shipment);
    }

    public static function forShipment(Shipment $shipment): array
    {
        if ($shipment->picked_up_at !== null) {
            return self::result(
                'picked_up',
                'Pickup Confirmed',
                'The initial seller pickup has been confirmed.',
                'success'
            );
        }

        if (in_array($shipment->status, [
            'cancelled', 'canceled',
        ], true)) {
            return self::result(
                'not_applicable',
                'Pickup Cancelled',
                'This shipment has been cancelled.',
                'secondary'
            );
        }

$originHubId = $shipment->origin_hub_id ?: $shipment->hub_id;

        if (
            !$originHubId ||
            !$shipment->destination_hub_id ||
            !$shipment->logistic_id
        ) {
            return self::result(
                'no_valid_hub',
                'Routing Assistance Required',
                'The shipment has an incomplete pickup route. Logistics must review it.',
                'danger'
            );
        }

        if ($shipment->pickup_rider_id !== null) {
            return self::result(
                'rider_assigned',
                'Rider Assigned',
                'A pickup rider is assigned. The parcel is awaiting physical collection.',
                'info'
            );
        }

        if ($shipment->status !== 'pending') {
            return self::result(
                'review_required',
                'Pickup Review Required',
                'The shipment state requires logistics review before pickup can continue.',
                'warning'
            );
        }

        $sellerOrder = $shipment->sellerOrder;

        if (!$sellerOrder || $sellerOrder->status !== 'ready_for_pickup') {
            return self::result(
                'not_ready',
                'Awaiting Seller Preparation',
                'The seller must mark the order Ready for Pickup.',
                'secondary'
            );
        }

        $activeOffer = PickupRequest::query()
            ->where('shipment_id', $shipment->id)
            ->where('status', 'offered')
            ->where('expires_at', '>', now())
            ->exists();

        if ($activeOffer) {
            return self::result(
                'offer_pending',
                'Waiting for Rider Response',
                'An eligible rider has an active pickup offer.',
                'info'
            );
        }

        // Show expired offers even when the scheduler has not processed them.
        $expiredOffer = PickupRequest::query()
            ->where('shipment_id', $shipment->id)
            ->where('status', 'offered')
            ->where('expires_at', '<=', now())
            ->exists();

        if ($expiredOffer) {
            return self::result(
                'offer_expired',
                'Pickup Offer Expired',
                'The rider offer deadline has passed. Logistics can retry dispatch.',
                'warning'
            );
        }
        $eligibleRiders = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('status', User::STATUS_ACTIVE)
            ->where('logistic_status', 'approved')
            ->where('logistic_id', $shipment->logistic_id)
            ->where('hub_id', $originHubId)
            ->where('availability_status', 'available')
            ->where('max_capacity', '>', 0)
            ->whereColumn('current_load', '<', 'max_capacity')
            ->exists();

        if (!$eligibleRiders) {
            return self::result(
                'no_available_rider',
                'Waiting for Available Rider',
                'No eligible pickup rider is currently available at the origin hub.',
                'warning'
            );
        }

        $lastOffer = PickupRequest::query()
            ->where('shipment_id', $shipment->id)
            ->latest('id')
            ->first();

        if (
            $lastOffer &&
            in_array($lastOffer->status, [
                'declined', 'expired',
            ], true)
        ) {
            return self::result(
                'reassignment_needed',
                'Pickup Reassignment Needed',
                'The previous pickup offer was not accepted. Logistics can retry rider dispatch.',
                'warning'
            );
        }

        if (
            $shipment->created_at &&
            $shipment->created_at->lte(now()->subHours(24))
        ) {
            return self::result(
                'pickup_delayed',
                'Pickup Follow-up Required',
                'This parcel has been waiting for pickup for at least 24 hours.',
                'warning'
            );
        }

        return self::result(
            'awaiting_dispatch',
            'Awaiting Pickup Dispatch',
            'The parcel is ready for dispatch to an eligible pickup rider.',
            'info'
        );
    }

    private static function result(
        string $code,
        string $label,
        string $message,
        string $severity
    ): array {
        return compact('code', 'label', 'message', 'severity');
    }
}
