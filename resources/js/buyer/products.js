const readPayload = (id) => {
    const element = document.getElementById(id);
    if (!element) return null;

    try {
        const payload = JSON.parse(element.textContent || '');
        return payload && typeof payload === 'object' ? payload : null;
    } catch {
        return null;
    }
};

const initProducts = () => {
    const sizeSelect = document.getElementById('size_id');
    const cartQty = document.getElementById('cart-quantity');
    const cartBtn = document.getElementById('cart-btn');
    const buyBtn = document.getElementById('buy-btn');
    const mainProductImage = document.getElementById('main-product-image');
    const productData = readPayload('product-page-data');
    const thumbs = document.querySelectorAll('.variation-thumb');
    const productMainImageUrl = typeof productData?.mainImageUrl === 'string'
        ? productData.mainImageUrl
        : mainProductImage?.getAttribute('src') || '';
    const payloadVariations = Array.isArray(productData?.variations) ? productData.variations : [];
    const variations = payloadVariations.length > 0 ? payloadVariations : Array.from(thumbs, (thumb) => ({
        id: thumb.getAttribute('data-variation-id'),
        stock: Number(thumb.getAttribute('data-variation-stock')) || 0,
        price: Number(thumb.getAttribute('data-variation-price')) || 0,
        originalPrice: Number(thumb.getAttribute('data-variation-original-price')) || 0,
        image: thumb.getAttribute('data-variation-image') || productMainImageUrl,
        name: thumb.getAttribute('data-variation-name') || ''
    }));
    const quantityMax = Number(cartQty?.max);
    const productStock = Number(productData?.stock);
    const fallbackStock = Number.isFinite(productStock) ? productStock : (Number.isFinite(quantityMax) ? quantityMax : 0);
    let selectedVariationId = null;

    const hasMultipleVariations = () => variations.length > 1;

    const computeStockLimit = () => {
        const limits = [];
        if (selectedVariationId !== null) {
            const variation = variations.find((item) => String(item.id) === String(selectedVariationId));
            if (variation && Number.isFinite(Number(variation.stock))) limits.push(Number(variation.stock));
        }
        if (sizeSelect && sizeSelect.value) {
            const option = sizeSelect.options[sizeSelect.selectedIndex];
            const stock = Number(option?.dataset.stock);
            if (Number.isFinite(stock)) limits.push(stock);
        }
        if (limits.length === 0) limits.push(fallbackStock);
        let minimum = Math.min(...limits);
        if (!Number.isFinite(minimum) || minimum < 1) minimum = 1;
        return minimum;
    };

    const updateButtons = () => {
        const limit = computeStockLimit();
        let canPurchase = limit >= 1;
        if (hasMultipleVariations()) canPurchase = canPurchase && selectedVariationId !== null;
        if (cartBtn) cartBtn.disabled = !canPurchase;
        if (buyBtn) buyBtn.disabled = !canPurchase;
    };

    const updateQuantityLimit = () => {
        if (cartQty) {
            const limit = computeStockLimit();
            cartQty.max = String(limit);
            if (parseInt(cartQty.value, 10) > limit) cartQty.value = '1';
        }
        updateButtons();
    };

    const syncHiddenSize = () => {
        const value = sizeSelect ? sizeSelect.value : '';
        const cartSize = document.getElementById('cart-size-id');
        const buySize = document.getElementById('buy-size-id');
        if (cartSize) cartSize.value = value;
        if (buySize) buySize.value = value;
        updateQuantityLimit();
    };

    const updateStockDisplay = (stock) => {
        const stockDisplay = document.getElementById('stock-display');
        const stockAmount = document.getElementById('stock-amount');
        if (!stockDisplay || !stockAmount) return;
        stockDisplay.className = '';
        if (stock > 0) {
            stockDisplay.className = 'text-muted d-block';
            stockAmount.textContent = `${stock} ${stock === 1 ? 'unit' : 'units'} available`;
        } else {
            stockDisplay.className = 'badge bg-danger rounded-pill';
            stockDisplay.id = 'stock-display';
            stockDisplay.textContent = 'Sold Out';
        }
    };

    window.selectVariation = (id, stock, price, originalPrice, image, name) => {
        if (stock <= 0) return;

        thumbs.forEach((thumb) => thumb.classList.remove('variation-selected'));
        const clicked = document.querySelector(`[data-variation-id="${id}"]`);
        if (clicked) clicked.classList.add('variation-selected');

        selectedVariationId = id;
        const selectedVariation = document.getElementById('selected-variation-id');
        const cartVariation = document.getElementById('cart-variation-id');
        const buyVariation = document.getElementById('buy-variation-id');
        if (selectedVariation) selectedVariation.value = id;
        if (cartVariation) cartVariation.value = id;
        if (buyVariation) buyVariation.value = id;

        if (mainProductImage && image) {
            mainProductImage.src = image;
            mainProductImage.style.opacity = '0';
            setTimeout(() => {
                mainProductImage.style.opacity = '1';
            }, 150);
        }

        const displayPrice = document.getElementById('display-price');
        if (displayPrice) {
            const currentPrice = parseFloat(price);
            const previousPrice = parseFloat(originalPrice);
            if (previousPrice && currentPrice < previousPrice) {
                displayPrice.innerHTML = `<span class="text-danger fw-bold">&#8369;${currentPrice.toFixed(2)}</span> <small class="text-muted text-decoration-line-through">&#8369;${previousPrice.toFixed(2)}</small> <span class="badge bg-danger rounded-pill small">${Math.round((1 - currentPrice / previousPrice) * 100)}%</span>`;
            } else {
                displayPrice.innerHTML = `<span class="text-danger fw-bold">&#8369;${currentPrice.toFixed(2)}</span>`;
            }
        }

        updateStockDisplay(stock);
        const info = document.getElementById('selected-variant-info');
        const nameElement = document.getElementById('selected-variant-name');
        if (info && nameElement) {
            nameElement.textContent = name;
            info.style.display = 'block';
        }
        updateQuantityLimit();
    };

    window.addToCart = () => {
        if (hasMultipleVariations() && selectedVariationId === null) {
            window.alert('Please select a product option first.');
            return;
        }
        const quantityInput = document.getElementById('cart-quantity');
        const submitQty = document.getElementById('cart-submit-qty');
        let quantity = quantityInput ? parseInt(quantityInput.value, 10) : 1;
        if (!Number.isFinite(quantity)) quantity = 1;
        const quantityLimit = computeStockLimit();
        if (quantity > quantityLimit) quantity = quantityLimit;
        if (submitQty) submitQty.value = String(quantity);
        document.getElementById('cart-form')?.submit();
    };

    window.placeOrder = () => {
        if (hasMultipleVariations() && selectedVariationId === null) {
            window.alert('Please select a product option first.');
            return;
        }
        const quantityInput = document.getElementById('cart-quantity');
        const buyQty = document.getElementById('buy-quantity');
        let quantity = quantityInput ? parseInt(quantityInput.value, 10) : 1;
        if (!Number.isFinite(quantity)) quantity = 1;
        const quantityLimit = computeStockLimit();
        if (quantity > quantityLimit) quantity = quantityLimit;
        if (buyQty) buyQty.value = String(quantity);
        document.getElementById('buynow-form')?.submit();
    };

    if (sizeSelect) {
        sizeSelect.addEventListener('change', syncHiddenSize);
        syncHiddenSize();
    }

    thumbs.forEach((thumb) => {
        thumb.addEventListener('click', () => {
            const id = thumb.getAttribute('data-variation-id');
            const stock = parseInt(thumb.getAttribute('data-variation-stock'), 10);
            const price = parseFloat(thumb.getAttribute('data-variation-price'));
            const originalPrice = parseFloat(thumb.getAttribute('data-variation-original-price')) || price;
            const image = thumb.getAttribute('data-variation-image') || productMainImageUrl;
            const name = thumb.getAttribute('data-variation-name') || '';
            window.selectVariation(id, stock, price, originalPrice, image, name);
        });
    });

    updateButtons();

    const modal = document.getElementById('secondaryImageModal');
    const bootstrapApi = window.bootstrap;
    if (modal && bootstrapApi && bootstrapApi.Carousel) {
        modal.addEventListener('shown.bs.modal', () => {
            const carouselElement = document.getElementById('lightbox-carousel');
            if (!carouselElement) return;
            const carousel = bootstrapApi.Carousel.getOrCreateInstance(carouselElement);
            const items = carouselElement.querySelectorAll('.carousel-item');
            const currentIndexElement = document.getElementById('current-image-index');
            const updateIndex = () => {
                const active = carouselElement.querySelector('.carousel-item.active');
                if (active && currentIndexElement) {
                    currentIndexElement.textContent = String(Array.from(items).indexOf(active) + 1);
                }
            };
            carousel.cycle();
            updateIndex();
            carouselElement.addEventListener('slide.bs.carousel', updateIndex);
        });
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProducts, { once: true });
} else {
    initProducts();
}

export { initProducts };
