@extends('layouts.app')

@section('content')
<div class="container py-4">
  <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to Orders</a>

  <div class="row">
    <div class="col-md-8">
      <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between">
          <h5 class="mb-0">Order #{{ $order->order_number }}</h5>
          <span class="badge bg-{{ $order->statusBadgeClass() }}">
            {{ ucfirst(str_replace('_', ' ', $order->status)) }}
          </span>
        </div>
        <div class="card-body">
          <p><strong>Order Date:</strong> {{ $order->ordered_at->format('M d, Y H:i') }}</p>
          <p><strong>Shipping Address:</strong> {{ $order->shipping_address }}</p>
          <p><strong>Payment Method:</strong> <span class="badge bg-{{ $order->payment_method == 'cod' ? 'warning' : 'success' }}">{{ strtoupper($order->payment_method) }}</span></p>
          <p><strong>Payment Status:</strong> <span class="badge bg-{{ $order->payment_status == 'paid' ? 'success' : 'secondary' }}">{{ ucfirst($order->payment_status) }}</span></p>
          @if($order->delivery_status)
            <p><strong>Delivery Status:</strong> <span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $order->delivery_status)) }}</span></p>
          @endif
          @if($order->ready_for_pickup)
            <p><strong>Pickup Status:</strong> <span class="badge bg-success">Ready for Pickup</span></p>
          @endif
          @if($order->notes)
            <p><strong>Notes:</strong> {{ $order->notes }}</p>
          @endif
        </div>
      </div>

      @php
        $timeline = [];
        $timeline[] = ['label' => 'Order Placed', 'timestamp' => $order->ordered_at, 'icon' => 'bi-cart-check', 'class' => 'primary'];
        if ($order->ready_for_pickup) {
          $timeline[] = ['label' => 'Seller Prepared Order', 'timestamp' => $order->updated_at, 'icon' => 'bi-box-seam', 'class' => 'info'];
        }
        if ($order->rider_id) {
          $timeline[] = ['label' => 'Rider Assigned', 'timestamp' => $order->assigned_at ?? $order->updated_at, 'icon' => 'bi-person-check', 'class' => 'primary'];
        }
        if ($order->picked_up_at) {
          $timeline[] = ['label' => 'Picked Up by Rider', 'timestamp' => $order->picked_up_at, 'icon' => 'bi-bag-check', 'class' => 'warning'];
        }
        if ($order->shipment && $order->shipment->at_sorting_center_at) {
          $timeline[] = ['label' => 'At Sorting Center', 'timestamp' => $order->shipment->at_sorting_center_at, 'icon' => 'bi-building', 'class' => 'info'];
        }
        if ($order->delivered_at) {
          $timeline[] = ['label' => 'Delivered', 'timestamp' => $order->delivered_at, 'icon' => 'bi-truck', 'class' => 'success'];
        }
        if ($order->confirmed_received_at) {
          $timeline[] = ['label' => 'Confirmed Received', 'timestamp' => $order->confirmed_received_at, 'icon' => 'bi-emoji-smile', 'class' => 'success'];
        }
        if ($order->status == 'cancelled') {
          $timeline[] = ['label' => 'Cancelled', 'timestamp' => $order->updated_at, 'icon' => 'bi-x-circle', 'class' => 'danger'];
        }
      @endphp

      <div class="card mb-4">
        <div class="card-header bg-white">
          <h5 class="mb-0">Order Tracking</h5>
        </div>
        <div class="card-body">
          @php
            $steps = [
              ['label' => 'Order Placed', 'status' => 'placed', 'icon' => 'bi-cart-check'],
              ['label' => 'Confirmed', 'status' => 'confirmed', 'icon' => 'bi-check-circle'],
              ['label' => 'Preparing', 'status' => 'preparing', 'icon' => 'bi-box-seam'],
              ['label' => 'Ready for Pickup', 'status' => 'ready_for_pickup', 'icon' => 'bi-bag-check'],
              ['label' => 'Picked Up', 'status' => 'picked_up', 'icon' => 'bi-truck'],
              ['label' => 'In Transit', 'status' => 'in_transit', 'icon' => 'bi-geo-alt'],
              ['label' => 'Delivered', 'status' => 'delivered', 'icon' => 'bi-check2-all'],
            ];

            $currentStepIndex = 0;
            foreach ($steps as $index => $step) {
              if (in_array($order->status, ['delivered', 'completed']) || $order->delivery_status == 'delivered') {
                $currentStepIndex = 6;
                break;
              }
              if ($order->status == 'in_transit' || $order->delivery_status == 'out_for_delivery') {
                $currentStepIndex = 5;
                break;
              }
              if ($order->status == 'picked_up' || ($order->shipment && $order->shipment->status == 'picked_up')) {
                $currentStepIndex = 4;
                break;
              }
              if ($order->status == 'ready_for_pickup') {
                $currentStepIndex = 3;
                break;
              }
              if ($order->status == 'preparing') {
                $currentStepIndex = 2;
                break;
              }
              if ($order->status == 'confirmed') {
                $currentStepIndex = 1;
                break;
              }
              if ($order->status == 'placed') {
                $currentStepIndex = 0;
                break;
              }
              if ($order->status == 'cancelled') {
                $currentStepIndex = -1;
                break;
              }
            }
          @endphp

          @if($currentStepIndex >= 0)
            <div class="d-flex justify-content-between align-items-center mb-2">
              @foreach($steps as $index => $step)
                @php
                  $isCompleted = $index <= $currentStepIndex;
                  $isCurrent = $index == $currentStepIndex;
                  $circleClass = $isCompleted ? 'bg-primary text-white' : 'bg-light text-muted';
                  $lineClass = $index < $currentStepIndex ? 'bg-primary' : 'bg-light';
                @endphp
                <div class="d-flex flex-column align-items-center" style="flex: 1; position: relative;">
                  @if($index < count($steps) - 1)
                    <div class="position-absolute top-50 start-50 w-100" style="height: 2px; transform: translateY(-50%); z-index: 0;">
                      <div class="w-100 h-100 {{ $lineClass }}"></div>
                    </div>
                  @endif
                  <div class="rounded-circle d-flex align-items-center justify-content-center position-relative" style="width: 40px; height: 40px; z-index: 1; background: {{ $isCompleted ? '#0ea5e9' : '#e2e8f0' }}; color: {{ $isCompleted ? '#fff' : '#64748b' }};">
                    <i class="bi {{ $step['icon'] }}"></i>
                  </div>
                  <small class="mt-2 text-center fw-medium" style="font-size: 0.75rem; color: {{ $isCompleted ? '#0f172a' : '#64748b' }};">{{ $step['label'] }}</small>
                </div>
              @endforeach
            </div>
          @endif

          @if($order->status == 'cancelled')
            <div class="alert alert-danger">
              <i class="bi bi-x-circle me-1"></i> This order has been cancelled.
            </div>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header bg-white">
          <h5 class="mb-0">Order Items</h5>
        </div>
        <div class="card-body p-0">
          <table class="table mb-0">
            <thead class="table-light">
              <tr>
                <th>Product</th>
                <th>Size</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              @foreach($order->items as $item)
                <tr>
                  <td>{{ $item->product_name }}</td>
                  <td>{{ $item->size->name ?? 'N/A' }}</td>
                  <td>&#8369;{{ number_format($item->price, 2) }}</td>
                  <td>{{ $item->quantity }}</td>
                  <td>&#8369;{{ number_format($item->subtotal, 2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      @if(Auth::check() && Auth::id() == $order->user_id)
        <div class="card mt-4">
          <div class="card-header bg-white">
            <h5 class="mb-0">Actions</h5>
          </div>
          <div class="card-body">
            @if(in_array($order->status, ['placed', 'confirmed', 'preparing']) && $order->delivery_status != 'delivered')
              <form action="{{ route('orders.cancel', $order) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this order?')">
                @csrf
                <div class="mb-2">
                  <label class="form-label">Cancellation Reason</label>
                  <textarea name="reason" class="form-control" rows="2" required placeholder="Please provide a reason for cancellation"></textarea>
                </div>
                <button type="submit" class="btn btn-danger btn-sm">Request Cancellation</button>
              </form>
            @endif

            @if($order->status == 'delivered' && $order->return_status == 'none')
              <form action="{{ route('orders.return', $order) }}" method="POST" class="d-inline mt-2" onsubmit="return confirm('Request return for this order?')">
                @csrf
                <div class="mb-2">
                  <label class="form-label">Return Reason</label>
                  <textarea name="reason" class="form-control" rows="2" required placeholder="Please provide a reason for return"></textarea>
                </div>
                <button type="submit" class="btn btn-warning btn-sm">Request Return</button>
              </form>
            @endif

            @if($order->status == 'delivery_failed')
              <div class="alert alert-warning mt-2">
                <i class="bi bi-exclamation-triangle"></i> Delivery failed. Please choose an option below.
              </div>
              <form action="{{ route('orders.reschedule', $order) }}" method="POST" class="d-inline mt-2" onsubmit="return confirm('Request reschedule for this order?')">
                @csrf
                <div class="mb-2">
                  <label class="form-label">Reschedule Reason (Optional)</label>
                  <textarea name="reschedule_reason" class="form-control" rows="2" placeholder="Provide a reason or preferred time for reschedule"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class="bi bi-calendar-check"></i> Reschedule Delivery
                </button>
              </form>
              <form action="{{ route('orders.return', $order) }}" method="POST" class="d-inline mt-2 ms-2" onsubmit="return confirm('Request return for this order? This will send the item back to the seller.')">
                @csrf
                <div class="mb-2">
                  <label class="form-label">Return Reason</label>
                  <textarea name="reason" class="form-control" rows="2" required placeholder="Please provide a reason for return"></textarea>
                </div>
                <button type="submit" class="btn btn-danger btn-sm">
                  <i class="bi bi-arrow-return-left"></i> Return Item
                </button>
              </form>
            @endif

            @if($order->status == 'delivered')
              @if($order->confirmed_received_at)
                <div class="alert alert-success mt-2">
                  <i class="bi bi-check-circle"></i> You confirmed receiving this order on {{ $order->confirmed_received_at->format('M d, Y H:i') }}.
                </div>
              @else
                <form action="{{ route('orders.confirm-received', $order) }}" method="POST" class="d-inline mt-2" onsubmit="return confirm('Confirm that you have received this order?')">
                  @csrf
                  <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-emoji-check"></i> Confirm Received
                  </button>
                </form>
              @endif
            @endif

            @if($order->status == 'completed' || ($order->status == 'delivered' && $order->confirmed_received_at))
              <a href="{{ route('buyer.reviews.create', $order) }}" class="btn btn-outline-primary btn-sm mt-2">
                <i class="bi bi-star"></i> Write a Review
              </a>
            @endif

            @if($order->return_status == 'requested')
              <div class="alert alert-warning mt-2">
                <i class="bi bi-hourglass-split"></i> Return request pending approval.
              </div>
            @endif
            @if($order->return_status == 'approved')
              <div class="alert alert-success mt-2">
                <i class="bi bi-check-circle"></i> Return request approved.
              </div>
            @endif
            @if($order->return_status == 'rejected')
              <div class="alert alert-danger mt-2">
                <i class="bi bi-x-circle"></i> Return request rejected.
              </div>
            @endif
          </div>
        </div>
      @endif
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-header bg-white">
          <h5 class="mb-0">Order Summary</h5>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between mb-2">
            <span>Subtotal</span>
            <span>&#8369;{{ number_format($order->subtotal, 2) }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span>Tax</span>
            <span>&#8369;{{ number_format($order->tax, 2) }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span>Shipping</span>
            <span>&#8369;{{ number_format($order->shipping, 2) }}</span>
          </div>
          <hr>
          <div class="d-flex justify-content-between fw-bold">
            <span>Total</span>
            <span>&#8369;{{ number_format($order->total, 2) }}</span>
          </div>
        </div>
      </div>

          @if($order->rider)
            <div class="card mt-3">
              <div class="card-header bg-white">
                <h5 class="mb-0">Delivery Information</h5>
              </div>
              <div class="card-body">
                <p><strong>Rider:</strong> {{ $order->rider->name }}</p>
                <p><strong>Contact:</strong> {{ $order->rider->phone ?? 'N/A' }}</p>
                @if($order->delivery_status == 'on_the_way' || $order->delivery_status == 'delivered')
                  <a href="{{ route('rider.track') }}" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-geo-alt"></i> Track Delivery
                  </a>
                @endif
              </div>
            </div>
          @endif

          @if($order->proof_type)
            <div class="card mt-3">
              <div class="card-header bg-white">
                <h5 class="mb-0">Proof of Delivery</h5>
              </div>
              <div class="card-body">
                <p><strong>Type:</strong> {{ ucfirst($order->proof_type) }}</p>
                <p><strong>Details:</strong> {{ $order->proof_data }}</p>
                @if($order->delivery_notes)
                  <p><strong>Rider Notes:</strong> {{ $order->delivery_notes }}</p>
                @endif
              </div>
            </div>
          @endif
    </div>
  </div>
</div>
@endsection


