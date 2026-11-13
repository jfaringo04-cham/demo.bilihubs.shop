@extends('admin.layout')

@section('content')

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Sales Summary Report</h1>

    <div class="d-flex gap-2">
        <a href="{{ route('admin.reports.commission') }}"
            class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-percent"></i>
            Commission Report
        </a>

        <a href="{{ route('admin.reports.sales.export') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
            class="btn btn-bili-hub btn-sm">
            <i class="bi bi-download"></i>
            Export CSV
        </a>
    </div>
</div>

{{-- REPORT FILTERS --}}
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET"
            action="{{ route('admin.reports.sales') }}"
            class="row g-3 align-items-end">

            <div class="col-md-6 col-xl-2">
                <label class="form-label fw-bold">From</label>
                <input type="date" name="from"
                    value="{{ request('from') }}" class="form-control">
            </div>

            <div class="col-md-6 col-xl-2">
                <label class="form-label fw-bold">To</label>
                <input type="date" name="to"
                    value="{{ request('to') }}" class="form-control">
            </div>

            <div class="col-md-6 col-xl-2">
                <label class="form-label fw-bold">Order Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                    <option value="{{ $status }}"
                        @selected(request('status')===$status)>
                        {{ \Illuminate\Support\Str::headline($status) }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 col-xl-2">
                <label class="form-label fw-bold">Seller</label>
                <select name="seller_id" class="form-select">
                    <option value="">All Sellers</option>
                    @foreach($sellers as $seller)
                    <option value="{{ $seller->id }}"
                        @selected((string) request('seller_id')===(string) $seller->id)>
                        {{ $seller->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 col-xl-2">
                <label class="form-label fw-bold">Product</label>
                <select name="product_id" class="form-select">
                    <option value="">All Products</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}"
                        @selected((string) request('product_id')===(string) $product->id)>
                        {{ $product->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 col-xl-2">
                <label class="form-label fw-bold">Sort By</label>
                <select name="sort" class="form-select">
                    <option value="sales_desc" @selected(request('sort', 'sales_desc' )==='sales_desc' )>
                        Highest Sales
                    </option>
                    <option value="sales_asc" @selected(request('sort')==='sales_asc' )>
                        Lowest Sales
                    </option>
                    <option value="orders_desc" @selected(request('sort')==='orders_desc' )>
                        Most Orders / Qty
                    </option>
                </select>
            </div>

            <div class="col-md-6">
                <button type="submit" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-filter"></i>
                    Apply Filters
                </button>
            </div>

            <div class="col-md-6">
                <a href="{{ route('admin.reports.sales') }}"
                    class="btn btn-outline-danger w-100">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Reset Filters
                </a>
            </div>
        </form>
    </div>
</div>

{{-- ACTIVE FILTER SUMMARY --}}
@if(request()->hasAny(['from', 'to', 'status', 'seller_id', 'product_id', 'sort']))
<div class="d-flex flex-wrap gap-2 mb-4">
    @if(request('from'))
    <span class="badge text-bg-light border">From: {{ request('from') }}</span>
    @endif

    @if(request('to'))
    <span class="badge text-bg-light border">To: {{ request('to') }}</span>
    @endif

    @if(request('status'))
    <span class="badge text-bg-light border">
        Status: {{ \Illuminate\Support\Str::headline(request('status')) }}
    </span>
    @endif

    @if(request('seller_id'))
    @php($selectedSeller = $sellers->firstWhere('id', (int) request('seller_id')))
    @if($selectedSeller)
    <span class="badge text-bg-light border">
        Seller: {{ $selectedSeller->name }}
    </span>
    @endif
    @endif

    @if(request('product_id'))
    @php($selectedProduct = $products->firstWhere('id', (int) request('product_id')))
    @if($selectedProduct)
    <span class="badge text-bg-light border">
        Product: {{ $selectedProduct->name }}
    </span>
    @endif
    @endif
</div>
@endif

{{-- SUMMARY CARDS --}}
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-success text-white me-3">
                    <i class="bi bi-cash"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">
                        @if(request()->filled('product_id'))
                        Delivered Product Sales
                        @elseif(request()->filled('seller_id'))
                        Delivered Seller-Order Value
                        @else
                        Delivered Buyer-Order Value
                        @endif
                    </h6>
                    <h3 class="mb-0">
                        &#8369;{{ number_format($totalSales, 2) }}
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary text-white me-3">
                    <i class="bi bi-receipt"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Orders</h6>
                    <h3 class="mb-0">{{ $orderCount }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-info text-white me-3">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Completed Sales</h6>
                    <h3 class="mb-0">{{ $deliveredCount }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-warning text-white me-3">
                    <i class="bi bi-bar-chart"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Cancelled Orders</h6>
                    <h3 class="mb-0">{{ $byStatus->get('cancelled', 0) }}</h3>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="card mb-4" aria-labelledby="delivered-value-heading">
    <div class="card-body">
        <h2 id="delivered-value-heading" class="h5 mb-3">Delivered Value Breakdown</h2>
        @if(request()->filled('product_id'))
            <p class="small text-muted">Product filters show item subtotals. Shipping, service fees, and order-level discounts are outside this total.</p>
        @endif
        <dl class="mb-0">
            @foreach($salesBreakdown as $label => $amountMinor)
                <div class="d-flex justify-content-between gap-3 py-2">
                    <dt class="fw-normal">{{ $label }}</dt>
                    <dd class="mb-0 text-nowrap">{{ $amountMinor < 0 ? '−' : '' }}&#8369;{{ number_format(abs($amountMinor) / 100, 2) }}</dd>
                </div>
            @endforeach
            <div class="d-flex justify-content-between gap-3 border-top pt-2 fw-semibold">
                <dt>Total</dt>
                <dd class="mb-0 text-nowrap">&#8369;{{ number_format($totalSales, 2) }}</dd>
            </div>
        </dl>
    </div>
</section>

{{-- STATUS + TOP PRODUCTS --}}
<div class="row g-4">
    <div class="col-lg-5">
        <div class="table-container h-100">
            <h5 class="mb-3">Orders by Status</h5>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($byStatus as $status => $count)
                        <tr>
                            <td>{{ \Illuminate\Support\Str::headline($status) }}</td>
                            <td>{{ $count }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2" class="text-center text-muted py-4">
                                No order data found for the selected filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="table-container h-100">
            <h5 class="mb-3">Top Selling Products</h5>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Seller</th>
                            <th>Qty Sold</th>
                            <th class="text-end">Product Sales</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($topProducts as $item)
                        <tr>
                            <td class="fw-semibold">
                                {{ $item->product->name ?? 'Deleted Product' }}
                            </td>
                            <td>
                                {{ $item->product->seller->name ?? 'N/A' }}
                            </td>
                            <td>{{ (int) $item->total_quantity }}</td>
                            <td class="text-end fw-semibold">
                                &#8369;{{ number_format(
                                        ((int) $item->total_revenue_minor) / 100,
                                        2
                                    ) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                No delivered product sales found for the selected filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- TOP SELLERS --}}
<div class="table-container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Top Sellers</h5>
        <small class="text-muted">
            Based on delivered seller-order values and current filters
        </small>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Seller</th>
                    <th>Delivered Orders</th>
                    <th class="text-end">Delivered Seller-Order Value</th>
                </tr>
            </thead>

            <tbody>
                @forelse($topSellers as $seller)
                <tr>
                    <td class="fw-semibold">{{ $seller->name }}</td>
                    <td>{{ $seller->delivered_orders_count }}</td>
                    <td class="text-end fw-semibold">
                        &#8369;{{ number_format(
                                ((int) ($seller->delivered_sales_minor ?? 0)) / 100,
                                2
                            ) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="text-center text-muted py-4">
                        No delivered seller-order values found for the selected filters.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection