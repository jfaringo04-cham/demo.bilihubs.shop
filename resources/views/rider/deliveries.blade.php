@extends('rider.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Delivery Notifications</h1>
  <div class="d-flex gap-2">
    <a href="{{ route('rider.pickups') }}" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-box-seam"></i> View Pickups
    </a>
    <a href="{{ route('rider.deliveries') }}" class="btn btn-sm btn-outline-secondary">Refresh</a>
  </div>
</div>

<div class="table-container">
  <h5 class="mb-3">Available Deliveries</h5>
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
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($deliveries as $order)
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
              <form method="POST" action="{{ route('rider.deliveries.accept', $order) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-success">Accept</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">No available deliveries at the moment.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">
    {{ $deliveries->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection


