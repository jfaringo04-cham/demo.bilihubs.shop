@extends('layouts.app')

@section('content')
<div class="container py-4">
  <a href="{{ route('buyer.support.index') }}" class="btn btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to Tickets</a>

  <div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0">Ticket #{{ $ticket->id }}: {{ $ticket->subject }}</h5>
        <small class="text-muted">Created on {{ $ticket->created_at->format('M d, Y H:i') }}</small>
      </div>
      <span class="badge bg-{{ $ticket->status == 'open' ? 'warning' : ($ticket->status == 'resolved' ? 'success' : 'secondary') }}">
        {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
      </span>
    </div>
    <div class="card-body">
      <div class="mb-4">
        <h6>Your Message:</h6>
        <p>{{ $ticket->message }}</p>
      </div>

      @if($ticket->response)
        <div class="alert alert-info">
          <h6>Support Response:</h6>
          <p>{{ $ticket->response }}</p>
          <small class="text-muted">{{ $ticket->responded_at->format('M d, Y H:i') }}</small>
        </div>
      @else
        <div class="alert alert-warning">
          <i class="bi bi-hourglass-split"></i> Waiting for support response...
        </div>
      @endif
    </div>
  </div>
</div>
@endsection


