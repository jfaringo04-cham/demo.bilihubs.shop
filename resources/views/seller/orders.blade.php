@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <div>
    <h1 class="h2 mb-1">Seller Orders</h1>
    <p class="text-muted mb-0">
      Manage orders, prepare parcels, and monitor pickup and delivery status for your shop.
    </p>
  </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <i class="bi bi-check-circle me-1"></i>
  {{ session('success') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <i class="bi bi-exclamation-triangle me-1"></i>
  {{ session('error') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger" role="alert">
  <i class="bi bi-exclamation-triangle me-1"></i>
  {{ $errors->first() }}
</div>
@endif

<div class="table-container">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>My Items</th>
          <th>My Total</th>
          <th>Status</th>
          <th>Shipment / Pickup</th>
          <th>Actions</th>
        </tr>
      </thead>

      <tbody>
        @forelse($orders as $order)
        @php
        // Controller loads only the authenticated shop's seller order.
        $sellerOrder = $order->sellerOrders->first();

        $sellerItems = $sellerOrder
        ? $sellerOrder->items
        : collect();

        $status = $sellerOrder?->status ?? 'pending';
        $shipment = $sellerOrder?->shipment;

        $statusColors = [
        'pending' => 'secondary',
        'placed' => 'secondary',
        'confirmed' => 'primary',
        'preparing' => 'info',
        'ready_for_pickup' => 'success',
        'cancelled' => 'danger',
        'canceled' => 'danger',
        'picked_up' => 'warning',
        'at_sorting_center' => 'info',
        'sorted' => 'info',
        'staged' => 'info',
        'assigned_to_rider' => 'primary',
        'out_for_delivery' => 'primary',
        'in_transit' => 'primary',
        'delivery_failed' => 'danger',
        'returned' => 'secondary',
        'delivered' => 'success',
        'completed' => 'success',
        ];

        $statusColor = $statusColors[$status] ?? 'secondary';

        $progressValues = [
        'pending' => 10,
        'placed' => 10,
        'confirmed' => 25,
        'preparing' => 45,
        'ready_for_pickup' => 60,
        'picked_up' => 75,
        'at_sorting_center' => 80,
        'sorted' => 82,
        'staged' => 85,
        'assigned_to_rider' => 88,
        'in_transit' => 90,
        'out_for_delivery' => 95,
        'delivered' => 100,
        'completed' => 100,
        'cancelled' => 0,
        'canceled' => 0,
        'returned' => 0,
        ];

        $progress = $progressValues[$status] ?? 0;

        $isTerminalStatus = in_array($status, [
        'cancelled',
        'canceled',
        'returned',
        'delivered',
        'completed',
        ], true);

        $pickupException = $sellerOrder
        ? \App\Services\PickupExceptionService::forSellerOrder($sellerOrder)
        : null;

        $pickupCode = $pickupException['code'] ?? null;

        $showPickupAlert = $pickupException
        && in_array($pickupCode, [
        'no_valid_hub',
        'no_available_rider',
        'reassignment_needed',
        'pickup_delayed',
        'review_required',
        ], true);
        @endphp

        @if($sellerOrder)
        <tr>
          {{-- ORDER --}}
          <td>
            <div class="fw-semibold">
              {{ $order->order_number }}
            </div>

            <small class="text-muted d-block">
              Seller Order #{{ $sellerOrder->id }}
            </small>

            @if($order->created_at)
            <small class="text-muted d-block mt-1">
              {{ $order->created_at->format('M d, Y') }}
            </small>
            @endif
          </td>

          {{-- CUSTOMER --}}
          <td>
            <div class="fw-medium">
              {{ $order->user?->name ?? 'N/A' }}
            </div>
          </td>

          {{-- ITEMS --}}
          <td>
            @forelse($sellerItems as $item)
            <div class="mb-2">
              <span class="fw-medium">
                {{ $item->product_name }}
              </span>

              <span class="text-muted">
                ×{{ $item->quantity }}
              </span>

              @if($item->size)
              <small class="text-muted d-block">
                Size: {{ $item->size->name }}
              </small>
              @endif
            </div>
            @empty
            <span class="text-muted">
              No items found
            </span>
            @endforelse
          </td>

          {{-- TOTAL --}}
          <td>
            <span class="fw-bold text-nowrap">
              &#8369;{{ number_format($sellerOrder->total_minor / 100, 2) }}
            </span>
          </td>

          {{-- SELLER ORDER STATUS --}}
          <td>
            <span class="badge bg-{{ $statusColor }} mb-2">
              {{ ucwords(str_replace('_', ' ', $status)) }}
            </span>

            @if(!$isTerminalStatus)
            <div class="progress"
              style="height: 6px; min-width: 100px;">
              <div class="progress-bar"
                role="progressbar"
                style="width: {{ $progress }}%;"
                aria-valuenow="{{ $progress }}"
                aria-valuemin="0"
                aria-valuemax="100">
              </div>
            </div>
            @endif

            {{-- EXISTING SELLER ACTIONS --}}
            @if(in_array($status, [
            'pending',
            'placed',
            'confirmed',
            'preparing',
            ], true))
            <div class="mt-2">
              @if(in_array($status, ['pending', 'placed'], true))
              <form method="POST"
                action="{{ route('seller.orders.updateStatus', $order) }}">
                @csrf

                <input type="hidden"
                  name="status"
                  value="confirmed">

                <button type="submit"
                  class="btn btn-sm btn-outline-primary">
                  Confirm Order
                </button>
              </form>

              @elseif($status === 'confirmed')
              <form method="POST"
                action="{{ route('seller.orders.updateStatus', $order) }}">
                @csrf

                <input type="hidden"
                  name="status"
                  value="preparing">

                <button type="submit"
                  class="btn btn-sm btn-outline-primary">
                  Start Preparing
                </button>
              </form>

              @elseif($status === 'preparing')
              <form method="POST"
                action="{{ route('seller.orders.ready', $order) }}">
                @csrf

                <button type="submit"
                  class="btn btn-sm btn-outline-success"
                  onclick="return confirm('Mark your seller order as ready for pickup?')">
                  Ready for Pickup
                </button>
              </form>
              @endif
            </div>
            @endif
          </td>

          {{-- SHIPMENT AND PICKUP STATUS --}}
          <td style="min-width: 230px; max-width: 340px;">

            {{-- PICKUP EXCEPTION --}}
            @if($showPickupAlert)
            <div class="alert alert-{{ $pickupException['severity'] }} p-2 mb-2"
              role="status">
              <div class="d-flex align-items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>

                <div>
                  <div class="fw-semibold small">
                    {{ $pickupException['label'] }}
                  </div>

                  <div class="small mt-1">
                    {{ $pickupException['message'] }}
                  </div>
                </div>
              </div>
            </div>
            @endif

            @if($shipment)

            {{-- SHIPMENT STATUS --}}
            <div class="mb-2">
              <span class="badge bg-info text-dark">
                {{ ucwords(str_replace('_', ' ', $shipment->status)) }}
              </span>
            </div>

            {{-- TRACKING NUMBER --}}
            <small class="d-block text-muted mb-1">
              <i class="bi bi-upc-scan me-1"></i>
              {{ $shipment->tracking_number ?: 'Tracking pending' }}
            </small>

            {{-- LOGISTICS COMPANY --}}
            @if($shipment->logistic)
            <small class="d-block text-muted mb-2">
              <i class="bi bi-building me-1"></i>
              {{ $shipment->logistic->company_name }}
            </small>
            @endif

            {{-- INITIAL SELLER PICKUP --}}
            @if($shipment->picked_up_at)
            <div class="mt-2">
              <span class="badge bg-success">
                <i class="bi bi-check-circle me-1"></i>
                Pickup Confirmed
              </span>

              <small class="d-block text-muted mt-1">
                {{ $shipment->picked_up_at->format('M d, Y h:i A') }}
              </small>
            </div>

            @elseif($shipment->pickup_rider_id)
            <div class="mt-2">
              <span class="badge bg-primary">
                <i class="bi bi-person-check me-1"></i>
                Pickup Rider Assigned
              </span>

              <small class="d-block text-muted mt-1">
                Awaiting physical parcel collection
              </small>
            </div>

            @elseif($status === 'ready_for_pickup')
            <div class="mt-2">
              @if($pickupCode === 'offer_pending')
              <span class="badge bg-info text-dark">
                <i class="bi bi-hourglass-split me-1"></i>
                Awaiting Rider Response
              </span>

              @elseif($pickupCode === 'no_available_rider')
              <span class="badge bg-warning text-dark">
                <i class="bi bi-person-x me-1"></i>
                No Available Rider
              </span>

              @elseif($pickupCode === 'reassignment_needed')
              <span class="badge bg-warning text-dark">
                Pickup Reassignment Needed
              </span>

              @else
              <span class="badge bg-secondary">
                <i class="bi bi-clock me-1"></i>
                Awaiting Pickup Dispatch
              </span>
              @endif
            </div>

            @elseif(!$isTerminalStatus)
            <small class="d-block text-muted mt-2">
              Pickup will begin once the parcel is ready.
            </small>
            @endif

            {{-- FINAL-MILE RIDER --}}
            @if($shipment->rider_id)
            <small class="d-block text-primary mt-2">
              <i class="bi bi-truck me-1"></i>
              Delivery rider assigned
            </small>
            @endif

            @else
            {{-- NO SHIPMENT YET --}}
            <span class="badge bg-light text-dark border">
              No Shipment Yet
            </span>

            @if(in_array($status, ['pending', 'placed', 'confirmed'], true))
            <small class="d-block text-muted mt-2">
              Complete order preparation to arrange pickup.
            </small>

            @elseif($status === 'preparing')
            <small class="d-block text-muted mt-2">
              Mark the parcel Ready for Pickup when preparation is complete.
            </small>

            @elseif($status === 'ready_for_pickup' && !$showPickupAlert)
            <small class="d-block text-muted mt-2">
              Waiting for shipment routing.
            </small>
            @endif
            @endif

          </td>

          {{-- ACTIONS --}}
          <td>
            <a href="{{ route('seller.orders.show', $order) }}"
              class="btn btn-sm btn-outline-primary text-nowrap">
              <i class="bi bi-eye me-1"></i>
              View
            </a>
          </td>
        </tr>
        @endif

        @empty
        <tr>
          <td colspan="7"
            class="text-center text-muted py-4">
            <i class="bi bi-inbox fs-4 d-block mb-2"></i>
            No orders found for your shop.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- PAGINATION --}}
  <div class="mt-3">
    {{ $orders->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection