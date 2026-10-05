@extends('rider.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">My Orders</h1>
</div>

<div class="table-container">
  <form method="GET" action="{{ route('rider.orders') }}" class="row g-3 mb-3">
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
            @forelse($orders as $order)
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
                $shippingData = json_decode($order->shipping_address, true);

$displayAddress = is_array($shippingData)
    ? ($shippingData['address'] ?? $order->shipping_address)
    : $order->shipping_address;
              @endphp
              <tr>
                <td>{{ $order->order_number }}</td>
                <td>{{ $order->user->name ?? 'N/A' }}</td>
                <td>
    <a href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($displayAddress) }}"
       target="_blank"
       class="text-decoration-none">
        {{ Str::limit($displayAddress, 40) }}
    </a>
</td>
                <td>{{ $order->user->phone ?? 'N/A' }}</td>
                <td>
                  <span class="badge bg-info">{{ $order->delivery_zone ?? 'N/A' }}</span>
                </td>
                <td>
                  @if($order->delivery_status === 'delivered')
    <span class="badge bg-success">Completed</span>

@elseif($order->delivery_status === 'delivery_failed')
    <span class="badge bg-danger">Failed</span>

@elseif(in_array($order->delivery_status, [
    'picked_up_from_sorting_center',
    'out_for_delivery'
]))
    <span class="badge bg-primary">In Delivery</span>

@elseif($order->ready_for_pickup)
    <span class="badge bg-warning text-dark">Ready</span>

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
                  <span class="badge bg-{{ $order->delivery_status == 'on_the_way' ? 'primary' : ($order->delivery_status == 'delivered' ? 'success' : ($order->delivery_status == 'failed' ? 'danger' : 'warning')) }}">
                    {{ ucfirst(str_replace('_', ' ', $order->delivery_status)) }}
                  </span>
                </td>
                <td>
                  <a href="{{ route('rider.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="9" class="text-center text-muted py-4">No orders found.</td></tr>
            @endforelse
          </tbody>
        </table>
  </div>
  <div class="mt-3">
    {{ $orders->appends(request()->query())->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection


