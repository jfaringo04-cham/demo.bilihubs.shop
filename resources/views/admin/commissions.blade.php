@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Platform Commission Records (10%)</h1>
</div>

{{-- COMMISSION SUMMARY --}}
<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-primary text-white me-3">
          <i class="bi bi-cash-stack"></i>
        </div>
        <div>
          <h6 class="text-muted mb-1">Recorded Commission Obligations</h6>
          <h3 class="mb-0">&#8369;{{ number_format($totalEarned, 2) }}</h3>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-warning text-white me-3">
          <i class="bi bi-hourglass-split"></i>
        </div>
        <div>
          <h6 class="text-muted mb-1">Pending Commission Records</h6>
          <h3 class="mb-0">&#8369;{{ number_format($totalPending, 2) }}</h3>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-success text-white me-3">
          <i class="bi bi-check-circle"></i>
        </div>
        <div>
          <h6 class="text-muted mb-1">Paid-Status Commission Records</h6>
          <h3 class="mb-0">&#8369;{{ number_format($totalPaid, 2) }}</h3>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- FILTERS --}}
<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('admin.commissions.index') }}" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-bold">Seller</label>
        <select name="seller" class="form-select" onchange="this.form.submit()">
          <option value="">All Sellers</option>
          @foreach($sellers as $s)
          <option value="{{ $s->id }}" {{ request('seller') == $s->id ? 'selected' : '' }}>
            {{ $s->seller?->name ?? $s->name }}
          </option>
          @endforeach
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label fw-bold">Record Status</label>
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>
            Pending
          </option>
          <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>
            Paid-Status
          </option>
        </select>
      </div>
    </form>
  </div>
</div>

{{-- COMMISSION RECORDS --}}
<div class="table-container">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Seller</th>
          <th>Commission Basis</th>
          <th>Rate</th>
          <th>Commission Amount</th>
          <th>Record Status</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        @forelse($commissions as $c)
        <tr>
          <td>{{ $c->order->order_number ?? 'N/A' }}</td>

          <td>
            {{ $c->seller?->seller?->name ?? $c->seller?->name ?? 'N/A' }}
          </td>

          <td>
            &#8369;{{ number_format($c->order_total_minor / 100, 2) }}
          </td>

          <td>{{ $c->rate }}%</td>

          <td class="fw-bold text-danger">
            &#8369;{{ number_format($c->amount_minor / 100, 2) }}
          </td>

          <td>
            <span class="badge bg-{{ $c->status === 'paid' ? 'success' : 'warning' }} text-capitalize">
              {{ $c->status === 'paid' ? 'Paid-Status' : 'Pending' }}
            </span>
          </td>

          <td>
            @if($c->status === 'pending')
            <form method="POST"
              action="{{ route('admin.commissions.pay', $c) }}"
              class="d-inline">
              @csrf

              <button type="submit"
                class="btn btn-sm btn-success"
                onclick="return confirm('Change this commission record status to paid? This does not execute a seller bank transfer or withdrawal.')">
                <i class="bi bi-check-circle"></i>
                Mark Paid-Status
              </button>
            </form>
            @else
            <span class="text-muted small">
              Status updated:
              {{ $c->paid_at?->format('M d, Y') ?? 'Date unavailable' }}
            </span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="text-center text-muted py-4">
            <i class="bi bi-inbox"></i>
            No commissions yet. Completed sales generate commission records.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $commissions->links('pagination::bootstrap-5') }}
  </div>
</div>

<div class="alert alert-info mt-4">
  <i class="bi bi-info-circle me-2"></i>
  Changing a commission record to Paid-Status only updates its administrative
  record status and timestamp. It does not transfer funds to the seller.
  Seller settlement approval and seller withdrawal processing are separate workflows.
</div>
@endsection