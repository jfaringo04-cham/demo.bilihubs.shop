@extends('rider.layout')

@section('content')
<div class="container py-4">

  <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
      <h1 class="h2 mb-1">Account Settings</h1>
      <p class="text-muted mb-0">
        Manage your rider profile and account information.
      </p>
    </div>
  </div>

  @if(session('success'))
  <div class="alert alert-success">
    {{ session('success') }}
  </div>
  @endif

  {{-- Rider Profile Summary --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">

      <div class="row align-items-center g-4">

        <div class="col-md-auto text-center">
          @if($profilePhotoUrl)
          <img
            src="{{ $profilePhotoUrl }}"
            alt="{{ $user->name }}"
            class="rounded-circle border shadow-sm"
            style="width: 120px; height: 120px; object-fit: cover;">
          @else
          <div
            class="rounded-circle bg-light border d-flex align-items-center justify-content-center mx-auto"
            style="width: 120px; height: 120px;">
            <i class="bi bi-person-fill text-secondary fs-1"></i>
          </div>
          @endif
        </div>

        <div class="col">
          <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
            <h3 class="mb-0">
              {{ $user->name }}
            </h3>

            <span class="badge bg-{{ $user->logistic_status === 'approved' ? 'success' : ($user->logistic_status === 'pending' ? 'warning text-dark' : 'danger') }}">
              {{ ucfirst($user->logistic_status ?? 'Unknown') }}
            </span>

            <span class="badge {{ $user->availability_status === 'available' ? 'bg-success' : 'bg-secondary' }}">
              {{ ucfirst($user->availability_status ?? 'offline') }}
            </span>
          </div>

          <p class="text-muted mb-3">
            Rider Account
          </p>

          <div class="row g-3 small">

            <div class="col-md-4">
              <span class="text-muted d-block">Logistics Company</span>
              <strong>
                {{ optional($user->logistic)->company_name ?? 'Not assigned' }}
              </strong>
            </div>

            <div class="col-md-4">
              <span class="text-muted d-block">Assigned Hub</span>
              <strong>
                {{ optional($user->hub)->name ?? 'Not assigned' }}
              </strong>
            </div>

            <div class="col-md-4">
              <span class="text-muted d-block">Assigned Zone</span>
              <strong>
                {{ $user->assigned_zone ?: 'Not assigned' }}
              </strong>
            </div>

            <div class="col-md-4">
              <span class="text-muted d-block">Vehicle</span>
              <strong>
                {{ ucfirst($user->vehicle_type ?: 'Not specified') }}
              </strong>
            </div>

            <div class="col-md-4">
              <span class="text-muted d-block">Current Load</span>
              <strong>
                {{ (int) ($user->current_load ?? 0) }}
                /
                {{ (int) ($user->max_capacity ?? 0) }}
                parcels
              </strong>
            </div>

            <div class="col-md-4">
              <span class="text-muted d-block">Account Status</span>
              <strong>
                {{ ucfirst($user->status ?? 'Unknown') }}
              </strong>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>

  {{-- Profile Form --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">

      <form
        method="POST"
        action="{{ route('rider.account.update') }}"
        enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- Profile Photo --}}
        <h5 class="mb-3">Profile Photo</h5>

        <div class="row g-3 mb-4">
          <div class="col-md-8">

            <label class="form-label">
              Upload New Photo
            </label>

            <input
              type="file"
              name="profile_photo"
              class="form-control"
              accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

            <div class="form-text">
              JPG, JPEG, PNG or WebP. Maximum file size: 2 MB.
            </div>

            @error('profile_photo')
            <div class="text-danger small">
              {{ $message }}
            </div>
            @enderror

          </div>

          @if($user->profile_photo_path)
          <div class="col-md-4 d-flex align-items-end">

            <div class="form-check mb-2">
              <input
                class="form-check-input"
                type="checkbox"
                value="1"
                name="remove_profile_photo"
                id="removeProfilePhoto">

              <label
                class="form-check-label text-danger"
                for="removeProfilePhoto">
                Remove current photo
              </label>
            </div>

          </div>
          @endif
        </div>

        <hr class="my-4">

        {{-- Personal Information --}}
        <h5 class="mb-3">Personal Information</h5>

        <div class="row g-3 mb-4">

          <div class="col-md-4">
            <label class="form-label">First Name</label>

            <input
              type="text"
              name="first_name"
              class="form-control"
              value="{{ old('first_name', $user->first_name) }}"
              required>

            @error('first_name')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Middle Name</label>

            <input
              type="text"
              name="middle_name"
              class="form-control"
              value="{{ old('middle_name', $user->middle_name) }}">

            @error('middle_name')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Last Name</label>

            <input
              type="text"
              name="last_name"
              class="form-control"
              value="{{ old('last_name', $user->last_name) }}"
              required>

            @error('last_name')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Sex</label>

            <select
              name="sex"
              class="form-select"
              required>
              <option value="">Select Sex</option>

              <option
                value="Male"
                {{ old('sex', $user->sex) === 'Male' ? 'selected' : '' }}>
                Male
              </option>

              <option
                value="Female"
                {{ old('sex', $user->sex) === 'Female' ? 'selected' : '' }}>
                Female
              </option>
            </select>

            @error('sex')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Email</label>

            <input
              type="email"
              name="email"
              class="form-control"
              value="{{ old('email', $user->email) }}"
              required>

            @error('email')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Mobile Number</label>

            <input
              type="text"
              name="mobile_number"
              class="form-control"
              value="{{ old('mobile_number', $user->mobile_number) }}"
              required>

            @error('mobile_number')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Birthday</label>

            <input
              type="date"
              name="birthday"
              class="form-control"
              value="{{ old('birthday', $user->birthday) }}"
              required>

            @error('birthday')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Age</label>

            <input
              type="number"
              name="age"
              class="form-control"
              value="{{ old('age', $user->age) }}"
              readonly>
          </div>

        </div>

        <hr class="my-4">

        {{-- Address --}}
        <h5 class="mb-3">Address</h5>

        <div class="row g-3 mb-4">

          <div class="col-md-4">
            <label class="form-label">Region</label>

            <select
              name="region"
              class="form-select"
              id="regionSelect"
              required>
              <option value="">Select Region</option>
            </select>

            @error('region')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Province/City</label>

            <select
              name="province"
              class="form-select"
              id="provinceSelect"
              required>
              <option value="">Select Province/City</option>
            </select>

            @error('province')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

          <div
            class="col-md-4"
            id="municipality-field">
            <label class="form-label">Municipality</label>

            <select
              name="municipality"
              class="form-select"
              id="municipalitySelect"
              required>
              <option value="">Select Municipality</option>
            </select>

            @error('municipality')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Barangay</label>

            <select
              name="barangay"
              class="form-select"
              id="barangaySelect"
              required>
              <option value="">Select Barangay</option>
            </select>

            @error('barangay')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">House Number</label>

            <input
              type="text"
              name="house_number"
              class="form-control"
              value="{{ old('house_number', $user->house_number) }}">
          </div>

          <div class="col-md-8">
            <label class="form-label">Street Address</label>

            <input
              type="text"
              name="street_address"
              class="form-control"
              value="{{ old('street_address', $user->street_address) }}">
          </div>

        </div>

        <hr class="my-4">

        {{-- Rider Information --}}
        <h5 class="mb-3">Rider Information</h5>

        <div class="row g-3 mb-4">

          <div class="col-md-6">
            <label class="form-label">Vehicle Type</label>

            <select
              name="vehicle_type"
              class="form-select"
              required>
              <option value="">Select Vehicle</option>

              @foreach([
              'motorcycle' => 'Motorcycle',
              'car' => 'Car',
              'van' => 'Van',
              'truck' => 'Truck',
              ] as $value => $label)

              <option
                value="{{ $value }}"
                {{ strtolower((string) old('vehicle_type', $user->vehicle_type)) === $value ? 'selected' : '' }}>
                {{ $label }}
              </option>

              @endforeach
            </select>

            @error('vehicle_type')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label">License Number</label>

            <input
              type="text"
              name="license_number"
              class="form-control"
              value="{{ old('license_number', $user->license_number) }}"
              required>

            @error('license_number')
            <div class="text-danger small">{{ $message }}</div>
            @enderror
          </div>

        </div>

        {{-- Read-only operational info --}}
        <div class="alert alert-light border mb-4">
          <strong>Operational Information</strong>

          <p class="small text-muted mb-0 mt-1">
            Logistics company, hub assignment, delivery zone, rider
            capacity and approval status are managed by your logistics
            company and cannot be changed from this page.
          </p>
        </div>

        <button
          type="submit"
          class="btn btn-primary">
          <i class="bi bi-save me-1"></i>
          Update Account
        </button>

      </form>

    </div>
  </div>

  {{-- Documents --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">

      <h5 class="mb-3">Rider Documents</h5>

      <div class="row g-3">

        <div class="col-md-4">
          <div class="border rounded p-3 h-100">

            <div class="text-muted small mb-1">
              Driver's License
            </div>

            @if($user->license_number)
            <span class="badge bg-success">
              Information Provided
            </span>

            <div class="small mt-2">
              {{ $user->license_number }}
            </div>
            @else
            <span class="badge bg-warning text-dark">
              Missing
            </span>
            @endif

          </div>
        </div>

        <div class="col-md-4">
          <div class="border rounded p-3 h-100">

            <div class="text-muted small mb-1">
              OR Document
            </div>

            @if($user->or_document)
            <span class="badge bg-success">
              Uploaded
            </span>
            @else
            <span class="badge bg-warning text-dark">
              Missing
            </span>
            @endif

          </div>
        </div>

        <div class="col-md-4">
          <div class="border rounded p-3 h-100">

            <div class="text-muted small mb-1">
              CR Document
            </div>

            @if($user->cr_document)
            <span class="badge bg-success">
              Uploaded
            </span>
            @else
            <span class="badge bg-warning text-dark">
              Missing
            </span>
            @endif

          </div>
        </div>

      </div>

    </div>
  </div>

  {{-- Security --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">

      <h5 class="mb-1">Security</h5>

      <p class="text-muted small mb-4">
        Change your account password.
      </p>

      <form
        method="POST"
        action="{{ route('profile.password.update') }}">
        @csrf
        @method('PATCH')

        <div class="row g-3">

          <div class="col-md-4">
            <label class="form-label">
              Current Password
            </label>

            <input
              type="password"
              name="current_password"
              class="form-control"
              autocomplete="current-password"
              required>

            @error('current_password', 'passwordUpdate')
            <div class="text-danger small">
              {{ $message }}
            </div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">
              New Password
            </label>

            <input
              type="password"
              name="password"
              class="form-control"
              autocomplete="new-password"
              required>

            @error('password', 'passwordUpdate')
            <div class="text-danger small">
              {{ $message }}
            </div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">
              Confirm New Password
            </label>

            <input
              type="password"
              name="password_confirmation"
              class="form-control"
              autocomplete="new-password"
              required>
          </div>

        </div>

        <button
          type="submit"
          class="btn btn-outline-primary mt-4">
          <i class="bi bi-shield-lock me-1"></i>
          Change Password
        </button>

      </form>

    </div>
  </div>

</div>
@endsection

@push('scripts')
@vite('resources/js/rider/account.js')
@endpush

<script
  type="application/json"
  id="rider-account-data">
  @php
  $riderAccountData = [
    'selectedRegion' => old('region', $user->region),
    'selectedProvince' => old('province', $user->province),
    'selectedMunicipality' => old('municipality', $user->municipality),
    'selectedBarangay' => old('barangay', $user->barangay),
  ];
  @endphp
  @json($riderAccountData)
</script>