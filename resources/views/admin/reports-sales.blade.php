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


{{-- DATE FILTER --}}
<div class="card shadow-sm mb-4">
    <div class="card-body">

        <form method="GET"
              action="{{ route('admin.reports.sales') }}"
              class="row g-3 align-items-end">

            <div class="col-md-4">
                <label class="form-label fw-bold">From</label>

                <input type="date"
                       name="from"
                       value="{{ request('from') }}"
                       class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">To</label>

                <input type="date"
                       name="to"
                       value="{{ request('to') }}"
                       class="form-control">
            </div>

            <div class="col-md-4">
                <button type="submit"
                        class="btn btn-outline-secondary w-100">

                    <i class="bi bi-filter"></i>
                    Apply Filter
                </button>
            </div>

        </form>

    </div>
</div>


{{-- SUMMARY CARDS --}}
<div class="row g-4 mb-4">

    {{-- TOTAL SALES --}}
    <div class="col-md-3">
        <div class="card stat-card h-100">

            <div class="card-body d-flex align-items-center">

                <div class="stat-icon bg-success text-white me-3">
                    <i class="bi bi-cash"></i>
                </div>

                <div>
<h6 class="text-muted mb-1">
    Total Sales
</h6>

<h3 class="mb-0">
    &#8369;{{ number_format($totalSales, 2) }}
</h3>
                </div>

            </div>

        </div>
    </div>


    {{-- TOTAL ORDERS --}}
<div class="col-md-3">
    <div class="card stat-card h-100">
        <div class="card-body d-flex align-items-center">

            <div class="stat-icon bg-primary text-white me-3">
                <i class="bi bi-receipt"></i>
            </div>

            <div>
                <h6 class="text-muted mb-1">
                    Total Orders
                </h6>

                <h3 class="mb-0">
                    {{ $orderCount }}
                </h3>
            </div>

        </div>
    </div>
</div>


    {{-- COMPLETED SALES --}}
<div class="col-md-3">
    <div class="card stat-card h-100">

        <div class="card-body d-flex align-items-center">

            <div class="stat-icon bg-info text-white me-3">
                <i class="bi bi-check2-circle"></i>
            </div>

            <div>
                <h6 class="text-muted mb-1">
                    Completed Sales
                </h6>

                <h3 class="mb-0">
                    {{ $deliveredCount }}
                </h3>
            </div>

        </div>
    </div>
</div>

    {{-- STATUSES --}}
    <div class="col-md-3">
        <div class="card stat-card h-100">

            <div class="card-body d-flex align-items-center">

                <div class="stat-icon bg-warning text-white me-3">
                    <i class="bi bi-bar-chart"></i>
                </div>

                <div>
                    <h6 class="text-muted mb-1">
                        Statuses
                    </h6>

                    <h3 class="mb-0">
                        {{ $byStatus->count() }}
                    </h3>
                </div>

            </div>

        </div>
    </div>

</div>


{{-- STATUS + TOP PRODUCTS --}}
<div class="row g-4">

    {{-- ORDERS BY STATUS --}}
    <div class="col-lg-5">

        <div class="table-container h-100">

            <h5 class="mb-3">
                Orders by Status
            </h5>

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
                                <td class="text-capitalize">
                                    {{ $status }}
                                </td>

                                <td>
                                    {{ $count }}
                                </td>
                            </tr>

                        @empty

                            <tr>
                                <td colspan="2"
                                    class="text-center text-muted py-4">

                                    No order data found.

                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    {{-- TOP SELLING PRODUCTS --}}
    <div class="col-lg-7">

        <div class="table-container h-100">

            <h5 class="mb-3">
                Top Selling Products
            </h5>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-light">

                        <tr>
                            <th>Product</th>
                            <th>Seller</th>
                            <th>Qty Sold</th>
                            <th class="text-end">
                                Revenue
                            </th>
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


                                <td>

                                    {{ (int) $item->total_quantity }}

                                </td>


                                <td class="text-end fw-semibold">

                                    &#8369;{{ number_format(
                                        ((int) $item->total_revenue_minor) / 100,
                                        2
                                    ) }}

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="4"
                                    class="text-center text-muted py-4">

                                    No delivered product sales found.

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

        <h5 class="mb-0">
            Top Sellers
        </h5>

        <small class="text-muted">
            Based on delivered sales
        </small>

    </div>


    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead class="table-light">

                <tr>
                    <th>Seller</th>
                    <th>Delivered Orders</th>
                    <th class="text-end">
                        Delivered Sales
                    </th>
                </tr>

            </thead>


            <tbody>

                @forelse($topSellers as $seller)

                    <tr>

                        <td class="fw-semibold">

                            {{ $seller->name }}

                        </td>


                        <td>

                            {{ $seller->delivered_orders_count }}

                        </td>


                        <td class="text-end fw-semibold">

                            &#8369;{{ number_format(
                                ((int) ($seller->delivered_sales_minor ?? 0)) / 100,
                                2
                            ) }}

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="3"
                            class="text-center text-muted py-4">

                            No delivered seller sales found.

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@endsection