@extends('rider.layout')

@section('content')
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h2 class="mb-1">Notifications</h2>
      <p class="text-muted mb-0">View your rider updates and pickup requests.</p>
    </div>

    <form action="{{ route('rider.notifications.readAll') }}" method="POST">
      @csrf
      <button type="submit" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-check2-all me-1"></i>
        Mark All as Read
      </button>
    </form>
  </div>

  @if($notifications->count() > 0)
    <div class="card border-0 shadow-sm">
      <div class="list-group list-group-flush">
        @foreach($notifications as $notification)
          <a
            href="{{ $notification->link ?: route('rider.notifications') }}"
            class="list-group-item list-group-item-action {{ $notification->is_read ? '' : 'list-group-item-primary' }}"
            onclick="return markAsRead({{ $notification->id }}, this, event)"
          >
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div class="flex-grow-1" style="min-width: 0;">
                <h6 class="mb-1 {{ $notification->is_read ? 'text-muted' : 'fw-bold' }}">
                  {{ $notification->title }}
                </h6>
                <p class="mb-1 small text-break">{{ $notification->message }}</p>
                <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
              </div>

              @if(!$notification->is_read)
                <span class="badge bg-primary rounded-pill flex-shrink-0">New</span>
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
    <div class="card border-0 shadow-sm">
      <div class="text-center py-5">
        <i class="bi bi-bell display-1 text-muted"></i>
        <p class="text-muted mt-3 mb-0">No notifications yet.</p>
      </div>
    </div>
  @endif
</div>
@endsection