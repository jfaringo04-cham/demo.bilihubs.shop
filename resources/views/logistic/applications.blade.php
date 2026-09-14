@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Pending Rider Applications</h1>
  <a href="{{ route('logistic.riders') }}" class="btn btn-secondary btn-sm">View All Riders</a>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Rider Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Vehicle Type</th>
          <th>Applied At</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($applications as $rider)
          <tr>
            <td>
              <div class="d-flex align-items-center">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;">
                  {{ substr($rider->name, 0, 1) }}
                </div>
                <strong>{{ $rider->name }}</strong>
              </div>
            </td>
            <td>{{ $rider->email }}</td>
            <td>{{ $rider->phone ?? 'N/A' }}</td>
            <td>{{ $rider->vehicle_type ?? 'N/A' }}</td>
            <td>{{ $rider->created_at->format('M d, Y') }}</td>
            <td>
              <a href="{{ route('logistic.riders.show', $rider) }}" class="btn btn-sm btn-outline-primary">Review</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No pending applications.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $applications->links() }}
</div>
@endsection
