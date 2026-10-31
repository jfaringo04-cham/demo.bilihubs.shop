<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Logistic;
use App\Models\User;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\SellerOrder;
use App\Models\ShipmentMessage;
use App\Models\RiderLocation;
use App\Models\Hub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\DB;

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

        $alertsCount = $logistic->shipments()
            ->where(function ($query) {
                $query->whereIn('status', ['cancelled', 'delayed'])
                    ->orWhere(function ($pendingQuery) {
                        $pendingQuery->where('status', 'pending')
                            ->where('created_at', '<', now()->subHours(24));
                    });
            })
            ->count();

        $totalHubs = $logistic->hubs()->count();
        $activeHubs = $logistic->hubs()->where('status', 'active')->count();

        $recentShipments = $shipments->with(['sellerOrder.order.user', 'sellerOrder.seller', 'rider'])->latest()->take(10)->get();

        $recentRiders = $logistic->riders()->latest()->take(5)->get();

        return view('logistic.dashboard', compact(
            'logistic',
            'totalRiders',
            'pendingRiders',
            'activeRiders',
            'recentRiders',
            'totalShipments',
            'pendingShipments',
            'inTransitShipments',
            'deliveredShipments',
            'delayedShipments',
            'cancelledShipments',
            'alertsCount',
            'totalHubs',
            'activeHubs',
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
            ->where(function ($query) {
                $query->whereIn('status', ['cancelled', 'delayed'])
                    ->orWhere(function ($pendingQuery) {
                        $pendingQuery->where('status', 'pending')
                            ->where('created_at', '<', now()->subHours(24));
                    });
            })
            ->latest()
            ->take(10)
            ->get();

        return view('logistic.home', compact('logistic', 'totalRiders', 'pendingRiders', 'activeRiders', 'alerts'));
    }

    public function riderAssignment(Request $request)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()
                ->route('home')
                ->with('error', 'No logistics company found for your account.');
        }

        /*
    |--------------------------------------------------------------------------
    | Shipments ready for rider assignment
    |--------------------------------------------------------------------------
    */
        $shipmentsQuery = Shipment::query()
            ->where('logistic_id', $logistic->id)
            ->where('sorting_status', 'staged')
            ->where('received_by_sorting_center', true)
            ->whereNull('rider_id')
            ->whereNotIn('status', [
                'delivered',
                'cancelled',
                'canceled',
                'delivery_failed',
            ])
            ->whereHas('sellerOrder.order')
            ->where(function ($query) {
                $query
                    ->where(function ($normalQuery) {
                        $normalQuery
                            ->where('status', '!=', 'failed_return_at_sorting_center')
                            ->whereNull('failed_return_received_at');
                    })
                    ->orWhere(function ($returnQuery) {
                        $returnQuery
                            ->where('status', 'failed_return_at_sorting_center')
                            ->whereNotNull('failed_return_received_at')
                            ->whereHas('sellerOrder', function ($sellerQuery) {
                                $sellerQuery->where('reschedule_status', 'approved');
                            });
                    });
            })
            ->with([
                'sellerOrder.order.user',
                'sellerOrder.seller',
            ]);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $shipmentsQuery->where(function ($query) use ($search) {
                $query->where('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('sellerOrder.order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('sellerOrder.order.user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $shipments = $shipmentsQuery
            ->latest()
            ->paginate(15)
            ->withQueryString();

        /*
    |--------------------------------------------------------------------------
    | Available riders
    |--------------------------------------------------------------------------
    */
        $availableRiders = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('logistic_id', $logistic->id)
            ->where('logistic_status', 'approved')
            ->where('status', User::STATUS_ACTIVE)
            ->where('availability_status', 'available')
            ->where('max_capacity', '>', 0)
            ->whereColumn('current_load', '<', 'max_capacity')
            ->orderBy('name')
            ->get();

        /*
    |--------------------------------------------------------------------------
    | Dashboard counters
    |--------------------------------------------------------------------------
    */
        $awaitingAssignmentCount = Shipment::query()
            ->where('logistic_id', $logistic->id)
            ->where('sorting_status', 'staged')
            ->where('received_by_sorting_center', true)
            ->whereNull('rider_id')
            ->whereNotIn('status', [
                'delivered',
                'cancelled',
                'canceled',
                'delivery_failed',
            ])
            ->whereHas('sellerOrder.order')
            ->where(function ($query) {
                $query
                    ->where(function ($normalQuery) {
                        $normalQuery
                            ->where('status', '!=', 'failed_return_at_sorting_center')
                            ->whereNull('failed_return_received_at');
                    })
                    ->orWhere(function ($returnQuery) {
                        $returnQuery
                            ->where('status', 'failed_return_at_sorting_center')
                            ->whereNotNull('failed_return_received_at')
                            ->whereHas('sellerOrder', function ($sellerQuery) {
                                $sellerQuery->where('reschedule_status', 'approved');
                            });
                    });
            })
            ->count();

        $availableRidersCount = $availableRiders->count();

        $assignedCount = Shipment::where('logistic_id', $logistic->id)
            ->whereNotNull('rider_id')
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->count();

        $fullCapacityCount = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('logistic_id', $logistic->id)
            ->whereColumn('current_load', '>=', 'max_capacity')
            ->count();

        return view('logistic.rider-assignment', compact(
            'logistic',
            'shipments',
            'availableRiders',
            'awaitingAssignmentCount',
            'availableRidersCount',
            'assignedCount',
            'fullCapacityCount'
        ));
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

        $request->merge([
            'vehicle_type' => strtolower(
                trim((string) $request->input('vehicle_type'))
            ),
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'mobile_number' => ['required', 'string', 'digits:11'],
            'birthday' => ['required', 'date'],
            'age' => ['required', 'integer', 'min:0', 'max:120'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'vehicle_type' => ['required', 'string', 'in:motorcycle,car,van,truck'],
            'license_number' => ['required', 'string', 'max:255'],
            'or_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'cr_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);


        $maxCapacity = match ($validated['vehicle_type']) {
            'motorcycle' => 5,
            'car' => 10,
            'van' => 30,
            'truck' => 100,
            default => 5,
        };

        $uploadedPaths = [];

        try {
            // Upload required rider documents to private storage.
            $orPath = $request->file('or_document')
                ->store('rider-documents', 'supabase_private');

            if (!$orPath) {
                throw new \RuntimeException('OR document upload failed.');
            }

            $uploadedPaths[] = $orPath;

            $crPath = $request->file('cr_document')
                ->store('rider-documents', 'supabase_private');

            if (!$crPath) {
                throw new \RuntimeException('CR document upload failed.');
            }

            $uploadedPaths[] = $crPath;

            // Create rider and assign role atomically.
            $user = DB::transaction(function () use (
                $validated,
                $logistic,
                $orPath,
                $crPath,
                $maxCapacity
            ) {
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
                    'max_capacity' => $maxCapacity,
                    'current_load' => 0,
                    'availability_status' => 'offline',
                    'license_number' => $validated['license_number'],
                    'logistic_id' => $logistic->id,
                    'logistic_status' => 'approved',
                    'logistic_approved_at' => now(),
                    'or_document' => $orPath,
                    'cr_document' => $crPath,
                ]);

                $riderRole = \App\Models\Role::where('name', 'rider')
                    ->firstOrFail();

                $user->roles()->syncWithoutDetaching([
                    $riderRole->id,
                ]);

                return $user;
            });
        } catch (\Throwable $e) {
            // Delete only files uploaded during this attempt.
            foreach ($uploadedPaths as $path) {
                try {
                    Storage::disk('supabase_private')->delete($path);
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $e;
        }


        $this->createNotification(
            $user->id,
            'Rider Registered by Logistics',
            'You have been registered as a rider by ' . $logistic->company_name . '. Your account is now active.',
            'logistic'
        );

        try {
            $admins = User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new \App\Mail\NewRegistrationAdmin($user));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send admin notification for new rider: ' . $e->getMessage());
        }

        return redirect()->route('logistic.riders')->with('success', 'Rider registered successfully and linked to your company.');
    }

    public function orders(Request $request)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()
                ->route('home')
                ->with('error', 'No logistics company found for your account.');
        }

        $baseQuery = SellerOrder::query()
            ->where('logistic_id', $logistic->id);

        // Summary cards
        $totalOrders = (clone $baseQuery)->count();

        $pendingOrders = (clone $baseQuery)
            ->where('status', 'pending')
            ->count();

        $deliveredOrders = (clone $baseQuery)
            ->where('status', 'delivered')
            ->count();

        // Order list
        $orders = SellerOrder::query()
            ->where('logistic_id', $logistic->id)
            ->with([
                'order.user',
                'seller',
                'items.product',
                'shipment.rider',
            ]);

        // Search
        if ($request->filled('search')) {
            $search = trim($request->search);

            $orders->where(function ($query) use ($search) {
                $query
                    ->whereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery
                            ->where('order_number', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereHas('shipment', function ($shipmentQuery) use ($search) {
                        $shipmentQuery
                            ->where('tracking_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('seller', function ($sellerQuery) use ($search) {
                        $sellerQuery
                            ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Seller order status
        if ($request->filled('status')) {
            $orders->where('status', $request->status);
        }

        $orders = $orders
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('logistic.orders.index', compact(
            'logistic',
            'orders',
            'totalOrders',
            'pendingOrders',
            'deliveredOrders'
        ));
    }




    public function shipments(Request $request)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()
                ->route('home')
                ->with('error', 'No logistics company found for your account.');
        }

        /*
    |--------------------------------------------------------------------------
    | Base Query
    |--------------------------------------------------------------------------
    */
        $baseQuery = $logistic->shipments();

        /*
    |--------------------------------------------------------------------------
    | Summary Cards
    |--------------------------------------------------------------------------
    */
        $totalShipments = (clone $baseQuery)->count();

        $pendingShipments = (clone $baseQuery)
            ->whereIn('status', ['pending', 'assigned'])
            ->count();

        $inTransitShipments = (clone $baseQuery)
            ->whereIn('status', [
                'picked_up',
                'in_transit',
                'at_sorting_center',
                'sorted',
                'staged'
            ])
            ->count();

        $deliveredShipments = (clone $baseQuery)
            ->where('status', 'delivered')
            ->count();

        /*
    |--------------------------------------------------------------------------
    | Shipment List
    |--------------------------------------------------------------------------
    */
        $shipments = $logistic->shipments()
            ->with([
                'sellerOrder.order.user',
                'sellerOrder.items.product',
                'sellerOrder.seller',
                'rider'
            ]);

        /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */
        if ($request->filled('search')) {

            $search = trim($request->search);

            $shipments->where(function ($query) use ($search) {

                $query->where('tracking_number', 'like', "%{$search}%")

                    ->orWhere('courier', 'like', "%{$search}%")

                    ->orWhereHas('rider', function ($riderQuery) use ($search) {
                        $riderQuery->where('name', 'like', "%{$search}%");
                    })

                    ->orWhereHas('sellerOrder.order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_number', 'like', "%{$search}%");
                    })

                    ->orWhereHas('sellerOrder.order.user', function ($buyerQuery) use ($search) {
                        $buyerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })

                    ->orWhereHas('sellerOrder.seller', function ($sellerQuery) use ($search) {
                        $sellerQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        /*
    |--------------------------------------------------------------------------
    | Status Filter
    |--------------------------------------------------------------------------
    */
        if ($request->filled('status')) {

            $allowedStatuses = [
                'pending',
                'assigned',
                'picked_up',
                'in_transit',
                'delivered',
                'cancelled',
                'delayed'
            ];

            if (in_array($request->status, $allowedStatuses, true)) {
                $shipments->where('status', $request->status);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Results
    |--------------------------------------------------------------------------
    */
        $shipments = $shipments
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('logistic.shipments', compact(
            'logistic',
            'shipments',
            'totalShipments',
            'pendingShipments',
            'inTransitShipments',
            'deliveredShipments'
        ));
    }

    public function sortingArea()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $pendingShipments = $logistic->shipments()
            ->where('sorting_status', 'pending')
            ->where('status', 'at_sorting_center')
            ->whereNotIn('routing_status', ['at_origin_hub', 'in_hub_transfer'])
            ->whereNotNull('pickup_rider_id')
            ->whereNotNull('picked_up_at')
            ->whereNotNull('at_sorting_center_at')
            ->where('received_by_sorting_center', false)
            ->whereHas('sellerOrder.order', function ($query) {
                $query->where('status', '!=', 'cancelled');
            })
            ->with(
                'sellerOrder.order.user',
                'sellerOrder.items.product',
                'sellerOrder.seller'
            )
            ->latest()
            ->paginate(20);

        $receivedShipments = $logistic->shipments()
            ->where('sorting_status', 'received')
            ->with('sellerOrder.order.user', 'sellerOrder.items.product', 'sellerOrder.seller')
            ->latest()
            ->paginate(20);

        $sortedShipments = $logistic->shipments()
            ->whereIn('sorting_status', ['scanned', 'sorted', 'staged'])
            ->with('sellerOrder.order.user', 'sellerOrder.items.product', 'sellerOrder.seller', 'rider')
            ->latest()
            ->paginate(20);

        return view('logistic.sorting-area', compact('logistic', 'pendingShipments', 'receivedShipments', 'sortedShipments'));
    }


    public function receiveShipment(Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        $isFailedReturn = $shipment->status === 'failed_return_at_sorting_center';

        if (!$isFailedReturn) {
            if (
                !in_array($shipment->status, ['in_transit', 'at_sorting_center'], true) ||
                !$shipment->pickup_rider_id ||
                !$shipment->picked_up_at
            ) {
                throw new \RuntimeException(
                    'The pickup rider must collect the parcel before it can be received at the origin hub.'
                );
            }
        }

        if (!$logistic || (int) $shipment->logistic_id !== (int) $logistic->id) {
            abort(403, 'Shipment does not belong to your logistics company.');
        }

        try {
            $result = \Illuminate\Support\Facades\DB::transaction(
                function () use ($shipment, $logistic) {
                    $shipment = Shipment::query()
                        ->whereKey($shipment->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ((int) $shipment->logistic_id !== (int) $logistic->id) {
                        throw new \RuntimeException('Shipment logistics ownership has changed.');
                    }

                    $shipment->loadMissing([
                        'sellerOrder.order',
                        'sellerOrder.seller',
                        'hub',
                        'originHub',
                        'destinationHub',
                    ]);

                    $sellerOrder = $shipment->sellerOrder;
                    $order = $sellerOrder?->order;

                    if (!$sellerOrder || !$order) {
                        throw new \RuntimeException('Shipment is not linked to a valid seller order.');
                    }

                    $isFailedReturn = $shipment->status === 'failed_return_at_sorting_center';

                    if ($isFailedReturn) {
                        if ($shipment->received_by_sorting_center) {
                            throw new \RuntimeException('This shipment has already been received.');
                        }

                        $riderIdToRelease = $shipment->rider_id;
                        if (!$riderIdToRelease) {
                            throw new \RuntimeException('Failed-return parcel has no assigned delivery rider.');
                        }

                        $this->releaseRiderCapacity($riderIdToRelease, false);

                        $shipment->update([
                            'sorting_status' => 'received',
                            'received_at' => now(),
                            'at_sorting_center_at' => now(),
                            'received_by_sorting_center' => true,
                            'rider_id' => null,
                            'status' => 'failed_return_at_sorting_center',
                            'failed_return_received_at' => now(),
                        ]);

                        return [
                            'order' => $order,
                            'seller_order' => $sellerOrder,
                            'hub_name' => $shipment->hub?->name ?? 'Sorting Center',
                            'transfer_required' => false,
                        ];
                    }

                    if ($shipment->origin_hub_received_at !== null) {
                        throw new \RuntimeException('This parcel has already been received at its origin hub.');
                    }

                    $originHubId = (int) ($shipment->origin_hub_id ?: $shipment->hub_id);
                    $destinationHubId = (int) ($shipment->destination_hub_id ?: $originHubId);

                    if (!$originHubId || !$destinationHubId) {
                        throw new \RuntimeException('Shipment route is incomplete. Assign origin and destination hubs first.');
                    }

                    if ((int) $shipment->hub_id !== $originHubId) {
                        throw new \RuntimeException('Parcel must be received by its assigned origin hub.');
                    }

                    if ($shipment->pickup_rider_id) {
                        $this->releaseRiderCapacity((int) $shipment->pickup_rider_id, true);
                    }

                    $sameHub = $originHubId === $destinationHubId;
                    $now = now();

                    if ($sameHub) {
                        $shipment->update([
                            'hub_id' => $destinationHubId,
                            'routing_status' => 'at_destination_hub',
                            'origin_hub_received_at' => $now,
                            'destination_hub_received_at' => $now,
                            'sorting_status' => 'received',
                            'received_at' => $now,
                            'at_sorting_center_at' => $now,
                            'received_by_sorting_center' => true,
                            'rider_id' => null,
                            'status' => 'at_sorting_center',
                        ]);
                    } else {
                        $shipment->update([
                            'hub_id' => $originHubId,
                            'routing_status' => 'at_origin_hub',
                            'origin_hub_received_at' => $now,
                            'sorting_status' => 'pending',
                            'received_at' => null,
                            'at_sorting_center_at' => $now,
                            'received_by_sorting_center' => false,
                            'rider_id' => null,
                            'status' => 'at_sorting_center',
                        ]);
                    }

                    return [
                        'order' => $order,
                        'seller_order' => $sellerOrder,
                        'hub_name' => $shipment->originHub?->name ?? $shipment->hub?->name ?? 'Origin Hub',
                        'transfer_required' => !$sameHub,
                    ];
                }
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        app(\App\Services\OrderFlowSyncService::class)->syncShipment($shipment->fresh());

        $order = $result['order'];
        $sellerOrder = $result['seller_order'];
        $hubName = $result['hub_name'];

        if ($sellerOrder->seller?->user_id) {
            $this->createNotification(
                $sellerOrder->seller->user_id,
                'Parcel Received at Hub',
                'Parcel #' . $sellerOrder->id . ' from order ' . $order->order_number
                    . ' has arrived at ' . $hubName . '.',
                'shipment',
                route('seller.orders.show', $order)
            );
        }

        if ($order->user_id) {
            $this->createNotification(
                $order->user_id,
                'Parcel Received at Hub',
                'Parcel #' . $sellerOrder->id . ' from order ' . $order->order_number
                    . ' has arrived at ' . $hubName . '.',
                'delivery',
                route('orders.show', $order)
            );
        }

        return back()->with(
            'success',
            $result['transfer_required']
                ? 'Parcel received at origin hub. It is ready for inter-hub dispatch.'
                : 'Parcel received at destination hub. It is ready for sorting.'
        );
    }

    private function releaseRiderCapacity(int $riderId, bool $countPickup): void
    {
        $rider = \App\Models\User::query()
            ->whereKey($riderId)
            ->lockForUpdate()
            ->firstOrFail();

        $newLoad = max(0, (int) $rider->current_load - 1);
        $capacity = $rider->max_capacity;

        $rider->update([
            'current_load' => $newLoad,
            'availability_status' => $capacity === null || $newLoad < (int) $capacity
                ? 'available'
                : 'busy',
        ]);

        if ($countPickup) {
            $rider->increment('daily_pickups_completed');
        }
    }

    public function hubTransfers()
    {
        $logistic = Auth::user()->ownedLogistic;
        if (!$logistic) {
            return redirect()->route('home')->with('error', 'No logistics company found for your account.');
        }

        $atOrigin = Shipment::query()
            ->where('logistic_id', $logistic->id)
            ->where('routing_status', 'at_origin_hub')
            ->with(['sellerOrder.order.user', 'originHub', 'destinationHub'])
            ->latest()
            ->get();

        $inTransfer = Shipment::query()
            ->where('logistic_id', $logistic->id)
            ->where('routing_status', 'in_hub_transfer')
            ->with(['sellerOrder.order.user', 'originHub', 'destinationHub'])
            ->latest()
            ->get();

        return view('logistic.hub-transfers', compact('logistic', 'atOrigin', 'inTransfer'));
    }

    public function dispatchHubTransfer(Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;
        abort_unless($logistic && (int) $shipment->logistic_id === (int) $logistic->id, 403);

        try {
            \App\Services\HubTransferService::dispatch($shipment);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Parcel dispatched from origin hub to destination hub.');
    }

    public function receiveDestinationHub(Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;
        abort_unless($logistic && (int) $shipment->logistic_id === (int) $logistic->id, 403);

        try {
            \App\Services\HubTransferService::receiveAtDestination($shipment);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Parcel received at destination hub and is ready for sorting.');
    }

    public function scanShipment(Request $request, Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || (int) $shipment->logistic_id !== (int) $logistic->id) {
            abort(403, 'Shipment does not belong to your logistics company.');
        }

        $validated = $request->validate([
            'delivery_zone' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::exists('users', 'assigned_zone')
                    ->where(function ($query) use ($logistic) {
                        $query->where('logistic_id', $logistic->id)
                            ->where('logistic_status', 'approved')
                            ->where('status', \App\Models\User::STATUS_ACTIVE);
                    }),
            ],
            'delivery_type' => ['required', 'in:standard,same_day,express'],
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(
                function () use ($shipment, $logistic, $validated) {
                    $lockedShipment = Shipment::query()
                        ->whereKey($shipment->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ((int) $lockedShipment->logistic_id !== (int) $logistic->id) {
                        throw new \RuntimeException('Shipment ownership changed.');
                    }

                    \App\Services\HubTransferService::ensureAtDestinationForSorting(
                        $lockedShipment
                    );

                    if (
                        !$lockedShipment->received_by_sorting_center ||
                        $lockedShipment->sorting_status !== 'received'
                    ) {
                        throw new \RuntimeException(
                            'Shipment must be received before scanning.'
                        );
                    }

                    if ($lockedShipment->rider_id !== null) {
                        throw new \RuntimeException(
                            'Shipment still has an assigned rider.'
                        );
                    }

                    $lockedShipment->update([
                        'sorting_status' => 'scanned',
                        'scanned_at' => now(),
                        'delivery_zone' => $validated['delivery_zone'],
                        'delivery_type' => $validated['delivery_type'],
                    ]);
                }
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Shipment scanned successfully.');
    }

    public function sortShipment(Request $request, Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || (int) $shipment->logistic_id !== (int) $logistic->id) {
            abort(403, 'Shipment does not belong to your logistics company.');
        }

        $validated = $request->validate([
            'sorting_area' => ['required', 'string', 'max:255'],
            'rack_number' => ['required', 'string', 'max:50'],
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(
                function () use ($shipment, $logistic, $validated) {
                    $lockedShipment = Shipment::query()
                        ->whereKey($shipment->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ((int) $lockedShipment->logistic_id !== (int) $logistic->id) {
                        throw new \RuntimeException('Shipment ownership changed.');
                    }

                    \App\Services\HubTransferService::ensureAtDestinationForSorting(
                        $lockedShipment
                    );

                    if (
                        !$lockedShipment->received_by_sorting_center ||
                        $lockedShipment->sorting_status !== 'scanned'
                    ) {
                        throw new \RuntimeException(
                            'Shipment must be scanned before sorting.'
                        );
                    }

                    $lockedShipment->update([
                        'sorting_status' => 'sorted',
                        'sorting_area' => $validated['sorting_area'],
                        'rack_number' => $validated['rack_number'],
                        'sorted_at' => now(),
                    ]);
                }
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Shipment sorted successfully.');
    }

    public function stageShipment(Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || (int) $shipment->logistic_id !== (int) $logistic->id) {
            abort(403, 'Shipment does not belong to your logistics company.');
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(
                function () use ($shipment, $logistic) {
                    $lockedShipment = Shipment::query()
                        ->whereKey($shipment->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ((int) $lockedShipment->logistic_id !== (int) $logistic->id) {
                        throw new \RuntimeException('Shipment ownership changed.');
                    }

                    \App\Services\HubTransferService::ensureAtDestinationForSorting(
                        $lockedShipment
                    );

                    if (
                        !$lockedShipment->received_by_sorting_center ||
                        $lockedShipment->sorting_status !== 'sorted'
                    ) {
                        throw new \RuntimeException(
                            'Shipment must be sorted before staging.'
                        );
                    }

                    if ($lockedShipment->rider_id !== null) {
                        throw new \RuntimeException(
                            'Cannot stage a shipment with an active rider.'
                        );
                    }

                    $lockedShipment->update([
                        'sorting_status' => 'staged',
                        'staged_at' => now(),
                    ]);
                }
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Shipment staged for final-mile delivery.');
    }




    public function assignPickupRider(
        \Illuminate\Http\Request $request,
        \App\Models\Shipment $shipment
    ) {
        $logistic = \Illuminate\Support\Facades\Auth::user()->ownedLogistic;

        if (
            !$logistic ||
            (int) $shipment->logistic_id !== (int) $logistic->id
        ) {
            abort(403, 'This shipment does not belong to your logistics company.');
        }

        $validated = $request->validate([
            'rider_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $offer = \Illuminate\Support\Facades\DB::transaction(
                function () use ($shipment, $validated, $logistic) {
                    $shipment = \App\Models\Shipment::query()
                        ->whereKey($shipment->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (
                        $shipment->status !== 'pending' ||
                        $shipment->pickup_rider_id !== null ||
                        $shipment->rider_id !== null ||
                        $shipment->received_by_sorting_center ||
                        $shipment->picked_up_at !== null
                    ) {
                        throw new \RuntimeException(
                            'This shipment is no longer available for seller pickup.'
                        );
                    }

                    if (
                        !$shipment->sellerOrder ||
                        $shipment->sellerOrder->status !== 'ready_for_pickup'
                    ) {
                        throw new \RuntimeException(
                            'The seller must mark the order Ready for Pickup first.'
                        );
                    }

                    \App\Models\PickupRequest::query()
                        ->where('shipment_id', $shipment->id)
                        ->where('status', 'offered')
                        ->where('expires_at', '<=', now())
                        ->update([
                            'status' => 'expired',
                            'responded_at' => now(),
                        ]);

                    $activeOffer = \App\Models\PickupRequest::query()
                        ->where('shipment_id', $shipment->id)
                        ->where('status', 'offered')
                        ->first();

                    if ($activeOffer) {
                        throw new \RuntimeException(
                            'This shipment already has an active pickup offer.'
                        );
                    }

                    $rider = \App\Models\User::query()
                        ->whereKey($validated['rider_id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    $eligible = $rider->roles()
                        ->where('name', 'rider')
                        ->exists();

                    if (
                        !$eligible ||
                        $rider->status !== \App\Models\User::STATUS_ACTIVE ||
                        $rider->logistic_status !== 'approved' ||
                        (int) $rider->logistic_id !== (int) $logistic->id ||
                        $rider->availability_status !== 'available' ||
                        (int) $rider->max_capacity <= 0 ||
                        (int) $rider->current_load >= (int) $rider->max_capacity ||
                        (
                            !$shipment->hub_id ||
                            (int) $rider->hub_id !== (int) $shipment->hub_id
                        )
                    ) {
                        throw new \RuntimeException(
                            'The selected rider is not eligible for this pickup.'
                        );
                    }

                    $pickupZone = \App\Services\SellerPickupZoneService::resolve(
                        $shipment->sellerOrder
                    );

                    if (
                        !$pickupZone ||
                        \App\Services\SellerPickupZoneService::normalize(
                            $rider->assigned_zone
                        ) !== $pickupZone
                    ) {
                        throw new \RuntimeException(
                            'The selected rider is not assigned to the seller pickup zone.'
                        );
                    }

                    $previouslyOffered = \App\Models\PickupRequest::query()
                        ->where('shipment_id', $shipment->id)
                        ->where('rider_id', $rider->id)
                        ->exists();

                    if ($previouslyOffered) {
                        throw new \RuntimeException(
                            'This rider has already received an offer for this shipment.'
                        );
                    }

                    return \App\Models\PickupRequest::create([
                        'shipment_id' => $shipment->id,
                        'rider_id' => $rider->id,
                        'status' => 'offered',
                        'offered_at' => now(),
                        'expires_at' => now()->addMinutes(10),
                    ]);
                }
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $rider = \App\Models\User::findOrFail($offer->rider_id);

        $this->createNotification(
            $rider->id,
            'New Seller Pickup Request',
            'You have a pickup request for shipment '
                . $shipment->tracking_number
                . '. Please accept or decline within 10 minutes.',
            'shipment',
            route('rider.pickups')
        );

        return back()->with(
            'success',
            'Pickup offer sent to ' . $rider->name
                . ' for shipment ' . $shipment->tracking_number
                . '. Awaiting rider acceptance.'
        );
    }


    public function assignRider(Request $request, Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (
            !$logistic ||
            (int) $shipment->logistic_id !== (int) $logistic->id
        ) {
            return redirect()
                ->route('logistic.sorting-area')
                ->with('error', 'Shipment not found.');
        }

        $validated = $request->validate([
            'rider_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Final-mile assignments require a staged parcel.
        if ($shipment->sorting_status !== 'staged') {
            return back()->with(
                'error',
                'This shipment must be sorted and staged before assigning a delivery rider.'
            );
        }

        if ($shipment->rider_id !== null) {
            return back()->with(
                'error',
                'This shipment already has a delivery rider assigned.'
            );
        }

        $order = $shipment->sellerOrder?->order;

        if (!$order) {
            return back()->with(
                'error',
                'Shipment is not linked to a valid order.'
            );
        }


        // Failed-return parcels require approval from their own seller.
        if ($shipment->failed_return_received_at !== null) {
            $sellerOrder = $shipment->sellerOrder;

            if (
                !$sellerOrder ||
                $sellerOrder->reschedule_status !== 'approved'
            ) {
                return back()->with(
                    'error',
                    'This seller order requires an approved reschedule before rider assignment.'
                );
            }

            if ($shipment->status !== 'failed_return_at_sorting_center') {
                return back()->with(
                    'error',
                    'The failed-return shipment is not in the expected sorting-center state.'
                );
            }
        }


        $rider = User::find($validated['rider_id']);

        if (!$rider) {
            return back()->with('error', 'Rider not found.');
        }

        try {
            \App\Services\RiderAssignmentService::assignRiderToShipment(
                $shipment,
                $rider
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Assignment has been committed successfully.
        $this->createNotification(
            $rider->id,
            'New Delivery Assignment',
            'You have been assigned a new delivery. Tracking: '
                . $shipment->tracking_number,
            'shipment',
            route('rider.orders.show', $order)
        );

        return back()->with(
            'success',
            'Rider ' . $rider->name . ' assigned to shipment '
                . $shipment->tracking_number . '.'
        );
    }

    public function availableRiders()
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return response()->json([]);
        }

        $riders = $logistic->riders()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('logistic_status', 'approved')
            ->where('status', User::STATUS_ACTIVE)
            ->where('availability_status', 'available')
            ->where('max_capacity', '>', 0)
            ->whereColumn('current_load', '<', 'max_capacity')
            ->select(
                'id',
                'name',
                'hub_id',
                'vehicle_type',
                'current_load',
                'max_capacity',
                'assigned_zone'
            )
            ->get();

        return response()->json($riders);
    }

    public function autoAssignByZone(Request $request)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic) {
            return redirect()
                ->route('logistic.sorting-area')
                ->with('error', 'No logistics company found.');
        }

        $validated = $request->validate([
            'zone' => ['required', 'string', 'max:255'],
        ]);

        $zone = $validated['zone'];

        // Only staged, unassigned parcels are eligible.
        $shipments = Shipment::query()
            ->where('logistic_id', $logistic->id)
            ->where('delivery_zone', $zone)
            ->where('sorting_status', 'staged')
            ->whereNull('rider_id')
            ->whereNotIn('status', [
                'delivered',
                'cancelled',
                'canceled',
                'delivery_failed',
            ])
            ->whereHas('sellerOrder.order')
            ->where('received_by_sorting_center', true)
            ->where(function ($query) {
                $query
                    ->where(function ($normalQuery) {
                        $normalQuery
                            ->where('status', '!=', 'failed_return_at_sorting_center')
                            ->whereNull('failed_return_received_at');
                    })
                    ->orWhere(function ($returnQuery) {
                        $returnQuery
                            ->where('status', 'failed_return_at_sorting_center')
                            ->whereNotNull('failed_return_received_at')
                            ->whereHas('sellerOrder', function ($sellerQuery) {
                                $sellerQuery->where('reschedule_status', 'approved');
                            });
                    });
            })
            ->with('sellerOrder.order')
            ->orderBy('id')
            ->get();

        if ($shipments->isEmpty()) {
            return back()->with(
                'error',
                'No staged and unassigned shipments found for zone: ' . $zone
            );
        }

        $assignedCount = 0;
        $assignedRiders = [];
        $skippedCount = 0;

        foreach ($shipments as $shipment) {
            $order = $shipment->sellerOrder?->order;


            // Failed-return parcels require seller-specific approval.
            if ($shipment->failed_return_received_at !== null) {
                $sellerOrder = $shipment->sellerOrder;

                if (
                    !$sellerOrder ||
                    $sellerOrder->reschedule_status !== 'approved' ||
                    $shipment->status !== 'failed_return_at_sorting_center'
                ) {
                    $skippedCount++;
                    continue;
                }
            }

            if (!$shipment->hub_id) {
                $skippedCount++;
                continue;
            }


            // Prefer approved riders assigned to this delivery zone.
            // Re-query for every parcel because rider capacity changes.
            $riderQuery = User::query()
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'rider');
                })
                ->where('logistic_id', $logistic->id)
                ->where('logistic_status', 'approved')
                ->where('status', User::STATUS_ACTIVE)
                ->where('availability_status', 'available')
                ->where('max_capacity', '>', 0)
                ->whereColumn('current_load', '<', 'max_capacity');

            $riderQuery->where('hub_id', $shipment->hub_id);

            $rider = (clone $riderQuery)
                ->where('assigned_zone', $zone)
                ->orderBy('current_load')
                ->orderBy('id')
                ->first();

            if (!$rider) {
                $skippedCount++;
                continue;
            }

            try {
                \App\Services\RiderAssignmentService::assignRiderToShipment(
                    $shipment,
                    $rider
                );

                $assignedCount++;

                $assignedRiders[$rider->id] = (
                    $assignedRiders[$rider->id] ?? 0
                ) + 1;
            } catch (\RuntimeException $e) {
                // Another request may have assigned this shipment
                // or filled the rider's remaining capacity.
                report($e);
                $skippedCount++;
            }
        }

        // Notify riders only after successful assignments.
        foreach ($assignedRiders as $riderId => $count) {
            $this->createNotification(
                $riderId,
                'New Delivery Assignment',
                'You have been assigned ' . $count .
                    ' shipment(s) for zone: ' . $zone . '.',
                'shipment'
            );
        }

        if ($assignedCount === 0) {
            return back()->with(
                'error',
                'No shipments were assigned. Check rider availability, approval, hub and capacity.'
            );
        }

        return back()->with(
            'success',
            'Successfully assigned ' . $assignedCount .
                ' shipment(s) for zone ' . $zone .
                '. Skipped: ' . $skippedCount . '.'
        );
    }

    public function autoScanZone(Request $request, Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || (int) $shipment->logistic_id !== (int) $logistic->id) {
            return response()->json(['error' => 'Shipment not found'], 404);
        }

        try {
            $zone = \Illuminate\Support\Facades\DB::transaction(
                function () use ($shipment, $logistic) {
                    $lockedShipment = Shipment::query()
                        ->whereKey($shipment->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ((int) $lockedShipment->logistic_id !== (int) $logistic->id) {
                        throw new \RuntimeException('Shipment ownership changed.');
                    }

                    if (
                        !$lockedShipment->received_by_sorting_center ||
                        $lockedShipment->sorting_status !== 'received'
                    ) {
                        throw new \RuntimeException(
                            'Shipment must be received at the sorting center before scanning.'
                        );
                    }

                    if ($lockedShipment->rider_id !== null) {
                        throw new \RuntimeException(
                            'Shipment still has an assigned rider.'
                        );
                    }

                    $deliveryAddress = strtolower(
                        $lockedShipment->delivery_address ?? ''
                    );

                    $zone = 'Zone B - Luzon';

                    if (
                        str_contains($deliveryAddress, 'manila') ||
                        str_contains($deliveryAddress, 'makati') ||
                        str_contains($deliveryAddress, 'pasig') ||
                        str_contains($deliveryAddress, 'taguig') ||
                        str_contains($deliveryAddress, 'ncr')
                    ) {
                        $zone = 'Zone A - Metro Manila';
                    } elseif (
                        str_contains($deliveryAddress, 'laguna') ||
                        str_contains($deliveryAddress, 'cavite') ||
                        str_contains($deliveryAddress, 'batangas') ||
                        str_contains($deliveryAddress, 'pampanga') ||
                        str_contains($deliveryAddress, 'bulacan') ||
                        str_contains($deliveryAddress, 'rizal')
                    ) {
                        $zone = 'Zone A';
                    } elseif (
                        str_contains($deliveryAddress, 'cebu') ||
                        str_contains($deliveryAddress, 'bohol') ||
                        str_contains($deliveryAddress, 'iloilo') ||
                        str_contains($deliveryAddress, 'bacolod')
                    ) {
                        $zone = 'Zone C - Visayas';
                    } elseif (
                        str_contains($deliveryAddress, 'davao') ||
                        str_contains($deliveryAddress, 'cagayan de oro') ||
                        str_contains($deliveryAddress, 'bukidnon')
                    ) {
                        $zone = 'Zone D - Mindanao';
                    }

                    $lockedShipment->update([
                        'sorting_status' => 'scanned',
                        'scanned_at' => now(),
                        'delivery_zone' => $zone,
                    ]);

                    return $zone;
                }
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'zone' => $zone,
        ]);
    }

    public function showShipment(Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.shipments')->with('error', 'Shipment not found in your company.');
        }

        $riders = $logistic->riders()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('logistic_status', 'approved')
            ->where('status', User::STATUS_ACTIVE)
            ->where(
                'hub_id',
                $shipment->destination_hub_id ?: $shipment->hub_id
            )
            ->where('availability_status', 'available')
            ->where('max_capacity', '>', 0)
            ->whereColumn('current_load', '<', 'max_capacity')
            ->select('id', 'name', 'current_load', 'max_capacity', 'assigned_zone')
            ->get();

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
            return redirect()->route('home')
                ->with('error', 'No logistics company found for your account.');
        }

        $validated = $request->validate([
            'seller_order_id' => ['required', 'exists:seller_orders,id'],
            'courier' => ['nullable', 'string', 'max:255'],
            'pickup_address' => ['required', 'string', 'max:1000'],
            'delivery_address' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = DB::transaction(function () use ($validated, $logistic) {
            $sellerOrder = SellerOrder::query()
                ->whereKey($validated['seller_order_id'])
                ->lockForUpdate()
                ->first();

            if (
                !$sellerOrder ||
                (
                    $sellerOrder->logistic_id !== null &&
                    (int) $sellerOrder->logistic_id !== (int) $logistic->id
                )
            ) {
                return ['status' => 'unavailable'];
            }

            if (
                Shipment::query()
                ->where('seller_order_id', $sellerOrder->id)
                ->exists()
            ) {
                return ['status' => 'duplicate'];
            }

            $trackingNumber = 'SPE-' . strtoupper(uniqid());

            Shipment::create([
                'seller_order_id' => $sellerOrder->id,
                'logistic_id' => $logistic->id,
                'rider_id' => null,
                'pickup_rider_id' => null,
                'tracking_number' => $trackingNumber,
                'status' => 'pending',
                'courier' => $validated['courier'] ?? null,
                'pickup_address' => $validated['pickup_address'],
                'delivery_address' => $validated['delivery_address'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($sellerOrder->logistic_id === null) {
                $sellerOrder->update([
                    'logistic_id' => $logistic->id,
                ]);
            }

            return [
                'status' => 'created',
                'tracking_number' => $trackingNumber,
            ];
        }, 3);

        if ($result['status'] === 'unavailable') {
            return back()->with(
                'error',
                'Seller order is not available for this logistics company.'
            );
        }

        if ($result['status'] === 'duplicate') {
            return back()->with(
                'error',
                'This seller order already has a shipment.'
            );
        }

        return redirect()
            ->route('logistic.shipments')
            ->with(
                'success',
                'Shipment created successfully. Tracking: ' .
                    $result['tracking_number']
            );
    }

    public function updateShipmentStatus(Request $request, Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        abort_unless(
            $logistic &&
                (int) $shipment->logistic_id === (int) $logistic->id,
            403,
            'Shipment does not belong to your logistics company.'
        );

        $request->validate([
            'status' => ['required', 'in:delayed'],
        ]);

        if (in_array($shipment->status, [
            'delivered',
            'cancelled',
            'delivery_failed',
        ], true)) {
            return back()->with(
                'error',
                'This shipment can no longer be marked as delayed.'
            );
        }

        if ($shipment->status === 'delayed') {
            return back()->with(
                'error',
                'This shipment is already marked as delayed.'
            );
        }

        $shipment->update([
            'status' => 'delayed',
        ]);

        $this->createNotification(
            $logistic->owner_user_id,
            'Shipment Delayed',
            'Shipment ' . $shipment->tracking_number .
                ' has been marked as delayed.',
            'alert'
        );

        return back()->with(
            'success',
            'Shipment marked as delayed successfully.'
        );
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

        return view('logistic.shipments-track', compact(
            'shipment',
            'riderLocation',
            'logistic'
        ));
    }

    public function shipmentChat(Shipment $shipment)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $shipment->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.shipments')->with('error', 'Shipment not found in your company.');
        }

        $messages = $shipment->messages()->with('user')->latest()->paginate(50);

        return view('logistic.shipments-chat', compact(
            'shipment',
            'messages',
            'logistic'
        ));
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
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered"),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled"),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) as delayed")
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        $riderPerformance = $logistic->riders()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->withCount(['shipments as delivered_count' => function ($query) {
                $query->where('status', 'delivered');
            }])
            ->withCount('shipments as total_count')
            ->get()
            ->map(function ($rider) {
                $rider->success_rate = $rider->total_count > 0 ? round(($rider->delivered_count / $rider->total_count) * 100, 1) : 0;
                return $rider;
            });

        // COD amounts are stored in centavos (minor units).
        // Keep all totals scoped to the authenticated logistics company.
        $codFinancial = [
            'collected_minor' => DB::table('payments as p')
                ->join('shipments as s', 's.id', '=', 'p.shipment_id')
                ->where('s.logistic_id', $logistic->id)
                ->whereColumn('s.rider_id', 'p.collected_by')
                ->where('s.status', 'delivered')
                ->where('p.method', 'cod')
                ->where('p.status', 'paid')
                ->whereNotNull('p.paid_at')
                ->where('p.amount_minor', '>', 0)
                ->sum('p.amount_minor'),

            'pending_minor' => DB::table('cod_remittances')
                ->where('logistic_id', $logistic->id)
                ->where('status', 'pending')
                ->sum('total_minor'),

            'confirmed_minor' => DB::table('cod_remittances')
                ->where('logistic_id', $logistic->id)
                ->where('status', 'confirmed')
                ->sum('total_minor'),
        ];

        return view('logistic.reports', compact(
            'logistic',
            'totalShipments',
            'deliveredShipments',
            'cancelledShipments',
            'delayedShipments',
            'pendingShipments',
            'successRate',
            'dailyReports',
            'riderPerformance',
            'codFinancial'
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
            // Delete previous logo from S3 or old local public storage.
            if ($logistic->logo) {
                try {
                    if (Storage::disk('s3')->exists($logistic->logo)) {
                        Storage::disk('s3')->delete($logistic->logo);
                    } elseif (Storage::disk('public')->exists($logistic->logo)) {
                        Storage::disk('public')->delete($logistic->logo);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // Store new logistics logo persistently in Supabase Storage.
            $validated['logo'] = $request->file('logo')->store('logistic-logos', 's3');
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
            'barangay' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $geocoded = $this->geocodeAddress($validated['api_address'] ?? $validated['address']);

        $hub = \App\Models\Hub::create([
            'logistic_id' => $logistic->id,
            'name' => $validated['name'],
            'address' => $validated['address'],
            'api_address' => $validated['api_address'] ?? null,
            'barangay' => $validated['barangay'],
            'city' => $validated['city'],
            'province' => $validated['province'],
            'latitude' => $geocoded['lat'] ?? null,
            'longitude' => $geocoded['lng'] ?? null,
            'contact_person' => $validated['contact_person'],
            'phone' => $validated['phone'],
            'status' => $validated['status'],
            'is_default' => $request->boolean('is_default') && $validated['status'] === 'active',
        ]);

        if ($hub->is_default) {
            \App\Models\Hub::query()
                ->where('logistic_id', $logistic->id)
                ->whereKeyNot($hub->id)
                ->update(['is_default' => false]);
        }

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
            'barangay' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $geocoded = $this->geocodeAddress($validated['api_address'] ?? $validated['address']);

        if ($geocoded) {
            $validated['latitude'] = $geocoded['lat'];
            $validated['longitude'] = $geocoded['lng'];
        }

        $validated['is_default'] = $request->boolean('is_default')
            && $validated['status'] === 'active';

        if ($validated['is_default']) {
            \App\Models\Hub::query()
                ->where('logistic_id', $logistic->id)
                ->whereKeyNot($hub->id)
                ->update(['is_default' => false]);
        }

        $hub->update($validated);

        \App\Services\RiderHubAssignmentService::syncHubRiderZones($hub);

        if ($hub->status === 'active') {
            \App\Services\PickupOfferService::retryPendingForHub(
                (int) $logistic->id,
                (int) $hub->id
            );
        }

        return back()->with('success', 'Hub updated successfully.');
    }

    public function destroyHub(\App\Models\Hub $hub)
    {
        $logistic = Auth::user()->ownedLogistic;

        if (!$logistic || $hub->logistic_id !== $logistic->id) {
            return redirect()->route('logistic.hubs')->with('error', 'Hub not found.');
        }

        $hub->riders()->update(['hub_id' => null, 'assigned_zone' => null]);
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

        \App\Services\RiderHubAssignmentService::assign($rider, $hub);

        if ($rider->availability_status === 'available') {
            \App\Services\PickupOfferService::retryPendingForHub(
                (int) $logistic->id,
                (int) $hub->id
            );
        }

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

        \App\Services\RiderHubAssignmentService::unassign($rider);

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



