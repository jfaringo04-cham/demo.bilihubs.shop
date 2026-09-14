@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Report Product</h2>
    <a href="{{ route('products.show', $product) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <div class="card" style="max-width: 700px;">
    <div class="card-body">
      <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
        <img src="{{ $product->image ? asset('storage/' . $product->image) : 'https://via.placeholder.com/80x80?text=No+Image' }}" class="rounded me-3" style="width: 80px; height: 80px; object-fit: cover;" alt="{{ $product->name }}">
        <div>
          <h5 class="mb-1">{{ $product->name }}</h5>
          <small class="text-muted">Sold by: {{ $product->seller->business_name ?? $product->seller->name ?? 'Unknown' }}</small>
        </div>
      </div>

      <p class="text-muted">Help us keep Bili Hub safe. Tell us what's wrong with this listing.</p>

      <form method="POST" action="{{ route('buyer.report-product.store', $product) }}">
        @csrf
        <div class="mb-3">
          <label for="reason" class="form-label">Reason for Report <span class="text-danger">*</span></label>
          <select name="reason" id="reason" class="form-select" required>
            <option value="">Select a reason</option>
            <option value="scam" {{ old('reason') == 'scam' ? 'selected' : '' }}>Scam or fraud</option>
            <option value="offensive" {{ old('reason') == 'offensive' ? 'selected' : '' }}>Offensive or inappropriate content</option>
            <option value="misleading" {{ old('reason') == 'misleading' ? 'selected' : '' }}>Misleading description or images</option>
            <option value="copyright" {{ old('reason') == 'copyright' ? 'selected' : '' }}>Copyright or trademark violation</option>
            <option value="other" {{ old('reason') == 'other' ? 'selected' : '' }}>Other policy violation</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="details" class="form-label">Details <span class="text-danger">*</span></label>
          <textarea name="details" id="details" class="form-control" rows="5" required placeholder="Please describe the issue in detail...">{{ old('details') }}</textarea>
          <small class="text-muted">Your report will be reviewed by our compliance team. Repeated reports may auto-flag this product.</small>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-flag"></i> Submit Report
          </button>
          <a href="{{ route('products.show', $product) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
