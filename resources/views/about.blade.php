@extends('layouts.app')

@section('content')
<!-- Hero -->
<section class="hero-gradient py-5">
  <div class="container py-5 text-center">
    <img src="{{ asset('images/bilihublogo.png') }}" alt="Bili Hub" class="mb-4" style="height: 100px; width: auto;">
    <h1 class="display-4 fw-bold text-slate-900 mb-3">About BiliHub</h1>
    <p class="lead text-muted mb-0 max-w-3xl mx-auto">Your trusted marketplace connecting buyers, sellers, and riders across the Philippines.</p>
  </div>
</section>

<!-- About Content (same as landing page #about section) -->
<section class="py-5 bg-slate-50">
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
