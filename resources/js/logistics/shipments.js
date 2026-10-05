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

    /*
     * Do not display a generic map when the shipment has no
     * real location data yet. This prevents users from assuming
     * that the default map position is the shipment location.
     */
    const hasLocation = data.pickup || data.rider || data.lastKnown;

    if (!hasLocation) {
        mapElement.innerHTML = `
            <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                <div class="text-center px-4">
                    <div class="mb-3">
                        <i class="bi bi-geo-alt fs-1 text-muted"></i>
                    </div>

                    <h5 class="mb-2">
                        Location not yet available
                    </h5>

                    <p class="text-muted mb-0">
                        Tracking will appear here once pickup or rider
                        location data becomes available.
                    </p>
                </div>
            </div>
        `;

        return;
    }

    /*
     * Create the Leaflet map only when actual location data exists.
     */
    const map = window.L.map(mapElement);

    window.L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            attribution: '© OpenStreetMap contributors',
        }
    ).addTo(map);

    const markers = [];

    /*
     * Rider current-location marker.
     */
    const blueIcon = window.L.icon({
        iconUrl:
            'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
        shadowUrl:
            'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41],
    });

    /*
     * Rider last-known-location marker.
     */
    const redIcon = window.L.icon({
        iconUrl:
            'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl:
            'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41],
    });

    /*
     * Pickup / shipment location.
     */
    if (data.pickup) {
        const pickupMarker = window.L.marker([
            data.pickup.latitude,
            data.pickup.longitude,
        ])
            .addTo(map)
            .bindPopup(
                createPopup(
                    'Pickup Location',
                    data.pickup.address
                )
            );

        markers.push(pickupMarker);
    }

    /*
     * Current rider location.
     */
    if (data.rider) {
        const riderMarker = window.L.marker(
            [
                data.rider.latitude,
                data.rider.longitude,
            ],
            {
                icon: blueIcon,
            }
        )
            .addTo(map)
            .bindPopup(
                createPopup(
                    'Rider Location',
                    'Current rider position'
                )
            );

        markers.push(riderMarker);
    }

    /*
     * Rider's last recorded location.
     */
    if (data.lastKnown) {
        const lastKnownMarker = window.L.marker(
            [
                data.lastKnown.latitude,
                data.lastKnown.longitude,
            ],
            {
                icon: redIcon,
            }
        )
            .addTo(map)
            .bindPopup(
                createPopup(
                    'Rider Last Known Location',
                    data.lastKnown.updatedLabel
                        ? `Updated: ${data.lastKnown.updatedLabel}`
                        : ''
                )
            );

        markers.push(lastKnownMarker);
    }

    /*
     * One marker:
     * Center directly on that location.
     */
    if (markers.length === 1) {
        const position = markers[0].getLatLng();

        map.setView(position, 15);
        markers[0].openPopup();
    }

    /*
     * Multiple markers:
     * Automatically fit all locations on screen.
     */
    if (markers.length > 1) {
        const group = window.L.featureGroup(markers);

        map.fitBounds(
            group.getBounds().pad(0.1)
        );
    }
};

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        initShipments,
        { once: true }
    );
} else {
    initShipments();
}

export { initShipments };