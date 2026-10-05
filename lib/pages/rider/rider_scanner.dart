import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../models/parcel_model.dart';
import '../../styles/rider_style.dart';

class RiderScanner extends StatefulWidget {
  final ParcelModel? scannedParcel;

  const RiderScanner({super.key, this.scannedParcel});

  @override
  State<RiderScanner> createState() => _RiderScannerState();
}

class _RiderScannerState extends State<RiderScanner> {
  late final MobileScannerController _scannerController;

  String? _scannedCode;
  bool _hasScanned = false;

  @override
  void initState() {
    super.initState();

    _scannerController = MobileScannerController(
      facing: CameraFacing.back,
      torchEnabled: false,
      detectionSpeed: DetectionSpeed.noDuplicates,

      // QR CODE ONLY
      formats: const [BarcodeFormat.qrCode],

      autoZoom: true,
    );
  }

  @override
  void dispose() {
    _scannerController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final parcel = widget.scannedParcel;

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: _buildAppBar(),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Expanded(child: _cameraScanner()),

            _scannerControls(),

            _bottomPanel(parcel),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // APP BAR
  // ============================================================

  PreferredSizeWidget _buildAppBar() {
    return AppBar(
      backgroundColor: Colors.black,
      elevation: 0,
      surfaceTintColor: Colors.transparent,
      iconTheme: const IconThemeData(color: Colors.white),
      titleSpacing: 4,
      title: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Scan Parcel',
            style: TextStyle(
              color: Colors.white,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          SizedBox(height: 2),
          Text(
            'Scan the parcel QR code',
            style: TextStyle(
              color: Colors.white60,
              fontSize: 11,
              fontWeight: FontWeight.w400,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // CAMERA
  // ============================================================

  Widget _cameraScanner() {
    return LayoutBuilder(
      builder: (context, constraints) {
        final size = constraints.biggest;

        // ------------------------------------------------------
        // SQUARE QR SCANNING AREA
        // ------------------------------------------------------
        //
        // Around 64% of the available screen width.
        // This creates a square frame similar to the physical
        // proportions of a normal QR code.
        //
        final scanSize = size.width * 0.64;

        final scanWindow = Rect.fromCenter(
          center: Offset(size.width / 2, size.height * 0.43),
          width: scanSize,
          height: scanSize,
        );

        return Stack(
          fit: StackFit.expand,
          children: [
            // --------------------------------------------------
            // LIVE CAMERA
            // --------------------------------------------------

            MobileScanner(
              controller: _scannerController,
              fit: BoxFit.cover,
              scanWindow: scanWindow,
              onDetect: _handleDetection,
              errorBuilder: (context, error) {
                return _cameraError(error);
              },
            ),

            // --------------------------------------------------
            // SQUARE QR SCAN WINDOW
            // --------------------------------------------------
            ScanWindowOverlay(
              controller: _scannerController,
              scanWindow: scanWindow,
              borderColor: RiderColors.primary,
              borderWidth: 3,
              borderRadius: BorderRadius.circular(16),
              color: Colors.black.withValues(alpha: 0.58),
            ),

            // --------------------------------------------------
            // SCAN GUIDE
            // --------------------------------------------------
            Positioned(
              left: 0,
              right: 0,
              top: scanWindow.bottom + 18,
              child: Column(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.45),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      _hasScanned
                          ? 'QR code detected'
                          : 'Place the QR code inside the frame',
                      style: TextStyle(
                        color: _hasScanned ? Colors.greenAccent : Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),

                  if (_scannedCode != null) ...[
                    const SizedBox(height: 8),

                    Container(
                      constraints: BoxConstraints(maxWidth: size.width * 0.80),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 7,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.55),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        _scannedCode!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        );
      },
    );
  }

  // ============================================================
  // QR CODE DETECTION
  // ============================================================

  void _handleDetection(BarcodeCapture capture) {
    if (_hasScanned || capture.barcodes.isEmpty) {
      return;
    }

    final barcode = capture.barcodes.first;
    final value = barcode.rawValue;

    // Make sure the detected format is actually QR.
    if (barcode.format != BarcodeFormat.qrCode) {
      return;
    }

    if (value == null || value.trim().isEmpty) {
      return;
    }

    setState(() {
      _scannedCode = value;
      _hasScanned = true;
    });

    // Stop scanning after successful detection.
    // This prevents the same QR code from being detected repeatedly.
    _scannerController.stop();

    _showScanSuccess(value);
  }

  // ============================================================
  // SUCCESS MESSAGE
  // ============================================================

  void _showScanSuccess(String value) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: RiderColors.success,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        content: Row(
          children: [
            const Icon(Icons.check_circle_rounded, color: Colors.white),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                'QR code detected: $value',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // CAMERA ERROR
  // ============================================================

  Widget _cameraError(MobileScannerException error) {
    return Container(
      color: Colors.black,
      alignment: Alignment.center,
      padding: const EdgeInsets.all(30),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            height: 64,
            width: 64,
            decoration: BoxDecoration(
              color: RiderColors.lightPurple,
              borderRadius: BorderRadius.circular(18),
            ),
            child: const Icon(
              Icons.camera_alt_outlined,
              color: RiderColors.primary,
              size: 32,
            ),
          ),

          const SizedBox(height: 16),

          const Text(
            'Camera unavailable',
            style: TextStyle(
              color: Colors.white,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),

          const SizedBox(height: 8),

          const Text(
            'Please allow camera permission and try again.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.white70, fontSize: 12),
          ),

          const SizedBox(height: 20),

          ElevatedButton.icon(
            onPressed: () {
              _scannerController.start();
            },
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Try Again'),
            style: ElevatedButton.styleFrom(
              backgroundColor: RiderColors.primary,
              foregroundColor: Colors.white,
              elevation: 0,
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // CAMERA CONTROLS
  // ============================================================

  Widget _scannerControls() {
    return Container(
      color: Colors.black,
      padding: const EdgeInsets.fromLTRB(18, 10, 18, 14),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          _scannerControlButton(
            icon: _scannerController.value.torchState == TorchState.on
                ? Icons.flash_on_rounded
                : Icons.flash_off_rounded,
            label: 'Flash',
            onTap: () async {
              await _scannerController.toggleTorch();

              if (mounted) {
                setState(() {});
              }
            },
          ),

          const SizedBox(width: 18),

          _scannerControlButton(
            icon: Icons.refresh_rounded,
            label: 'Reset',
            onTap: _resetScanner,
          ),
        ],
      ),
    );
  }

  Widget _scannerControlButton({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.white.withValues(alpha: 0.10),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
          child: Row(
            children: [
              Icon(icon, color: Colors.white, size: 20),

              const SizedBox(width: 7),

              Text(
                label,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // RESET
  // ============================================================

  void _resetScanner() {
    setState(() {
      _scannedCode = null;
      _hasScanned = false;
    });

    _scannerController.start();
  }

  // ============================================================
  // BOTTOM PANEL
  // ============================================================

  Widget _bottomPanel(ParcelModel? parcel) {
    return Container(
      width: double.infinity,
      constraints: const BoxConstraints(maxHeight: 285),
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
      decoration: const BoxDecoration(
        color: RiderColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      child: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        child: parcel == null ? _waitingPanel() : _parcelFoundPanel(parcel),
      ),
    );
  }

  // ============================================================
  // WAITING PANEL
  // ============================================================

  Widget _waitingPanel() {
    return Column(
      children: [
        Container(
          height: 4,
          width: 42,
          decoration: BoxDecoration(
            color: RiderColors.border,
            borderRadius: BorderRadius.circular(10),
          ),
        ),

        const SizedBox(height: 14),

        Container(
          height: 48,
          width: 48,
          decoration: BoxDecoration(
            color: RiderColors.lightPurple,
            borderRadius: BorderRadius.circular(15),
          ),
          child: const Icon(
            Icons.qr_code_scanner_rounded,
            color: RiderColors.primary,
            size: 27,
          ),
        ),

        const SizedBox(height: 9),

        const Text('Ready to Scan', style: RiderStyle.cardTitle),

        const SizedBox(height: 3),

        Text(
          _scannedCode == null
              ? 'Point your camera at the parcel QR code.'
              : 'QR code scanned successfully.',
          textAlign: TextAlign.center,
          style: RiderStyle.small,
        ),

        if (_scannedCode != null) ...[
          const SizedBox(height: 10),

          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: RiderColors.lightGreen,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.check_circle_rounded,
                  color: RiderColors.success,
                  size: 18,
                ),

                const SizedBox(width: 8),

                Expanded(
                  child: Text(
                    _scannedCode!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: RiderColors.success,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  // ============================================================
  // PARCEL FOUND
  // ============================================================

  Widget _parcelFoundPanel(ParcelModel parcel) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Center(
          child: Container(
            height: 4,
            width: 42,
            decoration: BoxDecoration(
              color: RiderColors.border,
              borderRadius: BorderRadius.circular(10),
            ),
          ),
        ),

        const SizedBox(height: 14),

        Row(
          children: [
            Container(
              height: 44,
              width: 44,
              decoration: BoxDecoration(
                color: RiderColors.lightGreen,
                borderRadius: BorderRadius.circular(13),
              ),
              child: const Icon(
                Icons.check_circle_outline_rounded,
                color: RiderColors.success,
                size: 23,
              ),
            ),

            const SizedBox(width: 12),

            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Parcel Found', style: RiderStyle.sectionTitle),
                  SizedBox(height: 2),
                  Text(
                    'Delivery information is ready.',
                    style: RiderStyle.small,
                  ),
                ],
              ),
            ),
          ],
        ),

        const SizedBox(height: 14),

        _infoRow('Tracking Number', parcel.trackingNumber),

        _infoRow('Customer', parcel.customerName),

        _infoRow('Address', parcel.deliveryAddress),

        _infoRow('Status', parcel.status),

        const SizedBox(height: 8),

        SizedBox(
          width: double.infinity,
          height: 50,
          child: ElevatedButton(
            onPressed: () {
              // Navigate to DeliveryConfirmation later.
            },
            style: RiderStyle.primaryButton,
            child: const Text(
              'Continue Delivery',
              style: RiderStyle.buttonText,
            ),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // INFO ROW
  // ============================================================

  Widget _infoRow(String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 9),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 105, child: Text(title, style: RiderStyle.small)),

          const SizedBox(width: 8),

          Expanded(
            child: Text(value, softWrap: true, style: RiderStyle.cardTitle),
          ),
        ],
      ),
    );
  }
}
