@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
  <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">{{ $logistic->company_name }} Dashboard</h1>
    <p class="text-muted mb-0">Manage shipments, riders, and hubs</p>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('logistic.shipments.create') }}" class="btn btn-primary btn-sm rounded-xl">
      <i class="bi bi-plus-circle me-1"></i> New Shipment
    </a>
    <a href="{{ route('logistic.riders.register') }}" class="btn btn-secondary btn-sm rounded-xl">
      <i class="bi bi-person-plus me-1"></i> Register Rider
    </a>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-sky-50 text-sky-500 me-3"><i class="bi bi-box"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Total Shipments</h6>
          <h3 class="mb-0 fw-bold">{{ $logistic->shipments()->count() }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-amber-50 text-amber-500 me-3"><i class="bi bi-clock"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Pending</h6>
          <h3 class="mb-0 fw-bold">{{ $logistic->shipments()->where('status', 'pending')->count() }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-emerald-50 text-emerald-500 me-3"><i class="bi bi-truck"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">In Transit</h6>
          <h3 class="mb-0 fw-bold">{{ $logistic->shipments()->whereIn('status', ['assigned', 'picked_up', 'in_transit'])->count() }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-violet-50 text-violet-500 me-3"><i class="bi bi-check-circle"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Delivered</h6>
          <h3 class="mb-0 fw-bold">{{ $logistic->shipments()->where('status', 'delivered')->count() }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-rose-50 text-rose-500 me-3"><i class="bi bi-x-circle"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Cancelled</h6>
          <h3 class="mb-0 fw-bold">{{ $logistic->shipments()->where('status', 'cancelled')->count() }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-sky-50 text-sky-500 me-3"><i class="bi bi-person-check"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Active Riders</h6>
          <h3 class="mb-0 fw-bold">{{ $activeRiders }}</h3>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-warning text-white me-3"><i class="bi bi-exclamation-triangle"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Delayed</h6>
          <h3 class="mb-0 fw-bold">{{ $logistic->shipments()->where('status', 'delayed')->count() }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-info text-white me-3"><i class="bi bi-bell"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Pending Riders</h6>
          <h3 class="mb-0 fw-bold">{{ $pendingRiders }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100 border-danger">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-danger text-white me-3"><i class="bi bi-bell"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Alerts</h6>
          <h3 class="mb-0 fw-bold">{{ $alerts->count() }}</h3>
        </div>
      </div>
    </div>
  </div>
</div>

@if($alerts->count() > 0)
<div class="row mb-4">
  <div class="col-12">
    <div class="table-container border-warning">
      <div class="p-4 border-bottom border-warning">
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle text-warning"></i>
            <h5 class="mb-0 fw-semibold">Alerts & Notifications</h5>
            <span class="badge bg-warning">{{ $alerts->count() }} Active</span>
          </div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="border-0">Status</th>
              <th class="border-0">Tracking #</th>
              <th class="border-0">Route</th>
              <th class="border-0">Updated</th>
              <th class="border-0"></th>
            </tr>
          </thead>
          <tbody>
            @foreach($alerts as $alert)
              <tr>
                <td>
                  <span class="badge bg-{{ $alert->status == 'cancelled' ? 'danger' : 'warning' }}">
                    {{ ucfirst($alert->status) }}
                  </span>
                </td>
                <td class="fw-medium">{{ $alert->tracking_number }}</td>
                <td class="text-muted">{{ Str::limit($alert->pickup_address, 30) }} &rarr; {{ Str::limit($alert->delivery_address, 30) }}</td>
                <td class="text-muted">
                  {{ $alert->created_at->diffForHumans() }}
                  @if($alert->status == 'pending' && $alert->created_at->lt(now()->subHours(24)))
                    <span class="text-danger d-block">(Pending >24h)</span>
                  @endif
                </td>
                <td>
                  <a href="{{ route('logistic.shipments.show', $alert) }}" class="btn btn-primary btn-sm rounded-xl">View</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endif

<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm stat-card h-100">
      <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold">Rider Management</h5>
        <a href="{{ route('logistic.riders') }}" class="btn btn-sm btn-secondary rounded-xl">View All</a>
      </div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <a href="{{ route('logistic.riders.register') }}" class="btn btn-primary rounded-xl">
            <i class="bi bi-person-plus me-1"></i> Add New Rider
          </a>
          <a href="{{ route('logistic.applications') }}" class="btn btn-warning rounded-xl">
            <i class="bi bi-clock me-1"></i> Pending Applications ({{ $pendingRiders }})
          </a>
          <a href="{{ route('logistic.riders') }}" class="btn btn-secondary rounded-xl">
            <i class="bi bi-people me-1"></i> Manage Riders
          </a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm stat-card h-100">
      <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold">Shipment Management</h5>
        <a href="{{ route('logistic.shipments') }}" class="btn btn-sm btn-secondary rounded-xl">View All</a>
      </div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <a href="{{ route('logistic.shipments.create') }}" class="btn btn-primary rounded-xl">
            <i class="bi bi-plus-circle me-1"></i> Create Shipment
          </a>
          <a href="{{ route('logistic.shipments') }}" class="btn btn-secondary rounded-xl">
            <i class="bi bi-box me-1"></i> All Shipments
          </a>
          <a href="{{ route('logistic.sorting-area') }}" class="btn btn-secondary rounded-xl">
            <i class="bi bi-sort-numeric-down me-1"></i> Sorting Center
          </a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm stat-card h-100">
      <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold">Hub Management</h5>
        <a href="{{ route('logistic.hubs') }}" class="btn btn-sm btn-secondary rounded-xl">View All</a>
      </div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <a href="{{ route('logistic.hubs.create') }}" class="btn btn-primary rounded-xl">
            <i class="bi bi-plus-circle me-1"></i> Create Hub
          </a>
          <a href="{{ route('logistic.hubs') }}" class="btn btn-secondary rounded-xl">
            <i class="bi bi-geo-alt me-1"></i> All Hubs
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
    <h5 class="mb-0 fw-semibold">Recent Shipments</h5>
    <a href="{{ route('logistic.shipments') }}" class="btn btn-sm btn-secondary rounded-xl">View All</a>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Tracking #</th>
          <th class="border-0">Rider</th>
          <th class="border-0">Pickup Address</th>
          <th class="border-0">Delivery Address</th>
          <th class="border-0">Status</th>
          <th class="border-0">Created</th>
          <th class="border-0"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($logistic->shipments()->latest()->take(5)->get() as $shipment)
          <tr>
            <td><code>{{ $shipment->tracking_number }}</code></td>
            <td class="text-muted">{{ $shipment->rider->name ?? 'Unassigned' }}</td>
            <td class="text-muted">{{ Str::limit($shipment->pickup_address, 30) }}</td>
            <td class="text-muted">{{ Str::limit($shipment->delivery_address, 30) }}</td>
            <td>
              @if($shipment->status == 'delivered')
                <span class="badge bg-success">Delivered</span>
              @elseif($shipment->status == 'in_transit')
                <span class="badge bg-primary">In Transit</span>
              @elseif($shipment->status == 'picked_up')
                <span class="badge bg-info">Picked Up</span>
              @elseif($shipment->status == 'assigned')
                <span class="badge bg-warning">Assigned</span>
              @elseif($shipment->status == 'pending')
                <span class="badge bg-secondary">Pending</span>
              @elseif($shipment->status == 'cancelled')
                <span class="badge bg-danger">Cancelled</span>
              @elseif($shipment->status == 'delayed')
                <span class="badge bg-danger">Delayed</span>
              @endif
            </td>
            <td class="text-muted">{{ $shipment->created_at->format('M d, Y') }}</td>
            <td>
              <a href="{{ route('logistic.shipments.show', $shipment) }}" class="btn btn-primary btn-sm rounded-xl">View</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No shipments yet. Create your first shipment!</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
</div>
@endsection
