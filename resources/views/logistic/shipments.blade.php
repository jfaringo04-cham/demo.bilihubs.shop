@extends('layouts.logistic', ['title' => 'Delivery Management', 'logistic' => $logistic])

@section('content')
<div class="container-fluid px-4">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
        <div>
            <h1 class="fw-bold text-slate-900 mb-1">Delivery Management</h1>
            <p class="text-muted mb-0">
                Manage, monitor, and track shipments handled by {{ $logistic->company_name }}.
            </p>
        </div>

        <a href="{{ route('logistic.shipments.create') }}"
           class="btn btn-primary btn-sm rounded-xl">
            <i class="bi bi-plus-circle me-1"></i>
            New Shipment
        </a>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Total Deliveries</p>
                        <h3 class="fw-bold mb-0">{{ $totalShipments }}</h3>
                    </div>

                    <div class="fs-2 text-primary">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Pending</p>
                        <h3 class="fw-bold mb-0">{{ $pendingShipments }}</h3>
                    </div>

                    <div class="fs-2 text-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">In Transit</p>
                        <h3 class="fw-bold mb-0">{{ $inTransitShipments }}</h3>
                    </div>

                    <div class="fs-2 text-info">
                        <i class="bi bi-truck"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Delivered</p>
                        <h3 class="fw-bold mb-0">{{ $deliveredShipments }}</h3>
                    </div>

                    <div class="fs-2 text-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Search / Filters --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body">

            <form method="GET"
                  action="{{ route('logistic.shipments') }}"
                  class="row g-3 align-items-end">

                <div class="col-lg-5 col-md-6">
                    <label for="search" class="form-label fw-medium">
                        Search Delivery
                    </label>

                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="text"
                            name="search"
                            id="search"
                            value="{{ request('search') }}"
                            class="form-control"
                            placeholder="Tracking #, order #, buyer, seller or rider"
                        >
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label for="status" class="form-label fw-medium">
                        Delivery Status
                    </label>

                    <select name="status"
                            id="status"
                            class="form-select">

                        <option value="">All Statuses</option>

                        <option value="pending"
                            {{ request('status') === 'pending' ? 'selected' : '' }}>
                            Pending
                        </option>

                        <option value="assigned"
                            {{ request('status') === 'assigned' ? 'selected' : '' }}>
                            Assigned
                        </option>

                        <option value="picked_up"
                            {{ request('status') === 'picked_up' ? 'selected' : '' }}>
                            Picked Up
                        </option>

                        <option value="in_transit"
                            {{ request('status') === 'in_transit' ? 'selected' : '' }}>
                            In Transit
                        </option>

                        <option value="delivered"
                            {{ request('status') === 'delivered' ? 'selected' : '' }}>
                            Delivered
                        </option>

                        <option value="delayed"
                            {{ request('status') === 'delayed' ? 'selected' : '' }}>
                            Delayed
                        </option>

                        <option value="cancelled"
                            {{ request('status') === 'cancelled' ? 'selected' : '' }}>
                            Cancelled
                        </option>

                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <button type="submit"
                            class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>
                        Apply
                    </button>
                </div>

                <div class="col-lg-2 col-md-6">
                    <a href="{{ route('logistic.shipments') }}"
                       class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        Reset
                    </a>
                </div>

            </form>

        </div>
    </div>

    {{-- Delivery Table --}}
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">

        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0">Delivery List</h5>
                    <small class="text-muted">
                        {{ $shipments->total() }} delivery record(s)
                    </small>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th class="border-0 ps-4">Tracking / Order</th>
                        <th class="border-0">Customer</th>
                        <th class="border-0">Rider</th>
                        <th class="border-0">Pickup</th>
                        <th class="border-0">Delivery Address</th>
                        <th class="border-0">Status</th>
                        <th class="border-0">Created</th>
                        <th class="border-0 text-end pe-4">Actions</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($shipments as $shipment)

                        @php
                            $order = $shipment->sellerOrder?->order;
                            $buyer = $order?->user;

                            /*
                             * Some existing delivery_address values are JSON strings:
                             * {"recipient":"...", "phone":"...", "address":"..."}
                             *
                             * Decode them when possible while retaining support
                             * for older plain-text addresses.
                             */
                            $deliveryData = json_decode($shipment->delivery_address ?? '', true);

                            $deliveryRecipient = is_array($deliveryData)
                                ? ($deliveryData['recipient'] ?? null)
                                : null;

                            $deliveryPhone = is_array($deliveryData)
                                ? ($deliveryData['phone'] ?? null)
                                : null;

                            $deliveryAddress = is_array($deliveryData)
                                ? ($deliveryData['address'] ?? $shipment->delivery_address)
                                : $shipment->delivery_address;
                        @endphp

                        <tr>

                            {{-- Tracking / Order --}}
                            <td class="ps-4">
                                <div class="fw-semibold text-dark">
                                    {{ $shipment->tracking_number }}
                                </div>

                                <small class="text-muted">
                                    {{ $order?->order_number ?? 'No order number' }}
                                </small>
                            </td>

                            {{-- Customer --}}
                            <td>
                                <div class="fw-medium">
                                    {{ $buyer?->name ?? $deliveryRecipient ?? 'N/A' }}
                                </div>

                                @if($buyer?->email)
                                    <small class="text-muted">
                                        {{ $buyer->email }}
                                    </small>
                                @elseif($deliveryPhone)
                                    <small class="text-muted">
                                        {{ $deliveryPhone }}
                                    </small>
                                @endif
                            </td>

                            {{-- Rider --}}
                            <td>
                                @if($shipment->rider)
                                    <div class="fw-medium">
                                        {{ $shipment->rider->name }}
                                    </div>

                                    <small class="text-muted">
                                        Assigned
                                    </small>
                                @else
                                    <span class="text-muted">
                                        <i class="bi bi-person-dash me-1"></i>
                                        Unassigned
                                    </span>
                                @endif
                            </td>

                            {{-- Pickup --}}
                            <td style="min-width: 180px;">
                                <span
                                    class="text-muted"
                                    title="{{ $shipment->pickup_address }}"
                                >
                                    {{ \Illuminate\Support\Str::limit($shipment->pickup_address, 45) }}
                                </span>
                            </td>

                            {{-- Delivery --}}
                            <td style="min-width: 220px;">
                                @if($deliveryRecipient)
                                    <div class="fw-medium">
                                        {{ $deliveryRecipient }}
                                    </div>
                                @endif

                                <div
                                    class="text-muted small"
                                    title="{{ $deliveryAddress }}"
                                >
                                    <i class="bi bi-geo-alt me-1"></i>
                                    {{ \Illuminate\Support\Str::limit($deliveryAddress ?: 'No address available', 60) }}
                                </div>

                                @if($deliveryPhone)
                                    <div class="text-muted small mt-1">
                                        <i class="bi bi-telephone me-1"></i>
                                        {{ $deliveryPhone }}
                                    </div>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td>
                                @switch($shipment->status)

                                    @case('delivered')
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Delivered
                                        </span>
                                        @break

                                    @case('in_transit')
                                        <span class="badge bg-primary">
                                            <i class="bi bi-truck me-1"></i>
                                            In Transit
                                        </span>
                                        @break

                                    @case('picked_up')
                                        <span class="badge bg-info text-dark">
                                            Picked Up
                                        </span>
                                        @break

                                    @case('assigned')
                                        <span class="badge bg-warning text-dark">
                                            Assigned
                                        </span>
                                        @break

                                    @case('pending')
                                        <span class="badge bg-secondary">
                                            Pending
                                        </span>
                                        @break

                                    @case('delayed')
                                        <span class="badge bg-danger">
                                            Delayed
                                        </span>
                                        @break

                                    @case('cancelled')
                                        <span class="badge bg-danger">
                                            Cancelled
                                        </span>
                                        @break

                                    @default
                                        <span class="badge bg-light text-dark border">
                                            {{ ucwords(str_replace('_', ' ', $shipment->status)) }}
                                        </span>

                                @endswitch
                            </td>

                            {{-- Created --}}
                            <td>
                                <div class="text-dark">
                                    {{ $shipment->created_at->format('M d, Y') }}
                                </div>

                                <small class="text-muted">
                                    {{ $shipment->created_at->format('h:i A') }}
                                </small>
                            </td>

                            {{-- Actions --}}
                            <td class="text-end pe-4 text-nowrap">

                                <a
                                    href="{{ route('logistic.shipments.show', $shipment) }}"
                                    class="btn btn-primary btn-sm"
                                    title="View delivery details"
                                >
                                    <i class="bi bi-eye me-1"></i>
                                    View
                                </a>

                                <a
                                    href="{{ route('logistic.shipments.track', $shipment) }}"
                                    class="btn btn-outline-secondary btn-sm"
                                    target="_blank"
                                    title="Track delivery"
                                >
                                    <i class="bi bi-geo-alt me-1"></i>
                                    Track
                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="8" class="text-center py-5">

                                <div class="text-muted">
                                    <i class="bi bi-box-seam fs-1 d-block mb-3"></i>

                                    <h6 class="fw-semibold">
                                        No deliveries found
                                    </h6>

                                    <p class="small mb-0">
                                        @if(request()->filled('search') || request()->filled('status'))
                                            No delivery matches the selected search or filter.
                                        @else
                                            There are currently no deliveries assigned to this logistics company.
                                        @endif
                                    </p>
                                </div>

                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>
        </div>

        @if($shipments->hasPages())
            <div class="card-footer bg-white border-top p-3">
                {{ $shipments->links('pagination::bootstrap-5') }}
            </div>
        @endif

    </div>

</div>
@endsection