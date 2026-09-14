@extends('rider.layout')

@section('content')
<div class="container-fluid px-4 mx-auto" style="max-width: 1400px;">
  <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 mb-0">Rider Dashboard</h1>
    <div class="d-flex gap-2">
      <a href="{{ route('rider.deliveries') }}" class="btn btn-bili-hub btn-sm">
        <i class="bi bi-bell"></i> Delivery Notifications
      </a>
      <a href="{{ route('rider.pickups') }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-box-seam"></i> View Pickups
      </a>
    </div>
  </div>

  @if($logistic)
  <div class="table-container mb-4">
    <h5 class="mb-3">My Logistics Company</h5>
    <div class="d-flex align-items-center">
      <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;">
        {{ substr($logistic->company_name, 0, 1) }}
      </div>
      <div>
        <h5 class="mb-1">{{ $logistic->company_name }}</h5>
        <p class="mb-0 text-muted small">
          @if($rider->logistic_status === 'approved')
            <span class="badge bg-success">Approved</span> Approved on {{ $rider->logistic_approved_at?->format('M d, Y') ?? '—' }}
          @elseif($rider->logistic_status === 'rejected')
            <span class="badge bg-danger">Rejected</span> Application declined
          @else
            <span class="badge bg-warning text-dark">Pending</span> Waiting for logistics approval
          @endif
        </p>
      </div>
    </div>
  </div>
  @endif

  <div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-primary text-white me-3"><i class="bi bi-box"></i></div>
        <div>
          <h6 class="text-muted mb-1">Assigned Orders</h6>
          <h3 class="mb-0">{{ $assignedOrders->total() }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-success text-white me-3"><i class="bi bi-check-circle"></i></div>
        <div>
          <h6 class="text-muted mb-1">Completed Today</h6>
          <h3 class="mb-0">{{ $completedToday }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-warning text-white me-3"><i class="bi bi-trophy"></i></div>
        <div>
          <h6 class="text-muted mb-1">Total Delivered</h6>
          <h3 class="mb-0">{{ $totalDelivered }}</h3>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-8">
    <div class="table-container">
      <h5 class="mb-3">Assigned Orders</h5>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-light">
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Address</th>
              <th>Zone</th>
              <th>Ready</th>
              <th>Distance</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($assignedOrders as $order)
              @php
                $distance = null;
                if ($order->customer_latitude && $order->customer_longitude && Auth::user()->latitude && Auth::user()->longitude) {
                  $distance = $order->calculateDistance(
                    Auth::user()->latitude,
                    Auth::user()->longitude,
                    $order->customer_latitude,
                    $order->customer_longitude
                  );
                }
              @endphp
              <tr>
                <td>{{ $order->order_number }}</td>
                <td>{{ $order->user->name ?? 'N/A' }}</td>
                <td>
                  <a href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($order->shipping_address) }}" target="_blank" class="text-decoration-none">
                    {{ Str::limit($order->shipping_address, 30) }}
                  </a>
                </td>
                <td>
                  <span class="badge bg-info">{{ $order->delivery_zone ?? 'N/A' }}</span>
                </td>
                <td>
                  @if($order->ready_for_pickup)
                    <span class="badge bg-success">Ready</span>
                  @else
                    <span class="badge bg-secondary">Pending</span>
                  @endif
                </td>
                <td>
                  @if($distance)
                    {{ number_format($distance, 1) }} km
                  @else
                    N/A
                  @endif
                </td>
                <td>
                  <span class="badge bg-{{ $order->delivery_status == 'out_for_delivery' || $order->delivery_status == 'picked_up_from_sorting_center' ? 'primary' : ($order->delivery_status == 'delivered' ? 'success' : ($order->delivery_status == 'delivered_to_sorting_center' ? 'info' : 'warning')) }}">
                    {{ ucfirst(str_replace('_', ' ', $order->delivery_status)) }}
                  </span>
                </td>
                <td>
                  <a href="{{ route('rider.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-4">No assigned orders.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="table-container">
      <h5 class="mb-3">Delivery Route</h5>
      <div id="map"></div>
    </div>
  </div>
</div>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    @if(config('services.googlemaps.key') && $assignedOrders->count() > 0)
      var map = new google.maps.Map(document.getElementById('map'), {
        zoom: 12,
        center: { lat: 14.5995, lng: 120.9842 }
      });

      var geocoder = new google.maps.Geocoder();

      @foreach($assignedOrders as $order)
        @if($order->shipping_address)
          geocoder.geocode({ address: '{{ $order->shipping_address }}' }, function(results, status) {
            if (status == 'OK') {
              new google.maps.Marker({
                map: map,
                position: results[0].geometry.location,
                title: '{{ $order->user->name ?? 'Customer' }}'
              });
            }
          });
        @endif
      @endforeach
    @elseif($assignedOrders->count() > 0)
      var map = L.map('map').setView([14.5995, 120.9842], 12);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
      }).addTo(map);

      @foreach($assignedOrders as $order)
        @if($order->shipping_address)
          L.marker([14.5995, 120.9842]).addTo(map)
            .bindPopup('{{ $order->user->name ?? 'Customer' }}<br>{{ $order->shipping_address }}');
        @endif
      @endforeach
    @endif
  });
</script>
@endpush


