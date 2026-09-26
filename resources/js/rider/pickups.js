const openScanModal = (orderId, expectedToken, orderNumber) => {
    const form = document.getElementById('scanForm');
    const tokenInput = document.getElementById('qr_token');
    const orderNumberInput = document.getElementById('scanOrderNumber');
    const tokenText = document.getElementById('expectedToken');
    const modalElement = document.getElementById('scanModal');

    if (!form || !tokenInput || !orderNumberInput || !tokenText || !modalElement || !window.bootstrap?.Modal) return;

    form.action = `/rider/shipments/${orderId}/scan`;
    orderNumberInput.value = orderNumber;
    tokenText.textContent = expectedToken;
    tokenInput.value = '';
    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    window.setTimeout(() => tokenInput.focus(), 500);
};

window.openScanModal = openScanModal;

export { openScanModal };
