@php
  $returnedItem = $sellerOrder->items->find($sellerOrder->return_order_item_id);
@endphp
@if($returnedItem)
<p>
  <strong>Item:</strong> {{ $returnedItem->product_name }}
  @if($returnedItem->variation)
    / {{ $returnedItem->variation->display_name ?: $returnedItem->variation->name }}
  @endif
  @if($returnedItem->size)
    ({{ $returnedItem->size->name }})
  @endif
  &times; {{ $sellerOrder->return_quantity }}
</p>
@endif

@if($sellerOrder->return_photo_path)
<p>
  <a href="{{ route('orders.returnPhoto', [$order, $sellerOrder->id]) }}"
    target="_blank" rel="noopener">View damage photo</a>
</p>
@endif

@if($sellerOrder->returnShipment)
<p><strong>Return tracking:</strong> {{ $sellerOrder->returnShipment->tracking_number }}</p>
<p><strong>Return shipment status:</strong> {{ $sellerOrder->returnShipment->status_label }}</p>
@if($sellerOrder->returnShipment->seller_shipping_fee_minor !== null)
  @if((int) auth()->id() === (int) $order->user_id)
  <p><strong>Return shipping:</strong> ₱0.00 · Covered by seller</p>
  @else
  <p><strong>Return shipping covered by seller:</strong>
    ₱{{ number_format($sellerOrder->returnShipment->seller_shipping_fee_minor / 100, 2) }}
    · @if($sellerOrder->returnShipment->wallet_settled_at)
      Wallet payout complete
    @elseif($sellerOrder->returnShipment->seller_shipping_fee_posted_at)
      Charge recorded
    @else
      Pending delivery
    @endif
  </p>
  @endif
@endif
@if((int) auth()->id() === (int) $order->user_id && $sellerOrder->returnShipment->canVerifyPickup())
<div class="border rounded p-3 mb-3">
  <h6>Return Pickup QR</h6>
  <p>Show this QR to the assigned rider when handing over the parcel.</p>
  <img class="img-fluid mb-2" width="240" height="240" alt="Return pickup QR"
    src="{{ route('return-shipments.pickup-qr', $sellerOrder->returnShipment, false) }}">
  <p class="mb-0">Manual pickup code: <code>{{ $sellerOrder->returnShipment->pickupCode() }}</code></p>
</div>
@endif
@if($sellerOrder->returnShipment->handover_photo)
<p>
  <a href="{{ route('return-shipments.proof', $sellerOrder->returnShipment) }}"
    target="_blank" rel="noopener">View handover photo</a>
</p>
@endif
@endif

@if($sellerOrder->return_received_at)
<p><strong>Returned item received:</strong> {{ $sellerOrder->return_received_at }}</p>
@endif

@if($sellerOrder->return_resolution === 'refund')
@include('orders.return-refund')
@elseif($sellerOrder->return_resolved_at)
<p><strong>Recorded outcome:</strong> {{ ucfirst($sellerOrder->return_resolution) }}</p>
@endif
