@extends('rider.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Delivery Notifications</h1>

  <div class="d-flex gap-2">
    <a href="{{ route('rider.pickups') }}" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-box-seam"></i> View Pickups
    </a>

    <a href="{{ route('rider.deliveries') }}" class="btn btn-sm btn-outline-secondary">
      Refresh
    </a>
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
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        @forelse($deliveries as $order)
          @php
            /*
            |--------------------------------------------------------------------------
            | Distance
            |--------------------------------------------------------------------------
            */
            $distance = null;

            if (
                $order->customer_latitude &&
                $order->customer_longitude &&
                Auth::user()->latitude &&
                Auth::user()->longitude
            ) {
                $distance = $order->calculateDistance(
                    Auth::user()->latitude,
                    Auth::user()->longitude,
                    $order->customer_latitude,
                    $order->customer_longitude
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Delivery Address
            |--------------------------------------------------------------------------
            */
            $shippingData = json_decode($order->shipping_address, true);

            $displayAddress = is_array($shippingData)
                ? ($shippingData['address'] ?? $order->shipping_address)
                : $order->shipping_address;

            $contactNumber = is_array($shippingData)
                ? ($shippingData['phone'] ?? ($order->user->phone ?? 'N/A'))
                : ($order->user->phone ?? 'N/A');
          @endphp

          <tr>
            <td>
              {{ $order->order_number }}
            </td>

            <td>
              {{ $order->user->name ?? 'N/A' }}
            </td>

            <td>
              <a
                href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($displayAddress) }}"
                target="_blank"
                class="text-decoration-none"
              >
                {{ Str::limit($displayAddress, 40) }}
              </a>
            </td>

            <td>
              {{ $contactNumber }}
            </td>

            <td>
              <span class="badge bg-info">
                {{ $order->delivery_zone ?? 'N/A' }}
              </span>
            </td>

            <td>
              @if($order->ready_for_pickup)
                <span class="badge bg-success">Ready</span>
              @else
                <span class="badge bg-secondary">Pending</span>
              @endif
            </td>

            <td>
              @if($distance !== null)
                {{ number_format($distance, 1) }} km
              @else
                N/A
              @endif
            </td>

            <td>
              @if($order->delivery_status === 'picked_up_from_sorting_center')
                <span class="badge bg-primary">
                  Picked Up
                </span>

              @elseif($order->delivery_status === 'out_for_delivery')
                <span class="badge bg-warning text-dark">
                  Out for Delivery
                </span>

              @elseif($order->delivery_status === 'ready_for_delivery_pickup')
                <span class="badge bg-info">
                  Ready for Delivery
                </span>

              @elseif($order->delivery_status === 'assigned_to_rider')
                <span class="badge bg-secondary">
                  Assigned
                </span>

              @else
                <span class="badge bg-secondary">
                  {{ ucfirst(str_replace('_', ' ', $order->delivery_status)) }}
                </span>
              @endif
            </td>

            <td>
              @if(
                  $order->rider_id === Auth::id() &&
                  in_array($order->delivery_status, [
                      'assigned_to_rider',
                      'ready_for_delivery_pickup',
                      'picked_up_from_sorting_center',
                      'out_for_delivery'
                  ])
              )
                <a
                  href="{{ route('rider.orders.show', $order) }}"
                  class="btn btn-sm btn-primary"
                >
                  <i class="bi bi-eye"></i> View
                </a>

              @elseif(!$order->rider_id)
                <form
                  method="POST"
                  action="{{ route('rider.deliveries.accept', $order) }}"
                  class="d-inline"
                >
                  @csrf

                  <button type="submit" class="btn btn-sm btn-success">
                    Accept
                  </button>
                </form>

              @else
                <span class="text-muted small">
                  Assigned
                </span>
              @endif
            </td>
          </tr>

        @empty
          <tr>
            <td colspan="9" class="text-center text-muted py-4">
              No available deliveries at the moment.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $deliveries->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection