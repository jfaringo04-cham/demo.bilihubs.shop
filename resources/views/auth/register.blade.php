@extends('layouts.app')

@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-5">
          <div class="text-center mb-4">
            <i class="bi bi-person-plus text-sky-500 fs-1"></i>
            <h2 class="fw-bold mt-2">Create Account</h2>
            <p class="text-muted">Join Bili Hub today</p>
          </div>

          @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-2 border-0" role="alert">
              <strong>Please fix the following errors:</strong>
              <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          @endif

          <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" id="registerForm">
            @csrf

            <h5 class="fw-semibold text-slate-800 mb-3">Personal Information</h5>
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <label for="last_name" class="form-label fw-medium">Last Name <span class="text-danger">*</span></label>
                <input type="text" name="last_name" id="last_name" class="form-control rounded-xl" value="{{ old('last_name') }}" required autofocus>
                @error('last_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4">
                <label for="first_name" class="form-label fw-medium">First Name <span class="text-danger">*</span></label>
                <input type="text" name="first_name" id="first_name" class="form-control rounded-xl" value="{{ old('first_name') }}" required>
                @error('first_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4">
                <label for="middle_name" class="form-label fw-medium">Middle Initial</label>
                <input type="text" name="middle_name" id="middle_name" class="form-control rounded-xl" value="{{ old('middle_name') }}" maxlength="1">
                @error('middle_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="role" class="form-label fw-medium">Register As <span class="text-danger">*</span></label>
                 <select name="role" id="role" class="form-select rounded-xl" required>
                   <option value="">Select Role</option>
                   <option value="buyer" {{ old('role') == 'buyer' ? 'selected' : '' }}>Buyer</option>
                   <option value="seller" {{ old('role') == 'seller' ? 'selected' : '' }}>Seller</option>
                   <option value="logistic" {{ old('role') == 'logistic' ? 'selected' : '' }}>Logistics Company</option>
                 </select>
                 @error('role') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="sex" class="form-label fw-medium">Sex <span class="text-danger">*</span></label>
                <select name="sex" id="sex" class="form-select rounded-xl" required>
                  <option value="">Select Sex</option>
                  <option value="Male" {{ old('sex') == 'Male' ? 'selected' : '' }}>Male</option>
                  <option value="Female" {{ old('sex') == 'Female' ? 'selected' : '' }}>Female</option>
                </select>
                @error('sex') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="email" class="form-label fw-medium">E-mail <span class="text-danger">*</span></label>
                <input type="email" name="email" id="email" class="form-control rounded-xl" value="{{ old('email') }}" required>
                @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="mobile_number" class="form-label fw-medium">Contact No. <span class="text-danger">*</span></label>
                <input type="text" name="mobile_number" id="mobile_number" class="form-control rounded-xl" value="{{ old('mobile_number') }}" required inputmode="numeric" maxlength="11" pattern="\d{11}" title="Please enter exactly 11 digits">
                @error('mobile_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="birthday" class="form-label fw-medium">Birthday <span class="text-danger">*</span></label>
                <input type="date" name="birthday" id="birthday" class="form-control rounded-xl" value="{{ old('birthday') }}" required>
                @error('birthday') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="age" class="form-label fw-medium">Age <span class="text-danger">*</span></label>
                <input type="number" name="age" id="age" class="form-control rounded-xl" value="{{ old('age') }}" readonly required>
                @error('age') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
            </div>

            <h5 class="fw-semibold text-slate-800 mb-3">Account Security</h5>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label for="password" class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" id="password" class="form-control rounded-xl" required minlength="8">
                <small class="text-muted">Minimum 8 characters</small>
                @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6">
                <label for="password_confirmation" class="form-label fw-medium">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control rounded-xl" required>
                @error('password_confirmation') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
            </div>

            <h5 class="fw-semibold text-slate-800 mb-3">Address</h5>
            <div class="row g-3 mb-4">
              <input type="hidden" name="region_name" id="region_name">
              <input type="hidden" name="province_name" id="province_name">
              <input type="hidden" name="municipality_name" id="municipality_name">
              <input type="hidden" name="barangay_name" id="barangay_name">
              <div class="col-md-4">
                <label for="region" class="form-label fw-medium">Region <span class="text-danger">*</span></label>
                <select name="region" id="region" class="form-select rounded-xl" required>
                  <option value="">Select Region</option>
                </select>
                @error('region') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4">
                <label for="province" class="form-label fw-medium">Province/City <span class="text-danger">*</span></label>
                <select name="province" id="province" class="form-select rounded-xl" required disabled>
                  <option value="">Select Province/City</option>
                </select>
                @error('province') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4" id="municipality-field">
                <label for="municipality" class="form-label fw-medium">Municipality <span class="text-danger">*</span></label>
                <select name="municipality" id="municipality" class="form-select rounded-xl" required disabled>
                  <option value="">Select Municipality</option>
                </select>
                @error('municipality') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4">
                <label for="barangay" class="form-label fw-medium">Barangay <span class="text-danger">*</span></label>
                <select name="barangay" id="barangay" class="form-select rounded-xl" required disabled>
                  <option value="">Select Barangay</option>
                </select>
                @error('barangay') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6">
                <label for="house_number" class="form-label fw-medium">House Number</label>
                <input type="text" name="house_number" id="house_number" class="form-control rounded-xl" value="{{ old('house_number') }}" placeholder="e.g. 123">
                @error('house_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6">
                <label for="street_address" class="form-label fw-medium">Street</label>
                <input type="text" name="street_address" id="street_address" class="form-control rounded-xl" value="{{ old('street_address') }}" placeholder="e.g. Rizal Street">
                @error('street_address') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
            </div>

             <h5 class="fw-semibold text-slate-800 mb-3">Seller Information</h5>
            <div class="row g-3 mb-4 d-none" id="seller-section">
              <div class="col-md-6">
                <label for="business_name" class="form-label fw-medium">Business Name <span class="text-danger">*</span></label>
                <input type="text" name="business_name" id="business_name" class="form-control rounded-xl" value="{{ old('business_name') }}">
                @error('business_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6">
                <label for="selling_categories" class="form-label fw-medium">Line of Business <span class="text-danger">*</span></label>
                <select name="selling_categories[]" id="selling_categories" class="form-select rounded-xl" multiple size="5">
                  @foreach($categories ?? [] as $category)
                    <option value="{{ $category->id }}" {{ in_array($category->id, old('selling_categories', [])) ? 'selected' : '' }}>
                      {{ $category->name }}
                    </option>
                  @endforeach
                </select>
                <small class="text-muted">Hold Ctrl to select multiple categories</small>
                @error('selling_categories') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
            </div>

             <h5 class="fw-semibold text-slate-800 mb-3">Logistics Company Information</h5>
             <div class="row g-3 mb-4 d-none" id="logistic-section">
               <div class="col-md-6">
                 <label for="company_name" class="form-label fw-medium">Company Name <span class="text-danger">*</span></label>
                 <input type="text" name="company_name" id="company_name" class="form-control rounded-xl" value="{{ old('company_name') }}">
                 @error('company_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
               </div>
               <div class="col-md-6">
                 <label for="contact_person" class="form-label fw-medium">Contact Person</label>
                 <input type="text" name="contact_person" id="contact_person" class="form-control rounded-xl" value="{{ old('contact_person') }}">
                 @error('contact_person') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
               </div>
               <div class="col-md-6">
                 <label for="company_email" class="form-label fw-medium">Company Email</label>
                 <input type="email" name="company_email" id="company_email" class="form-control rounded-xl" value="{{ old('company_email') }}">
                 @error('company_email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
               </div>
               <div class="col-md-6">
                 <label for="company_phone" class="form-label fw-medium">Company Phone</label>
                 <input type="text" name="company_phone" id="company_phone" class="form-control rounded-xl" value="{{ old('company_phone') }}">
                 @error('company_phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
               </div>
               <div class="col-12">
                 <label for="company_address" class="form-label fw-medium">Company Address</label>
                 <textarea name="company_address" id="company_address" class="form-control rounded-xl" rows="2">{{ old('company_address') }}</textarea>
                 @error('company_address') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
               </div>
               <div class="col-12">
                 <label for="api_address" class="form-label fw-medium">Hub API Address / Location</label>
                 <input type="text" name="api_address" id="api_address" class="form-control rounded-xl" value="{{ old('api_address') }}" placeholder="Enter hub API address or full location for auto-assignment">
                 <small class="text-muted">This address will be used to auto-match orders near your hub for rider assignment.</small>
                 @error('api_address') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
               </div>
             </div>

            <h5 class="fw-semibold text-slate-800 mb-3">Documents</h5>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label for="id_verification" class="form-label fw-medium">Upload ID <span class="text-danger">*</span></label>
                <input type="file" name="id_verification" id="id_verification" class="form-control rounded-xl" accept=".pdf,.jpg,.jpeg,.png" required>
                @error('id_verification') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6 d-none" id="business-permit-field">
                <label for="business_permit" class="form-label fw-medium">Upload Business Permit <span class="text-danger">*</span></label>
                <input type="file" name="business_permit" id="business_permit" class="form-control rounded-xl" accept=".pdf,.jpg,.jpeg,.png">
                @error('business_permit') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="alert alert-info rounded-2 border-0">
              <i class="bi bi-info-circle me-1"></i> After submitting your registration, please wait for the administrator's approval, which will be sent to your email.
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3 rounded-xl py-2.5">Register</button>
          </form>

          <p class="text-center mt-3 mb-0">
            Already have an account? <a href="{{ route('login') }}" class="text-sky-500 fw-medium">Login</a>
          </p>
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
  'geocodeKey' => config('services.googlemaps.key'),
]; @endphp @json($authPageData)</script>
@endsection

@push('scripts')
  @vite('resources/js/auth/register.js')
@endpush
