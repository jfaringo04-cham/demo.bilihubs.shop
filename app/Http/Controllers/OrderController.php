<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Review;
use App\Services\ComplianceMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        $orders = Order::where('user_id', Auth::id())->latest()->paginate(10);
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
                })->exists();

            if (!$hasSellerProduct) {
                abort(403);
            }
        }

        $order->load('items.product', 'items.size', 'items.variation', 'rider', 'sellerOrders.shipment');

        return view('orders.show', compact('order'));
    }

    public function requestReturn(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'seller_order_id' => 'required|integer',
            'return_reason' => 'required|string|max:1000',
        ]);

        $sellerOrder = $order->sellerOrders()
            ->with(['seller.owner', 'shipment'])
            ->whereKey($validated['seller_order_id'])
            ->firstOrFail();

        if ($sellerOrder->status !== 'delivered') {
            return back()->with(
                'error',
                'This seller order is not eligible for return.'
            );
        }

        $updated = $order->sellerOrders()
            ->whereKey($sellerOrder->id)
            ->where('status', 'delivered')
            ->where('return_status', 'none')
            ->update([
                'return_status' => 'requested',
                'return_reason' => $validated['return_reason'],
                'return_requested_at' => now(),
            ]);

        if ($updated !== 1) {
            return back()->with(
                'error',
                'This seller order is no longer eligible for a new return request.'
            );
        }

        $this->createNotification(
            $order->user_id,
            'Return Requested',
            'Your return request for order ' . $order->order_number
                . ' (' . $sellerOrder->seller->name . ') has been submitted.',
            'return',
            route('orders.show', $order)
        );

        $sellerUser = $sellerOrder->seller?->owner;

        if ($sellerUser) {
            $this->createNotification(
                $sellerUser->id,
                'Return Requested',
                'Buyer requested a return for seller order #'
                    . $sellerOrder->id
                    . '. Reason: ' . $validated['return_reason'],
                'return',
                route('seller.orders.show', $order)
            );
        }

        return back()->with(
            'success',
            'Return request submitted successfully.'
        );
    }

    public function requestReschedule(Request $request, Order $order)
    {
        if ((int) $order->user_id !== (int) Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'seller_order_id' => 'required|integer',
            'reschedule_reason' => 'nullable|string|max:1000',
        ]);

        $sellerOrder = $order->sellerOrders()
            ->with('seller.owner')
            ->whereKey($validated['seller_order_id'])
            ->firstOrFail();

        if ($sellerOrder->status !== 'delivery_failed') {
            return back()->with(
                'error',
                'This seller order is not eligible for reschedule.'
            );
        }

        $updated = $order->sellerOrders()
            ->whereKey($sellerOrder->id)
            ->where('status', 'delivery_failed')
            ->where('reschedule_status', 'none')
            ->update([
                'reschedule_status' => 'requested',
                'reschedule_reason' => $validated['reschedule_reason'] ?? null,
                'reschedule_requested_at' => now(),
                'rescheduled_at' => null,
            ]);

        if ($updated !== 1) {
            return back()->with(
                'error',
                'This seller order already has a reschedule request or is no longer eligible.'
            );
        }

        $this->createNotification(
            $order->user_id,
            'Reschedule Requested',
            'Your reschedule request for seller order #' .
                $sellerOrder->id .
                ' under order ' . $order->order_number .
                ' has been submitted.',
            'order',
            route('orders.show', $order)
        );

        $sellerOwner = $sellerOrder->seller?->owner;

        if ($sellerOwner) {
            $this->createNotification(
                $sellerOwner->id,
                'Reschedule Requested',
                'Buyer requested a delivery reschedule for seller order #' .
                    $sellerOrder->id .
                    ' under order ' . $order->order_number .
                    (!empty($validated['reschedule_reason'])
                        ? '. Reason: ' . $validated['reschedule_reason']
                        : '.'),
                'order',
                route('seller.orders.show', $order)
            );
        }

        return back()->with(
            'success',
            'Reschedule request submitted for this seller order.'
        );
    }

    public function requestCancellation(Request $request, Order $order)
    {
        if ((int) $order->user_id !== (int) Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $cancelled = DB::transaction(
                function () use ($order, $validated) {
                    $lockedOrder = Order::query()
                        ->whereKey($order->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (
                        !in_array(
                            $lockedOrder->status,
                            ['pending', 'placed', 'confirmed', 'preparing'],
                            true
                        )
                    ) {
                        return false;
                    }

                    $sellerOrderIds = $lockedOrder
                        ->sellerOrders()
                        ->pluck('id');

                    $shipments = \App\Models\Shipment::query()
                        ->whereIn(
                            'seller_order_id',
                            $sellerOrderIds
                        )
                        ->lockForUpdate()
                        ->get();

                    /*
                |--------------------------------------------------------------------------
                | PHYSICAL CUSTODY GUARD
                |--------------------------------------------------------------------------
                |
                | Once a parcel is already physically with a rider or has
                | entered logistics processing, normal buyer cancellation
                | is no longer allowed.
                |
                */
                    foreach ($shipments as $shipment) {
                        if (
                            in_array(
                                $shipment->status,
                                [
                                    'picked_up',
                                    'in_transit',
                                    'at_sorting_center',
                                    'sorted',
                                    'staged',
                                    'assigned_to_rider',
                                    'out_for_delivery',
                                    'delivery_failed',
                                    'failed_return_at_sorting_center',
                                    'delivered',
                                ],
                                true
                            )
                        ) {
                            throw new \RuntimeException(
                                'Order can no longer be cancelled because '
                                    . 'a parcel is already in the logistics '
                                    . 'delivery process.'
                            );
                        }
                    }

                    /*
                |--------------------------------------------------------------------------
                | RELEASE RESERVED RIDER CAPACITY
                |--------------------------------------------------------------------------
                */
                    foreach ($shipments as $shipment) {
                        $riderIds = collect([
                            $shipment->pickup_rider_id,
                            $shipment->rider_id,
                        ])
                            ->filter()
                            ->unique()
                            ->values();

                        foreach ($riderIds as $riderId) {
                            $rider = \App\Models\User::query()
                                ->whereKey($riderId)
                                ->lockForUpdate()
                                ->first();

                            if (!$rider) {
                                continue;
                            }

                            $newLoad = max(
                                0,
                                (int) $rider->current_load - 1
                            );

                            $capacity = $rider->max_capacity;

                            $rider->update([
                                'current_load' => $newLoad,
                                'availability_status' =>
                                $capacity === null ||
                                    $newLoad < (int) $capacity
                                    ? 'available'
                                    : 'busy',
                            ]);
                        }

                        $shipmentUpdates = [
                            'rider_id' => null,
                            'status' => 'cancelled',
                        ];

                        if (
                            \Illuminate\Support\Facades\Schema::hasColumn(
                                'shipments',
                                'pickup_rider_id'
                            )
                        ) {
                            $shipmentUpdates['pickup_rider_id'] = null;
                        }

                        $shipment->update($shipmentUpdates);
                    }

                    /*
                |--------------------------------------------------------------------------
                | RESTORE INVENTORY
                |--------------------------------------------------------------------------
                */
                    $items = $lockedOrder
                        ->items()
                        ->get();

                    $productIds = $items
                        ->pluck('product_id')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values();

                    foreach ($productIds as $productId) {
                        \App\Models\Product::query()
                            ->whereKey($productId)
                            ->lockForUpdate()
                            ->first();
                    }

                    foreach ($items as $item) {
                        $quantity = (int) $item->quantity;

                        if ($quantity <= 0) {
                            continue;
                        }

                        if (
                            $item->size_id &&
                            $item->product_id
                        ) {
                            DB::table('product_size')
                                ->where(
                                    'product_id',
                                    $item->product_id
                                )
                                ->where(
                                    'size_id',
                                    $item->size_id
                                )
                                ->increment(
                                    'stock',
                                    $quantity
                                );
                        }

                        if ($item->variant_id) {
                            DB::table('product_variants')
                                ->where(
                                    'id',
                                    $item->variant_id
                                )
                                ->where(
                                    'product_id',
                                    $item->product_id
                                )
                                ->increment(
                                    'stock',
                                    $quantity
                                );
                        }

                        if ($item->product_id) {
                            DB::table('products')
                                ->where(
                                    'id',
                                    $item->product_id
                                )
                                ->increment(
                                    'stock',
                                    $quantity
                                );
                        }
                    }

                    /*
                |--------------------------------------------------------------------------
                | KEEP ORDER / SELLER ORDER / SHIPMENT IN SYNC
                |--------------------------------------------------------------------------
                */
                    $lockedOrder->sellerOrders()->update([
                        'status' => 'cancelled',
                    ]);

                    $lockedOrder->update([
                        'status' => 'cancelled',
                        'notes' => (
                            $lockedOrder->notes
                            ? $lockedOrder->notes . "\n"
                            : ''
                        )
                            . 'Cancellation reason: '
                            . $validated['reason'],
                    ]);

                    return true;
                },
                3
            );
        } catch (\RuntimeException $e) {
            return back()->with(
                'error',
                $e->getMessage()
            );
        }

        if (!$cancelled) {
            return back()->with(
                'error',
                'Order cannot be cancelled at this stage.'
            );
        }

        $this->createNotification(
            $order->user_id,
            'Order Cancelled',
            'Your order '
                . $order->order_number
                . ' has been cancelled.',
            'order',
            route('orders.index')
        );

        return back()->with(
            'success',
            'Order cancelled successfully.'
        );
    }

    public function confirmReceived(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) abort(403);

        if ($order->status !== 'delivered') {
            return back()->with('error', 'Cannot confirm receipt for this order.');
        }

        \DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'completed',
                'confirmed_received_at' => now(),
            ]);

            app(\App\Services\CommissionService::class)
                ->generateForOrder($order);
        });

        $order->refresh();
        $order->load('sellerOrders');

        foreach ($order->sellerOrders as $sellerOrder) {
            try {
                app(\App\Services\SellerSettlementService::class)
                    ->createIfEligible((int) $sellerOrder->id);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'Seller settlement retry after buyer confirmation failed.',
                    [
                        'order_id' => $order->id,
                        'seller_order_id' => $sellerOrder->id,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }
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
