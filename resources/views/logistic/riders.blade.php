@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
  <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">Riders Management</h1>
    <p class="text-muted mb-0">Manage your courier team</p>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('logistic.riders.register') }}" class="btn btn-primary btn-sm rounded-xl">
      <i class="bi bi-person-plus me-1"></i> Register Rider
    </a>
    <a href="{{ route('logistic.applications') }}" class="btn btn-warning btn-sm rounded-xl">
      <i class="bi bi-clock me-1"></i> Pending Applications ({{ $logistic->riders()->where('logistic_status', 'pending')->count() }})
    </a>
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Rider Name</th>
          <th class="border-0">Email</th>
          <th class="border-0">Phone</th>
          <th class="border-0">Vehicle Type</th>
          <th class="border-0">License</th>
          <th class="border-0">Status</th>
          <th class="border-0"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($riders as $rider)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <div class="bg-sky-100 text-sky-600 rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; font-size: 0.875rem;">
                  {{ substr($rider->name, 0, 1) }}
                </div>
                <span class="fw-semibold">{{ $rider->name }}</span>
              </div>
            </td>
            <td class="text-muted">{{ $rider->email }}</td>
            <td class="text-muted">{{ $rider->phone ?? 'N/A' }}</td>
            <td class="text-muted">{{ $rider->vehicle_type ?? 'N/A' }}</td>
            <td class="text-muted">{{ $rider->license_number ?? 'N/A' }}</td>
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
          <tr><td colspan="7" class="text-center text-muted py-4">No riders found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 border-top">{{ $riders->links('pagination::bootstrap-5') }}</div>
</div>
</div>
@endsection
