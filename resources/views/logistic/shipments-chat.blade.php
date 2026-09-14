@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Shipment Chat</h1>
  <a href="{{ route('logistic.shipments.show', $shipment) }}" class="btn btn-secondary btn-sm">Back to Shipment</a>
</div>

<div class="row g-4">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0">Shipment Details</h5>
      </div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label text-muted">Tracking Number</label>
          <p class="fw-bold"><code>{{ $shipment->tracking_number }}</code></p>
        </div>
        <div class="mb-2">
          <label class="form-label text-muted">Status</label>
          <p class="fw-bold">
            <span class="badge bg-{{ $shipment->statusBadgeClass() }}">
              {{ ucfirst(str_replace('_', ' ', $shipment->status)) }}
            </span>
          </p>
        </div>
        <div class="mb-2">
          <label class="form-label text-muted">Rider</label>
          <p class="fw-bold">{{ $shipment->rider->name ?? 'Unassigned' }}</p>
        </div>
        <div class="mb-2">
          <label class="form-label text-muted">Pickup</label>
          <p class="fw-bold">{{ Str::limit($shipment->pickup_address, 40) }}</p>
        </div>
        <div>
          <label class="form-label text-muted">Delivery</label>
          <p class="fw-bold">{{ Str::limit($shipment->delivery_address, 40) }}</p>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0">Messages</h5>
      </div>
      <div class="card-body">
        <div class="chat-container" style="height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 1rem; background-color: #f8f9fa;">
          @forelse($messages as $message)
            <div class="mb-3 {{ $message->user_id == Auth::id() ? 'text-end' : 'text-start' }}">
              <div class="d-inline-block p-2 rounded {{ $message->user_id == Auth::id() ? 'bg-primary text-white' : 'bg-white border' }}" style="max-width: 75%;">
                <div class="small fw-bold mb-1">{{ $message->user->name }}</div>
                <div>{{ $message->message }}</div>
                <div class="small text-muted mt-1">{{ $message->created_at->diffForHumans() }}</div>
              </div>
            </div>
          @empty
            <div class="text-center text-muted py-5">No messages yet. Start the conversation!</div>
          @endforelse
        </div>

        <form method="POST" action="{{ route('logistic.shipments.chat.store', $shipment) }}" class="mt-3">
          @csrf
          <div class="input-group">
            <textarea name="message" class="form-control" rows="2" placeholder="Type your message..." required></textarea>
            <button type="submit" class="btn btn-bili-hub">Send</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
