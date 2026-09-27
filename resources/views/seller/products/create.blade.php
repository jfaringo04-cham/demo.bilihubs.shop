@extends('seller.layout')



@push('styles')

  @vite('resources/css/seller/products.css')

@endpush



@section('page-title', 'Add Product')



@section('content')

<form method="POST" action="{{ route('seller.products.store') }}" enctype="multipart/form-data" id="productForm" novalidate>

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

        <button type="button" class="btn btn-outline-secondary rounded-xl px-4" data-action="save-draft">

          <i class="bi bi-save"></i> Save as Draft

        </button>

        <button type="submit" class="btn btn-bili-hub rounded-xl px-4" id="publishBtn">

          <span class="btn-text">Save Product</span>

          <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>

        </button>

      </div>

    </div>



    <div class="product-form-layout">

      <!-- ============================================================

           LEFT COLUMN - product information

           ============================================================ -->

      <div class="product-form-column product-form-column--details">



        <!-- Basic Information -->

        <div class="form-section">

          <div class="section-header">

            <div class="section-icon"><i class="bi bi-info-circle"></i></div>

            <div>

              <h3 class="section-title">Basic Information</h3>

              <p class="section-description">Essential details about your product.</p>

            </div>

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

                    <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>

                      {{ $category->name }}

                    </option>

                  @endforeach

                </select>

                @if(empty($allowedCategoryIds))

                  <div class="text-danger mt-2 small">You have no selling categories set. Please contact admin support to assign categories to your seller account before adding products.</div>

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



        <!-- Pricing & Inventory -->

        <div class="form-section">

          <div class="section-header">

            <div class="section-icon"><i class="bi bi-currency-exchange"></i></div>

            <div>

              <h3 class="section-title">Pricing &amp; Inventory</h3>

              <p class="section-description">Set your product price and stock levels.</p>

            </div>

          </div>



          <div class="form-grid">

            <div class="form-row">

              <div class="form-group">

                <label for="price" class="form-label">Regular Price <span class="text-danger">*</span></label>

                <div class="input-group">

                  <span class="input-group-text">₱</span>

                  <input type="number" name="price" id="price" class="form-control product-input @error('price') is-invalid @enderror" value="{{ old('price') }}" placeholder="0.00" step="0.01" min="0" required>

                </div>

                <small class="text-muted">Used when a product has no variants.</small>

                @error('price')

                  <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>

                @enderror

              </div>



              <div class="form-group">

                <label for="stock" class="form-label">Stock <span class="text-danger">*</span></label>

                <input type="number" name="stock" id="stock" class="form-control product-input @error('stock') is-invalid @enderror" value="{{ old('stock', 0) }}" placeholder="0" min="0" required>

                <small class="text-muted">Total stock when variants are enabled.</small>

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



        <!-- Discount / Promo -->

        <div class="form-section">

          <div class="section-header">

            <div class="section-icon"><i class="bi bi-tag"></i></div>

            <div>

              <h3 class="section-title">Discount / Promo</h3>

              <p class="section-description">Optional promotional pricing (if applicable).</p>

            </div>

          </div>



          <div class="d-flex align-items-center mb-3">

            <input type="checkbox" name="enable_discount" id="enable_discount" class="form-check-input me-2" value="1" {{ old('discount_percent') ? 'checked' : '' }}>

            <label for="enable_discount" class="form-check-label mb-0 fw-medium">Put this product on sale</label>

          </div>



          <div id="discount-fields" class="{{ old('discount_percent') ? '' : 'd-none' }}">

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



        <!-- Shipping Information -->

        <div class="form-section">

          <div class="section-header">

            <div class="section-icon"><i class="bi bi-truck"></i></div>

            <div>

              <h3 class="section-title">Shipping Information</h3>

              <p class="section-description">Package details for accurate delivery calculations.</p>

            </div>

          </div>



          <div class="form-row">

            <div class="form-group">

              <label for="weight" class="form-label">Weight <span class="text-muted small">(Optional)</span></label>

              <div class="input-group">

                <input type="number" name="attributes[shipping][weight]" id="weight" class="form-control product-input" placeholder="0.00" step="0.01" min="0" value="{{ old('attributes.shipping.weight') }}">

                <span class="input-group-text">kg</span>

              </div>

              <small class="text-muted">Weight in kilograms.</small>

            </div>



            <div class="form-group">

              <label for="dimensions" class="form-label">Dimensions (LxWxH) <span class="text-muted small">(Optional)</span></label>

              <div class="input-group">

                <input type="number" name="attributes[shipping][length]" id="length" class="form-control product-input" placeholder="L" step="0.01" min="0" value="{{ old('attributes.shipping.length') }}">

                <span class="input-group-text">×</span>

                <input type="number" name="attributes[shipping][width]" id="width" class="form-control product-input" placeholder="W" step="0.01" min="0" value="{{ old('attributes.shipping.width') }}">

                <span class="input-group-text">×</span>

                <input type="number" name="attributes[shipping][height]" id="height" class="form-control product-input" placeholder="H" step="0.01" min="0" value="{{ old('attributes.shipping.height') }}">

              </div>

              <small class="text-muted">Length × Width × Height in centimeters.</small>

            </div>

          </div>

        </div>



        <!-- Other Details -->

        <div class="form-section">

          <div class="section-header">

            <div class="section-icon"><i class="bi bi-sliders"></i></div>

            <div>

              <h3 class="section-title">Other Details</h3>

              <p class="section-description">Publishing status and compliance handling.</p>

            </div>

          </div>



          <div class="form-row">

            <div class="form-group">

              <label for="product-status-select" class="form-label">Product Status</label>

              <select id="product-status-select" class="form-select product-select">

                <option value="published" {{ old('status', 'published') === 'published' ? 'selected' : '' }}>Published</option>

                <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>Draft</option>

              </select>

              <small class="text-muted">Drafts are not visible to buyers until you publish them.</small>

            </div>



            <div class="form-group">

              <span class="form-label d-block">Compliance Status</span>

              <div class="form-control bg-light d-flex align-items-center justify-content-between">

                <span class="text-muted small">Reviewed by BiliHub compliance</span>

                <span class="badge bg-info bg-opacity-10 text-info">On publish</span>

              </div>

              <small class="text-muted">Set automatically by the compliance team. Not editable here.</small>

            </div>

          </div>

        </div>

      </div>



      <!-- ============================================================

           RIGHT COLUMN - media and variants

           ============================================================ -->

      <div class="product-form-column product-form-column--media">



        <!-- Product Images -->

        <div class="form-section">

          <div class="section-header">

            <div class="section-icon"><i class="bi bi-image"></i></div>

            <div>

              <h3 class="section-title">Product Images <span class="text-danger">*</span></h3>

              <p class="section-description">The first image is used as the main product photo.</p>

            </div>

          </div>



          <div class="image-gallery" id="imageGallery">

            <div class="image-gallery__main" id="imageGalleryMain" data-drop-target>

              <img id="imageGalleryMainImage" class="image-gallery__main-image d-none" alt="Main product image preview">

              <div class="image-gallery__empty" id="imageGalleryEmpty">

                <i class="bi bi-cloud-arrow-up"></i>

                <p class="mb-1 fw-medium">No images yet</p>

                <p class="text-muted small mb-0">Click “Add Image” or drop images here</p>

              </div>

              <span class="image-gallery__main-badge" id="imageGalleryMainBadge" hidden>Main image</span>

            </div>



            <div class="image-gallery__thumbs" id="imageGalleryThumbs">

              <button type="button" class="image-gallery__add" id="imageGalleryAdd" aria-label="Add product image">

                <i class="bi bi-plus-lg"></i>

                <span>Add Image</span>

                <small id="imageGalleryCounter">0 / 5</small>

              </button>

            </div>

          </div>



          <input type="file" name="images[]" id="images" class="d-none" accept="image/jpeg,image/png,image/webp" multiple>



          <div class="invalid-feedback d-block mt-2 small" id="imageGalleryError" hidden></div>

          @error('images')

            <div class="invalid-feedback d-block mt-2 small">{{ $message }}</div>

          @enderror

          @error('images.*')

            <div class="invalid-feedback d-block mt-2 small">{{ $message }}</div>

          @enderror



          <p class="text-muted small mb-0 mt-2">Upload high-quality images (JPG, PNG, WebP)</p>

          <p class="text-muted small mb-0">Recommended size: 1000 x 1000px</p>



          <details class="more-media mt-3">

            <summary class="more-media__summary">More media (optional)</summary>

            <div class="more-media__body">

              <div class="form-group">

                <label for="secondary_image" class="form-label">Additional Image <span class="text-muted small">(Optional)</span></label>

                <input type="file" name="secondary_image" id="secondary_image" class="form-control product-input @error('secondary_image') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">

                <small class="text-muted">Infographic, banner, or lifestyle shot. Max 2MB.</small>

                @error('secondary_image')

                  <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>

                @enderror

                <div id="secondary-preview" class="mt-2"></div>

              </div>



              <div class="form-group mb-0">

                <label for="video" class="form-label">Product Video <span class="text-muted small">(Optional)</span></label>

                <input type="file" name="video" id="video" class="form-control product-input @error('video') is-invalid @enderror" accept="video/mp4,video/quicktime,video/x-msvideo,video/webm">

                <small class="text-muted">Showcase your product with a short video. Max 10MB. MP4, MOV, AVI, WEBM.</small>

                @error('video')

                  <div class="invalid-feedback d-block mt-1 small">{{ $message }}</div>

                @enderror

                <div id="video-preview" class="mt-2"></div>

              </div>

            </div>

          </details>

        </div>



        <!-- Variants -->

        <div class="form-section">

          <div class="section-header section-header--toggle">

            <div class="section-icon"><i class="bi bi-layers"></i></div>

            <div class="flex-grow-1">

              <h3 class="section-title">Variants <span class="text-muted small fw-normal">(Optional)</span></h3>

              <p class="section-description">Create different options for this product such as size, color, or design. Each variant can have its own price, stock, and image.</p>

            </div>

            <div class="form-check form-switch mb-0">

              <input class="form-check-input" type="checkbox" role="switch" id="enable-variants" {{ count(old('variations', [])) ? 'checked' : '' }}>

              <label class="form-check-label small" for="enable-variants">Enable variants</label>

            </div>

          </div>



          <div id="variantPanel" class="{{ count(old('variations', [])) ? '' : 'd-none' }}">

            <div class="variant-list-head d-none d-md-flex">

              <span class="variant-col-image">Image</span>

              <span>Color</span>

              <span>Size</span>

              <span>Price (₱)</span>

              <span>Stock</span>

              <span>SKU</span>

              <span class="variant-col-name">Name</span>

              <span class="variant-col-actions"></span>

            </div>



            <div id="variants-list" class="variants-list">

              @php

                $oldVariants = old('variations', []);

                if (empty($oldVariants)) {

                  $oldVariants = [[

                      'color' => null,

                      'size' => null,

                      'name' => null,

                      'price' => null,

                      'stock' => 0,

                      'sku' => null,

                  ]];

                }

              @endphp

              @foreach($oldVariants as $variantIndex => $variant)

                <div class="variant-row" data-variant-row>

                  <div class="variant-row__image">
                    <span class="variant-field-label">Variant Image</span>
                    <button type="button" class="variant-image-box" data-action="variant-image-pick" aria-label="Choose variant image">

                      <img class="variant-image-preview d-none" alt="">

                      <span class="variant-image-placeholder">

                        <i class="bi bi-image"></i>

                        <span>Add image</span>

                      </span>

                    </button>

                    <input type="file" name="variations[{{ $variantIndex }}][image]" class="variant-image-input" accept="image/jpeg,image/png,image/webp" hidden>

                  </div>



                  <div class="variant-row__field variant-field--color">
                    <label class="variant-field-label">Color</label>
                    <input type="text" name="variations[{{ $variantIndex }}][color]" class="form-control product-input" placeholder="White" value="{{ $variant['color'] ?? '' }}">

                  </div>



                  <div class="variant-row__field variant-field--size">
                    <label class="variant-field-label">Size</label>
                    <input type="text" name="variations[{{ $variantIndex }}][size]" class="form-control product-input" placeholder="M" value="{{ $variant['size'] ?? '' }}">

                  </div>



                  <div class="variant-row__field variant-field--price">
                    <label class="variant-field-label">Price (₱)</label>
                    <input type="number" name="variations[{{ $variantIndex }}][price]" class="form-control product-input" placeholder="499.00" step="0.01" min="0" value="{{ $variant['price'] ?? '' }}">

                  </div>



                  <div class="variant-row__field variant-field--stock">
                    <label class="variant-field-label">Stock</label>
                    <input type="number" name="variations[{{ $variantIndex }}][stock]" class="form-control product-input" placeholder="0" min="0" value="{{ $variant['stock'] ?? 0 }}">

                  </div>



                  <div class="variant-row__field variant-field--sku">
                    <label class="variant-field-label">SKU</label>
                    <input type="text" name="variations[{{ $variantIndex }}][sku]" class="form-control product-input" placeholder="e.g. WHITE-M" value="{{ $variant['sku'] ?? '' }}">

                  </div>



                  <div class="variant-row__field variant-field--name">
                    <label class="variant-field-label">Variant Name</label>
                    <input type="text" name="variations[{{ $variantIndex }}][name]" class="form-control product-input" placeholder="e.g. White / M" value="{{ $variant['name'] ?? '' }}">

                  </div>



                  <div class="variant-row__actions">

                    <button type="button" class="variant-remove" data-action="variant-remove" aria-label="Remove variant" title="Remove this variant">

                      <i class="bi bi-trash"></i>

                    </button>

                  </div>

                </div>

              @endforeach

            </div>



            <button type="button" class="btn variant-add-btn w-100" id="add-variant-btn" data-action="variant-add">

              <i class="bi bi-plus-lg"></i> Add Variant

            </button>



            <div class="variant-summary" id="variantSummary" hidden>

              <div class="variant-summary__item">

                <span class="variant-summary__label">Total variant stock</span>

                <span class="variant-summary__value" id="variantStockTotal">0</span>

              </div>

              <div class="variant-summary__item">

                <span class="variant-summary__label">Lowest variant price</span>

                <span class="variant-summary__value" id="variantPriceMin">₱0.00</span>

              </div>

              <p class="variant-summary__note">

                Product stock and price are set automatically from your variants when this product has at least one variant.

              </p>

            </div>

          </div>

        </div>

      </div>

    </div>



    <!-- Bottom actions -->

    <div class="product-form-actions">

      <a href="{{ route('seller.products') }}" class="btn btn-outline-secondary rounded-xl px-4">Cancel</a>

      <button type="submit" class="btn btn-bili-hub rounded-xl px-4" data-submit-button>

        <span class="btn-text">Save Product</span>

        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>

      </button>

    </div>

  </div>

</form>

@endsection



@push('scripts')

  <template id="variant-row-template">

    <div class="variant-row" data-variant-row>

      <div class="variant-row__image">
        <span class="variant-field-label">Variant Image</span>
        <button type="button" class="variant-image-box" data-action="variant-image-pick" aria-label="Choose variant image">

          <img class="variant-image-preview d-none" alt="">

          <span class="variant-image-placeholder">

            <i class="bi bi-image"></i>

            <span>Add image</span>

          </span>

        </button>

        <input type="file" name="variations[__INDEX__][image]" class="variant-image-input" accept="image/jpeg,image/png,image/webp" hidden>

      </div>



      <div class="variant-row__field variant-field--color">
        <label class="variant-field-label">Color</label>
        <input type="text" name="variations[__INDEX__][color]" class="form-control product-input" placeholder="White">

      </div>



      <div class="variant-row__field variant-field--size">
        <label class="variant-field-label">Size</label>
        <input type="text" name="variations[__INDEX__][size]" class="form-control product-input" placeholder="M">

      </div>



      <div class="variant-row__field variant-field--price">
        <label class="variant-field-label">Price (₱)</label>
        <input type="number" name="variations[__INDEX__][price]" class="form-control product-input" placeholder="499.00" step="0.01" min="0">

      </div>



      <div class="variant-row__field variant-field--stock">
        <label class="variant-field-label">Stock</label>
        <input type="number" name="variations[__INDEX__][stock]" class="form-control product-input" placeholder="0" min="0" value="0">

      </div>



      <div class="variant-row__field variant-field--sku">
        <label class="variant-field-label">SKU</label>
        <input type="text" name="variations[__INDEX__][sku]" class="form-control product-input" placeholder="e.g. WHITE-M">

      </div>



      <div class="variant-row__field variant-field--name">
        <label class="variant-field-label">Variant Name</label>
        <input type="text" name="variations[__INDEX__][name]" class="form-control product-input" placeholder="e.g. White / M">

      </div>



      <div class="variant-row__actions">

        <button type="button" class="variant-remove" data-action="variant-remove" aria-label="Remove variant" title="Remove this variant">

          <i class="bi bi-trash"></i>

        </button>

      </div>

    </div>

  </template>



  <script type="application/json" id="seller-product-form-data">@php $sellerProductFormData = [

    'subcategoryGroups' => $subcategoryGroups->mapWithKeys(function ($group, $parentId) {

      return [$parentId => $group->pluck('name', 'id')->toArray()];

    }),

    'maxImages' => 5,

  ]; @endphp @json($sellerProductFormData)</script>

  @vite('resources/js/seller/products/create.js')

@endpush


