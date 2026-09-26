@extends('layouts.app')

@push('styles')
  @vite('resources/css/buyer/home.css')
@endpush

@section('content')
  <!-- Hero Carousel Section -->
  <section class="hero-carousel position-relative overflow-hidden">
    <!-- Carousel Slides -->
    <div class="hero-carousel-slides position-absolute w-100 h-100" style="z-index: 1;">
      <div class="hero-carousel-slide active d-flex align-items-center" style="background-image: url('{{ asset('images/hero-marketplace.jpg') }}');">
        <div class="hero-slide-badge hero-badge position-absolute top-0 end-0 mt-3 me-3">Trending Now</div>
      </div>
      <div class="hero-carousel-slide d-flex align-items-center" style="background-image: url('{{ asset('images/hero-womens-fashion.jpg') }}');">
        <div class="hero-slide-badge hero-badge position-absolute top-0 end-0 mt-3 me-3">Women's Fashion</div>
      </div>
      <div class="hero-carousel-slide d-flex align-items-center" style="background-image: url('{{ asset('images/hero-mens-fashion.jpg') }}');">
        <div class="hero-slide-badge hero-badge position-absolute top-0 end-0 mt-3 me-3">Men's Collection</div>
      </div>
      <div class="hero-carousel-slide d-flex align-items-center" style="background-image: url('{{ asset('images/hero-electronics.jpg') }}');">
        <div class="hero-slide-badge hero-badge position-absolute top-0 end-0 mt-3 me-3">Electronics</div>
      </div>
      <div class="hero-carousel-slide d-flex align-items-center" style="background-image: url('{{ asset('images/hero-shoes-accessories.jpg') }}');">
        <div class="hero-slide-badge hero-badge position-absolute top-0 end-0 mt-3 me-3">Shoes & Accessories</div>
      </div>
    </div>

    <!-- Dark Overlay -->
    <div class="hero-carousel-overlay position-absolute w-100 h-100" style="z-index: 2;"></div>

    <!-- Content -->
    <div class="hero-carousel-content position-relative" style="z-index: 3;">
      <div class="container h-100">
        <div class="row h-100">
          <div class="col-lg-6 d-flex flex-column justify-content-center py-5">
            <h1 class="display-4 fw-bold text-white mb-3">Your One-Stop Marketplace</h1>
            <p class="lead text-white text-opacity-75 mb-4">Connecting buyers, sellers, and riders across the Philippines. Fast delivery, trusted products, verified sellers.</p>
            <div class="d-flex gap-2 mb-4 flex-wrap">
              <a href="{{ route('products.index') }}" class="btn btn-primary btn-lg rounded-xl px-4">Shop Now</a>
              <a href="{{ route('register') }}" class="btn btn-secondary btn-lg rounded-xl px-4">Join Now</a>
            </div>

            <!-- Trust Badges -->
            <div class="d-flex gap-4 mt-3 flex-wrap">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-truck fs-4 text-violet-300"></i>
                <span class="small text-white text-opacity-75">Fast Delivery</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-check fs-4 text-violet-300"></i>
                <span class="small text-white text-opacity-75">Verified Sellers</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-currency-exchange fs-4 text-violet-300"></i>
                <span class="small text-white text-opacity-75">Secure Payments</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigation Arrows -->
    <button type="button" class="hero-carousel-nav hero-carousel-prev position-absolute start-0 top-50 translate-middle-y ms-3 ms-lg-4" style="z-index: 4;" aria-label="Previous slide">
      <i class="bi bi-chevron-left fs-1"></i>
    </button>
    <button type="button" class="hero-carousel-nav hero-carousel-next position-absolute top-50 translate-middle-y end-0 me-3 me-lg-4" style="z-index: 4;" aria-label="Next slide">
      <i class="bi bi-chevron-right fs-1"></i>
    </button>

    <!-- Pagination Dots -->
    <div class="hero-carousel-dots d-flex justify-content-center gap-2 position-absolute bottom-0 mb-4" style="left: 50%; transform: translateX(-50%); z-index: 4;">
      <span class="hero-carousel-dot active" data-slide="0"></span>
      <span class="hero-carousel-dot" data-slide="1"></span>
      <span class="hero-carousel-dot" data-slide="2"></span>
      <span class="hero-carousel-dot" data-slide="3"></span>
      <span class="hero-carousel-dot" data-slide="4"></span>
    </div>
  </section>



  <!-- Categories -->
  <section class="py-5 bg-white" id="categories">
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
                  <div class="rounded-circle bg-violet-100 text-violet-500 d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
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

  <!-- Flash Deals Section -->
  @if($flashDeals && $flashDeals->count() > 0)
  <section class="flash-deals-section" id="flash-deals">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
          <span class="flash-deals-label">
            <i class="bi bi-lightning-charge fs-5"></i> TODAY'S DEALS
          </span>
          <h2 class="flash-deals-title mb-2">Flash Deals</h2>
          <p class="text-muted">Grab your favorites before the deals are gone.</p>
        </div>

        <div class="d-flex align-items-center gap-3 flex-wrap">
          <div class="d-flex align-items-center gap-2 text-muted small">
            <span>Ends in</span>
            <div class="countdown-timer">
              <div class="countdown-box">
                <div class="countdown-digit" id="countdown-hours">05</div>
                <span class="countdown-separator">:</span>
                <div class="countdown-digit" id="countdown-minutes">42</div>
                <span class="countdown-separator">:</span>
                <div class="countdown-digit" id="countdown-seconds">18</div>
              </div>
            </div>
          </div>
          <a href="{{ route('products.index') }}" class="flash-view-all">
            View All Deals <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>

      <div class="row g-4">
        @foreach($flashDeals as $product)
          <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
            <x-product-card :product="$product" />
          </div>
        @endforeach
      </div>
    </div>
  </section>
  @endif

  <!-- Trending Products Section -->
  @if($trendingProducts && $trendingProducts->count() > 0)
  <section class="trending-products-section" id="trending">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
          <span class="trending-label">
            <i class="bi bi-fire"></i> TRENDING NOW
          </span>
          <h2 class="trending-title mb-2">Trending Products</h2>
          <p class="text-muted">Discover what shoppers are loving on BiliHub.</p>
        </div>
        <a href="{{ route('products.index') }}" class="trending-view-all">
          View All Products <i class="bi bi-arrow-right"></i>
        </a>
      </div>

      <div class="trending-category-tabs mb-4" style="color: #111827;">
        <div class="d-flex gap-2 flex-wrap">
          <button type="button" class="trending-tab active" data-category="">
            All
          </button>
          @foreach($categories as $category)
            <button type="button" class="trending-tab" data-category="{{ $category->name }}">
              {{ $category->name }}
            </button>
          @endforeach
        </div>
      </div>

      <div class="trending-cards-wrapper">
        <div class="row g-4">
          @foreach($trendingProducts as $product)
            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 trending-product-card" data-category="{{ $product->category->name ?? 'Uncategorized' }}">
              <x-product-card :product="$product" :show-trending="true" :trending-rank="$loop->iteration" />
            </div>
          @endforeach
        </div>
        <div class="trending-empty-state d-none">
          <div class="text-center py-5">
            <i class="bi bi-box-seam display-4 text-muted mb-3"></i>
            <p class="text-muted">No trending products in this category yet.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  @endif

  <!-- Featured Products -->
  <section class="py-5" id="featured">
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

   <!-- About Section -->
   <section class="about-section" id="about">
     <div class="container">
       <!-- Section 1: About Introduction -->
       <div class="text-center mb-5">
         <span class="about-label d-inline-block mb-3">ABOUT US</span>
         <h2 class="about-heading mb-3">ABOUT BILIHUB</h2>
         <p class="about-subheading d-block mx-auto">Built to Make Shopping Easier</p>
         <p class="about-description text-muted mx-auto" style="max-width: 42rem;">BiliHub connects buyers, sellers, and riders through one trusted marketplace built for convenient and reliable online shopping.</p>
       </div>

       <!-- Section 2: Mission & Vision -->
       <div class="row g-4 mb-5">
         <div class="col-lg-6">
           <div class="mission-vision-card h-100">
             <div class="section-label-icon">
               <i class="bi bi-compass"></i>
             </div>
             <p class="section-label mb-2">Our Mission</p>
             <h3 class="section-main-title mb-3">Building a Trusted Marketplace</h3>
             <p class="section-description mb-0">At BiliHub, our mission is to create a trusted and innovative marketplace that connects buyers, sellers, and riders in one seamless ecosystem. We aim to empower local entrepreneurs, provide convenient shopping experiences, and deliver reliable logistics all while fostering growth, inclusivity, and sustainability in e-commerce.</p>
           </div>
         </div>
         <div class="col-lg-6">
           <div class="mission-vision-card h-100">
             <div class="section-label-icon">
               <i class="bi bi-eye"></i>
             </div>
             <p class="section-label mb-2">Our Vision</p>
             <h3 class="section-main-title mb-3">A Leading Philippine Marketplace</h3>
             <p class="section-description mb-0">We envision BiliHub as a leading online marketplace recognized for its simplicity, reliability, and community-driven approach. Our vision is to build a platform where opportunities thrive, transactions are effortless, and every Filipino can access and benefit from digital commerce locally and globally.</p>
           </div>
         </div>
       </div>

       <!-- Section 3: Core Values -->
       <div class="text-center mb-5">
         <h3 class="fw-bold" style="color: var(--color-deep-purple);">OUR CORE VALUES</h3>
         <p class="text-muted mt-2">The principles behind every BiliHub experience.</p>
       </div>

       <div class="row g-4 mb-5">
         <div class="col-lg-4 col-md-6">
           <div class="value-card">
             <div class="value-number">01</div>
             <div class="value-icon">
               <i class="bi bi-person-heart"></i>
             </div>
             <h4 class="value-title">Customer First</h4>
             <p class="text-muted small">We prioritize the needs, satisfaction, and trust of every customer above all else.</p>
           </div>
         </div>
         <div class="col-lg-4 col-md-6">
           <div class="value-card">
             <div class="value-number">02</div>
             <div class="value-icon">
               <i class="bi bi-shield-check"></i>
             </div>
             <h4 class="value-title">Integrity & Transparency</h4>
             <p class="text-muted small">We conduct business honestly and openly. No hidden fees, no misleading information.</p>
           </div>
         </div>
         <div class="col-lg-4 col-md-6">
           <div class="value-card">
             <div class="value-number">03</div>
             <div class="value-icon">
               <i class="bi bi-stars"></i>
             </div>
             <h4 class="value-title">Quality & Reliability</h4>
             <p class="text-muted small">We verify sellers, review listings, and ensure that what you see is exactly what you get.</p>
           </div>
         </div>
         <div class="col-lg-4 col-md-6">
           <div class="value-card">
             <div class="value-number">04</div>
             <div class="value-icon">
               <i class="bi bi-lightbulb"></i>
             </div>
             <h4 class="value-title">Innovation & Growth</h4>
             <p class="text-muted small">We continuously improve our platform adopting new technologies and better user experiences.</p>
           </div>
         </div>
         <div class="col-lg-4 col-md-6">
           <div class="value-card">
             <div class="value-number">05</div>
             <div class="value-icon">
               <i class="bi bi-people"></i>
             </div>
             <h4 class="value-title">Inclusivity & Empowerment</h4>
             <p class="text-muted small">We provide equal opportunities for all from small local sellers to big brands.</p>
           </div>
         </div>
         <div class="col-lg-4 col-md-6">
           <div class="value-card">
             <div class="value-number">06</div>
             <div class="value-icon">
               <i class="bi bi-chat-left-text"></i>
             </div>
             <h4 class="value-title">Community & Collaboration</h4>
             <p class="text-muted small">We grow together, customers, sellers, riders, and developers alike.</p>
           </div>
         </div>
       </div>

       <!-- Section 4: Company Goals / Timeline -->
       <div class="text-center mb-5">
         <h3 class="fw-bold" style="color: var(--color-deep-purple);">WHERE WE'RE GOING</h3>
         <p class="text-muted mt-2">Building BiliHub one milestone at a time.</p>
       </div>

       <div class="timeline-container">
         <div class="timeline-track">
           <div class="timeline-dot"></div>
           <div class="timeline-line"></div>
           <div class="timeline-dot"></div>
           <div class="timeline-line"></div>
           <div class="timeline-dot"></div>
         </div>
         <div class="timeline-labels">
           <div class="timeline-label">SHORT TERM</div>
           <div class="timeline-label">MID TERM</div>
           <div class="timeline-label">LONG TERM</div>
         </div>
       </div>

       <div class="timeline-milestones">
         <div class="goal-card">
           <p class="goal-period">Short Term (0-2 Years)</p>
           <h4 class="goal-title">Launch</h4>
           <ul class="goal-list">
             <li>Fully launch BiliHub with complete core features</li>
             <li>Onboard 500+ verified local sellers</li>
             <li>Implement buyer & seller protection</li>
             <li>Establish delivery partnerships and tracking</li>
           </ul>
         </div>
         <div class="goal-card">
           <p class="goal-period">Mid Term (2-3 Years)</p>
           <h4 class="goal-title">Expansion</h4>
           <ul class="goal-list">
             <li>Expand to all major provinces and cities</li>
             <li>Reach 10,000+ sellers and 100,000+ buyers</li>
             <li>Diversify into fresh goods and artisan categories</li>
             <li>Build in-house logistics network</li>
           </ul>
         </div>
         <div class="goal-card">
           <p class="goal-period">Long Term (5+ Years)</p>
           <h4 class="goal-title">Future</h4>
           <ul class="goal-list">
             <li>Rank among top 3 e-commerce platforms in the Philippines</li>
             <li>Create 100,000+ livelihood opportunities</li>
             <li>Lead in sustainable carbon-neutral commerce</li>
             <li>Introduce AI recommendations and AR previews</li>
           </ul>
         </div>
       </div>
     </div>
   </section>

  <!-- CTA Section -->
  <section class="hero-bg py-5 text-center">
    <div class="container py-5 position-relative" style="z-index: 2;">
      <h2 class="display-5 fw-bold mb-3">Ready to Start Selling or Buying?</h2>
      <p class="lead mb-4 opacity-90">Join thousands of happy customers and sellers on BiliHub today!</p>
      <div class="d-flex justify-content-center gap-2 flex-wrap">
        <a href="{{ route('register') }}" class="btn btn-light btn-lg rounded-xl px-4">Get Started</a>
        <a href="{{ route('products.index') }}" class="btn btn-outline-light btn-lg rounded-xl px-4">Shop Now</a>
      </div>
    </div>
    </section>

  <!-- Deliver With BiliHub Section -->
  <section class="deliver-section">
    <div class="container">
      <div class="row align-items-center g-5">
        <!-- Left: Content -->
        <div class="col-lg-6 deliver-content">
          <span class="deliver-label d-inline-flex align-items-center gap-2">
            <i class="bi bi-motorcycle fs-5"></i> DELIVER WITH BILIHUB
          </span>
          <h2 class="deliver-title mb-3">Earn While You Deliver</h2>
          <p class="text-muted mb-4">Help connect local sellers and buyers while earning through deliveries. Join BiliHub's growing delivery community and bring orders closer to customers.</p>

          <ul class="deliver-benefits list-unstyled mb-4">
            <li class="deliver-benefit mb-3 d-flex align-items-start gap-3">
              <span class="benefit-icon flex-shrink-0"><i class="bi bi-clock-fill"></i></span>
              <div>
                <h6 class="fw-semibold mb-1">Flexible Deliveries</h6>
                <p class="text-muted small mb-0">Take delivery opportunities that fit your availability.</p>
              </div>
            </li>
            <li class="deliver-benefit mb-3 d-flex align-items-start gap-3">
              <span class="benefit-icon flex-shrink-0"><i class="bi bi-wallet2"></i></span>
              <div>
                <h6 class="fw-semibold mb-1">Earn Per Delivery</h6>
                <p class="text-muted small mb-0">Earn from successfully completed deliveries.</p>
              </div>
            </li>
            <li class="deliver-benefit mb-3 d-flex align-items-start gap-3">
              <span class="benefit-icon flex-shrink-0"><i class="bi bi-geo-alt-fill"></i></span>
              <div>
                <h6 class="fw-semibold mb-1">Local Opportunities</h6>
                <p class="text-muted small mb-0">Deliver within your available service areas.</p>
              </div>
            </li>
          </ul>

          <a href="{{ route('apply.rider') }}" class="btn btn-primary btn-lg rounded-xl px-4 mb-4">
            Become a Rider <i class="bi bi-arrow-right ms-2"></i>
          </a>

          <div class="deliver-flow">
            <p class="text-uppercase small fw-semibold text-muted mb-3">How Delivery Works</p>
            <div class="row g-3 text-center">
              <div class="col-6 col-md-3">
                <div class="flow-number d-inline-flex align-items-center justify-content-center mb-1" style="width: 32px; height: 32px; background-color: var(--color-soft-lavender); border-radius: 8px;">
                  <span class="fw-bold" style="color: var(--color-deep-purple);">1</span>
                </div>
                <small class="text-muted d-block">Accept Order</small>
              </div>
              <div class="col-6 col-md-3">
                <div class="flow-number d-inline-flex align-items-center justify-content-center mb-1" style="width: 32px; height: 32px; background-color: var(--color-soft-lavender); border-radius: 8px;">
                  <span class="fw-bold" style="color: var(--color-deep-purple);">2</span>
                </div>
                <small class="text-muted d-block">Pick Up</small>
              </div>
              <div class="col-6 col-md-3">
                <div class="flow-number d-inline-flex align-items-center justify-content-center mb-1" style="width: 32px; height: 32px; background-color: var(--color-soft-lavender); border-radius: 8px;">
                  <span class="fw-bold" style="color: var(--color-deep-purple);">3</span>
                </div>
                <small class="text-muted d-block">Deliver</small>
              </div>
              <div class="col-6 col-md-3">
                <div class="flow-number d-inline-flex align-items-center justify-content-center mb-1" style="width: 32px; height: 32px; background-color: var(--color-soft-lavender); border-radius: 8px;">
                  <span class="fw-bold" style="color: var(--color-deep-purple);">4</span>
                </div>
                <small class="text-muted d-block">Get Paid</small>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Rider Image -->
        <div class="col-lg-6 deliver-image-col">
          <div class="deliver-image-wrapper">
            <svg class="deliver-svg" width="400" height="300" viewBox="0 0 400 300" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect width="400" height="300" rx="24" fill="#F8F7FC"/>
              <line x1="0" y1="250" x2="400" y2="250" stroke="#e2e8f0" stroke-width="2" stroke-linecap="round"/>
              <ellipse cx="200" cy="200" rx="90" ry="18" fill="#8b5cf6" opacity="0.85"/>
              <ellipse cx="200" cy="185" rx="70" ry="6" fill="#8b5cf6"/>
              <rect x="150" y="158" width="50" height="30" rx="4" fill="#a855f7"/>
              <rect x="155" y="165" width="40" height="6" rx="2" fill="white" opacity="0.4"/>
              <rect x="155" y="175" width="24" height="5" rx="2" fill="white" opacity="0.4"/>
              <path d="M200 130 C208 125 212 128 212 136 C212 144 206 148 198 148 C190 148 186 142 188 134 C190 126 196 130 200 130" fill="#111827"/>
              <circle cx="200" cy="125" r="14" fill="#111827"/>
              <rect x="196" y="139" width="8" height="20" fill="#111827" rx="4"/>
              <path d="M188 159 L200 170 L192 160" fill="#334155"/>
              <path d="M192 170 L200 170 L196 180 L186 180 Z" fill="#334155"/>
              <path d="M272 190 C278 186 286 184 292 186" stroke="#8b5cf6" stroke-width="3" stroke-linecap="round"/>
              <circle cx="120" cy="240" r="18" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="2"/>
              <circle cx="280" cy="240" r="18" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="2"/>
              <circle cx="120" cy="240" r="5" fill="#94a3b8"/>
              <circle cx="280" cy="240" r="5" fill="#94a3b8"/>
            </svg>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Customer Reviews Section -->
  <section class="testimonials-section">
    <div class="container">
      <div class="text-center mb-5">
        <span class="testimonials-label">CUSTOMER STORIES</span>
        <h2 class="testimonials-title mb-3">Loved by BiliHub Shoppers</h2>
        <p class="text-muted mx-auto" style="max-width: 600px;">See what customers are saying about their BiliHub shopping experience.</p>
      </div>

      @if($testimonials && $testimonials->count() > 0)
        @if($avgRating && $reviewsCount > 0)
        <div class="text-center mb-5">
          <div class="d-inline-flex align-items-center gap-2 bg-light rounded-pill px-4 py-2">
            <span class="fw-bold" style="color: var(--color-text-dark);">{{ number_format($avgRating, 1) }}</span>
            <div class="d-flex">
              @for($i = 0; $i < 5; $i++)
                <i class="bi bi-star-fill" style="color: #d4a519;"></i>
              @endfor
            </div>
            <span class="text-muted small">Based on {{ number_format($reviewsCount) }} reviews</span>
          </div>
        </div>
        @endif

        <div class="testimonials-carousel" id="testimonialsCarousel">
          <div class="testimonials-track">
            @foreach($testimonials as $review)
              @php
                $user = $review->user;
                $displayName = $user->first_name
                  ? trim($user->first_name . ' ' . ($user->last_name ? substr($user->last_name, 0, 1) . '.' : ''))
                  : $user->name;
                $initial = strtoupper(substr($user->first_name ?? $user->name, 0, 1));
                $secondInitial = strtoupper(substr($user->last_name ?? '', 0, 1));
                $avatarText = $initial . ($secondInitial ?: $initial);
                $isVerified = $review->order_id && $review->order && in_array($review->order->status, ['completed', 'delivered']);
              @endphp
              <div class="testimonial-card">
                <div class="testimonial-content h-100">
                  <div class="testimonial-rating mb-3 d-flex">
                    @for($i = 0; $i < 5; $i++)
                      <i class="bi {{ $i < $review->rating ? 'bi-star-fill' : 'bi-star' }}"></i>
                    @endfor
                  </div>

                  <blockquote class="testimonial-quote">
                    <span class="quote-mark">"</span>
                    {{ \Str::limit($review->comment, 150) }}
                  </blockquote>

                  <div class="testimonial-author d-flex align-items-center mt-4">
                    <div class="author-avatar d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; border-radius: 10px; background-color: var(--color-soft-lavender); color: var(--color-deep-purple); font-size: 0.9rem; font-weight: 600;">
                      {{ $avatarText }}
                    </div>
                    <div class="author-info ms-3">
                      <h6 class="fw-semibold mb-0">{{ $displayName }}</h6>
                      <div class="d-flex align-items-center gap-2 flex-wrap">
                        @if($isVerified)
                          <span class="text-muted small">✓ Verified Purchase</span>
                        @endif
                        @if($review->product)
                          <a href="{{ route('products.show', $review->product) }}" class="text-muted small text-decoration-none">· {{ \Str::limit($review->product->name, 30) }}</a>
                        @endif
                      </div>
                    </div>
                </div>
              </div>
            </div>
            @endforeach
          </div>

          @if($testimonials->count() > 3)
          <button type="button" class="carousel-nav prev" id="testimonialsPrev"><i class="bi bi-chevron-left"></i></button>
          <button type="button" class="carousel-nav next" id="testimonialsNext"><i class="bi bi-chevron-right"></i></button>
          <div class="carousel-dots" id="testimonialsDots"></div>
          @endif
        </div>
      @else
        <div class="text-center py-5">
          <i class="bi bi-chat-left-text display-4 text-muted mb-3"></i>
          <p class="text-muted">Customer stories are coming soon.</p>
        </div>
      @endif
    </div>
  </section>

@push('scripts')
  @vite('resources/js/buyer/home.js')
@endpush

<script type="application/json" id="home-page-data">@php $homePageData = [
  'countdownEnd' => $countdownEnd->toIso8601String(),
  'testimonialCount' => $testimonials->count(),
]; @endphp @json($homePageData)</script>
@endsection
