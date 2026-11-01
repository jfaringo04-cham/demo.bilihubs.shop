@extends('rider.layout')



@section('content')



<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">

  <h1 class="h2">Available Pickups</h1>



  <div class="d-flex gap-2 flex-wrap">

    <a href="{{ route('rider.deliveries') }}"

      class="btn btn-sm btn-outline-primary">

      <i class="bi bi-bell"></i> Delivery Notifications

    </a>



    <a href="{{ route('rider.pickups', ['status' => 'sorting_center']) }}"

      class="btn btn-sm btn-outline-secondary {{ request('status') == 'sorting_center' ? 'active' : '' }}">

      <i class="bi bi-sort-numeric-down"></i> From Sorting Center

    </a>



    <a href="{{ route('rider.pickups') }}"

      class="btn btn-sm btn-outline-secondary {{ request('status') != 'sorting_center' ? 'active' : '' }}">

      <i class="bi bi-truck"></i> From Sellers

    </a>

  </div>

</div>







@if(session('error'))

<div class="alert alert-danger alert-dismissible fade show" role="alert">

  <i class="bi bi-exclamation-triangle-fill me-2"></i>

  {{ session('error') }}



  <button type="button"

    class="btn-close"

    data-bs-dismiss="alert"

    aria-label="Close"></button>

</div>

@endif



{{-- NEW: Pending seller pickup offers --}}

@if(request('status') !== 'sorting_center' && isset($pickupOffers) && $pickupOffers->isNotEmpty())

<div class="card border-warning shadow-sm mb-4">



  <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center flex-wrap gap-2">

    <div>

      <h5 class="mb-1 fw-bold">

        <i class="bi bi-box-seam me-2"></i>

        New Pickup Requests

      </h5>



      <small class="text-muted">

        Review and accept a request before collecting the parcel from the seller.

      </small>

    </div>



    <span class="badge bg-warning text-dark">

      {{ $pickupOffers->count() }} Pending

    </span>

  </div>



  <div class="card-body">



    @foreach($pickupOffers as $offer)

    @php

    $offerShipment = $offer->shipment;

    $offerSellerOrder = $offerShipment?->sellerOrder;

    $offerOrder = $offerSellerOrder?->order;

    $offerSeller = $offerSellerOrder?->seller;



    $pickupAddress = $offerShipment?->pickup_address;



    if (is_string($pickupAddress)) {

    $decodedPickup = json_decode($pickupAddress, true);



    if (is_array($decodedPickup)) {

    $pickupAddress = $decodedPickup;

    }

    }



    if (is_array($pickupAddress)) {

    $pickupAddress = $pickupAddress['address']

    ?? implode(', ', array_filter([

    $pickupAddress['address_line1'] ?? null,

    $pickupAddress['address_line2'] ?? null,

    $pickupAddress['city'] ?? null,

    $pickupAddress['province'] ?? null,

    ]));

    }

    @endphp



    <div class="border rounded-3 p-3 mb-3 pickup-offer-card" data-expires-at="{{ $offer->expires_at?->toIso8601String() }}">



      <div class="row g-3 align-items-center">



        <div class="col-lg-8">



          <div class="d-flex align-items-center gap-2 flex-wrap mb-2">

            <strong class="text-dark">

              {{ $offerShipment?->tracking_number ?? 'Unknown Shipment' }}

            </strong>



            <span class="badge bg-warning text-dark">

              Awaiting Response

            </span>

          </div>



          <div class="small text-muted mb-1">

            <i class="bi bi-receipt me-1"></i>

            Order:

            <strong>{{ $offerOrder?->order_number ?? 'N/A' }}</strong>

          </div>



          <div class="small text-muted mb-1">

            <i class="bi bi-shop me-1"></i>

            Seller:

            <strong>{{ $offerSeller?->name ?? 'N/A' }}</strong>

          </div>



          <div class="small text-muted mb-1">

            <i class="bi bi-geo-alt me-1"></i>

            Pickup Address:

            {{ $pickupAddress ?: 'Not available' }}

          </div>



          <div class="small text-danger mt-2">

            <i class="bi bi-clock-history me-1"></i>

            Expires:

            {{ $offer->expires_at?->format('M d, Y h:i A') ?? 'N/A' }}

          </div>

          <div class="small fw-semibold text-danger mt-1 pickup-offer-countdown">
            Calculating time remaining...
          </div>



        </div>



        <div class="col-lg-4">



          <div class="d-flex flex-column gap-2">



            <form method="POST"

              action="{{ route('rider.pickup-offers.accept', $offer->id) }}">

              @csrf



              <button type="submit"

                class="btn btn-success w-100">

                <i class="bi bi-check-circle me-1"></i>

                Accept Pickup

              </button>

            </form>



            <form method="POST"

              action="{{ route('rider.pickup-offers.decline', $offer->id) }}"

              onsubmit="return confirm('Decline this pickup request?')">

              @csrf



              <button type="submit"

                class="btn btn-outline-danger w-100">

                <i class="bi bi-x-circle me-1"></i>

                Decline

              </button>

            </form>



          </div>



        </div>



      </div>



    </div>

    @endforeach



  </div>

</div>

@endif



{{-- Existing pickup table --}}

<div class="table-container">

  <div class="table-responsive">



    <table class="table table-hover align-middle">



      <thead class="table-light">

        <tr>

          <th>Order #</th>

          <th>Shipment</th>

          <th>Seller</th>

          <th>Customer</th>

          <th>Address</th>

          <th>Status</th>

          <th>Action</th>

        </tr>

      </thead>



      <tbody>



        @forelse($pickups as $shipment)



        @php

        $sellerOrder = $shipment->sellerOrder;

        $order = $sellerOrder?->order;

        $seller = $sellerOrder?->seller;



        $shippingData = $order?->shipping_address;



        if (is_string($shippingData)) {

        $decoded = json_decode($shippingData, true);



        $shippingData = is_array($decoded)

        ? $decoded

        : $shippingData;

        }



        if (is_array($shippingData)) {

        $displayAddress = $shippingData['address']

        ?? implode(', ', array_filter([

        $shippingData['address_line1'] ?? null,

        $shippingData['address_line2'] ?? null,

        $shippingData['city'] ?? null,

        $shippingData['province'] ?? null,

        ]));

        } else {

        $displayAddress = $shippingData ?: 'N/A';

        }



        $isSellerPickup = request('status') !== 'sorting_center';



        $isHubPickup = request('status') === 'sorting_center'

        && $shipment->sorting_status === 'staged'

        && (bool) $shipment->received_by_sorting_center;

        @endphp



        <tr>



          <td>{{ $order?->order_number ?? 'N/A' }}</td>



          <td>

            <strong>{{ $shipment->tracking_number }}</strong>

          </td>



          <td>{{ $seller?->name ?? 'N/A' }}</td>



          <td>{{ $order?->user?->name ?? 'N/A' }}</td>



          <td>{{ Str::limit($displayAddress, 45) }}</td>



          <td>

            @switch($shipment->status)



            @case('assigned')

            <span class="badge bg-warning text-dark">

              Assigned

            </span>

            @break



            @case('in_transit')

            <span class="badge bg-primary">

              To Sorting Center

            </span>

            @break



            @case('staged')

            <span class="badge bg-info">

              Staged at Hub

            </span>

            @break



            @case('at_sorting_center')

            <span class="badge bg-info">

              At Sorting Center

            </span>

            @break



            @default

            <span class="badge bg-secondary">

              {{ ucfirst(str_replace('_', ' ', $shipment->status)) }}

            </span>



            @endswitch

          </td>



          <td>



            @if($isSellerPickup

            && $shipment->status === 'assigned'

            && !$shipment->received_by_sorting_center)



            <button type="button"

              class="btn btn-sm btn-primary"

              onclick="openScanModal(

                                            {{ $shipment->id }},

                                            null,

                                            @js($order?->order_number)

                                        )">

              <i class="bi bi-qr-code-scan me-1"></i>

              Scan QR

            </button>



            @elseif($isSellerPickup

            && $shipment->status === 'in_transit'

            && !$shipment->received_by_sorting_center)



            <form method="POST"

              action="{{ route('rider.pickups.deliver-to-sorting-center', $shipment) }}"

              onsubmit="return confirm('Confirm delivery to sorting center?')">

              @csrf



              <textarea name="notes"

                class="form-control form-control-sm mb-2"

                placeholder="Optional notes"></textarea>



              <button type="submit"

                class="btn btn-sm btn-warning">

                Deliver to Sorting Center

              </button>

            </form>



            @elseif($isHubPickup)



            <button type="button"
              class="btn btn-sm btn-success"
              onclick="openScanModal(
    {{ $shipment->id }},
    null,
    @js($order?->order_number),
    'hub'
  )">
              <i class="bi bi-qr-code-scan me-1"></i>
              Scan Hub QR
            </button>



            @else

            <span class="text-muted">

              No action available

            </span>

            @endif



          </td>



        </tr>



        @empty



        <tr>

          <td colspan="7"

            class="text-center text-muted py-4">

            No pickups available.

          </td>

        </tr>



        @endforelse



      </tbody>



    </table>



  </div>



  <div class="mt-3">

    {{ $pickups->links('pagination::bootstrap-5') }}

  </div>



</div>



{{-- Existing QR Scanner Modal --}}

<div class="modal fade" id="scanModal" tabindex="-1">



  <div class="modal-dialog modal-dialog-centered">



    <div class="modal-content">



      <div class="modal-header bg-primary text-white">



        <h5 class="modal-title">

          <i class="bi bi-qr-code-scan"></i>

          Scan QR Code / Enter Token

        </h5>



        <button type="button"

          class="btn-close btn-close-white"

          data-bs-dismiss="modal"></button>



      </div>



      <form method="POST" id="scanForm">

        @csrf



        <div class="modal-body text-center">



          <p class="text-muted">

            Scan the seller's QR code using your camera,

            or manually enter the tracking token shown on the parcel label.

          </p>



          <div class="mb-3">

            <label class="form-label fw-bold">

              Order #

            </label>



            <input type="text"

              id="scanOrderNumber"

              class="form-control"

              readonly>

          </div>



          <div class="mb-3 text-start" id="qrCameraControls">
            <div class="d-flex gap-2 flex-wrap mb-2">
              <button type="button" class="btn btn-outline-primary" id="startQrCamera">
                <i class="bi bi-camera"></i> Start Camera
              </button>
              <button type="button" class="btn btn-outline-secondary" id="stopQrCamera" disabled>
                Stop Camera
              </button>
            </div>
            <div
              id="qrCameraReader"
              class="w-100 rounded border d-none mb-2"
              style="max-width: 480px; overflow: hidden;"></div>
            <div id="qrCameraMessage" class="small text-muted" role="status" aria-live="polite">
              Camera scanning is optional. You can paste the QR code below.
            </div>
          </div>

          <div class="mb-3">

            <label for="qr_token" class="form-label fw-bold">

              QR Token

            </label>



            <input type="text"

              name="qr_token"

              id="qr_token"

              class="form-control form-control-lg text-center"

              required

              placeholder="Scan or type token"

              autocomplete="off"

              style="letter-spacing: 2px; font-family: monospace;">

          </div>



        </div>



        <div class="modal-footer">



          <button type="button"

            class="btn btn-secondary"

            data-bs-dismiss="modal">

            Cancel

          </button>



          <button type="submit"

            class="btn btn-primary">

            <i class="bi bi-check-circle"></i>

            Verify & Pick Up

          </button>



        </div>



      </form>



    </div>



  </div>



</div>



@endsection



@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cards = [...document.querySelectorAll('.pickup-offer-card')];

    if (!cards.length) {
        return;
    }

    let reloadScheduled = false;

    function updateOfferTimers() {
        const now = Date.now();

        cards.forEach((card) => {
            const expiresAt = card.dataset.expiresAt;
            const output = card.querySelector('.pickup-offer-countdown');

            if (!expiresAt || !output) {
                return;
            }

            const remaining = new Date(expiresAt).getTime() - now;

            if (remaining <= 0) {
                output.textContent = 'Offer expired — finding another rider...';

                card.querySelectorAll('button').forEach((button) => {
                    button.disabled = true;
                });

                if (!reloadScheduled) {
                    reloadScheduled = true;
                    setTimeout(() => window.location.reload(), 1200);
                }

                return;
            }

            const totalSeconds = Math.ceil(remaining / 1000);
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;

            output.textContent =
                `Time remaining: ${minutes}:${String(seconds).padStart(2, '0')}`;
        });
    }

    updateOfferTimers();
    setInterval(updateOfferTimers, 1000);
});
</script>

@vite('resources/js/rider/pickups.js')
@endpush