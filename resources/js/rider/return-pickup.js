import { Html5Qrcode } from 'html5-qrcode';

let activeCamera = null;
let scannerBusy = false;

for (const form of document.querySelectorAll('[data-return-pickup]')) {
    const start = form.querySelector('[data-return-scan]');
    const stop = form.querySelector('[data-return-stop]');
    const reader = form.querySelector('[data-return-camera]');
    const status = form.querySelector('[data-return-camera-status]');
    const image = form.querySelector('[data-return-image]');
    const input = form.elements.pickup_code;

    function useCode(text) {
        const code = text.trim();
        if (!/^[0-9a-f]{24}$/i.test(code)) {
            status.textContent = 'Use the Return Pickup QR from the buyer’s order.';
            return false;
        }

        input.value = code.toUpperCase();
        status.textContent = 'Code scanned. Select Verify pickup to confirm handover.';
        return true;
    }

    async function closeCamera() {
        if (activeCamera?.reader !== reader) return;

        await activeCamera.scanner.stop();
        activeCamera.scanner.clear();
        activeCamera = null;
        reader.hidden = true;
        stop.hidden = true;
        start.disabled = false;
    }

    start.hidden = false;
    form.querySelector('[data-return-image-options]').hidden = false;

    start.addEventListener('click', async () => {
        if (scannerBusy) return;

        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            status.textContent = 'Live camera needs HTTPS or localhost. Scan a screenshot or enter the pickup code.';
            return;
        }

        scannerBusy = true;
        start.disabled = true;
        status.textContent = 'Starting camera…';

        try {
            await activeCamera?.close();
            reader.hidden = false;
            const scanner = new Html5Qrcode(reader.id);
            let decoded = false;

            await scanner.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                },
                (text) => {
                    if (!decoded && useCode(text)) {
                        decoded = true;
                        if (!scannerBusy) stop.click();
                    }
                },
            );

            activeCamera = { scanner, reader, close: closeCamera };
            stop.hidden = false;

            if (decoded) {
                await closeCamera();
            } else {
                status.textContent = 'Hold the entire buyer QR, including its white border, steady in the camera preview.';
            }
        } catch {
            if (activeCamera?.reader !== reader) reader.hidden = true;
            start.disabled = activeCamera?.reader === reader;
            status.textContent = 'Camera could not complete the scan. Check camera permission, scan a screenshot, or enter the pickup code.';
        } finally {
            scannerBusy = false;
        }
    });

    stop.addEventListener('click', async () => {
        if (scannerBusy) return;

        scannerBusy = true;
        try {
            await closeCamera();
        } catch {
            status.textContent = 'Could not stop the camera. You can still enter the pickup code.';
        } finally {
            scannerBusy = false;
        }
    });

    image.addEventListener('change', async () => {
        const file = image.files[0];
        image.value = '';
        if (!file || scannerBusy) return;

        scannerBusy = true;
        status.textContent = 'Reading QR image…';
        let scanner;

        try {
            await activeCamera?.close();
            scanner = new Html5Qrcode(reader.id);
            useCode(await scanner.scanFile(file, false));
        } catch {
            status.textContent = 'Could not read the image. Try a clear screenshot of the full QR or enter the pickup code.';
        } finally {
            try {
                scanner?.clear();
            } finally {
                scannerBusy = false;
            }
        }
    });
}
