<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Logistic;
use App\Models\User;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\ShipmentMessage;
use App\Models\RiderLocation;
use App\Models\Hub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;

class LogisticController extends Controller
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
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

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
    ->where('status', \App\Models\User::STATUS_ACTIVE)
    ->count();
        $shipments = $logistic->shipments();

        $totalShipments = $shipments->count();
        $pendingShipments = (clone $shipments)->whereIn('status', ['pending', 'assigned'])->count();
        $inTransitShipments = (clone $shipments)->whereIn('status', ['picked_up', 'in_transit', 'at_sorting_center', 'sorted', 'staged'])->count();
        $deliveredShipments = (clone $shipments)->where('status', 'delivered')->count();
        $delayedShipments = (clone $shipments)->whereIn('status', ['delayed', 'delivery_failed'])->count();
        $cancelledShipments = (clone $shipments)->where('status', 'cancelled')->count();

        $alertsCount = (clone $shipments)
            ->whereIn('status', ['cancelled', 'delayed'])
            ->orWhere(function ($query) {
                $query->where('status', 'pending')
                    ->where('created_at', '<', now()->subHours(24));
            })
            ->count();

        $totalHubs = $logistic->hubs()->count();
        $activeHubs = $logistic->hubs()->where('status', 'active')->count();

        $recentShipments = $shipments->with(['sellerOrder.order.user', 'sellerOrder.seller', 'rider'])->latest()->take(10)->get();

        $recentRiders = $logistic->riders()->latest()->take(5)->get();

        return view('logistic.dashboard', compact(
            'logistic', 'totalRiders', 'pendingRiders', 'activeRiders',
            'recentRiders', 'totalShipments', 'pendingShipments',
            'inTransitShipments', 'deliveredShipments', 'delayedShipments',
            'cancelledShipments', 'alertsCount', 'totalHubs', 'activeHubs',
            'recentShipments'
        ));
    }

    public function home()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

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
    ->where('status', \App\Models\User::STATUS_ACTIVE)
    ->count();

        $alerts = $logistic->shipments()
            ->whereIn('status', ['cancelled', 'delayed'])
            ->orWhere(function ($query) {
                $query->where('status', 'pending')
                    ->where('created_at', '<', now()->subHours(24));
            })
            ->latest()
            ->take(10)
            ->get();

        return view('logistic.home', compact('logistic', 'totalRiders', 'pendingRiders', 'activeRiders', 'alerts'));
    }

    public function riders()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $riders = $logistic->riders()->latest()->paginate(15);

        return view('logistic.riders', compact('logistic', 'riders'));
    }

    public function showRider(User $rider)
    {
        $logistic = Auth::user()->ownedLogistic;

        abort_unless(
            $logistic && $rider->logistic_id && (int) $rider->logistic_id === (int) $logistic->id,
            403,
            'This rider does not belong to your logistics company.'
        );

        return view('logistic.riders-show', compact('logistic', 'rider'));
    }

    public function approveRiderApplication(User $rider)
    {
        $logistic = Auth::user()->ownedLogistic;

        abort_unless(
            $logistic && $rider->logistic_id && (int) $rider->logistic_id === (int) $logistic->id,
            403,
            'This rider does not belong to your logistics company.'
        );

        abort_unless($rider->logistic_status === 'pending', 422, 'Invalid rider application.');

        $rider->update([
            'logistic_status' => 'approved',
            'logistic_approved_at' => now(),
            'logistic_rejection_reason' => null,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->createNotification(
            $rider->id,
            'Application Approved by Logistics',
            'Your application to join ' . $logistic->company_name . ' has been approved. You can now log in and start receiving deliveries.',
            'logistic'
        );

        return back()->with('success', 'Rider application approved. Rider can now log in.');
    }

    public function rejectRiderApplication(Request $request, User $rider)
    {
        $logistic = Auth::user()->ownedLogistic;

        abort_unless(
            $logistic && $rider->logistic_id && (int) $rider->logistic_id === (int) $logistic->id,
            403,
            'This rider does not belong to your logistics company.'
        );

        abort_unless($rider->logistic_status === 'pending', 422, 'Invalid rider application.');

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $rider->update([
            'logistic_status' => 'rejected',
            'logistic_rejection_reason' => $request->rejection_reason,
            'logistic_approved_at' => null,
            'status' => User::STATUS_PENDING,
        ]);

        $this->createNotification(
            $rider->id,
            'Application Rejected by Logistics',
            'Your application to join ' . $logistic->company_name . ' was rejected. Reason: ' . $request->rejection_reason,
            'logistic'
        );

        return back()->with('success', 'Rider application rejected.');
    }

    public function pendingApplications()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $applications = $logistic->riders()->where('logistic_status', 'pending')->latest()->paginate(15);

        return view('logistic.applications', compact('logistic', 'applications'));
    }

    public function showRiderRegistration()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        return view('logistic.register-rider', compact('logistic'));
    }

    public function storeRider(Request $request)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'mobile_number' => ['required', 'string', 'digits:11'],
            'birthday' => ['required', 'date'],
            'age' => ['required', 'integer', 'min:0', 'max:120'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'vehicle_type' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:255'],
            'or_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'cr_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'middle_name' => null,
            'last_name' => $validated['last_name'],
            'name' => $validated['last_name'] . ', ' . $validated['first_name'],
            'sex' => 'Male',
            'email' => $validated['email'],
            'mobile_number' => $validated['mobile_number'],
            'birthday' => $validated['birthday'],
            'age' => $validated['age'],
            'province' => $logistic->address ?? '',
            'municipality' => '',
            'barangay' => '',
            'house_number' => '',
            'street_address' => '',
            'password' => Hash::make($validated['password']),
            'phone' => $validated['mobile_number'],
            'status' => User::STATUS_ACTIVE,
            'vehicle_type' => $validated['vehicle_type'],
            'license_number' => $validated['license_number'],
            'logistic_id' => $logistic->id,
            'logistic_status' => 'approved',
            'logistic_approved_at' => now(),
        ]);

        $riderRole = \App\Models\Role::where('name', 'rider')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$riderRole->id]);

        if ($request->hasFile('or_document')) {
            $user->update(['or_document' => $request->file('or_document')->store('rider-documents', 'public')]);
        }

        if ($request->hasFile('cr_document')) {
            $user->update(['cr_document' => $request->file('cr_document')->store('rider-documents', 'public')]);
        }

        $this->createNotification(
            $user->id,
            'Rider Registered by Logistics',
            'You have been registered as a rider by ' . $logistic->company_name . '. Your account is now active.',
            'logistic'
        );

        try {
            $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new \App\Mail\NewRegistrationAdmin($user));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send admin notification for new rider: ' . $e->getMessage());
        }

        return redirect()->route('logistic.riders')->with('success', 'Rider registered successfully and linked to your company.');
    }

    public function shipments()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $shipments = $logistic->shipments()->with('sellerOrder.order.user', 'sellerOrder.items.product', 'sellerOrder.seller', 'rider')->latest()->paginate(20);

        return view('logistic.shipments', compact('logistic', 'shipments'));
    }

    public function sortingArea()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $pendingShipments = $logistic->shipments()
            ->where('sorting_status', 'pending')
            ->with('sellerOrder.order.user', 'sellerOrder.items.product', 'sellerOrder.seller')
            ->latest()
            ->paginate(20);

        $receivedShipments = $logistic->shipments()
            ->where('sorting_status', 'received')
            ->with('sellerOrder.order.user', 'sellerOrder.items.product', 'sellerOrder.seller')
            ->latest()
            ->paginate(20);

        $sortedShipments = $logistic->shipments()
            ->whereIn('sorting_status', ['sorted', 'staged'])
            ->with('sellerOrder.order.user', 'sellerOrder.items.product', 'sellerOrder.seller', 'rider')
            ->latest()
            ->paginate(20);

        return view('logistic.sorting-area', compact('logistic', 'pendingShipments', 'receivedShipments', 'sortedShipments'));
    }

    public function receiveShipment(Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.sorting-area')->with('error', 'Shipment not found.');
        }

        // Ensure this parcel has a hub based on its SellerOrder pickup location.
        if (!$shipment->hub_id && $shipment->sellerOrder) {
            $hub = \App\Services\HubAssignmentService::findBestHubForSellerOrder($shipment->sellerOrder);

            if ($hub) {
                $shipment->update([
                    'hub_id' => $hub->id,
                    'logistic_id' => $hub->logistic_id,
                ]);
            }
        }

        $shipment->update([
            'sorting_status' => 'received',
            'received_at' => now(),
            'received_by_sorting_center' => true,
        ]);

        // Increment pickup rider's daily quota if this was a pickup rider delivery
        $order = $shipment->sellerOrder?->order;
        if ($order && $order->rider_id) {
            $pickupRider = \App\Models\User::find($order->rider_id);
            if ($pickupRider && $pickupRider->isRider()) {
                $pickupRider->increment('daily_pickups_completed');
            }
        }

        // Notify the seller that owns this specific SellerOrder/parcel.
        $sellerOwnerId = $shipment->sellerOrder?->seller?->user_id;

        if ($order && $sellerOwnerId) {
            $hubName = $shipment->hub ? $shipment->hub->name : 'Sorting Center';
            $this->createNotification(
                $sellerOwnerId,
                'Parcel Received at Sorting Center',
                'Your order ' . $order->order_number . ' has been received at ' . $hubName . ' and is being processed for delivery.',
                'shipment',
                route('seller.orders.show', $order)
            );
        }

        // Notify buyer that parcel is at sorting center
        if ($order && $order->user_id) {
            $hubName = $shipment->hub ? $shipment->hub->name : 'Sorting Center';
            $this->createNotification(
                $order->user_id,
                'Order Received at Sorting Center',
                'Your order ' . $order->order_number . ' has been received at ' . $hubName . ' and will be sorted for delivery soon.',
                'delivery',
                route('orders.show', $order)
            );
        }

        return back()->with('success', 'Shipment confirmed as received at sorting center. Pickup rider quota updated, seller and buyer notified.');
    }

    public function scanShipment(Request $request, Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.sorting-area')->with('error', 'Shipment not found.');
        }

        if (!$shipment->received_by_sorting_center) {
            return back()->with('error', 'Cannot scan shipment: not yet confirmed as received by sorting center. Please receive the shipment first.');
        }

        $validated = $request->validate([
            'delivery_zone' => ['required', 'string', 'max:255'],
            'delivery_type' => ['required', 'in:standard,same_day,cod'],
        ]);

        $shipment->update([
            'sorting_status' => 'scanned',
            'scanned_at' => now(),
            'sorted_at' => now(),
            'delivery_zone' => $validated['delivery_zone'],
            'delivery_type' => $validated['delivery_type'],
        ]);

        $order = $shipment->sellerOrder?->order;

        if ($order) {
            $order->update([
                'status' => 'sorted',
                'delivery_status' => 'at_sorting_center',
                'delivery_zone' => $validated['delivery_zone'],
            ]);
        }

        $bestRider = \App\Services\RiderAssignmentService::findBestRiderForShipment($shipment);

        if ($bestRider) {
            \App\Services\RiderAssignmentService::assignRiderToShipment($shipment, $bestRider);

            \App\Models\Notification::create([
                'user_id' => $bestRider->id,
                'title' => 'New Delivery Assigned from Hub',
                'type' => 'delivery',
                'message' => 'Order ' . ($order?->order_number ?? 'N/A') . ' has been sorted at the hub. Destination: ' . $validated['delivery_zone'] . '. Please pick it up for final delivery to the customer.',
                'link' => route('rider.pickups', ['status' => 'sorting_center']),
            ]);

            \App\Models\Notification::create([
                'user_id' => $order?->user_id,
                'title' => 'Order Sorted at Hub',
                'type' => 'delivery',
                'message' => 'Your order ' . ($order?->order_number ?? 'N/A') . ' has been sorted at the hub and assigned to a rider for final delivery.',
                'link' => $order ? route('orders.show', $order) : null,
            ]);
        }

        return back()->with('success', 'Shipment scanned and sorted. Zone: ' . $validated['delivery_zone'] . ($bestRider ? '. Assigned to rider ' . $bestRider->name . ' for delivery.' : '. Awaiting rider assignment.'));
    }

    public function sortShipment(Request $request, Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.sorting-area')->with('error', 'Shipment not found.');
        }

        if (!$shipment->received_by_sorting_center) {
            return back()->with('error', 'Cannot sort shipment: not yet confirmed as received by sorting center. Please receive the shipment first.');
        }

        $validated = $request->validate([
            'sorting_area' => ['required', 'string', 'max:255'],
            'rack_number' => ['required', 'string', 'max:50'],
        ]);

        $shipment->update([
            'sorting_status' => 'sorted',
            'sorted_at' => now(),
            'sorting_area' => $validated['sorting_area'],
            'rack_number' => $validated['rack_number'],
        ]);

        return back()->with('success', 'Package sorted to ' . $validated['sorting_area'] . ' rack ' . $validated['rack_number'] . '.');
    }

    public function stageShipment(Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.sorting-area')->with('error', 'Shipment not found.');
        }

        if (!$shipment->received_by_sorting_center) {
            return back()->with('error', 'Cannot stage shipment: not yet confirmed as received by sorting center. Please receive the shipment first.');
        }

        $shipment->update([
            'sorting_status' => 'staged',
            'staged_at' => now(),
        ]);

        return back()->with('success', 'Package staged for rider pickup.');
    }

    public function assignRider(Request $request, Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.sorting-area')->with('error', 'Shipment not found.');
        }

        $validated = $request->validate([
            'rider_id' => ['required', 'exists:users,id'],
        ]);

        $rider = User::where('id', $validated['rider_id'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'rider'))
            ->where('logistic_id', $logistic->id)
            ->where('availability_status', 'available')
            ->first();

        if (!$rider) {
            return back()->with('error', 'Rider not available or not found.');
        }

        if ($rider->current_load >= $rider->max_capacity) {
            return back()->with('error', 'Rider has reached maximum capacity.');
        }

        $shipment->update([
            'rider_id' => $rider->id,
            'status' => $shipment->status === 'at_sorting_center' ? 'staged' : 'assigned',
        ]);

        $order = $shipment->sellerOrder?->order;

        if ($order) {
            // Keep these legacy Order fields synchronized until the rider/order
            // screens are fully parcel-based.
            $order->update([
                'rider_id' => $rider->id,
                'delivery_status' => 'ready_for_delivery_pickup',
            ]);
        }

        $rider->increment('current_load');
        if ($rider->current_load >= $rider->max_capacity) {
            $rider->update(['availability_status' => 'busy']);
        }

        $this->createNotification(
            $rider->id,
            'New Delivery Assignment',
            'You have been assigned a new delivery. Tracking: ' . $shipment->tracking_number,
            'shipment',
            $order ? route('rider.orders.show', $order) : null
        );

        return back()->with('success', 'Rider ' . $rider->name . ' assigned to shipment.');
    }

    public function availableRiders()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return response()->json([]);
        }

        $riders = $logistic->riders()
            ->where('availability_status', 'available')
            ->whereColumn('current_load', '<', 'max_capacity')
            ->select('id', 'name', 'current_load', 'max_capacity', 'assigned_zone')
            ->get();

        return response()->json($riders);
    }

    public function autoAssignByZone(Request $request)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('logistic.sorting-area')->with('error', 'No logistics company found.');
        }

        $validated = $request->validate([
            'zone' => ['required', 'string', 'max:255'],
        ]);

        $zone = $validated['zone'];
        $zoneShipments = $logistic->shipments()
            ->where('delivery_zone', $zone)
            ->where('sorting_status', 'sorted')
            ->whereNull('rider_id')
            ->with('sellerOrder.order.user', 'sellerOrder.items.product', 'sellerOrder.seller')
            ->get();

        // Get the hub from the first shipment (they should all be in the same hub area)
        $hubId = $zoneShipments->first()?->hub_id;

        $riderQuery = $logistic->riders()
            ->where('availability_status', 'available')
            ->whereColumn('current_load', '<', 'max_capacity');

        if ($hubId) {
            $riderQuery->where('hub_id', $hubId);
        }

        $rider = $riderQuery->first();

        if (!$rider) {
            return back()->with('error', 'No available rider found for zone: ' . $zone);
        }

        $assignedCount = 0;
        foreach ($zoneShipments as $shipment) {
            if ($rider->current_load < $rider->max_capacity) {
                $shipment->update([
                    'rider_id' => $rider->id,
                    'status' => 'assigned',
                ]);

                $rider->increment('current_load');
                $assignedCount++;
            }
        }

        if ($rider->current_load >= $rider->max_capacity) {
            $rider->update(['availability_status' => 'busy']);
        }

        if ($assignedCount > 0) {
            $this->createNotification(
                $rider->id,
                'New Delivery Assignment (' . $assignedCount . ' packages)',
                'You have been assigned ' . $assignedCount . ' package(s) for zone: ' . $zone . '.',
                'shipment'
            );
        }

        return back()->with('success', 'Auto-assigned ' . $assignedCount . ' package(s) to ' . $rider->name . ' for zone: ' . $zone);
    }

    public function autoScanZone(Request $request, Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return response()->json(['error' => 'Shipment not found'], 404);
        }

        $deliveryAddress = strtolower($shipment->delivery_address);
        $zone = 'Zone B - Luzon';

        if (str_contains($deliveryAddress, 'manila') || str_contains($deliveryAddress, 'quezon') || str_contains($deliveryAddress, 'makati') || str_contains($deliveryAddress, 'pasig') || str_contains($deliveryAddress, 'taguig') || str_contains($deliveryAddress, 'mcity') || str_contains($deliveryAddress, 'paraÃƒÆ’Ã‚Â±aque') || str_contains($deliveryAddress, 'valenzuela') || str_contains($deliveryAddress, 'malabon') || str_contains($deliveryAddress, 'caloocan') || str_contains($deliveryAddress, 'las piÃƒÆ’Ã‚Â±as') || str_contains($deliveryAddress, 'mandaluyong') || str_contains($deliveryAddress, 'marikina') || str_contains($deliveryAddress, 'mersa') || str_contains($deliveryAddress, 'navotas') || str_contains($deliveryAddress, 'san juan') || str_contains($deliveryAddress, 'tondo') || str_contains($deliveryAddress, 'manila') || str_contains($deliveryAddress, 'ncr')) {
            $zone = 'Zone A - Metro Manila';
        } elseif (str_contains($deliveryAddress, 'cebu') || str_contains($deliveryAddress, 'iloilo') || str_contains($deliveryAddress, 'bacolod') || str_contains($deliveryAddress, 'cagayan de oro') || str_contains($deliveryAddress, 'davao') || str_contains($deliveryAddress, 'cavite') || str_contains($deliveryAddress, 'laguna') || str_contains($deliveryAddress, 'batangas') || str_contains($deliveryAddress, 'pampanga') || str_contains($deliveryAddress, 'bulacan') || str_contains($deliveryAddress, 'rizal') || str_contains($deliveryAddress, 'quezon') || str_contains($deliveryAddress, 'laguna') || str_contains($deliveryAddress, 'pagsanjan') || str_contains($deliveryAddress, 'los ba') || str_contains($deliveryAddress, 'santa cruz')) {
            $zone = 'Zone A - Metro Manila';
        } elseif (str_contains($deliveryAddress, 'cagayan') || str_contains($deliveryAddress, 'iligan') || str_contains($deliveryAddress, 'zonk') || str_contains($deliveryAddress, 'samal') || str_contains($deliveryAddress, 'bukidnon') || str_contains($deliveryAddress, 'misamis')) {
            $zone = 'Zone B - Luzon';
        } elseif (str_contains($deliveryAddress, 'cebu') || str_contains($deliveryAddress, 'bohol') || str_contains($deliveryAddress, 'negros') || str_contains($deliveryAddress, 'crown') || str_contains($deliveryAddress, 'siquior') || str_contains($deliveryAddress, 'bacolod') || str_contains($deliveryAddress, 'iloilo')) {
            $zone = 'Zone C - Visayas';
        } elseif (str_contains($deliveryAddress, 'davao') || str_contains($deliveryAddress, 'cagayan de oro') || str_contains($deliveryAddress, 'pagadian') || str_contains($deliveryAddress, 'iligan') || str_contains($deliveryAddress, 'ozamiz') || str_contains($deliveryAddress, 'misamis')) {
            $zone = 'Zone D - Mindanao';
        }

        $shipment->update([
            'sorting_status' => 'scanned',
            'scanned_at' => now(),
            'delivery_zone' => $zone,
        ]);

        return response()->json(['success' => true, 'zone' => $zone]);
    }

    public function showShipment(Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.shipments')->with('error', 'Shipment not found in your company.');
        }

        $riders = $logistic->riders()->where('logistic_status', 'approved')->get();

        return view('logistic.shipments-show', compact('logistic', 'shipment', 'riders'));
    }

    public function createShipment()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $riders = $logistic->riders()
            ->where('logistic_status', 'approved')
            ->get();

        // Only show SellerOrders/parcels that do not have a shipment yet
        // and are either unassigned or already assigned to this logistics company.
        $sellerOrders = \App\Models\SellerOrder::with([
                'order.user',
                'seller',
            ])
            ->whereDoesntHave('shipment')
            ->where(function ($query) use ($logistic) {
                $query->whereNull('logistic_id')
                    ->orWhere('logistic_id', $logistic->id);
            })
            ->latest()
            ->get();

        return view('logistic.shipments-create', compact(
            'logistic',
            'riders',
            'sellerOrders'
        ));
    }

    public function storeShipment(Request $request)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $validated = $request->validate([
            'seller_order_id' => ['required', 'exists:seller_orders,id'],
            'rider_id' => ['nullable', 'exists:users,id'],
            'courier' => ['nullable', 'string', 'max:255'],
            'pickup_address' => ['required', 'string', 'max:1000'],
            'delivery_address' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $sellerOrder = \App\Models\SellerOrder::with('order')
            ->whereKey($validated['seller_order_id'])
            ->where(function ($query) use ($logistic) {
                $query->whereNull('logistic_id')
                    ->orWhere('logistic_id', $logistic->id);
            })
            ->first();

        if (!$sellerOrder) {
            return back()->with('error', 'Seller order is not available for this logistics company.');
        }

        if ($sellerOrder->shipment()->exists()) {
            return back()->with('error', 'This seller order already has a shipment.');
        }

        $trackingNumber = 'SPE-' . strtoupper(uniqid());

        $shipment = Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistic_id' => $logistic->id,
            'rider_id' => $validated['rider_id'],
            'tracking_number' => $trackingNumber,
            'status' => $validated['rider_id'] ? 'assigned' : 'pending',
            'courier' => $validated['courier'],
            'pickup_address' => $validated['pickup_address'],
            'delivery_address' => $validated['delivery_address'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'notes' => $validated['notes'],
        ]);

        if (!$sellerOrder->logistic_id) {
            $sellerOrder->update(['logistic_id' => $logistic->id]);
        }

        if ($validated['rider_id']) {
            $this->createNotification(
                $validated['rider_id'],
                'New Shipment Assigned',
                'You have been assigned a new shipment. Tracking: ' . $trackingNumber,
                'shipment'
            );
        }

        return redirect()->route('logistic.shipments')->with('success', 'Shipment created successfully. Tracking: ' . $trackingNumber);
    }

    public function updateShipmentStatus(Request $request, Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.shipments')->with('error', 'Shipment not found in your company.');
        }

        $request->validate([
            'status' => ['required', 'in:pending,assigned,picked_up,in_transit,delivered,cancelled,delayed'],
        ]);

        $shipment->update(['status' => $request->status]);

        if ($request->status === 'delivered') {
            $shipment->update(['delivered_at' => now()]);
        }

        if ($request->status === 'picked_up') {
            $shipment->update(['picked_up_at' => now()]);
        }

        if ($shipment->rider_id) {
            $this->createNotification(
                $shipment->rider_id,
                'Shipment Status Updated',
                'Shipment ' . $shipment->tracking_number . ' status changed to ' . $request->status,
                'shipment'
            );
        }

        if ($request->status === 'cancelled') {
            $this->createNotification(
                $shipment->logistic->owner_user_id,
                'Shipment Cancelled',
                'Shipment ' . $shipment->tracking_number . ' has been cancelled.',
                'alert'
            );

            $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();
            foreach ($admins as $admin) {
                $this->createNotification(
                    $admin->id,
                    'Shipment Cancelled',
                    'Shipment ' . $shipment->tracking_number . ' from ' . $shipment->logistic->company_name . ' has been cancelled.',
                    'alert'
                );
            }
        }

        if ($request->status === 'delayed') {
            $this->createNotification(
                $shipment->logistic->owner_user_id,
                'Shipment Delayed',
                'Shipment ' . $shipment->tracking_number . ' has been marked as delayed.',
                'alert'
            );

            $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();
            foreach ($admins as $admin) {
                $this->createNotification(
                    $admin->id,
                    'Shipment Delayed',
                    'Shipment ' . $shipment->tracking_number . ' from ' . $shipment->logistic->company_name . ' has been delayed.',
                    'alert'
                );
            }
        }

        $pendingFor24Hours = $logistic->shipments()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        if ($pendingFor24Hours > 0) {
            $this->createNotification(
                Auth::id(),
                'Pending Shipments Alert',
                'You have ' . $pendingFor24Hours . ' shipment(s) pending for more than 24 hours.',
                'alert'
            );
        }

        return back()->with('success', 'Shipment status updated successfully.');
    }

    public function updateRiderLocation(Request $request, User $rider)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $rider->logistic_id !== $logistic->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        RiderLocation::updateOrCreate(
            ['user_id' => $rider->id],
            [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'accuracy' => $validated['accuracy'] ?? null,
            ]
        );

        return response()->json(['message' => 'Location updated successfully']);
    }

    public function trackShipment(Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.shipments')->with('error', 'Shipment not found in your company.');
        }

        $riderLocation = null;
        if ($shipment->rider_id) {
            $riderLocation = RiderLocation::where('user_id', $shipment->rider_id)->latest()->first();
        }

        return view('logistic.shipments-track', compact('shipment', 'riderLocation'));
    }

    public function shipmentChat(Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.shipments')->with('error', 'Shipment not found in your company.');
        }

        $messages = $shipment->messages()->with('user')->latest()->paginate(50);

        return view('logistic.shipments-chat', compact('shipment', 'messages'));
    }

    public function shipmentChatStore(Request $request, Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.shipments')->with('error', 'Shipment not found in your company.');
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        ShipmentMessage::create([
            'shipment_id' => $shipment->id,
            'user_id' => Auth::id(),
            'message' => $validated['message'],
        ]);

        if ($shipment->rider_id && Auth::id() !== $shipment->rider_id) {
            $this->createNotification(
                $shipment->rider_id,
                'New Message on Shipment ' . $shipment->tracking_number,
                'You have a new message on shipment ' . $shipment->tracking_number,
                'shipment',
                route('logistic.shipments.chat', $shipment)
            );
        }

        return back()->with('success', 'Message sent successfully.');
    }

    public function reports()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $totalShipments = $logistic->shipments()->count();
        $deliveredShipments = $logistic->shipments()->where('status', 'delivered')->count();
        $cancelledShipments = $logistic->shipments()->where('status', 'cancelled')->count();
        $delayedShipments = $logistic->shipments()->where('status', 'delayed')->count();
        $pendingShipments = $logistic->shipments()->where('status', 'pending')->count();

        $successRate = $totalShipments > 0 ? round(($deliveredShipments / $totalShipments) * 100, 1) : 0;

        $dailyReports = $logistic->shipments()
            ->select(
                \Illuminate\Support\Facades\DB::raw('DATE(created_at) as date'),
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN status = "delayed" THEN 1 ELSE 0 END) as `delayed`')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        $riderPerformance = $logistic->riders()
            ->withCount(['shipments as delivered_count' => function ($query) {
                $query->where('status', 'delivered');
            }])
            ->withCount('shipments as total_count')
            ->get()
            ->map(function ($rider) {
                $rider->success_rate = $rider->total_count > 0 ? round(($rider->delivered_count / $rider->total_count) * 100, 1) : 0;
                return $rider;
            });

        return view('logistic.reports', compact(
            'logistic',
            'totalShipments',
            'deliveredShipments',
            'cancelledShipments',
            'delayedShipments',
            'pendingShipments',
            'successRate',
            'dailyReports',
            'riderPerformance'
        ));
    }

    public function account()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        return view('logistic.account', compact('logistic'));
    }

    public function about()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $totalShipments = $logistic->shipments()->count();
        $deliveredShipments = $logistic->shipments()->where('status', 'delivered')->count();
        $totalRiders = $logistic->riders()->where('logistic_status', 'approved')->count();
        $totalHubs = $logistic->hubs()->count();

        return view('logistic.about', compact('logistic', 'totalShipments', 'deliveredShipments', 'totalRiders', 'totalHubs'));
    }

    public function updateAccount(Request $request)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'api_address' => 'nullable|string|max:500',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $apiAddress = $validated['api_address'] ?? $logistic->api_address;

        if ($apiAddress && ($apiAddress !== $logistic->api_address || !$logistic->latitude || !$logistic->longitude)) {
            $geocoded = $this->geocodeAddress($apiAddress);
            if ($geocoded) {
                $validated['latitude'] = $geocoded['lat'];
                $validated['longitude'] = $geocoded['lng'];
            }
        }

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('logistic-logos', 'public');
        }

        $logistic->update($validated);

        return back()->with('success', 'Account information updated successfully.');
    }

    public function hubs()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $hubs = $logistic->hubs()->with('riders')->latest()->paginate(15);

        return view('logistic.hubs.index', compact('logistic', 'hubs'));
    }

    public function createHub()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        return view('logistic.hubs.create', compact('logistic'));
    }

    public function storeHub(Request $request)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'api_address' => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $geocoded = $this->geocodeAddress($validated['api_address'] ?? $validated['address']);

        $hub = \App\Models\Hub::create([
            'logistic_id' => $logistic->id,
            'name' => $validated['name'],
            'address' => $validated['address'],
            'api_address' => $validated['api_address'] ?? null,
            'latitude' => $geocoded['lat'] ?? null,
            'longitude' => $geocoded['lng'] ?? null,
            'contact_person' => $validated['contact_person'],
            'phone' => $validated['phone'],
            'status' => $validated['status'],
        ]);

        return redirect()->route('logistic.hubs.show', $hub)->with('success', 'Hub created successfully.');
    }

    public function showHub(\App\Models\Hub $hub)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $hub->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.hubs')->with('error', 'Hub not found.');
        }

        $hub->load('riders');

        $availableRiders = $logistic->riders()
            ->where('logistic_status', 'approved')
            ->whereNull('hub_id')
            ->where('availability_status', 'available')
            ->get();

        return view('logistic.hubs.show', compact('logistic', 'hub', 'availableRiders'));
    }

    public function updateHub(Request $request, \App\Models\Hub $hub)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $hub->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.hubs')->with('error', 'Hub not found.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'api_address' => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $geocoded = $this->geocodeAddress($validated['api_address'] ?? $validated['address']);

        if ($geocoded) {
            $validated['latitude'] = $geocoded['lat'];
            $validated['longitude'] = $geocoded['lng'];
        }

        $hub->update($validated);

        return back()->with('success', 'Hub updated successfully.');
    }

    public function destroyHub(\App\Models\Hub $hub)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $hub->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.hubs')->with('error', 'Hub not found.');
        }

        $hub->riders()->update(['hub_id' => null]);
        $hub->delete();

        return redirect()->route('logistic.hubs')->with('success', 'Hub deleted successfully.');
    }

    public function hubRiders(\App\Models\Hub $hub)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $hub->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.hubs')->with('error', 'Hub not found.');
        }

        $hub->load(['riders']);

        $availableRiders = $logistic->riders()
            ->where('logistic_status', 'approved')
            ->whereNull('hub_id')
            ->get();

        return view('logistic.hubs.riders', compact('logistic', 'hub', 'availableRiders'));
    }

    public function assignRiderToHub(Request $request, \App\Models\Hub $hub)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $hub->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.hubs')->with('error', 'Hub not found.');
        }

        $validated = $request->validate([
            'rider_id' => ['required', 'exists:users,id'],
        ]);

        $rider = \App\Models\User::where('id', $validated['rider_id'])
            ->where('logistic_id', $logistic->id)
            ->whereNull('hub_id')
            ->first();

        if (!$rider) {
            return back()->with('error', 'Rider not found or already assigned to a hub.');
        }

        $rider->update(['hub_id' => $hub->id]);

        return back()->with('success', 'Rider assigned to hub successfully.');
    }

    public function unassignRiderFromHub(Request $request, \App\Models\Hub $hub)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $hub->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.hubs')->with('error', 'Hub not found.');
        }

        $validated = $request->validate([
            'rider_id' => ['required', 'exists:users,id'],
        ]);

        $rider = \App\Models\User::where('id', $validated['rider_id'])
            ->where('hub_id', $hub->id)
            ->first();

        if (!$rider) {
            return back()->with('error', 'Rider not found in this hub.');
        }

        $rider->update(['hub_id' => null]);

        return back()->with('success', 'Rider unassigned from hub successfully.');
    }

    public function notifications()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->paginate(20);

        return view('logistic.notifications.index', compact('logistic', 'notifications'));
    }

    public function markNotificationRead(Notification $notification)
    {
        if ($notification->user_id !== Auth::id()) {
            abort(403);
        }

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllNotificationsRead()
    {
        Auth::user()->notifications()->where('is_read', false)->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    private function geocodeAddress($address)
    {
        $apiKey = config('services.googlemaps.key');
        if (!$apiKey || !$address) {
            return null;
        }

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $apiKey;

        $response = @file_get_contents($url);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if ($data['status'] === 'OK' && !empty($data['results'])) {
            $location = $data['results'][0]['geometry']['location'];
            return [
                'lat' => $location['lat'],
                'lng' => $location['lng'],
            ];
        }

        return null;
    }
}
