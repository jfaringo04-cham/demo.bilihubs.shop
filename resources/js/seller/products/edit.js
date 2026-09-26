import {
  formatFileSize,
  initVariantImageHandling,
  changeVariantImage,
  removeVariantImage,
  removeVariantRow,
  initSizeStockSync,
} from './shared.js';

document.addEventListener('DOMContentLoaded', function () {
  function parseJsonElement(id) {
    const el = document.getElementById(id);
    if (!el) return null;
    try {
      return JSON.parse(el.textContent);
    } catch {
      return null;
    }
  }

  const data = parseJsonElement('seller-product-form-data');

  initSizeStockSync();

  window.setPrimaryImage = function (imageId) {
    const hidden = document.getElementById('primary-image-id');
    if (hidden) hidden.value = imageId;
    document.querySelectorAll('#image-gallery .col').forEach((col) => {
      const btn = col.querySelector('button[onclick*="setPrimaryImage"]');
      const badge = col.querySelector('.bg-success');
      if (col.id === 'image-item-' + imageId) {
        if (badge) badge.style.display = 'block';
        if (btn) btn.style.display = 'none';
      } else {
        if (badge) badge.style.display = 'none';
        if (btn) btn.style.display = 'block';
      }
    });
  };

  window.markRemoveImage = function (id, name) {
    if (!confirm('Remove "' + name + '"? This will be saved when you click Update Product.')) return;
    const item = document.getElementById('image-item-' + id);
    if (item) item.remove();
    const container = document.getElementById('remove-inputs');
    if (!container) return;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'remove_images[]';
    input.value = id;
    container.appendChild(input);
  };

  window.removeVideoAndSubmit = function () {
    const input = document.getElementById('remove-video-input');
    if (input) input.value = '1';
    const btn = document.querySelector('button[type="submit"]');
    if (btn) btn.click();
  };

  window.removeSecondaryImageAndSubmit = function () {
    const input = document.getElementById('remove-secondary-image-input');
    if (input) input.value = '1';
    const btn = document.querySelector('button[type="submit"]');
    if (btn) btn.click();
  };

  window.confirmRemoveProduct = function (id, name) {
    if (!confirm('Are you sure you want to permanently remove "' + name + '"?\n\nThis will also delete all of its images, variations, and remove it from your shop. This action cannot be undone.')) return;
    const form = document.getElementById('remove-product-form-' + id);
    if (form) form.submit();
  };

  initVariantImageHandling();
  let variantCounter = document.querySelectorAll('.variant-row').length;

  window.addVariantRowEdit = function () {
    const list = document.getElementById('variants-list');
    if (!list) return;
    const index = variantCounter++;
    const row = document.createElement('div');
    row.className = 'variant-row card mb-3';
    row.setAttribute('data-index', index);
    row.innerHTML = `
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label form-label-sm mb-0">Image</label>
            <div class="variant-image-uploader" data-index="${index}">
              <div id="variant-image-placeholder-${index}" class="image-upload-placeholder" onclick="window.changeVariantImage(${index})">
                <i class="bi bi-image"></i>
                <span>Upload Image</span>
              </div>
              <div id="variant-image-preview-${index}" class="image-preview-container d-none">
                <img src="" class="image-preview-thumb" alt="Variant preview">
                <button type="button" class="btn-change" onclick="window.changeVariantImage(${index})">Change</button>
                <button type="button" class="btn-remove" onclick="window.removeVariantImage(${index})">×</button>
                <input type="file" name="variations[${index}][image]" class="variant-image-input" style="display:none;" accept="image/*">
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-0">Color</label>
            <input type="text" name="variations[${index}][color]" class="form-control form-control-sm" placeholder="e.g. White">
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-0">Size</label>
            <input type="text" name="variations[${index}][size]" class="form-control form-control-sm" placeholder="e.g. 8 / XL">
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-0">Price</label>
            <input type="number" name="variations[${index}][price]" class="form-control form-control-sm" placeholder="Optional" step="0.01" min="0">
            <small class="text-muted small d-block">Blank = uses product price</small>
          </div>
          <div class="col-md-1">
            <label class="form-label form-label-sm mb-0">Stock</label>
            <input type="number" name="variations[${index}][stock]" class="form-control form-control-sm" placeholder="0" min="0" value="0">
          </div>
          <div class="col-md-1">
            <label class="form-label form-label-sm mb-0">SKU</label>
            <input type="text" name="variations[${index}][sku]" class="form-control form-control-sm" placeholder="Optional">
          </div>
          <div class="col-md-auto d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.removeVariantRow(${index})" title="Remove this variant">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>
      </div>
    `;
    list.appendChild(row);
  };

  window.removeVariantRow = removeVariantRow;
  window.changeVariantImage = changeVariantImage;
  window.removeVariantImage = removeVariantImage;

  const newImagesInput = document.getElementById('new_images');
  const newImagePreviews = document.getElementById('new-image-previews');
  if (newImagesInput && newImagePreviews) {
    newImagesInput.addEventListener('change', function (e) {
      const files = Array.from(e.target.files);
      if (files.length === 0) return;
      const fragment = document.createDocumentFragment();
      files.forEach((file) => {
        if (!file.type.startsWith('image/')) return;
        const div = document.createElement('div');
        div.className = 'col';
        div.innerHTML = `
          <div class="position-relative border rounded" style="width: 80px; height: 80px;">
            <img src="${URL.createObjectURL(file)}" class="w-100 h-100 rounded" style="object-fit: cover;" alt="New image">
          </div>
        `;
        fragment.appendChild(div);
      });
      newImagePreviews.appendChild(fragment);
    });
  }

  function updateDiscountPreviewEdit() {
    const pct = parseFloat(document.getElementById('discount_percent')?.value || 0);
    const preview = document.getElementById('discount-preview');
    const text = document.getElementById('discount-preview-text');
    if (!preview || !text) return;
    if (pct > 0) {
      const prices = document.querySelectorAll('.variants-list input[name$="[price]"]');
      if (prices.length > 0) {
        const firstPrice = parseFloat(prices[0].value || 0);
        if (firstPrice > 0) {
          const discounted = (firstPrice * (1 - pct / 100)).toFixed(2);
          text.textContent = `${pct}% off → first variant will sell at ₱${discounted} (was ₱${firstPrice.toFixed(2)}). Buyers save ₱${(firstPrice - discounted).toFixed(2)}.`;
          preview.style.display = 'block';
          return;
        }
      }
      text.textContent = `${pct}% discount will be applied to the price of each variant.`;
      preview.style.display = 'block';
    } else {
      preview.style.display = 'none';
    }
  }
  const discountPercent = document.getElementById('discount_percent');
  if (discountPercent) discountPercent.addEventListener('input', updateDiscountPreviewEdit);
  document.addEventListener('input', function (e) {
    if (e.target.name && e.target.name.indexOf('[price]') > -1) {
      updateDiscountPreviewEdit();
    }
  });
  updateDiscountPreviewEdit();

  const flaggedModalEl = document.getElementById('flaggedModal');
  if (flaggedModalEl && window.bootstrap && bootstrap.Modal) {
    const modal = new bootstrap.Modal(flaggedModalEl);
    modal.show();
  }
});