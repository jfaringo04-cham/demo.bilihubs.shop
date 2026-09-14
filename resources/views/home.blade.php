@extends('layouts.app')

@section('content')
<!-- Hero Section -->
<section class="hero-gradient py-5 position-relative overflow-hidden">
  <div class="container py-5 position-relative" style="z-index: 2;">
    <div class="row align-items-center">
      <div class="col-lg-6">
        <img src="{{ asset('images/bilihublogo.png') }}" alt="Bili Hub" class="mb-4" style="height: 80px; width: auto;">
        <h1 class="display-5 fw-bold text-slate-900 mb-3">Your One-Stop Marketplace</h1>
        <p class="lead text-muted mb-4">Connecting buyers, sellers, and riders across the Philippines. Fast delivery, trusted products, verified sellers.</p>
        <div class="d-flex gap-2 mb-3">
          <a href="{{ route('products.index') }}" class="btn btn-primary btn-lg rounded-xl px-4">Shop Now</a>
          <a href="{{ route('register') }}" class="btn btn-secondary btn-lg rounded-xl px-4">Join Now</a>
        </div>

        <!-- Trust Badges -->
        <div class="d-flex gap-4 mt-3">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-truck fs-4 text-sky-500"></i>
            <span class="small text-muted">Fast Delivery</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-shield-check fs-4 text-sky-500"></i>
            <span class="small text-muted">Verified Sellers</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-currency-exchange fs-4 text-sky-500"></i>
            <span class="small text-muted">Secure Payments</span>
          </div>
        </div>
      </div>
      <div class="col-lg-6 d-none d-lg-block">
        <div class="text-center">
          <img src="https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?auto=format&fit=crop&w=800&q=80" alt="Shopping" class="img-fluid rounded-3 shadow-sm">
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Categories -->
<section class="py-5 bg-white">
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="section-title mb-0">Shop by Category</h2>
      <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm rounded-xl">View All</a>
    </div>
    <div class="row g-4">
      @php
        $categoryIcons = [
          'pet supplies' => 'bi-box',
          'kids and baby' => 'bi-emoji-smile',
          'electronics and gadgets' => 'bi-laptop',
          'home and garden' => 'bi-house',
          'women\'s apparel' => 'bi-person-heart',
          'sports and outdoors' => 'bi-trophy',
          'men\'s apparel' => 'bi-person-fill',
          'health and beauty' => 'bi-heart-pulse',
        ];
      @endphp
      @foreach($categories as $category)
        @php
          $icon = $categoryIcons[strtolower($category->name)] ?? 'bi-tag';
        @endphp
        <div class="col-6 col-md-3">
          <a href="{{ route('products.index', ['category' => $category->id]) }}" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm text-center py-4 hover-shadow">
              <div class="card-body">
                <div class="rounded-circle bg-sky-100 text-sky-500 d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                  <i class="bi {{ $icon }} fs-3"></i>
                </div>
                <h6 class="fw-semibold text-slate-800 mb-0">{{ $category->name }}</h6>
              </div>
            </div>
          </a>
        </div>
      @endforeach
    </div>
  </div>
</section>

<!-- Featured Products -->
<section class="py-5">
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="section-title mb-0">Featured Products</h2>
      <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm rounded-xl">View All</a>
    </div>
    <div class="row g-4">
      @foreach($products->take(8) as $product)
        <div class="col-6 col-md-3">
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
                <small class="text-muted">{{ $product->category->name ?? 'Uncategorized' }}</small>
              </div>
            </div>
          </a>
        </div>
      @endforeach
    </div>
  </div>
</section>

<!-- About / Mission Section -->
<section class="py-5 bg-slate-50" id="about">
  <div class="container py-4">
    <div class="row g-5">
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-2 h-100">
          <div class="card-body p-4">
            <h3 class="fw-bold text-sky-500 mb-3">Mission Statement</h3>
            <p class="typography-body mb-3">At BiliHub, our mission is to create a trusted and innovative marketplace that connects buyers, sellers, and riders in one seamless ecosystem. We aim to empower local entrepreneurs, provide convenient shopping experiences, and deliver reliable logistics all while fostering growth, inclusivity, and sustainability in e-commerce.</p>
            <h3 class="fw-bold text-sky-500 mb-3 mt-4">Vision Statement</h3>
            <p class="typography-body mb-0">We envision BiliHub as a leading online marketplace recognized for its simplicity, reliability, and community-driven approach. Our vision is to build a platform where opportunities thrive, transactions are effortless, and every Filipino can access and benefit from digital commerce locally and globally.</p>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-2 h-100">
          <div class="card-body p-4">
            <h3 class="fw-bold text-sky-500 mb-3">Core Values</h3>
            <div class="d-flex mb-3">
              <div class="flex-shrink-0">
                <div class="bg-sky-100 text-sky-600 rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px;">1</div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="fw-semibold mb-1">Customer First</h6>
                <p class="typography-small mb-0">We prioritize the needs, satisfaction, and trust of every customer above all else.</p>
              </div>
            </div>
            <div class="d-flex mb-3">
              <div class="flex-shrink-0">
                <div class="bg-sky-100 text-sky-600 rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px;">2</div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="fw-semibold mb-1">Integrity & Transparency</h6>
                <p class="typography-small mb-0">We conduct business honestly and openly. No hidden fees, no misleading information.</p>
              </div>
            </div>
            <div class="d-flex mb-3">
              <div class="flex-shrink-0">
                <div class="bg-sky-100 text-sky-600 rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px;">3</div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="fw-semibold mb-1">Quality & Reliability</h6>
                <p class="typography-small mb-0">We verify sellers, review listings, and ensure that what you see is exactly what you get.</p>
              </div>
            </div>
            <div class="d-flex mb-3">
              <div class="flex-shrink-0">
                <div class="bg-sky-100 text-sky-600 rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px;">4</div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="fw-semibold mb-1">Innovation & Growth</h6>
                <p class="typography-small mb-0">We continuously improve our platform adopting new technologies and better user experiences.</p>
              </div>
            </div>
            <div class="d-flex mb-3">
              <div class="flex-shrink-0">
                <div class="bg-sky-100 text-sky-600 rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px;">5</div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="fw-semibold mb-1">Inclusivity & Empowerment</h6>
                <p class="typography-small mb-0">We provide equal opportunities for all from small local sellers to big brands.</p>
              </div>
            </div>
            <div class="d-flex">
              <div class="flex-shrink-0">
                <div class="bg-sky-100 text-sky-600 rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px;">6</div>
              </div>
              <div class="flex-grow-1 ms-3">
                <h6 class="fw-semibold mb-1">Community & Collaboration</h6>
                <p class="typography-small mb-0">We grow together, customers, sellers, riders, and developers alike.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4 mt-2">
      <div class="col-12">
        <div class="card border-0 shadow-sm rounded-2">
          <div class="card-body p-4">
            <h3 class="fw-bold text-sky-500 mb-3">Company Goals</h3>
            <div class="row g-4">
              <div class="col-md-4">
                <h6 class="fw-semibold mb-2">Short-Term Goals</h6>
                <ul class="typography-small ps-3 mb-0">
                  <li>Fully launch BiliHub with complete core features</li>
                  <li>Onboard 500+ verified local sellers</li>
                  <li>Implement buyer & seller protection</li>
                  <li>Achieve mobile-first user experience</li>
                  <li>Establish delivery partnerships and tracking</li>
                </ul>
              </div>
              <div class="col-md-4">
                <h6 class="fw-semibold mb-2">Mid-Term Goals (2–3 Years)</h6>
                <ul class="typography-small ps-3 mb-0">
                  <li>Expand coverage to all major provinces and cities</li>
                  <li>Reach 10,000+ active sellers and 100,000+ monthly active buyers</li>
                  <li>Diversify into fresh goods, handmade products, and local artisan categories</li>
                  <li>Build in-house logistics</li>
                  <li>Become a recognized brand supporting local Filipino businesses</li>
                </ul>
              </div>
              <div class="col-md-4">
                <h6 class="fw-semibold mb-2">Long-Term Goals (5+ Years)</h6>
                <ul class="typography-small ps-3 mb-0">
                  <li>Rank among top 3 homegrown e-commerce platforms in the Philippines</li>
                  <li>Create 100,000+ livelihood opportunities</li>
                  <li>Lead in sustainable commerce and carbon-neutral operations</li>
                  <li>Expand regionally and export Filipino products</li>
                  <li>Innovate with AI recommendations and AR product previews</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-sky-500 text-white text-center">
  <div class="container py-4">
    <h2 class="display-5 fw-bold mb-3">Ready to Start Selling or Buying?</h2>
    <p class="lead mb-4 opacity-90">Join thousands of happy customers and sellers on BiliHub today!</p>
    <div class="d-flex justify-content-center gap-2">
      <a href="{{ route('register') }}" class="btn btn-light btn-lg rounded-xl px-4">Get Started</a>
      <a href="{{ route('products.index') }}" class="btn btn-outline-light btn-lg rounded-xl px-4">Shop Now</a>
    </div>
  </div>
</section>
@endsection
