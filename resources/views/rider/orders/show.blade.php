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
        <div class="mb-3">
          <strong>Delivery Address:</strong><br>
          {{ $order->shipping_address }}
          <div class="mt-2">
            <a href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($order->shipping_address) }}" target="_blank" class="btn btn-sm btn-outline-primary me-1">
              <i class="bi bi-google"></i> Google Maps
            </a>
            <a href="https://waze.com/ul?q={{ urlencode($order->shipping_address) }}" target="_blank" class="btn btn-sm btn-outline-success">
              <i class="bi bi-bicycle"></i> Waze
            </a>
          </div>
        </div>
        <div class="mb-3">
          <strong>Delivery Zone:</strong> 
          <span class="badge bg-info">{{ $order->delivery_zone ?? 'N/A' }}</span>
        </div>
        @if($order->ready_for_pickup)
          <div class="mb-3">
            <span class="badge bg-success">Ready for Pickup</span>
          </div>
        @endif
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
                <td>&#8369;{{ number_format($item->price, 2) }}</td>
                <td>&#8369;{{ number_format($item->subtotal, 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="table-container">
      <h5 class="mb-3">Update Delivery Status</h5>
      <form action="{{ route('rider.orders.updateStatus', $order) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="delivery_status" class="form-label">Status</label>
            <select name="delivery_status" id="delivery_status" class="form-select" required>
              <option value="{{ $order->delivery_status }}" selected>{{ ucfirst(str_replace('_', ' ', $order->delivery_status)) }}</option>
              @if($order->delivery_status == 'picked_up_from_sorting_center')
                <option value="on_the_way">On the Way</option>
                <option value="delivered">Delivered</option>
                <option value="failed">Failed Attempt</option>
              @elseif($order->delivery_status == 'on_the_way')
                <option value="delivered">Delivered</option>
                <option value="failed">Failed Attempt</option>
              @elseif($order->delivery_status == 'assigned')
                <option value="on_the_way">On the Way</option>
                <option value="failed">Failed Attempt</option>
              @elseif($order->delivery_status == 'failed')
                <option value="on_the_way">Retry Delivery</option>
              @endif
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label for="delivery_notes" class="form-label">Notes</label>
            <textarea name="delivery_notes" id="delivery_notes" class="form-control" rows="1" placeholder="Add notes...">{{ $order->delivery_notes }}</textarea>
          </div>
        </div>
        
        <div id="delivery-proof-fields" style="display: none;">
          <hr>
          <h6 class="mb-3">Proof of Delivery (Required for Delivered)</h6>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="proof_of_delivery" class="form-label">Delivery Photo <span class="text-danger">*</span></label>
              <input type="file" name="proof_of_delivery" id="proof_of_delivery" class="form-control" accept="image/*">
              <small class="text-muted">Photo of delivered package (JPG/PNG, max 5MB)</small>
            </div>
            <div class="col-md-6 mb-3">
              <label for="delivered_to" class="form-label">Delivered To</label>
              <input type="text" name="delivered_to" id="delivered_to" class="form-control" placeholder="Name of person who received">
              <small class="text-muted">Optional: Name of recipient</small>
            </div>
          </div>
          <div class="mb-3">
            <label for="delivery_signature" class="form-label">Signature / Notes</label>
            <textarea name="delivery_signature" id="delivery_signature" class="form-control" rows="2" placeholder="Digital signature or additional notes..."></textarea>
          </div>
        </div>
        
        <button type="submit" class="btn btn-bili-hub">Update Status</button>
      </form>
    </div>
  </div>

  <div class="col-md-4">
    <div class="table-container mb-4">
      <h5 class="mb-3">Delivery Route</h5>
      <div id="rider-map" style="height: 300px; border-radius: 10px;"></div>
    </div>

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
        <strong>Total Amount:</strong> &#8369;{{ number_format($order->total, 2) }}
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
            <input type="number" name="amount_collected" class="form-control" step="0.01" value="{{ $order->total }}" required>
          </div>
          <button type="submit" class="btn btn-bili-hub w-100">Collect Payment</button>
        </form>
      @endif

      @if($order->payment_status == 'paid')
        <div class="alert alert-success mt-2">
          <strong>Collected:</strong> &#8369;{{ number_format($order->amount_collected, 2) }}<br>
          <small>{{ $order->collected_at->format('M d, Y H:i') }}</small>
          @if($order->collector)
            <br><small>by {{ $order->collector->name }}</small>
          @endif
        </div>
      @endif
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    @if(config('services.googlemaps.key'))
      var map = new google.maps.Map(document.getElementById('rider-map'), {
        zoom: 14,
        center: { lat: 14.5995, lng: 120.9842 }
      });

      var geocoder = new google.maps.Geocoder();
      geocoder.geocode({ address: '{{ $order->shipping_address }}' }, function(results, status) {
        if (status == 'OK') {
          map.setCenter(results[0].geometry.location);
          new google.maps.Marker({
            map: map,
            position: results[0].geometry.location,
            title: '{{ $order->user->name ?? 'Customer' }}'
          });
        }
      });
    @else
      var map = L.map('rider-map').setView([14.5995, 120.9842], 14);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
      }).addTo(map);

      L.marker([14.5995, 120.9842]).addTo(map)
        .bindPopup('{{ $order->user->name ?? 'Customer' }}<br>{{ $order->shipping_address }}');
    @endif

    // Show/hide proof of delivery fields when "Delivered" is selected
    const statusSelect = document.getElementById('delivery_status');
    const proofFields = document.getElementById('delivery-proof-fields');
    const photoInput = document.getElementById('proof_of_delivery');

    function toggleProofFields() {
      if (statusSelect.value === 'delivered') {
        proofFields.style.display = 'block';
        photoInput.required = true;
      } else {
        proofFields.style.display = 'none';
        photoInput.required = false;
      }
    }

    statusSelect.addEventListener('change', toggleProofFields);
    toggleProofFields(); // Initial check
  });
</script>
@endpush


