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

    private function riderShipmentForOrder(Order $order, $rider = null)

    {

        $rider = $rider ?: Auth::user();

        if (!$rider) {

            return null;
        }

        return \App\Models\Shipment::query()

            ->whereHas('sellerOrder', function ($query) use ($order) {

                $query->where('order_id', $order->id);
            })

            ->where('rider_id', $rider->id)

            ->orderBy('id')

            ->first();
    }

    public function index()

    {

        $rider = Auth::user();

        $assignedOrders = \App\Models\Shipment::query()

            ->where('rider_id', $rider->id)

            ->whereNotIn('status', [

                'delivered',

                'delivery_failed',

                'cancelled',

                'failed_return_at_sorting_center',

            ])

            ->whereHas('sellerOrder.order')

            ->with([

                'sellerOrder.order.user',

                'sellerOrder.seller',

            ])

            ->latest()

            ->paginate(20);

        $completedToday = \App\Models\Shipment::query()

            ->where('rider_id', $rider->id)

            ->where('status', 'delivered')

            ->whereDate('delivered_at', today())

            ->count();

        $totalDelivered = \App\Models\Shipment::query()

            ->where('rider_id', $rider->id)

            ->where('status', 'delivered')

            ->count();

        $logistic = $rider->logistic;

        return view('rider.dashboard', compact(

            'assignedOrders',

            'completedToday',

            'totalDelivered',

            'logistic',

            'rider'

        ));
    }

    public function orders(Request $request)

    {

        $rider = Auth::user();

        $query = Order::query()

            ->whereHas('sellerOrders.shipment', function ($shipmentQuery) use ($rider) {

                $shipmentQuery->where('rider_id', $rider->id);
            })

            ->with([

                'user',

                'items.product',

                'sellerOrders' => function ($sellerOrderQuery) use ($rider) {

                    $sellerOrderQuery->whereHas('shipment', function ($shipmentQuery) use ($rider) {

                        $shipmentQuery->where('rider_id', $rider->id);
                    })->with('shipment');
                },

            ]);

        if ($request->filled('status')) {

            $query->whereHas('sellerOrders.shipment', function ($shipmentQuery) use ($request, $rider) {

                $shipmentQuery

                    ->where('rider_id', $rider->id)

                    ->where('status', $request->status);
            });
        }

        $orders = $query->latest()->paginate(20);

        return view('rider.orders', compact('orders'));
    }

    public function show(Order $order)

    {

        $shipment = $this->riderShipmentForOrder(

            $order,

            Auth::user()

        );

        abort_unless(

            $shipment,

            403,

            'This shipment is not assigned to you.'

        );

        return redirect()->route(

            'rider.shipments.show',

            $shipment

        );
    }

    /**

     * Legacy order-level status changes are disabled.

     * All rider delivery updates must use the assigned shipment endpoint.

     */

    public function updateStatus(Request $request, Order $order)

    {

        abort_unless(

            $this->riderShipmentForOrder($order, $request->user()),

            403,

            'This shipment is not assigned to you.'

        );

        throw \Illuminate\Validation\ValidationException::withMessages([

            'delivery_status' =>

            'Order-level delivery updates are disabled. Open the assigned shipment to update delivery status.',

        ]);
    }

    public function collectShipmentPayment(

        Request $request,

        \App\Models\Shipment $shipment

    ) {

        $validated = $request->validate([

            'amount_collected' => [

                'required',

                'numeric',

                'gt:0',

                'decimal:0,2',

            ],

        ]);

        // Convert pesos to centavos without floating-point rounding.

        $amount = (string) $validated['amount_collected'];

        [$pesos, $centavos] = array_pad(

            explode('.', $amount, 2),

            2,

            '0'

        );

        $amountMinor = ((int) $pesos * 100)

            + (int) str_pad($centavos, 2, '0');

        return \Illuminate\Support\Facades\DB::transaction(

            function () use ($shipment, $amountMinor) {

                // Lock the shipment to prevent concurrent collection.

                $shipment = \App\Models\Shipment::query()

                    ->whereKey($shipment->id)

                    ->lockForUpdate()

                    ->firstOrFail();

                abort_unless(

                    (int) $shipment->rider_id === (int) Auth::id(),

                    403

                );

                $sellerOrder = \App\Models\SellerOrder::query()

                    ->whereKey($shipment->seller_order_id)

                    ->firstOrFail();

                $order = Order::query()

                    ->whereKey($sellerOrder->order_id)

                    ->lockForUpdate()

                    ->firstOrFail();

                if ($order->payment_method !== 'cod') {

                    return back()->with(

                        'error',

                        'This order is not Cash on Delivery.'

                    );
                }

                // Protect previously collected legacy COD orders.

                if (

                    $order->payment_status === 'paid'

                    && (int) $order->amount_collected_minor

                    === (int) $order->total_minor

                ) {

                    return back()->with(

                        'error',

                        'This order has already been fully paid.'

                    );
                }

                if (!in_array(

                    $order->payment_status,

                    ['unpaid', 'partially_paid'],

                    true

                )) {

                    return back()->with(

                        'error',

                        'This order is not eligible for COD collection.'

                    );
                }

                if (!in_array(

                    $shipment->status,

                    ['out_for_delivery', 'delivered'],

                    true

                )) {

                    return back()->with(

                        'error',

                        'COD can only be collected during or after shipment delivery.'

                    );
                }

                // Prevent duplicate shipment payment.

                $existingPayment = \App\Models\Payment::query()

                    ->where('shipment_id', $shipment->id)

                    ->first();

                if ($existingPayment) {

                    return back()->with(

                        'error',

                        'COD has already been recorded for this shipment.'

                    );
                }

                // Exact amount assigned to this seller's shipment.

                $expectedMinor = (int) $sellerOrder->total_minor;

                if (

                    $expectedMinor <= 0

                    || $amountMinor !== $expectedMinor

                ) {

                    return back()->with(

                        'error',

                        'Collected amount must match the exact shipment total.'

                    );
                }

                // Prevent collection exceeding the parent order total.

                $alreadyCollectedMinor = (int) (

                    $order->amount_collected_minor ?? 0

                );

                $newCollectedMinor = $alreadyCollectedMinor + $amountMinor;

                if ($newCollectedMinor > (int) $order->total_minor) {

                    return back()->with(

                        'error',

                        'Collected amount exceeds the order total.'

                    );
                }

                $collectedAt = now();

                // Record the actual rider who collected this shipment's COD.

                \App\Models\Payment::create([

                    'order_id' => $order->id,

                    'shipment_id' => $shipment->id,

                    'collected_by' => Auth::id(),

                    'method' => 'cod',

                    'status' => 'paid',

                    'amount_minor' => $amountMinor,

                    'currency' => 'PHP',

                    'paid_at' => $collectedAt,

                ]);

                $fullyPaid = $newCollectedMinor

                    === (int) $order->total_minor;

                // Parent order tracks aggregate payment.

                // Individual rider attribution stays in payments.collected_by.

                $order->update([

                    'amount_collected_minor' => $newCollectedMinor,

                    'payment_status' => $fullyPaid

                        ? 'paid'

                        : 'unpaid',

                    'collected_at' => $fullyPaid

                        ? $collectedAt

                        : null,

                    'collected_by' => null,

                ]);

                return back()->with(

                    'success',

                    'Shipment COD payment collected successfully.'

                );
            }

        );
    }

    /**

     * Legacy order-level COD collection is disabled.

     * Historical collections remain readable in reportCash().

     */

    public function collectPayment(Request $request, Order $order)

    {

        abort_unless(

            $this->riderShipmentForOrder($order, $request->user()),

            403,

            'This shipment is not assigned to you.'

        );

        throw \Illuminate\Validation\ValidationException::withMessages([

            'amount_collected' =>

            'Order-level COD collection is disabled. Collect payment from the assigned shipment.',

        ]);
    }

    public function reportCash()

    {

        $rider = Auth::user();

        // Historical/legacy COD collected directly on the parent order.

        // Exclude orders with shipment payments to avoid double counting.

        $legacyQuery = Order::query()

            ->where('collected_by', $rider->id)

            ->where('payment_method', 'cod')

            ->where('payment_status', 'paid')

            ->whereNotNull('collected_at')

            ->whereDoesntHave('payments', function ($query) {

                $query->whereNotNull('shipment_id');
            });

        // New COD payments collected for individual shipments.

        $shipmentQuery = \App\Models\Payment::query()

            ->where('collected_by', $rider->id)

            ->whereNotNull('shipment_id')

            ->where('method', 'cod')

            ->where('status', 'paid')

            ->whereNotNull('paid_at');

        $legacyToday = (clone $legacyQuery)

            ->whereDate('collected_at', today())

            ->selectRaw(

                'COALESCE(SUM(amount_collected_minor), 0) as total_minor, COUNT(*) as total_count'

            )

            ->first();

        $shipmentToday = (clone $shipmentQuery)

            ->whereDate('paid_at', today())

            ->selectRaw(

                'COALESCE(SUM(amount_minor), 0) as total_minor, COUNT(*) as total_count'

            )

            ->first();

        $totalMinor = (int) $legacyToday->total_minor

            + (int) $shipmentToday->total_minor;

        $totalCount = (int) $legacyToday->total_count

            + (int) $shipmentToday->total_count;

        $cashReport = (object) [

            'total_collected_minor' => $totalMinor,

            'total_collected' => $totalMinor / 100,

            'total_orders' => $totalCount,

        ];

        $legacyCollections = (clone $legacyQuery)

            ->with('user')

            ->latest('collected_at')

            ->take(20)

            ->get()

            ->map(function ($order) {

                return (object) [

                    'order_number' => $order->order_number,

                    'customer_name' => $order->user?->name ?? 'N/A',

                    'amount_minor' => (int) $order->amount_collected_minor,

                    'collected_at' => $order->collected_at,

                    'shipment_number' => null,

                ];
            });

        $shipmentCollections = (clone $shipmentQuery)

            ->with(['order.user', 'shipment'])

            ->latest('paid_at')

            ->take(20)

            ->get()

            ->map(function ($payment) {

                return (object) [

                    'order_number' => $payment->order?->order_number ?? 'N/A',

                    'customer_name' => $payment->order?->user?->name ?? 'N/A',

                    'amount_minor' => (int) $payment->amount_minor,

                    'collected_at' => $payment->paid_at,

                    'shipment_number' => $payment->shipment?->tracking_number,

                ];
            });

        $recentCollections = $legacyCollections

            ->concat($shipmentCollections)

            ->sortByDesc('collected_at')

            ->take(20)

            ->values();

        return view(

            'rider.cash_report',

            compact('cashReport', 'recentCollections')

        );
    }

    /**

     * Legacy order-level proof upload is disabled.

     * Proof of delivery must be submitted through the assigned shipment.

     */

    public function uploadProof(Request $request, Order $order)

    {

        abort_unless(

            $this->riderShipmentForOrder($order, $request->user()),

            403,

            'This shipment is not assigned to you.'

        );

        throw \Illuminate\Validation\ValidationException::withMessages([

            'proof_of_delivery' =>

            'Order-level proof uploads are disabled. Submit proof through the assigned shipment.',

        ]);
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

        $deliveries = \App\Models\Shipment::query()

            ->where('rider_id', $rider->id)

            ->whereIn('status', [

                'assigned',

                'picked_up',

                'in_transit',

                'out_for_delivery',

            ])

            ->whereHas('sellerOrder.order')

            ->with([

                'sellerOrder.order.user',

                'sellerOrder.seller',

            ])

            ->latest()

            ->paginate(20);

        return view('rider.deliveries', compact('deliveries'));
    }

    public function accept(Order $order)

    {

        abort(

            403,

            'Direct order acceptance is disabled. Rider assignments must be handled by logistics.'

        );
    }

    public function pickups(Request $request)

    {

        // Fallback processing for environments where the Laravel scheduler
        // is not continuously running. Expired offers are re-offered before
        // this rider's pickup page is rendered.
        \App\Services\PickupOfferService::processExpiredOffers();

        $rider = Auth::user();

        // Keep legacy rider records aligned with their assigned hub barangay.
        \App\Services\RiderHubAssignmentService::syncRiderToCurrentHub($rider);

        // Also retry shipments that never received an offer because no eligible
        // rider existed when the seller first marked the order ready for pickup.
        if (
            $rider->availability_status === 'available' &&
            $rider->logistic_id !== null &&
            $rider->hub_id !== null
        ) {
            \App\Services\PickupOfferService::retryPendingForHub(
                (int) $rider->logistic_id,
                (int) $rider->hub_id
            );
        }

        $query = \App\Models\Shipment::query()

            ->whereHas('sellerOrder.order')

            ->with([

                'sellerOrder.order.user',

                'sellerOrder.seller',

            ]);

        if ($request->query('status') === 'sorting_center') {

            // Final-mile parcels assigned to this rider.

            $query->where('rider_id', $rider->id)

                ->where('sorting_status', 'staged')

                ->where('received_by_sorting_center', true)

                ->whereIn('status', [

                    'assigned',

                    'staged',

                    'at_sorting_center',

                ]);
        } else {

            // Seller pickups assigned to this rider.

            $query->where('pickup_rider_id', $rider->id)

                ->where('received_by_sorting_center', false)

                ->whereIn('status', [

                    'assigned',

                    'in_transit',

                ]);

            if ($request->filled('status')) {

                $allowedStatuses = ['assigned', 'in_transit'];

                if (in_array($request->status, $allowedStatuses, true)) {

                    $query->where('status', $request->status);
                }
            }
        }

        $pickups = $query->latest()->paginate(20);

        // Pickup offers awaiting this rider's response.

        $pickupOffers = \App\Models\PickupRequest::query()

            ->with([

                'shipment.sellerOrder.order',

                'shipment.sellerOrder.seller',

            ])

            ->where('rider_id', $rider->id)

            ->where('status', 'offered')

            ->where('expires_at', '>', now())

            ->whereHas('shipment', function ($shipmentQuery) {

                $shipmentQuery

                    ->where('status', 'pending')

                    ->whereNull('pickup_rider_id')

                    ->whereNull('rider_id')

                    ->where('received_by_sorting_center', false);
            })

            ->orderBy('expires_at')

            ->get();

        return view('rider.pickups', compact('pickups', 'pickupOffers'));
    }

    public function confirmPickup(

        Request $request,

        \App\Models\Shipment $shipment

    ) {

        abort_unless(

            (int) $shipment->pickup_rider_id === (int) Auth::id(),

            403,

            'This parcel is not assigned to you for seller pickup.'

        );

        return redirect()

            ->route('rider.pickups')

            ->with(

                'error',

                'Manual pickup confirmation is disabled. Please scan the parcel QR code to confirm pickup.'

            );
    }

    public function deliverToSortingCenter(

        Request $request,

        \App\Models\Shipment $shipment

    ) {

        $data = $request->validate([

            'notes' => ['nullable', 'string', 'max:1000'],

        ]);

        $result = \Illuminate\Support\Facades\DB::transaction(

            function () use ($shipment, $data) {

                $shipment = \App\Models\Shipment::query()

                    ->whereKey($shipment->id)

                    ->lockForUpdate()

                    ->firstOrFail();

                abort_unless(

                    (int) $shipment->pickup_rider_id === (int) Auth::id(),

                    403,

                    'This parcel is not assigned to you for seller pickup.'

                );

                if ($shipment->received_by_sorting_center) {

                    return [

                        'success' => false,

                        'message' => 'This parcel has already been received by the sorting center.',

                    ];
                }

                if ($shipment->status !== 'in_transit') {

                    return [

                        'success' => false,

                        'message' => 'Only parcels in transit from the seller can be handed over.',

                    ];
                }

                $sellerOrder = $shipment->sellerOrder;

                $order = $sellerOrder?->order;

                if (!$order) {

                    return [

                        'success' => false,

                        'message' => 'This shipment has no valid order.',

                    ];
                }

                if (!$shipment->hub_id) {

                    $hub = \App\Services\HubAssignmentService::findBestHubForSellerOrder(

                        $sellerOrder

                    );

                    if (!$hub) {

                        return [

                            'success' => false,

                            'message' => 'No sorting center is available for this parcel.',

                        ];
                    }

                    $shipment->hub_id = $hub->id;

                    $shipment->logistic_id = $hub->logistic_id;
                }

                $newNotes = trim((string) ($data['notes'] ?? ''));

                $existingNotes = trim((string) ($shipment->notes ?? ''));

                $shipment->update([

                    'status' => 'at_sorting_center',

                    'at_sorting_center_at' => now(),

                    'notes' => implode("\n", array_filter([

                        $newNotes,

                        $existingNotes,

                    ])),

                    'received_by_sorting_center' => false,

                    'hub_id' => $shipment->hub_id,

                    'logistic_id' => $shipment->logistic_id,

                ]);

                $logistic = \App\Models\Logistic::find(

                    $shipment->logistic_id

                );

                return [

                    'success' => true,

                    'tracking_number' => $shipment->tracking_number,

                    'order_number' => $order->order_number,

                    'hub_name' => $shipment->hub?->name ?? 'Sorting Center',

                    'logistic_owner_id' => $logistic?->owner_user_id,

                ];
            }

        );

        if (!$result['success']) {

            return back()->with('error', $result['message']);
        }

        if ($result['logistic_owner_id']) {

            $this->createNotification(

                $result['logistic_owner_id'],

                'Parcel Awaiting Sorting Center Confirmation',

                'Rider ' . Auth::user()->name

                    . ' delivered shipment '

                    . $result['tracking_number']

                    . ' from order '

                    . $result['order_number']

                    . ' to '

                    . $result['hub_name']

                    . '. Awaiting physical receipt confirmation.',

                'shipment',

                route('logistic.sorting-area')

            );
        }

        return back()->with(

            'success',

            'Parcel delivered to sorting center. Awaiting sorting center confirmation.'

        );
    }

    public function returnFailedToSortingCenter(

        Request $request,

        \App\Models\Shipment $shipment

    ) {

        $data = $request->validate([

            'notes' => 'nullable|string|max:1000',

        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($shipment, $data) {

            $shipment = \App\Models\Shipment::query()

                ->whereKey($shipment->id)

                ->lockForUpdate()

                ->firstOrFail();

            // Verify the rider assigned to this exact parcel.

            abort_unless(

                (int) $shipment->rider_id === (int) Auth::id(),

                403

            );

            // Only failed parcels can be returned.

            if ($shipment->status !== 'delivery_failed') {

                throw \Illuminate\Validation\ValidationException::withMessages([

                    'shipment' => 'Only failed deliveries can be returned to the sorting center.',

                ]);
            }

            $sellerOrder = $shipment->sellerOrder;

            if (!$sellerOrder) {

                throw new \RuntimeException('Seller order not found for this shipment.');
            }

            $order = $sellerOrder->order;

            if (!$order) {

                throw new \RuntimeException('Order not found for this shipment.');
            }

            // Assign a sorting center when missing.

            if (!$shipment->hub_id) {

                $hub = \App\Services\HubAssignmentService::findBestHubForSellerOrder(

                    $sellerOrder

                );

                if ($hub) {

                    $shipment->hub_id = $hub->id;

                    $shipment->logistic_id = $hub->logistic_id;
                }
            }

            $shipment->status = 'failed_return_at_sorting_center';

            $shipment->sorting_status = 'pending';

            $shipment->failed_returned_to_sorting_center_at = now();

            $shipment->failed_return_received_at = null;

            $shipment->received_by_sorting_center = false;

            $shipment->notes = trim(

                ($shipment->notes ? $shipment->notes . "\n" : '') .

                    ($data['notes'] ?? 'Failed delivery returned by rider to sorting center.')

            );

            $shipment->save();

            // Preserve the failed state for this seller parcel.

            $sellerOrder->update([

                'status' => 'delivery_failed',

            ]);

            // The failed return belongs to this seller shipment only.

            // Do not overwrite the shared parent Order status.

            // Parent Order status must be calculated from all seller orders.

            $logisticOwner = $shipment->logistic?->owner;

            if ($logisticOwner) {

                $hubName = $shipment->hub?->name ?? 'Sorting Center';

                $this->createNotification(

                    $logisticOwner->id,

                    'Failed Parcel Awaiting Receipt',

                    'Rider ' . Auth::user()->name .

                        ' returned failed parcel ' . $shipment->tracking_number .

                        ' to ' . $hubName .

                        '. Please confirm physical receipt.',

                    'shipment',

                    route('logistic.sorting-area')

                );
            }
        });

        return back()->with(

            'success',

            'Failed parcel returned to sorting center. Awaiting logistics confirmation.'

        );
    }

    public function pickupFromSortingCenter(\App\Models\Shipment $shipment)
    {
        abort(403, 'Manual hub pickup is disabled. Scan the parcel QR code to confirm pickup.');
    }

    public function history(Request $request)

    {

        $validated = $request->validate([

            'status' => 'nullable|in:delivered,delivery_failed',

            'from_date' => 'nullable|date',

            'to_date' => 'nullable|date|after_or_equal:from_date',

        ]);

        $rider = Auth::user();

        $query = \App\Models\Shipment::query()

            ->where('rider_id', $rider->id)

            ->whereIn('status', [

                'delivered',

                'delivery_failed',

            ])

            ->whereHas('sellerOrder.order')

            ->with([

                'sellerOrder.order.user',

                'sellerOrder.seller',

            ]);

        if (!empty($validated['status'])) {

            $query->where('status', $validated['status']);
        }

        if (!empty($validated['from_date'])) {

            $query->whereDate('updated_at', '>=', $validated['from_date']);
        }

        if (!empty($validated['to_date'])) {

            $query->whereDate('updated_at', '<=', $validated['to_date']);
        }

        $history = $query

            ->latest('updated_at')

            ->paginate(20)

            ->withQueryString();

        return view('rider.history', compact('history'));
    }

    public function profit(Request $request)

    {

        $validated = $request->validate([

            'from_date' => ['nullable', 'date'],

            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],

        ]);

        $rider = Auth::user();

        // Count delivered parcels, not parent buyer orders.

        $shipmentQuery = \App\Models\Shipment::query()

            ->where('rider_id', $rider->id)

            ->where('status', 'delivered');

        if (!empty($validated['from_date'])) {

            $shipmentQuery->whereDate(

                'delivered_at',

                '>=',

                $validated['from_date']

            );
        }

        if (!empty($validated['to_date'])) {

            $shipmentQuery->whereDate(

                'delivered_at',

                '<=',

                $validated['to_date']

            );
        }

        $totalDelivered = $shipmentQuery->count();

        // Only confirmed shipment-specific COD collections

        // collected by this rider.

        $paymentQuery = \App\Models\Payment::query()

            ->where('collected_by', $rider->id)

            ->where('method', 'cod')

            ->where('status', 'paid')

            ->whereNotNull('shipment_id')

            ->whereHas('shipment', function ($query) use ($rider) {

                $query->where('rider_id', $rider->id);
            })

            ->with([

                'shipment.sellerOrder.order.user',

            ]);

        if (!empty($validated['from_date'])) {

            $paymentQuery->whereDate(

                'paid_at',

                '>=',

                $validated['from_date']

            );
        }

        if (!empty($validated['to_date'])) {

            $paymentQuery->whereDate(

                'paid_at',

                '<=',

                $validated['to_date']

            );
        }

        $collections = $paymentQuery

            ->orderByDesc('paid_at')

            ->get();

        $totalCollectedMinor = $collections->sum(

            fn($payment) => (int) $payment->amount_minor

        );

        $totalCollected = $totalCollectedMinor / 100;

        $collectionCount = $collections->count();

        $averageCollection = $collectionCount > 0

            ? $totalCollected / $collectionCount

            : 0;

        // Daily collected COD amount, in pesos.

        $chartData = $collections

            ->filter(fn($payment) => $payment->paid_at !== null)

            ->groupBy(fn($payment) => $payment->paid_at->format('Y-m-d'))

            ->sortKeys()

            ->map(fn($group) => $group->sum(

                fn($payment) => (int) $payment->amount_minor

            ) / 100);

        return view('rider.profit', compact(

            'collections',

            'totalDelivered',

            'totalCollected',

            'collectionCount',

            'averageCollection',

            'chartData'

        ));
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

            'vehicle_type' => ['required', 'string', 'in:motorcycle,car,van,truck'],

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
        abort_unless(
            (int) $notification->user_id === (int) Auth::id(),
            403
        );

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return back();
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

    public function showShipment(\App\Models\Shipment $shipment)

    {

        abort_unless(

            (int) $shipment->rider_id === (int) Auth::id(),

            403

        );

        $shipment->load([

            'sellerOrder.order.user',

            'sellerOrder.seller',

            'sellerOrder.items.product',

            'logistic',

        ]);

        abort_unless($shipment->sellerOrder?->order, 404);

        return view('rider.shipments.show', compact('shipment'));
    }

    public function updateShipmentStatus(

        Request $request,

        \App\Models\Shipment $shipment

    ) {

        abort_unless(

            (int) $shipment->rider_id === (int) Auth::id(),

            403

        );

        $data = $request->validate([

            'delivery_status' => [

                'required',

                'in:out_for_delivery,delivered,delivery_failed',

            ],

            'delivery_notes' => 'nullable|string|max:1000',

            'failure_reason' => [

                'nullable',

                'required_if:delivery_status,delivery_failed',

                'string',

                'max:1000',

            ],

            'proof_of_delivery' => [

                'nullable',

                'required_if:delivery_status,delivered',

                'image',

                'mimes:jpg,jpeg,png',

                'max:5120',

            ],

            'delivery_signature' => 'nullable|string|max:1000',

            'delivered_to' => 'nullable|string|max:255',

        ]);

        $proofPath = null;

        try {

            \Illuminate\Support\Facades\DB::transaction(function () use (

                $request,

                $shipment,

                $data,

                &$proofPath

            ) {

                $shipment = \App\Models\Shipment::query()

                    ->whereKey($shipment->id)

                    ->lockForUpdate()

                    ->firstOrFail();

                abort_unless(

                    (int) $shipment->rider_id === (int) Auth::id(),

                    403

                );

                if (in_array($shipment->status, [

                    'delivered',

                    'delivery_failed',

                    'cancelled',

                ], true)) {

                    throw \Illuminate\Validation\ValidationException::withMessages([

                        'delivery_status' => 'This shipment is already finalized.',

                    ]);
                }

                if (!in_array($shipment->status, [

                    'picked_up',

                    'in_transit',

                    'out_for_delivery',

                ], true)) {

                    throw \Illuminate\Validation\ValidationException::withMessages([

                        'delivery_status' => 'Shipment is not ready for delivery.',

                    ]);
                }

                if (

                    !$shipment->received_by_sorting_center ||

                    $shipment->sorting_status !== 'staged' ||

                    $shipment->staged_at === null

                ) {

                    throw \Illuminate\Validation\ValidationException::withMessages([

                        'delivery_status' => 'Shipment must complete sorting center processing before delivery.',

                    ]);
                }

                $newStatus = $data['delivery_status'];

                if (

                    in_array($newStatus, ['delivered', 'delivery_failed'], true)

                    && $shipment->status !== 'out_for_delivery'

                ) {

                    throw \Illuminate\Validation\ValidationException::withMessages([

                        'delivery_status' => 'Shipment must be out for delivery first.',

                    ]);
                }

                $sellerOrder = \App\Models\SellerOrder::query()

                    ->whereKey($shipment->seller_order_id)

                    ->lockForUpdate()

                    ->firstOrFail();

                $order = \App\Models\Order::query()

                    ->whereKey($sellerOrder->order_id)

                    ->lockForUpdate()

                    ->firstOrFail();

                $tracking = $shipment->tracking_number

                    ?: ('Shipment #' . $shipment->id);

                if ($newStatus === 'out_for_delivery') {

                    if (!in_array($shipment->status, [

                        'picked_up',

                        'in_transit',

                        'out_for_delivery',

                    ], true)) {

                        throw \Illuminate\Validation\ValidationException::withMessages([

                            'delivery_status' => 'Shipment must be picked up first.',

                        ]);
                    }

                    if ($shipment->status === 'out_for_delivery') {

                        throw \Illuminate\Validation\ValidationException::withMessages([

                            'delivery_status' => 'Shipment is already out for delivery.',

                        ]);
                    }

                    $shipment->update([

                        'status' => 'out_for_delivery',

                        'notes' => $data['delivery_notes'] ?? null,

                    ]);

                    app(\App\Services\OrderFlowSyncService::class)
                        ->syncShipment($shipment);

                    $this->createNotification(

                        $order->user_id,

                        'Parcel Out for Delivery',

                        "Your parcel {$tracking} is on its way.",

                        'delivery',

                        route('orders.show', $order)

                    );

                    return;
                }

                if ($newStatus === 'delivered') {

                    if ($order->payment_method === 'cod') {

                        $expectedMinor = (int) $sellerOrder->total_minor;

                        $shipmentPayment = \App\Models\Payment::query()

                            ->where('shipment_id', $shipment->id)

                            ->where('order_id', $order->id)

                            ->where('method', 'cod')

                            ->where('status', 'paid')

                            ->first();

                        $shipmentPaid = $shipmentPayment

                            && $expectedMinor > 0

                            && (int) $shipmentPayment->amount_minor === $expectedMinor;

                        // Compatibility for historical fully paid orders without payment rows.

                        $legacyFullyPaid = !$shipmentPayment

                            && $order->payment_status === 'paid'

                            && (int) $order->amount_collected_minor === (int) $order->total_minor

                            && $order->collected_at !== null

                            && $order->collected_by !== null

                            && !\App\Models\Payment::query()

                                ->where('order_id', $order->id)

                                ->whereNotNull('shipment_id')

                                ->exists();

                        if (!$shipmentPaid && !$legacyFullyPaid) {

                            throw \Illuminate\Validation\ValidationException::withMessages([

                                'delivery_status' =>

                                'COD for this shipment must be collected before marking it as Delivered.',

                            ]);
                        }
                    }

                    $proofPath = $request->file('proof_of_delivery')
                        ->store('proof-of-delivery', 'supabase_private');

                    if (!$proofPath) {
                        throw new \RuntimeException(
                            'Proof of delivery upload failed.'
                        );
                    }
                    $shipment->update([

                        'status' => 'delivered',

                        'sorting_status' => 'staged',

                        'delivered_at' => now(),

                        'proof_of_delivery' => $proofPath,

                        'delivery_signature' => $data['delivery_signature'] ?? null,

                        'delivered_to' => $data['delivered_to'] ?? null,

                        'notes' => $data['delivery_notes'] ?? null,

                    ]);

                    app(\App\Services\OrderFlowSyncService::class)
                        ->syncShipment($shipment);

                    $sellerOrder->update([

                        'status' => 'delivered',

                    ]);
                } else {

                    $shipment->update([

                        'status' => 'delivery_failed',

                        'notes' => $data['failure_reason'],

                    ]);

                    app(\App\Services\OrderFlowSyncService::class)
                        ->syncShipment($shipment);

                    $sellerOrder->update([

                        'status' => 'delivery_failed',

                    ]);
                }

                $rider = \App\Models\User::query()

                    ->whereKey(Auth::id())

                    ->lockForUpdate()

                    ->firstOrFail();

                // A delivered parcel no longer occupies rider capacity.

                // A failed parcel remains with the rider until logistics

                // confirms its physical return to the sorting center.

                if ($newStatus === 'delivered') {

                    if ((int) $rider->current_load > 0) {

                        $rider->decrement('current_load');
                    }

                    $rider->refresh();

                    if (

                        $rider->max_capacity !== null

                        && (int) $rider->current_load < (int) $rider->max_capacity

                    ) {

                        $rider->update([

                            'availability_status' => 'available',

                        ]);
                    }
                }

                if ($newStatus === 'delivered') {

                    $rider->increment('daily_deliveries_completed');
                }

                $hasUndelivered = $order->sellerOrders()

                    ->where('status', '!=', 'delivered')

                    ->exists();

                $hasDelivered = $order->sellerOrders()

                    ->where('status', 'delivered')

                    ->exists();

                if (!$hasUndelivered) {

                    $order->update([

                        'status' => 'delivered',

                        'delivery_status' => 'delivered',

                        'delivered_at' => now(),

                    ]);
                } elseif ($hasDelivered) {

                    $order->update([

                        'delivery_status' => 'partially_delivered',

                    ]);
                }

                if ($newStatus === 'delivered') {

                    app(\App\Services\CommissionService::class)

                        ->generateForOrder($order);

                    $this->createNotification(

                        $order->user_id,

                        'Parcel Delivered',

                        "Your parcel {$tracking} has been delivered.",

                        'delivery',

                        route('orders.show', $order)

                    );

                    $sellerUserId = $sellerOrder->seller?->user_id;

                    if ($sellerUserId) {

                        $this->createNotification(

                            $sellerUserId,

                            'Parcel Delivered to Customer',

                            "Parcel {$tracking} has been delivered successfully.",

                            'shipment'

                        );
                    }
                } else {

                    $this->createNotification(

                        $order->user_id,

                        'Parcel Delivery Failed',

                        "Delivery failed for parcel {$tracking}. Reason: "

                            . $data['failure_reason'],

                        'delivery',

                        route('orders.show', $order)

                    );
                }

                $logisticOwner = $shipment->logistic?->owner;

                if ($logisticOwner) {

                    $title = $newStatus === 'delivered'

                        ? 'Parcel Delivery Completed'

                        : 'Parcel Delivery Failed';

                    $this->createNotification(

                        $logisticOwner->id,

                        $title,

                        "Rider " . $rider->name

                            . " updated parcel {$tracking} to {$newStatus}.",

                        'shipment'

                    );
                }
            });
        } catch (\Throwable $e) {
            if ($proofPath !== null) {
                try {
                    \Illuminate\Support\Facades\Storage::disk('supabase_private')
                        ->delete($proofPath);
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $e;
        }

        return redirect()

            ->route('rider.shipments.show', $shipment)

            ->with('success', 'Shipment status updated successfully.');
    }

    public function updateAvailability(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'availability_status' => ['required', 'in:available,offline'],
        ]);

        $rider = \App\Models\User::query()
            ->whereKey(\Illuminate\Support\Facades\Auth::id())
            ->firstOrFail();

        abort_unless(
            $rider->roles()->where('name', 'rider')->exists(),
            403,
            'Only riders can update availability.'
        );

        abort_unless(
            $rider->status === 'active'
                && $rider->logistic_status === 'approved'
                && $rider->logistic_id !== null
                && $rider->hub_id !== null,
            422,
            'Your rider account must be approved and assigned to a hub.'
        );

        if (
            $validated['availability_status'] === 'available'
            && $rider->max_capacity !== null
            && (int) $rider->current_load >= (int) $rider->max_capacity
        ) {
            return back()->with('error', 'Your rider capacity is already full.');
        }

        $rider->update([
            'availability_status' => $validated['availability_status'],
        ]);

        if ($validated['availability_status'] === 'available') {
            \App\Services\RiderHubAssignmentService::syncRiderToCurrentHub($rider);

            \App\Services\PickupOfferService::retryPendingForHub(
                (int) $rider->logistic_id,
                (int) $rider->hub_id
            );
        }

        return back()->with(
            'success',
            $validated['availability_status'] === 'available'
                ? 'You are now online and available for assignments.'
                : 'You are now offline and unavailable for new assignments.'
        );
    }
}
