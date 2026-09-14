@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Rider Details</h1>
  <a href="{{ route('logistic.riders') }}" class="btn btn-secondary btn-sm">Back to Riders</a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0">Rider Information</h5>
    @if($rider->logistic_status == 'pending')
      <div>
        <form method="POST" action="{{ route('logistic.riders.approve', $rider) }}" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-bili-hub btn-sm">Approve</button>
        </form>
        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
      </div>
    @elseif($rider->logistic_status == 'approved')
      <form method="POST" action="{{ route('logistic.riders.submit', $rider) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Submit this rider to admin for final approval?')">Submit to Admin</button>
      </form>
    @endif
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label text-muted">Full Name</label>
        <p class="fw-bold">{{ $rider->name }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">Email</label>
        <p class="fw-bold">{{ $rider->email }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">Phone</label>
        <p class="fw-bold">{{ $rider->phone ?? 'N/A' }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">Address</label>
        <p class="fw-bold">{{ $rider->address ?? 'N/A' }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">Vehicle Type</label>
        <p class="fw-bold">{{ $rider->vehicle_type ?? 'N/A' }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">License Number</label>
        <p class="fw-bold">{{ $rider->license_number ?? 'N/A' }}</p>
      </div>
      <div class="col-12">
        <label class="form-label text-muted">Status</label>
        <p>
          @if($rider->logistic_status == 'approved')
            <span class="badge bg-success">Approved</span>
          @elseif($rider->logistic_status == 'pending')
            <span class="badge bg-warning">Pending</span>
          @else
            <span class="badge bg-danger">Rejected</span>
          @endif
        </p>
      </div>
      @if($rider->logistic_rejection_reason)
        <div class="col-12">
          <label class="form-label text-muted">Rejection Reason</label>
          <p class="text-danger">{{ $rider->logistic_rejection_reason }}</p>
        </div>
      @endif
      @if($rider->logistic_approved_at)
        <div class="col-12">
          <label class="form-label text-muted">Approved At</label>
          <p class="fw-bold">{{ $rider->logistic_approved_at->format('M d, Y h:i A') }}</p>
        </div>
      @endif
    </div>
  </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('logistic.riders.reject', $rider) }}">
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
@endsection
