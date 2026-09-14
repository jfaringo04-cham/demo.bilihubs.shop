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

  const roleSelect = document.getElementById('role');
  const sellerSection = document.getElementById('seller-section');
  const businessPermitField = document.getElementById('business-permit-field');

  function toggleRoleFields() {
    const role = roleSelect.value;
    if (role === 'seller') {
      sellerSection.classList.remove('d-none');
      businessPermitField.classList.remove('d-none');
    } else {
      sellerSection.classList.add('d-none');
      businessPermitField.classList.add('d-none');
    }
  }

  if (roleSelect) {
    roleSelect.addEventListener('change', toggleRoleFields);
    toggleRoleFields();
  }

  const regionSelect = document.getElementById('region');
  const provinceSelect = document.getElementById('province');
  const municipalitySelect = document.getElementById('municipality');
  const barangaySelect = document.getElementById('barangay');
  const municipalityField = document.getElementById('municipality-field');

  const regionNameInput = document.getElementById('region_name');
  const provinceNameInput = document.getElementById('province_name');
  const municipalityNameInput = document.getElementById('municipality_name');
  const barangayNameInput = document.getElementById('barangay_name');

  const NCR_REGION_CODE = '1300000000';

  function setSelectName(select, nameInput) {
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.value) {
      nameInput.value = selectedOption.text;
    } else {
      nameInput.value = '';
    }
  }

  if (regionSelect) {
    var oldRegion = '{{ old('region') }}';
    var oldProvince = '{{ old('province') }}';
    var oldMunicipality = '{{ old('municipality') }}';
    var oldBarangay = '{{ old('barangay') }}';

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

    if (oldRegion) {
      setTimeout(function() {
        regionSelect.value = oldRegion;
        regionSelect.disabled = false;
        var event = new Event('change');
        regionSelect.dispatchEvent(event);
      }, 500);
    }

    regionSelect.addEventListener('change', function() {
      provinceSelect.innerHTML = '<option value="">Select Province/City</option>';
      municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
      barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
      barangaySelect.disabled = true;
      municipalitySelect.disabled = true;
      provinceSelect.disabled = true;
      setSelectName(regionSelect, regionNameInput);

      if (this.value === NCR_REGION_CODE) {
        if (municipalityField) municipalityField.style.display = 'none';
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
          .catch(error => console.error('Error loading NCR cities:', error));
      } else if (this.value) {
        if (municipalityField) municipalityField.style.display = 'block';
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
          .catch(error => console.error('Error loading provinces:', error));
      }
    });
  }

  if (provinceSelect) {
    provinceSelect.addEventListener('change', function() {
      municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
      barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
      barangaySelect.disabled = true;
      municipalitySelect.disabled = true;
      setSelectName(provinceSelect, provinceNameInput);

      if (this.value) {
        const isNCR = regionSelect && regionSelect.value === NCR_REGION_CODE;
        if (isNCR) {
          barangaySelect.disabled = false;
          const selectedOption = provinceSelect.options[provinceSelect.selectedIndex];
          if (selectedOption) {
            municipalitySelect.innerHTML = `<option value="${selectedOption.value}" selected>${selectedOption.text}</option>`;
            municipalitySelect.disabled = false;
            municipalityNameInput.value = selectedOption.text;
            if (municipalityField) municipalityField.style.display = 'none';
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
            .catch(error => console.error('Error loading barangays:', error));
        } else {
          if (municipalityField) municipalityField.style.display = 'block';
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
            .catch(error => console.error('Error loading municipalities:', error));
        }
      }
    });
  }

  if (municipalitySelect) {
    municipalitySelect.addEventListener('change', function() {
      barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
      setSelectName(municipalitySelect, municipalityNameInput);

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
          .catch(error => console.error('Error loading barangays:', error));
      } else {
        barangaySelect.disabled = true;
      }
    });
  }

  if (barangaySelect) {
    barangaySelect.addEventListener('change', function() {
      setSelectName(barangaySelect, barangayNameInput);
    });
  }
});
</script>
@endsection
