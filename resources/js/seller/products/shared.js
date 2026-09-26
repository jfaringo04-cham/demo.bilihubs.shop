export function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

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