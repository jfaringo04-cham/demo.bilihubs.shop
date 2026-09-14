@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Shipment Details</h1>
  <a href="{{ route('admin.shipments.index') }}" class="btn btn-secondary btn-sm">Back to List</a>
</div>

<div class="row g-4">
  <div class="col-md-8">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Shipment Information</h5>
        <span class="badge bg-{{ $shipment->statusBadgeClass() }}">
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
            <label class="form-label text-muted">Logistics Company</label>
            <p class="fw-bold">{{ $shipment->logistic->company_name ?? 'N/A' }}</p>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted">Rider</label>
            <p class="fw-bold">{{ $shipment->rider->name ?? 'Unassigned' }}</p>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted">Courier</label>
            <p class="fw-bold">{{ $shipment->courier ?? 'N/A' }}</p>
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
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0">Update Status</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.shipments.status', $shipment) }}">
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
        <form method="POST" action="{{ route('admin.shipments.assignRider', $shipment) }}">
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
          @php
            $steps = [
              ['label' => 'Created', 'timestamp' => $shipment->created_at, 'class' => 'primary'],
            ];

            if ($shipment->status === 'assigned' || $shipment->status === 'picked_up' || $shipment->status === 'in_transit' || $shipment->status === 'delivered') {
              $steps[] = ['label' => 'Assigned', 'timestamp' => $shipment->created_at, 'class' => 'info'];
            }

            if ($shipment->status === 'picked_up' || $shipment->status === 'in_transit' || $shipment->status === 'delivered') {
              $steps[] = ['label' => 'Picked Up', 'timestamp' => $shipment->picked_up_at, 'class' => 'warning'];
            }

            if ($shipment->status === 'in_transit' || $shipment->status === 'delivered') {
              $steps[] = ['label' => 'In Transit', 'timestamp' => null, 'class' => 'primary'];
            }

            if ($shipment->status === 'delivered') {
              $steps[] = ['label' => 'Out for Delivery', 'timestamp' => null, 'class' => 'info'];
            }

            if ($shipment->status === 'delivered') {
              $steps[] = ['label' => 'Delivered', 'timestamp' => $shipment->delivered_at, 'class' => 'success'];
            }

            if ($shipment->status === 'cancelled') {
              $steps[] = ['label' => 'Cancelled', 'timestamp' => null, 'class' => 'danger'];
            }

            if ($shipment->status === 'delayed') {
              $steps[] = ['label' => 'Delayed', 'timestamp' => null, 'class' => 'danger'];
            }
          @endphp

          @foreach($steps as $step)
            <div class="timeline-item">
              <div class="timeline-marker bg-{{ $step['class'] }}"></div>
              <div class="timeline-content">
                <h6 class="mb-0">{{ $step['label'] }}</h6>
                @if($step['timestamp'])
                  <small class="text-muted">{{ $step['timestamp']->format('M d, Y h:i A') }}</small>
                @else
                  <small class="text-muted">Pending</small>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
