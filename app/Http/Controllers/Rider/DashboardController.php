<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private function createNotification($userId, $title, $message, $type = 'delivery', $link = null)
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
        $rider = Auth::user();
        $assignedOrders = Order::where('rider_id', $rider->id)
            ->whereIn('delivery_status', ['assigned_to_rider', 'out_for_delivery'])
            ->with(['user', 'items.product'])
            ->latest()->paginate(20);

        $completedToday = Order::where('rider_id', $rider->id)
            ->where('delivery_status', 'delivered')
            ->whereDate('delivered_at', today())
            ->count();

        $totalDelivered = Order::where('rider_id', $rider->id)
            ->where('delivery_status', 'delivered')
            ->count();

        $logistic = $rider->logistic;

        return view('rider.dashboard', compact('assignedOrders', 'completedToday', 'totalDelivered', 'logistic', 'rider'));
    }

    public function orders(Request $request)
    {
        $rider = Auth::user();
        $query = Order::where('rider_id', $rider->id)
            ->with(['user', 'items.product']);

        if ($request->filled('status')) {
            $query->where('delivery_status', $request->status);
        }

        $orders = $query->latest()->paginate(20);

        return view('rider.orders', compact('orders'));
    }

    public function show(Order $order)
    {
        if ($order->rider_id !== Auth::id()) {
            abort(403);
        }

        $order->load('user', 'items.product');

        return view('rider.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        if ($order->rider_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'delivery_status' => 'required|in:out_for_delivery,delivered,delivery_failed',
            'delivery_notes' => 'nullable|string|max:1000',
            'failure_reason' => 'nullable|string|max:1000',
            'proof_of_delivery' => 'required_if:delivery_status,delivered|nullable|image|mimes:jpg,jpeg,png|max:5120',
            'delivery_signature' => 'nullable|string|max:1000',
            'delivered_to' => 'nullable|string|max:255',
        ]);

        if (!in_array($order->delivery_status, ['assigned_to_rider', 'picked_up_from_sorting_center', 'out_for_delivery', 'ready_for_delivery_pickup'])) {
            if ($request->delivery_status === 'out_for_delivery' && $order->delivery_status !== 'picked_up_from_sorting_center' && $order->delivery_status !== 'ready_for_delivery_pickup') {
                return back()->with('error', 'Order must be picked up from sorting center first.');
            }
        }

        $data = [
            'delivery_status' => $request->delivery_status,
            'delivery_notes' => $request->delivery_notes,
        ];

        if ($request->delivery_status === 'delivered') {
            $data['delivered_at'] = now();
            $data['status'] = 'delivered';

            // Handle proof of delivery upload
            if ($request->hasFile('proof_of_delivery')) {
                $data['proof_of_delivery'] = $request->file('proof_of_delivery')->store('proof-of-delivery', 'public');
            }
            $data['delivery_signature'] = $request->delivery_signature;
            $data['delivered_to'] = $request->delivered_to;

            \App\Models\Shipment::where('order_id', $order->id)->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);

            Auth::user()->decrement('current_load');
            if (Auth::user()->current_load < Auth::user()->max_capacity) {
                Auth::user()->update(['availability_status' => 'available']);
            }

            // Increment delivery rider's daily quota
            Auth::user()->increment('daily_deliveries_completed');

            // Notify buyer
            $this->createNotification(
                $order->user_id,
                'Order Delivered',
                'Your order ' . $order->order_number . ' has been delivered' . ($request->delivered_to ? ' to ' . $request->delivered_to : '') . '.',
                'delivery',
                route('orders.show', $order)
            );

            // Notify seller
            if ($order->seller_id) {
                $this->createNotification(
                    $order->seller_id,
                    'Order Delivered to Customer',
                    'Order ' . $order->order_number . ' has been successfully delivered' . ($request->delivered_to ? ' to ' . $request->delivered_to : '') . '. Proof of delivery available.',
                    'shipment',
                    route('seller.orders.show', $order)
                );
            }

            // Notify logistic owner
            if (Auth::user()->logistic_id) {
                $logisticOwner = \App\Models\Logistic::find(Auth::user()->logistic_id)->owner ?? null;
                if ($logisticOwner) {
                    $this->createNotification(
                        $logisticOwner->id,
                        'Delivery Completed',
                        'Rider ' . Auth::user()->name . ' completed delivery for order ' . $order->order_number . ' with proof of delivery.',
                        'shipment',
                        route('logistic.shipments')
                    );
                }
            }
        } elseif ($request->delivery_status === 'delivery_failed') {
            $data['failed_at'] = now();
            $data['failure_reason'] = $request->failure_reason;
            $data['status'] = 'delivery_failed';

            \App\Models\Shipment::where('order_id', $order->id)->update([
                'status' => 'delivery_failed',
            ]);

            Auth::user()->decrement('current_load');
            if (Auth::user()->current_load < Auth::user()->max_capacity) {
                Auth::user()->update(['availability_status' => 'available']);
            }

            $this->createNotification(
                $order->user_id,
                'Delivery Failed - Action Required',
                'Delivery for order ' . $order->order_number . ' failed. Reason: ' . ($request->failure_reason ?? 'Unknown') . '. Please choose whether to reschedule delivery or return the item.',
                'delivery',
                route('orders.show', $order)
            );

            if (Auth::user()->logistic_id) {
                $logisticOwner = \App\Models\Logistic::find(Auth::user()->logistic_id)->owner ?? null;
                if ($logisticOwner) {
                    $this->createNotification(
                        $logisticOwner->id,
                        'Delivery Failed - Rider Report',
                        'Rider ' . Auth::user()->name . ' reported failed delivery for order ' . $order->order_number . '. Reason: ' . ($request->failure_reason ?? 'Unknown') . '. Buyer has been notified to choose reschedule or return.',
                        'alert'
                    );
                }
            }
        } elseif ($request->delivery_status === 'out_for_delivery') {
            \App\Models\Shipment::where('order_id', $order->id)->update([
                'status' => 'out_for_delivery',
            ]);

            $this->createNotification(
                $order->user_id,
                'Order On The Way',
                'Your order ' . $order->order_number . ' is on the way!',
                'delivery',
                route('orders.show', $order)
            );
        }

        $order->update($data);

        return back()->with('success', 'Delivery status updated.');
    }

    public function confirmCOD(Order $order)
    {
        if ($order->rider_id !== Auth::id()) {
            abort(403);
        }

        $order->update(['payment_method' => 'cod']);

        return back()->with('success', 'COD confirmed for this order.');
    }

    public function collectPayment(Request $request, Order $order)
    {
        if ($order->rider_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'amount_collected' => 'required|numeric|min:0',
        ]);

        $order->update([
            'payment_status' => 'paid',
            'amount_collected' => $request->amount_collected,
            'collected_at' => now(),
            'collected_by' => Auth::id(),
        ]);

        return back()->with('success', 'Payment collected successfully.');
    }

    public function reportCash()
    {
        $rider = Auth::user();
        $today = now()->startOfDay();

        $cashReport = Order::where('collected_by', $rider->id)
            ->where('payment_method', 'cod')
            ->where('payment_status', 'paid')
            ->whereDate('collected_at', $today)
            ->selectRaw('SUM(amount_collected) as total_collected, COUNT(*) as total_orders')
            ->first();

        $recentCollections = Order::where('collected_by', $rider->id)
            ->where('payment_method', 'cod')
            ->where('payment_status', 'paid')
            ->with('user')
            ->latest('collected_at')
            ->take(20)
            ->get();

        return view('rider.cash_report', compact('cashReport', 'recentCollections'));
    }

    public function uploadProof(Request $request, Order $order)
    {
        if ($order->rider_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'proof_type' => 'required|in:photo,signature,otp',
            'proof_data' => 'required|string',
        ]);

        $order->update([
            'proof_type' => $request->proof_type,
            'proof_data' => $request->proof_data,
        ]);

        return back()->with('success', 'Proof of delivery submitted.');
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $rider = Auth::user();
        $rider->update([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'location_updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function track()
    {
        $rider = Auth::user();
        return response()->json([
            'latitude' => $rider->latitude,
            'longitude' => $rider->longitude,
            'updated_at' => $rider->location_updated_at,
        ]);
    }

    public function addresses(Request $request)
    {
        $rider = Auth::user();

        $query = Order::where('rider_id', $rider->id)
            ->whereNotNull('shipping_address')
            ->with(['user']);

        if ($request->filled('status')) {
            $query->where('delivery_status', $request->status);
        }

        $addresses = $query->latest()->paginate(20);

        return view('rider.addresses', compact('addresses'));
    }

    public function deliveries(Request $request)
    {
        $rider = Auth::user();

        $deliveries = Order::where(function ($query) {
            $query->whereNull('rider_id')
                  ->orWhere('delivery_status', 'pending');
        })
        ->where('ready_for_pickup', true)
        ->with(['user', 'items.product'])
        ->latest()
        ->paginate(20);

        return view('rider.deliveries', compact('deliveries'));
    }

    public function accept(Order $order)
    {
        if ($order->rider_id && $order->rider_id !== Auth::id()) {
            return back()->with('error', 'This delivery has already been accepted by another courier.');
        }

        $order->update([
            'rider_id' => Auth::id(),
            'delivery_status' => 'assigned_to_rider',
            'assigned_at' => now(),
        ]);

        $shipment = \App\Models\Shipment::where('order_id', $order->id)->first();
        if ($shipment) {
            $shipment->update([
                'rider_id' => Auth::id(),
                'status' => 'assigned',
            ]);
        } else {
            $seller = $order->items->first()->product->user ?? null;
            $hub = \App\Services\HubAssignmentService::findBestHubForOrder($order);

            if ($hub) {
                $trackingNumber = 'SPE-' . strtoupper(uniqid());
                \App\Models\Shipment::create([
                    'logistic_id' => $hub->logistic_id,
                    'hub_id' => $hub->id,
                    'rider_id' => Auth::id(),
                    'order_id' => $order->id,
                    'tracking_number' => $trackingNumber,
                    'status' => 'assigned',
                    'pickup_address' => $seller ? ($seller->business_name . ', ' . $seller->street_address . ', ' . $seller->barangay . ', ' . $seller->municipality . ', ' . $seller->province) : 'Seller address',
                    'delivery_address' => $order->shipping_address,
                    'notes' => 'Rider accepted delivery - auto-assigned hub based on order location',
                ]);
            }
        }

        $this->createNotification(
            $order->user_id,
            'Rider Assigned',
            'A rider has been assigned to your order ' . $order->order_number . '.',
            'delivery',
            route('orders.show', $order)
        );

        $this->createNotification(
            Auth::id(),
            'Delivery Accepted',
            'You have accepted delivery for order ' . $order->order_number . '.',
            'delivery',
            route('rider.orders.show', $order)
        );

        return redirect()->route('rider.deliveries')->with('success', 'Delivery accepted! Please proceed to pickup.');
    }

    public function pickups(Request $request)
    {
        $rider = Auth::user();
        $query = Order::where('rider_id', $rider->id)
            ->whereIn('delivery_status', ['assigned_to_rider', 'in_transit'])
            ->with(['user', 'items.product', 'shipment']);

        if ($request->filled('status')) {
            $query->where('delivery_status', $request->status);
        }

        $pickups = $request->filled('status') && $request->status === 'sorting_center'
            ? Order::where(function ($q) use ($rider) {
                    $q->where('rider_id', $rider->id)
                      ->whereIn('delivery_status', ['at_sorting_center', 'ready_for_delivery_pickup', 'picked_up_from_sorting_center']);
                })
                ->whereHas('shipment', function ($q) {
                    $q->whereIn('status', ['at_sorting_center', 'staged', 'picked_up']);
                })->with(['user', 'items.product', 'shipment'])
                ->latest()->paginate(20)
            : $query->latest()->paginate(20);

        return view('rider.pickups', compact('pickups'));
    }

    public function confirmPickup(Request $request, Order $order)
    {
        if ($order->rider_id !== Auth::id()) {
            abort(403);
        }

        $order->update([
            'status' => 'picked_up',
            'delivery_status' => 'in_transit',
            'picked_up_at' => now(),
        ]);

        \App\Models\Shipment::where('order_id', $order->id)->update([
            'status' => 'in_transit',
            'picked_up_at' => now(),
        ]);

        $this->createNotification(
            $order->user_id,
            'Order Picked Up',
            'Your order ' . $order->order_number . ' has been picked up and is on the way to the sorting center.',
            'delivery',
            route('orders.show', $order)
        );

        return back()->with('success', 'Pickup confirmed. Order is now in transit to the sorting center.');
    }

    public function deliverToSortingCenter(Request $request, Order $order)
    {
        if ($order->rider_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $oldRider = \App\Models\User::find($order->rider_id);

        // Ensure shipment has hub assigned based on seller/pickup location
        $shipment = $order->shipment;
        if ($shipment && !$shipment->hub_id) {
            $hub = \App\Services\HubAssignmentService::findBestHubForOrder($order);
            if ($hub) {
                $shipment->update(['hub_id' => $hub->id, 'logistic_id' => $hub->logistic_id]);
            }
        }

        $order->update([
            'status' => 'at_sorting_center',
            'delivery_status' => 'delivered_to_sorting_center',
            'delivered_at' => now(),
        ]);

        \App\Models\Shipment::where('order_id', $order->id)->update([
            'status' => 'at_sorting_center',
            'at_sorting_center_at' => now(),
            'notes' => ($request->notes ? $request->notes . "\n" : '') . ($order->shipment->notes ?? ''),
            'received_by_sorting_center' => false,
        ]);

        if ($oldRider) {
            $oldRider->decrement('current_load');
            if ($oldRider->current_load < $oldRider->max_capacity) {
                $oldRider->update(['availability_status' => 'available']);
            }
        }

        // Notify sorting center / logistic owner that parcel is awaiting confirmation
        $logisticOwner = \App\Models\Logistic::find(Auth::user()->logistic_id)->owner ?? null;
        if ($logisticOwner) {
            $hubName = $shipment->hub ? $shipment->hub->name : 'Sorting Center';
            $this->createNotification(
                $logisticOwner->id,
                'Parcel Awaiting Sorting Center Confirmation',
                'Rider ' . Auth::user()->name . ' has delivered order ' . $order->order_number . ' to ' . $hubName . '. Awaiting physical receipt confirmation.',
                'shipment',
                route('logistic.sorting-area')
            );
        }

        return back()->with('success', 'Parcel delivered to sorting center. Awaiting sorting center confirmation.');
    }

    public function pickupFromSortingCenter(Order $order)
    {
        $rider = Auth::user();
        $shipment = \App\Models\Shipment::where('order_id', $order->id)->first();

        if (!$shipment || !in_array($shipment->status, ['at_sorting_center', 'staged'])) {
            return back()->with('error', 'This parcel is not available for pickup from the sorting center.');
        }

        $order->update([
            'rider_id' => $rider->id,
            'delivery_status' => 'picked_up_from_sorting_center',
            'picked_up_at' => now(),
        ]);

        $shipment->update([
            'rider_id' => $rider->id,
            'status' => 'picked_up',
            'picked_up_at' => now(),
        ]);

        $this->createNotification(
            $order->user_id,
            'Order Picked Up for Delivery',
            'Your order ' . $order->order_number . ' has been picked up from the sorting center for delivery.',
            'delivery',
            route('orders.show', $order)
        );

        return back()->with('success', 'Parcel picked up from sorting center. Proceed to deliver to customer.');
    }

    public function history(Request $request)
    {
        $rider = Auth::user();

        $query = Order::where('rider_id', $rider->id)
            ->whereIn('delivery_status', ['delivered', 'failed'])
            ->with(['user', 'items.product']);

        if ($request->filled('status')) {
            $query->where('delivery_status', $request->status);
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $from = \Carbon\Carbon::parse($request->from_date)->startOfDay();
            $to = \Carbon\Carbon::parse($request->to_date)->endOfDay();
            $query->whereBetween('delivered_at', [$from, $to]);
        }

        $history = $query->latest()->paginate(20);

        return view('rider.history', compact('history'));
    }

    public function profit(Request $request)
    {
        $rider = Auth::user();

        $query = Order::where('rider_id', $rider->id)
            ->where('delivery_status', 'delivered')
            ->with(['user', 'items.product']);

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $from = \Carbon\Carbon::parse($request->from_date)->startOfDay();
            $to = \Carbon\Carbon::parse($request->to_date)->endOfDay();
            $query->whereBetween('delivered_at', [$from, $to]);
        }

        $deliveries = $query->latest()->get();

        $totalDelivered = $deliveries->count();
        $totalEarnings = $deliveries->sum(function ($order) {
            return $order->amount_collected ?: 0;
        });

        $chartData = $deliveries->groupBy(function ($order) {
            return $order->delivered_at->format('M d, Y');
        })->map(function ($group) {
            return $group->count();
        })->sortKeys();

        return view('rider.profit', compact('deliveries', 'totalDelivered', 'totalEarnings', 'chartData'));
    }

    public function messages()
    {
        return view('rider.messages');
    }

    public function account()
    {
        $user = Auth::user();
        return view('rider.account', compact('user'));
    }

    public function updateAccount(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'sex' => ['required', 'in:Male,Female'],
            'email' => ['required', 'email', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'birthday' => ['required', 'date'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'province' => ['required', 'string', 'max:255'],
            'municipality' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'vehicle_type' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:255'],
        ]);

        $userData = [
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'name' => $request->last_name . ', ' . $request->first_name . ' ' . $request->middle_name,
            'sex' => $request->sex,
            'email' => $request->email,
            'mobile_number' => $request->mobile_number,
            'birthday' => $request->birthday,
            'age' => $request->age,
            'province' => $request->province,
            'municipality' => $request->municipality,
            'barangay' => $request->barangay,
            'house_number' => $request->house_number,
            'street_address' => $request->street_address,
            'vehicle_type' => $request->vehicle_type,
            'license_number' => $request->license_number,
        ];

        $user->update($userData);

        return back()->with('success', 'Account updated successfully.');
    }

    public function markNotificationRead(Request $request, Notification $notification)
    {
        if ($notification->user_id !== Auth::id()) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        return back()->with('success', 'Notification marked as read.');
    }

    public function notifications()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->paginate(20);

        return view('rider.notifications', compact('notifications'));
    }

    public function markAllNotificationsRead()
    {
        Auth::user()->notifications()->where('is_read', false)->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
