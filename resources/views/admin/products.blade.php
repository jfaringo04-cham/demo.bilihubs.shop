@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
  <div>
    <h1 class="fw-bold text-slate-900 mb-0">Products Management</h1>
    <p class="text-muted mb-0">Review and manage marketplace listings</p>
  </div>
  <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Showing <strong>{{ $products->count() }}</strong> of <strong>{{ $products->total() }}</strong> products</small>
</div>

<div class="card border-0 shadow-sm rounded-2 mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('admin.products') }}" class="row g-3 align-items-end">
      <div class="col-md-6">
        <label for="categoryFilter" class="form-label fw-medium">Filter by Category</label>
        <select name="category" id="categoryFilter" class="form-select rounded-xl" onchange="this.form.submit()">
          <option value="">All Categories</option>
          @foreach($categories as $category)
            <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
              {{ $category->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-6 d-flex gap-2">
        <a href="{{ route('admin.products') }}" class="btn btn-secondary rounded-xl"><i class="bi bi-arrow-clockwise me-1"></i>Reset Filters</a>
      </div>
    </form>
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="border-0">Product Name</th>
          <th class="border-0">Category</th>
          <th class="border-0">Seller</th>
          <th class="border-0">Price</th>
          <th class="border-0">Stock</th>
          <th class="border-0">Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse($products as $product)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <img src="{{ $product->image ? asset('storage/' . $product->image) : 'https://via.placeholder.com/40?text=No+Image' }}" alt="{{ $product->name }}" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                <span class="fw-medium">{{ $product->name }}</span>
              </div>
            </td>
            <td><span class="badge bg-light text-dark border">{{ $product->category->name ?? 'Uncategorized' }}</span></td>
            <td class="text-muted">{{ $product->seller->name ?? 'N/A' }}</td>
            <td class="fw-semibold text-slate-900">&#8369;{{ number_format($product->price, 2) }}</td>
            <td>
              @if($product->stock > 0)
                <span class="badge bg-success">{{ $product->stock }} in stock</span>
              @else
                <span class="badge bg-danger">Out of stock</span>
              @endif
            </td>
            <td>
              @if($product->stock > 0)
                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
              @else
                <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>Inactive</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No products found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 border-top">{{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
