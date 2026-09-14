@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">Logistics Companies</h1>
    <p class="text-muted mb-0">Manage delivery partners and their hubs</p>
  </div>
  <a href="{{ route('admin.logistics.index') }}" class="btn btn-secondary btn-sm rounded-xl"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</a>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Company Name</th>
          <th class="border-0">Owner</th>
          <th class="border-0">Contact</th>
          <th class="border-0">Riders</th>
          <th class="border-0">Status</th>
          <th class="border-0">Joined</th>
          <th class="border-0"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($logistics as $logistic)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <div class="bg-sky-100 text-sky-600 rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                  {{ substr($logistic->company_name, 0, 1) }}
                </div>
                <span class="fw-semibold">{{ $logistic->company_name }}</span>
              </div>
            </td>
            <td class="text-muted">{{ $logistic->owner->name ?? 'N/A' }}</td>
            <td class="text-muted">{{ $logistic->phone ?? 'N/A' }}</td>
            <td><span class="badge bg-info">{{ $logistic->riders->count() }} riders</span></td>
            <td>
              @if($logistic->status == 'active')
                <span class="badge bg-success">Active</span>
              @elseif($logistic->status == 'pending')
                <span class="badge bg-warning">Pending</span>
              @elseif($logistic->status == 'suspended')
                <span class="badge bg-danger">Suspended</span>
              @else
                <span class="badge bg-secondary">Rejected</span>
              @endif
            </td>
            <td class="text-muted">{{ $logistic->created_at->format('M d, Y') }}</td>
            <td>
              <a href="{{ route('admin.logistics.show', $logistic) }}" class="btn btn-primary btn-sm rounded-xl">
                <i class="bi bi-eye me-1"></i>View
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No logistics companies found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
