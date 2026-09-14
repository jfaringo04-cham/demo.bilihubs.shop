@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="card mb-4">
    <div class="card-body">
      <div class="d-flex align-items-center gap-4">
        @if($seller->logo)
          <img src="{{ asset('storage/' . $seller->logo) }}" alt="{{ $seller->business_name }}" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
        @else
          <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:80px;height:80px;font-size:2rem;">
            {{ substr($seller->name, 0, 1) }}
          </div>
        @endif
        <div>
          <h2 class="mb-1">{{ $seller->business_name ?? $seller->name }}</h2>
          <p class="text-muted mb-1">{{ $seller->email }}</p>
          <p class="text-muted mb-1">
            <i class="bi bi-geo-alt"></i>
            {{ $seller->street_address ? $seller->street_address . ', ' : '' }}{{ $seller->barangay_name ?? $seller->barangay }}, {{ $seller->municipality_name ?? $seller->municipality }}, {{ $seller->province_name ?? $seller->province }}
          </p>
          <div class="d-flex gap-3">
            <span><strong>{{ $totalProducts }}</strong> Products</span>
            <span><strong>{{ number_format($averageRating ?? 0, 1) }}</strong> <i class="bi bi-star-fill text-warning"></i> ({{ $totalReviews }} reviews)</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <h3 class="mb-3">Products by {{ $seller->business_name ?? $seller->name }}</h3>

  @if($products->count() > 0)
    <div class="row g-4">
      @foreach($products as $product)
        <div class="col-md-3 col-6">
          <a href="{{ route('products.show', $product) }}" class="text-decoration-none">
            <div class="card product-card h-100 border-0 shadow-sm">
              <img src="{{ $product->image_url }}" class="product-image" alt="{{ $product->alt_text ?? $product->name }}">
              <div class="card-body">
                <h6 class="card-title text-dark">{{ $product->name }}</h6>
                <p class="price">&#8369;{{ number_format($product->price, 2) }}</p>
                @if($product->reviews->count() > 0)
                  <small class="text-muted">
                    <i class="bi bi-star-fill text-warning"></i>
                    {{ number_format($product->reviews->avg('rating'), 1) }} ({{ $product->reviews->count() }})
                  </small>
                @endif
              </div>
            </div>
          </a>
        </div>
      @endforeach
    </div>
    <div class="mt-4">{{ $products->links('pagination::bootstrap-5') }}</div>
  @else
    <div class="text-center py-5">
      <i class="bi bi-shop fs-1 text-muted"></i>
      <p class="text-muted mt-2">This seller has no products yet.</p>
    </div>
  @endif
</div>
@endsection
