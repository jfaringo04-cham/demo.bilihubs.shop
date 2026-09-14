@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">New Announcement</h1>
  <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="table-container" style="max-width: 720px;">
  <form method="POST" action="{{ route('admin.settings.announcements.store') }}">
    @csrf
    <div class="mb-3">
      <label class="form-label fw-bold">Title</label>
      <input type="text" name="title" value="{{ old('title') }}" class="form-control @error('title') is-invalid @enderror" required>
      @error('title') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
      <label class="form-label fw-bold">Message</label>
      <textarea name="body" rows="6" class="form-control @error('body') is-invalid @enderror" required>{{ old('body') }}</textarea>
      @error('body') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="isActive">
      <label class="form-check-label" for="isActive">Publish immediately (visible to users)</label>
    </div>
    <button type="submit" class="btn btn-bili-hub"><i class="bi bi-megaphone"></i> Post Announcement</button>
  </form>
</div>
@endsection
