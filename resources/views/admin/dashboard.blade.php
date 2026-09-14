@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">Dashboard Overview</h1>
    <p class="text-muted mb-0">Welcome back, here's what's happening today.</p>
  </div>
  <span class="text-muted small">{{ now()->format('F d, Y') }}</span>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-sky-50 text-sky-500 me-3"><i class="bi bi-shop"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Total Sellers</h6>
          <h3 class="mb-0 fw-bold">{{ $totalSellers }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-emerald-50 text-emerald-500 me-3"><i class="bi bi-box"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Total Products</h6>
          <h3 class="mb-0 fw-bold">{{ $totalProducts }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-amber-50 text-amber-500 me-3"><i class="bi bi-receipt"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Total Orders</h6>
          <h3 class="mb-0 fw-bold">{{ $totalOrders }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-violet-50 text-violet-500 me-3"><i class="bi bi-currency-dollar"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Total Revenue</h6>
          <h3 class="mb-0 fw-bold">&#8369;{{ number_format($totalRevenue, 2) }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-rose-50 text-rose-500 me-3"><i class="bi bi-truck"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Logistics</h6>
          <h3 class="mb-0 fw-bold">{{ $totalLogistics }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <a href="{{ route('admin.registrations.index') }}" class="text-decoration-none">
      <div class="card stat-card h-100 border-warning">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-warning text-white me-3"><i class="bi bi-person-plus"></i></div>
          <div>
            <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Pending</h6>
            <h3 class="mb-0 fw-bold {{ $pendingRegistrationsCount > 0 ? 'text-warning' : '' }}">{{ $pendingRegistrationsCount }}</h3>
          </div>
        </div>
      </div>
    </a>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-4">
    <a href="{{ route('admin.compliance.index', ['filter' => 'flagged']) }}" class="text-decoration-none">
      <div class="card stat-card h-100 border-danger">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-danger text-white me-3"><i class="bi bi-flag-fill"></i></div>
          <div>
            <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Flagged Products</h6>
            <h3 class="mb-0 fw-bold {{ $flaggedProductsCount > 0 ? 'text-danger' : '' }}">{{ $flaggedProductsCount }}</h3>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="{{ route('admin.compliance.index', ['filter' => 'pending']) }}" class="text-decoration-none">
      <div class="card stat-card h-100 border-info">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-info text-white me-3"><i class="bi bi-arrow-clockwise"></i></div>
          <div>
            <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Resubmitted</h6>
            <h3 class="mb-0 fw-bold {{ $resubmittedProductsCount > 0 ? 'text-info' : '' }}">{{ $resubmittedProductsCount }}</h3>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-sky-50 text-sky-500 me-3"><i class="bi bi-people"></i></div>
        <div>
          <h6 class="text-muted mb-1" style="font-size: 0.8rem;">Total Users</h6>
          <h3 class="mb-0 fw-bold">{{ $totalSellers + $totalOrders }}</h3>
        </div>
      </div>
    </div>
  </div>
</div>

@if($flaggedProducts->count() > 0)
<div class="row mb-4">
  <div class="col-12">
    <div class="table-container border-danger">
      <div class="p-4 border-bottom border-danger">
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-flag-fill text-danger"></i>
            <h5 class="mb-0 fw-semibold">Flagged Products</h5>
            <span class="badge bg-danger">{{ $flaggedProducts->count() }}</span>
          </div>
          <a href="{{ route('admin.compliance.index', ['filter' => 'flagged']) }}" class="btn btn-outline-danger btn-sm rounded-xl">Review All</a>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="border-0">Product</th>
              <th class="border-0">Seller</th>
              <th class="border-0">Category</th>
              <th class="border-0">Status</th>
              <th class="border-0">Flagged</th>
              <th class="border-0"></th>
            </tr>
          </thead>
          <tbody>
            @foreach($flaggedProducts as $p)
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <img src="{{ $p->image ? asset('storage/' . $p->image) : 'https://via.placeholder.com/40x40?text=No+Image' }}" class="rounded me-3" style="width: 40px; height: 40px; object-fit: cover;" alt="{{ $p->name }}">
                    <span class="fw-medium">{{ $p->name }}</span>
                  </div>
                </td>
                <td class="text-muted">{{ $p->seller->business_name ?? $p->seller->name ?? 'N/A' }}</td>
                <td class="text-muted">{{ $p->category->name ?? 'N/A' }}</td>
                <td>
                  @if($p->compliance_status == 'auto_flagged')
                    <span class="badge bg-danger"><i class="bi bi-robot me-1"></i>Auto-Flagged</span>
                  @else
                    <span class="badge bg-danger"><i class="bi bi-flag-fill me-1"></i>Manually Flagged</span>
                  @endif
                </td>
                <td class="text-muted">{{ $p->flagged_at?->diffForHumans() ?? $p->updated_at->diffForHumans() }}</td>
                <td>
                  <a href="{{ route('admin.compliance.show', $p) }}" class="btn btn-primary btn-sm rounded-xl">
                    <i class="bi bi-eye me-1"></i>Review
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endif

@if($resubmittedProducts->count() > 0)
<div class="row mb-4">
  <div class="col-12">
    <div class="table-container border-info">
      <div class="p-4 border-bottom border-info">
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-arrow-clockwise text-info"></i>
            <h5 class="mb-0 fw-semibold">Resubmitted Products</h5>
            <span class="badge bg-info">{{ $resubmittedProducts->count() }}</span>
          </div>
          <a href="{{ route('admin.compliance.index', ['filter' => 'pending']) }}" class="btn btn-outline-info btn-sm rounded-xl">Review All</a>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="border-0">Product</th>
              <th class="border-0">Seller</th>
              <th class="border-0">Category</th>
              <th class="border-0">Price</th>
              <th class="border-0">Resubmitted</th>
              <th class="border-0"></th>
            </tr>
          </thead>
          <tbody>
            @foreach($resubmittedProducts as $p)
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <img src="{{ $p->image ? asset('storage/' . $p->image) : 'https://via.placeholder.com/40x40?text=No+Image' }}" class="rounded me-3" style="width: 40px; height: 40px; object-fit: cover;" alt="{{ $p->name }}">
                    <span class="fw-medium">{{ $p->name }}</span>
                  </div>
                </td>
                <td class="text-muted">{{ $p->seller->business_name ?? $p->seller->name ?? 'N/A' }}</td>
                <td class="text-muted">{{ $p->category->name ?? 'N/A' }}</td>
                <td class="fw-medium">&#8369;{{ number_format($p->price, 2) }}</td>
                <td class="text-muted">{{ $p->updated_at->diffForHumans() }}</td>
                <td>
                  <a href="{{ route('admin.compliance.show', $p) }}" class="btn btn-primary btn-sm rounded-xl">
                    <i class="bi bi-eye me-1"></i>Review
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endif

@if($pendingRegistrations->count() > 0)
<div class="row mb-4">
  <div class="col-12">
    <div class="table-container border-warning">
      <div class="p-4 border-bottom border-warning">
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-person-plus text-warning"></i>
            <h5 class="mb-0 fw-semibold">Recent Pending Registrations</h5>
            <span class="badge bg-warning">{{ $pendingRegistrations->count() }}</span>
          </div>
          <a href="{{ route('admin.registrations.index') }}" class="btn btn-outline-warning btn-sm rounded-xl">View All</a>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="border-0">Name</th>
              <th class="border-0">Email</th>
              <th class="border-0">Role</th>
              <th class="border-0">Registered</th>
              <th class="border-0"></th>
            </tr>
          </thead>
          <tbody>
            @foreach($pendingRegistrations as $reg)
              <tr>
                <td class="fw-medium">{{ $reg->name }}</td>
                <td class="text-muted">{{ $reg->email }}</td>
                <td>
                  <span class="badge bg-info">
                    {{ $reg->role == 'customer' ? 'Buyer' : ucfirst(str_replace('_', ' ', $reg->role)) }}
                  </span>
                </td>
                <td class="text-muted">{{ $reg->created_at->diffForHumans() }}</td>
                <td>
                  <a href="{{ route('admin.registrations.show', $reg) }}" class="btn btn-warning btn-sm rounded-xl">
                    <i class="bi bi-eye me-1"></i>Review
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endif

<div class="row g-4">
  <div class="col-lg-6">
    <div class="table-container">
      <div class="p-4 border-bottom">
        <h5 class="mb-0 fw-semibold">Recent Orders</h5>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="border-0">Order #</th>
              <th class="border-0">Customer</th>
              <th class="border-0">Status</th>
              <th class="border-0">Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recentOrders as $order)
              <tr>
                <td class="fw-medium">{{ $order->order_number }}</td>
                <td class="text-muted">{{ $order->user->name ?? 'N/A' }}</td>
                <td><span class="badge bg-{{ $order->statusBadgeClass() }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span></td>
                <td class="fw-medium">&#8369;{{ number_format($order->total, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted py-4">No orders yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="table-container">
      <div class="p-4 border-bottom">
        <h5 class="mb-0 fw-semibold">Top Products</h5>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="border-0">Product</th>
              <th class="border-0">Seller</th>
              <th class="border-0">Price</th>
              <th class="border-0">Stock</th>
            </tr>
          </thead>
          <tbody>
            @forelse($topProducts as $product)
              <tr>
                <td class="fw-medium">{{ $product->name }}</td>
                <td class="text-muted">{{ $product->seller->name ?? 'N/A' }}</td>
                <td class="fw-medium">&#8369;{{ number_format($product->price, 2) }}</td>
                <td class="text-muted">{{ $product->stock }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted py-4">No products yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
