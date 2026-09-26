@extends('seller.layout')

@push('styles')
  @vite('resources/css/seller/products.css')
@endpush

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
        <input type="number" name="price" id="price" class="form-control" value="{{ old('price', ($product->price_minor / 100)) }}" step="0.01" min="0" required>
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
       <label class="form-label">Product Images</label>
       <p class="text-muted small">Manage your product images. Each image appears in the product gallery.</p>
       <input type="hidden" name="primary_image_id" id="primary-image-id" value="{{ $product->images()->where('is_primary', true)->first()?->id ?? '' }}">
       @if($product->images->count() > 0)
         <div class="row row-cols-auto g-2 mb-2" id="image-gallery">
           @foreach($product->images as $img)
             <div class="col" id="image-item-{{ $img->id }}">
               <div class="position-relative border rounded" style="width: 80px; height: 80px;">
                 <img src="{{ $img->url }}" class="w-100 h-100 rounded" style="object-fit: cover;" alt="{{ $img->alt_text ?? $product->name }}">
                 @if($img->is_primary)
                   <span class="position-absolute top-0 start-0 badge bg-success rounded-0" style="font-size: 0.6rem;"><i class="bi bi-star-fill"></i></span>
                 @else
                   <button type="button" class="btn btn-sm btn-outline-secondary position-absolute top-0 start-0 rounded-0" style="font-size: 0.5rem; padding: 2px 4px;" onclick="setPrimaryImage({{ $img->id }})" title="Set as Main Image">
                     <i class="bi bi-star"></i>
                   </button>
                 @endif
                 <button type="button" class="btn btn-sm btn-outline-danger position-absolute bottom-0 end-0 rounded-0" style="font-size: 0.5rem; padding: 2px 4px;" onclick="markRemoveImage({{ $img->id }}, '{{ addslashes($img->alt_text ?? $product->name) }}')" title="Remove image">
                   <i class="bi bi-x"></i>
                 </button>
               </div>
             </div>
           @endforeach
         </div>
         <div id="remove-inputs"></div>
       @endif
       <label for="new_images" class="form-label mt-2">Add more images</label>
       <input type="file" name="new_images[]" id="new_images" class="form-control" accept="image/*" multiple>
       <small class="text-muted">You can upload multiple images (jpeg, png, jpg, gif). Max 2MB each.</small>
       @error('new_images') <div class="text-danger">{{ $message }}</div> @enderror
       @error('new_images.*') <div class="text-danger">{{ $message }}</div> @enderror
     </div>

     <div id="new-image-previews" class="row row-cols-auto g-2 mb-3"></div>

    <hr>

    <div class="mb-3">
      <label class="form-label fw-medium">Product Variations</label>
      <p class="text-muted small">Create buyable options with color, size, price, and stock. Each row is an independent purchasable variant.</p>
      <p class="text-muted small mb-3">Color and size are optional. Leave Price blank to use the product's base price.</p>
      <p class="text-muted small mb-3">If a variant has no image, it falls back to the product's cover image on the storefront.</p>

      <div id="variants-list" class="variants-list">
        @php
          $existingVariations = $product->variations;
          $variantIndex = 0;
        @endphp
        @forelse($existingVariations as $variation)
          <div class="variant-row card mb-3" data-variation-id="{{ $variation->id }}" data-index="{{ $variantIndex }}">
            <div class="card-body">
              <input type="hidden" name="variations[{{ $variantIndex }}][id]" value="{{ $variation->id }}">
              <div class="row g-3 align-items-end">
                <div class="col-md-4">
                  <label class="form-label form-label-sm mb-0">Image</label>
                  <div class="variant-image-uploader" data-index="{{ $variantIndex }}">
                    @if($variation->image_url)
                      <div id="variant-image-placeholder-{{ $variantIndex }}" class="image-upload-placeholder d-none">
                        <i class="bi bi-image"></i>
                        <span>Upload Image</span>
                      </div>
                      <div id="variant-image-preview-{{ $variantIndex }}" class="image-preview-container">
                        <img src="{{ $variation->image_url }}" class="image-preview-thumb" alt="Variant preview">
                        <button type="button" class="btn-change" onclick="changeVariantImage({{ $variantIndex }})">Change</button>
                        <button type="button" class="btn-remove" onclick="removeVariantImage({{ $variantIndex }})">×</button>
                        <input type="file" name="variations[{{ $variantIndex }}][image]" class="variant-image-input" style="display:none;" accept="image/*">
                      </div>
                    @else
                      <div id="variant-image-placeholder-{{ $variantIndex }}" class="image-upload-placeholder" onclick="changeVariantImage({{ $variantIndex }})">
                        <i class="bi bi-image"></i>
                        <span>Upload Image</span>
                      </div>
                      <div id="variant-image-preview-{{ $variantIndex }}" class="image-preview-container d-none">
                        <img src="" class="image-preview-thumb" alt="Variant preview">
                        <button type="button" class="btn-change" onclick="changeVariantImage({{ $variantIndex }})">Change</button>
                        <button type="button" class="btn-remove" onclick="removeVariantImage({{ $variantIndex }})">×</button>
                        <input type="file" name="variations[{{ $variantIndex }}][image]" class="variant-image-input" style="display:none;" accept="image/*">
                      </div>
                    @endif
                  </div>
                </div>

                <div class="col-md-2">
                  <label class="form-label form-label-sm mb-0">Color</label>
                  <input type="text" name="variations[{{ $variantIndex }}][color]" class="form-control form-control-sm" placeholder="e.g. White" value="{{ $variation->color ?? '' }}">
                </div>

                <div class="col-md-2">
                  <label class="form-label form-label-sm mb-0">Size</label>
                  <input type="text" name="variations[{{ $variantIndex }}][size]" class="form-control form-control-sm" placeholder="e.g. 8 / XL" value="{{ $variation->size ?? '' }}">
                </div>

                <div class="col-md-2">
                  <label class="form-label form-label-sm mb-0">Price</label>
                  <input type="number" name="variations[{{ $variantIndex }}][price]" class="form-control form-control-sm" placeholder="Optional" step="0.01" min="0" value="{{ ($variation->price_minor !== null ? $variation->price_minor / 100 : '') }}">
                  <small class="text-muted small d-block">Blank = uses product price</small>
                </div>

                <div class="col-md-1">
                  <label class="form-label form-label-sm mb-0">Stock</label>
                  <input type="number" name="variations[{{ $variantIndex }}][stock]" class="form-control form-control-sm" placeholder="0" min="0" value="{{ $variation->stock ?? 0 }}">
                </div>

                <div class="col-md-1">
                  <label class="form-label form-label-sm mb-0">SKU</label>
                  <input type="text" name="variations[{{ $variantIndex }}][sku]" class="form-control form-control-sm" placeholder="Optional" value="{{ $variation->sku ?? '' }}">
                </div>

                <div class="col-md-auto d-flex align-items-end">
                  <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVariantRow({{ $variantIndex }})" title="Remove this variant">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
          @php $variantIndex++; @endphp
        @empty
          <div class="variant-row card mb-3" data-index="0">
            <div class="card-body">
              <div class="row g-3 align-items-end">
                <div class="col-md-4">
                  <label class="form-label form-label-sm mb-0">Image</label>
                  <div class="variant-image-uploader" data-index="0">
                    <div id="variant-image-placeholder-0" class="image-upload-placeholder" onclick="changeVariantImage(0)">
                      <i class="bi bi-image"></i>
                      <span>Upload Image</span>
                    </div>
                    <div id="variant-image-preview-0" class="image-preview-container d-none">
                      <img src="" class="image-preview-thumb" alt="Variant preview">
                      <button type="button" class="btn-change" onclick="changeVariantImage(0)">Change</button>
                      <button type="button" class="btn-remove" onclick="removeVariantImage(0)">×</button>
                      <input type="file" name="variations[0][image]" class="variant-image-input" style="display:none;" accept="image/*">
                    </div>
                  </div>
                </div>

                <div class="col-md-2">
                  <label class="form-label form-label-sm mb-0">Color</label>
                  <input type="text" name="variations[0][color]" class="form-control form-control-sm" placeholder="e.g. White">
                </div>

                <div class="col-md-2">
                  <label class="form-label form-label-sm mb-0">Size</label>
                  <input type="text" name="variations[0][size]" class="form-control form-control-sm" placeholder="e.g. 8 / XL">
                </div>

                <div class="col-md-2">
                  <label class="form-label form-label-sm mb-0">Price</label>
                  <input type="number" name="variations[0][price]" class="form-control form-control-sm" placeholder="Optional" step="0.01" min="0">
                  <small class="text-muted small d-block">Blank = uses product price</small>
                </div>

                <div class="col-md-1">
                  <label class="form-label form-label-sm mb-0">Stock</label>
                  <input type="number" name="variations[0][stock]" class="form-control form-control-sm" placeholder="0" min="0" value="0">
                </div>

                <div class="col-md-1">
                  <label class="form-label form-label-sm mb-0">SKU</label>
                  <input type="text" name="variations[0][sku]" class="form-control form-control-sm" placeholder="Optional">
                </div>

                <div class="col-md-auto d-flex align-items-end">
                  <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVariantRow(0)" title="Remove this variant">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        @endforelse
      </div>

      <button type="button" class="btn btn-sm btn-outline-secondary" id="add-variant-btn" onclick="addVariantRowEdit()">
        <i class="bi bi-plus"></i> Add Another Variant
      </button>
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
@vite('resources/js/seller/products/edit.js')
@endpush




