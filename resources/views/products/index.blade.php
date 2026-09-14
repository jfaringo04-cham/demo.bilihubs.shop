@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="row">
    <div class="col-lg-3">
      <div class="card border-0 shadow-sm rounded-2 mb-4">
        <div class="card-header bg-white border-0 py-3">
          <h5 class="mb-0 fw-semibold">Categories</h5>
        </div>
        <div class="list-group list-group-flush">
          @foreach($categories as $category)
            <a href="{{ route('products.index', ['category' => $category->id]) }}" 
               class="list-group-item list-group-item-action {{ request('category') == $category->id ? 'active' : '' }}">
              {{ $category->name }}
            </a>
          @endforeach
        </div>
      </div>
    </div>

    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="section-title mb-0">Products</h2>
        <span class="text-muted">{{ $products->total() }} products found</span>
      </div>

      <div class="row g-4">
        @forelse($products as $product)
          <div class="col-md-4 col-6">
            <a href="{{ route('products.show', $product) }}" class="text-decoration-none">
              <div class="card product-card h-100 border-0 shadow-sm">
                <div class="position-relative">
                  <img src="{{ $product->image_url }}" class="product-image" alt="{{ $product->alt_text ?? $product->name }}" style="height: 220px; object-fit: cover;">
                  @if($product->hasActiveDiscount())
                    <span class="badge bg-danger position-absolute" style="top: 12px; left: 12px; border-radius: 8px;">
                      -{{ rtrim(rtrim(number_format($product->discount_percent, 2), '0'), '.') }}%
                    </span>
                  @endif
                </div>
                <div class="card-body">
                  <h6 class="fw-semibold text-slate-800 mb-2" style="min-height: 40px;">{{ $product->name }}</h6>
                  @if($product->hasActiveDiscount())
                    <p class="price mb-1">
                      <span class="text-danger">&#8369;{{ number_format($product->effective_price, 2) }}</span>
                      <small class="text-muted text-decoration-line-through ms-1">&#8369;{{ number_format($product->price, 2) }}</small>
                    </p>
                  @else
                    <p class="price mb-1">&#8369;{{ number_format($product->price, 2) }}</p>
                  @endif
                  @if($product->stock > 0)
                    <small class="text-muted">{{ $product->category->name ?? 'Uncategorized' }}</small>
                  @else
                    <span class="badge bg-danger">Sold Out</span>
                  @endif
                </div>
              </div>
            </a>
          </div>
        @empty
          <div class="col-12 text-center py-5">
            <i class="bi bi-box display-1 text-muted"></i>
            <p class="text-muted mt-3">No products found.</p>
          </div>
        @endforelse
      </div>

      <div class="mt-4">
        {{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}
      </div>
    </div>
  </div>
</div>
@endsection
