<?php

namespace App\Http\Controllers\Api\Logistic;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $logistic = $user?->ownedLogistic;

        if (!$logistic) {
            return response()->json([
                'success' => false,
                'message' => 'No logistics company found for this account.',
            ], 404);
        }

        $shipments = $logistic->shipments();

        $totalShipments = (clone $shipments)->count();

        $pendingShipments = (clone $shipments)
            ->whereIn('status', [
                'pending',
                'assigned',
            ])
            ->count();

        $inTransitShipments = (clone $shipments)
            ->whereIn('status', [
                'picked_up',
                'in_transit',
                'at_sorting_center',
                'sorted',
                'staged',
            ])
            ->count();

        $deliveredShipments = (clone $shipments)
            ->where('status', 'delivered')
            ->count();

        $delayedShipments = (clone $shipments)
            ->whereIn('status', [
                'delayed',
                'delivery_failed',
            ])
            ->count();

        $cancelledShipments = (clone $shipments)
            ->whereIn('status', [
                'cancelled',
                'canceled',
            ])
            ->count();

        $awaitingReceive = (clone $shipments)
            ->where('status', 'at_sorting_center')
            ->where(function ($query) {
                $query->whereNull('received_by_sorting_center')
                    ->orWhere('received_by_sorting_center', false);
            })
            ->count();

        $receivedShipments = (clone $shipments)
            ->where('sorting_status', 'received')
            ->count();

        $scannedShipments = (clone $shipments)
            ->where('sorting_status', 'scanned')
            ->count();

        $sortedShipments = (clone $shipments)
            ->where('sorting_status', 'sorted')
            ->count();

        $stagedShipments = (clone $shipments)
            ->where('sorting_status', 'staged')
            ->count();

        $alertsCount = (clone $shipments)
            ->where(function ($query) {
                $query->whereIn('status', [
                    'cancelled',
                    'canceled',
                    'delayed',
                    'delivery_failed',
                ])->orWhere(function ($pendingQuery) {
                    $pendingQuery
                        ->where('status', 'pending')
                        ->where('created_at', '<', now()->subHours(24));
                });
            })
            ->count();

        $totalRiders = $logistic->riders()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->count();

        $pendingRiders = $logistic->riders()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('logistic_status', 'pending')
            ->count();

        $activeRiders = $logistic->riders()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('logistic_status', 'approved')
            ->where('status', User::STATUS_ACTIVE)
            ->count();

        $availableRiders = $logistic->riders()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('logistic_status', 'approved')
            ->where('status', User::STATUS_ACTIVE)
            ->where('availability_status', 'available')
            ->count();

        $totalHubs = $logistic->hubs()->count();

        $activeHubs = $logistic->hubs()
            ->where('status', 'active')
            ->count();

        $recentShipments = $logistic->shipments()
            ->with([
                'sellerOrder.order.user',
                'sellerOrder.seller',
                'rider',
                'hub',
            ])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($shipment) {
                $order = $shipment->sellerOrder?->order;
                $customer = $order?->user;

                return [
                    'id' => $shipment->id,
                    'shipment_id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'status' => $shipment->status,
                    'sorting_status' => $shipment->sorting_status,

                    'customer_name' => $customer?->name,
                    'customer_phone' => $customer?->phone,

                    'delivery_address' => $shipment->delivery_address,
                    'delivery_zone' => $shipment->delivery_zone,
                    'delivery_type' => $shipment->delivery_type,

                    'sorting_area' => $shipment->sorting_area,
                    'rack_number' => $shipment->rack_number,

                    'rider_id' => $shipment->rider_id,
                    'rider_name' => $shipment->rider?->name,

                    'hub_id' => $shipment->hub_id,
                    'hub_name' => $shipment->hub?->name,

                    'order_number' => $order?->order_number,

                    'latitude' => $shipment->latitude !== null
                        ? (float) $shipment->latitude
                        : null,

                    'longitude' => $shipment->longitude !== null
                        ? (float) $shipment->longitude
                        : null,

                    'created_at' => optional($shipment->created_at)
                        ->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,

            'data' => [
                'logistic' => [
                    'id' => $logistic->id,
                    'company_name' => $logistic->company_name,
                    'status' => $logistic->status,
                ],

                'shipments' => [
                    'total' => $totalShipments,
                    'pending' => $pendingShipments,
                    'in_transit' => $inTransitShipments,
                    'delivered' => $deliveredShipments,
                    'delayed' => $delayedShipments,
                    'cancelled' => $cancelledShipments,

                    'awaiting_receive' => $awaitingReceive,
                    'received' => $receivedShipments,
                    'scanned' => $scannedShipments,
                    'sorted' => $sortedShipments,
                    'staged' => $stagedShipments,
                ],

                'riders' => [
                    'total' => $totalRiders,
                    'pending' => $pendingRiders,
                    'active' => $activeRiders,
                    'available' => $availableRiders,
                ],

                'hubs' => [
                    'total' => $totalHubs,
                    'active' => $activeHubs,
                ],

                'alerts_count' => $alertsCount,

                'recent_shipments' => $recentShipments,
            ],
        ]);
    }
}