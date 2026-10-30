@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h2 class="fw-bold text-slate-900 mb-4">Shopping Cart</h2>

  @if($cartItems->count() > 0)
  <div class="card border-0 shadow-sm rounded-2">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="border-0">Product</th>
            <th class="border-0">Price</th>
            <th class="border-0">Quantity</th>
            <th class="border-0">Subtotal</th>
            <th class="border-0"></th>
          </tr>
        </thead>
        <tbody>
          @foreach($cartItems as $item)
          <tr>
            <td>
              <div class="d-flex align-items-center">
                @if($item->variation)
                @php
                $variantImg = $item->variation->image_url ?: $item->product->image_url;
                $variantName = $item->variation->display_name ?: $item->variation->name;
                @endphp
                <img src="{{ $variantImg }}" class="rounded me-3" style="width: 64px; height: 64px; object-fit: cover;" alt="{{ $variantName }}">
                <div>
                  <h6 class="mb-0 fw-semibold">{{ $item->product->name }}</h6>
                  <small class="text-muted d-block">{{ $variantName }}</small>
                  @if($item->product->stock == 0)
                  <span class="badge bg-danger rounded-pill">Sold Out</span>
                  @endif
                  @if($item->size)
                  <small class="text-muted">Size: {{ $item->size->name }}</small>
                  @endif
                </div>
                @else
                <img src="{{ $item->product->image_url }}"
                  class="rounded me-3 flex-shrink-0"
                  style="width: 64px; height: 64px; object-fit: cover;"
                  alt="{{ $item->product->name }}">
                <div>
                  <h6 class="mb-0 fw-semibold">{{ $item->product->name }}</h6>
                  @if($item->product->stock == 0)
                  <span class="badge bg-danger rounded-pill">Sold Out</span>
                  @endif
                  @if($item->size)
                  <small class="text-muted">Size: {{ $item->size->name }}</small>
                  @endif
                </div>
                @endif
              </div>
            </td>
            <td class="text-muted">&#8369;{{ number_format(($item->variation ? $item->variation->effective_price_minor : $item->product->effective_price_minor) / 100, 2) }}</td>
            <td>

              @php
              $availableStock = (int) $item->product->stock;

              if ($item->variation) {
              $availableStock = min(
              $availableStock,
              (int) $item->variation->stock
              );
              }

              if ($item->size_id) {
              $selectedSize = $item->product->sizes
              ->firstWhere('id', $item->size_id);

              $availableStock = $selectedSize
              ? min($availableStock, (int) ($selectedSize->pivot->stock ?? 0))
              : 0;
              }
              @endphp
              <!-- #region -->
              <form action="{{ route('cart.update', $item) }}" method="POST" class="d-inline">
                @csrf
                @method('PUT')
                <div class="input-group" style="max-width: 160px;">
                  <input
                    type="number"
                    name="quantity"
                    class="form-control rounded-xl"
                    value="{{ $item->quantity }}"
                    min="1"
                    max="{{ $availableStock }}"
                    {{ $availableStock > 0 ? '' : 'disabled' }}>
                  <button
                    class="btn btn-secondary"
                    type="submit"
                    {{ $availableStock > 0 ? '' : 'disabled' }}>
                    Update
                  </button>
                </div>
              </form>
            </td>
            <td class="fw-semibold">&#8369;{{ number_format(($item->subtotal_minor / 100), 2) }}</td>
            <td>
              <form action="{{ route('cart.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this item?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger rounded-xl" type="submit">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @if(($availableSellerVouchers ?? collect())->isNotEmpty())
    <div
      class="border-top p-3"
      data-buyer-voucher-discovery="cart"
    >
      <div class="fw-bold mb-2">
        <i class="bi bi-ticket-perforated-fill text-primary me-1"></i>
        Seller Vouchers Available
      </div>

      <div class="row g-2">
        @foreach($availableSellerVouchers as $voucher)
        <div class="col-lg-6">
          <div class="border rounded-3 p-3 h-100">
            <div class="d-flex justify-content-between gap-2">
              <div>
                <div class="fw-semibold">{{ $voucher->name }}</div>
                <small class="text-muted">
                  {{ $voucher->seller->name ?? 'Seller Voucher' }}
                </small>
              </div>

              <span class="badge text-bg-light border text-dark">
                {{ $voucher->code }}
              </span>
            </div>

            <div class="text-success fw-semibold mt-2">
              @if($voucher->discount_type === 'fixed')
                &#8369;{{ number_format($voucher->discount_value_minor / 100, 2) }} OFF
              @else
                {{ rtrim(rtrim(number_format($voucher->discount_percent_bps / 100, 2), '0'), '.') }}% OFF
                @if($voucher->maximum_discount_minor !== null)
                  up to &#8369;{{ number_format($voucher->maximum_discount_minor / 100, 2) }}
                @endif
              @endif
            </div>

            @if($voucher->minimum_spend_minor > 0)
            <small class="text-muted d-block mt-1">
              Minimum seller spend:
              &#8369;{{ number_format($voucher->minimum_spend_minor / 100, 2) }}
            </small>
            @endif

            @if(($voucher->remaining_spend_minor ?? 0) > 0)
            <small class="text-warning d-block mt-1">
              Add
              &#8369;{{ number_format($voucher->remaining_spend_minor / 100, 2) }}
              more from this seller to qualify.
            </small>
            @else
            <small class="text-success d-block mt-1">
              Ready to use at checkout.
            </small>
            @endif

            <div class="small mt-2">
              Checkout code:
              <strong>{{ $voucher->code }}</strong>
            </div>
          </div>
        </div>
        @endforeach
      </div>

      <small class="text-muted d-block mt-2">
        Enter one voucher code in the existing voucher field during checkout.
      </small>
    </div>
    @endif

    <div class="card-footer bg-white border-0 py-3">
      <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0 fw-bold">Total: <span class="text-sky-500">&#8369;{{ number_format($total, 2) }}</span></h4>
        <a href="{{ route('checkout.index') }}" class="btn btn-primary rounded-xl px-4">Proceed to Checkout</a>
      </div>
    </div>
  </div>
  @else
  <div class="text-center py-5">
    <div class="card border-0 shadow-sm rounded-3 py-5">
      <div class="card-body">
        <i class="bi bi-cart-x display-1 text-muted"></i>
        <p class="text-muted mt-3 mb-4">Your cart is empty.</p>
        <a href="{{ route('products.index') }}" class="btn btn-primary rounded-xl px-4">Continue Shopping</a>
      </div>
    </div>
  </div>
  @endif
</div>
@endsection