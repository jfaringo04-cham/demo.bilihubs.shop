@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Messages</h1>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr><th>User</th><th>Role</th><th>Last Activity</th><th>Action</th></tr>
      </thead>
      <tbody>
        @forelse($conversations as $user)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                  {{ substr($user->name, 0, 1) }}
                </div>
                <strong>{{ $user->name }}</strong>
              </div>
            </td>
            <td><span class="badge bg-info text-capitalize">{{ $user->role }}</span></td>
            <td>{{ $user->last_activity?->format('M d, Y H:i') ?? '—' }}</td>
            <td><a href="{{ route('admin.messages.show', $user) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-chat-dots"></i> Open</a></td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No conversations yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
