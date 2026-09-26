<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShipmentController extends Controller
{
    public function track(Request $request, Shipment $shipment)
    {
        $shipment->loadMissing([
            'sellerOrder.order',
            'sellerOrder.seller',
        ]);

        $sellerOrder = $shipment->sellerOrder;
        $order = $sellerOrder?->order;

        if (!$sellerOrder || !$order) {
            return response()->json([
                'message' => 'Shipment is not linked to a valid seller order',
            ], 404);
        }

        // Buyer who owns the parent order or rider assigned to this parcel.
        if ($order->user_id !== Auth::id() && $shipment->rider_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $shipment->load([
            'rider:id,name,phone,vehicle_type,latitude,longitude,current_load,max_capacity',
            'hub:id,name,address,latitude,longitude',
            'logistic:id,business_name,logo',
        ]);

        return response()->json([
            'data' => [
                'shipment' => [
                    'id' => $shipment->id,
                    'seller_order_id' => $sellerOrder->id,
                    'tracking_number' => $shipment->tracking_number,
                    'status' => $shipment->status,
                    'courier' => $shipment->courier,
                    'pickup_address' => $shipment->pickup_address,
                    'delivery_address' => $shipment->delivery_address,
                    'picked_up_at' => $shipment->picked_up_at?->toISOString(),
                    'delivered_at' => $shipment->delivered_at?->toISOString(),
                    'at_sorting_center_at' => $shipment->at_sorting_center_at?->toISOString(),
                    'notes' => $shipment->notes,
                    'sorting_area' => $shipment->sorting_area,
                    'rack_number' => $shipment->rack_number,
                    'delivery_zone' => $shipment->delivery_zone,
                    'delivery_type' => $shipment->delivery_type,
                ],
                'seller_order' => [
                    'id' => $sellerOrder->id,
                    'seller_id' => $sellerOrder->seller_id,
                    'status' => $sellerOrder->status,
                    'seller' => $sellerOrder->seller ? [
                        'id' => $sellerOrder->seller->id,
                        'name' => $sellerOrder->seller->name,
                    ] : null,
                ],
                'rider' => $shipment->rider ? [
                    'id' => $shipment->rider->id,
                    'name' => $shipment->rider->name,
                    'phone' => $shipment->rider->phone,
                    'vehicle_type' => $shipment->rider->vehicle_type,
                    'latitude' => $shipment->rider->latitude,
                    'longitude' => $shipment->rider->longitude,
                    'load_percentage' => $shipment->rider->max_capacity > 0
                        ? round(($shipment->rider->current_load / $shipment->rider->max_capacity) * 100)
                        : 0,
                ] : null,
                'hub' => $shipment->hub ? [
                    'id' => $shipment->hub->id,
                    'name' => $shipment->hub->name,
                    'address' => $shipment->hub->address,
                    'latitude' => $shipment->hub->latitude,
                    'longitude' => $shipment->hub->longitude,
                ] : null,
                'logistic' => $shipment->logistic ? [
                    'id' => $shipment->logistic->id,
                    'name' => $shipment->logistic->business_name,
                    'logo' => $shipment->logistic->logo,
                ] : null,
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'delivery_status' => $order->delivery_status,
                    'total' => $order->total_minor / 100,
                ],
            ],
        ]);
    }

    public function showForOrder(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order->loadMissing([
            'sellerOrders.seller',
            'sellerOrders.shipment',
        ]);

        $sellerOrders = $order->sellerOrders
            ->filter(fn ($sellerOrder) => $sellerOrder->shipment !== null)
            ->values();

        if ($sellerOrders->isEmpty()) {
            return response()->json([
                'message' => 'No shipments found for this order',
            ], 404);
        }

        /*
         * An Order may contain multiple SellerOrders/parcels.
         * Return all parcel shipments instead of assuming Order::shipment.
         */
        return response()->json([
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'delivery_status' => $order->delivery_status,
                    'total' => $order->total_minor / 100,
                ],
                'shipments' => $sellerOrders->map(function ($sellerOrder) {
                    $shipment = $sellerOrder->shipment;

                    return [
                        'seller_order_id' => $sellerOrder->id,
                        'seller_id' => $sellerOrder->seller_id,
                        'seller_name' => $sellerOrder->seller?->name,
                        'shipment_id' => $shipment->id,
                        'tracking_number' => $shipment->tracking_number,
                        'status' => $shipment->status,
                        'courier' => $shipment->courier,
                        'pickup_address' => $shipment->pickup_address,
                        'delivery_address' => $shipment->delivery_address,
                        'picked_up_at' => $shipment->picked_up_at?->toISOString(),
                        'delivered_at' => $shipment->delivered_at?->toISOString(),
                    ];
                })->values(),
            ],
        ]);
    }
}
