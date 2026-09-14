@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Complaints & Disputes</h1>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('admin.complaints.index') }}" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-bold">Type</label>
        <select name="type" class="form-select" onchange="this.form.submit()">
          <option value="">All Types</option>
          <option value="complaint" {{ request('type') == 'complaint' ? 'selected' : '' }}>Complaint</option>
          <option value="dispute" {{ request('type') == 'dispute' ? 'selected' : '' }}>Dispute</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">Status</label>
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>Open</option>
          <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
        </select>
      </div>
    </form>
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Type</th>
          <th>Subject</th>
          <th>From</th>
          <th>Against</th>
          <th>Priority</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($tickets as $ticket)
          <tr>
            <td>#{{ $ticket->id }}</td>
            <td><span class="badge bg-info text-capitalize">{{ $ticket->type }}</span></td>
            <td>{{ $ticket->subject }}</td>
            <td>{{ $ticket->user->name ?? 'N/A' }}</td>
            <td>{{ $ticket->againstUser->name ?? '—' }}</td>
            <td>
              @if($ticket->priority)
                <span class="badge bg-{{ $ticket->priority == 'high' ? 'danger' : ($ticket->priority == 'medium' ? 'warning' : 'secondary') }} text-capitalize">{{ $ticket->priority }}</span>
              @else — @endif
            </td>
            <td><span class="badge bg-{{ $ticket->status == 'resolved' ? 'success' : 'warning' }} text-capitalize">{{ $ticket->status }}</span></td>
            <td><a href="{{ route('admin.complaints.show', $ticket) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a></td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No complaints or disputes.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $tickets->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
