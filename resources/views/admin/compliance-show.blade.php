@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Product Compliance Review</h1>
  <a href="{{ route('admin.compliance.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

@if($product->compliance_status === 'pending' && $product->updated_at && $product->updated_at->gt($product->created_at))
  <div class="alert alert-info">
    <i class="bi bi-arrow-clockwise"></i> <strong>This product was resubmitted by the seller</strong> for re-review on {{ $product->updated_at->format('M d, Y g:i A') }} ({{ $product->updated_at->diffForHumans() }}).
  </div>
@endif

<div class="row g-4">
  <div class="col-md-7">
    <div class="table-container mb-4">
      <h5 class="mb-3">Product Details</h5>
      <table class="table table-borderless">
        <tr><th class="w-40">Name</th><td>{{ $product->name }}</td></tr>
        <tr><th>Category</th><td>{{ $product->category->name ?? 'N/A' }}</td></tr>
        <tr><th>Seller</th><td>{{ $product->seller->name ?? 'N/A' }}</td></tr>
        <tr><th>Price</th><td>&#8369;{{ number_format($product->price, 2) }}</td></tr>
        <tr><th>Stock</th><td>{{ $product->stock }}</td></tr>
        <tr><th>Description</th><td>{{ $product->description ?? 'N/A' }}</td></tr>
        <tr>
          <th>Compliance</th>
          <td>
            <span class="badge bg-{{ $product->compliance_status == 'approved' ? 'success' : ($product->compliance_status == 'flagged' ? 'danger' : 'warning') }} text-capitalize">
              {{ $product->compliance_status }}
            </span>
            @if($isMismatch)
              <span class="badge bg-danger ms-1">Category Mismatch</span>
            @endif
          </td>
        </tr>
        @if($product->flagged_reason)
          <tr><th>Flag Reason</th><td class="text-danger">{{ $product->flagged_reason }}</td></tr>
        @endif
        @if($product->flagged_at)
          <tr>
            <th>Flagged On</th>
            <td>
              {{ $product->flagged_at->format('M d, Y g:i A') }}
              <span class="text-muted">({{ $product->flagged_at->diffForHumans() }})</span>
              <br><small class="text-muted">Seller has until <strong>{{ $product->flaggedDeadline()->format('M d, Y g:i A') }}</strong> to resubmit ({{ \App\Models\Product::RESUBMIT_DEADLINE_DAYS }} days).</small>
            </td>
          </tr>
        @endif
        @if($product->admin_notes)
          <tr><th>Admin Notes</th><td>{{ $product->admin_notes }}</td></tr>
        @endif
      </table>
    </div>

    <div class="table-container mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Product Images ({{ $product->images->count() }})</h5>
        <form method="POST" action="{{ route('admin.compliance.rescan', $product) }}" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-sm btn-outline-info" onclick="return confirm('Re-run all compliance scans (image content, brand IP, medical claims, etc.) on this product?')">
            <i class="bi bi-shield-check"></i> Re-scan All
          </button>
        </form>
      </div>
      @if($product->images->count() > 0)
        <div class="row g-2">
          @foreach($product->images as $img)
            <div class="col-md-4 col-6">
              <div class="position-relative border rounded">
                <img src="{{ $img->url }}" class="img-fluid" style="height:140px;width:100%;object-fit:cover;" alt="{{ $img->alt_text ?? $product->name }}">
                @if($img->is_primary)
                  <span class="badge bg-primary position-absolute top-0 start-0 m-1">Primary</span>
                @endif
                <button type="button" class="btn btn-sm btn-dark position-absolute top-0 end-0 m-1" onclick="document.getElementById('blacklist-form-{{ $img->id }}').classList.toggle('d-none')" title="Add to global blacklist">
                  <i class="bi bi-shield-x"></i>
                </button>
                <form id="blacklist-form-{{ $img->id }}" method="POST" action="{{ route('admin.compliance.blacklistImage', $product) }}" class="d-none mt-2 p-2 bg-light border-top">
                  @csrf
                  <input type="hidden" name="image_path" value="{{ $img->path }}">
                  <div class="mb-1">
                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason for blacklisting" required>
                  </div>
                  <button type="submit" class="btn btn-sm btn-danger w-100" onclick="return confirm('Blacklist this image globally? Future uploads matching it will be auto-flagged.')">
                    <i class="bi bi-shield-x"></i> Confirm Blacklist
                  </button>
                </form>
              </div>
            </div>
          @endforeach
        </div>
      @elseif($product->image)
        <img src="{{ asset('storage/' . $product->image) }}" class="img-fluid rounded border" alt="{{ $product->name }}">
      @else
        <p class="text-muted">No images uploaded.</p>
      @endif
    </div>
  </div>

  <div class="col-md-5">
    @if($product->compliance_status === 'approved')
      <div class="table-container mb-4">
        <h5 class="mb-3">Live on Platform</h5>
        <div class="alert alert-success mb-0">
          <i class="bi bi-check-circle"></i> This product is <strong>live</strong> on the platform and visible to buyers.
        </div>
        <p class="small text-muted mt-2">
          <i class="bi bi-info-circle"></i> Automated scans (image content, IP/brand detection, medical claims, returns, etc.) continuously monitor all approved listings. If any rule is violated, the product will be auto-flagged and you'll be notified.
        </p>
      </div>
    @elseif($product->compliance_status === 'pending' && $product->updated_at && $product->updated_at->gt($product->created_at))
      <div class="table-container mb-4 border-info">
        <h5 class="mb-3"><i class="bi bi-arrow-clockwise text-info"></i> Resubmitted - Needs Review</h5>
        <div class="alert alert-info mb-3">
          <strong>Action Required:</strong> The seller has updated this product after it was flagged. Please review the changes and approve to republish the product in the seller's shop, or flag it again if issues remain.
        </div>
        <form method="POST" action="{{ route('admin.compliance.approve', $product) }}" class="mb-2">
          @csrf
          <button type="submit" class="btn btn-success w-100" onclick="return confirm('Approve this product? It will be published live in the seller\\'s shop.')">
            <i class="bi bi-check-circle"></i> Approve & Publish to Shop
          </button>
        </form>
        <form method="POST" action="{{ route('admin.compliance.flag', $product) }}" onsubmit="return confirm('Flag this product again?');">
          @csrf
          <div class="mb-2">
            <label class="form-label">Re-Flag Reason <span class="text-danger">*</span></label>
            <textarea name="flagged_reason" class="form-control" rows="2" required placeholder="Why is this still a violation?"></textarea>
          </div>
          <div class="mb-2">
            <label class="form-label">Instructions for Seller</label>
            <textarea name="admin_notes" class="form-control" rows="3" placeholder="What else needs to be fixed?"></textarea>
          </div>
          <button type="submit" class="btn btn-danger w-100"><i class="bi bi-flag"></i> Re-Flag Product</button>
        </form>
      </div>
    @else
      <div class="table-container mb-4">
        <h5 class="mb-3">Compliance Actions</h5>
        <form method="POST" action="{{ route('admin.compliance.approve', $product) }}" class="mb-2">
          @csrf
          <button type="submit" class="btn btn-success w-100" onclick="return confirm('Mark as compliant?')">
            <i class="bi bi-check-circle"></i> Approve & Publish
          </button>
        </form>
        <form method="POST" action="{{ route('admin.compliance.flag', $product) }}" onsubmit="return confirm('Flag this product?');">
          @csrf
          <div class="mb-2">
            <label class="form-label">Flag Reason <span class="text-danger">*</span></label>
            <textarea name="flagged_reason" class="form-control" rows="2" required placeholder="Brief reason for the violation"></textarea>
          </div>
          <div class="mb-2">
            <label class="form-label">Instructions for Seller</label>
            <textarea name="admin_notes" class="form-control" rows="3" placeholder="What should the seller fix or update?"></textarea>
            <small class="text-muted">Optional. Clear instructions help the seller resolve the issue faster.</small>
          </div>
          <button type="submit" class="btn btn-danger w-100"><i class="bi bi-flag"></i> Flag Product</button>
        </form>
      </div>
    @endif

    @if($product->seller)
      <div class="table-container mb-4">
        <h5 class="mb-3">Seller Violations</h5>
        <form method="POST" action="{{ route('admin.compliance.warn', $product) }}" class="mb-2" onsubmit="return confirm('Issue warning to seller?');">
          @csrf
          <div class="mb-2">
            <select name="type" class="form-select mb-2">
              <option value="product_violation">Product Violation</option>
              <option value="policy_violation">Policy Violation</option>
              <option value="other">Other</option>
            </select>
            <textarea name="reason" class="form-control" rows="2" placeholder="Warning reason" required></textarea>
          </div>
          <button type="submit" class="btn btn-warning w-100"><i class="bi bi-exclamation-triangle"></i> Issue Warning</button>
        </form>
        <form method="POST" action="{{ route('admin.compliance.suspendSeller', $product) }}" onsubmit="return confirm('Suspend this seller account?');">
          @csrf
          <button type="submit" class="btn btn-dark w-100 mt-2"><i class="bi bi-slash-circle"></i> Suspend Seller</button>
        </form>
      </div>

      <div class="table-container">
        <h5 class="mb-3">Previous Warnings</h5>
        @forelse($warnings as $w)
          <div class="border rounded p-2 mb-2">
            <div class="d-flex justify-content-between">
              <strong class="text-capitalize">{{ str_replace('_', ' ', $w->type) }}</strong>
              <small class="text-muted">{{ $w->created_at->format('M d, Y') }}</small>
            </div>
            <p class="mb-0 small">{{ $w->reason }}</p>
          </div>
        @empty
          <p class="text-muted mb-0">No warnings issued.</p>
        @endforelse
      </div>
    @endif
  </div>
</div>
@endsection
