import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../models/parcel_model.dart';
import '../../sevices/api_services.dart';
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

  ParcelModel? _parcel;

  String? _trackingNumber;
  String? _message;

  bool _hasScanned = false;
  bool _isLookingUp = false;

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
    _trackingNumber = _parcel?.trackingNumber;

    if (_parcel != null) {
      _message = 'Parcel loaded successfully.';
      _hasScanned = true;
    }
  }

  @override
  void dispose() {
    _scannerController.dispose();
    super.dispose();
  }

  // ============================================================
  // QR DETECTION
  // ============================================================

  Future<void> _handleDetection(BarcodeCapture capture) async {
    if (_hasScanned || _isLookingUp) {
      return;
    }

    if (capture.barcodes.isEmpty) {
      return;
    }

    final String? rawValue = capture.barcodes.first.rawValue?.trim();

    if (rawValue == null || rawValue.isEmpty) {
      return;
    }

    final String? trackingNumber = _extractTrackingNumber(rawValue);

    if (trackingNumber == null || trackingNumber.trim().isEmpty) {
      if (!mounted) return;

      setState(() {
        _hasScanned = true;
        _message = 'The QR code does not contain a valid tracking number.';
      });

      await _scannerController.stop();
      return;
    }

    await _scannerController.stop();

    if (!mounted) return;

    setState(() {
      _hasScanned = true;
      _trackingNumber = trackingNumber.trim();
      _message = null;
    });

    await _lookupParcel(trackingNumber.trim());
  }

  // ============================================================
  // EXTRACT TRACKING NUMBER
  // ============================================================

  String? _extractTrackingNumber(String raw) {
    final String cleaned = raw.trim();

    if (cleaned.isEmpty) {
      return null;
    }

    try {
      final dynamic decoded = jsonDecode(cleaned);

      if (decoded is Map) {
        const List<String> possibleKeys = [
          'tracking_number',
          'tracking_code',
          'tracking',
          'code',
          't',
        ];

        for (final String key in possibleKeys) {
          final String? value = decoded[key]?.toString().trim();

          if (value != null && value.isNotEmpty) {
            return value;
          }
        }
      }
    } catch (_) {
      // Plain text QR codes are also accepted.
    }

    return cleaned;
  }

  // ============================================================
  // LOOKUP PARCEL
  // ============================================================

  Future<void> _lookupParcel(String tracking) async {
    if (!mounted) return;

    setState(() {
      _isLookingUp = true;
      _message = 'Verifying parcel...';
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.getLogisticsShipments();

      if (!mounted) return;

      if (result['success'] != true) {
        setState(() {
          _parcel = null;
          _isLookingUp = false;
          _message =
              result['message']?.toString() ?? 'Unable to verify parcel.';
        });

        return;
      }

      final dynamic rawData = result['data'];

      if (rawData is! List) {
        setState(() {
          _parcel = null;
          _isLookingUp = false;
          _message = 'No shipment data was returned.';
        });

        return;
      }

      ParcelModel? found;

      final String normalizedTracking = tracking.trim().toLowerCase();

      for (final dynamic item in rawData) {
        if (item is! Map) {
          continue;
        }

        final Map<String, dynamic> map = Map<String, dynamic>.from(item);

        final String trackingNumber =
            map['tracking_number']?.toString().trim().toLowerCase() ?? '';

        final String trackingCode =
            map['tracking_code']?.toString().trim().toLowerCase() ?? '';

        final bool matches =
            trackingNumber == normalizedTracking ||
            trackingCode == normalizedTracking;

        if (!matches) {
          continue;
        }

        try {
          map['order_date'] ??= map['created_at'] ?? '';

          map['tracking_number'] ??= map['tracking_code'] ?? '';

          map['customer_name'] ??= '';
          map['customer_phone'] ??= '';
          map['delivery_address'] ??= '';
          map['rider_name'] ??= '';
          map['status'] ??= 'pending';

          found = ParcelModel.fromJson(map);
        } catch (e) {
          debugPrint('Unable to parse scanned parcel: $e');
        }

        break;
      }

      setState(() {
        _parcel = found;
        _isLookingUp = false;

        if (found != null) {
          _message = 'Parcel verified successfully.';
        } else {
          _message = 'No parcel matched this tracking number.';
        }
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _parcel = null;
        _isLookingUp = false;
        _message = 'Unable to verify parcel: $e';
      });
    }
  }

  // ============================================================
  // RESET SCANNER
  // ============================================================

  Future<void> _resetScanner() async {
    if (!mounted) return;

    setState(() {
      _parcel = null;
      _trackingNumber = null;
      _message = null;
      _hasScanned = false;
      _isLookingUp = false;
    });

    await _scannerController.start();
  }

  // ============================================================
  // OPEN PARCEL
  // ============================================================

  Future<void> _openParcel() async {
    final ParcelModel? parcel = _parcel;

    if (parcel == null) {
      return;
    }

    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (BuildContext context) {
          return ParcelInformation(parcel: parcel);
        },
      ),
    );

    if (!mounted) return;

    await _lookupParcel(parcel.trackingNumber);
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
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
              'Scan and verify parcel QR code',
              style: TextStyle(color: Colors.white60, fontSize: 11),
            ),
          ],
        ),
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  MobileScanner(
                    controller: _scannerController,
                    onDetect: _handleDetection,
                    errorBuilder:
                        (BuildContext context, MobileScannerException error) {
                          return _cameraError(error);
                        },
                  ),

                  // Scanner frame
                  IgnorePointer(
                    child: Center(
                      child: Container(
                        width: 245,
                        height: 245,
                        decoration: BoxDecoration(
                          border: Border.all(color: Colors.white, width: 3),
                          borderRadius: BorderRadius.circular(24),
                        ),
                      ),
                    ),
                  ),

                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 26,
                    child: Center(
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 8,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.60),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Text(
                          'Place the parcel QR code inside the frame',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),

            _bottomPanel(),
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
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.camera_alt_outlined, color: Colors.white, size: 48),
          const SizedBox(height: 14),
          const Text(
            'Camera Unavailable',
            style: TextStyle(
              color: Colors.white,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Unable to open the scanner camera.\n${error.errorCode}',
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white70,
              fontSize: 12,
              height: 1.5,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // BOTTOM PANEL
  // ============================================================

  Widget _bottomPanel() {
    final bool verified = _parcel != null;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 22),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 42,
            height: 4,
            decoration: BoxDecoration(
              color: LogisticsColors.border,
              borderRadius: BorderRadius.circular(10),
            ),
          ),

          const SizedBox(height: 14),

          if (_isLookingUp) ...[
            const LinearProgressIndicator(color: LogisticsColors.primary),
            const SizedBox(height: 14),
          ],

          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              color: verified
                  ? LogisticsColors.success.withValues(alpha: 0.10)
                  : LogisticsColors.lightPurple,
              shape: BoxShape.circle,
            ),
            child: Icon(
              verified ? Icons.verified_rounded : Icons.qr_code_2_rounded,
              color: verified
                  ? LogisticsColors.success
                  : LogisticsColors.primary,
              size: 32,
            ),
          ),

          const SizedBox(height: 10),

          Text(
            _trackingNumber ?? 'Ready to Scan',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: LogisticsStyle.sectionTitle,
          ),

          const SizedBox(height: 6),

          Text(
            _message ?? 'Point your camera at the parcel QR code.',
            textAlign: TextAlign.center,
            style: LogisticsStyle.small,
          ),

          if (verified) ...[
            const SizedBox(height: 14),

            _parcelPreview(_parcel!),

            const SizedBox(height: 14),

            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton.icon(
                onPressed: _isLookingUp ? null : _openParcel,
                icon: const Icon(Icons.inventory_2_outlined),
                label: const Text('View Parcel Information'),
                style: LogisticsStyle.primaryButton,
              ),
            ),
          ],

          if (_hasScanned) ...[
            const SizedBox(height: 8),

            TextButton.icon(
              onPressed: _isLookingUp ? null : _resetScanner,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Scan Another'),
            ),
          ],
        ],
      ),
    );
  }

  // ============================================================
  // PARCEL PREVIEW
  // ============================================================

  Widget _parcelPreview(ParcelModel parcel) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: LogisticsColors.background,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: LogisticsColors.border),
      ),
      child: Column(
        children: [
          _previewRow('Customer', parcel.customerName),
          const SizedBox(height: 7),
          _previewRow('Status', parcel.status),
          const SizedBox(height: 7),
          _previewRow('Address', parcel.deliveryAddress),
        ],
      ),
    );
  }

  Widget _previewRow(String title, String value) {
    final String display = value.trim().isEmpty
        ? 'Not available'
        : value.trim();

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(width: 68, child: Text(title, style: LogisticsStyle.small)),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            display,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.right,
            style: LogisticsStyle.cardTitle,
          ),
        ),
      ],
    );
  }
}
