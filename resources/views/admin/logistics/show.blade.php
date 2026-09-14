@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Logistics Company Details</h1>
  <div>
    <a href="{{ route('admin.logistics.index') }}" class="btn btn-secondary btn-sm">Back to List</a>
    @if($logistic->status == 'pending')
      <form method="POST" action="{{ route('admin.logistics.approve', $logistic) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-bili-hub btn-sm">Approve</button>
      </form>
      <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
    @elseif($logistic->status == 'active')
      <form method="POST" action="{{ route('admin.logistics.suspend', $logistic) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Suspend this logistics company?')">Suspend</button>
      </form>
    @elseif($logistic->status == 'suspended')
      <form method="POST" action="{{ route('admin.logistics.activate', $logistic) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-success btn-sm">Activate</button>
      </form>
    @endif
  </div>
</div>

<div class="row">
  <div class="col-md-6">
    <div class="table-container mb-4">
      <h5 class="mb-3">Company Information</h5>
      <table class="table table-borderless">
        <tr><th>Company Name</th><td>{{ $logistic->company_name }}</td></tr>
        <tr><th>Owner</th><td>{{ $logistic->owner->name ?? 'N/A' }}</td></tr>
        <tr><th>Contact Person</th><td>{{ $logistic->contact_person ?? 'N/A' }}</td></tr>
        <tr><th>Email</th><td>{{ $logistic->email ?? 'N/A' }}</td></tr>
        <tr><th>Phone</th><td>{{ $logistic->phone ?? 'N/A' }}</td></tr>
        <tr><th>Address</th><td>{{ $logistic->address ?? 'N/A' }}</td></tr>
        <tr><th>Status</th><td>
          @if($logistic->status == 'active')
            <span class="badge bg-success">Active</span>
          @elseif($logistic->status == 'pending')
            <span class="badge bg-warning">Pending</span>
          @elseif($logistic->status == 'suspended')
            <span class="badge bg-danger">Suspended</span>
          @else
            <span class="badge bg-secondary">Rejected</span>
          @endif
        </td></tr>
        @if($logistic->rejection_reason)
          <tr><th>Rejection Reason</th><td class="text-danger">{{ $logistic->rejection_reason }}</td></tr>
        @endif
        <tr><th>Approved At</th><td>{{ $logistic->approved_at ? $logistic->approved_at->format('M d, Y h:i A') : 'N/A' }}</td></tr>
        <tr><th>Joined</th><td>{{ $logistic->created_at->format('M d, Y') }}</td></tr>
      </table>
    </div>
  </div>
  <div class="col-md-6">
    <div class="table-container mb-4">
      <h5 class="mb-3">Statistics</h5>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
              <h3 class="text-primary">{{ $logistic->riders->count() }}</h3>
              <small class="text-muted">Total Riders</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
              <h3 class="text-warning">{{ $pendingRiders->count() }}</h3>
              <small class="text-muted">Pending</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
              <h3 class="text-success">{{ $approvedRiders->count() }}</h3>
              <small class="text-muted">Approved</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="table-container">
  <h5 class="mb-3">Riders</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Rider Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Vehicle Type</th>
          <th>License</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($logistic->riders as $rider)
          <tr>
            <td>{{ $rider->name }}</td>
            <td>{{ $rider->email }}</td>
            <td>{{ $rider->phone ?? 'N/A' }}</td>
            <td>{{ $rider->vehicle_type ?? 'N/A' }}</td>
            <td>{{ $rider->license_number ?? 'N/A' }}</td>
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
              @if($rider->logistic_status == 'pending')
                <form method="POST" action="{{ route('admin.logistics.riders.approve', $rider) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-bili-hub">Approve</button>
                </form>
                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectRiderModal{{ $rider->id }}">Reject</button>
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

<!-- Reject Logistics Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.logistics.reject', $logistic) }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Reject Logistics Company</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Rejection Reason</label>
            <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Reject</button>
        </div>
      </form>
    </div>
  </div>
</div>

@foreach($logistic->riders as $rider)
  @if($rider->logistic_status == 'pending')
    <div class="modal fade" id="rejectRiderModal{{ $rider->id }}" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST" action="{{ route('admin.logistics.riders.reject', $rider) }}">
            @csrf
            <div class="modal-header">
              <h5 class="modal-title">Reject Rider</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Rejection Reason</label>
                <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger">Reject</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif
@endforeach
@endsection
