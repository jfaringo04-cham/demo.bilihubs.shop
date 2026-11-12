@extends(isset($logistic) ? 'layouts.logistic' : 'rider.layout', ['title' => 'Return Shipments'])

@section('content')
<div class="container-fluid py-4">
  <h1 class="h3 mb-3">Return Shipments</h1>

  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  @forelse($shipments as $shipment)
    @php
      $item = $shipment->sellerOrder->items->find($shipment->sellerOrder->return_order_item_id);
    @endphp
    <div class="card mb-3">
      <div class="card-body">
        <h2 class="h5">{{ $shipment->tracking_number }}</h2>
        <p>Order {{ $shipment->sellerOrder->order->order_number }} · {{ $shipment->status_label }}</p>
        @if($item)
          <p>{{ $item->product_name }} × {{ $shipment->sellerOrder->return_quantity }}</p>
        @endif
        @unless($shipment->delivered_at)
          <p><strong>Pickup from buyer:</strong> {{ $shipment->pickup_address_label }}</p>
          <p><strong>Deliver to seller:</strong> {{ $shipment->delivery_address }}</p>
        @endunless

        @if(!isset($logistic) && !$shipment->delivered_at)
          <p><strong>No cash to collect.</strong></p>
        @endif

        @isset($logistic)
          @if($shipment->seller_shipping_fee_minor !== null)
            <p><strong>Seller return shipping fee:</strong>
              ₱{{ number_format($shipment->seller_shipping_fee_minor / 100, 2) }}
              · @if($shipment->wallet_settled_at)
      Wallet payout complete
    @elseif($shipment->seller_shipping_fee_posted_at)
      Charge recorded
    @else
      Pending delivery
    @endif
            </p>
          @endif
          @php
            $pickupHub = $pickupHubs->get($shipment->id);
            $eligibleRiders = $ridersByHub->get($pickupHub?->id, collect());
          @endphp
          @if($pickupHub)
            <p><strong>Pickup hub:</strong> {{ $pickupHub->name }}</p>
          @endif
          @if($shipment->rider_id)
            <p class="mb-0"><strong>Assigned rider:</strong> {{ $shipment->rider?->name }}</p>
          @elseif(!$pickupHub)
            <p class="mb-0">No active buyer-side hub is linked to the original shipment.</p>
          @elseif($eligibleRiders->isNotEmpty())
            <form method="POST" action="{{ route('logistic.returns.assign', $shipment) }}">
              @csrf
              <label class="form-label" for="rider-{{ $shipment->id }}">Rider</label>
              <select class="form-select mb-2" id="rider-{{ $shipment->id }}" name="rider_id" required>
                <option value="">Choose rider</option>
                @foreach($eligibleRiders as $rider)
                  <option value="{{ $rider->id }}">{{ $rider->name }} ({{ $rider->current_load }}/{{ $rider->max_capacity }} assigned)</option>
                @endforeach
              </select>
              <button class="btn btn-primary" type="submit">Assign rider</button>
            </form>
          @else
            <p class="mb-0">No approved riders at this hub have available capacity.</p>
          @endif
        @else
          @if(!$shipment->picked_up_at)
            @include('returns.pickup-verification')
          @elseif(!$shipment->delivered_at)
            <form method="POST" enctype="multipart/form-data" action="{{ route('rider.returns.deliver', $shipment) }}">
              @csrf
              <label class="form-label" for="handover-{{ $shipment->id }}">Handover photo (JPG, PNG or WebP, up to 2 MB)</label>
              <input class="form-control mb-2" id="handover-{{ $shipment->id }}" type="file" name="handover_photo" accept="image/jpeg,image/png,image/webp" required>
              <button class="btn btn-primary" type="submit">Mark delivered to seller</button>
            </form>
          @endif
        @endisset
      </div>
    </div>
  @empty
    <p>No return shipments yet.</p>
  @endforelse

  {{ $shipments->links() }}
</div>
@endsection
