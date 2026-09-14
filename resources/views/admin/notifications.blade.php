@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">Notifications</h1>
    <p class="text-muted mb-0">Stay updated with platform activities</p>
  </div>
  <form method="POST" action="{{ route('admin.notifications.readAll') }}" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-secondary btn-sm rounded-xl"><i class="bi bi-check-all me-1"></i>Mark All Read</button>
  </form>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Title</th>
          <th class="border-0">Message</th>
          <th class="border-0">Date</th>
          <th class="border-0"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($notifications as $n)
          <tr class="{{ $n->is_read ? '' : 'table-warning' }}">
            <td><strong>{{ $n->title }}</strong></td>
            <td class="text-muted">{{ $n->message }}</td>
            <td class="text-muted">{{ $n->created_at->format('M d, Y H:i') }}</td>
            <td>
              <form method="POST" action="{{ route('admin.notifications.read', $n) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm rounded-xl">
                  <i class="bi bi-eye me-1"></i>{{ $n->is_read ? 'View' : 'Mark Read' }}
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-bell-slash"></i> No notifications.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 border-top">{{ $notifications->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
