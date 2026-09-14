@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Platform Settings</h1>
  <a href="{{ route('admin.settings.announcements.create') }}" class="btn btn-bili-hub btn-sm"><i class="bi bi-plus-lg"></i> New Announcement</a>
</div>

<div class="row g-4">
  <div class="col-md-7">
    <div class="table-container">
      <h5 class="mb-3">Announcements</h5>
      @forelse($announcements as $a)
        <div class="border rounded p-3 mb-2">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <strong>{{ $a->title }}</strong>
              <small class="d-block text-muted">By {{ $a->admin->name ?? 'Admin' }} · {{ $a->created_at->format('M d, Y') }}</small>
            </div>
            <div class="d-flex gap-1">
              <span class="badge bg-{{ $a->is_active ? 'success' : 'secondary' }}">{{ $a->is_active ? 'Active' : 'Hidden' }}</span>
            </div>
          </div>
          <p class="mb-2 mt-2">{{ $a->body }}</p>
          <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admin.settings.announcements.toggle', $a) }}" class="d-inline">
              @csrf
              <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-eye{{ $a->is_active ? '-slash' : '' }}"></i> {{ $a->is_active ? 'Hide' : 'Show' }}
              </button>
            </form>
            <form method="POST" action="{{ route('admin.settings.announcements.delete', $a) }}" class="d-inline" onsubmit="return confirm('Delete this announcement?');">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
            </form>
          </div>
        </div>
      @empty
        <p class="text-muted">No announcements posted yet.</p>
      @endforelse
      <div class="mt-3">{{ $announcements->links('pagination::bootstrap-5') }}</div>
    </div>
  </div>

  <div class="col-md-5">
    <div class="table-container">
      <h5 class="mb-3">Platform Policies</h5>
      <form method="POST" action="{{ route('admin.settings.policies.update') }}">
        @csrf
        @foreach($policies as $policy)
          <div class="mb-3">
            <label class="form-label fw-bold">{{ $policy->label }}</label>
            <textarea name="{{ $policy->key }}" class="form-control" rows="4">{{ old($policy->key, $policy->value) }}</textarea>
          </div>
        @endforeach
        <button type="submit" class="btn btn-bili-hub w-100"><i class="bi bi-save"></i> Update Policies</button>
      </form>
    </div>
  </div>
</div>
@endsection
