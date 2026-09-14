@extends('rider.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Delivery Addresses</h1>
</div>

<div class="table-container">
  <form method="GET" action="{{ route('rider.addresses') }}" class="row g-3 mb-3">
    <div class="col-md-4">
      <select name="status" class="form-select">
        <option value="">All Statuses</option>
        <option value="assigned" {{ request('status') == 'assigned' ? 'selected' : '' }}>Assigned</option>
        <option value="on_the_way" {{ request('status') == 'on_the_way' ? 'selected' : '' }}>On the Way</option>
        <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>Delivered</option>
        <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-outline-secondary w-100">Filter</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>Delivery Address</th>
          <th>Contact</th>
          <th>Zone</th>
          <th>Distance</th>
          <th>Status</th>
          <th>Navigation</th>
        </tr>
      </thead>
      <tbody>
        @forelse($addresses as $order)
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
            $address = urlencode($order->shipping_address);
          @endphp
          <tr>
            <td>{{ $order->order_number }}</td>
            <td>{{ $order->user->name ?? 'N/A' }}</td>
            <td>{{ $order->shipping_address }}</td>
            <td>{{ $order->user->phone ?? 'N/A' }}</td>
            <td>
              <span class="badge bg-info">{{ $order->delivery_zone ?? 'N/A' }}</span>
            </td>
            <td>
              @if($distance)
                {{ number_format($distance, 1) }} km
              @else
                N/A
              @endif
            </td>
            <td>
              <span class="badge bg-{{ $order->delivery_status == 'on_the_way' ? 'primary' : ($order->delivery_status == 'delivered' ? 'success' : ($order->delivery_status == 'failed' ? 'danger' : 'warning')) }}">
                {{ ucfirst(str_replace('_', ' ', $order->delivery_status)) }}
              </span>
            </td>
            <td>
              <div class="d-flex gap-1">
                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $address }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Google Maps">
                  <i class="bi bi-google"></i>
                </a>
                <a href="https://waze.com/ul?q={{ $address }}" target="_blank" class="btn btn-sm btn-outline-success" title="Waze">
                  <i class="bi bi-bicycle"></i>
                </a>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">No addresses found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">
    {{ $addresses->appends(request()->query())->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection
