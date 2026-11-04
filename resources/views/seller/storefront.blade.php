@extends('layouts.app')

@push('styles')
@vite('resources/css/seller/storefront.css')
@endpush

@section('content')
@php
    $shopName = $seller->business_name ?? $seller->store_name ?? $seller->name;
    $categoryName = $primaryCategory?->name ?? 'Marketplace Store';

    $themeContent = [
        'fashion' => [
            'eyebrow' => 'Style curated for you',
            'headline' => 'Fresh looks from ' . $shopName,
            'description' => 'Discover apparel and accessories from this seller.',
            'icon' => 'bi-bag-heart-fill',
            'section' => 'Featured Styles',
        ],
        'tech' => [
            'eyebrow' => 'Smart picks, modern living',
            'headline' => 'Upgrade your everyday tech',
            'description' => 'Browse gadgets, devices, and accessories from ' . $shopName . '.',
            'icon' => 'bi-cpu-fill',
            'section' => 'Latest Tech',
        ],
        'food' => [
            'eyebrow' => 'Good finds for every craving',
            'headline' => 'Taste something worth sharing',
            'description' => 'Explore food and gourmet selections from this shop.',
            'icon' => 'bi-cup-hot-fill',
            'section' => 'Food Favorites',
        ],
        'home' => [
            'eyebrow' => 'Make every space feel better',
            'headline' => 'Find your next home favorite',
            'description' => 'Shop practical and stylish home and garden finds.',
            'icon' => 'bi-house-heart-fill',
            'section' => 'Home Favorites',
        ],
        'kids' => [
            'eyebrow' => 'Little things, happy moments',
            'headline' => 'Made for growing families',
            'description' => 'Browse useful and delightful picks for kids and babies.',
            'icon' => 'bi-balloon-heart-fill',
            'section' => 'Little Favorites',
        ],
        'pets' => [
            'eyebrow' => 'For happy paws and tails',
            'headline' => 'Everything for your pet',
            'description' => 'Find food, care, grooming, and pet essentials in one shop.',
            'icon' => 'bi-heart-fill',
            'section' => 'Pet Favorites',
        ],
        'beauty' => [
            'eyebrow' => 'Care that fits your routine',
            'headline' => 'Beauty and wellness essentials',
            'description' => 'Discover skincare, personal care, grooming, and wellness finds.',
            'icon' => 'bi-stars',
            'section' => 'Beauty Picks',
        ],
        'luxury' => [
            'eyebrow' => 'Details that make a statement',
            'headline' => 'Timeless pieces, modern style',
            'description' => 'Explore jewelry and watches selected by this seller.',
            'icon' => 'bi-gem',
            'section' => 'Signature Pieces',
        ],
        'sport' => [
            'eyebrow' => 'Move more, explore farther',
            'headline' => 'Gear up for your next activity',
            'description' => 'Shop sports, fitness, and outdoor essentials.',
            'icon' => 'bi-trophy-fill',
            'section' => 'Active Picks',
        ],
        'books' => [
            'eyebrow' => 'Stories, learning, entertainment',
            'headline' => 'Find your next great read or watch',
            'description' => 'Browse books, media, games, and educational content.',
            'icon' => 'bi-book-half',
            'section' => 'Popular Reads & Media',
        ],
        'office' => [
            'eyebrow' => 'Workspaces that work better',
            'headline' => 'Build a more comfortable setup',
            'description' => 'Shop furniture and equipment for home and office workspaces.',
            'icon' => 'bi-lamp-fill',
            'section' => 'Workspace Picks',
        ],
        'default' => [
            'eyebrow' => 'Welcome to our shop',
            'headline' => 'Discover products from ' . $shopName,
            'description' => 'Browse the latest approved products available from this seller.',
            'icon' => 'bi-shop',
            'section' => 'Shop Products',
        ],
    ][$theme] ?? null;
@endphp

<div class="seller-storefront theme-{{ $theme }}">
    <section class="store-hero">
        <div class="store-hero-pattern" aria-hidden="true"></div>
        <div class="container py-4 py-lg-5 position-relative">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-3 gap-md-4">
                        <div class="store-logo-wrap flex-shrink-0">
                            @if($seller->logo_url)
                                <img src="{{ $seller->logo_url }}" alt="{{ $shopName }}" class="store-logo">
                            @else
                                <div class="store-logo store-logo-placeholder">
                                    {{ strtoupper(substr($shopName, 0, 1)) }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <span class="store-eyebrow">{{ $themeContent['eyebrow'] }}</span>
                            <h1 class="store-title">{{ $shopName }}</h1>
                            <div class="store-category-label">
                                <i class="bi {{ $themeContent['icon'] }}"></i>
                                {{ $categoryName }}
                            </div>
                            <p class="store-tagline">{{ $themeContent['description'] }}</p>

                            <div class="store-meta">
                                <span><i class="bi bi-geo-alt-fill"></i>
                                    {{ collect([$seller->barangay_name ?? $seller->barangay, $seller->municipality_name ?? $seller->municipality, $seller->province_name ?? $seller->province])->filter()->implode(', ') ?: 'Location not specified' }}
                                </span>
                                <span><i class="bi bi-box-seam-fill"></i> {{ $totalProducts }} Products</span>
                                <span><i class="bi bi-star-fill"></i> {{ number_format($averageRating ?? 0, 1) }} ({{ $totalReviews }} reviews)</span>
                            </div>

                            <div class="mt-3 d-flex flex-wrap gap-2">
                                @auth
                                    @if(Auth::user()->isCustomer() && Auth::id() !== $seller->id)
                                        <a
                                            href="{{ route('buyer.messages.show', $seller) }}"
                                            class="btn store-primary-btn rounded-pill px-4"
                                        >
                                            <i class="bi bi-chat-dots-fill me-2"></i>
                                            Message Seller
                                        </a>
                                    @elseif(Auth::id() === $seller->id)
                                        <a
                                            href="{{ route('seller.messages.index') }}"
                                            class="btn btn-outline-secondary rounded-pill px-4"
                                        >
                                            <i class="bi bi-chat-dots me-2"></i>
                                            Open Messages
                                        </a>
                                    @endif
                                @else
                                    <a
                                        href="{{ route('login') }}"
                                        class="btn store-primary-btn rounded-pill px-4"
                                    >
                                        <i class="bi bi-chat-dots-fill me-2"></i>
                                        Login to Message Seller
                                    </a>
                                @endauth
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="hero-feature-card">
                        <div class="hero-feature-icon">
                            <i class="bi {{ $themeContent['icon'] }}"></i>
                        </div>
                        <div>
                            <div class="small text-uppercase fw-semibold opacity-75">{{ $categoryName }}</div>
                            <div class="hero-feature-title">{{ $themeContent['headline'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if($sellerCategories->isNotEmpty())
        <section class="store-navigation border-bottom">
            <div class="container py-3">
                <div class="category-scroll">
                    <a href="{{ route('seller.storefront', $seller) }}"
                       class="store-category-pill {{ !$selectedCategory ? 'active' : '' }}">
                        All Products
                    </a>
                    @foreach($sellerCategories as $category)
                        <a href="{{ route('seller.storefront', ['seller' => $seller, 'category' => $category->id]) }}"
                           class="store-category-pill {{ $selectedCategory?->id === $category->id ? 'active' : '' }}">
                            {{ $category->name }}
                        </a>
                    @endforeach
                </div>

                @if($primaryCategory && $primaryCategory->subcategories->isNotEmpty())
                    <div class="subcategory-scroll mt-2">
                        <a href="{{ route('seller.storefront', ['seller' => $seller] + ($selectedCategory ? ['category' => $selectedCategory->id] : [])) }}"
                           class="store-subcategory-link {{ !$selectedSubcategory ? 'active' : '' }}">
                            All {{ $primaryCategory->name }}
                        </a>
                        @foreach($primaryCategory->subcategories as $subcategory)
                            <a href="{{ route('seller.storefront', ['seller' => $seller, 'category' => $primaryCategory->id, 'subcategory' => $subcategory->id]) }}"
                               class="store-subcategory-link {{ $selectedSubcategory?->id === $subcategory->id ? 'active' : '' }}">
                                {{ $subcategory->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    <main class="container py-4 py-lg-5">
        @if($seller->seller?->isOnVacation())
            <div class="alert alert-warning mb-4" role="status">
                <div class="fw-bold mb-1">
                    <i class="bi bi-calendar-x me-2"></i>
                    This Seller Is On Vacation
                </div>
                <div>
                    This shop is temporarily not accepting new orders.
                    Products are available for viewing only.
                </div>
            </div>
        @endif
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
            <div>
                <span class="products-kicker">{{ $selectedSubcategory?->name ?? $selectedCategory?->name ?? $categoryName }}</span>
                <h2 class="products-heading mb-1">{{ $themeContent['section'] }}</h2>
                <p class="text-muted mb-0">
                    {{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }} listed
                </p>
            </div>
        </div>

        @if($products->count() > 0)
            <div class="row g-3 g-lg-4">
                @foreach($products as $product)
                    <div class="col-6 col-md-4 col-xl-3">
                        <a href="{{ route('products.show', $product) }}" class="store-product-link">
                            <article class="store-product-card h-100">
                                <div class="product-media">
                                    <img src="{{ $product->image_url }}"
                                         class="store-product-image"
                                         alt="{{ $product->alt_text ?? $product->name }}"
                                         loading="lazy">
                                    @if((float) $product->discount_percent > 0)
                                        <span class="discount-badge">-{{ rtrim(rtrim(number_format((float) $product->discount_percent, 2), '0'), '.') }}%</span>
                                    @endif
                                </div>

                                <div class="product-info">
                                    <div class="product-category-name">
                                        {{ $product->category?->name ?? $categoryName }}
                                    </div>
                                    <h3 class="product-name">{{ $product->name }}</h3>
                                    @if($product->seller?->isOnVacation())
                                        <div class="mb-2">
                                            <span class="badge text-bg-warning">
                                                Seller on Vacation
                                            </span>
                                        </div>
                                    @endif
                                    <div class="d-flex justify-content-between align-items-end gap-2 mt-auto">
                                        <div>
                                            <div class="product-price">&#8369;{{ number_format(($product->effective_price_minor / 100), 2) }}</div>
                                            @if($product->reviews->count() > 0)
                                                <div class="product-rating">
                                                    <i class="bi bi-star-fill"></i>
                                                    {{ number_format($product->reviews->avg('rating'), 1) }}
                                                    <span>({{ $product->reviews->count() }})</span>
                                                </div>
                                            @else
                                                <div class="product-rating no-rating">New item</div>
                                            @endif
                                        </div>
                                        <span class="product-arrow"><i class="bi bi-arrow-up-right"></i></span>
                                    </div>
                                </div>
                            </article>
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 storefront-pagination">
                {{ $products->links('pagination::bootstrap-5') }}
            </div>
        @else
            <div class="store-empty-state">
                <div class="empty-icon"><i class="bi bi-bag-x"></i></div>
                <h3>No products in this section yet</h3>
                <p class="text-muted mb-3">Try another category or view all products from this shop.</p>
                <a href="{{ route('seller.storefront', $seller) }}" class="btn store-primary-btn">View all products</a>
            </div>
        @endif
    </main>
</div>
@endsection
