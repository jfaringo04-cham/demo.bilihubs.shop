@extends('rider.layout')

@section('content')
@php
$status = $shipment->status;

$statusColors = [
'assigned' => 'secondary',
'picked_up' => 'info',
'in_transit' => 'primary',
'out_for_delivery' => 'warning',
'delivered' => 'success',
'delivery_failed' => 'danger',
'cancelled' => 'secondary',
];

$statusColor = $statusColors[$status] ?? 'secondary';

$deliveryAddress = $shipment->delivery_address;

if (is_string($deliveryAddress)) {
$decoded = json_decode($deliveryAddress, true);

if (json_last_error() === JSON_ERROR_NONE) {
$deliveryAddress = $decoded;
}
}

if (is_array($deliveryAddress)) {
$deliveryAddress = $deliveryAddress['address'] ?? 'N/A';
}

$canUpdate = in_array($status, [
'picked_up',
'in_transit',
'out_for_delivery',
], true);
$order = $shipment->sellerOrder?->order;
$sellerOrder = $shipment->sellerOrder;

$isCod = $order?->payment_method === 'cod';

$shipmentAmountMinor = (int) ($sellerOrder?->total_minor ?? 0);

// Check whether this shipment already has a successful COD payment.
$shipmentPaid = \App\Models\Payment::query()
->where('shipment_id', $shipment->id)
->where('method', 'cod')
->where('status', 'paid')
->exists();

// Historical fully paid orders must not be collected again.
$orderAlreadyPaid = $order?->payment_status === 'paid';

$canCollectCod = $isCod
&& !$shipmentPaid
&& !$orderAlreadyPaid
&& $shipmentAmountMinor > 0
&& in_array($status, ['out_for_delivery', 'delivered'], true);
@endphp

<div class="container-fluid px-3 px-lg-4 py-4">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a href="{{ route('rider.orders') }}"
                class="text-decoration-none small">
                <i class="bi bi-arrow-left me-1"></i>
                Back to My Deliveries
            </a>

            <h2 class="fw-bold mt-3 mb-1">Shipment Details</h2>

            <div class="text-muted">
                Tracking Number:
                <span class="fw-semibold text-dark">
                    {{ $shipment->tracking_number ?? $shipment->tracking_code ?? 'N/A' }}
                </span>
            </div>
        </div>

        <span class="badge bg-{{ $statusColor }} fs-6 px-3 py-2">
            {{ ucwords(str_replace('_', ' ', $status)) }}
        </span>
    </div>

    

    {{-- Validation Errors --}}
    @if($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-2">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            Please check the following:
        </div>

        <ul class="mb-0">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="row g-4">

        {{-- Parcel Information --}}
        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-box-seam me-2 text-primary"></i>
                        Parcel Information
                    </h5>
                </div>

                <div class="card-body p-4">

                    <div class="row g-4">

                        <div class="col-sm-6">
                            <div class="small text-muted mb-1">Shipment Status</div>
                            <span class="badge bg-{{ $statusColor }}">
                                {{ ucwords(str_replace('_', ' ', $status)) }}
                            </span>
                        </div>

                        <div class="col-sm-6">
                            <div class="small text-muted mb-1">Logistics Provider</div>
                            <div class="fw-semibold">
                                {{ $shipment->logistic?->company_name ?? 'N/A' }}
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="small text-muted mb-1">Seller</div>
                            <div class="fw-semibold">
                                {{ $shipment->sellerOrder?->seller?->name ?? 'N/A' }}
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="small text-muted mb-1">Customer</div>
                            <div class="fw-semibold">
                                {{ $shipment->sellerOrder?->order?->user?->name ?? 'N/A' }}
                            </div>
                        </div>

                        <div class="col-12">
                            <hr class="my-0">
                        </div>

                        <div class="col-12">
                            <div class="small text-muted mb-2">
                                <i class="bi bi-geo-alt me-1"></i>
                                Delivery Address
                            </div>

                            <div class="fw-medium">
                                {{ $deliveryAddress ?: 'N/A' }}
                            </div>

                            @if($deliveryAddress && $deliveryAddress !== 'N/A')
                            <a href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($deliveryAddress) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn btn-sm btn-outline-primary mt-3">
                                <i class="bi bi-map me-1"></i>
                                Open in Google Maps
                            </a>
                            @endif
                        </div>

                        @if($shipment->delivered_at)
                        <div class="col-12">
                            <hr class="my-0">
                        </div>

                        <div class="col-12">
                            <div class="small text-muted mb-1">
                                <i class="bi bi-check-circle me-1"></i>
                                Delivery Completed
                            </div>

                            <div class="fw-semibold text-success">
                                {{ $shipment->delivered_at->format('M d, Y h:i A') }}
                            </div>
                        </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>

        {{-- Delivery Status --}}
        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-truck me-2 text-primary"></i>
                        Delivery Status
                    </h5>
                </div>

                <div class="card-body p-4">

                    @if($canUpdate)

                    <p class="small text-muted mb-4">
                        Update the current status of this assigned parcel.
                    </p>

                    <form action="{{ route('rider.shipments.updateStatus', $shipment) }}"
                        method="POST"
                        enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="delivery_status"
                                class="form-label fw-semibold">
                                New Status
                            </label>

                            <select id="delivery_status"
                                name="delivery_status"
                                class="form-select @error('delivery_status') is-invalid @enderror"
                                required>
                                <option value="">Select status</option>

                                @if($status !== 'out_for_delivery')
                                <option value="out_for_delivery"
                                    @selected(old('delivery_status')==='out_for_delivery' )>
                                    Out for Delivery
                                </option>
                                @else
                                <option value="delivered"
                                    @selected(old('delivery_status')==='delivered' )>
                                    Delivered
                                </option>

                                <option value="delivery_failed"
                                    @selected(old('delivery_status')==='delivery_failed' )>
                                    Delivery Failed
                                </option>
                                @endif
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="delivery_notes" class="form-label fw-semibold">
                                Delivery Notes
                            </label>

                            <textarea id="delivery_notes"
                                name="delivery_notes"
                                rows="3"
                                maxlength="1000"
                                class="form-control"
                                placeholder="Optional delivery notes">{{ old('delivery_notes') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="failure_reason" class="form-label fw-semibold">
                                Failure Reason
                            </label>

                            <textarea id="failure_reason"
                                name="failure_reason"
                                rows="2"
                                maxlength="1000"
                                class="form-control"
                                placeholder="Required when delivery fails">{{ old('failure_reason') }}</textarea>

                            <div class="form-text">
                                Required only when selecting Delivery Failed.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="proof_of_delivery" class="form-label fw-semibold">
                                Proof of Delivery
                            </label>

                            <input id="proof_of_delivery"
                                type="file"
                                name="proof_of_delivery"
                                accept="image/jpeg,image/png"
                                class="form-control">

                            <div class="form-text">
                                JPG or PNG, maximum 5 MB. Required when marking Delivered.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="delivered_to" class="form-label fw-semibold">
                                Received By
                            </label>

                            <input id="delivered_to"
                                type="text"
                                name="delivered_to"
                                maxlength="255"
                                value="{{ old('delivered_to') }}"
                                class="form-control"
                                placeholder="Recipient's name">
                        </div>

                        <button type="submit"
                            class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i>
                            Update Shipment
                        </button>
                    </form>

                    @else

                    <div class="text-center py-4">
                        @if($status === 'delivered')
                        <i class="bi bi-check-circle-fill text-success"
                            style="font-size: 3rem;"></i>

                        <h5 class="fw-bold mt-3">Delivery Completed</h5>

                        <p class="text-muted mb-0">
                            This parcel has been successfully delivered.
                            No further status updates are available.
                        </p>
                        @elseif($status === 'delivery_failed')
                        <i class="bi bi-exclamation-circle-fill text-danger"
                            style="font-size: 3rem;"></i>

                        <h5 class="fw-bold mt-3">Delivery Failed</h5>

                        <p class="text-muted mb-0">
                            This parcel requires further handling by logistics.
                        </p>
                        @else
                        <i class="bi bi-clock-history text-secondary"
                            style="font-size: 3rem;"></i>

                        <h5 class="fw-bold mt-3">Update Not Available</h5>

                        <p class="text-muted mb-0">
                            This shipment is not currently eligible
                            for a delivery status update.
                        </p>
                        @endif
                    </div>

                    @endif
                </div>
            </div>
        </div>

        {{-- Shipment COD Collection --}}
        @if($isCod)
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-cash-coin me-2 text-success"></i>
                        Cash on Delivery
                    </h5>
                </div>

                <div class="card-body p-4">

                    <div class="row align-items-center g-4">
                        <div class="col-md-7">
                            <div class="small text-muted mb-1">
                                Amount to Collect for This Shipment
                            </div>

                            <h3 class="fw-bold text-success mb-2">
                                &#8369;{{ number_format($shipmentAmountMinor / 100, 2) }}
                            </h3>

                            <p class="text-muted small mb-0">
                                This amount belongs to the current seller's shipment,
                                not the entire marketplace order.
                            </p>
                        </div>

                        <div class="col-md-5">

                            @if($shipmentPaid)

                            <div class="alert alert-success mb-0">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                Shipment COD payment recorded.
                            </div>

                            @elseif($orderAlreadyPaid)

                            <div class="alert alert-success mb-0">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                Order payment already completed.
                                No additional COD collection required.
                            </div>

                            @elseif($canCollectCod)

                            <form
                                action="{{ route('rider.shipments.collect', $shipment) }}"
                                method="POST">
                                @csrf

                                <input
                                    type="hidden"
                                    name="amount_collected"
                                    value="{{ number_format($shipmentAmountMinor / 100, 2, '.', '') }}">

                                <button
                                    type="submit"
                                    class="btn btn-success w-100 py-3 fw-semibold"
                                    onclick="return confirm('Confirm that you received the exact COD amount for this shipment?')">
                                    <i class="bi bi-cash-stack me-2"></i>
                                    Confirm COD Collection
                                </button>
                            </form>

                            <div class="form-text mt-2 text-center">
                                Confirm only after receiving the cash.
                            </div>

                            @else

                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                COD collection is not available at this shipment status.
                            </div>

                            @endif

                        </div>
                    </div>

                </div>
            </div>
        </div>
        @endif

        {{-- Proof of Delivery --}}
        @if($shipment->proof_of_delivery)
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-image me-2 text-primary"></i>
                        Proof of Delivery
                    </h5>
                </div>

                <div class="card-body p-4">
                    <div class="row g-4 align-items-start">
                        <div class="col-md-5">
                            <img src="{{ route('rider.shipments.proof', $shipment) }}"
                                alt="Proof of Delivery"
                                class="img-fluid rounded-3 border"
                                style="max-height: 320px; object-fit: contain;">
                        </div>

                        <div class="col-md-7">
                            <div class="small text-muted mb-1">
                                Received By
                            </div>

                            <div class="fw-semibold">
                                {{ $shipment->delivered_to ?? 'Not specified' }}
                            </div>

                            @if($shipment->delivered_at)
                            <div class="small text-muted mt-3 mb-1">
                                Delivered On
                            </div>

                            <div class="fw-semibold">
                                {{ $shipment->delivered_at->format('M d, Y h:i A') }}
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection