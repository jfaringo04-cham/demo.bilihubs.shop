@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Complaint / Dispute #{{ $ticket->id }}</h1>
  <a href="{{ route('admin.complaints.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="row g-4">
  <div class="col-md-7">
    <div class="table-container mb-4">
      <h5 class="mb-3">Details</h5>
      <table class="table table-borderless">
        <tr><th class="w-40">Type</th><td><span class="badge bg-info text-capitalize">{{ $ticket->type }}</span></td></tr>
        <tr><th>Subject</th><td>{{ $ticket->subject }}</td></tr>
        <tr><th>From (Complainant)</th><td>{{ $ticket->user->name ?? 'N/A' }} ({{ $ticket->user->email ?? '' }})</td></tr>
        <tr><th>Against</th><td>{{ $ticket->againstUser->name ?? '—' }}</td></tr>
        <tr><th>Order</th><td>{{ $ticket->order ? $ticket->order->order_number : '—' }}</td></tr>
        <tr><th>Priority</th><td>{{ $ticket->priority ?? '—' }}</td></tr>
        <tr><th>Status</th><td><span class="badge bg-{{ $ticket->status == 'resolved' ? 'success' : 'warning' }} text-capitalize">{{ $ticket->status }}</span></td></tr>
      </table>
    </div>

    <div class="table-container mb-4">
      <h5 class="mb-3">Message</h5>
      <p>{{ $ticket->message }}</p>
      @if($ticket->evidence)
        <a href="{{ asset('storage/' . $ticket->evidence) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-paperclip"></i> View Supporting Evidence
        </a>
      @endif
    </div>

    @if($ticket->response)
      <div class="table-container">
        <h5 class="mb-3">Admin Response</h5>
        <p>{{ $ticket->response }}</p>
        <small class="text-muted">Responded: {{ $ticket->responded_at?->format('M d, Y H:i') }}</small>
      </div>
    @endif
  </div>

  <div class="col-md-5">
    <div class="table-container mb-4">
      <h5 class="mb-3">Resolve & Respond</h5>
      <form method="POST" action="{{ route('admin.complaints.respond', $ticket) }}" onsubmit="return confirm('Send response and resolve?');">
        @csrf
        <div class="mb-2">
          <textarea name="response" class="form-control" rows="4" placeholder="Write your resolution / response" required>{{ old('response', $ticket->response) }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-reply"></i> Respond & Resolve</button>
      </form>
      @if($ticket->status != 'resolved')
        <form method="POST" action="{{ route('admin.complaints.resolve', $ticket) }}" class="mt-2">
          @csrf
          <button type="submit" class="btn btn-outline-success w-100" onclick="return confirm('Mark as resolved without a response?');">
            <i class="bi bi-check-circle"></i> Mark Resolved
          </button>
        </form>
      @endif
    </div>

    <div class="table-container">
      <h5 class="mb-3">Coordinate (Message a Party)</h5>
      <form method="POST" action="{{ route('admin.complaints.message', $ticket) }}">
        @csrf
        <div class="mb-2">
          <select name="recipient_id" class="form-select">
            <option value="{{ $ticket->user_id }}">Complainant: {{ $ticket->user->name ?? 'N/A' }}</option>
            @if($ticket->againstUser)
              <option value="{{ $ticket->against_user_id }}">Against: {{ $ticket->againstUser->name }}</option>
            @endif
          </select>
        </div>
        <div class="mb-2">
          <textarea name="message" class="form-control" rows="3" placeholder="Message to coordinate" required></textarea>
        </div>
        <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-chat-dots"></i> Send Message</button>
      </form>
    </div>
  </div>
</div>
@endsection
