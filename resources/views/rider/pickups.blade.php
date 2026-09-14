@extends('rider.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Available Pickups</h1>
  <div class="d-flex gap-2">
    <a href="{{ route('rider.deliveries') }}" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-bell"></i> Delivery Notifications
    </a>
    <a href="{{ route('rider.pickups', ['status' => 'sorting_center']) }}" class="btn btn-sm btn-outline-secondary {{ request('status') == 'sorting_center' ? 'active' : '' }}">
      <i class="bi bi-sort-numeric-down"></i> From Sorting Center
    </a>
    <a href="{{ route('rider.pickups') }}" class="btn btn-sm btn-outline-secondary {{ request('status') != 'sorting_center' ? 'active' : '' }}">
      <i class="bi bi-truck"></i> From Sellers
    </a>
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>Address</th>
          <th>Contact</th>
          <th>Zone</th>
          <th>Ready</th>
          <th>Distance</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($pickups as $order)
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
          <tr>
            <td>{{ $order->order_number }}</td>
            <td>{{ $order->user->name ?? 'N/A' }}</td>
            <td>{{ Str::limit($order->shipping_address, 40) }}</td>
            <td>{{ $order->user->phone ?? 'N/A' }}</td>
            <td>
              <span class="badge bg-info">{{ $order->delivery_zone ?? 'N/A' }}</span>
            </td>
            <td>
              @if($order->ready_for_pickup)
                <span class="badge bg-success">Ready</span>
              @else
                <span class="badge bg-secondary">Pending</span>
              @endif
            </td>
            <td>
              @if($distance)
                {{ number_format($distance, 1) }} km
              @else
                N/A
              @endif
            </td>
            <td>
              <span class="badge bg-{{ $order->delivery_status == 'picked_up_from_sorting_center' ? 'info' : ($order->delivery_status == 'out_for_delivery' ? 'primary' : ($order->delivery_status == 'delivered_to_sorting_center' ? 'info' : 'warning')) }}">
                {{ $order->delivery_status == 'picked_up_from_sorting_center' ? 'Ready for Delivery' : ucfirst(str_replace('_', ' ', $order->delivery_status)) }}
              </span>
            </td>
            <td>
              @if($order->delivery_status == 'picked_up_from_sorting_center')
                <form method="POST" action="{{ route('rider.pickups.pickup-from-sorting-center', $order) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm pickup from sorting center?')">
                    <i class="bi bi-box-seam"></i> Pick for Delivery
                  </button>
                </form>
              @elseif(in_array($order->delivery_status, ['assigned_to_rider', 'in_transit']))
                @if($order->delivery_status == 'in_transit')
                  <form method="POST" action="{{ route('rider.pickups.deliver-to-sorting-center', $order) }}" class="d-inline" onsubmit="return confirm('Confirm delivery to sorting center?')">
                    @csrf
                    <textarea name="notes" class="form-control form-control-sm mt-1" placeholder="Optional notes for sorting center"></textarea>
                    <button type="submit" class="btn btn-sm btn-warning mt-1">Deliver to Sorting Center</button>
                  </form>
                @else
                  <button type="button" class="btn btn-sm btn-primary" onclick="openScanModal({{ $order->id }}, '{{ $order->shipment->qr_token ?? '' }}', '{{ $order->order_number }}')">
                    <i class="bi bi-qr-code-scan"></i> Scan QR
                  </button>

                  <form method="POST" action="{{ route('rider.pickups.confirm', $order) }}" class="d-inline mt-1">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary" onclick="return confirm('Confirm pickup without scanning? Use Scan QR for proper tracking.')">
                      Manual Confirm
                    </button>
                  </form>
                @endif
              @endif
            </td>
          </tr>
         @empty
          <tr><td colspan="9" class="text-center text-muted py-4">No pickups available.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">
    {{ $pickups->links('pagination::bootstrap-5') }}
  </div>
</div>

<div class="modal fade" id="scanModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-qr-code-scan"></i> Scan QR Code / Enter Token</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="scanForm">
        @csrf
        <div class="modal-body text-center">
          <p class="text-muted">Scan the seller's QR code using your camera, or manually enter the tracking token shown on the parcel label.</p>
          <div class="mb-3">
            <label class="form-label fw-bold">Order #</label>
            <input type="text" id="scanOrderNumber" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label for="qr_token" class="form-label fw-bold">QR Token / Tracking Number</label>
            <input type="text" name="qr_token" id="qr_token" class="form-control form-control-lg text-center" required placeholder="Scan or type token" autocomplete="off" style="letter-spacing: 2px; font-family: monospace;">
            <small class="text-muted">Expected token: <code id="expectedToken"></code></small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Verify & Pick Up</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
function openScanModal(orderId, expectedToken, orderNumber) {
  const form = document.getElementById('scanForm');
  form.action = '/rider/shipments/' + orderId + '/scan';
  document.getElementById('scanOrderNumber').value = orderNumber;
  document.getElementById('expectedToken').textContent = expectedToken;
  document.getElementById('qr_token').value = '';
  const modal = new bootstrap.Modal(document.getElementById('scanModal'));
  modal.show();
  setTimeout(() => document.getElementById('qr_token').focus(), 500);
}
</script>
@endpush


