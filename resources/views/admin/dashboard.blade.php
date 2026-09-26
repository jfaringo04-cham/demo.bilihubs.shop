@extends('admin.layout')

@section('title', 'Admin Dashboard')

@push('styles')
    @vite('resources/css/admin/dashboard.css')
@endpush

@section('content')

{{-- PAGE HEADER --}}
<div class="dashboard-header">
    <h1>Admin Dashboard</h1>
    <p>Monitor Bili Hub marketplace activity and performance</p>
</div>


{{-- MAIN STATISTICS --}}
<div class="dashboard-cards">

    <div class="dashboard-card">
        <div class="dashboard-card-icon">
            <i class="bi bi-people"></i>
        </div>

        <div class="dashboard-card-label">
            Total Buyers
        </div>

        <div class="dashboard-card-value">
            {{ $totalBuyers ?? 0 }}
        </div>
    </div>


    <div class="dashboard-card">
        <div class="dashboard-card-icon">
            <i class="bi bi-shop"></i>
        </div>

        <div class="dashboard-card-label">
            Total Sellers
        </div>

        <div class="dashboard-card-value">
            {{ $totalSellers ?? 0 }}
        </div>
    </div>


    <div class="dashboard-card">
        <div class="dashboard-card-icon">
            <i class="bi bi-box-seam"></i>
        </div>

        <div class="dashboard-card-label">
            Total Products
        </div>

        <div class="dashboard-card-value">
            {{ $totalProducts ?? 0 }}
        </div>
    </div>


    <div class="dashboard-card">
        <div class="dashboard-card-icon">
            <i class="bi bi-bag-check"></i>
        </div>

        <div class="dashboard-card-label">
            Total Orders
        </div>

        <div class="dashboard-card-value">
            {{ $totalOrders ?? 0 }}
        </div>
    </div>


    <div class="dashboard-card">
        <div class="dashboard-card-icon">
            <i class="bi bi-truck"></i>
        </div>

        <div class="dashboard-card-label">
            Logistics
        </div>

        <div class="dashboard-card-value">
            {{ $totalLogistics ?? 0 }}
        </div>
    </div>


    <div class="dashboard-card">
        <div class="dashboard-card-icon">
            <i class="bi bi-bicycle"></i>
        </div>

        <div class="dashboard-card-label">
            Total Riders
        </div>

        <div class="dashboard-card-value">
            {{ $totalRiders ?? 0 }}
        </div>
    </div>

</div>


{{-- REVENUE & ACTIVITY --}}
<div class="dashboard-section">

    <h2>Revenue & Activity</h2>

    <div class="dashboard-stats">

        <div class="dashboard-mini-card">
            <div class="dashboard-mini-label">
                Delivered Revenue
            </div>

            <div class="dashboard-mini-value">
                ₱{{ number_format($totalRevenue ?? 0, 2) }}
            </div>
        </div>


        <div class="dashboard-mini-card">
            <div class="dashboard-mini-label">
                Pending Registrations
            </div>

            <div class="dashboard-mini-value">
                {{ $pendingRegistrationsCount ?? 0 }}
            </div>
        </div>


        <div class="dashboard-mini-card">
            <div class="dashboard-mini-label">
                Total Orders
            </div>

            <div class="dashboard-mini-value">
                {{ $totalOrders ?? 0 }}
            </div>
        </div>

    </div>

</div>


{{-- PRODUCT MONITORING --}}
<div class="dashboard-section">

    <h2>Product Monitoring</h2>

    <div class="dashboard-stats">

        <div class="dashboard-mini-card">
            <div class="dashboard-mini-label">
                Products For Review
            </div>

            <div class="dashboard-mini-value">
                {{ $resubmittedProductsCount ?? 0 }}
            </div>
        </div>


        <div class="dashboard-mini-card">
            <div class="dashboard-mini-label">
                Flagged Products
            </div>

            <div class="dashboard-mini-value">
                {{ $flaggedProductsCount ?? 0 }}
            </div>
        </div>


        <div class="dashboard-mini-card">
            <div class="dashboard-mini-label">
                Total Products
            </div>

            <div class="dashboard-mini-value">
                {{ $totalProducts ?? 0 }}
            </div>
        </div>

    </div>

</div>


{{-- RECENT ORDERS --}}
<div class="dashboard-section">

    <h2>Recent Orders</h2>

    @if(isset($recentOrders) && $recentOrders->count())

        @foreach($recentOrders as $order)

            <div class="dashboard-list-item">

                <div>
                    <div class="dashboard-list-title">
                        Order #{{ $order->id }}
                    </div>

                    <div class="dashboard-list-subtitle">
                        {{ $order->user->name ?? $order->user->email ?? 'Customer' }}
                    </div>
                </div>

            </div>

        @endforeach

    @else

        <div class="dashboard-empty">
            <i class="bi bi-inbox"></i>
            <div>No orders available yet.</div>
        </div>

    @endif

</div>


{{-- RECENT PRODUCTS --}}
<div class="dashboard-section">

    <h2>Recent Products</h2>

    @if(isset($topProducts) && $topProducts->count())

        @foreach($topProducts as $product)

            <div class="dashboard-list-item">

                <div>
                    <div class="dashboard-list-title">
                        {{ $product->name }}
                    </div>

                    <div class="dashboard-list-subtitle">
                        Recent marketplace product
                    </div>
                </div>


                <div class="dashboard-list-title">
                    ₱{{ number_format(
                        ($product->price_minor ?? 0) / 100,
                        2
                    ) }}
                </div>

            </div>

        @endforeach

    @else

        <div class="dashboard-empty">
            <i class="bi bi-box-seam"></i>
            <div>No products available yet.</div>
        </div>

    @endif

</div>

@endsection