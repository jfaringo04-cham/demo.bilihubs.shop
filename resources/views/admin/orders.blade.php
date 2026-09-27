@extends('admin.layout')

@section('content')

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <div>
        <h1 class="fw-bold text-slate-900 mb-1">Order Monitoring</h1>
        <p class="text-muted mb-0">
            Monitor buyer orders, payments, sellers, and delivery progress across BiliHub.
        </p>
    </div>

    <div class="text-muted small">
        <i class="bi bi-receipt me-1"></i>
        Showing <strong>{{ $orders->count() }}</strong>
        of <strong>{{ $orders->total() }}</strong> orders
    </div>
</div>

{{-- FILTERS --}}
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-4">
        <form method="GET" action="{{ route('admin.orders') }}">
            <div class="row g-3">

                <div class="col-lg-4 col-md-6">
                    <label for="search" class="form-label fw-semibold">Search Order</label>

                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="text"
                            name="search"
                            id="search"
                            class="form-control"
                            placeholder="Order #, buyer name or email"
                            value="{{ request('search') }}"
                        >
                    </div>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label for="status" class="form-label fw-semibold">Order Status</label>

                    <select name="status" id="status" class="form-select">
                        <option value="">All Statuses</option>

                        @foreach([
                            'placed' => 'Placed',
                            'confirmed' => 'Confirmed',
                            'preparing' => 'Preparing',
                            'ready_for_pickup' => 'Ready for Pickup',
                            'picked_up' => 'Picked Up',
                            'at_sorting_center' => 'At Sorting Center',
                            'sorted' => 'Sorted',
                            'assigned_to_rider' => 'Assigned to Rider',
                            'out_for_delivery' => 'Out for Delivery',
                            'delivered' => 'Delivered',
                            'completed' => 'Completed',
                            'delivery_failed' => 'Delivery Failed',
                            'returned' => 'Returned',
                            'cancelled' => 'Cancelled',
                            'reschedule_requested' => 'Reschedule Requested',
                            'rescheduled' => 'Rescheduled',
                            'return_requested' => 'Return Requested',
                        ] as $value => $label)
                            <option
                                value="{{ $value }}"
                                {{ request('status') === $value ? 'selected' : '' }}
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label for="payment_method" class="form-label fw-semibold">Payment Method</label>

                    <select name="payment_method" id="payment_method" class="form-select">
                        <option value="">All Methods</option>
                        <option value="cod" {{ request('payment_method') === 'cod' ? 'selected' : '' }}>
                            Cash on Delivery
                        </option>
                        <option value="gcash" {{ request('payment_method') === 'gcash' ? 'selected' : '' }}>
                            GCash
                        </option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label for="payment_status" class="form-label fw-semibold">Payment Status</label>

                    <select name="payment_status" id="payment_status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" {{ request('payment_status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="failed" {{ request('payment_status') === 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="refunded" {{ request('payment_status') === 'refunded' ? 'selected' : '' }}>Refunded</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label for="delivery_status" class="form-label fw-semibold">Delivery</label>

                    <select name="delivery_status" id="delivery_status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" {{ request('delivery_status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="assigned_to_rider" {{ request('delivery_status') === 'assigned_to_rider' ? 'selected' : '' }}>Assigned</option>
                        <option value="out_for_delivery" {{ request('delivery_status') === 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
                        <option value="delivered" {{ request('delivery_status') === 'delivered' ? 'selected' : '' }}>Delivered</option>
                        <option value="delivery_failed" {{ request('delivery_status') === 'delivery_failed' ? 'selected' : '' }}>Failed</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label for="date_from" class="form-label fw-semibold">From</label>
                    <input
                        type="date"
                        name="date_from"
                        id="date_from"
                        class="form-control"
                        value="{{ request('date_from') }}"
                    >
                </div>

                <div class="col-lg-3 col-md-6">
                    <label for="date_to" class="form-label fw-semibold">To</label>
                    <input
                        type="date"
                        name="date_to"
                        id="date_to"
                        class="form-control"
                        value="{{ request('date_to') }}"
                    >
                </div>

                <div class="col-lg-6 d-flex align-items-end">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-funnel me-1"></i>
                            Filter
                        </button>

                        <a href="{{ route('admin.orders') }}" class="btn btn-outline-secondary px-4">
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            Reset
                        </a>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

{{-- ORDERS TABLE --}}
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3 border-0">Order</th>
                        <th class="py-3 border-0">Buyer</th>
                        <th class="py-3 border-0">Seller</th>
                        <th class="py-3 border-0 text-center">Items</th>
                        <th class="py-3 border-0">Total</th>
                        <th class="py-3 border-0">Payment</th>
                        <th class="py-3 border-0">Order Status</th>
                        <th class="py-3 border-0">Delivery</th>
                        <th class="py-3 border-0">Rider</th>
                        <th class="py-3 border-0">Date</th>
                        <th class="py-3 border-0 pe-4">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($orders as $order)

                        @php
                            $sellerNames = $order->sellerOrders
                                ->pluck('seller.name')
                                ->filter()
                                ->unique();

                            $itemCount = $order->sellerOrders
                                ->sum(fn ($sellerOrder) => $sellerOrder->items->sum('quantity'));

                            $statusClass = match($order->status) {
                                'delivered', 'completed' => 'success',
                                'cancelled', 'delivery_failed' => 'danger',
                                'placed', 'assigned_to_rider',
                                'reschedule_requested', 'return_requested' => 'warning',
                                'confirmed', 'at_sorting_center', 'rescheduled' => 'info',
                                'preparing', 'ready_for_pickup',
                                'picked_up', 'out_for_delivery' => 'primary',
                                'returned' => 'secondary',
                                default => 'secondary',
                            };

                            $deliveryClass = match($order->delivery_status) {
                                'delivered' => 'success',
                                'delivery_failed', 'failed' => 'danger',
                                'out_for_delivery' => 'primary',
                                'assigned_to_rider' => 'warning',
                                default => 'secondary',
                            };

                            $paymentClass = match($order->payment_status) {
                                'paid' => 'success',
                                'failed' => 'danger',
                                'refunded' => 'info',
                                'pending' => 'warning',
                                default => 'secondary',
                            };
                        @endphp

                        <tr>
                            <td class="px-4 py-3">
                                <div class="fw-semibold text-dark">
                                    {{ $order->order_number }}
                                </div>
                                <small class="text-muted">ID #{{ $order->id }}</small>
                            </td>

                            <td>
                                @if($order->user)
                                    <div class="fw-medium">{{ $order->user->name }}</div>
                                    <small class="text-muted">{{ $order->user->email }}</small>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>

                            <td>
                                @if($sellerNames->isNotEmpty())
                                    @foreach($sellerNames->take(2) as $sellerName)
                                        <div class="small fw-medium">{{ $sellerName }}</div>
                                    @endforeach

                                    @if($sellerNames->count() > 2)
                                        <small class="text-muted">
                                            +{{ $sellerNames->count() - 2 }} more
                                        </small>
                                    @endif
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>

                            <td class="text-center">
                                <span class="badge bg-light text-dark border">
                                    {{ $itemCount }}
                                </span>
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    ₱{{ number_format($order->total_minor / 100, 2) }}
                                </div>
                            </td>

                            <td>
                                <div class="fw-medium text-uppercase">
                                    {{ $order->payment_method ?: 'N/A' }}
                                </div>

                                <span class="badge bg-{{ $paymentClass }} mt-1">
                                    {{ ucfirst($order->payment_status ?? 'Unknown') }}
                                </span>
                            </td>

                            <td>
                                <span class="badge bg-{{ $statusClass }}">
                                    {{ ucwords(str_replace('_', ' ', $order->status ?? 'unknown')) }}
                                </span>
                            </td>

                            <td>
                                <span class="badge bg-{{ $deliveryClass }}">
                                    {{ ucwords(str_replace('_', ' ', $order->delivery_status ?? 'pending')) }}
                                </span>
                            </td>

                            <td>
                                @if($order->rider)
                                    <div class="fw-medium">{{ $order->rider->name }}</div>
                                @else
                                    <span class="text-muted">Not assigned</span>
                                @endif
                            </td>

                            <td>
                                <div class="small">
                                    {{ ($order->ordered_at ?? $order->created_at)?->format('M d, Y') }}
                                </div>
                                <small class="text-muted">
                                    {{ ($order->ordered_at ?? $order->created_at)?->format('h:i A') }}
                                </small>
                            </td>

                            <td class="pe-4">
                                <a
                                    href="{{ route('admin.orders.show', $order) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    <i class="bi bi-eye me-1"></i>
                                    View
                                </a>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-receipt fs-2 d-block mb-2"></i>

                                    @if(request()->hasAny([
                                        'search',
                                        'status',
                                        'payment_method',
                                        'payment_status',
                                        'delivery_status',
                                        'date_from',
                                        'date_to'
                                    ]))
                                        No orders match the selected filters.
                                    @else
                                        No marketplace orders have been placed yet.
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

        @if($orders->hasPages())
            <div class="p-3 border-top">
                {{ $orders->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

@endsection