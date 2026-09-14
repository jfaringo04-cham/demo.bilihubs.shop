@extends('layouts.app')

@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card shadow">
        <div class="card-body p-4">
          <h2 class="text-center mb-4">Apply as Rider</h2>

          <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Apply to join one of our logistics partners below. The logistics company will review your application, and once approved, the administrator will give you final access.
          </div>

          <form method="POST" action="{{ route('apply.rider.store') }}" enctype="multipart/form-data" id="riderApplyForm">
            @csrf

            <h5 class="mb-3">Apply to Logistics Company</h5>
            <div class="row g-3 mb-4">
              <div class="col-md-12">
                <label for="logistic_id" class="form-label">Select Logistics Company <span class="text-danger">*</span></label>
                <select name="logistic_id" id="logistic_id" class="form-select" required>
                  <option value="">Select Logistics Company</option>
                  @foreach($logistics as $logistic)
                    <option value="{{ $logistic->id }}" {{ old('logistic_id') == $logistic->id ? 'selected' : '' }}>
                      {{ $logistic->company_name }} - {{ $logistic->address ?? 'N/A' }}
                    </option>
                  @endforeach
                </select>
                @error('logistic_id') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
            </div>

            <h5 class="mb-3">Personal Information</h5>
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                <input type="text" name="last_name" id="last_name" class="form-control" value="{{ old('last_name') }}" required autofocus>
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
                <label for="sex" class="form-label">Sex <span class="text-danger">*</span></label>
                <select name="sex" id="sex" class="form-select" required>
                  <option value="">Select Sex</option>
                  <option value="Male" {{ old('sex') == 'Male' ? 'selected' : '' }}>Male</option>
                  <option value="Female" {{ old('sex') == 'Female' ? 'selected' : '' }}>Female</option>
                </select>
                @error('sex') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" required>
                @error('email') <div class="text-danger">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="mobile_number" class="form-label">Contact No. <span class="text-danger">*</span></label>
                <input type="text" name="mobile_number" id="mobile_number" class="form-control" value="{{ old('mobile_number') }}" required inputmode="numeric" maxlength="11" pattern="\d{11}" title="Please enter exactly 11 digits">
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

            <div class="alert alert-warning">
              <i class="bi bi-exclamation-triangle"></i> Your application will be reviewed by the administrator. You will be notified via email once approved.
            </div>

            <button type="submit" class="btn btn-bili-hub w-100">Submit Application</button>
          </form>

          <p class="text-center mt-3 mb-0">
            Already have an account? <a href="{{ route('login') }}">Login</a>
          </p>
        </div>
      </div>
    </div>
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

    const regionSelect = document.getElementById('region');
    const provinceSelect = document.getElementById('province');
    const municipalitySelect = document.getElementById('municipality');
    const barangaySelect = document.getElementById('barangay');
    const municipalityField = document.getElementById('municipality-field');

    const NCR_REGION_CODE = '1300000000';

    if (regionSelect) {
      fetch('/api/addresses/regions')
        .then(response => response.json())
        .then(data => {
          data.forEach(region => {
            const option = document.createElement('option');
            option.value = region.code;
            option.textContent = region.name;
            regionSelect.appendChild(option);
          });
        })
        .catch(error => {
          console.error('Error loading regions:', error);
        });

      regionSelect.addEventListener('change', function() {
        provinceSelect.innerHTML = '<option value="">Select Province/City</option>';
        municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        barangaySelect.disabled = true;
        municipalitySelect.disabled = true;
        provinceSelect.disabled = true;

        if (this.value === NCR_REGION_CODE) {
          if (municipalityField) {
            municipalityField.style.display = 'none';
          }
          provinceSelect.disabled = false;
          fetch(`/api/addresses/regions/${this.value}/cities`)
            .then(response => response.json())
            .then(data => {
              data.forEach(city => {
                const option = document.createElement('option');
                option.value = city.code;
                option.textContent = city.name;
                provinceSelect.appendChild(option);
              });
            })
            .catch(error => {
              console.error('Error loading NCR cities:', error);
            });
        } else if (this.value) {
          if (municipalityField) {
            municipalityField.style.display = 'block';
          }
          provinceSelect.disabled = false;
          fetch(`/api/addresses/regions/${this.value}/provinces`)
            .then(response => response.json())
            .then(data => {
              data.forEach(province => {
                const option = document.createElement('option');
                option.value = province.code;
                option.textContent = province.name;
                provinceSelect.appendChild(option);
              });
            })
            .catch(error => {
              console.error('Error loading provinces:', error);
            });
        }
      });
    }

    if (provinceSelect) {
      provinceSelect.addEventListener('change', function() {
        municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        barangaySelect.disabled = true;
        municipalitySelect.disabled = true;

        if (this.value) {
          if (regionSelect && regionSelect.value === NCR_REGION_CODE) {
            barangaySelect.disabled = false;
            const selectedOption = provinceSelect.options[provinceSelect.selectedIndex];
            if (selectedOption) {
              municipalitySelect.innerHTML = `<option value="${selectedOption.value}" selected>${selectedOption.text}</option>`;
              municipalitySelect.disabled = true;
              if (municipalityField) {
                municipalityField.style.display = 'none';
              }
            }
            fetch(`/api/addresses/cities/${this.value}/barangays`)
              .then(response => response.json())
              .then(data => {
                data.forEach(barangay => {
                  const option = document.createElement('option');
                  option.value = barangay.code;
                  option.textContent = barangay.name;
                  barangaySelect.appendChild(option);
                });
              })
              .catch(error => {
                console.error('Error loading barangays:', error);
              });
          } else {
            if (municipalityField) {
              municipalityField.style.display = 'block';
            }
            municipalitySelect.disabled = false;
            fetch(`/api/addresses/provinces/${this.value}/municipalities`)
              .then(response => response.json())
              .then(data => {
                data.forEach(municipality => {
                  const option = document.createElement('option');
                  option.value = municipality.code;
                  option.textContent = municipality.name;
                  municipalitySelect.appendChild(option);
                });
              })
              .catch(error => {
                console.error('Error loading municipalities:', error);
              });
          }
        }
      });
    }

    if (municipalitySelect && municipalityField && municipalityField.style.display !== 'none') {
      municipalitySelect.addEventListener('change', function() {
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        
        if (this.value) {
          barangaySelect.disabled = false;
          fetch(`/api/addresses/municipalities/${this.value}/barangays`)
            .then(response => response.json())
            .then(data => {
              data.forEach(barangay => {
                const option = document.createElement('option');
                option.value = barangay.code;
                option.textContent = barangay.name;
                barangaySelect.appendChild(option);
              });
            })
            .catch(error => {
              console.error('Error loading barangays:', error);
            });
        } else {
          barangaySelect.disabled = true;
        }
      });
    }
  });
</script>
@endsection
