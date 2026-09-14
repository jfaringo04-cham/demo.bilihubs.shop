@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">User Accounts</h1>
    <p class="text-muted mb-0">Manage all registered users</p>
  </div>
</div>

<div class="card border-0 shadow-sm rounded-2 mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label for="role" class="form-label fw-medium">Role</label>
        <select name="role" id="role" class="form-select rounded-xl" onchange="this.form.submit()">
          <option value="">All Roles</option>
          <option value="customer" {{ request('role') == 'customer' ? 'selected' : '' }}>Buyer</option>
          <option value="seller" {{ request('role') == 'seller' ? 'selected' : '' }}>Seller</option>
          <option value="rider" {{ request('role') == 'rider' ? 'selected' : '' }}>Courier</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="status" class="form-label fw-medium">Status</label>
        <select name="status" id="status" class="form-select rounded-xl" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          @foreach($statuses as $key => $label)
            <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label for="search" class="form-label fw-medium">Search</label>
        <input type="text" name="search" id="search" value="{{ request('search') }}" class="form-control rounded-xl" placeholder="Name or email">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-secondary w-100 rounded-xl"><i class="bi bi-search me-1"></i>Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Name</th>
          <th class="border-0">Email</th>
          <th class="border-0">Role</th>
          <th class="border-0">Status</th>
          <th class="border-0">Joined</th>
          <th class="border-0"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($users as $u)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <div class="bg-sky-100 text-sky-600 rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; font-size: 0.875rem;">
                  {{ substr($u->name, 0, 1) }}
                </div>
                <span class="fw-semibold">{{ $u->name }}</span>
              </div>
            </td>
            <td class="text-muted">{{ $u->email }}</td>
            <td><span class="badge bg-info text-capitalize">{{ $u->role }}</span></td>
            <td><span class="badge bg-{{ $u->statusBadgeClass() }} text-capitalize">{{ $u->status }}</span></td>
            <td class="text-muted">{{ $u->created_at->format('M d, Y') }}</td>
            <td>
              <a href="{{ route('admin.users.show', $u) }}" class="btn btn-primary btn-sm rounded-xl">
                <i class="bi bi-eye me-1"></i>View
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No users found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 border-top">{{ $users->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
