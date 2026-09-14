@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Notifications</h1>
  <div class="d-flex gap-2">
    <form method="POST" action="{{ route('seller.notifications.readAll') }}" class="d-inline">
      @csrf
      <button type="submit" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-check-all"></i> Mark All as Read
      </button>
    </form>
  </div>
</div>

@if($notifs->count() > 0)
  <div class="table-container mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <h5 class="mb-0"><i class="bi bi-megaphone text-primary"></i> System & Compliance Notifications</h5>
      <form method="GET" action="{{ route('seller.notifications') }}" class="d-inline">
        <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
          <option value="" {{ request('filter') == '' ? 'selected' : '' }}>All Notifications</option>
          <option value="unread" {{ request('filter') == 'unread' ? 'selected' : '' }}>Unread Only</option>
        </select>
      </form>
    </div>
    <div class="list-group">
      @foreach($notifs as $n)
        <div class="list-group-item {{ $n->is_read ? '' : 'list-group-item-warning' }}">
          <div class="d-flex w-100 justify-content-between align-items-start">
            <div class="flex-grow-1">
              <div class="d-flex align-items-center gap-2">
                <strong>{{ $n->title }}</strong>
                @if(!$n->is_read)
                  <span class="badge bg-danger">Unread</span>
                @endif
              </div>
              <p class="mb-1 mt-1">{{ $n->message }}</p>
              <small class="text-muted text-capitalize"><i class="bi bi-tag"></i> {{ str_replace('_', ' ', $n->type) }} | {{ $n->created_at->diffForHumans() }}</small>
            </div>
            <div class="d-flex flex-column gap-1 ms-2">
              @if($n->link)
                <form method="POST" action="{{ route('seller.notifications.read', $n) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right"></i> {{ $n->is_read ? 'View' : 'View & Mark Read' }}
                  </button>
                </form>
              @endif
              @if(!$n->is_read)
                <form method="POST" action="{{ route('seller.notifications.read', $n) }}" class="d-inline">
                  @csrf
                  <input type="hidden" name="redirect_back" value="1">
                  <button type="submit" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-check"></i> Mark Read
                  </button>
                </form>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endif

<div class="table-container">
  <h5 class="mb-3"><i class="bi bi-receipt text-warning"></i> Order Notifications</h5>
  <div class="row mb-3">
    <div class="col-md-4">
      <form method="GET" action="{{ route('seller.notifications') }}">
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">All Status</option>
          <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
          <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Processing</option>
          <option value="shipped" {{ request('status') == 'shipped' ? 'selected' : '' }}>Shipped</option>
          <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>Delivered</option>
          <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
        </select>
      </form>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>Items</th>
          <th>Total</th>
          <th>Status</th>
          <th>Date</th>
          <th>Action</th>
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
                <span class="badge bg-{{ $order->status == 'delivered' ? 'success' : ($order->status == 'cancelled' ? 'danger' : ($order->status == 'shipped' ? 'info' : 'warning')) }}">
                  {{ ucfirst($order->status) }}
                </span>
              </td>
              <td>{{ $order->ordered_at->format('M d, Y h:i A') }}</td>
                <td>
                  <a href="{{ route('seller.orders.show', $order) }}" class="btn btn-sm btn-outline-primary view-btn" target="_blank">
                    <i class="bi bi-eye"></i> <span class="view-text">View</span>
                  </a>
                </td>
            </tr>
          @endif
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No orders found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">
    {{ $orders->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection

