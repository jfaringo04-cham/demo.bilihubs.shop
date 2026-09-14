@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Order Details</h1>
  <a href="{{ route('seller.orders') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Orders</a>
</div>

<div class="row">
  <div class="col-md-8">
    <div class="table-container mb-4">
      <h5 class="mb-3">Customer Information</h5>
      <div class="row">
        <div class="col-md-6 mb-3">
          <strong>Name:</strong> {{ $order->user->name ?? 'N/A' }}
        </div>
        <div class="col-md-6 mb-3">
          <strong>Email:</strong> {{ $order->user->email ?? 'N/A' }}
        </div>
        <div class="col-md-6 mb-3">
          <strong>Phone:</strong> {{ $order->user->phone ?? 'N/A' }}
        </div>
        <div class="col-md-6 mb-3">
          <strong>Order #:</strong> {{ $order->order_number }}
        </div>
      </div>
      <div class="mb-3">
        <strong>Shipping Address:</strong><br>
        {{ $order->shipping_address }}
      </div>
      <div class="mb-3">
        <strong>Order Status:</strong> 
        <span class="badge bg-{{ $order->statusBadgeClass() }}">
          {{ ucfirst(str_replace('_', ' ', $order->status)) }}
        </span>
      </div>
      @if($order->ready_for_pickup && $order->shipment)
        <div class="mb-3">
          <strong>Ready for Pickup:</strong> 
          <span class="badge bg-success">
            Shipment #{{ $order->shipment->tracking_number }}
          </span>
        </div>
        <div class="mb-3">
          <strong>Shipment Status:</strong>
          <span class="badge bg-{{ $order->shipment->statusBadgeClass() }}">
            {{ ucfirst(str_replace('_', ' ', $order->shipment->status)) }}
          </span>
        </div>
        @if($order->shipment->rider_id)
          <div class="mb-3">
            <strong>Rider Assigned:</strong> {{ $order->shipment->rider->name ?? 'N/A' }}
            @if($order->shipment->rider->phone)
              <span class="text-muted">| {{ $order->shipment->rider->phone }}</span>
            @endif
          </div>
        @else
          <div class="mb-3">
            <strong>Rider Assignment:</strong> <span class="text-muted">Waiting for rider assignment...</span>
          </div>
        @endif
        @if($order->shipment->delivered_at)
          <div class="mb-3">
            <strong>Delivered At:</strong> {{ \Carbon\Carbon::parse($order->shipment->delivered_at)->format('M d, Y g:i A') }}
          </div>
        @endif
        @if($order->shipment->scanned_at_seller)
          <div class="mb-3">
            <strong>QR Scanned:</strong>
            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Rider scanned QR on {{ \Carbon\Carbon::parse($order->shipment->scanned_at_seller)->format('M d, Y g:i A') }}</span>
          </div>
        @endif
      @endif

      @if($order->ready_for_pickup && $order->shipment)
        <div class="mb-3">
          <strong>Shipment Progress</strong>
          <div class="progress" style="height: 20px;">
            @foreach($order->shipment->timelineSteps() as $step)
              @php
                $isActive = $step['timestamp'] !== null;
                $bgClass = $isActive ? $step['class'] : 'secondary';
              @endphp
              <div class="progress-bar bg-{{ $bgClass }}" role="progressbar" style="width: {{ 100 / count($order->shipment->timelineSteps()) }}%" title="{{ $step['label'] }}">
                {{ $step['label'] }}
              </div>
            @endforeach
          </div>
        </div>
      @endif
      @if($order->notes)
        <div class="mb-3">
          <strong>Notes:</strong> {{ $order->notes }}
        </div>
      @endif
    </div>

    <div class="table-container mb-4">
      <h5 class="mb-3">Order Items</h5>
      <div class="table-responsive">
        <table class="table">
          <thead class="table-light">
            <tr>
              <th>Product</th>
              <th>Size</th>
              <th>Qty</th>
              <th>Price</th>
              <th>Subtotal</th>
            </tr>
          </thead>
          <tbody>
            @php
              $sellerItems = $order->items->filter(function($item) {
                return $item->product->user_id === Auth::id();
              });
            @endphp
            @foreach($sellerItems as $item)
              <tr>
                <td>
                  {{ $item->product_name }}
                  @if($item->product && $item->product->stock < $item->quantity)
                    <span class="badge bg-danger ms-1"><i class="bi bi-exclamation-triangle"></i> Low Stock</span>
                  @endif
                </td>
                <td>{{ $item->size->name ?? 'N/A' }}</td>
                <td>{{ $item->quantity }}</td>
                <td>&#8369;{{ number_format($item->price, 2) }}</td>
                <td>&#8369;{{ number_format($item->subtotal, 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

      <div class="table-container mb-4">
      <h5 class="mb-3">Order Actions</h5>
      @if(!$order->ready_for_pickup)
        @if($order->status == 'placed')
          <form action="{{ route('seller.orders.updateStatus', $order) }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="status" value="confirmed">
            <button type="submit" class="btn btn-sm btn-primary">
              <i class="bi bi-check-circle"></i> Accept & Start Processing
            </button>
          </form>
        @endif

        @if(in_array($order->status, ['confirmed', 'preparing']))
          <form action="{{ route('seller.orders.ready', $order) }}" method="POST" class="d-inline" onsubmit="return confirm('Mark all items as ready for pickup? Ensure products are packed and ready for the rider.')">
            @csrf
            <button type="submit" class="btn btn-sm btn-warning">
              <i class="bi bi-box-seam"></i> Mark as Ready for Pickup
            </button>
          </form>
        @endif
      @endif

      @if($order->ready_for_pickup && $order->shipment)
        <a href="{{ route('logistic.shipments.show', $order->shipment) }}" target="_blank" class="btn btn-sm btn-outline-info">
          <i class="bi bi-printer"></i> Print Shipping Label
        </a>
        <a href="{{ route('shipments.qr.label', $order->shipment) }}" target="_blank" class="btn btn-sm btn-bili-hub">
          <i class="bi bi-qr-code"></i> Print QR / Barcode Label
        </a>
        <div class="mt-2 small text-muted">
          <i class="bi bi-info-circle"></i> Print the QR label and attach it to the parcel. The rider will scan it upon pickup to confirm and enter the logistics system.
        </div>
      @endif
    </div>

    @if($order->status == 'delivery_failed')
      <div class="table-container mb-4">
        <h5 class="mb-3">Delivery Failed - Action Required</h5>
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle"></i> Delivery failed. Reason: {{ $order->failure_reason ?? 'No reason provided' }}
        </div>
        <form action="{{ route('seller.orders.approveReschedule', $order) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve reschedule for this order?')">
          @csrf
          <button type="submit" class="btn btn-success btn-sm">
            <i class="bi bi-check-circle"></i> Approve Reschedule
          </button>
        </form>
        <form action="{{ route('seller.orders.rejectReschedule', $order) }}" method="POST" class="d-inline ms-2" onsubmit="return confirm('Reject reschedule? Buyer will be asked to return the item instead.')">
          @csrf
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-x-circle"></i> Reject Reschedule
          </button>
        </form>
      </div>
    @endif

    @if($order->status == 'return_requested')
      <div class="table-container mb-4">
        <h5 class="mb-3">Return Request</h5>
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle"></i> Buyer requested return for this order. Reason: {{ $order->return_reason ?? 'No reason provided' }}
        </div>
        <form action="{{ route('seller.orders.approveReturn', $order) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve return for this order?')">
          @csrf
          <button type="submit" class="btn btn-success btn-sm">
            <i class="bi bi-check-circle"></i> Approve Return
          </button>
        </form>
        <form action="{{ route('seller.orders.rejectReturn', $order) }}" method="POST" class="d-inline ms-2" onsubmit="return confirm('Reject return request?')">
          @csrf
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-x-circle"></i> Reject Return
          </button>
        </form>
      </div>
    @endif

    <div class="table-container mb-4">
      <h5 class="mb-3">Update Order Status</h5>
      <form action="{{ route('seller.orders.updateStatus', $order) }}" method="POST">
        @csrf
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="status" class="form-label">Status</label>
            <select name="status" id="status" class="form-select" {{ $order->ready_for_pickup ? 'disabled' : '' }} required>
              <option value="placed" {{ $order->status == 'placed' ? 'selected' : '' }}>Placed</option>
              <option value="confirmed" {{ $order->status == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
              <option value="preparing" {{ $order->status == 'preparing' ? 'selected' : '' }}>Preparing</option>
              <option value="ready_for_pickup" {{ $order->status == 'ready_for_pickup' ? 'selected' : '' }} {{ $order->ready_for_pickup ? 'hidden' : '' }}>Ready for Pickup</option>
              <option value="delivered" {{ $order->status == 'delivered' ? 'selected' : '' }} {{ $order->ready_for_pickup ? 'hidden' : '' }}>Delivered</option>
              <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-bili-hub" {{ $order->ready_for_pickup ? 'disabled' : '' }}>Update Status</button>
      </form>
    </div>
  </div>

  <div class="col-md-4">
    <div class="table-container mb-4">
      <h5 class="mb-3">Order Summary</h5>
      <div class="d-flex justify-content-between mb-2">
        <span>Subtotal</span>
        <span>&#8369;{{ number_format($order->subtotal, 2) }}</span>
      </div>
      <div class="d-flex justify-content-between mb-2">
        <span>Tax</span>
        <span>&#8369;{{ number_format($order->tax, 2) }}</span>
      </div>
      <div class="d-flex justify-content-between mb-2">
        <span>Shipping</span>
        <span>&#8369;{{ number_format($order->shipping, 2) }}</span>
      </div>
      <hr>
      <div class="d-flex justify-content-between fw-bold">
        <span>Total</span>
        <span>&#8369;{{ number_format($order->total, 2) }}</span>
      </div>
    </div>

    <div class="table-container">
      <h5 class="mb-3">Customer</h5>
      <p><strong>Name:</strong> {{ $order->user->name ?? 'N/A' }}</p>
      <p><strong>Email:</strong> {{ $order->user->email ?? 'N/A' }}</p>
      <p><strong>Phone:</strong> {{ $order->user->phone ?? 'N/A' }}</p>
    </div>
  </div>
</div>
@endsection
