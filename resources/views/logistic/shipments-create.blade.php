@extends('layouts.logistic', ['title' => 'Create Shipment', 'logistic' => $logistic])

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Create New Shipment</h1>
  <a href="{{ route('logistic.shipments') }}" class="btn btn-secondary btn-sm">Back to Shipments</a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <form method="POST" action="{{ route('logistic.shipments.store') }}">
      @csrf

      <h5 class="mb-3">Parcel / Seller Order</h5>
      <div class="row g-3 mb-4">
        <div class="col-12">
          <label for="seller_order_id" class="form-label">
            Seller Order / Parcel <span class="text-danger">*</span>
          </label>

          <select name="seller_order_id" id="seller_order_id"
                  class="form-select @error('seller_order_id') is-invalid @enderror" required>
            <option value="">Select Seller Order / Parcel</option>

            @forelse($sellerOrders as $sellerOrder)
              <option value="{{ $sellerOrder->id }}"
                {{ old('seller_order_id') == $sellerOrder->id ? 'selected' : '' }}>
                {{ $sellerOrder->order?->order_number ?? 'Order #' . $sellerOrder->order_id }}
                — {{ $sellerOrder->seller?->name ?? 'Unknown Seller' }}
                — Parcel #{{ $sellerOrder->id }}
              </option>
            @empty
              <option value="" disabled>No available seller orders without a shipment</option>
            @endforelse
          </select>

          @error('seller_order_id')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror

          <div class="form-text">
            Each seller order represents one parcel. A shipment can only be created once for each parcel.
          </div>
        </div>
      </div>

      <h5 class="mb-3">Shipment Details</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label for="rider_id" class="form-label">Assign Rider (Optional)</label>
          <select name="rider_id" id="rider_id"
                  class="form-select @error('rider_id') is-invalid @enderror">
            <option value="">Select Rider (Optional)</option>
            @foreach($riders as $rider)
              <option value="{{ $rider->id }}" {{ old('rider_id') == $rider->id ? 'selected' : '' }}>
                {{ $rider->name }} - {{ $rider->vehicle_type ?? 'N/A' }}
              </option>
            @endforeach
          </select>
          @error('rider_id')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="col-md-6">
          <label for="courier" class="form-label">Courier</label>
          <input type="text"
                 name="courier"
                 id="courier"
                 class="form-control @error('courier') is-invalid @enderror"
                 value="{{ old('courier') }}"
                 placeholder="e.g. Speedy Express">
          @error('courier')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>
      </div>

      <h5 class="mb-3">Addresses</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label for="pickup_address" class="form-label">
            Pickup Address <span class="text-danger">*</span>
          </label>
          <textarea name="pickup_address"
                    id="pickup_address"
                    class="form-control @error('pickup_address') is-invalid @enderror"
                    rows="3"
                    required>{{ old('pickup_address') }}</textarea>
          @error('pickup_address')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="col-md-6">
          <label for="delivery_address" class="form-label">
            Delivery Address <span class="text-danger">*</span>
          </label>
          <textarea name="delivery_address"
                    id="delivery_address"
                    class="form-control @error('delivery_address') is-invalid @enderror"
                    rows="3"
                    required>{{ old('delivery_address') }}</textarea>
          @error('delivery_address')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>
      </div>

      <h5 class="mb-3">Additional Information</h5>
      <div class="row g-3 mb-4">
        <div class="col-12">
          <label for="notes" class="form-label">Notes</label>
          <textarea name="notes"
                    id="notes"
                    class="form-control @error('notes') is-invalid @enderror"
                    rows="3"
                    placeholder="Any special instructions...">{{ old('notes') }}</textarea>
          @error('notes')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>
      </div>

      <button type="submit"
              class="btn btn-bili-hub"
              {{ $sellerOrders->isEmpty() ? 'disabled' : '' }}>
        Create Shipment
      </button>

      @if($sellerOrders->isEmpty())
        <div class="alert alert-info mt-3 mb-0">
          There are currently no Seller Orders available for manual shipment creation.
        </div>
      @endif
    </form>
  </div>
</div>
@endsection
