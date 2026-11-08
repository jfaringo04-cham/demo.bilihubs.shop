<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Schema;

class OrderFlowSyncService
{
    /**
     * Keep seller-order and buyer-order intermediate statuses aligned
     * with the real shipment state.
     */
    public function syncShipment(Shipment $shipment): void
    {
        $shipment->refresh();
        $shipment->loadMissing('sellerOrder.order');

        $sellerOrder = $shipment->sellerOrder;
        $order = $sellerOrder?->order;

        if (!$sellerOrder || !$order) {
            return;
        }

        $sellerStatus = $this->sellerStatusFromShipment($shipment);

        if (
            $sellerStatus
            && !in_array($sellerOrder->status, ['cancelled', 'returned', 'completed'], true)
            && $sellerOrder->status !== $sellerStatus
        ) {
            $sellerOrder->update(['status' => $sellerStatus]);
        }

        $this->syncParentOrder($order);
    }

    private function sellerStatusFromShipment(Shipment $shipment): ?string
    {
        if ($shipment->status === 'delivered') {
            return 'delivered';
        }

        if ($shipment->status === 'delivery_failed') {
            return 'delivery_failed';
        }

        if ($shipment->status === 'out_for_delivery') {
            return 'out_for_delivery';
        }

        // Final-mile pickup from the sorting hub means the parcel is
        // already moving toward the customer.
        if ($shipment->hub_picked_up_at !== null) {
            return 'in_transit';
        }

        if (in_array($shipment->status, [
            'at_sorting_center',
            'sorted',
            'staged',
        ], true)) {
            return 'in_transit';
        }

        // Seller pickup uses in_transit on the Shipment model, but the
        // customer-facing milestone should first be "Picked Up".
        if (
            $shipment->status === 'in_transit'
            && $shipment->picked_up_at !== null
        ) {
            return $shipment->received_by_sorting_center
                ? 'in_transit'
                : 'picked_up';
        }

        if ($shipment->status === 'picked_up') {
            return $shipment->hub_picked_up_at !== null
                ? 'in_transit'
                : 'picked_up';
        }

        if ($shipment->status === 'assigned') {
            return 'ready_for_pickup';
        }

        return null;
    }

    private function syncParentOrder(Order $order): void
    {
        $order->refresh();

        // Never move a finalized order backwards.
        if (in_array($order->status, [
            'completed',
            'cancelled',
            'returned',
        ], true)) {
            return;
        }

        $sellerOrders = $order->sellerOrders()
            ->with('shipment')
            ->get();

        if ($sellerOrders->isEmpty()) {
            return;
        }

        $rank = [
            'pending' => 0,
            'placed' => 0,
            'confirmed' => 1,
            'preparing' => 2,
            'ready_for_pickup' => 3,
            'picked_up' => 4,
            'in_transit' => 5,
            'at_sorting_center' => 5,
            'sorted' => 5,
            'staged' => 5,
            'assigned_to_rider' => 5,
            'out_for_delivery' => 6,
            'delivered' => 7,
            'completed' => 8,
        ];

        $statuses = $sellerOrders->map(function ($sellerOrder) {
            $shipment = $sellerOrder->shipment;

            if ($shipment) {
                $mapped = $this->sellerStatusFromShipment($shipment);
                if ($mapped) {
                    return $mapped;
                }
            }

            return $sellerOrder->status ?: 'placed';
        });

        // For multi-seller orders, the buyer-facing order advances only
        // as far as the least-progressed seller parcel.
        $minimumRank = $statuses
            ->map(fn ($status) => $rank[$status] ?? 0)
            ->min();

        $parentStatus = match (true) {
            $minimumRank >= 7 => 'delivered',
            $minimumRank >= 6 => 'out_for_delivery',
            $minimumRank >= 5 => 'in_transit',
            $minimumRank >= 4 => 'picked_up',
            $minimumRank >= 3 => 'ready_for_pickup',
            $minimumRank >= 2 => 'preparing',
            $minimumRank >= 1 => 'confirmed',
            default => 'placed',
        };

        $deliveredCount = $statuses
            ->filter(fn ($status) => in_array($status, ['delivered', 'completed'], true))
            ->count();

        $totalSellerOrders = $statuses->count();

        $deliveryStatus = match (true) {
            $deliveredCount === $totalSellerOrders => 'delivered',
            $deliveredCount > 0 => 'partially_delivered',
            $minimumRank >= 6 => 'out_for_delivery',
            $minimumRank >= 4 => 'in_transit',
            default => 'pending',
        };

        $updates = [
            'status' => $parentStatus,
        ];

        // Some automated-test schemas intentionally contain only the core
        // order columns. Only write optional tracking columns when they
        // actually exist in the current database schema.
        if (Schema::hasColumn('orders', 'delivery_status')) {
            $updates['delivery_status'] = $deliveryStatus;
        }

        if (Schema::hasColumn('orders', 'ready_for_pickup')) {
            $updates['ready_for_pickup'] = $minimumRank === 3;
        }

        if (
            $minimumRank >= 4
            && Schema::hasColumn('orders', 'picked_up_at')
            && $order->picked_up_at === null
        ) {
            $updates['picked_up_at'] = now();
        }

        if (
            $minimumRank >= 7
            && Schema::hasColumn('orders', 'delivered_at')
            && $order->delivered_at === null
        ) {
            $updates['delivered_at'] = now();
        }

        $order->update($updates);
    }
}
