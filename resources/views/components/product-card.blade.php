@props(['product', 'showTrending' => false, 'trendingRank' => null])

@php
    $discount = $product->discount_percent;
    $originalPrice = $product->price_minor / 100;
    $salePrice = $product->effective_price_minor / 100;
    $rating = $product->reviews_avg_rating ?? 0;
    $reviewCount = $product->reviews_count ?? 0;
    $sold = $product->sold_count ?? 0;
    $stock = $product->stock ?? 0;
    $sellerOnVacation = $product->seller?->isOnVacation() ?? false;

    $total = $sold + $stock;
    $progress = $total > 0 ? min(($sold / $total) * 100, 100) : 0;
    $progress = $progress ?: 0;
    $rating = round($rating, 1);
    $fullStars = (int) floor($rating);
    $hasHalfStar = $rating - $fullStars >= 0.5;
    $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
@endphp

<div class="product-card-flash">
    <div class="product-image-container">
        <a href="{{ route('products.show', $product) }}" class="d-block text-decoration-none" aria-label="View {{ $product->name }}">
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">
        </a>

        @if($product->hasActiveDiscount())
            <span class="discount-badge">-{{ rtrim(rtrim(number_format($discount, 2), '0'), '.') }}%</span>
        @endif

        @if($showTrending && $trendingRank && $trendingRank <= 3)
            <span class="trending-badge">🔥 Trending</span>
        @endif

        @php
    $isWishlisted = auth()->check()
        && auth()->user()->wishlists()
            ->where('product_id', $product->id)
            ->exists();
@endphp

<button
    type="button"
    class="wishlist-btn {{ $isWishlisted ? 'active' : '' }}"
    onclick="toggleWishlist(this)"
    data-product-id="{{ $product->id }}"
    aria-label="{{ $isWishlisted ? 'Remove from wishlist' : 'Add to wishlist' }}"
>
    <i class="bi {{ $isWishlisted ? 'bi-heart-fill' : 'bi-heart' }}"></i>
</button>
    </div>

    <div class="product-info">
        @if($sellerOnVacation)
            <div class="mb-2">
                <span class="badge text-bg-warning">
                    <i class="bi bi-calendar-x me-1"></i>
                    Seller on Vacation
                </span>
            </div>
        @endif
        @if($product->category)
            <a href="{{ route('products.index', ['category' => $product->category->id]) }}"
               class="product-category text-muted text-decoration-none">
                {{ $product->category->name }}
            </a>
        @else
            <span class="product-category text-muted">Uncategorized</span>
        @endif
        <h6 class="product-name" title="{{ $product->name }}">
            <a href="{{ route('products.show', $product) }}"
               class="text-reset text-decoration-none stretched-product-link">
                {{ $product->name }}
            </a>
        </h6>

        <div class="rating-display">
            @for($i = 0; $i < $fullStars; $i++)
                <i class="bi bi-star-fill"></i>
            @endfor
            @if($hasHalfStar)
                <i class="bi bi-star-half"></i>
            @endif
            @for($i = 0; $i < $emptyStars; $i++)
                <i class="bi bi-star"></i>
            @endfor
            <span class="rating-text">{{ number_format($rating, 1) }}</span>
            <span class="review-count">({{ $reviewCount }})</span>
        </div>

        <div class="product-price">
            <span class="sale-price">&#8369;{{ number_format($salePrice, 2) }}</span>
            @if($product->hasActiveDiscount())
                <span class="original-price text-muted">&#8369;{{ number_format($originalPrice, 2) }}</span>
            @endif
        </div>

        <span class="sold-info">{{ $sold }} sold</span>
        <div class="progress-bar-custom">
            <div class="progress-fill" style="width: {{ $progress }}%"></div>
        </div>

        <a href="{{ route('products.show', $product) }}"
           class="add-to-cart-btn w-100 mt-2 d-inline-flex align-items-center justify-content-center text-decoration-none">
            @if($sellerOnVacation)
                <i class="bi bi-eye me-1"></i> View Product - Unavailable
            @else
                <i class="bi bi-cart me-1"></i> View / Add to Cart
            @endif
        </a>
    </div>
</div>

