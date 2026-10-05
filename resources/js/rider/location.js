const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content');

function updateRiderLocation() {
    if (!navigator.geolocation || !csrfToken) {
        return;
    }

    navigator.geolocation.getCurrentPosition(
        async (position) => {
            try {
                const response = await fetch('/rider/location', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                    }),
                });

                if (!response.ok) {
                    console.error('Unable to update rider location.');
                }
            } catch (error) {
                console.error('Rider location update failed:', error);
            }
        },
        (error) => {
            console.warn('Rider location unavailable:', error.message);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 60000,
        }
    );
}

updateRiderLocation();