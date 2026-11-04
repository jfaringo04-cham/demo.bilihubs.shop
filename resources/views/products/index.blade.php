@extends('layouts.app')

@section('content')
<style>
  .category-sidebar {
    position: sticky;
    top: 90px;
  }

  .category-sidebar .card {
    overflow: hidden;
  }

  .category-main-row {
    display: flex;
    align-items: stretch;
    border-top: 1px solid #eef0f4;
  }

  .category-main-row:first-child {
    border-top: 0;
  }

  .category-main-link {
    flex: 1;
    display: flex;
    align-items: center;
    padding: 13px 14px;
    color: #222;
    text-decoration: none;
    font-weight: 600;
    min-width: 0;
  }

  .category-main-link:hover {
    background: #f8f6ff;
    color: #6f42ff;
  }

  .category-main-link.active {
    color: #6f42ff;
    background: #f4efff;
  }

  .category-toggle {
    width: 46px;
    border: 0;
    border-left: 1px solid #eef0f4;
    background: #fff;
    color: #697386;
    transition: background .2s ease, color .2s ease;
  }

  .category-toggle:hover {
    background: #f8f6ff;
    color: #6f42ff;
  }

  .category-toggle .bi {
    transition: transform .2s ease;
  }

  .category-toggle[aria-expanded="true"] .bi {
    transform: rotate(180deg);
  }

  .subcategory-list {
    background: #fafafa;
    border-top: 1px solid #eef0f4;
  }

  .subcategory-link {
    display: block;
    padding: 10px 16px 10px 30px;
    text-decoration: none;
    color: #596273;
    border-bottom: 1px solid #f0f1f3;
    font-size: .94rem;
  }

  .subcategory-link:last-child {
    border-bottom: 0;
  }

  .subcategory-link:hover {
    color: #6f42ff;
    background: #f5f1ff;
  }

  .subcategory-link.active {
    color: #6f42ff;
    background: #eee7ff;
    font-weight: 600;
  }

  .all-categories-link {
    display: block;
    padding: 12px 14px;
    text-decoration: none;
    color: #596273;
    border-top: 1px solid #eef0f4;
  }

  .all-categories-link:hover,
  .all-categories-link.active {
    color: #6f42ff;
    background: #f8f6ff;
  }

  .active-filter {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    font-size: .92rem;
    color: #697386;
  }

  .active-filter a {
    color: #6f42ff;
    text-decoration: none;
    font-weight: 600;
  }

  @media (max-width: 991.98px) {
    .category-sidebar {
      position: static;
    }
  }

  .price-quick-filters {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
  }

  .price-quick-btn {
    border: 1px solid #e2e4e9;
    border-radius: 9px;
    background: #fff;
    color: #596273;
    padding: .5rem .55rem;
    font-size: .78rem;
    line-height: 1.2;
    transition: border-color .2s ease, background .2s ease, color .2s ease;
  }

  .price-quick-btn:hover {
    border-color: #cfc1f4;
    background: #f8f6ff;
    color: #6f42ff;
  }

  .price-quick-btn.active {
    border-color: #6f42ff;
    background: #f1ebff;
    color: #6f42ff;
    font-weight: 600;
  }

</style>

@php
  $activeCategoryId = (int) request('category');

  $activeMainId = null;

  if ($selectedCategory) {
      $activeMainId = $selectedCategory->parent_id
          ? (int) $selectedCategory->parent_id
          : (int) $selectedCategory->id;
  }
@endphp

<div class="container py-4">
  <div class="row g-4">

    <div class="col-lg-3">
      <div class="category-sidebar">
        <div class="card border-0 shadow-sm rounded-3 mb-4">
          <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0 fw-semibold">Categories</h5>
            <small class="text-muted">Choose a main category</small>
          </div>

          <a
            href="{{ route('products.index', request()->except(['category', 'page'])) }}"
            class="all-categories-link {{ !$selectedCategory ? 'active' : '' }}"
          >
            <i class="bi bi-grid me-2"></i>
            All Categories
          </a>

          @foreach($categories as $category)
            @php
              $isMainActive = $activeMainId === (int) $category->id;
              $collapseId = 'category-' . $category->id;
            @endphp

            <div class="category-main-row">
              <a
                href="{{ route('products.index', array_merge(request()->except(['category', 'page']), ['category' => $category->id])) }}"
                class="category-main-link {{ $activeCategoryId === (int) $category->id ? 'active' : '' }}"
              >
                {{ $category->name }}
              </a>

              @if($category->subcategories->isNotEmpty())
                <button
                  class="category-toggle"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#{{ $collapseId }}"
                  aria-expanded="{{ $isMainActive ? 'true' : 'false' }}"
                  aria-controls="{{ $collapseId }}"
                  title="Show subcategories"
                >
                  <i class="bi bi-chevron-down"></i>
                </button>
              @endif
            </div>

            @if($category->subcategories->isNotEmpty())
              <div
                id="{{ $collapseId }}"
                class="collapse {{ $isMainActive ? 'show' : '' }}"
              >
                <div class="subcategory-list">
                  @foreach($category->subcategories as $subcategory)
                    <a
                      href="{{ route('products.index', array_merge(request()->except(['category', 'page']), ['category' => $subcategory->id])) }}"
                      class="subcategory-link {{ $activeCategoryId === (int) $subcategory->id ? 'active' : '' }}"
                    >
                      {{ $subcategory->name }}
                    </a>
                  @endforeach
                </div>
              </div>
            @endif
          @endforeach
        </div>

        <div class="card border-0 shadow-sm rounded-3 mb-4">
          <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0 fw-semibold">Price Range</h5>
            <small class="text-muted">Filter products by your budget</small>
          </div>

          <div class="card-body pt-2">
            <form method="GET" action="{{ route('products.index') }}" id="priceFilterForm">
              @if(request('category'))
                <input type="hidden" name="category" value="{{ request('category') }}">
              @endif

              @if(request('search'))
                <input type="hidden" name="search" value="{{ request('search') }}">
              @endif

              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label for="min_price" class="form-label small text-muted mb-1">
                    Min Price
                  </label>

                  <div class="input-group input-group-sm">
                    <span class="input-group-text">₱</span>
                    <input
                      type="number"
                      class="form-control"
                      id="min_price"
                      name="min_price"
                      value="{{ request('min_price') }}"
                      min="0"
                      step="1"
                      placeholder="0"
                    >
                  </div>
                </div>

                <div class="col-6">
                  <label for="max_price" class="form-label small text-muted mb-1">
                    Max Price
                  </label>

                  <div class="input-group input-group-sm">
                    <span class="input-group-text">₱</span>
                    <input
                      type="number"
                      class="form-control"
                      id="max_price"
                      name="max_price"
                      value="{{ request('max_price') }}"
                      min="0"
                      step="1"
                      placeholder="Any"
                    >
                  </div>
                </div>
              </div>

              <div class="d-grid gap-2 mb-3">
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class="bi bi-funnel me-1"></i>
                  Apply Price
                </button>

                @if(request()->filled('min_price') || request()->filled('max_price'))
                  <a
                    href="{{ route('products.index', request()->except(['min_price', 'max_price', 'page'])) }}"
                    class="btn btn-outline-secondary btn-sm"
                  >
                    Clear Price
                  </a>
                @endif
              </div>

              <div class="price-quick-filters">
                <button type="button" class="price-quick-btn" data-min="" data-max="500">
                  Under ₱500
                </button>

                <button type="button" class="price-quick-btn" data-min="500" data-max="1000">
                  ₱500–₱1,000
                </button>

                <button type="button" class="price-quick-btn" data-min="1000" data-max="5000">
                  ₱1,000–₱5,000
                </button>

                <button type="button" class="price-quick-btn" data-min="5000" data-max="">
                  ₱5,000+
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div>
          <h2 class="section-title mb-1">Products</h2>

          @if($selectedCategory)
            <div class="active-filter">
              <a href="{{ route('products.index', request()->except(['category', 'page'])) }}">
                All Categories
              </a>

              <i class="bi bi-chevron-right small"></i>

              @if($selectedCategory->parent)
                <a
                  href="{{ route('products.index', array_merge(request()->except(['category', 'page']), ['category' => $selectedCategory->parent->id])) }}"
                >
                  {{ $selectedCategory->parent->name }}
                </a>

                <i class="bi bi-chevron-right small"></i>
              @endif

              <span>{{ $selectedCategory->name }}</span>
            </div>
          @endif
        </div>

        <div class="text-end">
          <span class="text-muted text-nowrap d-block">
            {{ $products->total() }} products found
          </span>

          @if(request()->filled('min_price') || request()->filled('max_price'))
            <small class="text-muted">
              Price:
              @if(request()->filled('min_price') && request()->filled('max_price'))
                ₱{{ number_format((float) request('min_price')) }}
                –
                ₱{{ number_format((float) request('max_price')) }}
              @elseif(request()->filled('min_price'))
                ₱{{ number_format((float) request('min_price')) }}+
              @else
                Under ₱{{ number_format((float) request('max_price')) }}
              @endif
            </small>
          @endif
        </div>
      </div>

      <div class="row g-4">
        @forelse($products as $product)
          <div class="col-md-4 col-6">
            <a href="{{ route('products.show', $product) }}" class="text-decoration-none">
              <div class="card product-card h-100 border-0 shadow-sm">
                <div class="position-relative">
                  @if($product->seller?->isOnVacation())
                    <span class="badge text-bg-warning position-absolute"
                          style="top: 12px; right: 12px; z-index: 2;">
                      <i class="bi bi-calendar-x me-1"></i>
                      On Vacation
                    </span>
                  @endif
                  <img
                    src="{{ $product->image_url }}"
                    class="product-image"
                    alt="{{ $product->alt_text ?? $product->name }}"
                    style="height: 220px; object-fit: cover;"
                  >

                  @if($product->hasActiveDiscount())
                    <span
                      class="badge bg-danger position-absolute"
                      style="top: 12px; left: 12px; border-radius: 8px;"
                    >
                      -{{ rtrim(rtrim(number_format($product->discount_percent, 2), '0'), '.') }}%
                    </span>
                  @endif
                </div>

                <div class="card-body">
                  <h6
                    class="fw-semibold text-slate-800 mb-2"
                    style="min-height: 40px;"
                  >
                    {{ $product->name }}
                  </h6>

                  @if($product->hasActiveDiscount())
                    <p class="price mb-1">
                      <span class="text-danger">
                        &#8369;{{ number_format($product->effective_price_minor / 100, 2) }}
                      </span>

                      <small class="text-muted text-decoration-line-through ms-1">
                        &#8369;{{ number_format($product->price_minor / 100, 2) }}
                      </small>
                    </p>
                  @else
                    <p class="price mb-1">
                      &#8369;{{ number_format($product->price_minor / 100, 2) }}
                    </p>
                  @endif

                  @if($product->seller?->isOnVacation())
                    <span class="badge text-bg-warning">
                      Temporarily Unavailable
                    </span>
                  @elseif($product->stock > 0)
                    <small class="text-muted">
                      {{ $product->category->name ?? 'Uncategorized' }}
                    </small>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('priceFilterForm');
  const minInput = document.getElementById('min_price');
  const maxInput = document.getElementById('max_price');
  const quickButtons = document.querySelectorAll('.price-quick-btn');

  quickButtons.forEach((button) => {
    const min = button.dataset.min || '';
    const max = button.dataset.max || '';

    if (
      String(minInput.value || '') === min &&
      String(maxInput.value || '') === max
    ) {
      button.classList.add('active');
    }

    button.addEventListener('click', function () {
      minInput.value = min;
      maxInput.value = max;
      form.submit();
    });
  });

  form.addEventListener('submit', function (event) {
    const min = parseFloat(minInput.value);
    const max = parseFloat(maxInput.value);

    if (
      !Number.isNaN(min) &&
      !Number.isNaN(max) &&
      min > max
    ) {
      event.preventDefault();
      alert('Minimum price cannot be greater than maximum price.');
      maxInput.focus();
    }
  });
});
</script>

@endsection
