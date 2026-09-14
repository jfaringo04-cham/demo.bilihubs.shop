@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Add New Product</h1>
  <a href="{{ route('seller.products') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="table-container mx-auto" style="max-width: 900px;">
  <form method="POST" action="{{ route('seller.products.store') }}" enctype="multipart/form-data">
    @csrf

    <h5 class="mb-3">Basic Information</h5>
    <div class="mb-3">
      <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
      <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required>
      @error('name') <div class="text-danger">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
      <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
      <select name="category_id" id="category_id" class="form-select" required {{ empty($allowedCategoryIds) ? 'disabled' : '' }}>
        <option value="">Select Category</option>
        @foreach($categories as $category)
          <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
        @endforeach
      </select>
      @if(empty($allowedCategoryIds))
        <div class="text-danger mt-2">You have no selling categories set. Please contact admin support to assign categories to your seller account before adding products.</div>
      @endif
      @error('category_id') <div class="text-danger">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
      <label for="description" class="form-label">Description</label>
      <textarea name="description" id="description" class="form-control" rows="4">{{ old('description') }}</textarea>
      @error('description') <div class="text-danger">{{ $message }}</div> @enderror
    </div>

    <hr>
    <h5 class="mb-3">Product Video <span class="text-muted small">(Optional)</span></h5>
    <div class="mb-3">
      <label for="video" class="form-label">Upload Product Video</label>
      <input type="file" name="video" id="video" class="form-control" accept="video/*">
      <small class="text-muted">Upload a short video showcasing your product. Max 10MB. Supported formats: mp4, mov, avi, webm.</small>
      @error('video') <div class="text-danger">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
      <label for="secondary_image" class="form-label">Additional Image <span class="text-muted small">(Optional)</span></label>
      <input type="file" name="secondary_image" id="secondary_image" class="form-control" accept="image/*">
      <small class="text-muted">Upload an additional image (infographic, banner, lifestyle shot, etc.). Max 2MB. JPG, PNG, GIF.</small>
      @error('secondary_image') <div class="text-danger">{{ $message }}</div> @enderror
    </div>

    <hr>
    <h5 class="mb-3">Product Images</h5>
    <div class="mb-3">
      <label for="images" class="form-label">Upload Multiple Images <span class="text-danger">*</span></label>
      <input type="file" name="images[]" id="images" class="form-control" accept="image/*" multiple required>
      <small class="text-muted">Hold Ctrl to select multiple images. The first image will be the main product image, and you can set a different price per image below.</small>
      @error('images') <div class="text-danger">{{ $message }}</div> @enderror
      @error('images.*') <div class="text-danger">{{ $message }}</div> @enderror
    </div>

    <div id="image-price-list" class="mb-3"></div>

    <hr>
    <h5 class="mb-3">Discount / Promo <span class="text-muted small">(Optional)</span></h5>
    <p class="text-muted small">Set a discount to attract buyers. Leave blank to sell at the regular price.</p>
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <label for="discount_percent" class="form-label">Discount %</label>
        <div class="input-group">
          <input type="number" name="discount_percent" id="discount_percent" class="form-control" min="0" max="95" step="0.01" value="{{ old('discount_percent') }}" placeholder="e.g. 20">
          <span class="input-group-text">%</span>
        </div>
        <small class="text-muted">0 = no discount, 20 = 20% off</small>
      </div>
      <div class="col-md-4">
        <label for="discount_starts_at" class="form-label">Starts At</label>
        <input type="datetime-local" name="discount_starts_at" id="discount_starts_at" class="form-control" value="{{ old('discount_starts_at') }}">
      </div>
      <div class="col-md-4">
        <label for="discount_ends_at" class="form-label">Ends At</label>
        <input type="datetime-local" name="discount_ends_at" id="discount_ends_at" class="form-control" value="{{ old('discount_ends_at') }}">
      </div>
    </div>
    <div class="alert alert-info py-2 small" id="discount-preview" style="display:none;">
      <i class="bi bi-tag-fill"></i> <span id="discount-preview-text"></span>
    </div>

    <hr>
    <h5 class="mb-3">Available Sizes <span class="text-muted small">(Optional — skip if your product doesn't come in sizes)</span></h5>
    <p class="text-muted small">Pick one size for each image below. Buyers will only see the size dropdown if you select at least one size here.</p>

    <button type="submit" class="btn btn-bili-hub"><i class="bi bi-save"></i> Save Product</button>
  </form>
</div>
@endsection

@push('scripts')
<script>
function updateDiscountPreview() {
  const pct = parseFloat(document.getElementById('discount_percent')?.value || 0);
  const preview = document.getElementById('discount-preview');
  const text = document.getElementById('discount-preview-text');
  if (pct > 0) {
    const prices = document.querySelectorAll('input[name^="image_variations"][name$="[price]"]');
    if (prices.length > 0) {
      let firstPrice = parseFloat(prices[0].value || 0);
      if (firstPrice > 0) {
        const discounted = (firstPrice * (1 - pct / 100)).toFixed(2);
        text.textContent = `${pct}% off → first image will sell at ₱${discounted} (was ₱${firstPrice.toFixed(2)}). Buyers save ₱${(firstPrice - discounted).toFixed(2)}.`;
        preview.style.display = 'block';
        return;
      }
    }
    text.textContent = `${pct}% discount will be applied to the price of each image/variation.`;
    preview.style.display = 'block';
  } else {
    preview.style.display = 'none';
  }
}

document.getElementById('discount_percent')?.addEventListener('input', updateDiscountPreview);
document.addEventListener('input', function(e) {
  if (e.target.name && e.target.name.startsWith('image_variations') && e.target.name.endsWith('[price]')) {
    updateDiscountPreview();
  }
});

const availableSizes = @json($sizes);

document.getElementById('images').addEventListener('change', function(e) {
  const list = document.getElementById('image-price-list');
  list.innerHTML = '';
  const files = Array.from(e.target.files);
  if (files.length === 0) return;

  const heading = document.createElement('h6');
  heading.className = 'mt-3 mb-2';
  heading.textContent = 'Set a price and size for each image (each becomes a buyable option)';
  list.appendChild(heading);

  files.forEach((file, idx) => {
    const row = document.createElement('div');
    row.className = 'card mb-2';
    const sizeOptions = availableSizes.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    row.innerHTML = `
      <div class="card-body py-2 px-3">
        <div class="d-flex align-items-center gap-3">
          <img src="${URL.createObjectURL(file)}" style="width:60px;height:60px;object-fit:cover;border-radius:4px;" alt="preview">
          <div class="flex-grow-1">
            <div class="small text-muted">Image #${idx + 1} ${idx === 0 ? '(Primary)' : ''}</div>
            <div class="fw-bold">${file.name}</div>
          </div>
          <div style="min-width: 240px;">
            <div class="row g-1">
              <div class="col-7">
                <input type="text" name="image_variations[${idx}][name]" class="form-control form-control-sm" placeholder="Variation name" value="${file.name.replace(/\.[^.]+$/, '')}" required>
              </div>
              <div class="col-5">
                <input type="number" name="image_variations[${idx}][price]" class="form-control form-control-sm" placeholder="Price" step="0.01" min="0" required>
              </div>
            </div>
            <div class="row g-1 mt-1">
              <div class="col-7">
                <select name="image_variations[${idx}][size_id]" class="form-select form-select-sm">
                  <option value="">No Size</option>
                  ${sizeOptions}
                </select>
              </div>
              <div class="col-5">
                <input type="number" name="image_variations[${idx}][stock]" class="form-control form-control-sm" placeholder="Stock" min="0" value="1" required>
              </div>
            </div>
            <div class="row g-1 mt-1">
              <div class="col-7">
                <input type="text" name="image_variations[${idx}][sku]" class="form-control form-control-sm" placeholder="SKU (optional)">
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
    list.appendChild(row);
  });
});
</script>
@endpush
