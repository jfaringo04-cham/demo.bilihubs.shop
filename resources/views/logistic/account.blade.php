@extends('layouts.logistic', ['title' => 'Account Settings', 'logistic' => $logistic])

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
              <label for="api_address" class="form-label fw-medium">Company Hub Address</label>
              <input type="text" name="api_address" id="api_address" class="form-control rounded-xl @error('api_address') is-invalid @enderror"
                     value="{{ old('api_address', $logistic->api_address) }}" maxlength="500"
                     placeholder="e.g. Bubukal, Santa Cruz, Laguna" aria-describedby="hub-address-help @error('api_address') hub-address-error @enderror">
              <div id="hub-address-help" class="form-text">Enter the full address to locate your company hub on the map. Manage delivery coverage for each hub under Hubs.</div>
              @error('api_address') <div id="hub-address-error" class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12">
              <label for="logo" class="form-label fw-medium">Company Logo</label>
              <input type="file" name="logo" id="logo" class="form-control rounded-xl @error('logo') is-invalid @enderror"
                     accept=".jpg,.jpeg,.png,image/jpeg,image/png" aria-describedby="logo-help @error('logo') logo-error @enderror">
              <div id="logo-help" class="form-text">JPG or PNG, up to 5 MB.</div>
              @error('logo') <div id="logo-error" class="invalid-feedback">{{ $message }}</div> @enderror
              @if ($logistic->logo_url)
                <div class="mt-2">
                  <div class="small text-muted">Current logo</div>
                  <img src="{{ $logistic->logo_url }}" alt="Current company logo" class="img-thumbnail rounded" style="max-height: 80px;">
                </div>
              @endif
              <div id="new-logo-preview" class="mt-2" hidden>
                <div class="small text-muted">Selected logo preview</div>
                <img id="new-logo-image" alt="Selected company logo" class="img-thumbnail rounded" style="max-height: 80px;">
              </div>
            </div>
          </div>
          <div class="mt-4">
            <button type="submit" class="btn btn-primary rounded-xl px-4">Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <h2 class="h5">Rider Application Policies</h2>
        <p>Applicants must accept these policies before they can apply to your company.</p>
        <form method="POST" action="{{ route('logistic.account.rider-policies', [], false) }}">
          @csrf
          @method('PUT')
          <label for="rider_application_terms" class="form-label">Rider Terms and Conditions</label>
          <textarea id="rider_application_terms" name="rider_application_terms" class="form-control mb-2"
                    rows="6" maxlength="12000" required>{{ old('rider_application_terms', $logistic->rider_application_terms) }}</textarea>
          @error('rider_application_terms') <p class="text-danger">{{ $message }}</p> @enderror
          <label for="rider_application_privacy" class="form-label">Rider Privacy Policy</label>
          <textarea id="rider_application_privacy" name="rider_application_privacy" class="form-control mb-2"
                    rows="6" maxlength="12000" required>{{ old('rider_application_privacy', $logistic->rider_application_privacy) }}</textarea>
          @error('rider_application_privacy') <p class="text-danger">{{ $message }}</p> @enderror
          <button class="btn btn-primary" type="submit">Save Rider Policies</button>
        </form>
      </div>
    </div>
  </div>
</div>
</div>
@endsection

@push('scripts')
<script>
  (() => {
    const input = document.getElementById('logo');
    const preview = document.getElementById('new-logo-preview');
    const image = document.getElementById('new-logo-image');
    let previewUrl;

    input.addEventListener('change', () => {
      if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = undefined;
      }

      preview.hidden = true;
      image.removeAttribute('src');

      const file = input.files[0];
      if (!file || !['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) {
        return;
      }

      previewUrl = URL.createObjectURL(file);
      image.src = previewUrl;
    });

    image.addEventListener('load', () => {
      preview.hidden = false;
    });
  })();
</script>
@endpush
