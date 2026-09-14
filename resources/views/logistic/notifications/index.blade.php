@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Notifications</h2>
    <form action="{{ route('logistic.notifications.readAll') }}" method="POST">
      @csrf
      <button type="submit" class="btn btn-outline-secondary btn-sm">Mark All as Read</button>
    </form>
  </div>

  @if($notifications->count() > 0)
    <div class="table-container">
      <div class="list-group list-group-flush">
        @foreach($notifications as $notification)
          <a href="{{ $notification->link ?: '#' }}" class="list-group-item list-group-item-action {{ $notification->is_read ? '' : 'list-group-item-primary' }}">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="mb-1 {{ $notification->is_read ? 'text-muted' : 'fw-bold' }}">{{ $notification->title }}</h6>
                <p class="mb-1 small">{{ $notification->message }}</p>
                <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
              </div>
              @if(!$notification->is_read)
                <span class="badge bg-primary rounded-pill">New</span>
              @endif
            </div>
          </a>
        @endforeach
      </div>
    </div>
    <div class="mt-3">
      {{ $notifications->links('pagination::bootstrap-5') }}
    </div>
  @else
    <div class="text-center py-5">
      <i class="bi bi-bell display-1 text-muted"></i>
      <p class="text-muted mt-3">No notifications yet.</p>
    </div>
  @endif
</div>
@endsection
