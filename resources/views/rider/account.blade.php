@extends('rider.layout')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Account Settings</h1>
  </div>

  <div class="table-container">
    <form method="POST" action="{{ route('rider.account.update') }}">
      @csrf
      @method('PUT')

      <h5 class="mb-3">Personal Information</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label class="form-label">First Name</label>
          <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $user->first_name) }}" required>
          @error('first_name') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Middle Name</label>
          <input type="text" name="middle_name" class="form-control" value="{{ old('middle_name', $user->middle_name) }}">
          @error('middle_name') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Last Name</label>
          <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $user->last_name) }}" required>
          @error('last_name') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Sex</label>
          <select name="sex" class="form-select" required>
            <option value="">Select Sex</option>
            <option value="Male" {{ old('sex', $user->sex) == 'Male' ? 'selected' : '' }}>Male</option>
            <option value="Female" {{ old('sex', $user->sex) == 'Female' ? 'selected' : '' }}>Female</option>
          </select>
          @error('sex') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
          @error('email') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Mobile Number</label>
          <input type="text" name="mobile_number" class="form-control" value="{{ old('mobile_number', $user->mobile_number) }}" required>
          @error('mobile_number') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Birthday</label>
          <input type="date" name="birthday" class="form-control" value="{{ old('birthday', $user->birthday) }}" required>
          @error('birthday') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Age</label>
          <input type="number" name="age" class="form-control" value="{{ old('age', $user->age) }}" readonly>
          @error('age') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
      </div>

      <h5 class="mb-3">Address</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label class="form-label">Region</label>
          <select name="region" class="form-select" id="regionSelect" required>
            <option value="">Select Region</option>
          </select>
          @error('region') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Province/City</label>
          <select name="province" class="form-select" id="provinceSelect" required>
            <option value="">Select Province/City</option>
          </select>
          @error('province') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4" id="municipality-field">
          <label class="form-label">Municipality</label>
          <select name="municipality" class="form-select" id="municipalitySelect" required>
            <option value="">Select Municipality</option>
          </select>
          @error('municipality') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Barangay</label>
          <select name="barangay" class="form-select" id="barangaySelect" required>
            <option value="">Select Barangay</option>
          </select>
          @error('barangay') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">House Number</label>
          <input type="text" name="house_number" class="form-control" value="{{ old('house_number', $user->house_number) }}">
          @error('house_number') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-8">
          <label class="form-label">Street Address</label>
          <input type="text" name="street_address" class="form-control" value="{{ old('street_address', $user->street_address) }}">
          @error('street_address') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
      </div>

      <h5 class="mb-3">Rider Information</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label">Vehicle Type</label>
          <select name="vehicle_type" class="form-select" required>
            <option value="">Select Vehicle</option>
            <option value="Motorcycle" {{ old('vehicle_type', $user->vehicle_type) == 'Motorcycle' ? 'selected' : '' }}>Motorcycle</option>
            <option value="Scooter" {{ old('vehicle_type', $user->vehicle_type) == 'Scooter' ? 'selected' : '' }}>Scooter</option>
          </select>
          @error('vehicle_type') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">License Number</label>
          <input type="text" name="license_number" class="form-control" value="{{ old('license_number', $user->license_number) }}" required>
          @error('license_number') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Update Account</button>
    </form>
  </div>
</div>
@endsection

@push('scripts')
  @vite('resources/js/rider/account.js')
@endpush

<script type="application/json" id="rider-account-data">@php $riderAccountData = [
  'selectedRegion' => old('region', $user->region),
  'selectedProvince' => old('province', $user->province),
  'selectedMunicipality' => old('municipality', $user->municipality),
  'selectedBarangay' => old('barangay', $user->barangay),
    ]; @endphp @json($riderAccountData)</script>


