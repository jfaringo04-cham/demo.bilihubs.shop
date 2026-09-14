@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Sales Reports</h1>
</div>

<div class="card shadow mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('seller.reports') }}" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label for="from_date" class="form-label">From Date</label>
        <input type="date" name="from_date" id="from_date" class="form-control" value="{{ request('from_date') }}">
      </div>
      <div class="col-md-3">
        <label for="to_date" class="form-label">To Date</label>
        <input type="date" name="to_date" id="to_date" class="form-control" value="{{ request('to_date') }}">
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-bili-hub"><i class="bi bi-funnel"></i> Filter</button>
        <a href="{{ route('seller.reports') }}" class="btn btn-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body">
        <h6 class="text-muted">Total Sales</h6>
        <h3 class="mb-0 text-success">&#8369;{{ number_format($totalSales, 2) }}</h3>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body">
        <h6 class="text-muted">Total Orders</h6>
        <h3 class="mb-0">{{ $totalOrders }}</h3>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body">
        <h6 class="text-muted">Delivered</h6>
        <h3 class="mb-0 text-primary">{{ $deliveredOrders }}</h3>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body">
        <h6 class="text-muted">Pending</h6>
        <h3 class="mb-0 text-warning">{{ $pendingOrders }}</h3>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body">
        <h6 class="text-muted">Processing</h6>
        <h3 class="mb-0 text-info">{{ $processingOrders }}</h3>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body">
        <h6 class="text-muted">Shipped</h6>
        <h3 class="mb-0 text-primary">{{ $shippedOrders }}</h3>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body">
        <h6 class="text-muted">Cancelled</h6>
        <h3 class="mb-0 text-danger">{{ $cancelledOrders }}</h3>
      </div>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-body">
    <h5 class="card-title mb-3">Sales Trend</h5>
    <canvas id="salesChart" height="100"></canvas>
  </div>
</div>

<div class="table-container">
  <h5 class="mb-3">Order Details</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>Items</th>
          <th>Total</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        @forelse($orders as $order)
          @php
            $sellerItems = $order->items->filter(function($item) {
              return $item->product->user_id === Auth::id();
            });
          @endphp
          @if($sellerItems->count() > 0)
            <tr>
              <td>{{ $order->order_number }}</td>
              <td>{{ $order->user->name ?? 'N/A' }}</td>
              <td>
                @foreach($sellerItems as $item)
                  <div>{{ $item->product_name }} x{{ $item->quantity }}</div>
                @endforeach
              </td>
              <td>&#8369;{{ number_format($order->total, 2) }}</td>
              <td>
                <span class="badge bg-{{ $order->statusBadgeClass() }}">
                  {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                </span>
              </td>
              <td>{{ $order->ordered_at->format('M d, Y h:i A') }}</td>
            </tr>
          @endif
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No orders found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
  const ctx = document.getElementById('salesChart');
  if (ctx) {
    const labels = @json($chartData->keys());
    const data = @json($chartData->values());
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Sales',
          data: data,
          borderColor: '#ee4d2d',
          backgroundColor: 'rgba(238, 77, 45, 0.1)',
          fill: true,
          tension: 0.3,
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: { beginAtZero: true }
        }
      }
    });
  }
</script>
@endsection

