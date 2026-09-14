@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">User Profile</h1>
  <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="row g-4">
  <div class="col-md-8">
    <div class="table-container mb-4">
      <h5 class="mb-3">Profile Details</h5>
      <table class="table table-borderless">
        <tr><th class="w-40">Name</th><td>{{ $user->name }}</td></tr>
        <tr><th>Email</th><td>{{ $user->email }}</td></tr>
        <tr><th>Mobile</th><td>{{ $user->mobile_number ?? 'N/A' }}</td></tr>
        <tr><th>Role</th><td><span class="badge bg-info text-capitalize">{{ $user->role }}</span></td></tr>
        <tr><th>Status</th><td><span class="badge bg-{{ $user->statusBadgeClass() }} text-capitalize">{{ $user->status }}</span></td></tr>
        @if($user->suspended_at)
          <tr><th>Suspended At</th><td>{{ $user->suspended_at->format('M d, Y g:i A') }}</td></tr>
        @endif
        @if($user->appeal_submitted_at)
          <tr><th>Appeal Submitted</th><td>{{ $user->appeal_submitted_at->format('M d, Y g:i A') }}</td></tr>
          @if($user->appeal_message)
            <tr><th>Appeal Message</th><td>{{ $user->appeal_message }}</td></tr>
          @endif
        @endif
        <tr><th>Joined</th><td>{{ $user->created_at->format('M d, Y') }}</td></tr>
        @if($user->role === 'seller')
          <tr><th>Business Name</th><td>{{ $user->business_name ?? 'N/A' }}</td></tr>
          <tr><th>Selling Categories</th>
            <td>
              @foreach($user->selling_categories ?? [] as $catId)
                <span class="badge bg-light text-dark">{{ \App\Models\Category::find($catId)->name ?? $catId }}</span>
              @endforeach
            </td>
          </tr>
        @endif
        @if($user->role === 'rider')
          <tr><th>Vehicle Type</th><td>{{ $user->vehicle_type ?? 'N/A' }}</td></tr>
          <tr><th>License Number</th><td>{{ $user->license_number ?? 'N/A' }}</td></tr>
        @endif
      </table>
    </div>
  </div>

  <div class="col-md-4">
    <div class="table-container mb-4">
      <h5 class="mb-3">Account Overview</h5>
      <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between"><span>Products</span><strong>{{ $user->products_count }}</strong></li>
        <li class="list-group-item d-flex justify-content-between"><span>Orders</span><strong>{{ $user->orders_count }}</strong></li>
        <li class="list-group-item d-flex justify-content-between"><span>Tickets</span><strong>{{ $user->support_tickets_count }}</strong></li>
        <li class="list-group-item d-flex justify-content-between"><span>Warnings</span><strong>{{ $user->warnings_count }}</strong></li>
      </ul>
    </div>

    @if(!$user->isAdmin())
      <div class="table-container">
        <h5 class="mb-3">Manage Account</h5>
        @if($user->status === 'suspended' && $user->appeal_submitted_at)
          <div class="alert alert-warning mb-3">
            <strong>Appeal Submitted:</strong> {{ $user->appeal_submitted_at->format('M d, Y g:i A') }}
          </div>
        @endif
        <form method="POST" action="{{ route('admin.users.activate', $user) }}" class="mb-2">
          @csrf
          <button type="submit" class="btn btn-success w-100" onclick="return confirm('Activate this account?')">
            <i class="bi bi-check-circle"></i> Activate
          </button>
        </form>
        <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="mb-2">
          @csrf
          <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Suspend this account?')">
            <i class="bi bi-pause-circle"></i> Suspend
          </button>
        </form>
        <form method="POST" action="{{ route('admin.users.deactivate', $user) }}">
          @csrf
          <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Deactivate this account? This is permanent.')">
            <i class="bi bi-x-circle"></i> Deactivate
          </button>
        </form>
      </div>
    @else
      <div class="alert alert-info">This is an administrator account and cannot be modified.</div>
    @endif
  </div>
</div>
@endsection
