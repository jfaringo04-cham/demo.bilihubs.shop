@extends('seller.layout')

@push('styles')
  @vite('resources/css/seller/products.css')
@endpush

@section('page-title', 'Add Product')

@section('content')
<form method="POST" action="{{ route('seller.products.store') }}" enctype="multipart/form-data" id="productForm">
  @csrf

  <input type="hidden" name="status" id="product-status" value="published">

  <div class="product-form-container">
    <!-- Header -->
    <div class="product-form-header">
      <div>
        <h1 class="product-form-title">Add New Product</h1>
        <p class="product-form-subtitle">Fill in the details below to list your product on BiliHub.</p>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary rounded-xl px-4" onclick="setStatus('draft')">
          <i class="bi bi-save"></i> Save as Draft
        </button>
        <button type="submit" class="btn btn-bili-hub rounded-xl px-4" id="publishBtn">
          <span class="btn-text">Publish Product</span>
          <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
        </button>
      </div>
    </div>

    <div class="product-form-body">
      <!-- Section 1: Basic Information -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon"><i class="bi bi-info-circle"></i></div>
          <h3 class="section-title">Basic Information</h3>
          <p class="section-description">Essential details about your product.</p>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
            <input
              type="text"
              name="name"
              id="name"
              class="form-control product-input @error('name') is-invalid @enderror"
              value="{{ old('name') }}"
              placeholder="e.g. Premium Wireless Headphones"
              required
            >
            @error('name')
              <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
            @enderror
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
              <select name="category_id" id="category_id" class="form-select product-select @error('category_id') is-invalid @enderror" required>
                <option value="">Select Category</option>
                @foreach($categories as $category)
                  <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }} data-subcategory-ids="{{ json_encode($subcategoryGroups[$category->id] ?? collect()->pluck('id')->toArray()) }}">
                    {{ $category->name }}
                  </option>
                @endforeach
              </select>
              @if(empty($allowedCategoryIds))
                <div class="text-danger mt-2">You have no selling categories set. Please contact admin support to assign categories to your seller account before adding products.</div>
              @endif
              @error('category_id')
                <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
              @enderror
            </div>

            <div class="form-group">
              <label for="subcategory_id" class="form-label">Subcategory</label>
              <select name="subcategory_id" id="subcategory_id" class="form-select product-select @error('subcategory_id') is-invalid @enderror">
                <option value="">Select Subcategory</option>
              </select>
              @error('subcategory_id')
                <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
              @enderror
            </div>
          </div>

           <div class="form-group">
             <label for="description" class="form-label">Description</label>
             <textarea
              name="description"
              id="description"
              class="form-control product-textarea @error('description') is-invalid @enderror"
              rows="5"
              placeholder="Describe your product in detail. Include key features, materials, and sizing information."
              maxlength="2000"
            >{{ old('description') }}</textarea>
            <div class="d-flex justify-content-between align-items-center mt-1">
              <span class="text-muted small">Be descriptive to help buyers make a decision. Optional.</span>
              <span class="text-muted small" id="desc-count">
                <span id="count-current">0</span> / 2000
              </span>
            </div>
            @error('description')
              <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
            @enderror
          </div>
        </div>
      </div>

      <!-- Section 2: Product Media -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon"><i class="bi bi-image"></i></div>
          <h3 class="section-title">Product Media</h3>
          <p class="section-description">Upload product images and optional video.</p>
        </div>

        <div class="form-group">
          <label for="images" class="form-label">Product Images <span class="text-danger">*</span></label>
          <label for="images" class="drop-zone" id="dropZone">
            <div class="drop-zone-content text-center">
              <i class="bi bi-cloud-arrow-up display-5 text-muted mb-2"></i>
              <p class="mb-1 fw-medium">Drag & drop product images here</p>
              <p class="text-muted small mb-2">or Browse Files</p>
              <p class="text-muted small">JPG, PNG, GIF - Max 2MB each</p>
              <input type="file" name="images[]" id="images" class="d-none" accept="image/*" multiple required>
            </div>
          </label>
          @error('images')
            <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
          @enderror
          @error('images.*')
            <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
          @enderror
        </div>

        <div id="image-previews" class="image-previews-grid"></div>

        <div class="form-group mt-3">
          <label for="secondary_image" class="form-label">Additional Image <span class="text-muted small">(Optional)</span></label>
          <input type="file" name="secondary_image" id="secondary_image" class="form-control product-input @error('secondary_image') is-invalid @enderror" accept="image/*">
          <small class="text-muted">Infographic, banner, or lifestyle shot. Max 2MB. JPG, PNG, GIF.</small>
          @error('secondary_image')
            <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
          @enderror
          <div id="secondary-preview" class="mt-2"></div>
        </div>

        <div class="form-group mt-3">
          <label for="video" class="form-label">Product Video <span class="text-muted small">(Optional)</span></label>
          <input type="file" name="video" id="video" class="form-control product-input @error('video') is-invalid @enderror" accept="video/*">
          <small class="text-muted">Showcase your product with a short video. Max 10MB. MP4, MOV, AVI, WEBM.</small>
          @error('video')
            <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
          @enderror
          <div id="video-preview" class="mt-2"></div>
        </div>
      </div>

      <!-- Section 3: Pricing & Inventory -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon"><i class="bi bi-currency-exchange"></i></div>
          <h3 class="section-title">Pricing & Inventory</h3>
          <p class="section-description">Set your product price and stock levels.</p>
        </div>

        <div class="form-grid">
          <div class="form-row">
            <div class="form-group">
              <label for="price" class="form-label">Regular Price <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="number" name="price" id="price" class="form-control product-input @error('price') is-invalid @enderror" value="{{ old('price') }}" placeholder="0.00" step="0.01" min="0" required>
              </div>
              @error('price')
                <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
              @enderror
            </div>

            <div class="form-group">
              <label for="stock" class="form-label">Stock <span class="text-danger">*</span></label>
              <input type="number" name="stock" id="stock" class="form-control product-input @error('stock') is-invalid @enderror" value="{{ old('stock') }}" placeholder="0" min="0" required>
              @error('stock')
                <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="sku" class="form-label">SKU <span class="text-muted small">(Optional)</span></label>
              <input type="text" name="sku" id="sku" class="form-control product-input" value="{{ old('sku') }}" placeholder="e.g. SKU-001">
              <small class="text-muted">Unique stock keeping unit identifier.</small>
            </div>

            <div class="form-group">
              <label for="low_stock_threshold" class="form-label">Low Stock Alert <span class="text-muted small">(Optional)</span></label>
              <input type="number" name="low_stock_threshold" id="low_stock_threshold" class="form-control product-input" value="{{ old('low_stock_threshold', 5) }}" placeholder="5" min="0">
              <small class="text-muted">Notify when stock falls below this number.</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 4: Discount / Promo -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon"><i class="bi bi-tag"></i></div>
          <h3 class="section-title">Discount / Promo</h3>
          <p class="section-description">Optional promotional pricing (if applicable).</p>
        </div>

        <div class="form-group">
          <div class="d-flex align-items-center mb-3">
            <input type="checkbox" name="enable_discount" id="enable_discount" class="form-check-input me-2" value="1" {{ old('discount_percent') ? 'checked' : '' }}>
            <label for="enable_discount" class="form-check-label mb-0 fw-medium">Put this product on sale</label>
          </div>

          <div id="discount-fields" style="{{ old('discount_percent') ? '' : 'display: none;' }}">
            <div class="form-row">
              <div class="form-group">
                <label for="discount_percent" class="form-label">Discount</label>
                <div class="input-group">
                  <input type="number" name="discount_percent" id="discount_percent" class="form-control product-input @error('discount_percent') is-invalid @enderror" min="0" max="100" step="0.01" value="{{ old('discount_percent') }}" placeholder="e.g. 20">
                  <span class="input-group-text">%</span>
                </div>
                @error('discount_percent')
                  <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
                @enderror
                <small class="text-muted">0 = no discount, 20 = 20% off</small>
              </div>

              <div class="form-group">
                <label for="sale_price_preview" class="form-label">Sale Price</label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="text" id="sale_price_preview" class="form-control product-input bg-light" readonly value="-">
                </div>
                <small class="text-muted">Calculated automatically.</small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="discount_starts_at" class="form-label">Starts At</label>
                <input type="datetime-local" name="discount_starts_at" id="discount_starts_at" class="form-control product-input @error('discount_starts_at') is-invalid @enderror" value="{{ old('discount_starts_at') }}">
                @error('discount_starts_at')
                  <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
                @enderror
              </div>

              <div class="form-group">
                <label for="discount_ends_at" class="form-label">Ends At</label>
                <input type="datetime-local" name="discount_ends_at" id="discount_ends_at" class="form-control product-input @error('discount_ends_at') is-invalid @enderror" value="{{ old('discount_ends_at') }}">
                @error('discount_ends_at')
                  <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>
                @enderror
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 5: Product Variations -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon"><i class="bi bi-layers"></i></div>
          <h3 class="section-title">Product Variations</h3>
          <p class="section-description">Create different buyable options for this product.</p>
        </div>

        <p class="text-muted small mb-3">Add an image, color, size, price, and stock for each option. Color and size are optional.</p>

        <div id="variants-list" class="variants-list">
          @php
            $oldVariants = old('variations', []);
            if (empty($oldVariants)) {
              $oldVariants = [['color' => null, 'size' => null, 'name' => null, 'price' => null, 'stock' => 0, 'sku' => null]];
            }
          @endphp
          @foreach($oldVariants as $variantIndex => $variant)
            <div class="variant-row card mb-3" data-index="{{ $variantIndex }}">
              <div class="card-body">
                <div class="row g-3 align-items-end">
                  <div class="col-md-4">
                    <label class="form-label form-label-sm mb-0">Image</label>
                    <div class="variant-image-uploader" data-index="{{ $variantIndex }}">
                       <div class="image-upload-placeholder" id="variant-image-placeholder-{{ $variantIndex }}" onclick="changeVariantImage({{ $variantIndex }})">
                          <i class="bi bi-image"></i>
                          <span>Upload Image</span>
                        </div>
                      <div id="variant-image-preview-{{ $variantIndex }}" class="image-preview-container d-none">
                        <img src="" class="image-preview-thumb" alt="Variant preview">
                        <button type="button" class="btn-change" onclick="changeVariantImage({{ $variantIndex }})">Change</button>
                        <button type="button" class="btn-remove" onclick="removeVariantImage({{ $variantIndex }})">×</button>
                        <input type="file" name="variations[{{ $variantIndex }}][image]" class="variant-image-input" style="display:none;" accept="image/*" />
                      </div>
                    </div>
                  </div>

                  <div class="col-md-2">
                    <label class="form-label form-label-sm mb-0">Color</label>
                    <input type="text" name="variations[{{ $variantIndex }}][color]" class="form-control form-control-sm" placeholder="e.g. White" value="{{ $variant['color'] ?? '' }}">
                  </div>

                  <div class="col-md-2">
                    <label class="form-label form-label-sm mb-0">Size</label>
                    <input type="text" name="variations[{{ $variantIndex }}][size]" class="form-control form-control-sm" placeholder="e.g. 8 / XL" value="{{ $variant['size'] ?? '' }}">
                  </div>

                  <div class="col-md-2">
                    <label class="form-label form-label-sm mb-0">Price</label>
                    <input type="number" name="variations[{{ $variantIndex }}][price]" class="form-control form-control-sm" placeholder="Optional" step="0.01" min="0" value="{{ $variant['price'] ?? '' }}">
                    <small class="text-muted small">Blank = uses product price</small>
                  </div>

                  <div class="col-md-1">
                    <label class="form-label form-label-sm mb-0">Stock</label>
                    <input type="number" name="variations[{{ $variantIndex }}][stock]" class="form-control form-control-sm" placeholder="0" min="0" value="{{ $variant['stock'] ?? 0 }}">
                  </div>

                  <div class="col-md-1">
                    <label class="form-label form-label-sm mb-0">SKU</label>
                    <input type="text" name="variations[{{ $variantIndex }}][sku]" class="form-control form-control-sm" placeholder="Optional" value="{{ $variant['sku'] ?? '' }}">
                  </div>

                  <div class="col-md-auto d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVariantRow({{ $variantIndex }})" title="Remove this variant">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          @endforeach
        </div>

        <button type="button" class="btn btn-sm btn-outline-secondary" id="add-variant-btn" onclick="addVariantRow()">
          <i class="bi bi-plus"></i> Add Another Variant
        </button>
      </div>

      <!-- Section 6: Shipping Information -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon"><i class="bi bi-truck"></i></div>
          <h3 class="section-title">Shipping Information</h3>
          <p class="section-description">Package details for accurate delivery calculations.</p>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="weight" class="form-label">Weight <span class="text-muted small">(Optional)</span></label>
            <div class="input-group">
              <input type="number" name="attributes[shipping][weight]" id="weight" class="form-control product-input" placeholder="0.00" step="0.01" min="0">
              <span class="input-group-text">kg</span>
            </div>
            <small class="text-muted">Weight in kilograms.</small>
          </div>

          <div class="form-group">
            <label for="dimensions" class="form-label">Dimensions (LxWxH) <span class="text-muted small">(Optional)</span></label>
            <div class="input-group">
              <input type="number" name="attributes[shipping][length]" id="length" class="form-control product-input" placeholder="L" step="0.01" min="0">
              <span class="input-group-text">×</span>
              <input type="number" name="attributes[shipping][width]" id="width" class="form-control product-input" placeholder="W" step="0.01" min="0">
              <span class="input-group-text">×</span>
              <input type="number" name="attributes[shipping][height]" id="height" class="form-control product-input" placeholder="H" step="0.01" min="0">
            </div>
            <small class="text-muted">Length × Width × Height in centimeters.</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
@endsection

@push('scripts')
<script type="application/json" id="seller-product-form-data">@php $sellerProductFormData = [
  'subcategoryGroups' => $subcategoryGroups->mapWithKeys(function ($group, $parentId) {
    return [$parentId => $group->pluck('name', 'id')->toArray()];
  }),
]; @endphp @json($sellerProductFormData)</script>
@vite('resources/js/seller/products/create.js')
@endpush