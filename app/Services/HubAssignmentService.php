<?php

namespace App\Services;

use App\Models\Hub;
use App\Models\Logistic;
use App\Models\Order;
use App\Models\User;
use App\Models\Shipment;

class HubAssignmentService
{
    public static function findBestHubForOrder(Order $order): ?Hub
    {
        // Use seller/pickup location for hub assignment (pickup rider delivers to nearest hub)
        $sellerLat = null;
        $sellerLng = null;

        $seller = $order->items->first()->product->user ?? null;
        if ($seller) {
            $sellerLat = $seller->latitude ?? null;
            $sellerLng = $seller->longitude ?? null;
        }

        if (!$sellerLat || !$sellerLng) {
            // Fallback to customer location if seller location not available
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

    public static function autoAssignRiderToOrder(Order $order): ?User
    {
        $hub = self::findBestHubForOrder($order);

        if (!$hub) {
            return null;
        }

        $shipment = $order->shipment;
        if ($shipment && !$shipment->rider_id) {
            $shipment->update(['logistic_id' => $hub->logistic_id]);
        } elseif (!$shipment) {
            $seller = $order->items->first()->product->user ?? null;
            $trackingNumber = 'SPE-' . strtoupper(uniqid());

            $shipment = Shipment::create([
                'logistic_id' => $hub->logistic_id,
                'hub_id' => $hub->id,
                'order_id' => $order->id,
                'tracking_number' => $trackingNumber,
                'status' => 'pending',
                'pickup_address' => $seller ? ($seller->business_name . ', ' . $seller->street_address . ', ' . $seller->barangay . ', ' . $seller->municipality . ', ' . $seller->province) : 'Seller address',
                'delivery_address' => $order->shipping_address,
                'notes' => 'Auto-assigned hub based on pickup location: ' . $hub->name,
            ]);
        }

        $rider = self::findBestRiderForHub($hub);

        if ($rider) {
            \App\Services\RiderAssignmentService::assignRiderToShipment($shipment, $rider);

            \App\Models\Notification::create([
                'user_id' => $rider->id,
                'title' => 'New Delivery Assigned from Hub',
                'type' => 'delivery',
                'message' => 'Order ' . $order->order_number . ' has been auto-assigned to your hub (' . $hub->name . '). Please proceed with delivery.',
                'link' => route('rider.pickups', ['status' => 'sorting_center']),
            ]);

            \App\Models\Notification::create([
                'user_id' => $order->user_id,
                'title' => 'Rider Assigned to Your Order',
                'type' => 'delivery',
                'message' => 'Your order ' . $order->order_number . ' has been assigned to a rider from ' . $hub->name . ' for delivery.',
                'link' => route('orders.show', $order),
            ]);

            return $rider;
        }

        return null;
    }

    public static function findBestRiderForHub(Hub $hub): ?User
    {
        return User::where('role', 'rider')
            ->where('hub_id', $hub->id)
            ->where('availability_status', 'available')
            ->whereColumn('current_load', '<', 'max_capacity')
            ->first();
    }
}
