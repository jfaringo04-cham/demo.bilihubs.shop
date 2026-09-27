@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <div>
        <h1 class="fw-bold text-slate-900 mb-1">Products Management</h1>
        <p class="text-muted mb-0">
            Monitor and manage product listings across the BiliHub marketplace.
        </p>
    </div>

    <div class="text-muted small">
        <i class="bi bi-box-seam me-1"></i>
        Showing <strong>{{ $products->count() }}</strong>
        of <strong>{{ $products->total() }}</strong> products
    </div>
</div>

{{-- FILTERS --}}
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-4">
        <form method="GET" action="{{ route('admin.products') }}">
            <div class="row g-3">

                {{-- Search --}}
                <div class="col-lg-4 col-md-6">
                    <label for="search" class="form-label fw-semibold">
                        Search Product
                    </label>

                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="text"
                            name="search"
                            id="search"
                            class="form-control"
                            placeholder="Product name or SKU"
                            value="{{ request('search') }}"
                        >
                    </div>
                </div>

                {{-- Category --}}
                <div class="col-lg-2 col-md-6">
                    <label for="category" class="form-label fw-semibold">
                        Category
                    </label>

                    <select
                        name="category"
                        id="category"
                        class="form-select"
                    >
                        <option value="">All Categories</option>

                        @foreach($categories as $category)
                            <option
                                value="{{ $category->id }}"
                                {{ (string) request('category') === (string) $category->id ? 'selected' : '' }}
                            >
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Seller --}}
                <div class="col-lg-2 col-md-6">
                    <label for="seller" class="form-label fw-semibold">
                        Seller
                    </label>

                    <select
                        name="seller"
                        id="seller"
                        class="form-select"
                    >
                        <option value="">All Sellers</option>

                        @foreach($sellers as $seller)
                            <option
                                value="{{ $seller->id }}"
                                {{ (string) request('seller') === (string) $seller->id ? 'selected' : '' }}
                            >
                                {{ $seller->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Publication Status --}}
                <div class="col-lg-2 col-md-6">
                    <label for="status" class="form-label fw-semibold">
                        Product Status
                    </label>

                    <select
                        name="status"
                        id="status"
                        class="form-select"
                    >
                        <option value="">All Statuses</option>

                        <option
                            value="published"
                            {{ request('status') === 'published' ? 'selected' : '' }}
                        >
                            Published
                        </option>

                        <option
                            value="draft"
                            {{ request('status') === 'draft' ? 'selected' : '' }}
                        >
                            Draft
                        </option>
                    </select>
                </div>

                {{-- Compliance --}}
                <div class="col-lg-2 col-md-6">
                    <label for="compliance_status" class="form-label fw-semibold">
                        Compliance
                    </label>

                    <select
                        name="compliance_status"
                        id="compliance_status"
                        class="form-select"
                    >
                        <option value="">All</option>

                        <option
                            value="approved"
                            {{ request('compliance_status') === 'approved' ? 'selected' : '' }}
                        >
                            Approved
                        </option>

                        <option
                            value="pending"
                            {{ request('compliance_status') === 'pending' ? 'selected' : '' }}
                        >
                            Pending
                        </option>

                        <option
                            value="flagged"
                            {{ request('compliance_status') === 'flagged' ? 'selected' : '' }}
                        >
                            Flagged
                        </option>

                        <option
                            value="auto_flagged"
                            {{ request('compliance_status') === 'auto_flagged' ? 'selected' : '' }}
                        >
                            Auto Flagged
                        </option>
                    </select>
                </div>

                {{-- Buttons --}}
                <div class="col-12">
                    <div class="d-flex gap-2">
                        <button
                            type="submit"
                            class="btn btn-primary px-4"
                        >
                            <i class="bi bi-funnel me-1"></i>
                            Filter
                        </button>

                        <a
                            href="{{ route('admin.products') }}"
                            class="btn btn-outline-secondary px-4"
                        >
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            Reset
                        </a>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

{{-- PRODUCTS TABLE --}}
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">

        <div class="table-responsive">
            <table class="table align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3 border-0">Product</th>
                        <th class="py-3 border-0">SKU</th>
                        <th class="py-3 border-0">Category</th>
                        <th class="py-3 border-0">Seller</th>
                        <th class="py-3 border-0">Price</th>
                        <th class="py-3 border-0">Stock</th>
                        <th class="py-3 border-0">Status</th>
                        <th class="py-3 border-0">Compliance</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($products as $product)

                        <tr>
                            {{-- Product --}}
                            <td class="px-4 py-3">
                                <div class="d-flex align-items-center gap-3">

                                    <img
                                        src="{{ $product->image_url }}"
                                        alt="{{ $product->alt_text ?: $product->name }}"
                                        class="rounded border"
                                        width="48"
                                        height="48"
                                        style="object-fit: cover;"
                                    >

                                    <div>
                                        <div class="fw-semibold text-dark">
                                            {{ $product->name }}
                                        </div>

                                        <small class="text-muted">
                                            ID #{{ $product->id }}
                                        </small>
                                    </div>

                                </div>
                            </td>

                            {{-- SKU --}}
                            <td>
                                @if($product->sku)
                                    <span class="font-monospace small">
                                        {{ $product->sku }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            {{-- Category --}}
                            <td>
                                @if($product->category)
                                    <span class="badge bg-light text-dark border">
                                        {{ $product->category->name }}
                                    </span>

                                    @if($product->subcategory)
                                        <div class="small text-muted mt-1">
                                            {{ $product->subcategory->name }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-muted">
                                        Uncategorized
                                    </span>
                                @endif
                            </td>

                            {{-- Seller --}}
                            <td>
                                @if($product->seller)
                                    <div class="fw-medium">
                                        {{ $product->seller->name }}
                                    </div>
                                @else
                                    <span class="text-muted">
                                        N/A
                                    </span>
                                @endif
                            </td>

                            {{-- Price --}}
                            <td>
                                <div class="fw-semibold">
                                    ₱{{ number_format($product->display_price, 2) }}
                                </div>

                                @if($product->hasActiveDiscount())
                                    <small class="text-muted text-decoration-line-through">
                                        ₱{{ number_format($product->price_minor / 100, 2) }}
                                    </small>

                                    <small class="text-danger ms-1">
                                        -{{ number_format($product->discount_percent, 0) }}%
                                    </small>
                                @endif
                            </td>

                            {{-- Stock --}}
                            <td>
                                @if($product->stock <= 0)
                                    <span class="badge bg-danger">
                                        Out of Stock
                                    </span>

                                @elseif(
                                    $product->low_stock_threshold !== null &&
                                    $product->stock <= $product->low_stock_threshold
                                )
                                    <span class="badge bg-warning text-dark">
                                        {{ $product->stock }} Low Stock
                                    </span>

                                @else
                                    <span class="badge bg-success">
                                        {{ $product->stock }} in stock
                                    </span>
                                @endif
                            </td>

                            {{-- Publication Status --}}
                            <td>
                                @if($product->status === 'draft')
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-pencil me-1"></i>
                                        Draft
                                    </span>
                                @else
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Published
                                    </span>
                                @endif
                            </td>

                            {{-- Compliance --}}
                            <td>
                                @switch($product->compliance_status)

                                    @case('approved')
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Approved
                                        </span>
                                        @break

                                    @case('pending')
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-clock me-1"></i>
                                            Pending
                                        </span>
                                        @break

                                    @case('flagged')
                                        <span class="badge bg-danger">
                                            <i class="bi bi-flag me-1"></i>
                                            Flagged
                                        </span>
                                        @break

                                    @case('auto_flagged')
                                        <span class="badge bg-danger">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            Auto Flagged
                                        </span>
                                        @break

                                    @default
                                        <span class="badge bg-secondary">
                                            {{ ucfirst($product->compliance_status ?? 'Unknown') }}
                                        </span>

                                @endswitch
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">

                                <div class="text-muted">
                                    <i class="bi bi-box-seam fs-2 d-block mb-2"></i>

                                    @if(request()->hasAny([
                                        'search',
                                        'category',
                                        'seller',
                                        'status',
                                        'compliance_status'
                                    ]))
                                        No products match the selected filters.
                                    @else
                                        No products have been added to the marketplace yet.
                                    @endif
                                </div>

                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

        @if($products->hasPages())
            <div class="p-3 border-top">
                {{ $products->links('pagination::bootstrap-5') }}
            </div>
        @endif

    </div>
</div>
@endsection