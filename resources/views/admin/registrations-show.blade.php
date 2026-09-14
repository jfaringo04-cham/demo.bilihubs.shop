@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Review Application</h1>
  <a href="{{ route('admin.registrations.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="row g-4">
  <div class="col-md-7">
    <div class="table-container mb-4">
      <h5 class="mb-3">Applicant Information</h5>
      <table class="table table-borderless">
        <tr><th class="w-40">Name</th><td>{{ $user->name }}</td></tr>
        <tr><th>Email</th><td>{{ $user->email }}</td></tr>
        <tr><th>Mobile</th><td>{{ $user->mobile_number ?? 'N/A' }}</td></tr>
        <tr><th>Role</th><td><span class="badge bg-info text-capitalize">{{ $user->role }}</span></td></tr>
        <tr><th>Sex</th><td>{{ $user->sex ?? 'N/A' }}</td></tr>
        <tr><th>Birthday</th><td>{{ $user->birthday ?? 'N/A' }}</td></tr>
        <tr><th>Address</th><td>
          {{ $user->house_number }} {{ $user->street_address }}, {{ $user->barangay }},
          {{ $user->municipality }}, {{ $user->province }}
        </td></tr>
      </table>
    </div>

     @if($user->role === 'seller')
      <div class="table-container mb-4">
        <h5 class="mb-3">Seller Details</h5>
        <table class="table table-borderless">
          <tr><th class="w-40">Business Name</th><td>{{ $user->business_name ?? 'N/A' }}</td></tr>
          <tr><th>Selling Categories</th>
            <td>
              @foreach($user->selling_categories ?? [] as $catId)
                <span class="badge bg-light text-dark">{{ \App\Models\Category::find($catId)->name ?? $catId }}</span>
              @endforeach
            </td>
          </tr>
        </table>
        @if($user->business_permit)
          <a href="{{ asset('storage/' . $user->business_permit) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf"></i> View Business Permit
          </a>
        @endif
      </div>
    @endif

    @if($user->role === 'logistic')
      <div class="table-container mb-4">
        <h5 class="mb-3">Logistics Company Details</h5>
        @php
          $logistic = \App\Models\Logistic::where('owner_user_id', $user->id)->first();
        @endphp
        @if($logistic)
          <table class="table table-borderless">
            <tr><th class="w-40">Company Name</th><td>{{ $logistic->company_name ?? 'N/A' }}</td></tr>
            <tr><th>Contact Person</th><td>{{ $logistic->contact_person ?? 'N/A' }}</td></tr>
            <tr><th>Email</th><td>{{ $logistic->email ?? 'N/A' }}</td></tr>
            <tr><th>Phone</th><td>{{ $logistic->phone ?? 'N/A' }}</td></tr>
            <tr><th>Address</th><td>{{ $logistic->address ?? 'N/A' }}</td></tr>
            <tr><th>Status</th><td><span class="badge bg-{{ $logistic->status == 'active' ? 'success' : ($logistic->status == 'pending' ? 'warning' : ($logistic->status == 'suspended' ? 'danger' : 'secondary')) }}">{{ ucfirst($logistic->status) }}</span></td></tr>
          </table>
          @if($logistic->business_permit)
            <a href="{{ asset('storage/' . $logistic->business_permit) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-file-earmark-pdf"></i> View Business Permit / DTI
            </a>
          @endif
          @if($logistic->logo)
            <img src="{{ asset('storage/' . $logistic->logo) }}" alt="Company Logo" class="mt-2" style="max-height: 80px;">
          @endif
        @else
          <p class="text-muted">No logistic company record found.</p>
        @endif
      </div>
    @endif

    @if($user->role === 'rider')
      <div class="table-container mb-4">
        <h5 class="mb-3">Rider Details</h5>
        <table class="table table-borderless">
          <tr><th class="w-40">Vehicle Type</th><td>{{ $user->vehicle_type ?? 'N/A' }}</td></tr>
          <tr><th>License Number</th><td>{{ $user->license_number ?? 'N/A' }}</td></tr>
        </table>
        @if($user->or_document)
          <a href="{{ asset('storage/' . $user->or_document) }}" target="_blank" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-file-earmark"></i> View OR</a>
        @endif
        @if($user->cr_document)
          <a href="{{ asset('storage/' . $user->cr_document) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark"></i> View CR</a>
        @endif
      </div>
    @endif

    <div class="table-container">
      <h5 class="mb-3">Identity Verification</h5>
      @if($user->id_verification)
        <a href="{{ asset('storage/' . $user->id_verification) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-file-earmark-person"></i> View ID Verification
        </a>
      @else
        <span class="text-muted">No document uploaded.</span>
      @endif
    </div>
  </div>

  <div class="col-md-5">
    <div class="table-container">
      <h5 class="mb-3">Decision</h5>
      <form method="POST" action="{{ route('admin.registrations.approve', $user) }}">
        @csrf
        <button type="submit" class="btn btn-success w-100 mb-2" onclick="return confirm('Approve this application?')">
          <i class="bi bi-check-circle"></i> Approve Registration
        </button>
      </form>

      <form method="POST" action="{{ route('admin.registrations.reject', $user) }}" onsubmit="return confirm('Reject this application?');">
        @csrf
        <div class="mb-2">
          <label class="form-label">Rejection Reason</label>
          <textarea name="rejection_reason" class="form-control @error('rejection_reason') is-invalid @enderror" rows="3" required>{{ old('rejection_reason') }}</textarea>
          @error('rejection_reason') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <button type="submit" class="btn btn-danger w-100">
          <i class="bi bi-x-circle"></i> Reject Registration
        </button>
      </form>
      <small class="text-muted d-block mt-2">The applicant will be notified by email of your decision.</small>
    </div>
  </div>
</div>
@endsection
