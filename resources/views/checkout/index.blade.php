@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h2 class="fw-bold text-slate-900 mb-4">Checkout</h2>

  <form action="{{ route('checkout.store') }}" method="POST">
    @csrf
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-2 mb-4">
          <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold">Shipping Address</h5>
            <small class="text-muted"><i class="bi bi-person-circle me-1"></i> Logged in as <strong>{{ $user->name ?? $user->first_name }}</strong></small>
          </div>
          <div class="card-body">
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label for="contact_name" class="form-label fw-medium">Recipient Name</label>
                <input type="text" name="contact_name" id="contact_name" class="form-control rounded-xl" value="{{ old('contact_name', trim(($user->first_name ?? '') . ' ' . ($user->middle_name ?? '') . ' ' . ($user->last_name ?? ''))) }}" required>
              </div>
              <div class="col-md-6">
                <label for="contact_phone" class="form-label fw-medium">Mobile Number</label>
                <input type="text" name="contact_phone" id="contact_phone" class="form-control rounded-xl" value="{{ old('contact_phone', $user->mobile_number ?? $user->phone) }}" required>
              </div>
            </div>

            @if(!empty($defaultAddress))
             <div class="alert alert-light border d-flex justify-content-between align-items-center mb-3" id="saved-address-box">
                <div>
                  <i class="bi bi-geo-alt-fill text-success me-1"></i>
                  <strong>Use my saved address:</strong><br>
                  <span class="text-break">{{ $defaultAddress }}</span>
                </div>
               <div class="d-flex gap-2">
                 <button type="button" class="btn btn-sm btn-success rounded-xl" id="use-saved-btn">
                   <i class="bi bi-check-lg"></i> Use This
                 </button>
                 <button type="button" class="btn btn-sm btn-outline-secondary rounded-xl" id="edit-address-btn">
                   <i class="bi bi-pencil"></i> Edit
                 </button>
               </div>
             </div>
            @endif

            <div class="mb-3" id="shipping-address-wrap" @if(!empty($defaultAddress)) style="display:none;" @endif>
              <label for="shipping_address" class="form-label fw-medium">Address</label>
              <textarea name="shipping_address" id="shipping_address" class="form-control rounded-xl" rows="3" required>{{ old('shipping_address', $defaultAddress) }}</textarea>
              @error('shipping_address') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
              <label for="notes" class="form-label fw-medium">Notes (optional)</label>
              <textarea name="notes" id="notes" class="form-control rounded-xl" rows="2" placeholder="Delivery instructions, landmarks, etc.">{{ old('notes') }}</textarea>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm rounded-2">
          <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0 fw-semibold">Payment Method</h5>
          </div>
          <div class="card-body">
            @foreach($paymentMethods as $value => $label)
              <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="payment_method" id="payment_{{ $value }}" value="{{ $value }}" {{ old('payment_method', 'cod') == $value ? 'checked' : '' }} required>
                <label class="form-check-label" for="payment_{{ $value }}">
                  {{ $label }}
                </label>
              </div>
            @endforeach
            @error('payment_method') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-2 sticky-top" style="top: 80px;">
          <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0 fw-semibold">Order Summary</h5>
          </div>
          <div class="card-body">
             @foreach($cartItems as $item)
               <div class="d-flex justify-content-between mb-2">
                 <span class="text-break me-2">
                   {{ $item->product->name }}
                   @if($item->variation)
                     <small class="text-muted">({{ $item->variation->name }})</small>
                   @endif
                   @if($item->size)
                     <small class="text-muted">{{ $item->size->name }}</small>
                   @endif
                   x{{ $item->quantity }}
                 </span>
                 <span class="fw-medium">&#8369;{{ number_format($item->subtotal, 2) }}</span>
               </div>
             @endforeach
            <hr>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Subtotal</span>
              <span>&#8369;{{ number_format($subtotal, 2) }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Tax (10%)</span>
              <span>&#8369;{{ number_format($tax, 2) }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Shipping</span>
              <span>&#8369;{{ number_format($shipping, 2) }}</span>
            </div>
            <hr>
            <div class="d-flex justify-content-between fw-bold fs-5">
              <span>Total</span>
              <span class="text-sky-500">&#8369;{{ number_format($total, 2) }}</span>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-3 rounded-xl py-2.5">Place Order</button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
  document.getElementById('use-saved-btn')?.addEventListener('click', function() {
    const address = @json($defaultAddress);
    const textarea = document.getElementById('shipping_address');
    if (textarea && address) {
      textarea.value = address;
    }
  });
  document.getElementById('edit-address-btn')?.addEventListener('click', function() {
    document.getElementById('shipping-address-wrap').style.display = 'block';
    document.getElementById('saved-address-box').style.display = 'none';
    document.getElementById('shipping_address').focus();
  });
</script>
@endsection
