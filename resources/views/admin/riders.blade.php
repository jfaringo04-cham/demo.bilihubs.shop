@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">Riders Management</h1>
    <p class="text-muted mb-0">Monitor courier performance and availability</p>
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
          <th class="border-0">Location</th>
          <th class="border-0">Active Deliveries</th>
          <th class="border-0">Total Delivered</th>
          <th class="border-0">Status</th>
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
            <td>
              @if($rider->latitude && $rider->longitude)
                <span class="badge bg-success">Online</span>
                <small class="text-muted d-block">{{ number_format($rider->latitude, 4) }}, {{ number_format($rider->longitude, 4) }}</small>
              @else
                <span class="badge bg-secondary">Offline</span>
              @endif
            </td>
            <td>
              <span class="badge bg-{{ $rider->active_deliveries_count > 0 ? 'warning' : 'success' }}">
                {{ $rider->active_deliveries_count }}
              </span>
            </td>
            <td class="text-muted">{{ $rider->orders()->where('delivery_status', 'delivered')->count() }}</td>
            <td>
              @if($rider->latitude && $rider->longitude)
                <span class="badge bg-success">Available</span>
              @else
                <span class="badge bg-secondary">No Location</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No riders found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
