@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">My Products</h1>
  <a href="{{ route('seller.products.create') }}" class="btn btn-bili-hub"><i class="bi bi-plus-circle"></i> Add Product</a>
</div>

<div class="table-container">
  <form method="GET" action="{{ route('seller.products') }}" class="row g-3 mb-3">
    <div class="col-md-4">
      <input type="text" name="search" class="form-control" placeholder="Search products..." value="{{ request('search') }}">
    </div>
    <div class="col-md-3">
      <select name="category" class="form-select">
        <option value="">All Categories</option>
        @foreach($categories as $category)
          <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-outline-secondary w-100">Filter</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Product</th>
          <th>Category</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Compliance</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($products as $product)
          <tr class="{{ in_array($product->compliance_status, ['flagged', 'auto_flagged']) ? 'table-danger' : ($product->compliance_status == 'pending' ? 'table-warning' : '') }}">
            <td>
              <div class="d-flex align-items-center">
                <img src="{{ $product->image ? asset('storage/' . $product->image) : 'https://via.placeholder.com/50x50?text=No+Image' }}" class="rounded me-2" style="width: 50px; height: 50px; object-fit: cover;" alt="{{ $product->name }}">
                <div>
                  <strong>{{ $product->name }}</strong>
                  @if($product->compliance_status == 'flagged' && $product->flagged_reason)
                    <div class="mt-1 p-2 bg-light border border-danger rounded">
                      <small class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> Admin Flag Reason:</small>
                      <div class="small text-dark">{{ $product->flagged_reason }}</div>
                      @if($product->admin_notes)
                        <div class="mt-1 small text-muted"><i class="bi bi-chat-left-text"></i> Note: {{ $product->admin_notes }}</div>
                      @endif
                      <small class="text-muted d-block mt-1">Please update your product and click "Resubmit for Review".</small>
                    </div>
                  @endif
                </div>
              </div>
            </td>
            <td>{{ $product->category->name ?? 'Uncategorized' }}</td>
            <td class="text-danger fw-bold">&#8369;{{ number_format($product->price, 2) }}</td>
            <td>
              {{ $product->stock }}
              @if($product->sizes->count() > 0)
                <br><small class="text-muted">
                  @foreach($product->sizes as $size)
                    {{ $size->name }}({{ $size->pivot->stock }})@if(!$loop->last), @endif
                  @endforeach
                </small>
              @endif
            </td>
            <td>
              @if($product->compliance_status == 'approved')
                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Approved</span>
              @elseif($product->compliance_status == 'flagged' || $product->compliance_status == 'auto_flagged')
                <span class="badge bg-danger">
                  <i class="bi {{ $product->compliance_status == 'auto_flagged' ? 'bi-robot' : 'bi-flag-fill' }}"></i>
                  {{ $product->compliance_status == 'auto_flagged' ? 'Auto-Flagged' : 'Flagged' }}
                </span>
                @php
                  $daysLeft = $product->daysUntilResubmitDeadline();
                  $deadlinePassed = $product->isResubmitDeadlinePassed();
                @endphp
                <div class="mt-1 small">
                  @if($deadlinePassed)
                    <span class="text-danger"><i class="bi bi-exclamation-octagon"></i> <strong>Deadline passed</strong></span>
                    <br><span class="text-muted">Contact admin to restore.</span>
                  @else
                    <span class="text-warning"><i class="bi bi-hourglass-split"></i> <strong>{{ $daysLeft }} day(s) left</strong> to resubmit</span>
                  @endif
                </div>
                @if(!$deadlinePassed)
                  <div class="mt-1">
                    <a href="{{ route('seller.products.edit', $product) }}" class="btn btn-sm btn-warning">
                      <i class="bi bi-arrow-clockwise"></i> Fix & Resubmit
                    </a>
                  </div>
                @endif
              @elseif($product->compliance_status == 'pending')
                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Pending Review</span>
              @elseif($product->compliance_status == 'rejected')
                <span class="badge bg-dark"><i class="bi bi-x-octagon"></i> Auto-Rejected</span>
                <div class="mt-1 small text-muted">Deadline expired. Contact admin to restore.</div>
              @else
                <span class="badge bg-secondary">{{ ucfirst($product->compliance_status ?? 'Unknown') }}</span>
              @endif
            </td>
            <td>
              <a href="{{ route('seller.products.edit', $product) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form action="{{ route('seller.products.destroy', $product) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No products found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">
    {{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection


