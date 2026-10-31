<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentCreationService
{
    public function createForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->load('sellerOrders');

            foreach ($order->sellerOrders as $sellerOrder) {
                $this->createForSellerOrder($sellerOrder);
            }
        });
    }

    private function createForSellerOrder(SellerOrder $sellerOrder): void
    {
        $lockedSellerOrder = SellerOrder::query()
            ->whereKey($sellerOrder->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (
            Shipment::query()
            ->where('seller_order_id', $lockedSellerOrder->id)
            ->exists()
        ) {
            return;
        }

        $lockedSellerOrder->load([
            'order.user',
            'seller.owner',
            'seller.pickupAddress',
        ]);

        $order = $lockedSellerOrder->order;
        $seller = $lockedSellerOrder->seller;
        $owner = $seller?->owner;
        $pickupAddress = $seller?->pickupAddress;

        $route = HubAssignmentService::findRoutingHubsForSellerOrder(
            $lockedSellerOrder
        );

        $originHub = $route['origin'];
        $destinationHub = $route['destination'];
        $originMatch = $route['origin_match'] ?? 'unresolved';
        $destinationMatch = $route['destination_match'] ?? 'unresolved';

        /*
         * Determine whether this shipment needs an inter-hub
         * line-haul vehicle such as a car, van, or truck.
         *
         * Parcel weight is currently nullable because the existing
         * order/product flow does not yet provide a reliable shipment
         * weight value.
         */
        $linehaulFields = LineHaulAssignmentService::routingFields(
            $originHub,
            $destinationHub
        );

        $pickup = $pickupAddress
            ? collect([
                $pickupAddress->address_line1,
                $pickupAddress->address_line2,
                $pickupAddress->barangay,
                $pickupAddress->city,
                $pickupAddress->province,
                $pickupAddress->postal_code,
                $pickupAddress->country,
            ])->filter()->implode(', ')
            : collect([
                $owner?->business_name,
                $owner?->street_address,
                $owner?->barangay_name ?: $owner?->barangay,
                $owner?->municipality_name ?: $owner?->municipality,
                $owner?->province_name ?: $owner?->province,
            ])->filter()->implode(', ');

        $address = $order->shipping_address;

        $delivery = is_array($address)
            ? (
                $address['address']
                ?? collect($address)
                ->only([
                    'address_line1',
                    'address_line2',
                    'barangay',
                    'city',
                    'province',
                    'postal_code',
                    'country',
                ])
                ->filter()
                ->implode(', ')
            )
            : (string) $address;

        Shipment::create([
            'seller_order_id' => $lockedSellerOrder->id,

            'logistic_id' => $originHub?->logistic_id,

            'hub_id' => $originHub?->id,

            'origin_hub_id' => $originHub?->id,

            'destination_hub_id' => $destinationHub?->id,

            'routing_status' => $originHub && $destinationHub
                ? 'awaiting_pickup'
                : 'routing_unresolved',

            /*
             * Line-haul routing information.
             */
            'required_vehicle_type' =>
            $linehaulFields['required_vehicle_type'],

            'parcel_weight_kg' =>
            $linehaulFields['parcel_weight_kg'],

            'estimated_distance_km' =>
            $linehaulFields['estimated_distance_km'],

            'linehaul_status' =>
            $linehaulFields['linehaul_status'],

            'tracking_number' =>
            'SPE-' . Str::upper(Str::random(10)),

            'tracking_code' =>
            'SPE-' . Str::upper(Str::random(12)),

            'status' => 'pending',

            'pickup_address' =>
            $pickup ?: 'Seller address',

            'delivery_address' =>
            $delivery ?: 'Delivery address unavailable',

            'notes' => $originHub && $destinationHub
                ? 'Route: '
                . $originHub->name
                . ' -> '
                . $destinationHub->name
                . ' | Origin match: '
                . $originMatch
                . ' | Destination match: '
                . $destinationMatch
                . (
                    $linehaulFields['required_vehicle_type']
                    ? ' | Required vehicle: '
                    . $linehaulFields['required_vehicle_type']
                    : ''
                )
                . (
                    $linehaulFields['estimated_distance_km'] !== null
                    ? ' | Estimated distance: '
                    . $linehaulFields['estimated_distance_km']
                    . ' km'
                    : ''
                )
                : 'Routing unresolved: no valid active hub/default hub found',
        ]);

        if (
            !$lockedSellerOrder->logistic_id
            && $originHub
        ) {
            $lockedSellerOrder->update([
                'logistic_id' => $originHub->logistic_id,
            ]);
        }
    }
}
