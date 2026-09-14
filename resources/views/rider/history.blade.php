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
        <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>Delivered</option>
        <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
      </select>
    </div>
    <div class="col-md-3">
      <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}" placeholder="From">
    </div>
    <div class="col-md-3">
      <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}" placeholder="To">
    </div>
    <div class="col-md-3">
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
              @if($distance)
                {{ number_format($distance, 1) }} km
              @else
                N/A
              @endif
            </td>
            <td>
              <span class="badge bg-{{ $order->delivery_status == 'delivered' ? 'success' : 'danger' }}">
                {{ ucfirst($order->delivery_status) }}
              </span>
            </td>
            <td>{{ $order->delivered_at ? $order->delivered_at->format('M d, Y H:i') : 'N/A' }}</td>
            <td>
              <a href="{{ route('rider.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-muted py-4">No history found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">
    {{ $history->appends(request()->query())->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection


