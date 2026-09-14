@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Seller Dashboard</h1>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-primary text-white me-3"><i class="bi bi-box"></i></div>
        <div>
          <h6 class="text-muted mb-1">Total Products</h6>
          <h3 class="mb-0">{{ $totalProducts }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-success text-white me-3"><i class="bi bi-receipt"></i></div>
        <div>
          <h6 class="text-muted mb-1">Total Orders</h6>
          <h3 class="mb-0">{{ $totalOrders }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-warning text-white me-3"><i class="bi bi-currency-dollar"></i></div>
        <div>
          <h6 class="text-muted mb-1">Total Revenue</h6>
          <h3 class="mb-0">&#8369;{{ number_format($totalRevenue, 2) }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-info text-white me-3"><i class="bi bi-people"></i></div>
        <div>
          <h6 class="text-muted mb-1">Customers</h6>
          @php
            $customerIds = $recentOrders->pluck('user_id')->unique();
          @endphp
          <h3 class="mb-0">{{ $customerIds->count() }}</h3>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card shadow">
      <div class="card-body">
        <h5 class="card-title mb-3">Recent Orders</h5>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Total</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentOrders as $order)
                <tr>
                  <td>{{ $order->order_number }}</td>
                  <td>{{ $order->user->name ?? 'N/A' }}</td>
                   <span class="badge bg-{{ $order->statusBadgeClass() }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span></td>
                  <td>&#8369;{{ number_format($order->total, 2) }}</td>
                  <td>{{ $order->ordered_at->format('M d, Y') }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No orders yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow">
      <div class="card-body">
        <h5 class="card-title mb-3">Order Status Distribution</h5>
        <div style="height: 320px;">
          <canvas id="statusChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-12">
    <div class="card shadow">
      <div class="card-body">
        <h5 class="card-title mb-3">Revenue Trend</h5>
        <div style="height: 300px;">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
  const statusCtx = document.getElementById('statusChart');
  if (statusCtx) {
    const delivered = {{ $recentOrders->where('status', 'delivered')->count() }};
    const processing = {{ $recentOrders->where('status', 'processing')->count() }};
    const pending = {{ $recentOrders->where('status', 'pending')->count() }};
    const shipped = {{ $recentOrders->where('status', 'shipped')->count() }};
    const cancelled = {{ $recentOrders->where('status', 'cancelled')->count() }};
    
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: ['Delivered', 'Processing', 'Pending', 'Shipped', 'Cancelled'],
        datasets: [{
          data: [delivered, processing, pending, shipped, cancelled],
          backgroundColor: ['#198754', '#0dcaf0', '#ffc107', '#0d6efd', '#dc3545'],
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      }
    });
  }

  const revenueCtx = document.getElementById('revenueChart');
  if (revenueCtx) {
    const revenueData = @json($recentOrders->groupBy(function($order) {
      return $order->ordered_at->format('M d, Y');
    })->map->sum('total')->sortKeys());
    
    new Chart(revenueCtx, {
      type: 'bar',
      data: {
        labels: revenueData.keys(),
        datasets: [{
          label: 'Revenue',
          data: revenueData.values(),
          backgroundColor: '#ee4d2d',
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true }
        }
      }
    });
  }
</script>
@endsection

