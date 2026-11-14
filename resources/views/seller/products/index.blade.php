@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">My Products</h1>

  <a href="{{ route('seller.products.create') }}" class="btn btn-bili-hub">
    <i class="bi bi-plus-circle"></i> Add Product
  </a>
</div>

<div class="table-container">

  {{-- Search and Category Filter --}}
  <form method="GET" action="{{ route('seller.products') }}" class="row g-3 mb-3">
    <div class="col-md-4">
      <input
        type="text"
        name="search"
        class="form-control"
        placeholder="Search products..."
        value="{{ request('search') }}">
    </div>

    <div class="col-md-3">
      <select name="category" class="form-select">
        <option value="">All Categories</option>

        @foreach($categories as $category)
        <option
          value="{{ $category->id }}"
          {{ request('category') == $category->id ? 'selected' : '' }}>
          {{ $category->name }}
        </option>
        @endforeach
      </select>
    </div>

    <div class="col-md-3">
      <select name="listing" class="form-select" aria-label="Listing filter">
        <option value="current">Current listings</option>
        <option value="archived" @selected(request('listing') === 'archived')>Archived</option>
      </select>
    </div>

    <div class="col-md-2">
      <button type="submit" class="btn btn-outline-secondary w-100">
        Filter
      </button>
    </div>
  </form>

  {{-- Products Table --}}
  <div class="table-responsive">
    <table class="table table-hover align-middle">

      <thead class="table-light">
        <tr>
          <th>Product</th>
          <th>Category</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Listing Status</th>
          <th>Compliance</th>
          <th>Actions</th>
        </tr>
      </thead>

      <tbody>
        @forelse($products as $product)

        @php
        $isDraft = $product->status === 'draft';
        $isArchived = $product->status === \App\Models\Product::STATUS_ARCHIVED;
        $isPublished = $product->status === 'published';
        $compliance = $product->compliance_status;

        $isFlagged = in_array(
        $compliance,
        ['flagged', 'auto_flagged'],
        true
        );

        $isLive = $isPublished && $compliance === 'approved';

        $rowClass = '';

        if (!$isDraft && $isFlagged) {
        $rowClass = 'table-danger';
        } elseif (!$isDraft && $compliance === 'pending') {
        $rowClass = 'table-warning';
        }
        @endphp

        <tr class="{{ $rowClass }}">

          {{-- Product --}}
          <td>
            <div class="d-flex align-items-center">
              <img
                src="{{ $product->image_url }}"
                class="rounded me-2"
                style="width: 50px; height: 50px; object-fit: cover;"
                alt="{{ $product->name }}">

              <div>
                <strong>{{ $product->name }}</strong>

                @if($isFlagged && $product->flagged_reason)
                <div class="mt-1 p-2 bg-light border border-danger rounded">

                  <small class="text-danger fw-bold">
                    <i class="bi bi-exclamation-triangle"></i>
                    Admin Flag Reason:
                  </small>

                  <div class="small text-dark">
                    {{ $product->flagged_reason }}
                  </div>

                  @if($product->admin_notes)
                  <div class="mt-1 small text-muted">
                    <i class="bi bi-chat-left-text"></i>
                    Note: {{ $product->admin_notes }}
                  </div>
                  @endif

                  <small class="text-muted d-block mt-1">
                    Please update your product and click
                    "Resubmit for Review".
                  </small>
                </div>
                @endif
              </div>
            </div>
          </td>

          {{-- Category --}}
          <td>
            {{ $product->category->name ?? 'Uncategorized' }}
          </td>

          {{-- Price --}}
          <td class="text-danger fw-bold">
            &#8369;{{ number_format(($product->price_minor / 100), 2) }}
          </td>

          {{-- Stock --}}
          <td>
            {{ $product->stock }}

            @if($product->sizes->count() > 0)
            <br>

            <small class="text-muted">
              @foreach($product->sizes as $size)
              {{ $size->name }}({{ $size->pivot->stock }})@if(!$loop->last), @endif
              @endforeach
            </small>
            @endif
          </td>

          {{-- Listing Status --}}
          <td>
            @if($isArchived)
            <span class="badge bg-secondary">Archived</span>
            @elseif($isDraft)
            <span class="badge bg-secondary">
              <i class="bi bi-file-earmark"></i>
              Draft
            </span>

            @elseif($isLive)
            <span class="badge bg-success">
              <i class="bi bi-check-circle-fill"></i>
              Live
            </span>

            @elseif($isPublished && $compliance === 'pending')
            <span class="badge bg-warning text-dark">
              <i class="bi bi-hourglass-split"></i>
              Awaiting Approval
            </span>

            @elseif($isFlagged)
            <span class="badge bg-danger">
              <i class="bi bi-eye-slash"></i>
              Hidden
            </span>

            @elseif($compliance === 'rejected')
            <span class="badge bg-dark">
              <i class="bi bi-x-circle"></i>
              Rejected
            </span>

            @else
            <span class="badge bg-secondary">
              {{ ucfirst($product->status ?? 'Unknown') }}
            </span>
            @endif
          </td>

          {{-- Compliance Status --}}
          <td>
            @if($isDraft)

            <span class="badge bg-light text-secondary border">
              <i class="bi bi-lock"></i>
              Not Submitted
            </span>

            <div class="small text-muted mt-1">
              Stored compliance:
              {{ ucfirst($compliance ?? 'pending') }}
            </div>

            @elseif($compliance === 'approved')

            <span class="badge bg-success">
              <i class="bi bi-check-circle"></i>
              Approved
            </span>

            @elseif($isFlagged)

            <span class="badge bg-danger">
              <i class="bi {{ $compliance === 'auto_flagged' ? 'bi-robot' : 'bi-flag-fill' }}"></i>

              {{ $compliance === 'auto_flagged' ? 'Auto-Flagged' : 'Flagged' }}
            </span>

            @php
            $daysLeft = $product->daysUntilResubmitDeadline();
            $deadlinePassed = $product->isResubmitDeadlinePassed();
            @endphp

            <div class="mt-1 small">
              @if($deadlinePassed)

              <span class="text-danger">
                <i class="bi bi-exclamation-octagon"></i>
                <strong>Deadline passed</strong>
              </span>

              <br>

              <span class="text-muted">
                Contact admin to restore.
              </span>

              @else

              <span class="text-warning">
                <i class="bi bi-hourglass-split"></i>
                <strong>{{ $daysLeft }} day(s) left</strong>
                to resubmit
              </span>

              @endif
            </div>

            @if(!$deadlinePassed)
            <div class="mt-1">
              <a
                href="{{ route('seller.products.edit', $product) }}"
                class="btn btn-sm btn-warning">
                <i class="bi bi-arrow-clockwise"></i>
                Fix & Resubmit
              </a>
            </div>
            @endif

            @elseif($compliance === 'pending')

            <span class="badge bg-warning text-dark">
              <i class="bi bi-hourglass-split"></i>
              Pending Review
            </span>

            @elseif($compliance === 'rejected')

            <span class="badge bg-dark">
              <i class="bi bi-x-octagon"></i>
              Auto-Rejected
            </span>

            <div class="mt-1 small text-muted">
              Deadline expired. Contact admin to restore.
            </div>

            @else

            <span class="badge bg-secondary">
              {{ ucfirst($compliance ?? 'Unknown') }}
            </span>

            @endif
          </td>

          {{-- Actions --}}
          <td>
            <div class="d-flex flex-wrap gap-2">
              @if($isArchived)
                @if(in_array($compliance, ['approved', 'pending'], true))
                  <form action="{{ route('seller.products.update', $product) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="listing_action" value="restore">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Restore as Draft</button>
                  </form>
                @else
                  <span class="text-muted small">Contact support to restore</span>
                @endif
              @else
                <a href="{{ route('seller.products.edit', $product) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                <form action="{{ route('seller.products.destroy', $product) }}" method="POST"
                      onsubmit="return confirm('Archive this product and hide it from buyers?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-outline-secondary">Archive</button>
                </form>
                @if($isDraft && $compliance === 'pending')
                  <form action="{{ route('seller.products.destroy', $product) }}" method="POST"
                        onsubmit="return confirm('Permanently delete this draft?')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="delete_draft" value="1">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete Draft</button>
                  </form>
                @endif
              @endif
            </div>
          </td>

        </tr>

        @empty

        <tr>
          <td colspan="7" class="text-center text-muted py-4">
            No products found.
          </td>
        </tr>

        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Pagination --}}
  <div class="mt-3">
    {{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}
  </div>

</div>
@endsection