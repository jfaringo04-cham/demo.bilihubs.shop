@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Customer Support</h2>
    <a href="{{ route('buyer.support.create') }}" class="btn btn-bili-hub"><i class="bi bi-plus-circle"></i> New Ticket</a>
  </div>

  @if($tickets->count() > 0)
    <div class="table-container">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Ticket ID</th>
              <th>Subject</th>
              <th>Status</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach($tickets as $ticket)
              <tr>
                <td>#{{ $ticket->id }}</td>
                <td>{{ $ticket->subject }}</td>
                <td>
                  <span class="badge bg-{{ $ticket->status == 'open' ? 'warning' : ($ticket->status == 'resolved' ? 'success' : 'secondary') }}">
                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                  </span>
                </td>
                <td>{{ $ticket->created_at->format('M d, Y') }}</td>
                <td><a href="{{ route('buyer.support.show', $ticket) }}" class="btn btn-sm btn-outline-primary">View</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @else
    <div class="text-center py-5">
      <i class="bi bi-headset display-1 text-muted"></i>
      <p class="text-muted mt-3">No support tickets yet.</p>
      <a href="{{ route('buyer.support.create') }}" class="btn btn-bili-hub">Create Your First Ticket</a>
    </div>
  @endif
</div>
@endsection


