const readPayload = (id) => {
    const element = document.getElementById(id);
    if (!element) return null;

    try {
        return JSON.parse(element.textContent || '');
    } catch {
        return null;
    }
};

const initDashboard = () => {
    const mapElement = document.getElementById('map');
    if (!mapElement) return;

    const orders = Array.isArray(readPayload('rider-dashboard-data')) ? readPayload('rider-dashboard-data') : [];
    if (!orders.length) return;

    const center = { lat: 14.5995, lng: 120.9842 };

    if (window.google?.maps) {
        const map = new window.google.maps.Map(mapElement, {
            zoom: 12,
            center,
        });
        const geocoder = new window.google.maps.Geocoder();

        orders.forEach((order) => {
            if (!order.address) return;
            geocoder.geocode({ address: order.address }, (results, status) => {
                if (status !== 'OK' || !results?.[0]) return;
                new window.google.maps.Marker({
                    map,
                    position: results[0].geometry.location,
                    title: order.customerName || 'Customer',
                });
            });
        });
        return;
    }

    if (!window.L) return;

    const map = window.L.map(mapElement).setView([center.lat, center.lng], 12);
    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
    }).addTo(map);

    orders.forEach((order) => {
        if (!order.address) return;
        const popup = document.createElement('div');
        const name = document.createElement('strong');
        name.textContent = order.customerName || 'Customer';
        const address = document.createElement('div');
        address.textContent = order.address;
        popup.append(name, document.createElement('br'), address);
        window.L.marker([center.lat, center.lng]).addTo(map).bindPopup(popup);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboard, { once: true });
} else {
    initDashboard();
}

export { initDashboard };
