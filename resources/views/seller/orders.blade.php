@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Orders</h1>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>Items</th>
          <th>Total</th>
          <th>Status</th>
          <th>Ready</th>
          <th>Return</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($orders as $order)
          @php
            $sellerItems = $order->items->filter(function($item) {
              return $item->product->user_id === Auth::id();
            });
          @endphp
          @if($sellerItems->count() > 0)
            <tr>
              <td>{{ $order->order_number }}</td>
              <td>{{ $order->user->name ?? 'N/A' }}</td>
              <td>
                @foreach($sellerItems as $item)
                  <div>{{ $item->product_name }} x{{ $item->quantity }}@if($item->size) ({{ $item->size->name }})@endif</div>
                @endforeach
              </td>
              <td>&#8369;{{ number_format($order->total, 2) }}</td>
              <td>
                <span class="badge bg-{{ $order->statusBadgeClass() }} mb-1 d-block">
                  {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                </span>
                @php
                  $progress = 0;
                  if ($order->status == 'placed') $progress = 10;
                  elseif ($order->status == 'confirmed') $progress = 25;
                  elseif ($order->status == 'preparing') $progress = 40;
                  elseif ($order->status == 'ready_for_pickup') $progress = 60;
                  elseif (in_array($order->status, ['picked_up', 'in_transit'])) $progress = 80;
                  elseif (in_array($order->status, ['delivered', 'completed'])) $progress = 100;
                @endphp
                <div class="progress" style="height: 6px; background: #e2e8f0; border-radius: 3px; min-width: 100px;">
                  <div class="progress-bar" role="progressbar" style="width: {{ $progress }}%; background: #0ea5e9; border-radius: 3px;"></div>
                </div>
                <form action="{{ route('seller.orders.updateStatus', $order) }}" method="POST" class="d-inline">
                  @csrf
                  <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="placed" {{ $order->status == 'placed' ? 'selected' : '' }}>Placed</option>
                    <option value="confirmed" {{ $order->status == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="preparing" {{ $order->status == 'preparing' ? 'selected' : '' }}>Preparing</option>
                    <option value="ready_for_pickup" {{ $order->status == 'ready_for_pickup' ? 'selected' : '' }}>Ready for Pickup</option>
                    <option value="delivered" {{ $order->status == 'delivered' ? 'selected' : '' }}>Delivered</option>
                    <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="reschedule_requested" {{ $order->status == 'reschedule_requested' ? 'selected' : '' }}>Reschedule Requested</option>
                    <option value="rescheduled" {{ $order->status == 'rescheduled' ? 'selected' : '' }}>Rescheduled</option>
                    <option value="return_requested" {{ $order->status == 'return_requested' ? 'selected' : '' }}>Return Requested</option>
                    <option value="returned" {{ $order->status == 'returned' ? 'selected' : '' }}>Returned</option>
                  </select>
                </form>
              </td>
              <td>
                @if($order->ready_for_pickup)
                  <span class="badge bg-success">Ready</span>
                  @if($order->rider_id)
                    <br><small class="text-muted">Rider assigned</small>
                  @else
                    <br><small class="text-muted">Waiting for rider</small>
                  @endif
                @else
                  <form action="{{ route('seller.orders.ready', $order) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Mark this order as ready for pickup?')">
                      <i class="bi bi-check-circle"></i> Ready
                    </button>
                  </form>
                @endif
              </td>
              <td>
                @if($order->status == 'delivery_failed')
                  <span class="badge bg-warning">Delivery Failed</span>
                  <form action="{{ route('seller.orders.approveReschedule', $order) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">Approve Reschedule</button>
                  </form>
                  <form action="{{ route('seller.orders.rejectReschedule', $order) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-danger">Reject Reschedule</button>
                  </form>
                @elseif($order->return_status == 'requested')
                  <span class="badge bg-warning">Return Requested</span>
                  <form action="{{ route('seller.orders.approveReturn', $order) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                  </form>
                  <form action="{{ route('seller.orders.rejectReturn', $order) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                  </form>
                @elseif($order->return_status == 'approved')
                  <span class="badge bg-info">Return Approved</span>
                @elseif($order->return_status == 'rejected')
                  <span class="badge bg-secondary">Return Rejected</span>
                @else
                  <span class="badge bg-light text-dark">No Return</span>
                @endif
              </td>
              <td>
                <a href="{{ route('seller.orders.show', $order) }}" class="btn btn-sm btn-outline-primary view-btn" target="_blank"><i class="bi bi-eye"></i> <span class="view-text">View</span></a>
              </td>
            </tr>
          @endif
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">No orders found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">
    {{ $orders->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection


