@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
  <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">Shipments</h1>
    <p class="text-muted mb-0">Track and manage all deliveries</p>
  </div>
  <a href="{{ route('logistic.shipments.create') }}" class="btn btn-primary btn-sm rounded-xl">
    <i class="bi bi-plus-circle me-1"></i> New Shipment
  </a>
</div>

<div class="card border-0 shadow-sm rounded-2 mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('logistic.shipments') }}" class="row g-3">
      <div class="col-md-3">
        <label for="status" class="form-label fw-medium">Status</label>
        <select name="status" id="status" class="form-select rounded-xl">
          <option value="">All Statuses</option>
          <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
          <option value="assigned" {{ request('status') == 'assigned' ? 'selected' : '' }}>Assigned</option>
          <option value="picked_up" {{ request('status') == 'picked_up' ? 'selected' : '' }}>Picked Up</option>
          <option value="in_transit" {{ request('status') == 'in_transit' ? 'selected' : '' }}>In Transit</option>
          <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>Delivered</option>
          <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
          <option value="delayed" {{ request('status') == 'delayed' ? 'selected' : '' }}>Delayed</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100 rounded-xl">Filter</button>
      </div>
      <div class="col-md-2">
        <a href="{{ route('logistic.shipments') }}" class="btn btn-secondary w-100 rounded-xl">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Tracking #</th>
          <th class="border-0">Rider</th>
          <th class="border-0">Courier</th>
          <th class="border-0">Pickup Address</th>
          <th class="border-0">Delivery Address</th>
          <th class="border-0">Status</th>
          <th class="border-0">Created</th>
          <th class="border-0"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($shipments as $shipment)
          <tr>
            <td><code>{{ $shipment->tracking_number }}</code></td>
            <td class="text-muted">{{ $shipment->rider->name ?? 'Unassigned' }}</td>
            <td class="text-muted">{{ $shipment->courier ?? 'N/A' }}</td>
            <td class="text-muted">{{ Str::limit($shipment->pickup_address, 30) }}</td>
            <td class="text-muted">{{ Str::limit($shipment->delivery_address, 30) }}</td>
            <td>
              @if($shipment->status == 'delivered')
                <span class="badge bg-success">Delivered</span>
              @elseif($shipment->status == 'in_transit')
                <span class="badge bg-primary">In Transit</span>
              @elseif($shipment->status == 'picked_up')
                <span class="badge bg-info">Picked Up</span>
              @elseif($shipment->status == 'assigned')
                <span class="badge bg-warning">Assigned</span>
              @elseif($shipment->status == 'pending')
                <span class="badge bg-secondary">Pending</span>
              @elseif($shipment->status == 'cancelled')
                <span class="badge bg-danger">Cancelled</span>
              @elseif($shipment->status == 'delayed')
                <span class="badge bg-danger">Delayed</span>
              @endif
            </td>
            <td class="text-muted">{{ $shipment->created_at->format('M d, Y') }}</td>
            <td>
              <a href="{{ route('logistic.shipments.show', $shipment) }}" class="btn btn-primary btn-sm rounded-xl me-1">View</a>
              <a href="{{ route('logistic.shipments.track', $shipment) }}" class="btn btn-secondary btn-sm rounded-xl" target="_blank">Track</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">No shipments found. Create your first shipment!</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 border-top">{{ $shipments->links('pagination::bootstrap-5') }}</div>
</div>
</div>
@endsection
