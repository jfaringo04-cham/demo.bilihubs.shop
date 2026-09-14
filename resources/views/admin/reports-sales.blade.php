@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Sales Summary Report</h1>
  <div>
    <a href="{{ route('admin.reports.commission') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-percent"></i> Commission Report</a>
    <a href="{{ route('admin.reports.sales.export') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}" class="btn btn-bili-hub btn-sm"><i class="bi bi-download"></i> Export CSV</a>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('admin.reports.sales') }}" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-bold">From</label>
        <input type="date" name="from" value="{{ request('from') }}" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">To</label>
        <input type="date" name="to" value="{{ request('to') }}" class="form-control">
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-outline-secondary w-100"><i class="bi bi-filter"></i> Apply Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-success text-white me-3"><i class="bi bi-cash"></i></div>
        <div><h6 class="text-muted mb-1">Total Sales</h6><h3 class="mb-0">&#8369;{{ number_format($totalSales, 2) }}</h3></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-primary text-white me-3"><i class="bi bi-receipt"></i></div>
        <div><h6 class="text-muted mb-1">Total Orders</h6><h3 class="mb-0">{{ $orderCount }}</h3></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-info text-white me-3"><i class="bi bi-check2-circle"></i></div>
        <div><h6 class="text-muted mb-1">Delivered</h6><h3 class="mb-0">{{ $deliveredCount }}</h3></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-warning text-white me-3"><i class="bi bi-bar-chart"></i></div>
        <div><h6 class="text-muted mb-1">Statuses</h6><h3 class="mb-0">{{ $byStatus->count() }}</h3></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-md-6">
    <div class="table-container">
      <h5 class="mb-3">Orders by Status</h5>
      <table class="table table-hover">
        <thead class="table-light"><tr><th>Status</th><th>Count</th></tr></thead>
        <tbody>
          @forelse($byStatus as $status => $count)
            <tr><td class="text-capitalize">{{ $status }}</td><td>{{ $count }}</td></tr>
          @empty
            <tr><td colspan="2" class="text-center text-muted">No data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-md-6">
    <div class="table-container">
      <h5 class="mb-3">Top Selling Products</h5>
      <table class="table table-hover">
        <thead class="table-light"><tr><th>Product</th><th>Seller</th><th>Qty Sold</th></tr></thead>
        <tbody>
          @forelse($topProducts as $p)
            <tr>
              <td>{{ $p->name }}</td>
              <td>{{ $p->seller->name ?? 'N/A' }}</td>
              <td>{{ $p->order_items_sum_quantity ?? 0 }}</td>
            </tr>
          @empty
            <tr><td colspan="3" class="text-center text-muted">No data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="table-container mt-4">
  <h5 class="mb-3">Top Sellers</h5>
  <table class="table table-hover">
    <thead class="table-light"><tr><th>Seller</th><th>Delivered Orders</th></tr></thead>
    <tbody>
      @forelse($topSellers as $s)
        <tr><td>{{ $s->name }}</td><td>{{ $s->delivered_orders_count }}</td></tr>
      @empty
        <tr><td colspan="2" class="text-center text-muted">No data.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
