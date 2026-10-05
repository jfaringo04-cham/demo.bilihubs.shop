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
    /*
    |--------------------------------------------------------------------------
    | DELIVERY MAP
    |--------------------------------------------------------------------------
    */

    const mapElement = document.getElementById('rider-map');
    const data = readPayload('rider-delivery-data');

    const latitude = Number.parseFloat(data?.latitude);
    const longitude = Number.parseFloat(data?.longitude);

    const hasCoordinates =
        Number.isFinite(latitude) &&
        Number.isFinite(longitude);

    const center = hasCoordinates
        ? { lat: latitude, lng: longitude }
        : null;

    if (mapElement && data) {
        if (window.google?.maps) {
            const fallbackCenter = {
                lat: 14.5995,
                lng: 120.9842,
            };

            const map = new window.google.maps.Map(mapElement, {
                zoom: 14,
                center: center || fallbackCenter,
            });

            if (hasCoordinates) {
                new window.google.maps.Marker({
                    map,
                    position: center,
                    title: data.customerName || 'Customer',
                });
            } else if (data.address) {
                const geocoder = new window.google.maps.Geocoder();

                geocoder.geocode(
                    { address: data.address },
                    (results, status) => {
                        if (status !== 'OK' || !results?.[0]) {
                            mapElement.innerHTML = `
                                <div class="alert alert-warning m-3">
                                    Exact delivery location is unavailable.
                                    Use Google Maps or Waze from the delivery address.
                                </div>
                            `;
                            return;
                        }

                        const location =
                            results[0].geometry.location;

                        map.setCenter(location);

                        new window.google.maps.Marker({
                            map,
                            position: location,
                            title:
                                data.customerName ||
                                'Customer',
                        });
                    }
                );
            }
        } else if (window.L) {
            if (hasCoordinates) {
                const map = window.L
                    .map(mapElement)
                    .setView(
                        [latitude, longitude],
                        15
                    );

                window.L.tileLayer(
                    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                    {
                        attribution:
                            '© OpenStreetMap contributors',
                    }
                ).addTo(map);

                const popup =
                    document.createElement('div');

                const name =
                    document.createElement('strong');

                name.textContent =
                    data.customerName || 'Customer';

                const address =
                    document.createElement('div');

                address.textContent =
                    data.address || '';

                popup.append(
                    name,
                    document.createElement('br'),
                    address
                );

                window.L
                    .marker([latitude, longitude])
                    .addTo(map)
                    .bindPopup(popup)
                    .openPopup();
            } else {
                mapElement.innerHTML = `
                    <div class="d-flex align-items-center justify-content-center h-100 p-4 text-center">
                        <div>
                            <i class="bi bi-geo-alt fs-1 text-muted"></i>

                            <h6 class="mt-2 mb-1">
                                Exact location unavailable
                            </h6>

                            <small class="text-muted">
                                Customer coordinates were not saved
                                for this order. Use Google Maps or
                                Waze from the delivery address.
                            </small>
                        </div>
                    </div>
                `;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELIVERY STATUS FORM
    |--------------------------------------------------------------------------
    */

    const statusSelect =
        document.getElementById('delivery_status');

    if (!statusSelect) {
        return;
    }

    const proofFields =
        document.getElementById(
            'delivery-proof-fields'
        );

    const photoInput =
        document.getElementById(
            'proof_of_delivery'
        );

    const deliveryNotesField =
        document.getElementById(
            'delivery-notes-field'
        );

    const deliveryNotesInput =
        document.getElementById(
            'delivery_notes'
        );

    const failureReasonField =
        document.getElementById(
            'failure-reason-field'
        );

    const failureReasonInput =
        document.getElementById(
            'failure_reason'
        );

    /*
    |--------------------------------------------------------------------------
    | TOGGLE DELIVERY FIELDS
    |--------------------------------------------------------------------------
    */

    const toggleDeliveryFields = () => {
        const status = statusSelect.value;

        const isDelivered =
            status === 'delivered';

        const isFailed =
            status === 'delivery_failed';

        /*
        |----------------------------------------------------------------------
        | Proof of Delivery
        |----------------------------------------------------------------------
        */

        if (proofFields) {
            proofFields.style.display =
                isDelivered ? 'block' : 'none';
        }

        if (photoInput) {
            photoInput.required = isDelivered;
            photoInput.disabled = !isDelivered;
        }

        /*
        |----------------------------------------------------------------------
        | Delivery Notes
        |----------------------------------------------------------------------
        */

        if (deliveryNotesField) {
            deliveryNotesField.style.display =
                isFailed ? 'none' : 'block';
        }

        if (deliveryNotesInput) {
            deliveryNotesInput.disabled =
                isFailed;
        }

        /*
        |----------------------------------------------------------------------
        | Failure Reason
        |----------------------------------------------------------------------
        */

        if (failureReasonField) {
            failureReasonField.style.display =
                isFailed ? 'block' : 'none';
        }

        if (failureReasonInput) {
            failureReasonInput.required =
                isFailed;

            failureReasonInput.disabled =
                !isFailed;
        }
    };

    /*
    |--------------------------------------------------------------------------
    | STATUS CHANGE
    |--------------------------------------------------------------------------
    */

    statusSelect.addEventListener(
        'change',
        toggleDeliveryFields
    );

    // Run immediately when page loads.
    toggleDeliveryFields();
};

/*
|--------------------------------------------------------------------------
| INITIALIZE
|--------------------------------------------------------------------------
*/

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        initDelivery,
        { once: true }
    );
} else {
    initDelivery();
}

export { initDelivery };