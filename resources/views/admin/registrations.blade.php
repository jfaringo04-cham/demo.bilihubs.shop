@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">Account Registrations</h1>
    <p class="text-muted mb-0">Review and manage new user applications</p>
  </div>
</div>

<div class="card border-0 shadow-sm rounded-2 mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('admin.registrations.index') }}" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label for="role" class="form-label fw-medium">Filter by Role</label>
        <select name="role" id="role" class="form-select rounded-xl" onchange="this.form.submit()">
          <option value="">All Roles</option>
          <option value="customer" {{ request('role') == 'customer' ? 'selected' : '' }}>Buyer</option>
          <option value="seller" {{ request('role') == 'seller' ? 'selected' : '' }}>Seller</option>
          <option value="rider" {{ request('role') == 'rider' ? 'selected' : '' }}>Courier</option>
        </select>
      </div>
    </form>
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Applicant</th>
          <th class="border-0">Email</th>
          <th class="border-0">Role</th>
          <th class="border-0">Applied</th>
          <th class="border-0"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($applications as $app)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <div class="bg-sky-100 text-sky-600 rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                  {{ substr($app->name, 0, 1) }}
                </div>
                <div>
                  <div class="fw-semibold">{{ $app->name }}</div>
                  <small class="text-muted">{{ $app->first_name }} {{ $app->last_name }}</small>
                </div>
              </div>
            </td>
            <td class="text-muted">{{ $app->email }}</td>
            <td><span class="badge bg-info text-capitalize">{{ $app->role }}</span></td>
            <td class="text-muted">{{ $app->created_at->format('M d, Y') }}</td>
            <td>
              <a href="{{ route('admin.registrations.show', $app) }}" class="btn btn-primary btn-sm rounded-xl">
                <i class="bi bi-eye me-1"></i>Review
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No pending applications.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
