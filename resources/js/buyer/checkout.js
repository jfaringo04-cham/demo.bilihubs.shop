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

const initCheckout = () => {
    const checkoutData = readPayload('checkout-page-data');
    const defaultAddress = typeof checkoutData?.defaultAddress === 'string' ? checkoutData.defaultAddress : '';
    const useSavedButton = document.getElementById('use-saved-btn');
    const editAddressButton = document.getElementById('edit-address-btn');
    const shippingAddressWrap = document.getElementById('shipping-address-wrap');
    const savedAddressBox = document.getElementById('saved-address-box');
    const shippingAddress = document.getElementById('shipping_address');

    useSavedButton?.addEventListener('click', () => {
        if (shippingAddress && defaultAddress) shippingAddress.value = defaultAddress;
    });

    editAddressButton?.addEventListener('click', () => {
        if (shippingAddressWrap) shippingAddressWrap.style.display = 'block';
        if (savedAddressBox) savedAddressBox.style.display = 'none';
        shippingAddress?.focus();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCheckout, { once: true });
} else {
    initCheckout();
}

export { initCheckout };
