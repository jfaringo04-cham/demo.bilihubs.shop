@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Edit Address</h2>
    <a href="{{ route('buyer.addresses.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <div class="card" style="max-width: 800px;">
    <div class="card-body">
      <form method="POST" action="{{ route('buyer.addresses.update', $address) }}">
        @csrf
        @method('PUT')
        <div class="mb-3">
          <label for="label" class="form-label">Label (optional)</label>
          <input type="text" name="label" id="label" class="form-control" placeholder="Home, Work, etc." value="{{ old('label', $address->label) }}">
        </div>
        <div class="mb-3">
          <label for="address_line1" class="form-label">Address Line 1</label>
          <input type="text" name="address_line1" id="address_line1" class="form-control" value="{{ old('address_line1', $address->address_line1) }}" required>
          @error('address_line1') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
          <label for="address_line2" class="form-label">Address Line 2 (optional)</label>
          <input type="text" name="address_line2" id="address_line2" class="form-control" value="{{ old('address_line2', $address->address_line2) }}">
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="city" class="form-label">City</label>
            <input type="text" name="city" id="city" class="form-control" value="{{ old('city', $address->city) }}" required>
            @error('city') <div class="text-danger">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6 mb-3">
            <label for="province" class="form-label">Province (optional)</label>
            <input type="text" name="province" id="province" class="form-control" value="{{ old('province', $address->province) }}">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="postal_code" class="form-label">Postal Code (optional)</label>
            <input type="text" name="postal_code" id="postal_code" class="form-control" value="{{ old('postal_code', $address->postal_code) }}">
          </div>
          <div class="col-md-6 mb-3">
            <label for="country" class="form-label">Country</label>
            <input type="text" name="country" id="country" class="form-control" value="{{ old('country', $address->country) }}">
          </div>
        </div>
        <div class="mb-3">
          <label for="phone" class="form-label">Phone Number (optional)</label>
          <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $address->phone) }}">
        </div>
        <div class="mb-3 form-check">
          <input type="checkbox" name="is_default" id="is_default" class="form-check-input" {{ old('is_default', $address->is_default) ? 'checked' : '' }}>
          <label for="is_default" class="form-check-label">Set as default address</label>
        </div>
        <button type="submit" class="btn btn-bili-hub">Update Address</button>
      </form>
    </div>
  </div>
</div>
@endsection


