@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Commission Report</h1>
  <div>
    <a href="{{ route('admin.reports.sales') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bar-chart"></i> Sales Report</a>
    <a href="{{ route('admin.reports.commission.export') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}" class="btn btn-bili-hub btn-sm"><i class="bi bi-download"></i> Export CSV</a>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('admin.reports.commission') }}" class="row g-3 align-items-end">
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
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-primary text-white me-3"><i class="bi bi-cash-stack"></i></div>
        <div><h6 class="text-muted mb-1">Total Commission</h6><h3 class="mb-0">&#8369;{{ number_format($totalCommission, 2) }}</h3></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-warning text-white me-3"><i class="bi bi-hourglass-split"></i></div>
        <div><h6 class="text-muted mb-1">Pending</h6><h3 class="mb-0">&#8369;{{ number_format($totalPending, 2) }}</h3></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-success text-white me-3"><i class="bi bi-check-circle"></i></div>
        <div><h6 class="text-muted mb-1">Paid</h6><h3 class="mb-0">&#8369;{{ number_format($totalPaid, 2) }}</h3></div>
      </div>
    </div>
  </div>
</div>

<div class="table-container mb-4">
  <h5 class="mb-3">Commission by Seller</h5>
  <table class="table table-hover">
    <thead class="table-light"><tr><th>Seller</th><th>Records</th><th>Commission</th></tr></thead>
    <tbody>
      @forelse($bySeller as $row)
        <tr>
          <td>{{ $row['seller']->name ?? 'N/A' }}</td>
          <td>{{ $row['count'] }}</td>
          <td class="fw-bold text-danger">&#8369;{{ number_format($row['amount'], 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="3" class="text-center text-muted">No commission data.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="table-container">
  <h5 class="mb-3">Commission Records</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr><th>Order #</th><th>Seller</th><th>Order Total</th><th>Rate</th><th>Commission</th><th>Status</th></tr>
      </thead>
      <tbody>
        @forelse($commissions as $c)
          <tr>
            <td>{{ $c->order->order_number ?? 'N/A' }}</td>
            <td>{{ $c->seller->name ?? 'N/A' }}</td>
            <td>&#8369;{{ number_format($c->order_total, 2) }}</td>
            <td>{{ $c->rate }}%</td>
            <td class="fw-bold text-danger">&#8369;{{ number_format($c->amount, 2) }}</td>
            <td><span class="badge bg-{{ $c->status == 'paid' ? 'success' : 'warning' }} text-capitalize">{{ $c->status }}</span></td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted">No commission records.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
