@extends('layouts.app')

@section('content')
@php
    $hasVacationItems = $cartItems->contains(
        fn ($item) => $item->product?->seller?->isOnVacation() ?? false
    );
@endphp

<div class="container py-4">
  <h2 class="fw-bold text-slate-900 mb-4">Shopping Cart</h2>

  @if($hasVacationItems)
    <div class="alert alert-warning mb-4" role="status">
      <i class="bi bi-calendar-x me-2"></i>
      <strong>Seller on Vacation</strong>
      <div class="mt-1">
        Some products are from a seller who is currently on vacation.
        Those products cannot be selected, but you can still check out other available items.
      </div>
    </div>
  @endif

  @if($cartItems->count() > 0)
  <div class="card border-0 shadow-sm rounded-2">
    <form id="cart-selection-form" action="{{ route('cart.checkoutSelected') }}" method="POST" class="d-none">
      @csrf
    </form>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="border-0 text-center" style="width: 52px;">
              <input
                type="checkbox"
                class="form-check-input"
                id="select-all-cart-items"
                aria-label="Select all available cart items">
            </th>
            <th class="border-0">Product</th>
            <th class="border-0">Price</th>
            <th class="border-0">Quantity</th>
            <th class="border-0">Subtotal</th>
            <th class="border-0"></th>
          </tr>
        </thead>
        <tbody>
          @foreach($cartItems as $item)
          @php
            $availableStock = $item->product ? (int) $item->product->stock : 0;

            if ($item->variation) {
              $availableStock = min($availableStock, (int) $item->variation->stock);
            }

            if ($item->size_id && $item->product) {
              $selectedSize = $item->product->sizes->firstWhere('id', $item->size_id);
              $availableStock = $selectedSize
                ? min($availableStock, (int) ($selectedSize->pivot->stock ?? 0))
                : 0;
            }

            $sellerOnVacation = $item->product?->seller?->isOnVacation() ?? false;
            $productUnavailable = !$item->product || !$item->product->isPublished() || !$item->product->seller_id;
            $itemUnavailable = $sellerOnVacation || $productUnavailable || $availableStock < (int) $item->quantity;
          @endphp
          <tr>
            <td class="text-center">
              <input
                type="checkbox"
                class="form-check-input cart-item-select"
                name="cart_item_ids[]"
                value="{{ $item->id }}"
                form="cart-selection-form"
                data-subtotal-minor="{{ $item->subtotal_minor }}"
                aria-label="Select {{ $item->product?->name ?? 'cart item' }}"
                {{ $itemUnavailable ? 'disabled' : '' }}>
              @if($itemUnavailable)
                <small class="d-block text-muted mt-1" style="font-size: 0.7rem;">Unavailable</small>
              @endif
            </td>
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
                  @if($item->product->seller?->isOnVacation())
                    <span class="badge text-bg-warning mt-1">
                      Seller on Vacation
                    </span>
                  @endif
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
                  @if($item->product->seller?->isOnVacation())
                    <span class="badge text-bg-warning mt-1">
                      Seller on Vacation
                    </span>
                  @endif
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
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
          <div class="fw-semibold">
            Cart total:
            <span class="text-sky-500">&#8369;{{ number_format($total, 2) }}</span>
          </div>
          <div class="mt-1">
            Selected subtotal:
            <strong id="selected-cart-total">&#8369;0.00</strong>
            <small class="text-muted ms-1" id="selected-cart-count">0 items selected</small>
          </div>
        </div>

        <button
          type="submit"
          form="cart-selection-form"
          id="checkout-selected-button"
          class="btn btn-primary rounded-xl px-4"
          disabled>
          <i class="bi bi-bag-check me-1"></i>
          Checkout Selected
        </button>
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
@if($cartItems->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('select-all-cart-items');
    const checkboxes = Array.from(document.querySelectorAll('.cart-item-select:not(:disabled)'));
    const checkoutButton = document.getElementById('checkout-selected-button');
    const selectedTotal = document.getElementById('selected-cart-total');
    const selectedCount = document.getElementById('selected-cart-count');

    if (!selectAll || !checkoutButton || !selectedTotal || !selectedCount) {
        return;
    }

    const formatPeso = (minorUnits) => '₱' + (minorUnits / 100).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    const updateSelectionSummary = () => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked);
        const totalMinor = selected.reduce((sum, checkbox) => {
            return sum + Number(checkbox.dataset.subtotalMinor || 0);
        }, 0);

        selectedTotal.textContent = formatPeso(totalMinor);
        selectedCount.textContent = selected.length + (selected.length === 1 ? ' item selected' : ' items selected');
        checkoutButton.disabled = selected.length === 0;
        selectAll.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
        selectAll.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
        selectAll.disabled = checkboxes.length === 0;
    };

    selectAll.addEventListener('change', function () {
        checkboxes.forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
        updateSelectionSummary();
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSelectionSummary);
    });

    updateSelectionSummary();
});
</script>
@endif
@endsection
