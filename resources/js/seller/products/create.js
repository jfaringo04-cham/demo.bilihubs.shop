import {
  formatFileSize,
  initSizeStockSync,
  IMAGE_ACCEPT,
  MAX_IMAGES,
  initImageGallery,
  initVariantManager,
  initStatusControls,
  lockSubmitButtons,
} from './shared.js';

const parseJsonElement = (id) => {
  const el = document.getElementById(id);
  if (!el) return null;
  try {
    return JSON.parse(el.textContent);
  } catch {
    return null;
  }
};

document.addEventListener('DOMContentLoaded', function () {
  const data = parseJsonElement('seller-product-form-data');

  initImageGallery({
    maxImages: data?.maxImages ?? MAX_IMAGES,
  });

  initVariantManager();

  lockSubmitButtons('productForm', {
    label: 'Publishing...',
    buttonSelector: '[data-submit-button], #publishBtn',
  });

  initStatusControls();

  /* ------------------------------------------------------------------
   | Description counter
   | ------------------------------------------------------------------ */
  const desc = document.getElementById('description');
  const countCurrent = document.getElementById('count-current');
  const updateDescCount = () => {
    if (!desc || !countCurrent) return;
    countCurrent.textContent = desc.value.length;
    countCurrent.className = desc.value.length > 1900 ? 'text-warning' : 'text-muted';
  };
  if (desc) {
    updateDescCount();
    desc.addEventListener('input', updateDescCount);
  }

  /* ------------------------------------------------------------------
   | Discount toggle + sale price preview
   | ------------------------------------------------------------------ */
  const discountPercent = document.getElementById('discount_percent');
  const priceInput = document.getElementById('price');
  const salePricePreview = document.getElementById('sale_price_preview');
  const updateSalePrice = () => {
    if (!discountPercent || !priceInput || !salePricePreview) return;
    const price = parseFloat(priceInput.value || 0);
    const percent = parseFloat(discountPercent.value || 0);
    salePricePreview.value = price > 0 && percent > 0 ? (price * (1 - percent / 100)).toFixed(2) : '-';
  };
  if (discountPercent) discountPercent.addEventListener('input', updateSalePrice);
  if (priceInput) priceInput.addEventListener('input', updateSalePrice);
  updateSalePrice();

  const enableDiscount = document.getElementById('enable_discount');
  const discountFields = document.getElementById('discount-fields');
  if (enableDiscount && discountFields) {
    enableDiscount.addEventListener('change', function () {
      discountFields.classList.toggle('d-none', !this.checked);
      if (!this.checked) {
        ['discount_percent', 'discount_starts_at', 'discount_ends_at'].forEach((id) => {
          const field = document.getElementById(id);
          if (field) field.value = '';
        });
        if (salePricePreview) salePricePreview.value = '-';
      }
    });
  }

  /* ------------------------------------------------------------------
   | Category -> Subcategory cascade
   | ------------------------------------------------------------------ */
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

  /* ------------------------------------------------------------------
   | Additional image + video previews
   | ------------------------------------------------------------------ */
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
            <button type="button" class="btn btn-sm btn-light" data-action="remove-secondary-image">
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
          <i class="bi bi-play-circle fs-2 text-muted"></i>
          <div class="flex-fill">
            <div class="fw-medium small">${file.name}</div>
            <div class="text-muted small">${formatFileSize(file.size)}</div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-action="remove-video">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      `;
    });
  }

  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.getAttribute('data-action');

    if (action === 'remove-secondary-image') {
      if (secondaryInput) secondaryInput.value = '';
      if (secondaryPreview) secondaryPreview.innerHTML = '';
    }

    if (action === 'remove-video') {
      if (videoInput) videoInput.value = '';
      if (videoPreview) videoPreview.innerHTML = '';
    }
  });

  initSizeStockSync();
});

export { IMAGE_ACCEPT };
