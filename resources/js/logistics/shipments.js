const readPayload = (id) => {
    const element = document.getElementById(id);
    if (!element) return null;

    try {
        return JSON.parse(element.textContent || '');
    } catch {
        return null;
    }
};

const createPopup = (title, detail) => {
    const popup = document.createElement('div');
    const heading = document.createElement('strong');
    heading.textContent = title;
    popup.appendChild(heading);
    if (detail) {
        popup.append(document.createElement('br'), detail);
    }
    return popup;
};

const initShipments = () => {
    const mapElement = document.getElementById('map');
    const data = readPayload('shipment-tracking-data');
    if (!mapElement || !data || !window.L) return;

    const map = window.L.map(mapElement).setView([14.5995, 120.9842], 12);
    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
    }).addTo(map);

    const markers = [];
    const blueIcon = window.L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41],
    });
    const redIcon = window.L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41],
    });

    if (data.pickup) {
        markers.push(window.L.marker([data.pickup.latitude, data.pickup.longitude])
            .addTo(map)
            .bindPopup(createPopup('Shipment Location', data.pickup.address)));
    }
    if (data.rider) {
        markers.push(window.L.marker([data.rider.latitude, data.rider.longitude], { icon: blueIcon })
            .addTo(map)
            .bindPopup(createPopup('Rider Location', 'Last known position')));
    }
    if (data.lastKnown) {
        markers.push(window.L.marker([data.lastKnown.latitude, data.lastKnown.longitude], { icon: redIcon })
            .addTo(map)
            .bindPopup(createPopup('Rider Last Known Location', data.lastKnown.updatedLabel ? `Updated: ${data.lastKnown.updatedLabel}` : '')));
    }

    if (markers.length > 1) {
        map.fitBounds(new window.L.featureGroup(markers).getBounds().pad(0.1));
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initShipments, { once: true });
} else {
    initShipments();
}

export { initShipments };
