@extends('admin.layout')

@section('content')

@php
    $statusClass = match($order->status) {
        'delivered', 'completed' => 'success',
        'cancelled', 'delivery_failed' => 'danger',
        'placed', 'assigned_to_rider',
        'reschedule_requested', 'return_requested' => 'warning',
        'confirmed', 'at_sorting_center', 'rescheduled' => 'info',
        'preparing', 'ready_for_pickup',
        'picked_up', 'out_for_delivery' => 'primary',
        'returned' => 'secondary',
        default => 'secondary',
    };

    $paymentClass = match($order->payment_status) {
        'paid' => 'success',
        'pending' => 'warning',
        'failed' => 'danger',
        'refunded' => 'info',
        default => 'secondary',
    };

    $deliveryClass = match($order->delivery_status) {
        'delivered' => 'success',
        'delivery_failed', 'failed' => 'danger',
        'out_for_delivery' => 'primary',
        'assigned_to_rider' => 'warning',
        default => 'secondary',
    };
@endphp

{{-- HEADER --}}
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="fw-bold text-slate-900 mb-0">Order Details</h1>

            <span class="badge bg-{{ $statusClass }}">
                {{ ucwords(str_replace('_', ' ', $order->status ?? 'unknown')) }}
            </span>
        </div>

        <p class="text-muted mb-0">
            Monitor order information, seller fulfillment, payment, and delivery progress.
        </p>
    </div>

    <a href="{{ route('admin.orders') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        Back to Orders
    </a>
</div>

{{-- ORDER SUMMARY --}}
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-4">

        <div class="row g-4">

            <div class="col-lg-3 col-md-6">
                <div class="text-muted small mb-1">Order Number</div>
                <div class="fw-bold fs-5">
                    {{ $order->order_number }}
                </div>
                <small class="text-muted">
                    Database ID #{{ $order->id }}
                </small>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="text-muted small mb-1">Order Date</div>
                <div class="fw-semibold">
                    {{ ($order->ordered_at ?? $order->created_at)?->format('M d, Y') ?? 'N/A' }}
                </div>
                <small class="text-muted">
                    {{ ($order->ordered_at ?? $order->created_at)?->format('h:i A') ?? '' }}
                </small>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="text-muted small mb-1">Order Status</div>
                <span class="badge bg-{{ $statusClass }}">
                    {{ ucwords(str_replace('_', ' ', $order->status ?? 'unknown')) }}
                </span>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="text-muted small mb-1">Total Amount</div>
                <div class="fw-bold fs-5">
                    ₱{{ number_format(($order->total_minor ?? 0) / 100, 2) }}
                </div>
            </div>

        </div>

    </div>
</div>

<div class="row g-4 mb-4">

    {{-- BUYER --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white border-bottom p-4">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-person me-2"></i>
                    Buyer Information
                </h5>
            </div>

            <div class="card-body p-4">

                @if($order->user)
                    <div class="mb-3">
                        <div class="text-muted small">Name</div>
                        <div class="fw-semibold">
                            {{ $order->user->name }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">Email</div>
                        <div>
                            {{ $order->user->email }}
                        </div>
                    </div>

                    @if($order->user->phone)
                        <div>
                            <div class="text-muted small">Phone</div>
                            <div>
                                {{ $order->user->phone }}
                            </div>
                        </div>
                    @endif
                @else
                    <span class="text-muted">
                        Buyer information is unavailable.
                    </span>
                @endif

            </div>
        </div>
    </div>

    {{-- SHIPPING --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white border-bottom p-4">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-geo-alt me-2"></i>
                    Shipping Information
                </h5>
            </div>

            <div class="card-body p-4">

                <div class="mb-3">
                    <div class="text-muted small mb-1">
                        Shipping Address
                    </div>

                    @php
    $shippingAddress = is_string($order->shipping_address)
        ? json_decode($order->shipping_address, true)
        : $order->shipping_address;
@endphp

@if(is_array($shippingAddress))
    <div class="fw-medium">
        {{ $shippingAddress['address'] ?? 'No shipping address recorded.' }}
    </div>

    @if(!empty($shippingAddress['recipient']))
        <div class="text-muted small mt-2">
            Recipient: {{ $shippingAddress['recipient'] }}
        </div>
    @endif

    @if(!empty($shippingAddress['phone']))
        <div class="text-muted small">
            Phone: {{ $shippingAddress['phone'] }}
        </div>
    @endif
@else
    <div class="fw-medium">
        {{ $order->shipping_address ?: 'No shipping address recorded.' }}
    </div>
@endif

                @if($order->notes)
                    <div>
                        <div class="text-muted small mb-1">
                            Order Notes
                        </div>

                        <div>
                            {{ $order->notes }}
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>

</div>

{{-- PAYMENT --}}
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white border-bottom p-4">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-credit-card me-2"></i>
            Payment Summary
        </h5>
    </div>

    <div class="card-body p-4">

        <div class="row g-4">

            <div class="col-lg-3 col-md-6">
                <div class="text-muted small mb-1">
                    Payment Method
                </div>

                <div class="fw-semibold text-uppercase">
                    {{ $order->payment_method ?: 'N/A' }}
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="text-muted small mb-1">
                    Payment Status
                </div>

                <span class="badge bg-{{ $paymentClass }}">
                    {{ ucfirst($order->payment_status ?? 'Unknown') }}
                </span>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="text-muted small mb-1">
                    Amount Collected
                </div>

                <div class="fw-semibold">
                    ₱{{ number_format(($order->amount_collected_minor ?? 0) / 100, 2) }}
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="text-muted small mb-1">
                    Collected At
                </div>

                <div class="fw-semibold">
                    {{ $order->collected_at?->format('M d, Y h:i A') ?? 'Not collected' }}
                </div>
            </div>

        </div>

        <hr class="my-4">

        <div class="row justify-content-end">
            <div class="col-lg-5">

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal</span>
                    <span>
                        ₱{{ number_format(($order->subtotal_minor ?? 0) / 100, 2) }}
                    </span>
                </div>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Tax</span>
                    <span>
                        ₱{{ number_format(($order->tax_minor ?? 0) / 100, 2) }}
                    </span>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Shipping</span>
                    <span>
                        ₱{{ number_format(($order->shipping_minor ?? 0) / 100, 2) }}
                    </span>
                </div>

                <div class="d-flex justify-content-between border-top pt-3">
                    <span class="fw-bold">Total</span>
                    <span class="fw-bold fs-5">
                        ₱{{ number_format(($order->total_minor ?? 0) / 100, 2) }}
                    </span>
                </div>

            </div>
        </div>

    </div>
</div>

{{-- SELLERS AND ITEMS --}}
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white border-bottom p-4">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-shop me-2"></i>
            Sellers & Order Items
        </h5>
    </div>

    <div class="card-body p-0">

        @forelse($order->sellerOrders as $sellerOrder)

            <div class="p-4 {{ !$loop->last ? 'border-bottom' : '' }}">

                <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">

                    <div>
                        <div class="text-muted small">
                            Seller
                        </div>

                        <div class="fw-bold">
                            {{ $sellerOrder->seller?->name ?? 'Unknown Seller' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-muted small">
                            Fulfillment Status
                        </div>

                        <span class="badge bg-secondary">
                            {{ ucwords(str_replace('_', ' ', $sellerOrder->status ?? 'unknown')) }}
                        </span>
                    </div>

                    <div>
                        <div class="text-muted small">
                            Logistics Provider
                        </div>

                        <div class="fw-semibold">
                            {{ $sellerOrder->shipment?->logistic?->company_name ?? 'Not assigned' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-muted small">
                            Seller Total
                        </div>

                        <div class="fw-bold">
                            ₱{{ number_format(($sellerOrder->total_minor ?? 0) / 100, 2) }}
                        </div>
                    </div>

                </div>

                <div class="table-responsive">
                    <table class="table align-middle border mb-0">

                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Variant</th>
                                <th class="text-center">Qty</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($sellerOrder->items as $item)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            {{ $item->product_name }}
                                        </div>

                                        @if($item->product)
                                            <small class="text-muted">
                                                Product ID #{{ $item->product_id }}
                                            </small>
                                        @endif
                                    </td>

                                    <td>
                                        @if($item->variant)
    <span class="badge bg-light text-dark border">
        {{ $item->variant->color ?? 'N/A' }}
        @if(!empty($item->variant->size))
            / {{ $item->variant->size }}
        @endif
    </span>
@elseif($item->variant_id)
    <span class="badge bg-light text-dark border">
        Variant #{{ $item->variant_id }}
    </span>
@elseif($item->size_id)
    <span class="badge bg-light text-dark border">
        Size #{{ $item->size_id }}
    </span>
@endif
                                    </td>

                                    <td class="text-center">
                                        {{ $item->quantity }}
                                    </td>

                                    <td>
                                        ₱{{ number_format(($item->price_minor ?? 0) / 100, 2) }}
                                    </td>

                                    <td class="fw-semibold">
                                        ₱{{ number_format(($item->subtotal_minor ?? 0) / 100, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No order items found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>

            </div>

        @empty

            <div class="text-center text-muted py-5">
                <i class="bi bi-bag-x fs-2 d-block mb-2"></i>
                No seller orders are associated with this order.
            </div>

        @endforelse

    </div>
</div>

{{-- DELIVERY --}}
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white border-bottom p-4">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-truck me-2"></i>
            Delivery Monitoring
        </h5>
    </div>

    <div class="card-body p-4">

        <div class="row g-4">

            <div class="col-lg-3 col-md-6">
                <div class="text-muted small mb-1">
                    Delivery Status
                </div>

                <span class="badge bg-{{ $deliveryClass }}">
                    {{ ucwords(str_replace('_', ' ', $order->delivery_status ?? 'pending')) }}
                </span>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="text-muted small mb-1">
                    Assigned Rider
                </div>

                <div class="fw-semibold">
                    {{ $order->rider?->name ?? 'Not assigned' }}
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="text-muted small mb-1">
                    Assigned At
                </div>

                <div class="fw-semibold">
                    {{ $order->assigned_at?->format('M d, Y h:i A') ?? 'Not assigned' }}
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="text-muted small mb-1">
                    Delivered At
                </div>

                <div class="fw-semibold">
                    {{ $order->delivered_at?->format('M d, Y h:i A') ?? 'Not delivered' }}
                </div>
            </div>

            @if($order->failure_reason)
                <div class="col-12">
                    <div class="alert alert-danger mb-0">
                        <div class="fw-semibold mb-1">
                            Delivery Failure Reason
                        </div>
                        {{ $order->failure_reason }}
                    </div>
                </div>
            @endif

            @if($order->reschedule_reason)
                <div class="col-12">
                    <div class="alert alert-warning mb-0">
                        <div class="fw-semibold mb-1">
                            Reschedule Reason
                        </div>
                        {{ $order->reschedule_reason }}
                    </div>
                </div>
            @endif

        </div>

    </div>
</div>

@endsection