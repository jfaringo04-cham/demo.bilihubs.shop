@extends('rider.layout')

@section('content')
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Delivery History</h1>
  <a href="{{ route('rider.truck-trip-history') }}" class="btn btn-outline-primary">
    Truck Trip History
  </a>
</div>

<div class="table-container">
  <form method="GET" action="{{ route('rider.history') }}" class="row g-3 mb-3">
    <div class="col-md-3">
      <select name="status" class="form-select">
        <option value="">All Statuses</option>
        <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>
          Delivered
        </option>
        <option value="delivery_failed" {{ request('status') === 'delivery_failed' ? 'selected' : '' }}>
          Failed
        </option>
      </select>
    </div>

    <div class="col-md-3">
      <input type="date" name="from_date" class="form-control"
        value="{{ request('from_date') }}">
    </div>

    <div class="col-md-3">
      <input type="date" name="to_date" class="form-control"
        value="{{ request('to_date') }}">
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
          <th>Tracking #</th>
          <th>Order #</th>
          <th>Customer</th>
          <th>Address</th>
          <th>Contact</th>
          <th>Zone</th>
          <th>Status</th>
          <th>Delivered / Updated</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        @forelse($history as $shipment)
        @php
        $order = $shipment->sellerOrder->order;

        $shippingData = is_array($order->shipping_address)
        ? $order->shipping_address
        : json_decode($order->shipping_address ?? '', true);

        $displayAddress = is_array($shippingData)
        ? ($shippingData['address']
        ?? implode(', ', array_filter([
        $shippingData['address_line1'] ?? null,
        $shippingData['address_line2'] ?? null,
        $shippingData['city'] ?? null,
        $shippingData['province'] ?? null,
        ])))
        : $order->shipping_address;
        @endphp

        <tr>
          <td>{{ $shipment->tracking_number ?? 'N/A' }}</td>
          <td>{{ $order->order_number }}</td>
          <td>{{ $order->user?->name ?? 'N/A' }}</td>
          <td>{{ Str::limit($displayAddress ?: 'N/A', 40) }}</td>
          <td>{{ $order->user?->phone ?? 'N/A' }}</td>

          <td>
            <span class="badge bg-info">
              {{ $shipment->delivery_zone ?? $order->delivery_zone ?? 'N/A' }}
            </span>
          </td>

          <td>
            @if($shipment->status === 'delivered')
            <span class="badge bg-success">Delivered</span>
            @else
            <span class="badge bg-danger">Delivery Failed</span>
            @endif
          </td>

          <td>
            {{ ($shipment->delivered_at ?? $shipment->updated_at)?->format('M d, Y H:i') ?? 'N/A' }}
          </td>

          <td>
            <a href="{{ route('rider.shipments.show', $shipment) }}"
              class="btn btn-sm btn-outline-primary">
              View
            </a>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="9" class="text-center text-muted py-4">
            No delivery history found.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $history->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection