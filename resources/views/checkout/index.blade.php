@extends('layouts.app')

@section('content')
<div class="container py-4">

  <h2 class="fw-bold text-slate-900 mb-4">
    Checkout
  </h2>

  {{-- Separate voucher forms: no nested forms --}}
  <form
    id="apply-voucher-form"
    action="{{ route('checkout.voucher.apply') }}"
    method="POST">
    @csrf
    <input
      type="hidden"
      name="voucher_code"
      id="voucher-code-hidden">
  </form>

  <form
    id="remove-voucher-form"
    action="{{ route('checkout.voucher.remove') }}"
    method="POST">
    @csrf
  </form>

  {{-- Main checkout form --}}
  <form action="{{ route('checkout.store') }}" method="POST">
    @csrf

    @if($defaultAddressRecord)
    <input
      type="hidden"
      name="address_id"
      value="{{ $defaultAddressRecord->id }}">
    @endif

    <div class="row g-4">

      {{-- LEFT COLUMN --}}
      <div class="col-lg-8">

        {{-- Shipping Address --}}
        <div class="card border-0 shadow-sm rounded-2 mb-4">

          <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center gap-2">
            <h5 class="mb-0 fw-semibold">
              Shipping Address
            </h5>

            <small class="text-muted">
              <i class="bi bi-person-circle me-1"></i>
              Logged in as
              <strong>
                {{ $user->name ?? $user->first_name }}
              </strong>
            </small>
          </div>

          <div class="card-body">

            <div class="row g-3 mb-3">

              <div class="col-md-6">
                <label
                  for="contact_name"
                  class="form-label fw-medium">
                  Recipient Name
                </label>

                <input
                  type="text"
                  name="contact_name"
                  id="contact_name"
                  class="form-control rounded-xl"
                  value="{{ old('contact_name', trim(($user->first_name ?? '') . ' ' . ($user->middle_name ?? '') . ' ' . ($user->last_name ?? ''))) }}"
                  required>
              </div>

              <div class="col-md-6">
                <label
                  for="contact_phone"
                  class="form-label fw-medium">
                  Mobile Number
                </label>

                <input
                  type="text"
                  name="contact_phone"
                  id="contact_phone"
                  class="form-control rounded-xl"
                  value="{{ old('contact_phone', $user->mobile_number ?? $user->phone) }}"
                  required>
              </div>

            </div>

            @if(!empty($defaultAddress))
            <div
              class="alert alert-light border d-flex justify-content-between align-items-center gap-3 mb-3"
              id="saved-address-box">
              <div class="text-break">
                <i class="bi bi-geo-alt-fill text-success me-1"></i>

                <strong>
                  Use my saved address:
                </strong>

                <div class="mt-1">
                  {{ $defaultAddress }}
                </div>
              </div>

              <div class="d-flex gap-2 flex-shrink-0">

                <button
                  type="button"
                  class="btn btn-sm btn-success rounded-xl"
                  id="use-saved-btn">
                  <i class="bi bi-check-lg"></i>
                  Use This
                </button>

                <button
                  type="button"
                  class="btn btn-sm btn-outline-secondary rounded-xl"
                  id="edit-address-btn">
                  <i class="bi bi-pencil"></i>
                  Edit
                </button>

              </div>
            </div>
            @endif

            <div
              class="mb-3"
              id="shipping-address-wrap"
              @if(!empty($defaultAddress))
              style="display:none;"
              @endif>
              <label
                for="shipping_address"
                class="form-label fw-medium">
                Address
              </label>

              <textarea
                name="shipping_address"
                id="shipping_address"
                class="form-control rounded-xl"
                rows="3"
                required>{{ old('shipping_address', $defaultAddress) }}</textarea>

              @error('shipping_address')
              <div class="text-danger small mt-1">
                {{ $message }}
              </div>
              @enderror
            </div>

            <div class="mb-3">
              <label
                for="notes"
                class="form-label fw-medium">
                Notes (optional)
              </label>

              <textarea
                name="notes"
                id="notes"
                class="form-control rounded-xl"
                rows="2"
                placeholder="Delivery instructions, landmarks, etc.">{{ old('notes') }}</textarea>
            </div>

          </div>
        </div>

        {{-- Payment Method --}}
        <div class="card border-0 shadow-sm rounded-2">

          <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0 fw-semibold">
              Payment Method
            </h5>
          </div>

          <div class="card-body">

            @foreach($paymentMethods as $value => $label)
            <div class="form-check mb-2">

              <input
                class="form-check-input"
                type="radio"
                name="payment_method"
                id="payment_{{ $value }}"
                value="{{ $value }}"
                {{ old('payment_method', 'cod') == $value ? 'checked' : '' }}
                required>

              <label
                class="form-check-label"
                for="payment_{{ $value }}">
                {{ $label }}
              </label>

            </div>
            @endforeach

            @error('payment_method')
            <div class="text-danger small mt-1">
              {{ $message }}
            </div>
            @enderror

          </div>
        </div>

      </div>

      {{-- RIGHT COLUMN --}}
      <div class="col-lg-4">

        <div
          class="card border-0 shadow-sm rounded-2 sticky-top"
          style="top:80px;">

          <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0 fw-semibold">
              Order Summary
            </h5>
          </div>

          <div class="card-body">

            {{-- Products --}}
            <div class="mb-3">

              @foreach($cartItems as $item)

              <div class="d-flex justify-content-between align-items-start gap-3 mb-3">

                <div
                  class="flex-grow-1 text-break"
                  style="min-width:0;">

                  <div class="fw-medium">
                    {{ $item->product->name }}
                  </div>

                  @if($item->variation)
                  <small class="text-muted d-block">
                    {{ $item->variation->display_name ?: $item->variation->name }}
                  </small>
                  @endif

                  @if($item->size)
                  <small class="text-muted d-block">
                    Size: {{ $item->size->name }}
                  </small>
                  @endif

                  <small class="text-muted">
                    Qty: {{ $item->quantity }}
                  </small>

                </div>

                <span class="fw-semibold text-nowrap">
                  &#8369;{{ number_format($item->subtotal_minor / 100, 2) }}
                </span>

              </div>

              @endforeach

            </div>

            <hr>

            {{-- Voucher Section --}}
            <div class="mb-4">

              <label
                for="voucher_code"
                class="form-label fw-semibold">
                Have a voucher?
              </label>

              <div class="input-group">

                <input
                  type="text"
                  id="voucher_code"
                  class="form-control"
                  placeholder="Enter voucher code"
                  value="{{ session('checkout.voucher_code', '') }}"
                  maxlength="50"
                  style="min-width:0;">

                <button
                  type="submit"
                  form="apply-voucher-form"
                  class="btn btn-outline-primary">
                  Apply
                </button>

              </div>

              @error('voucher_code')
              <div class="text-danger small mt-2">
                {{ $message }}
              </div>
              @enderror

              @if(session('checkout.voucher_code'))

              <div class="d-flex justify-content-between align-items-center gap-2 mt-2">

                <small class="text-success text-break">
                  Voucher:
                  {{ session('checkout.voucher_code') }}
                </small>

                <button
                  type="submit"
                  form="remove-voucher-form"
                  class="btn btn-sm btn-outline-danger">
                  Remove
                </button>

              </div>

              @if($voucherError ?? false)
              <div class="alert alert-warning mt-2 mb-0">
                {{ $voucherError }}
              </div>
              @elseif(($voucherDiscount ?? 0) > 0)
              <small class="text-success d-block mt-2">
                <i class="bi bi-check-circle me-1"></i>
                Voucher discount applied to your checkout preview.
              </small>
              @endif

              @endif

            </div>

            <hr>

            {{-- Subtotal --}}
            <div class="d-flex justify-content-between gap-3 mb-2">

              <span class="text-muted">
                Subtotal
              </span>

              <span class="text-nowrap">
                &#8369;{{ number_format($subtotal, 2) }}
              </span>

            </div>

            {{-- Service Fee --}}
            <div class="d-flex justify-content-between gap-3 mb-2">

              <span class="text-muted">
                Service Fee (3%)
              </span>

              <span class="text-nowrap">
                &#8369;{{ number_format($serviceFee, 2) }}
              </span>

            </div>

            {{-- Shipping Fee --}}
            <div class="mb-3">

              <div class="d-flex justify-content-between gap-3">
                <span class="text-muted">
                  Shipping Fee
                </span>

                <span class="text-nowrap fw-semibold">
                  &#8369;{{ number_format($shipping, 2) }}
                </span>
              </div>

              @if(!empty($shippingQuote['rows']))
              <div class="mt-2 small text-muted">

                @foreach($shippingQuote['rows'] as $shippingRow)

                <div
                  class="d-flex justify-content-between
                             align-items-start gap-3 mb-1">

                  <span class="text-break">
                    {{ $shippingRow['seller_name'] }}

                    @if($shippingRow['distance_km'] !== null)
                    &middot;
                    {{ number_format(
                              $shippingRow['distance_km'],
                              2
                          ) }} km
                    @else
                    &middot; distance unavailable
                    @endif
                  </span>

                  <span class="text-nowrap">
                    &#8369;{{ number_format(
                            $shippingRow['fee_minor'] / 100,
                            2
                        ) }}
                  </span>

                </div>

                @endforeach

                <div class="mt-2">
                  Base
                  &#8369;{{ number_format(
                        $shippingQuote['base_fee_minor'] / 100,
                        2
                    ) }}
                  includes the first
                  {{ number_format(
                        $shippingQuote['included_distance_km'],
                        0
                    ) }}
                  km.

                  Additional started km:
                  &#8369;{{ number_format(
                        $shippingQuote[
                            'additional_per_km_minor'
                        ] / 100,
                        2
                    ) }}/km.
                </div>

              </div>
              @endif

            </div>

            @if(($voucherDiscount ?? 0) > 0)
            <div class="d-flex justify-content-between text-success mb-2">
              <span>
                <i class="bi bi-ticket-perforated me-1"></i>
                Voucher Discount
                @if($voucherCode)
                ({{ $voucherCode }})
                @endif
              </span>

              <strong>
                -₱{{ number_format($voucherDiscount, 2) }}
              </strong>
            </div>
            @endif

            <hr>

            {{-- Total --}}
            <div class="d-flex justify-content-between align-items-center gap-3 fw-bold fs-5">

              <span>
                Total
              </span>

              <span class="text-primary text-nowrap">
                &#8369;{{ number_format($total, 2) }}
              </span>

            </div>

            <button
              type="submit"
              class="btn btn-primary w-100 mt-3 rounded-xl py-2">
              Place Order
            </button>

          </div>
        </div>

      </div>

    </div>

  </form>

</div>

<script id="checkout-page-data" type="application/json">
{{ Illuminate\Support\Js::from([
        'defaultAddress' => $defaultAddress,
    ]) }}
</script>

@push('scripts')
@vite('resources/js/buyer/checkout.js')
@endpush

@endsection