const readPayload = (id) => {
    const element = document.getElementById(id);
    if (!element) return null;

    try {
        return JSON.parse(element.textContent || '');
    } catch {
        return null;
    }
};

const initDelivery = () => {
    const mapElement = document.getElementById('rider-map');
    const data = readPayload('rider-delivery-data');
    const center = { lat: 14.5995, lng: 120.9842 };

    if (mapElement && data?.address) {
        if (window.google?.maps) {
            const map = new window.google.maps.Map(mapElement, {
                zoom: 14,
                center,
            });
            const geocoder = new window.google.maps.Geocoder();
            geocoder.geocode({ address: data.address }, (results, status) => {
                if (status !== 'OK' || !results?.[0]) return;
                map.setCenter(results[0].geometry.location);
                new window.google.maps.Marker({
                    map,
                    position: results[0].geometry.location,
                    title: data.customerName || 'Customer',
                });
            });
        } else if (window.L) {
            const map = window.L.map(mapElement).setView([center.lat, center.lng], 14);
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
            }).addTo(map);

            const popup = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = data.customerName || 'Customer';
            const address = document.createElement('div');
            address.textContent = data.address;
            popup.append(name, document.createElement('br'), address);
            window.L.marker([center.lat, center.lng]).addTo(map).bindPopup(popup);
        }
    }

    const statusSelect = document.getElementById('delivery_status');
    const proofFields = document.getElementById('delivery-proof-fields');
    const photoInput = document.getElementById('proof_of_delivery');
    if (!statusSelect || !proofFields || !photoInput) return;

    const toggleProofFields = () => {
        const delivered = statusSelect.value === 'delivered';
        proofFields.style.display = delivered ? 'block' : 'none';
        photoInput.required = delivered;
    };

    statusSelect.addEventListener('change', toggleProofFields);
    toggleProofFields();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDelivery, { once: true });
} else {
    initDelivery();
}

export { initDelivery };
