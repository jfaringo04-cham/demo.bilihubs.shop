@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Platform Commissions (10%)</h1>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-primary text-white me-3"><i class="bi bi-cash-stack"></i></div>
        <div>
          <h6 class="text-muted mb-1">Total Earned</h6>
          <h3 class="mb-0">&#8369;{{ number_format($totalEarned, 2) }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-warning text-white me-3"><i class="bi bi-hourglass-split"></i></div>
        <div>
          <h6 class="text-muted mb-1">Pending</h6>
          <h3 class="mb-0">&#8369;{{ number_format($totalPending, 2) }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-success text-white me-3"><i class="bi bi-check-circle"></i></div>
        <div>
          <h6 class="text-muted mb-1">Paid</h6>
          <h3 class="mb-0">&#8369;{{ number_format($totalPaid, 2) }}</h3>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('admin.commissions.index') }}" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-bold">Seller</label>
        <select name="seller" class="form-select" onchange="this.form.submit()">
          <option value="">All Sellers</option>
          @foreach($sellers as $s)
            <option value="{{ $s->id }}" {{ request('seller') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">Status</label>
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">All</option>
          <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
          <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
        </select>
      </div>
    </form>
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Seller</th>
          <th>Order Total</th>
          <th>Rate</th>
          <th>Commission</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
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
            <td>
              @if($c->status == 'pending')
                <form method="POST" action="{{ route('admin.commissions.pay', $c) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Mark this commission as paid?')">
                    <i class="bi bi-cash"></i> Mark Paid
                  </button>
                </form>
              @else
                <span class="text-muted small">{{ $c->paid_at?->format('M d, Y') }}</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No commissions yet. Delivered orders generate commissions.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $commissions->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
