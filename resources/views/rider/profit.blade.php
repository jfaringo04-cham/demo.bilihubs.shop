@extends('rider.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Profit Dashboard</h1>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-success text-white me-3"><i class="bi bi-check-circle"></i></div>
        <div>
          <h6 class="text-muted mb-1">Total Delivered</h6>
          <h3 class="mb-0">{{ $totalDelivered }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-primary text-white me-3"><i class="bi bi-currency-dollar"></i></div>
        <div>
          <h6 class="text-muted mb-1">Total Earnings</h6>
          <h3 class="mb-0">₱{{ number_format($totalEarnings, 2) }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-warning text-white me-3"><i class="bi bi-cash-stack"></i></div>
        <div>
          <h6 class="text-muted mb-1">Avg. Earnings</h6>
          <h3 class="mb-0">
            @if($totalDelivered > 0)
              ₱{{ number_format($totalEarnings / $totalDelivered, 2) }}
            @else
              ₱0.00
            @endif
          </h3>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-12">
    <div class="table-container">
      <h5 class="mb-3">Earnings Trend</h5>
      @if($chartData->count() > 0)
        <canvas id="profitChart" height="80"></canvas>
      @else
        <p class="text-muted text-center py-4">No delivery data available for the selected period.</p>
      @endif
    </div>
  </div>
</div>

<div class="table-container">
  <h5 class="mb-3">Delivery Details</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>Address</th>
          <th>Contact</th>
          <th>Zone</th>
          <th>Distance</th>
          <th>Delivered At</th>
          <th>Earnings</th>
        </tr>
      </thead>
      <tbody>
        @forelse($deliveries as $order)
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
            <td>{{ Str::limit($order->shipping_address, 40) }}</td>
            <td>{{ $order->user->phone ?? 'N/A' }}</td>
            <td>
              <span class="badge bg-info">{{ $order->delivery_zone ?? 'N/A' }}</span>
            </td>
            <td>
              @if($distance)
                {{ number_format($distance, 1) }} km
              @else
                N/A
              @endif
            </td>
            <td>{{ $order->delivered_at ? $order->delivered_at->format('M d, Y H:i') : 'N/A' }}</td>
            <td>₱{{ number_format($order->amount_collected ?: 0, 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">No delivered orders found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

@push('scripts')
@if($chartData->count() > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('profitChart').getContext('2d');
    var labels = {!! json_encode($chartData->keys()) !!};
    var data = {!! json_encode($chartData->values()) !!};

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Deliveries',
          data: data,
          backgroundColor: 'rgba(78, 115, 223, 0.5)',
          borderColor: 'rgba(78, 115, 223, 1)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            ticks: { stepSize: 1 }
          }
        }
      }
    });
  });
</script>
@endif
@endpush


