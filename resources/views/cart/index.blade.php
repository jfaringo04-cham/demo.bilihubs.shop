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
                      <img src="{{ $item->product->image ? asset('storage/' . $item->product->image) : 'https://via.placeholder.com/60x60?text=No+Image' }}" class="rounded me-3" style="width: 64px; height: 64px; object-fit: cover;" alt="{{ $item->product->name }}">
                      <div>
                        <h6 class="mb-0 fw-semibold">{{ $item->product->name }}</h6>
                        @if($item->product->stock == 0)
                          <span class="badge bg-danger rounded-pill">Sold Out</span>
                        @endif
                        @if($item->size)
                          <small class="text-muted">Size: {{ $item->size->name }}</small>
                        @endif
                      </div>
                    </div>
                  </td>
                <td class="text-muted">&#8369;{{ number_format($item->variation ? $item->variation->effective_price : ($item->product->effective_price ?? $item->product->price), 2) }}</td>
                <td>
                  <form action="{{ route('cart.update', $item) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PUT')
                    <div class="input-group" style="max-width: 160px;">
                      <input type="number" name="quantity" class="form-control rounded-xl" value="{{ $item->quantity }}" min="1" max="{{ $item->product->stock }}" {{ $item->product->stock > 0 ? '' : 'disabled' }}>
                      <button class="btn btn-secondary" type="submit" {{ $item->product->stock > 0 ? '' : 'disabled' }}>Update</button>
                    </div>
                  </form>
                </td>
                <td class="fw-semibold">&#8369;{{ number_format($item->subtotal, 2) }}</td>
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
