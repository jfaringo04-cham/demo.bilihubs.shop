@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
  <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">Account Settings</h1>
    <p class="text-muted mb-0">Update your logistics company information</p>
  </div>
  <a href="{{ route('logistic.dashboard') }}" class="btn btn-secondary rounded-xl"><i class="bi bi-arrow-left me-1"></i> Back to Dashboard</a>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm rounded-2 mb-4">
      <div class="card-header bg-white border-0 py-3">
        <h5 class="mb-0 fw-semibold">Company Information</h5>
      </div>
      <div class="card-body">
        <form action="{{ route('logistic.account.update') }}" method="POST" enctype="multipart/form-data">
          @csrf
          @method('PUT')
          <div class="row g-3">
            <div class="col-md-6">
              <label for="company_name" class="form-label fw-medium">Company Name <span class="text-danger">*</span></label>
              <input type="text" name="company_name" id="company_name" class="form-control rounded-xl" value="{{ $logistic->company_name ?? '' }}" required>
            </div>
            <div class="col-md-6">
              <label for="contact_person" class="form-label fw-medium">Contact Person</label>
              <input type="text" name="contact_person" id="contact_person" class="form-control rounded-xl" value="{{ $logistic->contact_person ?? '' }}">
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label fw-medium">Company Email</label>
              <input type="email" name="email" id="email" class="form-control rounded-xl" value="{{ $logistic->email ?? '' }}">
            </div>
            <div class="col-md-6">
              <label for="phone" class="form-label fw-medium">Company Phone</label>
              <input type="text" name="phone" id="phone" class="form-control rounded-xl" value="{{ $logistic->phone ?? '' }}">
            </div>
            <div class="col-12">
              <label for="address" class="form-label fw-medium">Company Address</label>
              <textarea name="address" id="address" class="form-control rounded-xl" rows="2">{{ $logistic->address ?? '' }}</textarea>
            </div>
            <div class="col-12">
              <label for="api_address" class="form-label fw-medium">Hub API Address / Location</label>
              <input type="text" name="api_address" id="api_address" class="form-control rounded-xl" value="{{ $logistic->api_address ?? '' }}" placeholder="Enter hub API address or full location for auto-assignment">
              <small class="text-muted">This address will be used to auto-match orders near your hub for rider assignment.</small>
            </div>
            <div class="col-12">
              <label for="logo" class="form-label fw-medium">Company Logo</label>
              <input type="file" name="logo" id="logo" class="form-control rounded-xl" accept="image/*">
              @if($logistic->logo)
                <div class="mt-2">
                  <img src="{{ asset('storage/' . $logistic->logo) }}" alt="Company Logo" class="img-thumbnail rounded" style="max-height: 80px;">
                </div>
              @endif
            </div>
          </div>
          <div class="mt-4">
            <button type="submit" class="btn btn-primary rounded-xl px-4">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
</div>
@endsection
