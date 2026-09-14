@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Edit Product</h1>
  <a href="{{ route('seller.products') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

@if(in_array($product->compliance_status, ['flagged', 'auto_flagged']))
  @php
    $daysLeft = $product->daysUntilResubmitDeadline();
    $deadlinePassed = $product->isResubmitDeadlinePassed();
  @endphp
  <div class="modal fade" id="flaggedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header {{ $deadlinePassed ? 'bg-danger' : 'bg-danger' }} text-white">
          <h5 class="modal-title">
            <i class="bi {{ $product->compliance_status == 'auto_flagged' ? 'bi-robot' : 'bi-flag-fill' }}"></i>
            Product {{ $product->compliance_status == 'auto_flagged' ? 'Auto-Flagged by System' : 'Flagged by Admin' }}
          </h5>
        </div>
        <div class="modal-body">
          @if($product->flagged_reason)
            <p class="mb-1"><strong>Reason:</strong> {{ $product->flagged_reason }}</p>
          @endif
          @if($product->admin_notes)
            <p class="mb-1"><strong>Admin Notes:</strong> {!! nl2br(e($product->admin_notes)) !!}</p>
          @endif
          @if($deadlinePassed)
            <p class="mb-0 mt-2"><strong><i class="bi bi-exclamation-octagon"></i> Deadline Passed:</strong> The 7-day resubmission window has expired. You can no longer update this product. Please contact admin support to restore it.</p>
          @else
            <p class="mb-0 mt-2"><strong>⏰ Resubmission Deadline:</strong> You have <strong>{{ $daysLeft }} day(s) left</strong> to fix the issues and resubmit (deadline: {{ $product->flaggedDeadline()->format('M d, Y g:i A') }}). After this, the product can only be restored by admin support.</p>
            <p class="mb-0 mt-2"><strong>Action Required:</strong> Please fix the issues mentioned above, update the product below, and save. The product will be automatically resubmitted for admin review.</p>
          @endif
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">I Understand</button>
        </div>
      </div>
    </div>
  </div>
@elseif($product->compliance_status === 'pending')
  <div class="alert alert-warning" style="max-width: 800px;">
    <i class="bi bi-hourglass-split"></i> This product is currently pending admin review.
  </div>
@endif

<div class="table-container mx-auto" style="max-width: 800px;">
  @if($product->compliance_status === 'flagged' && $deadlinePassed)
    <div class="text-center py-4">
      <i class="bi bi-lock-fill display-1 text-muted"></i>
      <h4 class="mt-3">Editing Locked</h4>
      <p class="text-muted">The 7-day resubmission deadline has passed. This product can no longer be edited.</p>
      <a href="{{ route('seller.products') }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Products</a>
    </div>
  @else
  <form method="POST" action="{{ route('seller.products.update', $product) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="mb-3">
      <label for="name" class="form-label">Product Name</label>
      <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $product->name) }}" required>
      @error('name') <div class="text-danger">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
      <label for="category_id" class="form-label">Category</label>
      <select name="category_id" id="category_id" class="form-select" required {{ empty($allowedCategoryIds) ? 'disabled' : '' }}>
        <option value="">Select Category</option>
        @foreach($categories as $category)
          <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
        @endforeach
      </select>
      @if(empty($allowedCategoryIds))
        <div class="text-danger mt-2">You have no selling categories set. Please contact admin support to assign categories to your seller account.</div>
      @endif
      @error('category_id') <div class="text-danger">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
      <label for="description" class="form-label">Description</label>
      <textarea name="description" id="description" class="form-control" rows="4">{{ old('description', $product->description) }}</textarea>
      @error('description') <div class="text-danger">{{ $message }}</div> @enderror
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="price" class="form-label">Price ($)</label>
        <input type="number" name="price" id="price" class="form-control" value="{{ old('price', $product->price) }}" step="0.01" min="0" required>
        @error('price') <div class="text-danger">{{ $message }}</div> @enderror
      </div>
      <div class="col-md-6 mb-3">
        <label for="stock" class="form-label">Stock</label>
        <input type="number" name="stock" id="stock" class="form-control" value="{{ old('stock', $product->stock) }}" min="0" required>
        @error('stock') <div class="text-danger">{{ $message }}</div> @enderror
      </div>
    </div>
    <hr>
    <h5 class="mb-3">Discount / Promo <span class="text-muted small">(Optional)</span></h5>
    <p class="text-muted small">Set a discount to attract buyers. Leave blank to sell at the regular price.</p>
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <label for="discount_percent" class="form-label">Discount %</label>
        <div class="input-group">
          <input type="number" name="discount_percent" id="discount_percent" class="form-control" min="0" max="95" step="0.01" value="{{ old('discount_percent', $product->discount_percent) }}" placeholder="e.g. 20">
          <span class="input-group-text">%</span>
        </div>
        <small class="text-muted">0 = no discount, 20 = 20% off</small>
      </div>
      <div class="col-md-4">
        <label for="discount_starts_at" class="form-label">Starts At</label>
        <input type="datetime-local" name="discount_starts_at" id="discount_starts_at" class="form-control" value="{{ old('discount_starts_at', $product->discount_starts_at ? $product->discount_starts_at->format('Y-m-d\TH:i') : '') }}">
      </div>
      <div class="col-md-4">
        <label for="discount_ends_at" class="form-label">Ends At</label>
        <input type="datetime-local" name="discount_ends_at" id="discount_ends_at" class="form-control" value="{{ old('discount_ends_at', $product->discount_ends_at ? $product->discount_ends_at->format('Y-m-d\TH:i') : '') }}">
      </div>
    </div>
    <div class="alert alert-info py-2 small" id="discount-preview" style="display:none;">
      <i class="bi bi-tag-fill"></i> <span id="discount-preview-text"></span>
    </div>

    <!-- Product Video -->
    <div class="mb-3">
      <label for="video" class="form-label">Product Video <span class="text-muted small">(Optional)</span></label>
      @if($product->video_path)
        <div class="mb-2">
          <video controls class="rounded shadow-sm" style="max-width: 100%; max-height: 200px;">
            <source src="{{ asset('storage/' . $product->video_path) }}" type="video/mp4">
            Your browser does not support the video tag.
          </video>
          <div class="mt-2">
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#removeVideoModal">
              <i class="bi bi-trash"></i> Remove Video
            </button>
          </div>
        </div>
      @endif
      <input type="file" name="video" id="video" class="form-control" accept="video/*">
      <small class="text-muted">Upload a new video to replace the current one. Max 10MB. Supported formats: mp4, mov, avi, webm.</small>
      @error('video') <div class="text-danger">{{ $message }}</div> @enderror
    </div>

    <!-- Product Secondary Image -->
    <div class="mb-3">
      <label for="secondary_image" class="form-label">Additional Image <span class="text-muted small">(Optional)</span></label>
      @if($product->secondary_image_path)
        <div class="mb-2">
          <img src="{{ asset('storage/' . $product->secondary_image_path) }}" class="img-fluid rounded shadow-sm" style="max-height: 200px;" alt="Secondary image">
          <div class="mt-2">
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#removeSecondaryImageModal">
              <i class="bi bi-trash"></i> Remove Image
            </button>
          </div>
        </div>
      @endif
      <input type="file" name="secondary_image" id="secondary_image" class="form-control" accept="image/*">
      <small class="text-muted">Upload a new image to replace the current one. Max 2MB. JPG, PNG, GIF.</small>
      @error('secondary_image') <div class="text-danger">{{ $message }}</div> @enderror
    </div>

    <hr>



    <div class="mb-3">
      <label class="form-label">Price per Image (Buyable Options)</label>
      <p class="text-muted small">Each image is a buyable option. Edit the price, name, and stock of each option below. Click <strong>Remove</strong> on any row to delete that image and its variation on save.</p>
      @if($product->images->count() > 0)
        <div class="table-responsive">
          <table class="table table-bordered align-middle" id="image-variations-table">
            <thead class="table-light">
              <tr>
                <th style="width:80px;">Image</th>
                <th>Variation Name</th>
                <th style="width:130px;">Price</th>
                <th style="width:100px;">Stock</th>
                <th>SKU (optional)</th>
                <th style="width:120px;" class="text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($product->images as $img)
                @php
                  $linkedVar = $product->variations->firstWhere('image_id', $img->id);
                @endphp
                <tr id="image-row-{{ $img->id }}">
                  <td>
                    <img src="{{ $img->url }}" style="width:60px;height:60px;object-fit:cover;border-radius:4px;" alt="">
                    <input type="hidden" name="image_variations[{{ $img->id }}][image_id]" value="{{ $img->id }}">
                  </td>
                  <td>
                    <input type="text" name="image_variations[{{ $img->id }}][name]" class="form-control form-control-sm" value="{{ old('image_variations.' . $img->id . '.name', $linkedVar->name ?? $product->name) }}" required>
                  </td>
                  <td>
                    <input type="number" name="image_variations[{{ $img->id }}][price]" class="form-control form-control-sm" step="0.01" min="0" value="{{ old('image_variations.' . $img->id . '.price', $linkedVar->price ?? $product->price) }}" required>
                  </td>
                  <td>
                    <input type="number" name="image_variations[{{ $img->id }}][stock]" class="form-control form-control-sm" min="0" value="{{ old('image_variations.' . $img->id . '.stock', $linkedVar->stock ?? 1) }}" required>
                  </td>
                  <td>
                    <input type="text" name="image_variations[{{ $img->id }}][sku]" class="form-control form-control-sm" value="{{ old('image_variations.' . $img->id . '.sku', $linkedVar->sku ?? '') }}">
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="markRemoveImage({{ $img->id }}, '{{ addslashes($img->alt_text ?? $product->name) }}')" title="Remove this image and its variation">
                      <i class="bi bi-trash"></i> Remove
                    </button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div id="remove-inputs"></div>
      @endif
      <div id="new-image-price-list"></div>
      <label for="new_images" class="form-label mt-2">Add more images (new options will appear below)</label>
      <input type="file" name="new_images[]" id="new_images" class="form-control" accept="image/*" multiple>
      <small class="text-muted">You can upload multiple images (jpeg, png, jpg, gif). Max 2MB each.</small>
      @error('new_images') <div class="text-danger">{{ $message }}</div> @enderror
      @error('new_images.*') <div class="text-danger">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
      <label class="form-label">Sizes</label>
      <div class="row">
        @foreach($sizes as $size)
          @php
            $hasSize = $product->sizes->contains('id', $size->id);
            $sizeStock = $hasSize ? $product->sizes->firstWhere('id', $size->id)->pivot->stock : 0;
          @endphp
          <div class="col-md-4 mb-2">
            <div class="form-check">
              <input class="form-check-input size-checkbox" type="checkbox" name="sizes[]" value="{{ $size->id }}" id="size_{{ $size->id }}" {{ $hasSize ? 'checked' : '' }}>
              <label class="form-check-label" for="size_{{ $size->id }}">
                {{ $size->name }}
              </label>
            </div>
            <input type="number" name="size_stock[{{ $size->id }}]" class="form-control form-control-sm mt-1 size-stock-input" placeholder="Stock" min="0" value="{{ old('size_stock.' . $size->id, $sizeStock) }}" {{ $hasSize ? '' : 'disabled' }}>
          </div>
        @endforeach
      </div>
      @error('sizes') <div class="text-danger">{{ $message }}</div> @enderror
      @error('size_stock') <div class="text-danger">{{ $message }}</div> @enderror
    </div>
     <input type="hidden" name="remove_video" id="remove-video-input" value="">
     <input type="hidden" name="remove_secondary_image" id="remove-secondary-image-input" value="">
    <div class="d-flex justify-content-between align-items-center mt-4">
      <button type="submit" class="btn btn-bili-hub"><i class="bi bi-save"></i> Update Product</button>
      <button type="button" class="btn btn-outline-danger" onclick="confirmRemoveProduct({{ $product->id }}, '{{ addslashes($product->name) }}')">
        <i class="bi bi-trash"></i> Remove Product
      </button>
    </div>
    </form>
  @endif
  </div>

@if($product->video_path)
<div class="modal fade" id="removeVideoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Remove Product Video</h5>
      </div>
      <div class="modal-body">
        Are you sure you want to permanently remove this product video?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-danger" onclick="removeVideoAndSubmit()">Yes, Remove Video</button>
      </div>
    </div>
  </div>
</div>
@endif

@if($product->secondary_image_path)
<div class="modal fade" id="removeSecondaryImageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Remove Additional Image</h5>
      </div>
      <div class="modal-body">
        Are you sure you want to permanently remove this additional image?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-danger" onclick="removeSecondaryImageAndSubmit()">Yes, Remove Image</button>
      </div>
    </div>
  </div>
</div>
@endif

<form id="remove-product-form-{{ $product->id }}" method="POST" action="{{ route('seller.products.destroy', $product) }}" style="display:none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
  function removeVideoAndSubmit() {
    document.getElementById('remove-video-input').value = '1';
    var btn = document.querySelector('button[type="submit"]');
    if (btn) btn.click();
  }

  function removeSecondaryImageAndSubmit() {
    document.getElementById('remove-secondary-image-input').value = '1';
    var btn = document.querySelector('button[type="submit"]');
    if (btn) btn.click();
  }

  function syncSizeStockState() {
    document.querySelectorAll('.size-checkbox').forEach(function(cb) {
      const stockInput = cb.closest('.col-md-4').querySelector('.size-stock-input');
      if (stockInput) {
        stockInput.disabled = !cb.checked;
      }
    });
  }
  syncSizeStockState();

  document.querySelectorAll('.size-checkbox').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
      const stockInput = this.closest('.col-md-4').querySelector('.size-stock-input');
      stockInput.disabled = !this.checked;
      if (!this.checked) {
        stockInput.value = 0;
      }
    });
  });

  function markRemoveImage(id, name) {
    if (!confirm('Remove "' + name + '" and its variation? This will be saved when you click Update Product.')) {
      return;
    }
    const row = document.getElementById('image-row-' + id);
    if (row) {
      row.style.display = 'none';
      row.querySelectorAll('input').forEach(function(input) {
        input.disabled = true;
      });
    }
    const container = document.getElementById('remove-inputs');
    if (!container) return;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'remove_images[]';
    input.value = id;
    container.appendChild(input);
  }

  function confirmRemoveProduct(id, name) {
    if (!confirm('Are you sure you want to permanently remove "' + name + '"?\n\nThis will also delete all of its images, variations, and remove it from your shop. This action cannot be undone.')) {
      return;
    }
    document.getElementById('remove-product-form-' + id).submit();
  }

  document.getElementById('new_images')?.addEventListener('change', function(e) {
    const list = document.getElementById('new-image-price-list');
    list.innerHTML = '';
    const files = Array.from(e.target.files);
    if (files.length === 0) return;

    const heading = document.createElement('h6');
    heading.className = 'mt-3 mb-2';
    heading.textContent = 'Set price for new images (each becomes a buyable option)';
    list.appendChild(heading);

    files.forEach((file, idx) => {
      const row = document.createElement('div');
      row.className = 'card mb-2';
      row.innerHTML = `
        <div class="card-body py-2 px-3">
          <div class="d-flex align-items-center gap-3">
            <img src="${URL.createObjectURL(file)}" style="width:60px;height:60px;object-fit:cover;border-radius:4px;" alt="preview">
            <div class="flex-grow-1">
              <div class="small text-muted">New Image #${idx + 1}</div>
              <div class="fw-bold">${file.name}</div>
            </div>
            <div style="min-width: 240px;">
              <div class="row g-1">
                <div class="col-7">
                  <input type="text" name="new_image_variations[${idx}][name]" class="form-control form-control-sm" placeholder="Variation name" value="${file.name.replace(/\.[^.]+$/, '')}" required>
                </div>
                <div class="col-5">
                  <input type="number" name="new_image_variations[${idx}][price]" class="form-control form-control-sm" placeholder="Price" step="0.01" min="0" required>
                </div>
              </div>
              <div class="row g-1 mt-1">
                <div class="col-7">
                  <input type="number" name="new_image_variations[${idx}][stock]" class="form-control form-control-sm" placeholder="Stock" min="0" value="1" required>
                </div>
                <div class="col-5">
                  <input type="text" name="new_image_variations[${idx}][sku]" class="form-control form-control-sm" placeholder="SKU (optional)">
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
      list.appendChild(row);
    });
  });

  function updateDiscountPreviewEdit() {
    const pct = parseFloat(document.getElementById('discount_percent')?.value || 0);
    const preview = document.getElementById('discount-preview');
    const text = document.getElementById('discount-preview-text');
    if (pct > 0) {
      const prices = document.querySelectorAll('#image-variations-table input[name^=\"image_variations\"][name$=\"[price]\"]');
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

  document.getElementById('discount_percent')?.addEventListener('input', updateDiscountPreviewEdit);
  document.addEventListener('input', function(e) {
    if (e.target.name && e.target.name.startsWith('image_variations') && e.target.name.endsWith('[price]')) {
      updateDiscountPreviewEdit();
    }
  });

  @if(in_array($product->compliance_status, ['flagged', 'auto_flagged']))
  const flaggedModal = new bootstrap.Modal(document.getElementById('flaggedModal'));
  flaggedModal.show();
  @endif
</script>
@endpush


