import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../models/parcel_model.dart';
import '../../styles/logistics_style.dart';
import 'parcel_information.dart';

class ParcelScanner extends StatefulWidget {
  final ParcelModel? scannedParcel;

  const ParcelScanner({super.key, this.scannedParcel});

  @override
  State<ParcelScanner> createState() => _ParcelScannerState();
}

class _ParcelScannerState extends State<ParcelScanner> {
  late final MobileScannerController _scannerController;

  String? _scannedCode;
  String? _trackingNumber;
  String? _scanError;

  bool _hasScanned = false;

  ParcelModel? _parcel;

  @override
  void initState() {
    super.initState();

    _scannerController = MobileScannerController(
      facing: CameraFacing.back,
      torchEnabled: false,
      detectionSpeed: DetectionSpeed.noDuplicates,
      formats: const [BarcodeFormat.qrCode],
      autoZoom: true,
    );

    _parcel = widget.scannedParcel;

    if (_parcel != null) {
      _trackingNumber = _parcel!.trackingNumber;
    }
  }

  @override
  void dispose() {
    _scannerController.dispose();

    super.dispose();
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,

      appBar: _buildAppBar(),

      body: SafeArea(
        top: false,

        child: Column(
          children: [
            Expanded(child: _cameraScanner()),

            _scannerControls(),

            _bottomPanel(),
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
            'Parcel Scanner',

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

        final scanSize = size.width * 0.64;

        final scanWindow = Rect.fromCenter(
          center: Offset(size.width / 2, size.height * 0.43),

          width: scanSize,

          height: scanSize,
        );

        return Stack(
          fit: StackFit.expand,

          children: [
            MobileScanner(
              controller: _scannerController,

              fit: BoxFit.cover,

              scanWindow: scanWindow,

              onDetect: _handleDetection,

              errorBuilder: (context, error) {
                return _cameraError(error);
              },
            ),

            ScanWindowOverlay(
              controller: _scannerController,

              scanWindow: scanWindow,

              borderColor: LogisticsColors.primary,

              borderWidth: 3,

              borderRadius: BorderRadius.circular(16),

              color: Colors.black.withValues(alpha: 0.58),
            ),

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
                      _scannerMessage,

                      style: TextStyle(
                        color: _scanError != null
                            ? Colors.orangeAccent
                            : _hasScanned
                            ? Colors.greenAccent
                            : Colors.white,

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
  // SCANNER MESSAGE
  // ============================================================

  String get _scannerMessage {
    if (_scanError != null) {
      return 'Invalid parcel QR code';
    }

    if (_hasScanned) {
      return 'QR code detected';
    }

    return 'Place the QR code inside the frame';
  }

  // ============================================================
  // QR DETECTION
  // ============================================================

  void _handleDetection(BarcodeCapture capture) {
    if (_hasScanned || capture.barcodes.isEmpty) {
      return;
    }

    final barcode = capture.barcodes.first;

    if (barcode.format != BarcodeFormat.qrCode) {
      return;
    }

    final value = barcode.rawValue;

    if (value == null || value.trim().isEmpty) {
      return;
    }

    final cleaned = value.trim();

    final tracking = _extractTrackingNumber(cleaned);

    if (tracking.isEmpty) {
      setState(() {
        _scannedCode = cleaned;

        _trackingNumber = null;

        _scanError = 'QR code does not contain a valid tracking number.';

        _hasScanned = true;

        _parcel = null;
      });

      _scannerController.stop();

      _showMessage('Invalid parcel QR code.', isError: true);

      return;
    }

    setState(() {
      _scannedCode = cleaned;

      _trackingNumber = tracking;

      _scanError = null;

      _hasScanned = true;

      if (_parcel != null &&
          _parcel!.trackingNumber.trim().toLowerCase() !=
              tracking.trim().toLowerCase()) {
        _parcel = null;
      }
    });

    _scannerController.stop();

    _showMessage('QR code detected.');
  }

  // ============================================================
  // EXTRACT TRACKING NUMBER
  // ============================================================

  String _extractTrackingNumber(String rawValue) {
    final value = rawValue.trim();

    if (value.isEmpty) {
      return '';
    }

    try {
      final decoded = jsonDecode(value);

      if (decoded is Map) {
        final possibleTracking =
            decoded['t'] ??
            decoded['tracking_number'] ??
            decoded['tracking'] ??
            decoded['code'];

        if (possibleTracking != null) {
          final tracking = possibleTracking.toString().trim();

          if (tracking.isNotEmpty) {
            return tracking;
          }
        }
      }
    } catch (_) {
      // Plain tracking number QR code is allowed.
    }

    return value;
  }

  // ============================================================
  // MESSAGE
  // ============================================================

  void _showMessage(String message, {bool isError = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,

        backgroundColor: isError ? Colors.red : LogisticsColors.success,

        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),

        content: Text(message),
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
              color: LogisticsColors.lightPurple,

              borderRadius: BorderRadius.circular(18),
            ),

            child: const Icon(
              Icons.camera_alt_outlined,

              color: LogisticsColors.primary,

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
              backgroundColor: LogisticsColors.primary,

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
  // CONTROLS
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

              if (!mounted) return;

              setState(() {});
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

      _trackingNumber = null;

      _scanError = null;

      _hasScanned = false;

      _parcel = widget.scannedParcel;
    });

    _scannerController.start();
  }

  // ============================================================
  // BOTTOM PANEL
  // ============================================================

  Widget _bottomPanel() {
    return Container(
      width: double.infinity,

      constraints: const BoxConstraints(maxHeight: 310),

      padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),

      decoration: const BoxDecoration(
        color: LogisticsColors.white,

        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),

      child: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),

        child: _parcel != null ? _parcelPanel(_parcel!) : _scannerResultPanel(),
      ),
    );
  }

  // ============================================================
  // SCANNER RESULT PANEL
  // ============================================================

  Widget _scannerResultPanel() {
    final hasTracking =
        _trackingNumber != null && _trackingNumber!.trim().isNotEmpty;

    return Column(
      children: [
        Container(
          height: 4,

          width: 42,

          decoration: BoxDecoration(
            color: LogisticsColors.border,

            borderRadius: BorderRadius.circular(10),
          ),
        ),

        const SizedBox(height: 14),

        Container(
          height: 48,

          width: 48,

          decoration: BoxDecoration(
            color: LogisticsColors.lightPurple,

            borderRadius: BorderRadius.circular(15),
          ),

          child: const Icon(
            Icons.qr_code_scanner_rounded,

            color: LogisticsColors.primary,

            size: 27,
          ),
        ),

        const SizedBox(height: 9),

        Text(
          _scanError != null
              ? 'Invalid QR Code'
              : hasTracking
              ? 'Tracking Number Detected'
              : 'Ready to Scan',

          style: LogisticsStyle.cardTitle,
        ),

        const SizedBox(height: 4),

        Text(
          _scanError ??
              (hasTracking
                  ? 'QR code was read successfully. Parcel lookup is unavailable until the Logistics API is enabled.'
                  : 'Point your camera at the parcel QR code.'),

          textAlign: TextAlign.center,

          style: LogisticsStyle.small,
        ),

        if (hasTracking) ...[
          const SizedBox(height: 12),

          Container(
            width: double.infinity,

            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),

            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,

              borderRadius: BorderRadius.circular(12),
            ),

            child: Row(
              children: [
                const Icon(
                  Icons.qr_code_2_rounded,

                  color: LogisticsColors.primary,

                  size: 19,
                ),

                const SizedBox(width: 9),

                Expanded(
                  child: Text(
                    _trackingNumber!,

                    maxLines: 1,

                    overflow: TextOverflow.ellipsis,

                    style: const TextStyle(
                      color: LogisticsColors.primary,

                      fontSize: 12,

                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 12),

          Container(
            width: double.infinity,

            padding: const EdgeInsets.all(12),

            decoration: BoxDecoration(
              color: Colors.orange.withValues(alpha: 0.08),

              borderRadius: BorderRadius.circular(12),
            ),

            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Icon(
                  Icons.info_outline_rounded,

                  color: LogisticsColors.warning,

                  size: 18,
                ),

                SizedBox(width: 8),

                Expanded(
                  child: Text(
                    'Scanning currently reads the QR only. Backend parcel validation and processing are not available yet.',

                    style: LogisticsStyle.small,
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
  // EXISTING PARCEL PANEL
  // ============================================================

  Widget _parcelPanel(ParcelModel parcel) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        Center(
          child: Container(
            height: 4,

            width: 42,

            decoration: BoxDecoration(
              color: LogisticsColors.border,

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
                color: LogisticsColors.lightPurple,

                borderRadius: BorderRadius.circular(13),
              ),

              child: const Icon(
                Icons.inventory_2_outlined,

                color: LogisticsColors.primary,

                size: 23,
              ),
            ),

            const SizedBox(width: 12),

            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  Text(
                    'Parcel Information',

                    style: LogisticsStyle.sectionTitle,
                  ),

                  SizedBox(height: 2),

                  Text(
                    'Parcel data was provided to the scanner.',

                    style: LogisticsStyle.small,
                  ),
                ],
              ),
            ),
          ],
        ),

        const SizedBox(height: 14),

        _infoRow('Tracking Number', _displayValue(parcel.trackingNumber)),

        _infoRow('Customer', _displayValue(parcel.customerName)),

        _infoRow('Status', _displayValue(parcel.status)),

        const SizedBox(height: 8),

        SizedBox(
          width: double.infinity,

          height: 50,

          child: ElevatedButton(
            onPressed: () {
              _openParcelInformation(parcel);
            },

            style: LogisticsStyle.primaryButton,

            child: const Text(
              'View Parcel Information',

              style: LogisticsStyle.buttonText,
            ),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // OPEN PARCEL INFORMATION
  // ============================================================

  Future<void> _openParcelInformation(ParcelModel parcel) async {
    await Navigator.push(
      context,

      MaterialPageRoute(
        builder: (context) => ParcelInformation(parcel: parcel),
      ),
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
          SizedBox(width: 105, child: Text(title, style: LogisticsStyle.small)),

          const SizedBox(width: 8),

          Expanded(
            child: Text(value, softWrap: true, style: LogisticsStyle.cardTitle),
          ),
        ],
      ),
    );
  }

  String _displayValue(String value, {String fallback = 'Not available'}) {
    final cleaned = value.trim();

    if (cleaned.isEmpty) {
      return fallback;
    }

    return cleaned;
  }
}
