@extends('admin.layout')

@section('title', 'Activity Log')

@section('content')
<div class="mb-4">
  <h1 class="h2">Activity Log</h1>
  <p class="text-muted mb-0">Admin actions recorded by BiliHub. Times are shown in Philippine time.</p>
</div>

@if ($errors->any())
  <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

<form class="row g-3 align-items-end mb-4" method="GET" action="{{ route('admin.activity-log.index') }}">
  <div class="col-sm-6 col-lg-2">
    <label for="activity-admin" class="form-label">Admin ID</label>
    <input id="activity-admin" name="actor_id" type="number" min="1" value="{{ $filters['actor_id'] ?? '' }}" class="form-control">
  </div>
  <div class="col-sm-6 col-lg-2">
    <label for="activity-area" class="form-label">Area</label>
    <select id="activity-area" name="area" class="form-select">
      <option value="">All areas</option>
      @foreach ($areas as $area)
        <option value="{{ $area }}" @selected(($filters['area'] ?? '') === $area)>{{ \Illuminate\Support\Str::headline($area) }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-sm-6 col-lg-2">
    <label for="activity-outcome" class="form-label">Outcome</label>
    <select id="activity-outcome" name="outcome" class="form-select">
      <option value="">All outcomes</option>
      @foreach ($outcomes as $value => $label)
        <option value="{{ $value }}" @selected(($filters['outcome'] ?? '') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-sm-6 col-lg-3">
    <label for="activity-date" class="form-label">Date</label>
    <input id="activity-date" name="date" type="date" value="{{ $filters['date'] ?? '' }}" class="form-control">
  </div>
  <div class="col-auto d-flex gap-2">
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="{{ route('admin.activity-log.index') }}" class="btn btn-outline-secondary">Clear</a>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <caption class="visually-hidden">Admin activity, newest first</caption>
      <thead class="table-light">
        <tr>
          <th scope="col">Date and Time</th>
          <th scope="col">Admin</th>
          <th scope="col">Action</th>
          <th scope="col">Record</th>
          <th scope="col">Outcome</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($entries as $entry)
          <tr>
            <td class="text-nowrap"><time datetime="{{ $entry->occurred_at->toIso8601String() }}">{{ $entry->occurred_at->timezone($timezone)->format('M j, Y g:i:s A') }}</time></td>
            <td>{{ $entry->actor_name }} <span class="text-muted">#{{ $entry->actor_id }}</span></td>
            <td>
              <span class="d-block fw-semibold">{{ \Illuminate\Support\Str::headline($entry->action) }}</span>
              <span class="text-muted small">{{ \Illuminate\Support\Str::headline($entry->area) }}</span>
            </td>
            <td>
              @forelse ($entry->targets as $name => $id)
                <span class="d-block text-nowrap">{{ \Illuminate\Support\Str::headline($name) }} #{{ $id }}</span>
              @empty
                <span class="text-muted">—</span>
              @endforelse
            </td>
            <td>
              <span class="badge {{ match ($entry->outcome) {
                  'completed' => 'text-bg-success',
                  'rejected', 'failed' => 'text-bg-danger',
                  default => 'text-bg-secondary',
              } }}">{{ $outcomes[$entry->outcome] }}</span>
              <span class="text-muted small">{{ $entry->http_status }}</span>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4">No matching admin activity.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3">{{ $entries->links() }}</div>
@endsection
