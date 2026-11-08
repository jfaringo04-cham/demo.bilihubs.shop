@extends('rider.layout')

@section('content')
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">COD Collection Summary</h1>
</div>

<div class="table-container mb-4">
  <form method="GET" action="{{ route('rider.profit') }}" class="row g-3 align-items-end">
    <div class="col-md-4">
      <label class="form-label">From Date</label>
      <input type="date" name="from_date"
        value="{{ request('from_date') }}"
        class="form-control">
    </div>

    <div class="col-md-4">
      <label class="form-label">To Date</label>
      <input type="date" name="to_date"
        value="{{ request('to_date') }}"
        class="form-control">
    </div>

    <div class="col-md-4">
      <button type="submit" class="btn btn-primary w-100">
        Apply Filter
      </button>
    </div>
  </form>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body">
        <h6 class="text-muted">Total Delivered</h6>
        <h3>{{ $totalDelivered }}</h3>
        <small class="text-muted">Delivered shipments</small>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body">
        <h6 class="text-muted">Total COD Collected</h6>
        <h3>₱{{ number_format($totalCollected, 2) }}</h3>
        <small class="text-muted">Confirmed COD payments</small>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body">
        <h6 class="text-muted">COD Collections</h6>
        <h3>{{ $collectionCount }}</h3>
        <small class="text-muted">Payment records</small>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card stat-card">
      <div class="card-body">
        <h6 class="text-muted">Average Collection</h6>
        <h3>₱{{ number_format($averageCollection, 2) }}</h3>
        <small class="text-muted">Per COD payment</small>
      </div>
    </div>
  </div>
</div>

<div class="table-container mb-4">
  <h5 class="mb-3">Daily COD Collection Trend</h5>

  @if($chartData->isNotEmpty())
  <canvas id="profitChart" height="80"></canvas>
  @else
  <p class="text-muted text-center py-4">
    No COD collections found for the selected period.
  </p>
  @endif
</div>

<div class="table-container">
  <h5 class="mb-3">Collection Details</h5>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Tracking #</th>
          <th>Order #</th>
          <th>Customer</th>
          <th>Collected At</th>
          <th>Payment Method</th>
          <th>Status</th>
          <th>Amount</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        @forelse($collections as $payment)
        @php
        $shipment = $payment->shipment;
        $order = $shipment?->sellerOrder?->order;
        @endphp

        <tr>
          <td>
            {{ $shipment?->tracking_number ?? 'N/A' }}
          </td>

          <td>
            {{ $order?->order_number ?? 'N/A' }}
          </td>

          <td>
            {{ $order?->user?->name ?? 'N/A' }}
          </td>

          <td>
            {{ $payment->paid_at?->format('M d, Y H:i') ?? 'N/A' }}
          </td>

          <td>
            <span class="badge bg-info">COD</span>
          </td>

          <td>
            <span class="badge bg-success">Paid</span>
          </td>

          <td>
            ₱{{ number_format($payment->amount_minor / 100, 2) }}
          </td>

          <td>
            @if($shipment)
            <a href="{{ route('rider.shipments.show', $shipment) }}"
              class="btn btn-sm btn-outline-primary">
              View
            </a>
            @else
            <span class="text-muted">N/A</span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8" class="text-center text-muted py-4">
            No COD collections found.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection


@push('scripts')
@if($chartData->isNotEmpty())
    @php
        $riderProfitChartData = [
            'labels' => $chartData->keys()->values(),
            'values' => $chartData->values(),
        ];
    @endphp

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <script type="application/json" id="rider-profit-data">
        @json($riderProfitChartData)
    </script>

    @vite('resources/js/rider/profit.js')
@endif
@endpush
