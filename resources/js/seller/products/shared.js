export function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/* ====================================================================
 | Seller Add Product - media gallery and variant manager
 | Shared by create.js (the Edit Product page keeps its own markup).
 | ==================================================================== */

export const IMAGE_ACCEPT = 'image/jpeg,image/png,image/webp';
export const MAX_IMAGES = 5;

const objectUrls = new Set();

function createObjectUrl(file) {
  const url = URL.createObjectURL(file);
  objectUrls.add(url);
  return url;
}

export function releaseObjectUrl(url) {
  if (url && objectUrls.has(url)) {
    URL.revokeObjectURL(url);
    objectUrls.delete(url);
  }
}

function setInputFiles(input, files) {
  const transfer = new DataTransfer();
  files.forEach((file) => transfer.items.add(file));
  input.files = transfer.files;
}

/* -------------------------------------------------------------------
 | Product image gallery: large preview + thumbnail strip
 | ------------------------------------------------------------------- */

export function initImageGallery({ maxImages = MAX_IMAGES } = {}) {
  const input = document.getElementById('images');
  const gallery = document.getElementById('imageGallery');
  if (!input || !gallery) return null;

  const mainImage = document.getElementById('imageGalleryMainImage');
  const emptyState = document.getElementById('imageGalleryEmpty');
  const mainBadge = document.getElementById('imageGalleryMainBadge');
  const thumbs = document.getElementById('imageGalleryThumbs');
  const addButton = document.getElementById('imageGalleryAdd');
  const counter = document.getElementById('imageGalleryCounter');
  const errorBox = document.getElementById('imageGalleryError');
  const mainDropTarget = document.getElementById('imageGalleryMain');

  let files = [];
  let thumbUrls = [];

  const allowedTypes = IMAGE_ACCEPT.split(',').map((type) => type.trim());

  const showError = (message) => {
    if (!errorBox) return;
    errorBox.textContent = message;
    errorBox.hidden = message === '';
  };

  const render = () => {
    if (!thumbs) return;

    const previousMain = mainImage ? mainImage.src : '';
    thumbUrls.forEach(releaseObjectUrl);
    thumbUrls = [];
    thumbs.innerHTML = '';

    if (addButton) {
      thumbs.appendChild(addButton);
    }

    files.forEach((file, index) => {
      const url = createObjectUrl(file);
      thumbUrls.push(url);
      const isPrimary = index === 0;

      const item = document.createElement('div');
      item.className = 'image-gallery__thumb' + (isPrimary ? ' is-primary' : '');
      item.dataset.index = String(index);
      item.setAttribute('role', 'button');
      item.setAttribute('tabindex', '0');
      item.setAttribute('aria-label', `Product image ${index + 1}${isPrimary ? ' (main image)' : ''}`);

      const img = document.createElement('img');
      img.src = url;
      img.alt = `Product image ${index + 1}`;
      item.appendChild(img);

      if (isPrimary) {
        const badge = document.createElement('span');
        badge.className = 'image-gallery__thumb-badge';
        badge.textContent = 'Main';
        item.appendChild(badge);
      }

      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'image-gallery__remove';
      remove.setAttribute('data-action', 'gallery-remove');
      remove.setAttribute('data-index', String(index));
      remove.setAttribute('aria-label', `Remove image ${index + 1}`);
      remove.innerHTML = '<i class="bi bi-x-lg"></i>';
      item.appendChild(remove);

      thumbs.insertBefore(item, addButton);
    });

    if (files.length > 0) {
      if (mainImage) {
        releaseObjectUrl(previousMain);
        mainImage.src = createObjectUrl(files[0]);
        mainImage.classList.remove('d-none');
      }
      if (emptyState) emptyState.classList.add('d-none');
      if (mainBadge) mainBadge.hidden = false;
    } else {
      if (mainImage) {
        releaseObjectUrl(previousMain);
        mainImage.classList.add('d-none');
        mainImage.removeAttribute('src');
      }
      if (emptyState) emptyState.classList.remove('d-none');
      if (mainBadge) mainBadge.hidden = true;
    }

    if (counter) counter.textContent = `${files.length} / ${maxImages}`;
    if (addButton) addButton.hidden = files.length >= maxImages;

    setInputFiles(input, files);
  };

  const addFiles = (incoming) => {
    const accepted = Array.from(incoming).filter((file) => allowedTypes.includes(file.type));

    if (accepted.length !== Array.from(incoming).length) {
      showError('Only JPG, PNG and WebP images can be uploaded.');
    } else {
      showError('');
    }

    const room = maxImages - files.length;

    if (accepted.length > room) {
      showError(`You can upload up to ${maxImages} images.`);
    }

    files = files.concat(accepted.slice(0, Math.max(room, 0)));
    render();
  };

  const setPrimary = (index) => {
    if (index <= 0 || index >= files.length) return;
    const [file] = files.splice(index, 1);
    files.unshift(file);
    render();
  };

  const removeAt = (index) => {
    files.splice(index, 1);
    render();
  };

  if (addButton) {
    addButton.addEventListener('click', () => input.click());
  }

  if (mainDropTarget) {
    mainDropTarget.addEventListener('click', () => input.click());
  }

  input.addEventListener('change', (e) => {
    const incoming = Array.from(e.target.files || []);
    if (incoming.length === 0) return;
    addFiles(incoming);
  });

  ['dragenter', 'dragover'].forEach((evt) => {
    if (!mainDropTarget) return;
    mainDropTarget.addEventListener(evt, (e) => {
      e.preventDefault();
      mainDropTarget.classList.add('is-dragover');
    });
  });

  ['dragleave', 'drop'].forEach((evt) => {
    if (!mainDropTarget) return;
    mainDropTarget.addEventListener(evt, (e) => {
      e.preventDefault();
      mainDropTarget.classList.remove('is-dragover');
    });
  });

  if (mainDropTarget) {
    mainDropTarget.addEventListener('drop', (e) => {
      const dropped = e.dataTransfer?.files;
      if (dropped && dropped.length > 0) addFiles(dropped);
    });
  }

  thumbs?.addEventListener('click', (e) => {
    const remove = e.target.closest('[data-action="gallery-remove"]');
    if (remove) {
      e.stopPropagation();
      removeAt(Number(remove.getAttribute('data-index')));
      return;
    }

    const thumb = e.target.closest('.image-gallery__thumb');
    if (thumb) setPrimary(Number(thumb.dataset.index));
  });

  thumbs?.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const thumb = e.target.closest('.image-gallery__thumb');
    if (!thumb) return;
    e.preventDefault();
    setPrimary(Number(thumb.dataset.index));
  });

  const form = document.getElementById('productForm');
  form?.addEventListener('submit', (e) => {
    if (files.length === 0) {
      e.preventDefault();
      showError('Please upload at least one product image.');
      addButton?.focus();
    }
  });

  render();

  return { addFiles, removeAt, setPrimary, getFiles: () => files.slice() };
}

/* -------------------------------------------------------------------
 | Variant manager
 | ------------------------------------------------------------------- */

export function initVariantManager() {
  const list = document.getElementById('variants-list');
  const template = document.getElementById('variant-row-template');
  const toggle = document.getElementById('enable-variants');
  const panel = document.getElementById('variantPanel');
  const addButton = document.getElementById('add-variant-btn');
  const summary = document.getElementById('variantSummary');
  const stockTotal = document.getElementById('variantStockTotal');
  const priceMin = document.getElementById('variantPriceMin');

  if (!list || !template) return null;

  const rows = () => Array.from(list.querySelectorAll('[data-variant-row]'));

  const reindex = () => {
    rows().forEach((row, index) => {
      row.dataset.index = String(index);
      row.querySelectorAll('[name]').forEach((field) => {
        field.name = field.name.replace(/variations\[\d*\]/, `variations[${index}]`);
      });
    });
  };

  const refreshSummary = () => {
    let total = 0;
    let lowest = null;

    rows().forEach((row) => {
      const stock = parseInt(row.querySelector('[name$="[stock]"]')?.value || '0', 10);
      const price = parseFloat(row.querySelector('[name$="[price]"]')?.value || '');

      if (!Number.isNaN(stock)) total += stock;
      if (!Number.isNaN(price) && price > 0 && (lowest === null || price < lowest)) lowest = price;
    });

    if (stockTotal) stockTotal.textContent = String(total);
    if (priceMin) priceMin.textContent = lowest === null ? '₱0.00' : `₱${lowest.toFixed(2)}`;
    if (summary) summary.hidden = rows().length === 0;
  };

  const setEnabled = (enabled) => {
    if (panel) panel.classList.toggle('d-none', !enabled);

    // Disabled inputs are not submitted, so toggling variants off sends no
    // variations at all and the product keeps its own price and stock.
    rows().forEach((row) => {
      row.querySelectorAll('input, select, textarea, button').forEach((field) => {
        field.disabled = !enabled;
      });
    });

    refreshSummary();
  };

  const addRow = () => {
    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector('[data-variant-row]');
    if (!row) return;
    list.appendChild(fragment);
    reindex();
    setEnabled(toggle ? toggle.checked : true);
    row.querySelector('input:not([type="file"])')?.focus();
  };

  const removeRow = (row) => {
    row.remove();
    reindex();
    refreshSummary();
  };

  list.addEventListener('click', (e) => {
    const remove = e.target.closest('[data-action="variant-remove"]');
    if (remove) {
      const row = remove.closest('[data-variant-row]');
      if (row) removeRow(row);
      return;
    }

    const picker = e.target.closest('[data-action="variant-image-pick"]');
    if (picker) {
      const row = picker.closest('[data-variant-row]');
      row?.querySelector('.variant-image-input')?.click();
    }
  });

  list.addEventListener('change', (e) => {
    const input = e.target;

    if (input.classList.contains('variant-image-input')) {
      const row = input.closest('[data-variant-row]');
      const preview = row?.querySelector('.variant-image-preview');
      const placeholder = row?.querySelector('.variant-image-placeholder');
      const file = input.files && input.files[0];

      if (!row || !file) return;

      const old = preview?.src;
      if (preview) {
        releaseObjectUrl(old);
        preview.src = createObjectUrl(file);
        preview.classList.remove('d-none');
      }
      if (placeholder) placeholder.classList.add('d-none');
      return;
    }

    const name = input.name || '';
    if (name.includes('[price]') || name.includes('[stock]')) refreshSummary();
  });

  list.addEventListener('input', (e) => {
    const name = e.target.name || '';
    if (name.includes('[price]') || name.includes('[stock]')) refreshSummary();
  });

  addButton?.addEventListener('click', addRow);

  toggle?.addEventListener('change', () => setEnabled(toggle.checked));

  reindex();
  setEnabled(toggle ? toggle.checked : true);

  return { addRow, reindex, refreshSummary, setEnabled };
}

/* -------------------------------------------------------------------
 | Status controls + submit lock
 | ------------------------------------------------------------------- */

export function initStatusControls() {
  const hidden = document.getElementById('product-status');
  const select = document.getElementById('product-status-select');
  const draftButton = document.querySelector('[data-action="save-draft"]');

  if (select && hidden) {
    select.addEventListener('change', () => {
      hidden.value = select.value;
    });
  }

  draftButton?.addEventListener('click', () => {
    if (hidden) hidden.value = 'draft';
    if (select) select.value = 'draft';
    document.getElementById('productForm')?.requestSubmit();
  });
}

export function lockSubmitButtons(formId, { label = 'Saving...', buttonSelector = '[data-submit-button]' } = {}) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener('submit', function () {
    form.querySelectorAll(buttonSelector).forEach((button) => {
      const text = button.querySelector('.btn-text');
      if (text) text.textContent = label;
      button.querySelectorAll('.spinner-border').forEach((spinner) => spinner.classList.remove('d-none'));
      button.disabled = true;
    });
  });
}

/* ====================================================================
 | Legacy helpers kept for the Edit Product page
 | ==================================================================== */

export function initVariantImageHandling() {
  document.addEventListener('change', function (e) {
    if (!e.target || !e.target.classList.contains('variant-image-input')) return;
    const uploader = e.target.closest('.variant-image-uploader');
    if (!uploader) return;
    const index = uploader.getAttribute('data-index');
    const file = e.target.files[0];
    if (!file) return;

    const placeholder = document.getElementById('variant-image-placeholder-' + index);
    const preview = document.getElementById('variant-image-preview-' + index);
    const img = preview?.querySelector('.image-preview-thumb');
    if (img) {
      const prevSrc = img.src;
      if (prevSrc && prevSrc.startsWith('blob:')) {
        URL.revokeObjectURL(prevSrc);
      }
      img.src = URL.createObjectURL(file);
    }
    if (placeholder) placeholder.classList.add('d-none');
    if (preview) preview.classList.remove('d-none');
  });
}

export function changeVariantImage(index) {
  const input = document.querySelector('.variant-image-uploader[data-index="' + index + '"] .variant-image-input');
  if (input) input.click();
}

export function removeVariantImage(index) {
  const placeholder = document.getElementById('variant-image-placeholder-' + index);
  const preview = document.getElementById('variant-image-preview-' + index);
  const input = document.querySelector('.variant-image-uploader[data-index="' + index + '"] .variant-image-input');
  if (input) {
    const img = preview?.querySelector('.image-preview-thumb');
    if (img && img.src.startsWith('blob:')) {
      URL.revokeObjectURL(img.src);
    }
    input.value = '';
  }
  if (placeholder && preview) {
    preview.classList.add('d-none');
    placeholder.classList.remove('d-none');
  }
}

export function removeVariantRow(index) {
  const row = document.querySelector('.variant-row[data-index="' + index + '"]');
  if (row) row.remove();
}

export function initSizeStockSync() {
  function update() {
    document.querySelectorAll('.size-checkbox').forEach((cb) => {
      const container = cb.closest('.col-md-4, .size-option');
      if (!container) return;
      const stockInput = container.querySelector('.size-stock-input');
      if (stockInput) {
        stockInput.disabled = !cb.checked;
        if (!cb.checked) stockInput.value = 0;
      }
    });
  }
  update();
  document.querySelectorAll('.size-checkbox').forEach((cb) => {
    cb.addEventListener('change', update);
  });
}
