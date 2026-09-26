@extends('layouts.app')

@section('content')
<div class="container py-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
      <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
      <li class="breadcrumb-item active">{{ $product->name }}</li>
    </ol>
  </nav>

  <div class="row g-5">
    <div class="col-lg-6">
      @php
        $galleryImages = $product->images->count() > 0
          ? $product->images
          : collect([(object)['url' => $product->image ? asset('storage/' . $product->image) : 'https://via.placeholder.com/600x400?text=No+Image', 'alt_text' => $product->alt_text ?? $product->name]]);
      @endphp
      <img id="main-product-image" src="{{ $galleryImages->first()->url }}" class="img-fluid rounded-3 shadow-sm" alt="{{ $galleryImages->first()->alt_text ?? $product->name }}" style="max-height: 500px; object-fit: cover; width: 100%;">
        @if($galleryImages->count() > 1)
        <div class="d-flex gap-2 mt-3">
          @foreach($galleryImages as $gImg)
            <img src="{{ $gImg->url }}" class="rounded-2 border" style="width: 72px; height: 72px; object-fit: cover; cursor: pointer;" onclick="document.getElementById('main-product-image').src='{{ $gImg->url }}'" alt="{{ $gImg->alt_text ?? $product->name }}">
          @endforeach
        </div>
      @endif
      @if($product->hasVideo())
        <div class="mt-3">
          <video controls class="img-fluid rounded-3 shadow-sm" style="max-height: 400px;" id="product-video">
            <source src="{{ $product->video_url }}" type="video/mp4">
            Your browser does not support the video tag.
          </video>
        </div>
      @endif
      @if($product->hasSecondaryImage())
        <div class="mt-3">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#secondaryImageModal">
            <i class="bi bi-eye"></i> View Additional Image
          </button>
        </div>
      @endif
    </div>

    <div class="col-lg-6">
      <h1 class="fw-bold text-slate-900">{{ $product->name }}</h1>
      
      @if($product->hasActiveDiscount())
        <div class="d-flex align-items-center gap-3 mt-3">
          <span class="price text-danger fs-3 fw-bold" id="display-price">&#8369;{{ number_format(($product->effective_price_minor / 100), 2) }}</span>
          <small class="text-muted text-decoration-line-through fs-5">&#8369;{{ number_format(($product->price_minor / 100), 2) }}</small>
          <span class="badge bg-danger rounded-pill">-{{ rtrim(rtrim(number_format($product->discount_percent, 2), '0'), '.') }}%</span>
        </div>
        @if($product->discount_ends_at)
          <small class="text-muted mt-2 d-block"><i class="bi bi-clock me-1"></i>Sale ends {{ $product->discount_ends_at->format('M d, Y g:i A') }}</small>
        @endif
      @else
      <p class="price fs-3 mt-3" id="display-price">&#8369;{{ number_format(($product->price_minor / 100), 2) }}</p>
      @endif

      <div class="mt-4">
        <span class="text-muted d-block mb-1">Category: <span class="text-slate-800">{{ $product->category->name ?? 'Uncategorized' }}</span></span>
        <span class="text-muted d-block mb-1">
          Seller:
          @if($product->seller)
            <a href="{{ route('seller.storefront', $product->sellerUser) }}" class="text-sky-500">
              {{ $product->seller->name ?? $product->sellerUser->business_name ?? $product->sellerUser->name ?? 'Unknown' }}
            </a>
          @else
            <span class="text-slate-800">{{ $product->sellerUser->business_name ?? $product->sellerUser->name ?? 'Unknown' }}</span>
          @endif
        </span>
        @if($product->stock > 0)
          <span class="text-muted d-block" id="stock-display">
            Stock: <span class="text-slate-800" id="stock-amount">{{ $product->stock }} units</span>
          </span>
        @else
          <span class="badge bg-danger rounded-pill" id="stock-display">Sold Out</span>
        @endif
        <div id="selected-variant-info" class="mt-2" style="display:none;">
          <span class="text-muted small">Selected:</span>
          <span class="fw-medium small" id="selected-variant-name"></span>
        </div>
      </div>

      @php
        $hasAnySize = $product->sizes->count() > 0;
        $hasAvailableSize = !$hasAnySize || $product->sizes->contains(function($size) { return $size->pivot->stock > 0; });
        $canPurchase = $product->stock > 0 && $hasAvailableSize;
        $productMainImageUrl = $product->image_url;
      @endphp

      @if($product->variations->count() > 1)
        <div class="mt-4">
          <label class="form-label fw-medium mb-2">Options</label>
          <div class="d-flex flex-wrap gap-2" id="variation-selector">
            @foreach($product->variations as $variation)
              @php
                $displayName = $variation->display_name ?: $variation->name;
                $variantPrice = $variation->price_minor !== null ? ($variation->effective_price_minor / 100) : ($product->effective_price_minor / 100);
                $variantOriginalPrice = $variation->price_minor !== null ? ($variation->price_minor / 100) : ($product->price_minor / 100);
              @endphp
              <div class="border rounded-2 p-2 text-center cursor-pointer variation-thumb {{ $variation->stock > 0 ? '' : 'variation-sold-out' }}"
                   style="min-width: 120px; min-height: 140px;"
                   data-variation-id="{{ $variation->id }}"
                   data-variation-stock="{{ $variation->stock }}"
                   data-variation-price="{{ $variantPrice }}"
                   data-variation-original-price="{{ $variantOriginalPrice }}"
                   data-variation-image="{{ $variation->image_url }}"
                   data-variation-name="{{ $displayName }}"
                   data-variation-color="{{ $variation->color ?? '' }}"
                   data-variation-size="{{ $variation->size ?? '' }}">
                <img src="{{ $variation->image_url }}" class="rounded" style="width: 50px; height: 50px; object-fit: cover;" alt="{{ $displayName }}">
                <div class="fw-medium mt-2 small">{{ $displayName }}</div>
                <div class="fw-bold text-sky-600 mb-1 small">&#8369;{{ number_format($variantPrice, 2) }}</div>
                @if($variation->stock > 0)
                <div class="text-muted small">{{ $variation->stock }} left</div>
                @else
                  <div class="text-danger small fw-medium">Sold Out</div>
                @endif
              </div>
            @endforeach
          </div>
          <input type="hidden" name="variant_id" id="selected-variation-id" value="">
        </div>
      @endif

      @if($hasAnySize)
        <div class="mt-4">
          <label for="size_id" class="form-label fw-medium mb-2">Size</label>
          <select name="size_id" id="size_id" class="form-select rounded-xl" {{ $canPurchase ? '' : 'disabled' }}>
            <option value="" data-stock="0">Select Size</option>
            @foreach($product->sizes as $size)
              @if($size->pivot->stock > 0)
                <option value="{{ $size->id }}" data-stock="{{ $size->pivot->stock }}">
                  {{ $size->name }} ({{ $size->pivot->stock }} left)
                </option>
              @else
                <option value="{{ $size->id }}" data-stock="0" disabled>
                  {{ $size->name }} — Sold Out
                </option>
              @endif
            @endforeach
          </select>
        </div>
      @endif

      @if($product->stock > 0)
        <div class="mt-4 pt-4 border-top">
          @auth
            @if(Auth::user()->isCustomer())
              <div class="row g-3 align-items-end">
                <div class="col-md-4">
                  <label for="cart-quantity" class="form-label fw-medium">Quantity</label>
                  <input type="number" name="quantity" id="cart-quantity" class="form-control rounded-xl" value="1" min="1" max="{{ $product->stock }}" {{ $canPurchase ? '' : 'disabled' }}>
                </div>
                <div class="col-md-8 d-flex gap-2">
                  <button type="button" class="btn btn-secondary rounded-xl" id="cart-btn" {{ $canPurchase ? '' : 'disabled' }} onclick="addToCart()">
                    <i class="bi bi-cart-plus"></i> Add to Cart
                  </button>
                  <button type="button" class="btn btn-primary rounded-xl px-4" id="buy-btn" {{ $canPurchase ? '' : 'disabled' }} onclick="placeOrder()">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Place Order
                  </button>
                </div>
              </div>
            @endif
          @else
            <a href="{{ route('login') }}" class="btn btn-primary rounded-xl px-4">Login to Purchase</a>
          @endauth

          <div id="purchase-forms" class="d-none">
            <form action="{{ route('cart.store', $product) }}" method="POST" id="cart-form">
              @csrf
              <input type="hidden" name="size_id" id="cart-size-id" value="">
              <input type="hidden" name="variant_id" id="cart-variation-id" value="">
              <input type="hidden" name="quantity" id="cart-submit-qty" value="1">
            </form>
            <form action="{{ route('buyNow', $product) }}" method="POST" id="buynow-form">
              @csrf
              <input type="hidden" name="size_id" id="buy-size-id" value="">
              <input type="hidden" name="variant_id" id="buy-variation-id" value="">
              <input type="hidden" name="quantity" id="buy-quantity" value="1">
            </form>
          </div>
        </div>
      @else
        <div class="mt-4">
          <span class="badge bg-danger rounded-pill fs-6"><i class="bi bi-x-circle"></i> Sold Out</span>
        </div>
      @endif
      @if(!$canPurchase)
        <p class="text-danger mt-2">This product is currently out of stock.</p>
      @endif

      <hr class="my-4">

      <p class="text-slate-600">{{ $product->description ?? 'No description available.' }}</p>
    </div>
  </div>

  @if($relatedProducts->count() > 0)
    <div class="mt-5">
      <h3 class="fw-bold text-slate-900 mb-3">Related Products</h3>
      <div class="row g-4 mt-2">
        @foreach($relatedProducts as $related)
          <div class="col-md-3 col-6">
            <a href="{{ route('products.show', $related) }}" class="text-decoration-none">
              <div class="card product-card h-100 border-0 shadow-sm">
                <div class="position-relative">
                  <img src="{{ $related->image_url }}" class="product-image" alt="{{ $related->alt_text ?? $related->name }}" style="height: 180px; object-fit: cover;">
                  @if($related->hasActiveDiscount())
                    <span class="badge bg-danger position-absolute" style="top: 10px; left: 10px; border-radius: 8px;">
                      -{{ rtrim(rtrim(number_format($related->discount_percent, 2), '0'), '.') }}%
                    </span>
                  @endif
                </div>
                <div class="card-body">
                  <h6 class="fw-semibold text-slate-800 mb-2">{{ $related->name }}</h6>
                  @if($related->hasActiveDiscount())
                    <p class="price mb-1">
                      <span class="text-danger">&#8369;{{ number_format(($related->effective_price_minor / 100), 2) }}</span>
                      <small class="text-muted text-decoration-line-through ms-1">&#8369;{{ number_format(($related->price_minor / 100), 2) }}</small>
                    </p>
                  @else
                    <p class="price mb-1">&#8369;{{ number_format(($related->price_minor / 100), 2) }}</p>
                  @endif
                </div>
              </div>
            </a>
          </div>
        @endforeach
      </div>
    </div>
  @endif
</div>

@if($product->hasSecondaryImage() || $product->images->count() > 0 || $product->image)
<div class="modal fade" id="secondaryImageModal" tabindex="-1" aria-labelledby="secondaryImageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen modal-dialog-scrollable">
    <div class="modal-content bg-dark border-0">
      <div class="modal-header bg-dark border-0 justify-content-between align-items-center px-4 py-3">
        <h5 class="modal-title text-white mb-0" id="secondaryImageModalLabel">
          <i class="bi bi-image"></i> {{ $product->name }}
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 bg-black d-flex align-items-center justify-content-center">
        @php
          $allImages = [];
          foreach ($galleryImages as $gImg) {
            $allImages[] = ['url' => $gImg->url, 'alt' => $gImg->alt_text ?? $product->name];
          }
          if ($product->hasSecondaryImage()) {
            $allImages[] = ['url' => $product->secondary_image_url, 'alt' => 'Additional image: ' . $product->name];
          }
          $totalImages = count($allImages);
        @endphp

        <div id="lightbox-carousel" class="carousel slide w-100" data-bs-interval="false" data-bs-wrap="false">
          <div class="carousel-inner">
            @foreach($allImages as $idx => $img)
              <div class="carousel-item{{ $idx === 0 ? ' active' : '' }}">
                <div class="d-flex align-items-center justify-content-center" style="min-height: 70vh;">
                  <img src="{{ $img['url'] }}" class="img-fluid" style="max-height: 90vh; max-width: 100%; object-fit: contain;" alt="{{ $img['alt'] }}">
                </div>
              </div>
            @endforeach
          </div>
          @if($totalImages > 1)
            <button class="carousel-control-prev" type="button" data-bs-target="#lightbox-carousel" data-bs-slide="prev">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#lightbox-carousel" data-bs-slide="next">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Next</span>
            </button>
          @endif
        </div>
      </div>
      <div class="modal-footer bg-dark border-0 justify-content-center px-4 py-3">
        <span class="text-white small">
          <span id="current-image-index">1</span> / <span id="total-images">{{ $totalImages }}</span>
        </span>
      </div>
    </div>
  </div>
</div>
@endif

  <script id="product-page-data" type="application/json">
    @php $productPageData = [
      'variations' => $variationsJson ?? [],
      'mainImageUrl' => $productMainImageUrl ?? $product->image_url,
      'stock' => $product->stock,
    ]; @endphp @json($productPageData)
  </script>

  @push('scripts')
    @vite('resources/js/buyer/products.js')
  @endpush

  @endsection



