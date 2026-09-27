@extends('seller.layout')

@section('page-title', 'Dashboard')

@section('content')
<div class="dashboard-content">
<!-- Welcome Header -->
<div class="dashboard-header d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
  <div>
    <h2 class="mb-1" style="color: var(--color-text-dark);">
      Good morning, {{ Auth::user()->first_name ?? Auth::user()->name }} 👋
    </h2>
    <p class="text-muted mb-0">Here's what's happening with your store today.</p>
  </div>
  <a href="{{ route('seller.products.create') }}" class="btn btn-primary btn-lg rounded-xl px-4">
    <i class="bi bi-plus-lg me-2"></i> Add Product
  </a>
</div>

<!-- Stat Cards -->
<div class="stat-grid w-100">
  <div class="stat-card h-100">
    <div class="stat-card-body">
      <div class="stat-icon">
        <i class="bi bi-box"></i>
      </div>
      <h6 class="stat-label">Total Products</h6>
      <h3 class="stat-value">{{ $totalProducts }}</h3>
      <a href="{{ route('seller.products') }}" class="stat-link">View products →</a>
    </div>
  </div>
  <div class="stat-card h-100">
    <div class="stat-card-body">
      <div class="stat-icon">
        <i class="bi bi-receipt"></i>
      </div>
      <h6 class="stat-label">Total Orders</h6>
      <h3 class="stat-value">{{ $totalOrders }}</h3>
      <a href="{{ route('seller.orders') }}" class="stat-link">View orders →</a>
    </div>
  </div>
  <div class="stat-card h-100">
    <div class="stat-card-body">
      <div class="stat-icon">
        <i class="bi bi-currency-exchange"></i>
      </div>
      <h6 class="stat-label">Total Revenue</h6>
      <h3 class="stat-value">₱{{ number_format($totalRevenue, 2) }}</h3>
      <a href="{{ route('seller.reports') }}" class="stat-link">View reports →</a>
    </div>
  </div>
  <div class="stat-card h-100">
    <div class="stat-card-body">
      <div class="stat-icon">
        <i class="bi bi-people"></i>
      </div>
      <h6 class="stat-label">Customers</h6>
      <h3 class="stat-value">{{ $totalCustomers }}</h3>
      <a href="{{ route('seller.orders') }}" class="stat-link">View orders →</a>
    </div>
  </div>
</div>

  <!-- Analytics Row -->
 <!-- Analytics Row -->
@if($totalRevenue > 0 || $totalOrders > 0)

<div class="analytics-grid w-100 mb-4">

    <!-- Revenue Overview -->
    <div class="analytics-card h-100">
        <div class="analytics-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold" style="color: var(--color-text-dark);">
                Revenue Overview
            </h5>

            <select
                class="form-select form-select-sm"
                id="revenuePeriod"
                style="width: auto;"
            >
                <option>Last 7 Days</option>
                <option>Last 30 Days</option>
                <option>This Month</option>
                <option>This Year</option>
            </select>
        </div>

        <div class="analytics-body">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Order Status -->
    <div class="analytics-card h-100">

        <div class="analytics-header">
            <h5 class="mb-0 fw-semibold" style="color: var(--color-text-dark);">
                Order Status
            </h5>
        </div>

        <div class="analytics-body">

            <div class="d-flex align-items-center justify-content-center h-100">

                <div class="text-center w-100">

                    <canvas id="statusChart"></canvas>

                    <div class="mt-2 px-4">

                        <p class="fw-bold mb-2">{{ $totalOrders }} Orders</p>

                        @php
                            $statusLabels = [
                                'placed' => [
                                    'label' => 'Placed',
                                    'color' => '#64748b',
                                ],

                                'confirmed' => [
                                    'label' => 'Confirmed',
                                    'color' => '#3b82f6',
                                ],

                                'preparing' => [
                                    'label' => 'Preparing',
                                    'color' => '#8b5cf6',
                                ],

                                'ready_for_pickup' => [
                                    'label' => 'Ready for Pickup',
                                    'color' => '#f59e0b',
                                ],

                                'delivered' => [
                                    'label' => 'Delivered',
                                    'color' => '#22c55e',
                                ],

                                'cancelled' => [
                                    'label' => 'Cancelled',
                                    'color' => '#ef4444',
                                ],
                            ];

                            $statusCounts = $recentOrders
                                ->groupBy('status')
                                ->map
                                ->count();
                        @endphp

                        <div class="d-flex flex-column gap-2 small">

                            @foreach($statusLabels as $key => $status)

                                @php
                                    $count = $statusCounts->get($key, 0);
                                @endphp

                                @if($count > 0)

                                    <div
                                        class="d-flex align-items-center justify-content-between gap-4"
                                    >

                                        <div class="d-flex align-items-center gap-2">

                                            <span
                                                style="
                                                    width: 10px;
                                                    height: 10px;
                                                    border-radius: 50%;
                                                    background-color: {{ $status['color'] }};
                                                    display: inline-block;
                                                    flex-shrink: 0;
                                                "
                                            ></span>

                                            <span>
                                                {{ $status['label'] }}
                                            </span>

                                        </div>

                                        <span class="fw-semibold">
                                            {{ $count }}
                                        </span>

                                    </div>

                                @endif

                            @endforeach

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@else

<!-- Empty State for Analytics -->
<div class="card border-0 rounded-16 mb-4 w-100">

    <div class="card-body text-center py-5">

        <i class="bi bi-clipboard-data display-5 text-muted mb-3"></i>

        <h4
            class="fw-semibold mb-2"
            style="color: var(--color-text-dark);"
        >
            No sales data yet
        </h4>

        <p class="text-muted mb-3">
            Your revenue analytics will appear here once customers start placing orders.
        </p>

        <a
            href="{{ route('seller.products.create') }}"
            class="btn btn-primary rounded-xl"
        >
            <i class="bi bi-plus-lg me-1"></i>
            Add Your First Product
        </a>

    </div>

</div>

@endif

  <!-- Quick Actions & Inventory Alerts -->
  <div class="dashboard-grid w-100 mb-4">
    <div class="quick-actions-card h-100">
      <h5 class="fw-semibold mb-3" style="color: var(--color-text-dark);">QUICK ACTIONS</h5>
      <div class="d-flex gap-3 flex-wrap">
        <a href="{{ route('seller.products.create') }}" class="quick-action d-flex align-items-center gap-2">
          <i class="bi bi-plus-lg"></i> Add Product
        </a>
        <a href="{{ route('seller.orders') }}" class="quick-action d-flex align-items-center gap-2">
          <i class="bi bi-receipt"></i> View Orders
        </a>
        <a href="{{ route('seller.account') }}" class="quick-action d-flex align-items-center gap-2">
          <i class="bi bi-gear"></i> Store Settings
        </a>
      </div>
    </div>
    @if($lowStockProducts && $lowStockProducts->isNotEmpty())
    <div class="quick-actions-card h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0" style="color: var(--color-text-dark);">INVENTORY ALERTS</h5>
        <a href="{{ route('seller.products') }}" class="small text-decoration-none" style="color: var(--color-primary);">Manage inventory →</a>
      </div>
      <div class="d-flex flex-column gap-3">
        @foreach($lowStockProducts as $product)
          <div class="d-flex align-items-center gap-3">
            @if($product->image_path)
              <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}" class="inventory-thumb">
            @else
              <div class="inventory-thumb d-flex align-items-center justify-content-center">
                <i class="bi bi-image text-muted"></i>
              </div>
            @endif
            <div class="flex-fill">
              <h6 class="fw-semibold mb-1 small">{{ \Str::limit($product->name, 30) }}</h6>
              <span class="text-muted small">{{ $product->stock }} left</span>
            </div>
          </div>
        @endforeach
      </div>
    </div>
    @endif
  </div>

  <!-- Recent Orders -->
  <div class="card border-0 rounded-16 mb-4 w-100">
    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
      <h5 class="fw-semibold mb-0" style="color: var(--color-text-dark);">Recent Orders</h5>
      <a href="{{ route('seller.orders') }}" class="small text-decoration-none" style="color: var(--color-primary);">View All →</a>
    </div>
    <div class="card-body p-0">
      @if($recentOrders->isEmpty())
        <div class="text-center py-5">
          <i class="bi bi-truck display-5 text-muted mb-3"></i>
          <h5 class="fw-semibold mb-2" style="color: var(--color-text-dark);">No orders yet</h5>
          <p class="text-muted small">Your customer orders will appear here.</p>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col" class="text-uppercase small fw-semibold" style="color: var(--color-text-dark);">ORDER ID</th>
                <th scope="col" class="text-uppercase small fw-semibold" style="color: var(--color-text-dark);">CUSTOMER</th>
                <th scope="col" class="text-uppercase small fw-semibold" style="color: var(--color-text-dark);">PRODUCT</th>
                <th scope="col" class="text-uppercase small fw-semibold" style="color: var(--color-text-dark);">STATUS</th>
                <th scope="col" class="text-uppercase small fw-semibold" style="color: var(--color-text-dark);">TOTAL</th>
                <th scope="col" class="text-uppercase small fw-semibold" style="color: var(--color-text-dark);">DATE</th>
                <th scope="col" class="text-uppercase small fw-semibold" style="color: var(--color-text-dark);">ACTION</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentOrders as $order)
                <tr>
                  <td><span class="fw-medium small">{{ $order->order_number }}</span></td>
                  <td>{{ $order->user->name ?? 'N/A' }}</td>
                  <td>
                    @if($order->items && $order->items->isNotEmpty())
                      @php
                        $firstItem = $order->items->first();
                        $productName = $firstItem->product ? \Str::limit($firstItem->product->name, 30) : \Str::limit($firstItem->name ?? 'N/A', 30);
                      @endphp
                      {{ $productName }}
                      @if($order->items->count() > 1)
                        <span class="text-muted small">+{{ $order->items->count() - 1 }} more</span>
                      @endif
                    @else
                      <span class="text-muted">N/A</span>
                    @endif
                  </td>
                  <td>
                    <span class="badge bg-{{ $order->statusBadgeClass() }} small">
                     {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                    </span>
                  </td>
                  <td>₱{{ number_format(($order->total_minor / 100), 2) }}</td>
                  <td>{{ $order->ordered_at->format('M d, Y') }}</td>
                  <td>
                    <a href="{{ route('seller.orders.show', $order) }}" class="text-decoration-none small" style="color: var(--color-primary);">View →</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script type="application/json" id="seller-dashboard-data">@php $sellerDashboardData = [
  'statusCounts' => [
    'placed' => $recentOrders->where('status', 'placed')->count(),
    'confirmed' => $recentOrders->where('status', 'confirmed')->count(),
    'preparing' => $recentOrders->where('status', 'preparing')->count(),
    'ready_for_pickup' => $recentOrders->where('status', 'ready_for_pickup')->count(),
    'delivered' => $recentOrders->where('status', 'delivered')->count(),
    'cancelled' => $recentOrders->where('status', 'cancelled')->count(),
],
'revenueData' => $revenueData,
]; @endphp @json($sellerDashboardData)</script>
@vite('resources/js/seller/dashboard.js')
@endpush

