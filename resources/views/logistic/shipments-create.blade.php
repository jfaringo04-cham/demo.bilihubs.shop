@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Create New Shipment</h1>
  <a href="{{ route('logistic.shipments') }}" class="btn btn-secondary btn-sm">Back to Shipments</a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <form method="POST" action="{{ route('logistic.shipments.store') }}">
      @csrf

      <h5 class="mb-3">Shipment Details</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label for="rider_id" class="form-label">Assign Rider (Optional)</label>
          <select name="rider_id" id="rider_id" class="form-select">
            <option value="">Select Rider (Optional)</option>
            @foreach($riders as $rider)
              <option value="{{ $rider->id }}" {{ old('rider_id') == $rider->id ? 'selected' : '' }}>
                {{ $rider->name }} - {{ $rider->vehicle_type ?? 'N/A' }}
              </option>
            @endforeach
          </select>
          @error('rider_id') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label for="courier" class="form-label">Courier</label>
          <input type="text" name="courier" id="courier" class="form-control" value="{{ old('courier') }}" placeholder="e.g. LBC, J&T Express">
          @error('courier') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
      </div>

      <h5 class="mb-3">Addresses</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label for="pickup_address" class="form-label">Pickup Address <span class="text-danger">*</span></label>
          <textarea name="pickup_address" id="pickup_address" class="form-control" rows="3" required>{{ old('pickup_address') }}</textarea>
          @error('pickup_address') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label for="delivery_address" class="form-label">Delivery Address <span class="text-danger">*</span></label>
          <textarea name="delivery_address" id="delivery_address" class="form-control" rows="3" required>{{ old('delivery_address') }}</textarea>
          @error('delivery_address') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
      </div>

      <h5 class="mb-3">Additional Information</h5>
      <div class="row g-3 mb-4">
        <div class="col-12">
          <label for="notes" class="form-label">Notes</label>
          <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Any special instructions...">{{ old('notes') }}</textarea>
          @error('notes') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
      </div>

      <button type="submit" class="btn btn-bili-hub">Create Shipment</button>
    </form>
  </div>
</div>
@endsection
