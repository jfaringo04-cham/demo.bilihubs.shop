@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Logistics Reports</h1>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-3">
    <div class="card border-0 shadow-sm stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-primary text-white me-3"><i class="bi bi-box"></i></div>
        <div>
          <h6 class="text-muted mb-1">Total Shipments</h6>
          <h3 class="mb-0">{{ $totalShipments }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-success text-white me-3"><i class="bi bi-check-circle"></i></div>
        <div>
          <h6 class="text-muted mb-1">Delivered</h6>
          <h3 class="mb-0">{{ $deliveredShipments }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-danger text-white me-3"><i class="bi bi-x-circle"></i></div>
        <div>
          <h6 class="text-muted mb-1">Cancelled</h6>
          <h3 class="mb-0">{{ $cancelledShipments }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-warning text-white me-3"><i class="bi bi-exclamation-triangle"></i></div>
        <div>
          <h6 class="text-muted mb-1">Delayed</h6>
          <h3 class="mb-0">{{ $delayedShipments }}</h3>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-3">
    <div class="card border-0 shadow-sm stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-info text-white me-3"><i class="bi bi-clock"></i></div>
        <div>
          <h6 class="text-muted mb-1">Pending</h6>
          <h3 class="mb-0">{{ $pendingShipments }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-success text-white me-3"><i class="bi bi-graph-up"></i></div>
        <div>
          <h6 class="text-muted mb-1">Overall Success Rate</h6>
          <h3 class="mb-0">{{ $successRate }}%</h3>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0">Logistics Company Performance</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Company</th>
                <th>Total Shipments</th>
                <th>Delivered</th>
                <th>Cancelled</th>
                <th>Delayed</th>
                <th>Success Rate</th>
              </tr>
            </thead>
            <tbody>
              @forelse($logisticsPerformance as $logistic)
                <tr>
                  <td>{{ $logistic->company_name }}</td>
                  <td>{{ $logistic->total_count }}</td>
                  <td>{{ $logistic->delivered_count }}</td>
                  <td>{{ $logistic->cancelled_count }}</td>
                  <td>{{ $logistic->delayed_count }}</td>
                  <td>
                    <span class="badge bg-{{ $logistic->success_rate >= 80 ? 'success' : ($logistic->success_rate >= 50 ? 'warning' : 'danger') }}">
                      {{ $logistic->success_rate }}%
                    </span>
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No logistics data available.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-md-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0">Daily Reports (Last 30 Days)</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Total</th>
                <th>Delivered</th>
                <th>Cancelled</th>
                <th>Delayed</th>
                <th>Success Rate</th>
              </tr>
            </thead>
            <tbody>
              @forelse($dailyReports as $report)
                <tr>
                  <td>{{ \Carbon\Carbon::parse($report->date)->format('M d, Y') }}</td>
                  <td>{{ $report->total }}</td>
                  <td>{{ $report->delivered }}</td>
                  <td>{{ $report->cancelled }}</td>
                  <td>{{ $report->delayed }}</td>
                  <td>
                    @php
                      $rate = $report->total > 0 ? round(($report->delivered / $report->total) * 100, 1) : 0;
                    @endphp
                    <span class="badge bg-{{ $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger') }}">
                      {{ $rate }}%
                    </span>
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No daily reports available.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
