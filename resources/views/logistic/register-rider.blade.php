@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Register New Rider</h1>
  <a href="{{ route('logistic.dashboard') }}" class="btn btn-secondary btn-sm">Back to Dashboard</a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="alert alert-info">
      <i class="bi bi-info-circle"></i> Registering a rider under <strong>{{ $logistic->company_name }}</strong>. The rider will need to wait for admin approval before they can log in.
    </div>

    <form method="POST" action="{{ route('logistic.riders.register.store') }}" enctype="multipart/form-data">
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
          <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
          <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" required>
          @error('email') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label for="mobile_number" class="form-label">Contact No. <span class="text-danger">*</span></label>
          <input type="text" name="mobile_number" id="mobile_number" class="form-control" value="{{ old('mobile_number') }}" required inputmode="numeric" maxlength="11" pattern="\d{11}" title="Please enter exactly 11 digits">
          @error('mobile_number') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label for="birthday" class="form-label">Birthday <span class="text-danger">*</span></label>
          <input type="date" name="birthday" id="birthday" class="form-control" value="{{ old('birthday') }}" required>
          @error('birthday') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label for="age" class="form-label">Age <span class="text-danger">*</span></label>
          <input type="number" name="age" id="age" class="form-control" value="{{ old('age') }}" readonly required>
          @error('age') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
      </div>

      <h5 class="mb-3">Rider Information</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label for="vehicle_type" class="form-label">Vehicle Type <span class="text-danger">*</span></label>
          <select name="vehicle_type" id="vehicle_type" class="form-select" required>
            <option value="">Select Vehicle Type</option>
            <option value="Motorcycle" {{ old('vehicle_type') == 'Motorcycle' ? 'selected' : '' }}>Motorcycle</option>
            <option value="Car" {{ old('vehicle_type') == 'Car' ? 'selected' : '' }}>Car</option>
            <option value="Van" {{ old('vehicle_type') == 'Van' ? 'selected' : '' }}>Van</option>
            <option value="Truck" {{ old('vehicle_type') == 'Truck' ? 'selected' : '' }}>Truck</option>
          </select>
          @error('vehicle_type') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label for="license_number" class="form-label">License Number <span class="text-danger">*</span></label>
          <input type="text" name="license_number" id="license_number" class="form-control" value="{{ old('license_number') }}" required>
          @error('license_number') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
      </div>

      <h5 class="mb-3">Account Security</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
          <input type="password" name="password" id="password" class="form-control" required>
          @error('password') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label for="password_confirmation" class="form-label">Confirm Password <span class="text-danger">*</span></label>
          <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
        </div>
      </div>

      <h5 class="mb-3">Documents</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label for="or_document" class="form-label">Upload OR <span class="text-danger">*</span></label>
          <input type="file" name="or_document" id="or_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
          @error('or_document') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label for="cr_document" class="form-label">Upload CR <span class="text-danger">*</span></label>
          <input type="file" name="cr_document" id="cr_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
          @error('cr_document') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
      </div>

      <button type="submit" class="btn btn-bili-hub">Register Rider</button>
    </form>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const birthdayInput = document.getElementById('birthday');
    const ageInput = document.getElementById('age');

    if (birthdayInput && ageInput) {
      birthdayInput.addEventListener('change', function() {
        const birthday = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - birthday.getFullYear();
        const monthDiff = today.getMonth() - birthday.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
          age--;
        }
        ageInput.value = age >= 0 ? age : '';
      });
    }
  });
</script>
@endsection
