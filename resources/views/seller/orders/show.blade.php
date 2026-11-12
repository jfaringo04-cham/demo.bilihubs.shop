@extends('seller.layout')

@section('content')
@php
$sellerOrder = $order->sellerOrders->first();
$status = $sellerOrder?->status ?? 'pending';
$shipment = $sellerOrder?->shipment;

$colors = [
'pending' => 'secondary',
'confirmed' => 'primary',
'preparing' => 'info',
'ready_for_pickup' => 'success',
'picked_up' => 'warning',
'in_transit' => 'primary',
'delivered' => 'success',
'cancelled' => 'danger',
];

$statusColor = $colors[$status] ?? 'secondary';
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="mb-1">Seller Order Details</h2>
    <div class="text-muted">
      {{ $order->order_number }}
      · Seller Order #{{ $sellerOrder?->id }}
    </div>
  </div>

  <a href="{{ route('seller.orders') }}"
    class="btn btn-outline-secondary">
    Back to Orders
  </a>
</div>

@if(!$sellerOrder)
<div class="alert alert-danger">
  Seller order not found for this account.
</div>
@else
<div class="row g-4">

  <div class="col-lg-8">

    <div class="table-container mb-4">
      <h5 class="mb-3">Customer Information</h5>

      <p><strong>Name:</strong> {{ $order->user?->name ?? 'N/A' }}</p>
      <p><strong>Email:</strong> {{ $order->user?->email ?? 'N/A' }}</p>
      <p><strong>Phone:</strong> {{ $order->user?->phone ?? 'N/A' }}</p>

      @php
      $shippingAddress = $order->shipping_address;

      if (is_string($shippingAddress)) {
      $decodedAddress = json_decode($shippingAddress, true);

      if (json_last_error() === JSON_ERROR_NONE && is_array($decodedAddress)) {
      $shippingAddress = $decodedAddress;
      }
      }
      @endphp

      <div class="mb-3">
        <strong>Shipping Address:</strong>

        @if(is_array($shippingAddress))
        <div>{{ $shippingAddress['recipient'] ?? 'N/A' }}</div>
        <div>{{ $shippingAddress['phone'] ?? 'N/A' }}</div>
        <div>{{ $shippingAddress['address'] ?? 'N/A' }}</div>
        @else
        <div>{{ $shippingAddress ?: 'N/A' }}</div>
        @endif
      </div>


      <div>
        <strong>My Order Status:</strong>
        <span class="badge bg-{{ $statusColor }}">
          {{ ucwords(str_replace('_', ' ', $status)) }}
        </span>
      </div>
    </div>

    <div class="table-container mb-4">
      <h5 class="mb-3">My Order Items</h5>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead class="table-light">
            <tr>
              <th>Product</th>
              <th>Size</th>
              <th>Quantity</th>
              <th>Price</th>
              <th>Subtotal</th>
            </tr>
          </thead>
          <tbody>
            @forelse($sellerOrder->items as $item)
            <tr>
              <td>{{ $item->product_name }}</td>
              <td>{{ $item->size?->name ?? 'N/A' }}</td>
              <td>{{ $item->quantity }}</td>
              <td>
                &#8369;{{ number_format($item->price_minor / 100, 2) }}
              </td>
              <td>
                &#8369;{{ number_format($item->subtotal_minor / 100, 2) }}
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="5" class="text-center text-muted">
                No items found.
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="table-container mb-4">
      <h5 class="mb-3">Order Processing</h5>

      @if(in_array($status, ['pending', 'placed']))
      <form method="POST"
        action="{{ route('seller.orders.updateStatus', $order) }}">
        @csrf
        <input type="hidden" name="status" value="confirmed">
        <button class="btn btn-primary" type="submit">
          Confirm Order
        </button>
      </form>

      @elseif($status === 'confirmed')
      <form method="POST"
        action="{{ route('seller.orders.updateStatus', $order) }}">
        @csrf
        <input type="hidden" name="status" value="preparing">
        <button class="btn btn-primary" type="submit">
          Start Preparing
        </button>
      </form>

      @elseif($status === 'preparing')
      <form method="POST"
        action="{{ route('seller.orders.ready', $order) }}"
        onsubmit="return confirm('Are your items packed and ready for pickup?')">
        @csrf
        <button class="btn btn-success" type="submit">
          Mark as Ready for Pickup
        </button>
      </form>

      @elseif($status === 'ready_for_pickup')
      <div class="alert alert-success mb-0">
        Your parcel is ready for logistics pickup.
      </div>
      @elseif($status === 'picked_up')
      <div class="alert alert-info mb-0">
        Your parcel has been picked up from your shop and is on the way to the sorting center.
      </div>

      @elseif($status === 'in_transit')
      <div class="alert alert-info mb-0">
        Your parcel is in transit through the logistics network.
      </div>

      @elseif($status === 'out_for_delivery')
      <div class="alert alert-warning mb-0">
        Your parcel is out for delivery to the customer.
      </div>

      @else
      <span class="badge bg-{{ $statusColor }}">
        {{ ucwords(str_replace('_', ' ', $status)) }}
      </span>
      @endif
    </div>

    @if($sellerOrder->return_status !== 'none')
    <div class="table-container mb-4">
      <h5 class="mb-3">Return Request</h5>

      <p>
        <strong>Status:</strong>
        {{ ucfirst($sellerOrder->return_status) }}
      </p>

      <p>
        <strong>Reason:</strong>
        {{ $sellerOrder->return_reason ?? 'N/A' }}
      </p>

      @include('orders.return-details')
      @error('return_refund')
      <p class="alert alert-danger">{{ $message }}</p>
      @enderror

      @error('return_pickup')
      <p class="alert alert-danger">{{ $message }}</p>
      @enderror

      @if($sellerOrder->return_status === 'requested'
          || ($sellerOrder->return_status === 'approved' && !$sellerOrder->return_received_at && !$sellerOrder->returnShipment))
      @php
        $returnShippingFee = \App\Models\ReturnShipment::quoteSellerShippingFee($sellerOrder);
      @endphp
      <p><strong>Return shipping fee covered by seller:</strong> ₱{{ number_format($returnShippingFee / 100, 2) }}.
        The fee will be recorded when the return is delivered.</p>
      @endif

      @if($sellerOrder->return_status === 'approved' && !$sellerOrder->return_received_at && !$sellerOrder->returnShipment)
      <form method="POST" action="{{ route('seller.orders.approveReturn', $order) }}" class="mb-2">
        @csrf
        <input type="hidden" name="seller_order_id" value="{{ $sellerOrder->id }}">
        <input type="hidden" name="seller_shipping_fee_minor" value="{{ $returnShippingFee }}">
        <button type="submit" class="btn btn-outline-primary btn-sm">Book return pickup</button>
      </form>
      @endif

      @if($sellerOrder->return_status === 'approved' && !$sellerOrder->return_received_at
          && (!$sellerOrder->returnShipment || $sellerOrder->returnShipment->delivered_at))
      <form method="POST" action="{{ route('seller.orders.receiveReturn', $order) }}">
        @csrf
        <input type="hidden" name="seller_order_id" value="{{ $sellerOrder->id }}">
        <button type="submit" class="btn btn-primary btn-sm">Mark returned item received</button>
      </form>
      @elseif($sellerOrder->return_status === 'approved' && $sellerOrder->return_received_at && !$sellerOrder->return_resolved_at)
      <form method="POST" action="{{ route('seller.orders.resolveReturn', $order) }}">
        @csrf
        <input type="hidden" name="seller_order_id" value="{{ $sellerOrder->id }}">
        <label for="return-resolution-{{ $sellerOrder->id }}" class="form-label">Outcome provided to buyer</label>
        <select id="return-resolution-{{ $sellerOrder->id }}" name="return_resolution" class="form-select mb-2" required>
          <option value="">Select an outcome</option>
          @if($order->payment_method === 'cod' && $order->payment_status === 'paid')
            <option value="refund">Start refund</option>
          @endif
          <option value="replacement">Replacement provided</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Record outcome</button>
      </form>
      @endif

      @if($sellerOrder->return_received_at && $sellerOrder->returnShipment?->rider_payout_minor !== null
          && !$sellerOrder->returnShipment->wallet_settled_at)
      <form method="POST" action="{{ route('seller.orders.receiveReturn', $order) }}" class="mt-2">
        @csrf
        <input type="hidden" name="seller_order_id" value="{{ $sellerOrder->id }}">
        <button type="submit" class="btn btn-outline-primary btn-sm">Retry return shipping payout</button>
      </form>
      @endif

      @if($sellerOrder->return_status === 'requested')
      <div class="d-flex gap-2">
        <form method="POST"
          action="{{ route('seller.orders.approveReturn', $order) }}"
          onsubmit="return confirm('Approve this return request?')">
          @csrf
          <input type="hidden"
            name="seller_order_id"
            value="{{ $sellerOrder->id }}">
          <input type="hidden" name="seller_shipping_fee_minor" value="{{ $returnShippingFee }}">
          <button type="submit" class="btn btn-success btn-sm">
            Approve Return
          </button>
        </form>

        <form method="POST"
          action="{{ route('seller.orders.rejectReturn', $order) }}"
          onsubmit="return confirm('Reject this return request?')">
          @csrf
          <input type="hidden"
            name="seller_order_id"
            value="{{ $sellerOrder->id }}">
          <button type="submit" class="btn btn-danger btn-sm">
            Reject Return
          </button>
        </form>
      </div>
      @endif
    </div>
    @endif


    {{-- Seller-specific Reschedule Request --}}
    @if(($sellerOrder->reschedule_status ?? 'none') !== 'none')
    <div class="table-container mb-4">
      <h5 class="mb-3">
        <i class="bi bi-calendar-check me-2"></i>
        Reschedule Request
      </h5>

      <p>
        <strong>Seller Order:</strong>
        #{{ $sellerOrder->id }}
      </p>

      <p>
        <strong>Status:</strong>
        @php
        $rescheduleStatus = $sellerOrder->reschedule_status;
        $rescheduleColor = match ($rescheduleStatus) {
        'requested' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
        };
        @endphp

        <span class="badge bg-{{ $rescheduleColor }}">
          {{ ucfirst($rescheduleStatus) }}
        </span>
      </p>

      <p>
        <strong>Reason:</strong>
        {{ $sellerOrder->reschedule_reason ?: 'No reason provided' }}
      </p>

      @if($sellerOrder->reschedule_requested_at)
      <p>
        <strong>Requested At:</strong>
        {{ $sellerOrder->reschedule_requested_at->format('M d, Y g:i A') }}
      </p>
      @endif

      @if($sellerOrder->reschedule_status === 'approved' && $sellerOrder->rescheduled_at)
      <p>
        <strong>Approved At:</strong>
        {{ $sellerOrder->rescheduled_at->format('M d, Y g:i A') }}
      </p>
      @endif

      @if($sellerOrder->reschedule_status === 'requested')
      @if(
      $shipment &&
      $shipment->status === 'failed_return_at_sorting_center' &&
      $shipment->failed_return_received_at !== null
      )
      <div class="alert alert-success">
        Logistics has confirmed receipt of the failed parcel.
        You may approve the reschedule request.
      </div>
      @else
      <div class="alert alert-warning">
        Waiting for Logistics to confirm receipt of the failed parcel.
        Approval is not available yet.
      </div>
      @endif

      <div class="d-flex gap-2 flex-wrap">
        @if(
        $shipment &&
        $shipment->status === 'failed_return_at_sorting_center' &&
        $shipment->failed_return_received_at !== null
        )
        <form method="POST"
          action="{{ route('orders.approveReschedule', $order) }}"
          onsubmit="return confirm('Approve this seller order reschedule?')">
          @csrf

          <input type="hidden"
            name="seller_order_id"
            value="{{ $sellerOrder->id }}">

          <button type="submit" class="btn btn-success btn-sm">
            <i class="bi bi-check-circle me-1"></i>
            Approve Reschedule
          </button>
        </form>
        @endif

        <form method="POST"
          action="{{ route('orders.rejectReschedule', $order) }}"
          onsubmit="return confirm('Reject this seller order reschedule?')">
          @csrf

          <input type="hidden"
            name="seller_order_id"
            value="{{ $sellerOrder->id }}">

          <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-x-circle me-1"></i>
            Reject Reschedule
          </button>
        </form>
      </div>
      @endif
    </div>
    @endif


    <div class="table-container mb-4">
      <h5 class="mb-3">My Shipment</h5>

      @if($shipment)
      <p>
        <strong>Tracking Number:</strong>
        {{ $shipment->tracking_number }}
      </p>

      <p>
        <strong>Shipment Status:</strong>
        {{ ucwords(str_replace('_', ' ', $shipment->status)) }}
      </p>

      <p>
        <strong>Logistics:</strong>
        {{ $shipment->logistic?->company_name ?? 'Pending assignment' }}
      </p>

      <p>
        <strong>Rider:</strong>
        {{ $shipment->rider?->name ?? 'Waiting for rider assignment' }}
      </p>

      @if($shipment->delivered_at)
      <p>
        <strong>Delivered At:</strong>
        {{ $shipment->delivered_at->format('M d, Y g:i A') }}
      </p>
      @endif

      <a href="{{ route('shipments.qr.label', $shipment) }}"
        target="_blank"
        class="btn btn-sm btn-outline-primary">
        Print QR / Barcode Label
      </a>
      @else
      <div class="text-muted">
        No shipment created for this seller order yet.
      </div>
      @endif
    </div>

  </div>

  <div class="col-lg-4">
    <div class="table-container mb-4">
      <h5 class="mb-3">My Order Summary</h5>

      <div class="d-flex justify-content-between mb-2">
        <span>Subtotal</span>
        <span>
          &#8369;{{ number_format($sellerOrder->subtotal_minor / 100, 2) }}
        </span>
      </div>

      <div class="d-flex justify-content-between mb-2">
        <span>Shipping</span>
        <span>
          &#8369;{{ number_format($sellerOrder->shipping_minor / 100, 2) }}
        </span>
      </div>

      @php
      $otherChargesMinor = $sellerOrder->total_minor
      - $sellerOrder->subtotal_minor
      - $sellerOrder->shipping_minor;
      @endphp

      @if($otherChargesMinor != 0)
      <div class="d-flex justify-content-between mb-2">
        <span>Other allocated charges / adjustments</span>
        <span>
          &#8369;{{ number_format($otherChargesMinor / 100, 2) }}
        </span>
      </div>
      @endif

      <hr>

      <div class="d-flex justify-content-between fw-bold">
        <span>My Total</span>
        <span>
          &#8369;{{ number_format($sellerOrder->total_minor / 100, 2) }}
        </span>
      </div>
    </div>
  </div>

</div>
@endif
@endsection