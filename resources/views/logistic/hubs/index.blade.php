@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
  <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">Hubs</h1>
    <p class="text-muted mb-0">Manage delivery hubs and rider assignments</p>
  </div>
  <a href="{{ route('logistic.hubs.create') }}" class="btn btn-primary rounded-xl">
    <i class="bi bi-plus-circle me-1"></i> Create Hub
  </a>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Hub Name</th>
          <th class="border-0">Address</th>
          <th class="border-0">Contact</th>
          <th class="border-0">Riders</th>
          <th class="border-0">Status</th>
          <th class="border-0"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($hubs as $hub)
          <tr>
            <td class="fw-medium">{{ $hub->name }}</td>
            <td class="text-muted">{{ $hub->address }}</td>
            <td class="text-muted">{{ $hub->contact_person ?? 'N/A' }}<br><small>{{ $hub->phone ?? '' }}</small></td>
            <td><span class="badge bg-info">{{ $hub->riders->count() }} riders</span></td>
            <td>
              @if($hub->status == 'active')
                <span class="badge bg-success">Active</span>
              @else
                <span class="badge bg-secondary">Inactive</span>
              @endif
            </td>
            <td>
              <a href="{{ route('logistic.hubs.show', $hub) }}" class="btn btn-primary btn-sm rounded-xl">
                <i class="bi bi-eye me-1"></i>View
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No hubs found. Create your first hub to get started.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 border-top">{{ $hubs->links('pagination::bootstrap-5') }}</div>
</div>
</div>
@endsection
