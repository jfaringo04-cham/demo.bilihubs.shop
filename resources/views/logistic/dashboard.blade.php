@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
  <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">{{ $logistic->company_name }} Dashboard</h1>
    <p class="text-muted mb-0">Manage your delivery operations</p>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('logistic.riders.register') }}" class="btn btn-primary btn-sm rounded-xl">
      <i class="bi bi-person-plus me-1"></i> Register Rider
    </a>
    <a href="{{ route('logistic.account') }}" class="btn btn-secondary btn-sm rounded-xl">
      <i class="bi bi-gear me-1"></i> Settings
    </a>
  </div>
</div>

<div class="alert alert-light border rounded-2 mb-4">
  <div class="d-flex align-items-center gap-3">
    <div class="stat-icon bg-sky-50 text-sky-500"><i class="bi bi-truck"></i></div>
    <div>
      <h5 class="mb-1 fw-semibold">{{ $logistic->company_name }}</h5>
      <p class="mb-0 text-muted">
        <strong>{{ $logistic->company_name }}</strong> is a dedicated delivery partner of Bili Hub. 
        We specialize in fast, reliable, and secure delivery services for all kinds of goods.
        Contact us at <strong>{{ $logistic->email ?? 'N/A' }}</strong> or <strong>{{ $logistic->phone ?? 'N/A' }}</strong>.
      </p>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-sky-50 text-sky-500 me-3"><i class="bi bi-truck"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Total Riders</h6>
          <h3 class="mb-0 fw-bold">{{ $totalRiders }}</h3>
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
          <h3 class="mb-0 fw-bold">{{ $pendingRiders }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-emerald-50 text-emerald-500 me-3"><i class="bi bi-check-circle"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Active Riders</h6>
          <h3 class="mb-0 fw-bold">{{ $activeRiders }}</h3>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white border-0 py-3">
    <div class="d-flex justify-content-between align-items-center">
      <h5 class="mb-0 fw-semibold">Company Information</h5>
      <div>
        @if($logistic->status == 'active')
          <span class="badge bg-success">Active</span>
        @elseif($logistic->status == 'pending')
          <span class="badge bg-warning">Pending Approval</span>
        @elseif($logistic->status == 'suspended')
          <span class="badge bg-danger">Suspended</span>
        @else
          <span class="badge bg-secondary">Rejected</span>
        @endif
      </div>
    </div>
  </div>
  <div class="card-body">
    <div class="row g-3">
      @if($logistic->logo)
        <div class="col-12">
          <img src="{{ asset('storage/' . $logistic->logo) }}" alt="{{ $logistic->company_name }} Logo" class="img-thumbnail rounded" style="max-height: 100px;">
        </div>
      @endif
      <div class="col-md-6">
        <label class="form-label text-muted">Contact Person</label>
        <p class="fw-semibold mb-0">{{ $logistic->contact_person ?? 'N/A' }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">Email</label>
        <p class="fw-semibold mb-0">{{ $logistic->email ?? 'N/A' }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">Phone</label>
        <p class="fw-semibold mb-0">{{ $logistic->phone ?? 'N/A' }}</p>
      </div>
      <div class="col-12">
        <label class="form-label text-muted">Address</label>
        <p class="fw-semibold mb-0">{{ $logistic->address ?? 'N/A' }}</p>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
    <h5 class="mb-0 fw-semibold">Recent Riders</h5>
    <div class="d-flex gap-2">
      <a href="{{ route('logistic.riders.register') }}" class="btn btn-primary btn-sm rounded-xl">
        <i class="bi bi-person-plus me-1"></i> Register Rider
      </a>
      <a href="{{ route('logistic.riders') }}" class="btn btn-secondary btn-sm rounded-xl">View All</a>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Name</th>
          <th class="border-0">Email</th>
          <th class="border-0">Vehicle Type</th>
          <th class="border-0">Status</th>
          <th class="border-0"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($recentRiders as $rider)
          <tr>
            <td class="fw-medium">{{ $rider->name }}</td>
            <td class="text-muted">{{ $rider->email }}</td>
            <td class="text-muted">{{ $rider->vehicle_type ?? 'N/A' }}</td>
            <td>
              @if($rider->logistic_status == 'approved')
                <span class="badge bg-success">Approved</span>
              @elseif($rider->logistic_status == 'pending')
                <span class="badge bg-warning">Pending</span>
              @else
                <span class="badge bg-danger">Rejected</span>
              @endif
            </td>
            <td>
              <a href="{{ route('logistic.riders.show', $rider) }}" class="btn btn-primary btn-sm rounded-xl">View</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4">No riders yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
    <h5 class="mb-0 fw-semibold">Hub Management</h5>
    <a href="{{ route('logistic.hubs.create') }}" class="btn btn-primary btn-sm rounded-xl">
      <i class="bi bi-plus-circle me-1"></i> Create Hub
    </a>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Hub Name</th>
          <th class="border-0">Address</th>
          <th class="border-0">Riders</th>
          <th class="border-0">Status</th>
          <th class="border-0"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($logistic->hubs()->latest()->take(5)->get() as $hub)
          <tr>
            <td class="fw-medium">{{ $hub->name }}</td>
            <td class="text-muted">{{ $hub->address }}</td>
            <td><span class="badge bg-info">{{ $hub->riders->count() }} riders</span></td>
            <td>
              @if($hub->status == 'active')
                <span class="badge bg-success">Active</span>
              @else
                <span class="badge bg-secondary">Inactive</span>
              @endif
            </td>
            <td>
              <a href="{{ route('logistic.hubs.show', $hub) }}" class="btn btn-primary btn-sm rounded-xl">Manage</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4">No hubs yet. Create your first hub to get started.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
</div>
@endsection
