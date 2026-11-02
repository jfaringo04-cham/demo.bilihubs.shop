import { Html5Qrcode } from 'html5-qrcode';

document.addEventListener('DOMContentLoaded', () => {
    const normalize = (value) =>
        String(value || '').trim().toLowerCase();

    /**
     * Select the shipment's resolved destination hub barangay.
     *
     * Primary source:
     * destination_hub -> barangay
     *
     * Fallback:
     * Existing address matching, for legacy shipments only.
     */
    const selectResolvedZone = (modal) => {
        const select = modal.querySelector('.js-delivery-zone');

        if (!select) {
            return {
                success: false,
                zone: '',
                reason: 'Delivery zone selector was not found.',
            };
        }

        const resolvedZone = String(
            modal.dataset.autoZone || ''
        ).trim();

        if (resolvedZone) {
            const exactMatch = [...select.options].find((option) =>
                normalize(option.value) === normalize(resolvedZone)
            );

            if (exactMatch) {
                select.value = exactMatch.value;

                select.dispatchEvent(
                    new Event('change', { bubbles: true })
                );

                return {
                    success: true,
                    zone: exactMatch.value,
                    source: 'destination-hub',
                };
            }

            return {
                success: false,
                zone: resolvedZone,
                reason:
                    `Resolved delivery zone "${resolvedZone}" is not configured ` +
                    'in the available delivery-zone list.',
            };
        }

        /*
         * Legacy fallback:
         * Try to match an existing zone against the delivery address.
         */
        const address = normalize(modal.dataset.destination);

        if (!address) {
            return {
                success: false,
                zone: '',
                reason: 'No resolved delivery zone is available.',
            };
        }

        const addressMatch = [...select.options].find((option) => {
            const value = normalize(option.value);

            return value && address.includes(value);
        });

        if (addressMatch) {
            select.value = addressMatch.value;

            select.dispatchEvent(
                new Event('change', { bubbles: true })
            );

            return {
                success: true,
                zone: addressMatch.value,
                source: 'address-fallback',
            };
        }

        return {
            success: false,
            zone: '',
            reason: 'Delivery zone could not be detected automatically.',
        };
    };

    /**
     * Default delivery type only.
     *
     * Delivery zone is intentionally selected after successful QR
     * verification instead of immediately when opening the modal.
     */
    document.querySelectorAll('.js-scan-tag-modal').forEach((modal) => {
        modal.addEventListener('shown.bs.modal', () => {
            const type = modal.querySelector('.js-delivery-type');

            if (type && !type.value) {
                type.value = 'standard';
            }
        });
    });

    document.querySelectorAll('.js-logistics-qr-scan').forEach((button) => {
        button.addEventListener('click', async () => {
            const modal = button.closest('.js-scan-tag-modal');
            const reader = modal?.querySelector(
                '.js-logistics-qr-reader'
            );
            const result = modal?.querySelector(
                '.js-logistics-qr-result'
            );

            if (!modal || !reader || !result) {
                return;
            }

            const tracking = String(
                modal.dataset.tracking || ''
            ).trim();

            const readerId = reader.id;

            if (!readerId || !tracking) {
                result.textContent =
                    'Unable to initialize parcel verification.';
                result.className =
                    'small text-danger mt-2 js-logistics-qr-result';
                return;
            }

            reader.classList.remove('d-none');

            result.textContent =
                'Point the camera at the parcel QR code.';

            result.className =
                'small text-muted mt-2 js-logistics-qr-result';

            button.disabled = true;

            const scanner = new Html5Qrcode(readerId);

            try {
                const cameras = await Html5Qrcode.getCameras();

                if (!cameras.length) {
                    throw new Error('No camera was found.');
                }

                await scanner.start(
                    { facingMode: 'environment' },
                    {
                        fps: 10,
                        qrbox: {
                            width: 240,
                            height: 240,
                        },
                    },
                    async (decodedText) => {
                        let scannedTracking = decodedText;

                        try {
                            const payload = JSON.parse(decodedText);

                            scannedTracking =
                                payload?.t ?? decodedText;
                        }
                        catch (_) {
                            /*
                             * Plain tracking numbers are accepted.
                             */
                        }

                        const expectedTracking =
                            tracking.toUpperCase();

                        const actualTracking =
                            String(scannedTracking)
                                .trim()
                                .toUpperCase();

                        if (
                            actualTracking !== expectedTracking
                        ) {
                            result.textContent =
                                'Wrong parcel QR. Please scan the QR for ' +
                                tracking +
                                '.';

                            result.className =
                                'small text-danger mt-2 js-logistics-qr-result';

                            return;
                        }

                        /*
                         * Stop camera only after correct parcel QR.
                         */
                        await scanner.stop();

                        reader.classList.add('d-none');

                        /*
                         * Automatically select zone from the
                         * resolved destination hub.
                         */
                        const zoneResult =
                            selectResolvedZone(modal);

                        const type = modal.querySelector(
                            '.js-delivery-type'
                        );

                        if (type) {
                            type.value = 'standard';
                        }

                        if (zoneResult.success) {
                            result.textContent =
                                `QR verified. Delivery zone automatically ` +
                                `detected: ${zoneResult.zone}. ` +
                                'Standard Delivery selected.';

                            result.className =
                                'small text-success mt-2 js-logistics-qr-result';
                        }
                        else {
                            result.textContent =
                                'QR verified, but ' +
                                zoneResult.reason +
                                ' Please select the correct delivery zone manually.';

                            result.className =
                                'small text-warning mt-2 js-logistics-qr-result';
                        }

                        button.disabled = false;
                    }
                );
            }
            catch (error) {
                try {
                    if (scanner?.isScanning) {
                        await scanner.stop();
                    }
                }
                catch (_) {
                    // Ignore cleanup errors.
                }

                reader.classList.add('d-none');

                result.textContent =
                    error?.message ||
                    'Unable to start the camera scanner.';

                result.className =
                    'small text-danger mt-2 js-logistics-qr-result';

                button.disabled = false;
            }
        });
    });
});
/* AUTO_RACK_PREVIEW_V1 */
document.addEventListener('DOMContentLoaded', () => {
    const buildZoneCode = (zone) => {
        const words = String(zone || '')
            .trim()
            .split(/[^A-Za-z0-9]+/)
            .filter(Boolean);

        if (!words.length) {
            return '';
        }

        if (words.length >= 2) {
            return words
                .map((word) => word.charAt(0).toUpperCase())
                .join('');
        }

        return words[0]
            .substring(0, 4)
            .toUpperCase();
    };

    const getAreaCode = (value) => {
        const match = String(value || '')
            .trim()
            .match(/^Area\s+([A-E])(?:\b|\s*-)/i);

        return match
            ? match[1].toUpperCase()
            : '';
    };

    const refreshRackPreview = (form) => {
        const areaSelect = form.querySelector(
            '[name="sorting_area"]'
        );

        const rackInput = form.querySelector(
            '.js-auto-rack-number'
        );

        if (!areaSelect || !rackInput) {
            return;
        }

        const zoneCode = buildZoneCode(
            form.dataset.sortZone
        );

        const areaCode = getAreaCode(
            areaSelect.value
        );

        const shipmentId = Number(
            form.dataset.shipmentId || 0
        );

        if (
            !zoneCode ||
            !areaCode ||
            !shipmentId
        ) {
            rackInput.value = '';
            rackInput.placeholder =
                'Select a sorting area first';
            return;
        }

        const sequence = String(
            shipmentId
        ).padStart(4, '0');

        rackInput.value =
            `${zoneCode}-${areaCode}-${sequence}`;
    };

    document.querySelectorAll(
        '.js-sort-package-form'
    ).forEach((form) => {
        const areaSelect = form.querySelector(
            '[name="sorting_area"]'
        );

        if (!areaSelect) {
            return;
        }

        areaSelect.addEventListener(
            'change',
            () => refreshRackPreview(form)
        );

        refreshRackPreview(form);
    });
});