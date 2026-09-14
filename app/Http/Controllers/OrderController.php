<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Review;
use App\Services\ComplianceMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    private function createNotification($userId, $title, $message, $type = 'order', $link = null)
    {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
        ]);
    }

    public function index()
    {
        $orders = Order::where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $user = Auth::user();

        if ($order->user_id !== $user->id && !$user->isAdmin() && !$user->isSeller()) {
            abort(403);
        }

        if ($user->isSeller()) {
            $hasSellerProduct = $order->items()
                ->whereHas('product', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->exists();

            if (!$hasSellerProduct) {
                abort(403);
            }
        }

        $order->load('items.product', 'items.size', 'rider', 'shipment');

        return view('orders.show', compact('order'));
    }

    public function requestReturn(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        if ($order->status !== 'reschedule_requested' && $order->status !== 'delivered') {
            return back()->with('error', 'This order is not eligible for return request.');
        }

        $request->validate([
            'return_reason' => 'required|string|max:1000',
        ]);

        $order->update([
            'status' => 'return_requested',
            'return_status' => 'requested',
            'return_reason' => $request->return_reason,
        ]);

        $shipment = $order->shipment;
        if ($shipment) {
            $shipment->update([
                'status' => 'returned',
                'notes' => ($shipment->notes ? $shipment->notes . "\n" : '') . "Return requested by buyer. Reason: " . $request->return_reason,
            ]);
        }

        $this->createNotification(
            $order->user_id,
            'Return Requested',
            'Your return request for order ' . $order->order_number . ' has been submitted.',
            'return',
            route('orders.show', $order)
        );

        $seller = $order->items->first()->product->user ?? null;
        if ($seller) {
            $this->createNotification(
                $seller->id,
                'Return Requested',
                'Buyer requested return for order ' . $order->order_number . '. Reason: ' . $request->return_reason,
                'return',
                route('seller.orders.show', $order)
            );
        }

        return back()->with('success', 'Return request submitted successfully.');
    }

    public function requestReschedule(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        if ($order->status !== 'delivery_failed') {
            return back()->with('error', 'This order is not eligible for reschedule.');
        }

        $request->validate([
            'reschedule_reason' => 'nullable|string|max:1000',
        ]);

        $order->update([
            'status' => 'rescheduled',
            'reschedule_reason' => $request->reschedule_reason,
            'reschedule_requested_at' => now(),
            'rescheduled_at' => now(),
            'delivery_status' => 'pending',
            'failed_at' => null,
            'failure_reason' => null,
        ]);

        $shipment = $order->shipment;
        if ($shipment) {
            $bestRider = \App\Services\RiderAssignmentService::findBestRiderForShipment($shipment);
            if ($bestRider) {
                \App\Services\RiderAssignmentService::assignRiderToShipment($shipment, $bestRider);

                \App\Models\Notification::create([
                    'user_id' => $bestRider->id,
                    'title' => 'Rescheduled Delivery Assigned',
                    'type' => 'delivery',
                    'message' => 'Order ' . $order->order_number . ' has been rescheduled for delivery. Destination: ' . ($shipment->delivery_zone ?: 'N/A') . '. Please proceed with the delivery.',
                    'link' => route('rider.pickups'),
                ]);
            }
        }

        $this->createNotification(
            $order->user_id,
            'Delivery Rescheduled',
            'Your order ' . $order->order_number . ' has been rescheduled for delivery.',
            'order',
            route('orders.show', $order)
        );

        $seller = $order->items->first()->product->user ?? null;
        if ($seller) {
            $this->createNotification(
                $seller->id,
                'Order Rescheduled',
                'Order ' . $order->order_number . ' has been rescheduled for delivery.',
                'order',
                route('seller.orders.show', $order)
            );
        }

        return back()->with('success', 'Delivery rescheduled successfully. A rider will be assigned shortly.');
    }

    public function requestCancellation(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        if (!in_array($order->status, ['placed', 'confirmed', 'preparing'])) {
            return back()->with('error', 'Order cannot be cancelled at this stage.');
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $order->load('items.product.sizes', 'items.size');

        foreach ($order->items as $item) {
            if ($item->size_id && $item->product) {
                $size = $item->product->sizes->firstWhere('id', $item->size_id);
                if ($size) {
                    $item->product->sizes()->updateExistingPivot($item->size_id, [
                        'stock' => $size->pivot->stock + $item->quantity,
                    ]);
                }
            }

            if ($item->product) {
                $item->product->update([
                    'stock' => $item->product->stock + $item->quantity,
                ]);
            }
        }

        $order->update([
            'status' => 'cancelled',
            'notes' => ($order->notes ? $order->notes . "\n" : '') . "Cancellation reason: " . $request->reason,
        ]);

        $this->createNotification(
            $order->user_id,
            'Order Cancelled',
            'Your order ' . $order->order_number . ' has been cancelled.',
            'order',
            route('orders.index')
        );

        return back()->with('success', 'Order cancelled successfully.');
    }

    public function confirmReceived(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        if ($order->status !== 'delivered') {
            return back()->with('error', 'Cannot confirm receipt for this order.');
        }

        $order->update([
            'status' => 'completed',
            'confirmed_received_at' => now(),
        ]);

        $this->createNotification(
            $order->user_id,
            'Order Confirmed',
            'You have confirmed receipt of order ' . $order->order_number . '. Transaction completed.',
            'order',
            route('orders.show', $order)
        );

        $seller = $order->items->first()->product->user ?? null;
        if ($seller) {
            $this->createNotification(
                $seller->id,
                'Order Completed',
                'Order ' . $order->order_number . ' has been completed by the buyer.',
                'order',
                route('seller.orders.show', $order)
            );
        }

        return back()->with('success', 'Order confirmed. Transaction completed successfully!');
    }


}
