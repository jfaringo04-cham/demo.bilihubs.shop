@extends('layouts.logistic')

@section('title', 'Rider Assignment')

@section('content')
<div class="container-fluid py-4">

    {{-- Header --}}
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-person-check me-2"></i>Rider Assignment
            </h2>
            <p class="text-muted mb-0">
                Assign staged deliveries to available Speedy Express riders.
            </p>
        </div>

        <a href="{{ route('logistic.shipments') }}"
           class="btn btn-outline-secondary">
            <i class="bi bi-box-seam me-1"></i>
            Delivery Management
        </a>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i>
            {{ session('success') }}

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i>
            {{ session('error') }}

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-2">
                                Awaiting Assignment
                            </div>

                            <h3 class="fw-bold mb-0">
                                {{ $awaitingAssignmentCount }}
                            </h3>
                        </div>

                        <div class="fs-3 text-warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-2">
                                Available Riders
                            </div>

                            <h3 class="fw-bold mb-0">
                                {{ $availableRidersCount }}
                            </h3>
                        </div>

                        <div class="fs-3 text-success">
                            <i class="bi bi-person-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-2">
                                Active Assignments
                            </div>

                            <h3 class="fw-bold mb-0">
                                {{ $assignedCount }}
                            </h3>
                        </div>

                        <div class="fs-3 text-primary">
                            <i class="bi bi-truck"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-2">
                                Full Capacity
                            </div>

                            <h3 class="fw-bold mb-0">
                                {{ $fullCapacityCount }}
                            </h3>
                        </div>

                        <div class="fs-3 text-danger">
                            <i class="bi bi-exclamation-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-4">

        {{-- Shipments --}}
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white border-0 pt-4 px-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                        <div>
                            <h5 class="fw-bold mb-1">
                                Shipments Ready for Assignment
                            </h5>

                            <small class="text-muted">
                                Only staged and unassigned shipments are shown.
                            </small>
                        </div>

                        <form method="GET"
                              action="{{ route('logistic.rider-assignment') }}"
                              class="d-flex gap-2">

                            <input type="text"
                                   name="search"
                                   value="{{ request('search') }}"
                                   class="form-control"
                                   placeholder="Tracking, order, customer...">

                            <button class="btn btn-primary">
                                <i class="bi bi-search"></i>
                            </button>

                            @if(request('search'))
                                <a href="{{ route('logistic.rider-assignment') }}"
                                   class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif

                        </form>
                    </div>
                </div>

                <div class="card-body p-0 mt-3">
                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Shipment</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Rider</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                            @forelse($shipments as $shipment)

                                @php
                                    $order = $shipment->sellerOrder?->order;
                                    $customer = $order?->user;
                                @endphp

                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-semibold">
                                            {{ $shipment->tracking_number }}
                                        </div>

                                        <small class="text-muted">
                                            {{ $order?->order_number ?? 'No order number' }}
                                        </small>
                                    </td>

                                    <td>
                                        <div class="fw-semibold">
                                            {{ $customer?->name ?? 'N/A' }}
                                        </div>

                                        <small class="text-muted">
                                            {{ $customer?->email ?? '' }}
                                        </small>
                                    </td>

                                    <td>
                                        <span class="badge bg-info-subtle text-info-emphasis">
                                            Staged
                                        </span>
                                    </td>

                                    <td style="min-width: 220px;">
                                        @if($availableRiders->isNotEmpty())

                                            <form method="POST"
                                                  action="{{ route('logistic.shipments.assign-rider', $shipment) }}"
                                                  class="d-flex gap-2 align-items-center">

                                                @csrf

                                                <select name="rider_id"
                                                        class="form-select form-select-sm"
                                                        required>

                                                    <option value="">
                                                        Select rider
                                                    </option>

                                                    @foreach($availableRiders as $rider)
                                                        <option value="{{ $rider->id }}">
                                                            {{ $rider->name }}
                                                            ({{ $rider->current_load ?? 0 }}/{{ $rider->max_capacity ?? 0 }})
                                                        </option>
                                                    @endforeach

                                                </select>
                                        @else
                                            <span class="text-muted small">
                                                No rider available
                                            </span>
                                        @endif
                                    </td>

                                    <td class="text-end pe-4">
                                        @if($availableRiders->isNotEmpty())

                                                <button type="submit"
                                                        class="btn btn-sm btn-primary text-nowrap">
                                                    <i class="bi bi-person-check me-1"></i>
                                                    Assign
                                                </button>

                                            </form>

                                        @else
                                            <button class="btn btn-sm btn-secondary"
                                                    disabled>
                                                Assign
                                            </button>
                                        @endif
                                    </td>
                                </tr>

                            @empty

                                <tr>
                                    <td colspan="5"
                                        class="text-center py-5">

                                        <i class="bi bi-check-circle fs-1 text-success"></i>

                                        <h6 class="fw-bold mt-3 mb-1">
                                            No shipments awaiting assignment
                                        </h6>

                                        <p class="text-muted mb-0">
                                            Staged shipments that need a rider will appear here.
                                        </p>

                                    </td>
                                </tr>

                            @endforelse
                            </tbody>
                        </table>

                    </div>
                </div>

                @if($shipments->hasPages())
                    <div class="card-footer bg-white border-0 px-4 py-3">
                        {{ $shipments->links() }}
                    </div>
                @endif

            </div>
        </div>

        {{-- Available Riders --}}
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-1">
                        Available Riders
                    </h5>

                    <small class="text-muted">
                        Riders currently able to accept deliveries.
                    </small>
                </div>

                <div class="card-body">

                    @forelse($availableRiders as $rider)

                        @php
                            $currentLoad = (int) ($rider->current_load ?? 0);
                            $maxCapacity = (int) ($rider->max_capacity ?? 0);

                            $loadPercent = $maxCapacity > 0
                                ? min(100, ($currentLoad / $maxCapacity) * 100)
                                : 0;
                        @endphp

                        <div class="border rounded-3 p-3 mb-3">

                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">

                                <div>
                                    <div class="fw-bold">
                                        {{ $rider->name }}
                                    </div>

                                    <small class="text-muted">
                                        {{ $rider->vehicle_type ?? 'Rider' }}
                                    </small>
                                </div>

                                <span class="badge bg-success-subtle text-success-emphasis">
                                    Available
                                </span>

                            </div>

                            @if($rider->assigned_zone)
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-geo-alt me-1"></i>
                                    {{ $rider->assigned_zone }}
                                </div>
                            @endif

                            <div class="d-flex justify-content-between small mb-1">
                                <span>Delivery Load</span>

                                <strong>
                                    {{ $currentLoad }} / {{ $maxCapacity }}
                                </strong>
                            </div>

                            <div class="progress"
                                 style="height: 6px;">

                                <div class="progress-bar"
                                     role="progressbar"
                                     style="width: {{ $loadPercent }}%"
                                     aria-valuenow="{{ $loadPercent }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                </div>

                            </div>

                        </div>

                    @empty

                        <div class="text-center py-5">

                            <i class="bi bi-person-x fs-1 text-muted"></i>

                            <h6 class="fw-bold mt-3">
                                No available riders
                            </h6>

                            <p class="text-muted small mb-0">
                                Riders will appear here when they are available
                                and have remaining delivery capacity.
                            </p>

                        </div>

                    @endforelse

                </div>
            </div>
        </div>

    </div>
</div>
@endsection