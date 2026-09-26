<?php

namespace App\Services;

use App\Models\Hub;
use App\Models\Logistic;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use App\Models\Shipment;

class HubAssignmentService
{
    /**
     * Legacy order-level hub lookup.
     * Kept temporarily for compatibility with existing controllers/services.
     */
    public static function findBestHubForOrder(Order $order): ?Hub
    {
        $sellerLat = null;
        $sellerLng = null;

        $seller = $order->items->first()?->product?->user ?? null;

        if ($seller) {
            $sellerLat = $seller->latitude ?? null;
            $sellerLng = $seller->longitude ?? null;
        }

        if (!$sellerLat || !$sellerLng) {
            $customerLat = $order->customer_latitude;
            $customerLng = $order->customer_longitude;

            if ($customerLat && $customerLng) {
                $sellerLat = $customerLat;
                $sellerLng = $customerLng;
            }
        }

        if (!$sellerLat || !$sellerLng) {
            return self::fallbackHub();
        }

        return self::findBestHubByCoordinates($sellerLat, $sellerLng);
    }

    /**
     * Seller-order-level hub lookup.
     *
     * Each SellerOrder represents one seller/shop parcel, so hub selection
     * is based on that parcel's seller pickup location.
     */
    public static function findBestHubForSellerOrder(SellerOrder $sellerOrder): ?Hub
    {
        $sellerOrder->loadMissing([
            'order',
            'seller.owner',
            'seller.pickupAddress',
        ]);

        $seller = $sellerOrder->seller;
        $owner = $seller?->owner;
        $pickupAddress = $seller?->pickupAddress;

        $sellerLat = $pickupAddress?->latitude;
        $sellerLng = $pickupAddress?->longitude;

        if (!$sellerLat || !$sellerLng) {
            $sellerLat = $owner?->latitude;
            $sellerLng = $owner?->longitude;
        }

        if (!$sellerLat || !$sellerLng) {
            $sellerLat = $sellerOrder->order?->customer_latitude;
            $sellerLng = $sellerOrder->order?->customer_longitude;
        }

        if (!$sellerLat || !$sellerLng) {
            return self::fallbackHub();
        }

        return self::findBestHubByCoordinates((float) $sellerLat, (float) $sellerLng);
    }

    public static function findBestHubByCoordinates(float $lat, float $lng): ?Hub
    {
        $hubs = Hub::where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($hubs->isEmpty()) {
            return self::fallbackHub();
        }

        $bestHub = null;
        $bestDistance = null;

        foreach ($hubs as $hub) {
            $distance = self::calculateDistance($lat, $lng, $hub->latitude, $hub->longitude);

            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestHub = $hub;
            }
        }

        return $bestHub;
    }

    public static function fallbackHub(): ?Hub
    {
        return Hub::where('status', 'active')->first();
    }

    public static function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Compatibility entry point for callers that still pass a parent Order.
     *
     * The actual shipment/rider assignment is now performed per SellerOrder
     * so a multi-seller Order never creates one shared order-level shipment.
     */
    public static function autoAssignRiderToOrder(Order $order): ?User
    {
        $order->loadMissing('sellerOrders');

        $firstAssignedRider = null;

        foreach ($order->sellerOrders as $sellerOrder) {
            $rider = self::autoAssignRiderToSellerOrder($sellerOrder);

            if (!$firstAssignedRider && $rider) {
                $firstAssignedRider = $rider;
            }
        }

        return $firstAssignedRider;
    }

    /**
     * Assign a hub/rider to one SellerOrder parcel.
     * Creates a shipment only for that SellerOrder when one does not exist.
     */
    public static function autoAssignRiderToSellerOrder(SellerOrder $sellerOrder): ?User
    {
        $sellerOrder->loadMissing([
            'order',
            'seller.owner',
            'seller.pickupAddress',
            'shipment',
        ]);

        $order = $sellerOrder->order;
        $hub = self::findBestHubForSellerOrder($sellerOrder);

        if (!$order || !$hub) {
            return null;
        }

        $shipment = $sellerOrder->shipment;

        if ($shipment && !$shipment->rider_id) {
            $shipment->update([
                'logistic_id' => $hub->logistic_id,
                'hub_id' => $hub->id,
            ]);
        } elseif (!$shipment) {
            $trackingNumber = 'SPE-' . strtoupper(uniqid());

            $seller = $sellerOrder->seller;
            $owner = $seller?->owner;
            $pickupAddress = $seller?->pickupAddress;

            $pickupAddressText = $pickupAddress
                ? implode(', ', array_filter([
                    $pickupAddress->address_line1 ?? null,
                    $pickupAddress->address_line2 ?? null,
                    $pickupAddress->city ?? null,
                    $pickupAddress->province ?? null,
                    $pickupAddress->postal_code ?? null,
                    $pickupAddress->country ?? null,
                ]))
                : implode(', ', array_filter([
                    $owner?->business_name,
                    $owner?->street_address,
                    $owner?->barangay,
                    $owner?->municipality,
                    $owner?->province,
                ]));

            $shipment = Shipment::create([
                'seller_order_id' => $sellerOrder->id,
                'logistic_id' => $hub->logistic_id,
                'hub_id' => $hub->id,
                'tracking_number' => $trackingNumber,
                'status' => 'pending',
                'pickup_address' => $pickupAddressText ?: 'Seller address',
                'delivery_address' => $order->shipping_address,
                'notes' => 'Auto-assigned hub based on SellerOrder pickup location: ' . $hub->name,
            ]);

            if (!$sellerOrder->logistic_id) {
                $sellerOrder->update(['logistic_id' => $hub->logistic_id]);
            }
        }

        $rider = self::findBestRiderForHub($hub);

        if ($rider) {
            \App\Services\RiderAssignmentService::assignRiderToShipment($shipment, $rider);

            \App\Models\Notification::create([
                'user_id' => $rider->id,
                'title' => 'New Delivery Assigned from Hub',
                'type' => 'delivery',
                'message' => 'Order ' . $order->order_number . ' parcel #' . $sellerOrder->id . ' has been auto-assigned to your hub (' . $hub->name . '). Please proceed with delivery.',
                'link' => route('rider.pickups', ['status' => 'sorting_center']),
            ]);

            \App\Models\Notification::create([
                'user_id' => $order->user_id,
                'title' => 'Rider Assigned to Your Parcel',
                'type' => 'delivery',
                'message' => 'A parcel from order ' . $order->order_number . ' has been assigned to a rider from ' . $hub->name . ' for delivery.',
                'link' => route('orders.show', $order),
            ]);

            return $rider;
        }

        return null;
    }

    public static function findBestRiderForHub(Hub $hub): ?User
    {
        return User::whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('hub_id', $hub->id)
            ->where('availability_status', 'available')
            ->whereColumn('current_load', '<', 'max_capacity')
            ->first();
    }
}
