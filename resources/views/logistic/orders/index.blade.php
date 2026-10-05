@extends('layouts.logistic', [
    'title' => 'Order Management',
    'logistic' => $logistic
])

@section('content')
<div class="container-fluid px-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-4">
        <div>
            <h1 class="fw-bold text-slate-900 mb-1">Order Management</h1>
            <p class="text-muted mb-0">
                View and manage orders assigned to {{ $logistic->company_name }}.
            </p>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-primary bg-opacity-10 p-3">
                        <i class="bi bi-box-seam fs-3 text-primary"></i>
                    </div>

                    <div>
                        <div class="text-muted small">Total Orders</div>
                        <div class="fs-3 fw-bold">
                            {{ number_format($totalOrders) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-warning bg-opacity-10 p-3">
                        <i class="bi bi-clock-history fs-3 text-warning"></i>
                    </div>

                    <div>
                        <div class="text-muted small">Pending Orders</div>
                        <div class="fs-3 fw-bold">
                            {{ number_format($pendingOrders) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-success bg-opacity-10 p-3">
                        <i class="bi bi-check-circle fs-3 text-success"></i>
                    </div>

                    <div>
                        <div class="text-muted small">Delivered Orders</div>
                        <div class="fs-3 fw-bold">
                            {{ number_format($deliveredOrders) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Search / Filter --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body">

            <form method="GET"
                  action="{{ route('logistic.orders') }}"
                  class="row g-3 align-items-end">

                <div class="col-lg-6">
                    <label class="form-label fw-semibold">
                        Search Orders
                    </label>

                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="{{ request('search') }}"
                            placeholder="Order #, tracking #, buyer, email or seller"
                        >
                    </div>
                </div>

                <div class="col-lg-3">
                    <label class="form-label fw-semibold">
                        Order Status
                    </label>

                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>

                        <option value="pending"
                            {{ request('status') === 'pending' ? 'selected' : '' }}>
                            Pending
                        </option>

                        <option value="delivered"
                            {{ request('status') === 'delivered' ? 'selected' : '' }}>
                            Delivered
                        </option>
                    </select>
                </div>

                <div class="col-lg-3">
                    <div class="d-flex gap-2">

                        <button type="submit"
                                class="btn btn-primary flex-grow-1">
                            <i class="bi bi-funnel me-1"></i>
                            Apply
                        </button>

                        <a href="{{ route('logistic.orders') }}"
                           class="btn btn-outline-secondary">
                            Reset
                        </a>

                    </div>
                </div>

            </form>

        </div>
    </div>

    {{-- Orders Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1">Assigned Orders</h5>
                    <small class="text-muted">
                        Orders currently handled by your logistics company
                    </small>
                </div>

                <span class="badge bg-light text-dark border">
                    {{ $orders->total() }} result{{ $orders->total() === 1 ? '' : 's' }}
                </span>
            </div>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">
                <table class="table align-middle mb-0">

                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="py-3">Buyer</th>
                            <th class="py-3">Seller</th>
                            <th class="py-3">Items</th>
                            <th class="py-3">Amount</th>
                            <th class="py-3">Tracking</th>
                            <th class="py-3">Rider</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 text-end pe-4">Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    @forelse($orders as $sellerOrder)

                        @php
                            $order = $sellerOrder->order;
                            $buyer = $order?->user;
                            $seller = $sellerOrder->seller;
                            $shipment = $sellerOrder->shipment;

                            $itemQuantity = $sellerOrder->items->sum('quantity');
                        @endphp

                        <tr>

                            {{-- Order --}}
                            <td class="px-4">
                                <div class="fw-semibold">
                                    {{ $order?->order_number ?? 'N/A' }}
                                </div>

                                <small class="text-muted">
                                    {{ $sellerOrder->created_at?->format('M d, Y') }}
                                </small>
                            </td>

                            {{-- Buyer --}}
                            <td>
                                <div class="fw-medium">
                                    {{ $buyer?->name ?? 'Unknown Buyer' }}
                                </div>

                                <small class="text-muted">
                                    {{ $buyer?->email ?? 'No email' }}
                                </small>
                            </td>

                            {{-- Seller --}}
                            <td>
                                <div class="fw-medium">
                                    {{ $seller?->name ?? 'Unknown Seller' }}
                                </div>
                            </td>

                            {{-- Items --}}
                            <td>
                                <span class="fw-semibold">
                                    {{ number_format($itemQuantity) }}
                                </span>

                                <small class="text-muted">
                                    item{{ $itemQuantity == 1 ? '' : 's' }}
                                </small>
                            </td>

                            {{-- Amount --}}
                            <td>
                                <div class="fw-semibold">
                                    ₱{{ number_format(
                                        ($sellerOrder->total_minor ?? 0) / 100,
                                        2
                                    ) }}
                                </div>
                            </td>

                            {{-- Tracking --}}
                            <td>
                                @if($shipment)
                                    <code>
                                        {{ $shipment->tracking_number }}
                                    </code>

                                    <div class="small text-muted mt-1">
                                        {{ ucwords(str_replace('_', ' ', $shipment->status)) }}
                                    </div>
                                @else
                                    <span class="badge bg-light text-dark border">
                                        No Shipment
                                    </span>
                                @endif
                            </td>

                            {{-- Rider --}}
                            <td>
                                @if($shipment?->rider)
                                    <div class="fw-medium">
                                        {{ $shipment->rider->name }}
                                    </div>
                                @else
                                    <span class="text-muted">
                                        Unassigned
                                    </span>
                                @endif
                            </td>

                            {{-- Seller Order Status --}}
                            <td>
                                @if($sellerOrder->status === 'delivered')

                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Delivered
                                    </span>

                                @elseif($sellerOrder->status === 'pending')

                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-clock me-1"></i>
                                        Pending
                                    </span>

                                @else

                                    <span class="badge bg-secondary">
                                        {{ ucfirst($sellerOrder->status) }}
                                    </span>

                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="text-end pe-4">

                                @if($shipment)

                                    <a href="{{ route(
                                            'logistic.shipments.show',
                                            $shipment
                                        ) }}"
                                       class="btn btn-sm btn-primary">

                                        <i class="bi bi-eye me-1"></i>
                                        View

                                    </a>

                                @else

                                    <button
                                        class="btn btn-sm btn-outline-secondary"
                                        disabled>

                                        No Shipment

                                    </button>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="9"
                                class="text-center py-5">

                                <div class="text-muted">

                                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>

                                    <h6 class="fw-semibold">
                                        No orders found
                                    </h6>

                                    <p class="small mb-0">
                                        No orders match the selected filters.
                                    </p>

                                </div>

                            </td>
                        </tr>

                    @endforelse

                    </tbody>
                </table>
            </div>

        </div>

        @if($orders->hasPages())
            <div class="card-footer bg-white border-top px-4 py-3">
                {{ $orders->links('pagination::bootstrap-5') }}
            </div>
        @endif

    </div>

</div>
@endsection