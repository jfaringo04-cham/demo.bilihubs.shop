@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">Riders</h1>
    <p class="text-muted mb-0">Monitor rider assignments, availability, and completed work.</p>
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Rider Name</th>
          <th class="border-0">Contact</th>
          <th class="border-0">Last Known Location</th>
          <th class="border-0">Active Final Deliveries</th>
          <th class="border-0">Final Deliveries</th>
          <th class="border-0">Completed Truck Trips</th>
          <th class="border-0">Availability</th>
        </tr>
      </thead>
      <tbody>
        @forelse($riders as $rider)
          <tr>
            <td>
              <div class="fw-semibold">{{ $rider->name }}</div>
              <div class="small text-muted">{{ $rider->logistic?->company_name ?? 'No logistics company' }}</div>
              <div class="small text-muted">
                {{ $rider->relationLoaded('hub') ? ($rider->hub?->name ?? 'No assigned hub') : '—' }}
                · {{ $rider->vehicle_type ? ucfirst($rider->vehicle_type) : 'No vehicle' }}
              </div>
            </td>
            <td class="text-muted">
              <div>{{ $rider->email }}</div>
              <div class="small">{{ $rider->mobile_number ?: ($rider->phone ?: 'No phone') }}</div>
            </td>
            <td class="text-muted">
              @if ($rider->latitude !== null && $rider->longitude !== null)
                <div class="text-nowrap">{{ number_format((float) $rider->latitude, 4) }}, {{ number_format((float) $rider->longitude, 4) }}</div>
                @if ($rider->location_updated_at)
                  <small>{{ \Illuminate\Support\Carbon::parse($rider->location_updated_at)->diffForHumans() }}</small>
                @endif
              @else
                No location
              @endif
            </td>
            <td>{{ $rider->active_deliveries_count ?? '—' }}</td>
            <td>{{ $rider->final_deliveries_count ?? '—' }}</td>
            <td>{{ $rider->completed_truck_trips_count ?? '—' }}</td>
            <td>
              @php
                [$label, $badge] = match ($rider->availability_status) {
                    'available' => ['Available', 'text-bg-success'],
                    'busy' => ['Busy', 'text-bg-warning'],
                    'offline' => ['Offline', 'text-bg-secondary'],
                    default => ['Unknown', 'text-bg-secondary'],
                };
              @endphp
              <span class="badge {{ $badge }}">{{ $label }}</span>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No riders found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($riders->hasPages())
    <div class="p-3">{{ $riders->links('pagination::bootstrap-5') }}</div>
  @endif
</div>
@endsection
