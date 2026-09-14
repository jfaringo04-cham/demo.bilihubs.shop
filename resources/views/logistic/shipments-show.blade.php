@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Shipment Details</h1>
  <a href="{{ route('logistic.shipments') }}" class="btn btn-secondary btn-sm me-2">Back to Shipments</a>
  <a href="{{ route('logistic.shipments.track', $shipment) }}" class="btn btn-outline-primary btn-sm">Track on Map</a>
</div>

<div class="row g-4">
  <div class="col-md-8">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Shipment Information</h5>
        <span class="badge bg-{{ $shipment->status == 'delivered' ? 'success' : ($shipment->status == 'in_transit' ? 'primary' : ($shipment->status == 'picked_up' ? 'info' : ($shipment->status == 'assigned' ? 'warning' : ($shipment->status == 'cancelled' ? 'danger' : ($shipment->status == 'delayed' ? 'danger' : 'secondary'))))) }}">
          {{ ucfirst(str_replace('_', ' ', $shipment->status)) }}
        </span>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label text-muted">Tracking Number</label>
            <p class="fw-bold"><code>{{ $shipment->tracking_number }}</code></p>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted">QR / Barcode</label>
            <div class="d-flex align-items-center gap-2">
              <a href="{{ route('shipments.qr.image', $shipment) }}" target="_blank">
                <img src="{{ route('shipments.qr.image', $shipment) }}" alt="QR Code" style="width: 80px; height: 80px; border: 1px solid #ddd; padding: 4px;">
              </a>
              <div>
                <a href="{{ route('shipments.qr.label', $shipment) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-printer"></i> Print Label
                </a>
                <div><code class="small">{{ $shipment->qr_token }}</code></div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted">Courier</label>
            <p class="fw-bold">{{ $shipment->courier ?? 'N/A' }}</p>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted">Rider</label>
            <p class="fw-bold">{{ $shipment->rider->name ?? 'Unassigned' }}</p>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted">Created</label>
            <p class="fw-bold">{{ $shipment->created_at->format('M d, Y h:i A') }}</p>
          </div>
          <div class="col-12">
            <label class="form-label text-muted">Pickup Address</label>
            <p class="fw-bold">{{ $shipment->pickup_address }}</p>
          </div>
          <div class="col-12">
            <label class="form-label text-muted">Delivery Address</label>
            <p class="fw-bold">{{ $shipment->delivery_address }}</p>
          </div>
          @if($shipment->notes)
            <div class="col-12">
              <label class="form-label text-muted">Notes</label>
              <p class="fw-bold">{{ $shipment->notes }}</p>
            </div>
          @endif
          @if($shipment->picked_up_at)
            <div class="col-md-6">
              <label class="form-label text-muted">Picked Up At</label>
              <p class="fw-bold">{{ $shipment->picked_up_at->format('M d, Y h:i A') }}</p>
            </div>
          @endif
          @if($shipment->delivered_at)
            <div class="col-md-6">
              <label class="form-label text-muted">Delivered At</label>
              <p class="fw-bold">{{ $shipment->delivered_at->format('M d, Y h:i A') }}</p>
            </div>
          @endif
          @if($shipment->scanned_at_seller)
            <div class="col-md-6">
              <label class="form-label text-muted">QR Scanned at Seller</label>
              <p class="fw-bold text-success"><i class="bi bi-check-circle"></i> {{ $shipment->scanned_at_seller->format('M d, Y h:i A') }}</p>
            </div>
          @endif
          @if($shipment->seller_scan_confirmed_at)
            <div class="col-md-6">
              <label class="form-label text-muted">Seller Hand-Over Confirmed</label>
              <p class="fw-bold text-success">{{ $shipment->seller_scan_confirmed_at->format('M d, Y h:i A') }}</p>
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0">Update Status</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('logistic.shipments.status', $shipment) }}">
          @csrf
          <div class="row g-3">
            <div class="col-md-8">
              <select name="status" class="form-select">
                <option value="pending" {{ $shipment->status == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="assigned" {{ $shipment->status == 'assigned' ? 'selected' : '' }}>Assigned</option>
                <option value="picked_up" {{ $shipment->status == 'picked_up' ? 'selected' : '' }}>Picked Up</option>
                <option value="in_transit" {{ $shipment->status == 'in_transit' ? 'selected' : '' }}>In Transit</option>
                <option value="delivered" {{ $shipment->status == 'delivered' ? 'selected' : '' }}>Delivered</option>
                <option value="cancelled" {{ $shipment->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                <option value="delayed" {{ $shipment->status == 'delayed' ? 'selected' : '' }}>Delayed</option>
              </select>
            </div>
            <div class="col-md-4">
              <button type="submit" class="btn btn-bili-hub w-100">Update Status</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white">
        <h5 class="mb-0">Assign Rider</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('logistic.shipments.status', $shipment) }}">
          @csrf
          <div class="mb-3">
            <label for="rider_id" class="form-label">Select Rider</label>
            <select name="rider_id" class="form-select" required>
              <option value="">Select Rider</option>
              @foreach($riders as $rider)
                <option value="{{ $rider->id }}" {{ $shipment->rider_id == $rider->id ? 'selected' : '' }}>
                  {{ $rider->name }} - {{ $rider->vehicle_type ?? 'N/A' }}
                </option>
              @endforeach
            </select>
          </div>
          <button type="submit" class="btn btn-bili-hub w-100">Assign Rider</button>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Timeline</h5>
      </div>
      <div class="card-body">
        <div class="timeline">
          @foreach($shipment->timelineSteps() as $step)
            <div class="timeline-item">
              <div class="timeline-marker bg-{{ $step['class'] }}"></div>
              <div class="timeline-content">
                <h6 class="mb-0">{{ $step['label'] }}</h6>
                @if($step['timestamp'])
                  @php
                    $ts = $step['timestamp'];
                    if (!$ts instanceof \Carbon\Carbon) {
                        $ts = \Carbon\Carbon::parse($ts);
                    }
                  @endphp
                  <small class="text-muted">{{ $ts->format('M d, Y h:i A') }}</small>
                @else
                  <small class="text-muted">Pending</small>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Chat</h5>
        <a href="{{ route('logistic.shipments.chat', $shipment) }}" class="btn btn-sm btn-outline-primary">Open Chat</a>
      </div>
      <div class="card-body">
        @php
          $latestMessages = $shipment->messages()->with('user')->latest()->take(5)->get();
        @endphp
        @forelse($latestMessages as $message)
          <div class="mb-2 {{ $message->user_id == Auth::id() ? 'text-end' : 'text-start' }}">
            <div class="d-inline-block p-2 rounded bg-light" style="max-width: 75%;">
              <div class="small fw-bold mb-1">{{ $message->user->name }}</div>
              <div>{{ Str::limit($message->message, 100) }}</div>
              <div class="small text-muted mt-1">{{ $message->created_at->diffForHumans() }}</div>
            </div>
          </div>
        @empty
          <p class="text-muted text-center py-3">No messages yet.</p>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection
