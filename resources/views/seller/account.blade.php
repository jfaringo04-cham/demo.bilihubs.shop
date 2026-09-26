@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Account Management</h1>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<div class="card shadow">
  <div class="card-body p-4">
    <form method="POST" action="{{ route('seller.account.update') }}" enctype="multipart/form-data" id="accountForm">
      @csrf
      @method('PUT')

      <h5 class="mb-3">Personal Information</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label class="form-label">Last Name</label>
          <input type="hidden" name="last_name" value="{{ $user->last_name }}">
          <input type="text" class="form-control" value="{{ $user->last_name }}" disabled>
        </div>
        <div class="col-md-4">
          <label class="form-label">First Name</label>
          <input type="hidden" name="first_name" value="{{ $user->first_name }}">
          <input type="text" class="form-control" value="{{ $user->first_name }}" disabled>
        </div>
        <div class="col-md-4">
          <label class="form-label">Middle Initial</label>
          <input type="hidden" name="middle_name" value="{{ $user->middle_name }}">
          <input type="text" class="form-control" value="{{ $user->middle_name }}" disabled>
        </div>
        <div class="col-md-3">
          <label class="form-label">Sex</label>
          <input type="hidden" name="sex" value="{{ $user->sex }}">
          <input type="text" class="form-control" value="{{ $user->sex }}" disabled>
        </div>
        <div class="col-md-3">
          <label class="form-label">Email</label>
          <input type="hidden" name="email" value="{{ $user->email }}">
          <input type="email" class="form-control" value="{{ $user->email }}" disabled>
        </div>
        <div class="col-md-3">
          <label for="mobile_number" class="form-label">Contact No. <span class="text-danger">*</span></label>
          <input type="text" name="mobile_number" id="mobile_number" class="form-control" value="{{ old('mobile_number', $user->mobile_number) }}" required>
          @error('mobile_number') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
          <label class="form-label">Birthday</label>
          <input type="hidden" name="birthday" value="{{ $user->birthday ? \Carbon\Carbon::parse($user->birthday)->format('Y-m-d') : '' }}">
          <input type="date" class="form-control" value="{{ $user->birthday ? \Carbon\Carbon::parse($user->birthday)->format('Y-m-d') : '' }}" disabled>
        </div>
        <div class="col-md-3">
          <label class="form-label">Age</label>
          <input type="hidden" name="age" value="{{ $user->age }}">
          <input type="number" class="form-control" value="{{ $user->age }}" disabled>
        </div>
      </div>

      <h5 class="mb-3">Address</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label for="region" class="form-label">Region <span class="text-danger">*</span></label>
          <input type="hidden" name="region" id="region-hidden" value="{{ $user->region }}">
          <select id="region" class="form-select" required>
            <option value="">Select Region</option>
          </select>
          @error('region') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label for="province" class="form-label">Province/City <span class="text-danger">*</span></label>
          <input type="hidden" name="province" id="province-hidden" value="{{ $user->province }}">
          <select id="province" class="form-select" required disabled>
            <option value="">Select Province/City</option>
          </select>
          @error('province') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4" id="municipality-field">
          <label for="municipality" class="form-label">Municipality <span class="text-danger">*</span></label>
          <input type="hidden" name="municipality" id="municipality-hidden" value="{{ $user->municipality }}">
          <select id="municipality" class="form-select" required disabled>
            <option value="">Select Municipality</option>
          </select>
          @error('municipality') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
          <input type="hidden" name="barangay" id="barangay-hidden" value="{{ $user->barangay }}">
          <select id="barangay" class="form-select" required disabled>
            <option value="">Select Barangay</option>
          </select>
          @error('barangay') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label for="house_number" class="form-label">House Number</label>
          <input type="text" name="house_number" id="house_number" class="form-control" value="{{ old('house_number', $user->house_number) }}" placeholder="e.g. 123">
          @error('house_number') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label for="street_address" class="form-label">Street</label>
          <input type="text" name="street_address" id="street_address" class="form-control" value="{{ old('street_address', $user->street_address) }}" placeholder="e.g. Rizal Street">
          @error('street_address') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
      </div>

      <h5 class="mb-3">Business Information</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label">Business Name</label>
          <input type="hidden" name="business_name" value="{{ $user->business_name ?? $user->store_name }}">
          <input type="text" class="form-control" value="{{ $user->business_name ?? $user->store_name }}" disabled>
        </div>
        <div class="col-md-6">
          <label for="logo" class="form-label">Shop Logo</label>
          <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
          <small class="text-muted">Recommended: 200x200px, max 2MB</small>
          @error('logo') <div class="text-danger">{{ $message }}</div> @enderror
          @if($user->logo)
            <div class="mt-2">
              <img src="{{ asset('storage/' . $user->logo) }}" alt="Logo" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
              <small class="d-block text-muted">Current logo</small>
            </div>
          @endif
        </div>
        <div class="col-md-12">
          <label for="preferred_logistic_id" class="form-label">Preferred Logistics Company</label>
          <select name="preferred_logistic_id" id="preferred_logistic_id" class="form-select">
            <option value="">Select Logistics Company</option>
            @foreach(\App\Models\Logistic::where('status', 'active')->get() as $logistic)
              <option value="{{ $logistic->id }}" {{ old('preferred_logistic_id', $user->preferred_logistic_id) == $logistic->id ? 'selected' : '' }}>
                {{ $logistic->company_name }}
              </option>
            @endforeach
          </select>
          <small class="text-muted">This logistics company will be used when you mark orders as ready for pickup.</small>
          @error('preferred_logistic_id') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
      </div>

      <button type="submit" class="btn btn-bili-hub">Update Account</button>
    </form>
  </div>
</div>

@push('scripts')
<script type="application/json" id="seller-account-data">@php $sellerAccountData = [
  'saved' => [
    'region' => $user->region,
    'province' => $user->province,
    'municipality' => $user->municipality,
    'barangay' => $user->barangay,
  ],
]; @endphp @json($sellerAccountData)</script>
@vite('resources/js/seller/account.js')
@endpush
@endsection

