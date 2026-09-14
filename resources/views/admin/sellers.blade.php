@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">Sellers Management</h1>
    <p class="text-muted mb-0">Overview of registered sellers</p>
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Seller Name</th>
          <th class="border-0">Email</th>
          <th class="border-0">Phone</th>
          <th class="border-0">Products</th>
          <th class="border-0">Joined</th>
          <th class="border-0"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($sellers as $seller)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <div class="bg-sky-100 text-sky-600 rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; font-size: 0.875rem;">
                  {{ substr($seller->name, 0, 1) }}
                </div>
                <span class="fw-semibold">{{ $seller->name }}</span>
              </div>
            </td>
            <td class="text-muted">{{ $seller->email }}</td>
            <td class="text-muted">{{ $seller->phone ?? 'N/A' }}</td>
            <td><span class="badge bg-info">{{ $seller->products_count }} products</span></td>
            <td class="text-muted">{{ $seller->created_at->format('M d, Y') }}</td>
            <td>
              <a href="{{ route('admin.products', ['seller' => $seller->id]) }}" class="btn btn-primary btn-sm rounded-xl">
                <i class="bi bi-eye me-1"></i>View Products
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No sellers found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
