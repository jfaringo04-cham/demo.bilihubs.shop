@extends('layouts.app')

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
<script>
document.addEventListener('DOMContentLoaded', function() {
    var map = L.map('map').setView([14.5995, 120.9842], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var markers = [];

    @if($shipment->latitude && $shipment->longitude)
        var pickupMarker = L.marker([{{ $shipment->latitude }}, {{ $shipment->longitude }}])
            .addTo(map)
            .bindPopup('<b>Shipment Location</b><br>{{ $shipment->pickup_address }}');
        markers.push(pickupMarker);
    @endif

    @if($shipment->rider_latitude && $shipment->rider_longitude)
        var riderMarker = L.marker([{{ $shipment->rider_latitude }}, {{ $shipment->rider_longitude }}], {
            icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            })
        })
            .addTo(map)
            .bindPopup('<b>Rider Location</b><br>Last known position');
        markers.push(riderMarker);
    @endif

    @if($riderLocation && $riderLocation->latitude && $riderLocation->longitude)
        var lastLocationMarker = L.marker([{{ $riderLocation->latitude }}, {{ $riderLocation->longitude }}], {
            icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            })
        })
            .addTo(map)
            .bindPopup('<b>Rider Last Known Location</b><br>Updated: {{ $riderLocation->updated_at->diffForHumans() }}');
        markers.push(lastLocationMarker);
    @endif

    if (markers.length > 1) {
        var group = new L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));
    }
});
</script>
@endpush
