@extends('rider.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Order Details</h1>
  <a href="{{ route('rider.orders') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
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
        @php
    $shipping = json_decode($order->shipping_address, true);

    $recipient = is_array($shipping)
        ? ($shipping['recipient'] ?? null)
        : null;

    $shippingPhone = is_array($shipping)
        ? ($shipping['phone'] ?? null)
        : null;

    $deliveryAddress = is_array($shipping)
        ? ($shipping['address'] ?? $order->shipping_address)
        : $order->shipping_address;
@endphp

<div class="mb-3">
    <strong>Delivery Address:</strong>

    <div class="mt-2">
        @if($recipient)
            <div>
                <i class="bi bi-person me-1"></i>
                <strong>{{ $recipient }}</strong>
            </div>
        @endif

        @if($shippingPhone)
            <div class="text-muted">
                <i class="bi bi-telephone me-1"></i>
                {{ $shippingPhone }}
            </div>
        @endif

        <div class="mt-1">
            <i class="bi bi-geo-alt me-1"></i>
            {{ $deliveryAddress }}
        </div>
    </div>

    <div class="mt-3">
        <a
            href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($deliveryAddress) }}"
            target="_blank"
            rel="noopener noreferrer"
            class="btn btn-sm btn-outline-primary me-1"
        >
            <i class="bi bi-google"></i>
            Google Maps
        </a>

        <a
            href="https://waze.com/ul?q={{ urlencode($deliveryAddress) }}&navigate=yes"
            target="_blank"
            rel="noopener noreferrer"
            class="btn btn-sm btn-outline-success"
        >
            <i class="bi bi-bicycle"></i>
            Waze
        </a>
    </div>
</div>
        <div class="mb-3">
          <strong>Delivery Zone:</strong> 
          <span class="badge bg-info">{{ $order->delivery_zone ?? 'N/A' }}</span>
        </div>
     <div class="mb-3">
  @switch($order->delivery_status)

    @case('assigned_to_rider')
      <span class="badge bg-warning text-dark">Assigned for Pickup</span>
      @break

    @case('in_transit')
      <span class="badge bg-primary">To Sorting Center</span>
      @break

    @case('delivered_to_sorting_center')
    @case('at_sorting_center')
      <span class="badge bg-info">At Sorting Center</span>
      @break

    @case('ready_for_delivery_pickup')
      <span class="badge bg-warning text-dark">Ready for Delivery Pickup</span>
      @break

    @case('picked_up_from_sorting_center')
      <span class="badge bg-primary">Picked Up</span>
      @break

    @case('out_for_delivery')
      <span class="badge bg-primary">Out for Delivery</span>
      @break

    @case('delivered')
      <span class="badge bg-success">Delivered</span>
      @break

    @case('delivery_failed')
      <span class="badge bg-danger">Delivery Failed</span>
      @break

    @default
      @if($order->ready_for_pickup)
        <span class="badge bg-success">Ready for Pickup</span>
      @else
        <span class="badge bg-secondary">
          {{ ucfirst(str_replace('_', ' ', $order->delivery_status ?? 'Pending')) }}
        </span>
      @endif

  @endswitch
</div>
        @php
          $distance = null;
          if ($order->customer_latitude && $order->customer_longitude && Auth::user()->latitude && Auth::user()->longitude) {
            $distance = $order->calculateDistance(
              Auth::user()->latitude,
              Auth::user()->longitude,
              $order->customer_latitude,
              $order->customer_longitude
            );
          }
        @endphp
        @if($distance)
          <div class="mb-3">
            <strong>Distance to Customer:</strong> {{ number_format($distance, 1) }} km
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
              <th>Qty</th>
              <th>Price</th>
              <th>Subtotal</th>
            </tr>
          </thead>
          <tbody>
            @foreach($order->items as $item)
              <tr>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>&#8369;{{ number_format(($item->price_minor / 100), 2) }}</td>
                <td>&#8369;{{ number_format(($item->subtotal_minor / 100), 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="table-container">

    @if($order->delivery_status === 'assigned_to_rider')

    {{-- SELLER PICKUP STAGE --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h5 class="mb-1">
                <i class="bi bi-box-seam text-primary me-2"></i>
                Pickup From Seller
            </h5>

            <p class="text-muted mb-0">
                This parcel is assigned to you for pickup from the seller.
            </p>
        </div>

        <span class="badge bg-warning text-dark px-3 py-2">
            Awaiting Pickup
        </span>
    </div>

    <hr>

    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        Confirm the pickup only after you have physically received the parcel from the seller.
    </div>

    <form action="{{ route('rider.pickups.confirm', $order) }}" method="POST">
        @csrf

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-box-arrow-in-down me-1"></i>
            Confirm Parcel Pickup
        </button>
    </form>

@elseif($order->delivery_status === 'in_transit')

    {{-- GOING TO SORTING CENTER --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h5 class="mb-1">
                <i class="bi bi-truck text-primary me-2"></i>
                Deliver to Sorting Center
            </h5>

            <p class="text-muted mb-0">
                Parcel has been picked up from the seller.
                Transport it to the assigned sorting center.
            </p>
        </div>

        <span class="badge bg-primary px-3 py-2">
            In Transit
        </span>
    </div>

    <hr>

    <form action="{{ route('rider.pickups.deliver-to-sorting-center', $order) }}" method="POST">
        @csrf

        <button type="submit" class="btn btn-success">
            <i class="bi bi-building-check me-1"></i>
            Delivered to Sorting Center
        </button>
    </form>

@elseif($order->delivery_status === 'delivered')

        {{-- COMPLETED / LOCKED DELIVERY --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="mb-1">
                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                    Delivery Completed
                </h5>
                <p class="text-muted mb-0">
                    This order has been successfully delivered and is now locked.
                </p>
            </div>

            <span class="badge bg-success px-3 py-2">
                Delivered
            </span>
        </div>

        <hr>

        <div class="row g-3">

            <div class="col-md-6">
                <small class="text-muted d-block">Delivered To</small>
                <strong>
                    {{ $order->delivered_to ?: 'Recipient not specified' }}
                </strong>
            </div>

            <div class="col-md-6">
                <small class="text-muted d-block">Delivered At</small>
                <strong>
                    {{ $order->delivered_at
                        ? $order->delivered_at->format('M d, Y h:i A')
                        : 'N/A'
                    }}
                </strong>
            </div>

            @if($order->delivery_signature)
                <div class="col-12">
                    <small class="text-muted d-block">Signature / Notes</small>
                    <strong>{{ $order->delivery_signature }}</strong>
                </div>
            @endif

        </div>

        <div class="alert alert-success mt-4 mb-0">
            <i class="bi bi-shield-check me-2"></i>
            Delivery completed. Further delivery status changes are disabled.
        </div>

    @else

        {{-- ACTIVE DELIVERY --}}
        <h5 class="mb-3">Update Delivery Status</h5>

        <form
            action="{{ route('rider.orders.updateStatus', $order) }}"
            method="POST"
            enctype="multipart/form-data"
        >
            @csrf

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="delivery_status" class="form-label">
                        Status
                    </label>

                    <select
                        name="delivery_status"
                        id="delivery_status"
                        class="form-select"
                        required
                    >
                        <option
                            value="{{ $order->delivery_status }}"
                            selected
                        >
                            {{ ucfirst(str_replace('_', ' ', $order->delivery_status)) }}
                        </option>

                        @if(in_array($order->delivery_status, [
                            'assigned_to_rider',
                            'ready_for_delivery_pickup',
                            'picked_up_from_sorting_center'
                        ]))

                            <option value="out_for_delivery">
                                On the Way
                            </option>

                            <option value="delivery_failed">
                                Failed Attempt
                            </option>

                        @elseif($order->delivery_status === 'out_for_delivery')

                            <option value="delivered">
                                Delivered
                            </option>

                            <option value="delivery_failed">
                                Failed Attempt
                            </option>

                        @elseif($order->delivery_status === 'delivery_failed')

    {{-- No direct retry.
         Failed parcels must be returned to the sorting center first. --}}

@endif
                    </select>
                </div>

                <div class="col-md-6 mb-3">

    {{-- Normal / successful delivery notes --}}
    <div id="delivery-notes-field">
        <label for="delivery_notes" class="form-label">
            Delivery Notes
        </label>

        <textarea
            name="delivery_notes"
            id="delivery_notes"
            class="form-control"
            rows="2"
            placeholder="Optional delivery notes..."
        >{{ old('delivery_notes') }}</textarea>
    </div>

    {{-- Failed delivery reason --}}
    <div id="failure-reason-field" style="display: none;">
        <label for="failure_reason" class="form-label">
            Failure Reason
            <span class="text-danger">*</span>
        </label>

        <textarea
            name="failure_reason"
            id="failure_reason"
            class="form-control"
            rows="2"
            placeholder="Example: Customer not available at delivery address."
        >{{ old('failure_reason') }}</textarea>
    </div>

</div>

            {{-- PROOF OF DELIVERY --}}
            <div id="delivery-proof-fields" style="display: none;">
                <hr>

                <h6 class="mb-3">
                    Proof of Delivery (Required for Delivered)
                </h6>

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label
                            for="proof_of_delivery"
                            class="form-label"
                        >
                            Delivery Photo
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="file"
                            name="proof_of_delivery"
                            id="proof_of_delivery"
                            class="form-control"
                            accept="image/jpeg,image/png,image/jpg"
                        >

                        <small class="text-muted">
                            Photo of delivered package (JPG/PNG, max 5MB)
                        </small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label
                            for="delivered_to"
                            class="form-label"
                        >
                            Delivered To
                        </label>

                        <input
                            type="text"
                            name="delivered_to"
                            id="delivered_to"
                            class="form-control"
                            value="{{ old('delivered_to') }}"
                            placeholder="Name of person who received"
                        >

                        <small class="text-muted">
                            Optional: Name of recipient
                        </small>
                    </div>

                </div>

                <div class="mb-3">
                    <label
                        for="delivery_signature"
                        class="form-label"
                    >
                        Signature / Notes
                    </label>

                    <textarea
                        name="delivery_signature"
                        id="delivery_signature"
                        class="form-control"
                        rows="2"
                        placeholder="Digital signature or additional notes..."
                    >{{ old('delivery_signature') }}</textarea>
                </div>
            </div>

                        <button
                type="submit"
                class="btn btn-bili-hub"
            >
                Update Status
            </button>

        </form>

        {{-- FAILED DELIVERY: parcel must physically return to Speedy Express --}}
        @if(
    $order->delivery_status === 'delivery_failed' &&
    $shipment &&
    $shipment->status === 'delivery_failed'
)
            <div class="mt-3">
                <div class="alert alert-warning mb-3">
                    <strong>Delivery Failed.</strong>
                    Return this parcel to the Speedy Express sorting center before it can be scheduled for redelivery.
                </div>

                <form
                    method="POST"
                    action="{{ route('rider.pickups.return-failed-to-sorting-center', $order) }}"
                    onsubmit="return confirm('Confirm that you have physically returned this parcel to the Speedy Express sorting center?')"
                >
                    @csrf

                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-arrow-return-left"></i>
                        Return to Sorting Center
                    </button>
                </form>
            </div>
        @endif

    @endif

    </div> {{-- end table-container --}}
  </div> {{-- end col-md-8 --}}

  <div class="col-md-4">
    <div class="table-container mb-4">
      <h5 class="mb-3">Delivery Route</h5>
      <div id="rider-map" style="height: 300px; border-radius: 10px;"></div>
    </div>

    @if(in_array($order->delivery_status, [
        'picked_up_from_sorting_center',
        'ready_for_delivery_pickup',
        'out_for_delivery',
        'delivery_failed',
        'delivered'
    ]))

    <div class="table-container proof-section mb-3">
      <h5 class="mb-3">Proof of Delivery</h5>
      @if($order->proof_of_delivery)
        <div class="alert alert-success">
          <strong>Proof submitted:</strong><br>
          @if($order->proof_of_delivery)
            <div class="mt-2">
              <img src="{{ asset('storage/' . $order->proof_of_delivery) }}" alt="Proof of Delivery" class="img-thumbnail" style="max-width: 200px;">
            </div>
          @endif
          @if($order->delivered_to)
            <div class="mt-2"><strong>Delivered to:</strong> {{ $order->delivered_to }}</div>
          @endif
          @if($order->delivery_signature)
            <div class="mt-2"><strong>Signature/Notes:</strong> {{ $order->delivery_signature }}</div>
          @endif
          <div class="mt-2"><small class="text-muted">Delivered at {{ $order->delivered_at->format('M d, Y H:i') }}</small></div>
        </div>
      @else
        <div class="text-muted text-center py-3">
          <i class="bi bi-camera fs-1 d-block mb-2"></i>
          No proof of delivery yet
        </div>
      @endif
    </div>

    <div class="table-container proof-section">
      <h5 class="mb-3">Payment</h5>
      <div class="mb-2">
        <strong>Method:</strong> 
        <span class="badge bg-{{ $order->payment_method == 'cod' ? 'warning' : 'success' }}">
          {{ strtoupper($order->payment_method) }}
        </span>
      </div>
      <div class="mb-2">
        <strong>Status:</strong> 
        <span class="badge bg-{{ $order->payment_status == 'paid' ? 'success' : 'secondary' }}">
          {{ ucfirst($order->payment_status) }}
        </span>
      </div>
      <div class="mb-3">
        <strong>Total Amount:</strong> &#8369;{{ number_format(($order->total_minor / 100), 2) }}
      </div>

      @if($order->payment_method == 'cod' && $order->payment_status == 'unpaid')
        <form action="{{ route('rider.orders.confirmCOD', $order) }}" method="POST" class="mb-2">
          @csrf
          <button type="submit" class="btn btn-outline-warning w-100 mb-2">Confirm COD Order</button>
        </form>

        <form action="{{ route('rider.orders.collect', $order) }}" method="POST">
          @csrf
          <div class="mb-2">
            <label class="form-label">Amount Collected</label>
            <input type="number" name="amount_collected" class="form-control" step="0.01" value="{{ ($order->total_minor / 100) }}" required>
          </div>
          <button type="submit" class="btn btn-bili-hub w-100">Collect Payment</button>
        </form>
      @endif

      @if($order->payment_status == 'paid')
        <div class="alert alert-success mt-2">
          <strong>Collected:</strong> &#8369;{{ number_format(($order->amount_collected_minor / 100), 2) }}<br>
          <small>{{ $order->collected_at->format('M d, Y H:i') }}</small>
          @if($order->collector)
            <br><small>by {{ $order->collector->name }}</small>
          @endif
        </div>
           @endif
    </div>

    @endif

  </div>
</div>
@endsection

@push('scripts')
  @vite('resources/js/rider/deliveries.js')
@endpush

@php
    $mapShipping = json_decode($order->shipping_address, true);

    $mapAddress = is_array($mapShipping)
        ? ($mapShipping['address'] ?? $order->shipping_address)
        : $order->shipping_address;

    $riderDeliveryData = [
        'address' => $mapAddress,
        'customerName' => $order->user->name ?? 'Customer',
        'latitude' => $order->customer_latitude,
        'longitude' => $order->customer_longitude,
    ];
@endphp

<script type="application/json" id="rider-delivery-data">
    @json($riderDeliveryData)
</script>


