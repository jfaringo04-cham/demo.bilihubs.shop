@extends('layouts.app')

@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card shadow">
        <div class="card-body p-4">
          <h2 class="text-center mb-4">Complete Your Registration</h2>

          @if(session('social_user'))
            <div class="alert alert-info text-center">
              <i class="bi bi-person-circle"></i> {{ session('social_user.name') }}
              <br><small>{{ session('social_user.email') }}</small>
            </div>
          @endif

          @if($errors->any())
            <div class="alert alert-danger">
              <h6 class="mb-2">Please fix the following errors:</h6>
              <ul class="mb-0">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('social.register.store') }}" enctype="multipart/form-data">
            @csrf

            <h5 class="mb-3">Personal Information</h5>
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                <input type="text" name="last_name" id="last_name" class="form-control" value="{{ old('last_name') }}" required>
                @error('last_name') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4">
                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name') }}" required>
                @error('first_name') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4">
                <label for="middle_name" class="form-label">Middle Initial</label>
                <input type="text" name="middle_name" id="middle_name" class="form-control" value="{{ old('middle_name') }}" maxlength="1">
                @error('middle_name') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="role" class="form-label">Register As <span class="text-danger">*</span></label>
                <select name="role" id="role" class="form-select" required>
                  <option value="">Select Role</option>
                  <option value="customer" {{ old('role') == 'customer' ? 'selected' : '' }}>Buyer</option>
                  <option value="seller" {{ old('role') == 'seller' ? 'selected' : '' }}>Seller</option>
                </select>
                @error('role') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="sex" class="form-label">Sex <span class="text-danger">*</span></label>
                <select name="sex" id="sex" class="form-select" required>
                  <option value="">Select Sex</option>
                  <option value="Male" {{ old('sex') == 'Male' ? 'selected' : '' }}>Male</option>
                  <option value="Female" {{ old('sex') == 'Female' ? 'selected' : '' }}>Female</option>
                </select>
                @error('sex') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="mobile_number" class="form-label">Contact No. <span class="text-danger">*</span></label>
                <input type="text" name="mobile_number" id="mobile_number" class="form-control" value="{{ old('mobile_number') }}" required inputmode="numeric" maxlength="11" pattern="\d{11}" title="Please enter exactly 11 digits" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);">
                @error('mobile_number') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="birthday" class="form-label">Birthday <span class="text-danger">*</span></label>
                <input type="date" name="birthday" id="birthday" class="form-control" value="{{ old('birthday') }}" required>
                @error('birthday') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="age" class="form-label">Age <span class="text-danger">*</span></label>
                <input type="number" name="age" id="age" class="form-control" value="{{ old('age') }}" readonly required>
                @error('age') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
            </div>

            <h5 class="mb-3">Address</h5>
            <div class="row g-3 mb-4">
              <input type="hidden" name="region_name" id="region_name">
              <input type="hidden" name="province_name" id="province_name">
              <input type="hidden" name="municipality_name" id="municipality_name">
              <input type="hidden" name="barangay_name" id="barangay_name">
              <div class="col-md-4">
                <label for="region" class="form-label">Region <span class="text-danger">*</span></label>
                <select name="region" id="region" class="form-select" required>
                  <option value="">Select Region</option>
                </select>
                @error('region') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4">
                <label for="province" class="form-label">Province/City <span class="text-danger">*</span></label>
                <select name="province" id="province" class="form-select" required disabled>
                  <option value="">Select Province/City</option>
                </select>
                @error('province') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4" id="municipality-field">
                <label for="municipality" class="form-label">Municipality <span class="text-danger">*</span></label>
                <select name="municipality" id="municipality" class="form-select" required disabled>
                  <option value="">Select Municipality</option>
                </select>
                @error('municipality') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4">
                <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                <select name="barangay" id="barangay" class="form-select" required disabled>
                  <option value="">Select Barangay</option>
                </select>
                @error('barangay') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6">
                <label for="house_number" class="form-label">House Number</label>
                <input type="text" name="house_number" id="house_number" class="form-control" value="{{ old('house_number') }}" placeholder="e.g. 123">
                @error('house_number') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6">
                <label for="street_address" class="form-label">Street</label>
                <input type="text" name="street_address" id="street_address" class="form-control" value="{{ old('street_address') }}" placeholder="e.g. Rizal Street">
                @error('street_address') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
            </div>

            <h5 class="mb-3">Seller Information</h5>
            <div class="row g-3 mb-4 d-none" id="seller-section">
              <div class="col-md-6">
                <label for="business_name" class="form-label">Business Name <span class="text-danger">*</span></label>
                <input type="text" name="business_name" id="business_name" class="form-control" value="{{ old('business_name') }}">
                @error('business_name') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6">
                <label for="selling_categories" class="form-label">Line of Business <span class="text-danger">*</span></label>
                <select name="selling_categories[]" id="selling_categories" class="form-select" multiple size="5">
                  @foreach($categories ?? [] as $category)
                    <option value="{{ $category->id }}" {{ in_array($category->id, old('selling_categories', [])) ? 'selected' : '' }}>
                      {{ $category->name }}
                    </option>
                  @endforeach
                </select>
                <small class="text-muted">Hold Ctrl to select multiple categories</small>
                @error('selling_categories') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
            </div>

            <h5 class="mb-3">Documents</h5>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label for="id_verification" class="form-label">Upload ID <span class="text-danger">*</span></label>
                <input type="file" name="id_verification" id="id_verification" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                @error('id_verification') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6 d-none" id="business-permit-field">
                <label for="business_permit" class="form-label">Upload Business Permit <span class="text-danger">*</span></label>
                <input type="file" name="business_permit" id="business_permit" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                @error('business_permit') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
            </div>

            <button type="submit" class="btn btn-bili-hub w-100">Complete Registration</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script type="application/json" id="auth-page-data">@php $authPageData = [
  'region' => old('region'),
  'province' => old('province'),
  'municipality' => old('municipality'),
  'barangay' => old('barangay'),
]; @endphp @json($authPageData)</script>
@endsection

@push('scripts')
  @vite('resources/js/auth/social-register.js')
@endpush
