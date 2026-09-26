@extends('layouts.logistic', ['title' => 'Track Shipment', 'logistic' => $logistic])

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Track Shipment</h1>
  <a href="{{ route('logistic.shipments.show', $shipment) }}" class="btn btn-secondary btn-sm">Back to Shipment</a>
</div>

<div class="row g-4">
  <div class="col-md-8">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white">
        <h5 class="mb-0">Live Map</h5>
      </div>
      <div class="card-body p-0">
        <div id="map" style="height: 500px; width: 100%;"></div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white">
        <h5 class="mb-0">Shipment Information</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label text-muted">Tracking Number</label>
          <p class="fw-bold"><code>{{ $shipment->tracking_number }}</code></p>
        </div>
        <div class="mb-3">
          <label class="form-label text-muted">Status</label>
          <p class="fw-bold">
            <span class="badge bg-{{ $shipment->statusBadgeClass() }}">
              {{ ucfirst(str_replace('_', ' ', $shipment->status)) }}
            </span>
          </p>
        </div>
        <div class="mb-3">
          <label class="form-label text-muted">Pickup Address</label>
          <p class="fw-bold">{{ $shipment->pickup_address }}</p>
        </div>
        <div class="mb-3">
          <label class="form-label text-muted">Delivery Address</label>
          <p class="fw-bold">{{ $shipment->delivery_address }}</p>
        </div>
      </div>
    </div>

    @if($shipment->rider)
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white">
        <h5 class="mb-0">Rider Information</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label text-muted">Rider Name</label>
          <p class="fw-bold">{{ $shipment->rider->name }}</p>
        </div>
        @if($shipment->rider->vehicle_type)
        <div class="mb-3">
          <label class="form-label text-muted">Vehicle Type</label>
          <p class="fw-bold">{{ $shipment->rider->vehicle_type }}</p>
        </div>
        @endif
        @if($shipment->rider->mobile_number)
        <div class="mb-3">
          <label class="form-label text-muted">Contact</label>
          <p class="fw-bold">{{ $shipment->rider->mobile_number }}</p>
        </div>
        @endif
      </div>
    </div>
    @endif

    @if($riderLocation)
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0">Last Known Location</h5>
      </div>
      <div class="card-body">
        <p class="text-muted">Updated: {{ $riderLocation->updated_at->diffForHumans() }}</p>
        <p class="fw-bold">Latitude: {{ $riderLocation->latitude }}</p>
        <p class="fw-bold">Longitude: {{ $riderLocation->longitude }}</p>
        @if($riderLocation->accuracy)
        <p class="fw-bold">Accuracy: {{ $riderLocation->accuracy }}m</p>
        @endif
      </div>
    </div>
    @endif
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@vite('resources/js/logistics/shipments.js')
<script type="application/json" id="shipment-tracking-data">@php $shipmentTrackingData = [
  'pickup' => $shipment->latitude && $shipment->longitude ? [
    'latitude' => $shipment->latitude,
    'longitude' => $shipment->longitude,
    'address' => $shipment->pickup_address,
  ] : null,
  'rider' => $shipment->rider_latitude && $shipment->rider_longitude ? [
    'latitude' => $shipment->rider_latitude,
    'longitude' => $shipment->rider_longitude,
  ] : null,
  'lastKnown' => $riderLocation && $riderLocation->latitude && $riderLocation->longitude ? [
    'latitude' => $riderLocation->latitude,
    'longitude' => $riderLocation->longitude,
    'updatedLabel' => $riderLocation->updated_at->diffForHumans(),
  ] : null,
]; @endphp @json($shipmentTrackingData)</script>
@endpush


