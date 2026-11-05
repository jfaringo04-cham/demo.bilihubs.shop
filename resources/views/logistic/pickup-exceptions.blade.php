@extends('layouts.logistic')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Pickup Exceptions
            </h1>

            <p class="text-muted mb-0">
                Monitor seller parcels awaiting pickup and unresolved routing issues.
            </p>
        </div>

        <a href="{{ route('logistic.shipments') }}"
            class="btn btn-outline-secondary">
            <i class="bi bi-box-seam me-1"></i>
            All Shipments
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">
                        Parcels Awaiting Pickup
                    </div>
                    <div class="h2 mb-1">
                        {{ number_format($summary['awaiting_pickup']) }}
                    </div>
                    <small class="text-muted">
                        Pending rider offers and assigned pickup riders
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">
                        Unresolved Routing
                    </div>
                    <div class="h2 mb-1 text-danger">
                        {{ number_format($summary['routing_issues']) }}
                    </div>
                    <small class="text-muted">
                        Seller orders without a valid route
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- ROUTING ISSUES -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0">
                <i class="bi bi-signpost-split me-2"></i>
                Routing Assistance Required
            </h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Seller Order</th>
                            <th>Status</th>
                            <th>Issue</th>
                            <th>Recommended Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($routingIssues as $sellerOrder)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">
                                    #{{ $sellerOrder->id }}
                                </div>
                                <small class="text-muted">
                                    {{ $sellerOrder->order?->order_number ?? 'Order unavailable' }}
                                </small>
                            </td>

                            <td>
                                <span class="badge bg-secondary">
                                    {{ ucwords(str_replace('_', ' ', $sellerOrder->status)) }}
                                </span>
                            </td>

                            <td>
                                <span class="badge bg-danger">
                                    No Valid Hub
                                </span>
                            </td>

                            <td>
                                <span class="small">
                                    Review the pickup address, hub coverage
                                    or default hub configuration.
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4"
                                class="text-center text-muted py-4">
                                No unresolved routing issues found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PENDING PICKUP SHIPMENTS -->
    <div class="card shadow-sm">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0">
                <i class="bi bi-truck me-2"></i>
                Pending Pickup Monitoring
            </h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Shipment</th>
                            <th>Origin Hub</th>
                            <th>Pickup Status</th>
                            <th>Waiting Since</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($shipments as $shipment)
                        @php
                        $exception = $shipment->pickup_exception;

                        $canRetry =
                        $shipment->status === 'pending'
                        && !$shipment->pickup_rider_id
                        && !$shipment->rider_id
                        && !$shipment->picked_up_at
                        && !$shipment->received_by_sorting_center
                        && $shipment->sellerOrder?->status === 'ready_for_pickup'
                        && !in_array($exception['code'], [
                        'offer_pending',
                        'no_valid_hub',
                        ], true);

                        $badgeColor = match ($exception['severity']) {
                        'danger' => 'danger',
                        'warning' => 'warning text-dark',
                        'success' => 'success',
                        'info' => 'info text-dark',
                        default => 'secondary',
                        };
                        @endphp

                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">
                                    {{ $shipment->tracking_number ?: '#' . $shipment->id }}
                                </div>

                                <small class="text-muted d-block">
                                    Seller Order #{{ $shipment->seller_order_id }}
                                </small>

                                <small class="text-muted d-block">
                                    {{ $shipment->sellerOrder?->order?->order_number }}
                                </small>
                            </td>

                            <td>
                                <small>
                                    {{ $shipment->originHub?->name ?? 'Not assigned' }}
                                </small>
                            </td>

                            <td style="min-width: 200px;">
                                <span class="badge bg-{{ $badgeColor }}">
                                    {{ $exception['label'] }}
                                </span>

                                <p class="small text-muted mb-0 mt-2">
                                    {{ $exception['message'] }}
                                </p>
                            </td>

                            <td>
                                <small class="text-muted">
                                    {{ $shipment->created_at?->format('M d, Y h:i A') ?? 'Unknown' }}
                                </small>
                            </td>

                            <td>
                                @if($canRetry)
                                <form method="POST"
                                    action="{{ route('logistic.pickup-exceptions.retry', $shipment) }}">
                                    @csrf

                                    <button type="submit"
                                        class="btn btn-sm btn-outline-primary text-nowrap"
                                        onclick="return confirm('Retry pickup dispatch for this parcel?')">
                                        <i class="bi bi-arrow-repeat me-1"></i>
                                        Retry Pickup
                                    </button>
                                </form>

                                @elseif($exception['code'] === 'offer_pending')
                                <small class="text-muted">
                                    Awaiting rider response
                                </small>

                                @elseif($exception['code'] === 'rider_assigned')
                                <small class="text-muted">
                                    Awaiting collection
                                </small>

                                @else
                                <small class="text-muted">
                                    Review required
                                </small>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5"
                                class="text-center text-muted py-4">
                                No parcels currently awaiting pickup.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($shipments->hasPages())
        <div class="card-footer bg-transparent">
            {{ $shipments->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>

    <div class="alert alert-info mt-4">
        <i class="bi bi-info-circle me-1"></i>
        Retrying pickup sends an offer to an eligible rider.
        It does not confirm physical collection or bypass hub eligibility.
    </div>

</div>
@endsection