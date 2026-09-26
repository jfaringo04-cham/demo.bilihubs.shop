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

  const desc = document.getElementById('description');
  const countCurrent = document.getElementById('count-current');
  function updateDescCount() {
    if (!desc || !countCurrent) return;
    countCurrent.textContent = desc.value.length;
    countCurrent.className = desc.value.length > 1900 ? 'text-warning' : 'text-muted';
  }
  if (desc) {
    updateDescCount();
    desc.addEventListener('input', updateDescCount);
  }

  const discountPercent = document.getElementById('discount_percent');
  const priceInput = document.getElementById('price');
  const salePricePreview = document.getElementById('sale_price_preview');
  function updateSalePrice() {
    if (!discountPercent || !priceInput || !salePricePreview) return;
    const price = parseFloat(priceInput.value || 0);
    const percent = parseFloat(discountPercent.value || 0);
    if (price > 0 && percent > 0) {
      const salePrice = price * (1 - percent / 100);
      salePricePreview.value = salePrice.toFixed(2);
    } else {
      salePricePreview.value = '-';
    }
  }
  if (discountPercent) discountPercent.addEventListener('input', updateSalePrice);
  if (priceInput) priceInput.addEventListener('input', updateSalePrice);
  updateSalePrice();

  const enableDiscount = document.getElementById('enable_discount');
  const discountFields = document.getElementById('discount-fields');
  if (enableDiscount && discountFields) {
    enableDiscount.addEventListener('change', function () {
      discountFields.style.display = this.checked ? 'block' : 'none';
      if (!this.checked) {
        const dp = document.getElementById('discount_percent');
        const sp = document.getElementById('discount_starts_at');
        const ep = document.getElementById('discount_ends_at');
        if (dp) dp.value = '';
        if (sp) sp.value = '';
        if (ep) ep.value = '';
        if (salePricePreview) salePricePreview.value = '-';
      }
    });
  }

  const categorySelect = document.getElementById('category_id');
  const subcategorySelect = document.getElementById('subcategory_id');
  const subcategoryData = data?.subcategoryGroups ?? {};

  function loadSubcategories(parentId) {
    if (!subcategorySelect) return;
    subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
    if (parentId && subcategoryData[parentId]) {
      Object.entries(subcategoryData[parentId]).forEach(([id, name]) => {
        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = name;
        subcategorySelect.appendChild(opt);
      });
    }
  }
  if (categorySelect) {
    categorySelect.addEventListener('change', function () {
      loadSubcategories(this.value);
    });
    if (categorySelect.value) loadSubcategories(categorySelect.value);
  }

  const imagesInput = document.getElementById('images');
  const imagePreviews = document.getElementById('image-previews');
  if (imagesInput && imagePreviews) {
    imagesInput.addEventListener('change', function (e) {
      imagePreviews.innerHTML = '';
      const files = Array.from(e.target.files);
      if (files.length === 0) return;
      const fragment = document.createDocumentFragment();
      files.forEach((file, idx) => {
        if (!file.type.startsWith('image/')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'image-preview-item';
        wrapper.innerHTML = `
          <img src="${URL.createObjectURL(file)}" class="image-preview-thumb" alt="preview">
          <div class="image-preview-overlay">
            <button type="button" class="btn btn-sm btn-light" onclick="window.removeImage(${idx})">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
        `;
        fragment.appendChild(wrapper);
      });
      imagePreviews.appendChild(fragment);
    });
  }

  const secondaryInput = document.getElementById('secondary_image');
  const secondaryPreview = document.getElementById('secondary-preview');
  if (secondaryInput && secondaryPreview) {
    secondaryInput.addEventListener('change', function (e) {
      secondaryPreview.innerHTML = '';
      const file = e.target.files[0];
      if (!file || !file.type.startsWith('image/')) return;
      secondaryPreview.innerHTML = `
        <div class="image-preview-item">
          <img src="${URL.createObjectURL(file)}" class="image-preview-thumb" alt="secondary preview">
          <div class="image-preview-overlay">
            <button type="button" class="btn btn-sm btn-light" onclick="window.removeSecondaryImage()">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
        </div>
      `;
    });
  }

  const videoInput = document.getElementById('video');
  const videoPreview = document.getElementById('video-preview');
  if (videoInput && videoPreview) {
    videoInput.addEventListener('change', function (e) {
      videoPreview.innerHTML = '';
      const file = e.target.files[0];
      if (!file) return;
      videoPreview.innerHTML = `
        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-xl">
          <i class="bi bi-play-circle display-5 text-muted"></i>
          <div class="flex-fill">
            <div class="fw-medium small">${file.name}</div>
            <div class="text-muted small">${formatFileSize(file.size)}</div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.removeVideoFile()">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      `;
    });
  }

  const productForm = document.getElementById('productForm');
  const publishBtn = document.getElementById('publishBtn');
  const btnText = publishBtn?.querySelector('.btn-text');
  const spinner = publishBtn?.querySelector('.spinner-border');
  if (productForm && publishBtn && btnText && spinner) {
    productForm.addEventListener('submit', function () {
      publishBtn.setAttribute('disabled', 'disabled');
      btnText.textContent = 'Publishing...';
      spinner.classList.remove('d-none');
    });
  }

  initSizeStockSync();

  const dropZone = document.getElementById('dropZone');
  if (dropZone && imagesInput) {
    dropZone.addEventListener('click', function () {
      imagesInput.click();
    });
    ['dragenter', 'dragover'].forEach((evt) => {
      dropZone.addEventListener(evt, function (e) {
        e.preventDefault();
        dropZone.classList.add('drop-zone-dragover');
      });
    });
    ['dragleave', 'drop'].forEach((evt) => {
      dropZone.addEventListener(evt, function (e) {
        e.preventDefault();
        dropZone.classList.remove('drop-zone-dragover');
      });
    });
    dropZone.addEventListener('drop', function (e) {
      const dt = e.dataTransfer;
      const files = dt.files;
      if (files.length > 0) {
        imagesInput.files = files;
        imagesInput.dispatchEvent(new Event('change'));
      }
    });
  }

  initVariantImageHandling();
  let variantCounter = document.querySelectorAll('.variant-row').length;

  window.addVariantRow = function () {
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
              <div class="image-upload-placeholder" id="variant-image-placeholder-${index}" onclick="window.changeVariantImage(${index})">
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

  window.setStatus = function (status) {
    const el = document.getElementById('product-status');
    if (el) el.value = status;
  };

  window.removeImage = function (index) {
    const input = document.getElementById('images');
    if (!input) return;
    const files = Array.from(input.files);
    files.splice(index, 1);
    const dt = new DataTransfer();
    files.forEach((f) => dt.items.add(f));
    input.files = dt.files;
    input.dispatchEvent(new Event('change'));
  };

  window.removeSecondaryImage = function () {
    const input = document.getElementById('secondary_image');
    const preview = document.getElementById('secondary-preview');
    if (input) input.value = '';
    if (preview) preview.innerHTML = '';
  };

  window.removeVideoFile = function () {
    const input = document.getElementById('video');
    const preview = document.getElementById('video-preview');
    if (input) input.value = '';
    if (preview) preview.innerHTML = '';
  };
});