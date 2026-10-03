@props(['product', 'showTrending' => false, 'trendingRank' => null])

@php
    $discount = $product->discount_percent;
    $originalPrice = $product->price_minor / 100;
    $salePrice = $product->effective_price_minor / 100;
    $rating = $product->reviews_avg_rating ?? 0;
    $reviewCount = $product->reviews_count ?? 0;
    $sold = $product->sold_count ?? 0;
    $stock = $product->stock ?? 0;

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
        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">

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
        <span class="product-category text-muted">{{ $product->category->name ?? 'Uncategorized' }}</span>
        <h6 class="product-name" title="{{ $product->name }}">{{ $product->name }}</h6>

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

        <button type="button" class="add-to-cart-btn w-100 mt-2">
            <i class="bi bi-cart me-1"></i> Add to Cart
        </button>
    </div>
</div>

