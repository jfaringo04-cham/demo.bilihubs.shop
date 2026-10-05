@extends('rider.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Delivery History</h1>
</div>

<div class="table-container">
  <form method="GET" action="{{ route('rider.history') }}" class="row g-3 mb-3">
    <div class="col-md-3">
      <select name="status" class="form-select">
        <option value="">All Statuses</option>
        <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>
          Delivered
        </option>
        <option value="delivery_failed" {{ request('status') == 'delivery_failed' ? 'selected' : '' }}>
          Failed
        </option>
      </select>
    </div>

    <div class="col-md-3">
      <input
        type="date"
        name="from_date"
        class="form-control"
        value="{{ request('from_date') }}"
        placeholder="From"
      >
    </div>

    <div class="col-md-3">
      <input
        type="date"
        name="to_date"
        class="form-control"
        value="{{ request('to_date') }}"
        placeholder="To"
      >
    </div>

    <div class="col-md-3">
      <button type="submit" class="btn btn-outline-secondary w-100">
        Filter
      </button>
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
          <th>Distance</th>
          <th>Status</th>
          <th>Delivered At</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        @forelse($history as $order)

          @php
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
             * Decode shipping address.
             * Newer orders store shipping_address as JSON.
             * Older orders may still contain a normal string address.
             */
            $shippingData = json_decode($order->shipping_address, true);

            $displayAddress = is_array($shippingData)
                ? ($shippingData['address'] ?? $order->shipping_address)
                : $order->shipping_address;
          @endphp

          <tr>
            <td>
              {{ $order->order_number }}
            </td>

            <td>
              {{ $order->user->name ?? 'N/A' }}
            </td>

            <td>
              {{ Str::limit($displayAddress, 40) }}
            </td>

            <td>
              {{ $order->user->phone ?? 'N/A' }}
            </td>

            <td>
              <span class="badge bg-info">
                {{ $order->delivery_zone ?? 'N/A' }}
              </span>
            </td>

            <td>
              @if($distance !== null)
                {{ number_format($distance, 1) }} km
              @else
                N/A
              @endif
            </td>

            <td>
              @if($order->delivery_status === 'delivered')
                <span class="badge bg-success">
                  Delivered
                </span>

              @elseif($order->delivery_status === 'delivery_failed')
                <span class="badge bg-danger">
                  Delivery Failed
                </span>

              @else
                <span class="badge bg-secondary">
                  {{ ucfirst(str_replace('_', ' ', $order->delivery_status ?? 'N/A')) }}
                </span>
              @endif
            </td>

            <td>
              {{ $order->delivered_at
                  ? $order->delivered_at->format('M d, Y H:i')
                  : 'N/A'
              }}
            </td>

            <td>
              <a
                href="{{ route('rider.orders.show', $order) }}"
                class="btn btn-sm btn-outline-primary"
              >
                View
              </a>
            </td>
          </tr>

        @empty

          <tr>
            <td colspan="9" class="text-center text-muted py-4">
              No history found.
            </td>
          </tr>

        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $history->appends(request()->query())->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection