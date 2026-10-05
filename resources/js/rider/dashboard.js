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

    const firstOrderWithCoordinates = orders.find(
    (order) => order.latitude !== null && order.longitude !== null
);

const center = firstOrderWithCoordinates
    ? {
        lat: Number(firstOrderWithCoordinates.latitude),
        lng: Number(firstOrderWithCoordinates.longitude),
    }
    : { lat: 14.2816, lng: 121.4103 };

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
    if (order.latitude === null || order.longitude === null) return;

    const lat = Number(order.latitude);
    const lng = Number(order.longitude);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

    const popup = document.createElement('div');

    const name = document.createElement('strong');
    name.textContent = order.customerName || 'Customer';

    const address = document.createElement('div');
    address.textContent = order.address || 'Address unavailable';

    popup.append(name, document.createElement('br'), address);

    window.L.marker([lat, lng])
        .addTo(map)
        .bindPopup(popup);
});
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboard, { once: true });
} else {
    initDashboard();
}

export { initDashboard };
