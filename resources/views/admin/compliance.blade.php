@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Compliance Monitor</h1>
  <small class="text-muted"><i class="bi bi-info-circle"></i> Products are auto-approved. Only flagged products need admin attention.</small>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('admin.compliance.index') }}" class="row g-3 align-items-end">
      <div class="col-md-5">
        <label class="form-label fw-bold">Filter</label>
        <select name="filter" class="form-select" onchange="this.form.submit()">
          <option value="flagged" {{ $filter == 'flagged' ? 'selected' : '' }}>Flagged (Needs Attention)</option>
          <option value="auto_flagged" {{ $filter == 'auto_flagged' ? 'selected' : '' }}>Auto-Flagged by System</option>
          <option value="resubmitted" {{ $filter == 'resubmitted' ? 'selected' : '' }}>Resubmitted by Seller (Awaiting Review)</option>
          <option value="rejected" {{ $filter == 'rejected' ? 'selected' : '' }}>Rejected (Expired Deadline)</option>
          <option value="mismatch" {{ $filter == 'mismatch' ? 'selected' : '' }}>Category Mismatch</option>
          <option value="all" {{ $filter == 'all' ? 'selected' : '' }}>All Non-Approved</option>
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label fw-bold">Seller</label>
        <select name="seller" class="form-select" onchange="this.form.submit()">
          <option value="">All Sellers</option>
          @foreach($sellers as $s)
            <option value="{{ $s->id }}" {{ request('seller') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
    </form>
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Product</th>
          <th>Category</th>
          <th>Seller</th>
          <th>Compliance</th>
          <th>Last Updated</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($products as $product)
          @php
            $mismatch = $product->seller
              && is_array($product->seller->selling_categories)
              && $product->category_id
              && !in_array($product->category_id, $product->seller->selling_categories);
            $isResubmitted = $product->compliance_status === 'pending'
              && $product->updated_at && $product->updated_at->gt($product->created_at);
          @endphp
          <tr class="{{ $isResubmitted ? 'table-info' : '' }}">
            <td>
              <strong>{{ $product->name }}</strong>
              @if($isResubmitted)
                <br><span class="badge bg-info"><i class="bi bi-arrow-clockwise"></i> Resubmitted</span>
              @endif
            </td>
            <td><span class="badge bg-light text-dark">{{ $product->category->name ?? 'N/A' }}</span></td>
            <td>{{ $product->seller->name ?? 'N/A' }}</td>
            <td>
              <span class="badge bg-{{ $product->compliance_status == 'approved' ? 'success' : ($product->compliance_status == 'flagged' || $product->compliance_status == 'auto_flagged' ? 'danger' : ($product->compliance_status == 'rejected' ? 'dark' : 'warning')) }} text-capitalize">
                {{ str_replace('_', ' ', $product->compliance_status) }}
              </span>
              @if($mismatch)
                <span class="badge bg-danger ms-1" title="Category not in seller's registered categories">Mismatch</span>
              @endif
            </td>
            <td>
              <small>{{ $product->updated_at->diffForHumans() }}</small>
            </td>
            <td>
              <div class="d-flex flex-column gap-1">
                <a href="{{ route('admin.compliance.show', $product) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Review</a>
                @if($isResubmitted)
                  <form method="POST" action="{{ route('admin.compliance.approve', $product) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success w-100" onclick="return confirm('Approve and publish this product to the seller\\'s shop?')">
                      <i class="bi bi-check-circle"></i> Approve
                    </button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No products to review.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
