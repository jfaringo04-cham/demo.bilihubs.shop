@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h2>My Orders</h2>

  @if($orders->count() > 0)
    <div class="table-responsive">
      <table class="table">
        <thead class="table-light">
          <tr>
            <th>Order #</th>
            <th>Date</th>
            <th>Status</th>
            <th>Total</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach($orders as $order)
            <tr>
              <td>{{ $order->order_number }}</td>
              <td>{{ $order->ordered_at->format('M d, Y') }}</td>
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
              </td>
              <td>&#8369;{{ number_format($order->total, 2) }}</td>
              <td><a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
      <div class="mt-4">
        {{ $orders->links('pagination::bootstrap-5') }}
      </div>
  @else
    <div class="text-center py-5">
      <i class="bi bi-receipt display-1 text-muted"></i>
      <p class="text-muted mt-3">No orders yet.</p>
      <a href="{{ route('products.index') }}" class="btn btn-bili-hub">Start Shopping</a>
    </div>
  @endif
</div>
@endsection


