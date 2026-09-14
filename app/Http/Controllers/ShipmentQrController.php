<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ShipmentQrController extends Controller
{
    public function image(Shipment $shipment)
    {
        $payload = $shipment->qr_payload;

        $svg = QrCode::format('svg')
            ->size(300)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($payload);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function png(Shipment $shipment)
    {
        if (!extension_loaded('gd')) {
            return $this->image($shipment);
        }

        try {
            $png = QrCode::format('png')
                ->size(400)
                ->margin(2)
                ->errorCorrection('H')
                ->generate($shipment->qr_payload);

            return response($png, 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        } catch (\Throwable $e) {
            return $this->image($shipment);
        }
    }

    public function label(Shipment $shipment)
    {
        $user = Auth::user();
        $isOwner = $user->id === $shipment->order->user_id;
        $isSeller = $user->id === optional($shipment->order->items->first())->product->user_id;
        $isRider = $user->id === $shipment->rider_id;
        $isLogistic = $shipment->logistic && $user->id === $shipment->logistic->owner_user_id;
        $isAdmin = $user->isAdmin();

        if (!$isOwner && !$isSeller && !$isRider && !$isLogistic && !$isAdmin) {
            abort(403);
        }

        $shipment->load(['order.items.product', 'order.user', 'rider', 'logistic']);
        return view('shipments.qr-label', compact('shipment'));
    }

    public function scan(Request $request, Shipment $shipment)
    {
        $request->validate([
            'qr_token' => 'required|string',
        ]);

        $rider = Auth::user();
        if (!$rider->isRider()) {
            abort(403, 'Only riders can scan.');
        }

        if (strtoupper($request->qr_token) !== strtoupper($shipment->qr_token)
            && $request->qr_token !== $shipment->tracking_number) {
            return back()->with('error', 'Invalid QR code. The scanned code does not match this shipment.');
        }

        if ($shipment->rider_id && $shipment->rider_id !== $rider->id) {
            return back()->with('error', 'This shipment is already assigned to another rider.');
        }

        $shipment->update([
            'rider_id' => $rider->id,
            'status' => 'in_transit',
            'scanned_at_seller' => now(),
            'scanned_by_rider_id' => $rider->id,
            'seller_scan_confirmed_at' => now(),
            'picked_up_at' => now(),
        ]);

        $shipment->order->update([
            'rider_id' => $rider->id,
            'delivery_status' => 'in_transit',
            'assigned_at' => now(),
            'picked_up_at' => now(),
        ]);

        \App\Models\Notification::create([
            'user_id' => $shipment->order->user_id,
            'title' => 'Rider Picked Up Your Parcel',
            'type' => 'delivery',
            'message' => 'A rider has scanned and picked up your order ' . $shipment->order->order_number . ' from the seller. It is now on the way to the sorting center.',
            'link' => route('orders.show', $shipment->order),
        ]);

        if ($shipment->logistic && $shipment->logistic->owner_user_id) {
            \App\Models\Notification::create([
                'user_id' => $shipment->logistic->owner_user_id,
                'title' => 'Parcel Picked Up by Rider',
                'type' => 'shipment',
                'message' => 'Rider ' . $rider->name . ' scanned and picked up shipment ' . $shipment->tracking_number . ' from the seller. Status: In Transit to Hub.',
                'link' => route('logistic.shipments.show', $shipment),
            ]);
        }

        return redirect()->route('rider.orders.show', $shipment->order)
            ->with('success', 'QR code verified! Parcel picked up. Please deliver to the sorting center.');
    }

    public function scanAtHub(Request $request, Shipment $shipment)
    {
        $request->validate([
            'qr_token' => 'required|string',
            'delivery_zone' => 'nullable|string|max:255',
            'delivery_type' => 'nullable|in:standard,same_day,cod',
        ]);

        $user = Auth::user();
        $isLogistic = $shipment->logistic && $user->id === $shipment->logistic->owner_user_id;
        $isAdmin = $user->isAdmin();

        if (!$isLogistic && !$isAdmin) {
            abort(403, 'Only logistic owner or admin can scan at hub.');
        }

        if (strtoupper($request->qr_token) !== strtoupper($shipment->qr_token)
            && $request->qr_token !== $shipment->tracking_number) {
            return back()->with('error', 'Invalid QR code. The scanned code does not match this shipment.');
        }

        $shipment->update([
            'status' => 'at_sorting_center',
            'at_sorting_center_at' => now(),
            'sorting_status' => 'sorted',
            'received_at' => now(),
            'sorted_at' => now(),
            'delivery_zone' => $request->delivery_zone,
            'delivery_type' => $request->delivery_type,
        ]);

        $shipment->order->update([
            'delivery_status' => 'at_sorting_center',
            'delivery_zone' => $request->delivery_zone,
        ]);

        $bestRider = \App\Services\RiderAssignmentService::findBestRiderForShipment($shipment);

        if ($bestRider) {
            \App\Services\RiderAssignmentService::assignRiderToShipment($shipment, $bestRider);

            \App\Models\Notification::create([
                'user_id' => $bestRider->id,
                'title' => 'New Delivery Assigned from Hub',
                'type' => 'delivery',
                'message' => 'Order ' . $shipment->order->order_number . ' has been sorted at the hub. Destination: ' . ($request->delivery_zone ?: 'N/A') . '. Please pick it up for final delivery to the customer.',
                'link' => route('rider.pickups', ['status' => 'sorting_center']),
            ]);

            \App\Models\Notification::create([
                'user_id' => $shipment->order->user_id,
                'title' => 'Order Sorted at Hub',
                'type' => 'delivery',
                'message' => 'Your order ' . $shipment->order->order_number . ' has been sorted at the hub and assigned to a rider for final delivery.',
                'link' => route('orders.show', $shipment->order),
            ]);
        }

        if ($shipment->logistic && $shipment->logistic->owner_user_id) {
            \App\Models\Notification::create([
                'user_id' => $shipment->logistic->owner_user_id,
                'title' => 'Parcel Arrived at Hub',
                'type' => 'shipment',
                'message' => 'Shipment ' . $shipment->tracking_number . ' has arrived at the sorting center and is being processed.',
                'link' => route('logistic.shipments.show', $shipment),
            ]);
        }

        return back()->with('success', 'Parcel scanned at hub. ' . ($availableRider ? 'Sorted and assigned to rider ' . $availableRider->name . ' for final delivery.' : 'Awaiting rider assignment.'));
    }

    public function scanByOrder(Request $request, \App\Models\Order $order)
    {
        $shipment = $order->shipment;
        if (!$shipment) {
            return back()->with('error', 'No shipment found for this order.');
        }

        return $this->scan($request, $shipment);
    }
}
