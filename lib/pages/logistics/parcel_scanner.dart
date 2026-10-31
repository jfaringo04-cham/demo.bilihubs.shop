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
      formats: const <BarcodeFormat>[BarcodeFormat.qrCode],
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
    if (_hasScanned || _isLookingUp || capture.barcodes.isEmpty) {
      return;
    }

    final Barcode barcode = capture.barcodes.first;

    if (barcode.format != BarcodeFormat.qrCode) {
      return;
    }

    final String? rawValue = barcode.rawValue?.trim();

    if (rawValue == null || rawValue.isEmpty) {
      return;
    }

    final String? trackingNumber = _extractTrackingNumber(rawValue);

    if (trackingNumber == null || trackingNumber.trim().isEmpty) {
      if (!mounted) {
        return;
      }

      setState(() {
        _hasScanned = true;

        _message =
            'The QR code does not contain '
            'a valid BiliHub tracking number.';
      });

      try {
        await _scannerController.stop();
      } catch (error) {
        debugPrint('Unable to stop logistics scanner: $error');
      }

      return;
    }

    try {
      await _scannerController.stop();
    } catch (error) {
      debugPrint('Unable to stop logistics scanner: $error');
    }

    if (!mounted) {
      return;
    }

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

    // ------------------------------------------------------------
    // JSON QR
    // ------------------------------------------------------------

    try {
      final dynamic decoded = jsonDecode(cleaned);

      if (decoded is Map) {
        const List<String> possibleKeys = <String>[
          'tracking_number',
          'tracking_code',
          'tracking',
          'waybill_number',
          'waybill',
          'code',
          't',
        ];

        for (final String key in possibleKeys) {
          final String value = decoded[key]?.toString().trim() ?? '';

          if (value.isNotEmpty) {
            return value;
          }
        }
      }
    } catch (_) {
      // Continue with URL/plain-text parsing.
    }

    // ------------------------------------------------------------
    // WEBSITE URL QR
    // ------------------------------------------------------------

    try {
      final Uri? uri = Uri.tryParse(cleaned);

      if (uri != null &&
          (uri.scheme.toLowerCase() == 'http' ||
              uri.scheme.toLowerCase() == 'https')) {
        const List<String> queryKeys = <String>[
          'tracking_number',
          'tracking',
          'tracking_code',
          'waybill',
          'code',
          't',
        ];

        for (final String key in queryKeys) {
          final String? value = uri.queryParameters[key];

          if (value != null && value.trim().isNotEmpty) {
            return value.trim();
          }
        }

        final List<String> segments = uri.pathSegments
            .where((String segment) => segment.trim().isNotEmpty)
            .toList();

        if (segments.isNotEmpty) {
          final String last = segments.last.trim();

          if (_looksLikeTrackingNumber(last)) {
            return last;
          }
        }

        return null;
      }
    } catch (_) {
      // Continue with plain-text handling.
    }

    // ------------------------------------------------------------
    // PLAIN TRACKING NUMBER
    // ------------------------------------------------------------

    if (_looksLikeTrackingNumber(cleaned)) {
      return cleaned;
    }

    /*
     * Keep compatibility with existing BiliHub QR values.
     * Laravel may generate tracking formats that are not known
     * to this mobile app yet, so non-URL plain text is accepted.
     */
    if (!cleaned.contains('\n') && cleaned.length <= 150) {
      return cleaned;
    }

    return null;
  }

  bool _looksLikeTrackingNumber(String value) {
    final String text = value.trim();

    if (text.length < 4 || text.length > 100) {
      return false;
    }

    return RegExp(r'^[A-Za-z0-9_-]+$').hasMatch(text);
  }

  // ============================================================
  // LOOKUP PARCEL
  // ============================================================

  Future<void> _lookupParcel(String tracking) async {
    if (!mounted) {
      return;
    }

    setState(() {
      _isLookingUp = true;

      _message = 'Verifying parcel and routing information...';
    });

    try {
      /*
       * There is currently no verified dedicated
       * logistics tracking-number lookup method in
       * ApiService, so use the existing shipments API.
       */
      final Map<String, dynamic> result =
          await ApiService.getLogisticsShipments(perPage: 100);

      if (!mounted) {
        return;
      }

      if (result['success'] != true) {
        final String message = result['message']?.toString().trim() ?? '';

        setState(() {
          _parcel = null;
          _isLookingUp = false;

          _message = message.isNotEmpty ? message : 'Unable to verify parcel.';
        });

        return;
      }

      final List<Map<String, dynamic>> shipmentMaps = _extractShipmentMaps(
        result,
      );

      if (shipmentMaps.isEmpty) {
        setState(() {
          _parcel = null;
          _isLookingUp = false;

          _message = 'No shipment data was returned.';
        });

        return;
      }

      final String normalizedTracking = tracking.trim().toLowerCase();

      Map<String, dynamic>? matchedMap;

      for (final Map<String, dynamic> item in shipmentMaps) {
        final String trackingNumber = _firstText(<dynamic>[
          item['tracking_number'],
          item['tracking_code'],
          item['tracking'],
          item['waybill_number'],
        ]).toLowerCase();

        if (trackingNumber == normalizedTracking) {
          matchedMap = item;

          break;
        }
      }

      if (matchedMap == null) {
        setState(() {
          _parcel = null;
          _isLookingUp = false;

          _message =
              'No parcel matched tracking number '
              '$tracking.';
        });

        return;
      }

      final Map<String, dynamic> normalized = _normalizeShipment(matchedMap);

      ParcelModel parcel;

      try {
        parcel = ParcelModel.fromJson(normalized);
      } catch (error) {
        debugPrint(
          'Unable to parse scanned logistics parcel: '
          '$error',
        );

        setState(() {
          _parcel = null;
          _isLookingUp = false;

          _message =
              'The parcel was found, but its '
              'shipment data could not be read.';
        });

        return;
      }

      /*
       * Once we know the shipment ID, request the
       * detailed shipment endpoint so the scanner
       * shows the newest routing, sorting and
       * line-haul information.
       */
      final ParcelModel freshParcel = await _loadFreshShipment(parcel);

      if (!mounted) {
        return;
      }

      setState(() {
        _parcel = freshParcel;

        _trackingNumber = freshParcel.trackingNumber.trim().isNotEmpty
            ? freshParcel.trackingNumber
            : tracking;

        _isLookingUp = false;

        _message = 'Parcel verified successfully.';
      });
    } catch (error) {
      debugPrint('Logistics scanner lookup error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _parcel = null;
        _isLookingUp = false;

        _message =
            'Unable to verify parcel. '
            'Please try again.';
      });
    }
  }

  // ============================================================
  // LOAD FRESH SHIPMENT
  // ============================================================

  Future<ParcelModel> _loadFreshShipment(ParcelModel fallback) async {
    try {
      final Map<String, dynamic> result = await ApiService.getLogisticsShipment(
        fallback.id,
      );

      if (result['success'] != true) {
        return fallback;
      }

      final Map<String, dynamic>? raw = _extractSingleShipment(result);

      if (raw == null) {
        return fallback;
      }

      return ParcelModel.fromJson(_normalizeShipment(raw));
    } catch (error) {
      debugPrint('Unable to refresh scanned shipment: $error');

      return fallback;
    }
  }

  // ============================================================
  // EXTRACT SHIPMENT RESPONSE
  // ============================================================

  List<Map<String, dynamic>> _extractShipmentMaps(Map<String, dynamic> result) {
    for (final String key in <String>['data', 'shipments', 'parcels']) {
      final List<Map<String, dynamic>> list = _toMapList(result[key]);

      if (list.isNotEmpty) {
        return list;
      }
    }

    final dynamic rawData = result['data'];

    if (rawData is Map) {
      final Map<String, dynamic> data = Map<String, dynamic>.from(rawData);

      for (final String key in <String>['data', 'shipments', 'parcels']) {
        final List<Map<String, dynamic>> list = _toMapList(data[key]);

        if (list.isNotEmpty) {
          return list;
        }
      }
    }

    return <Map<String, dynamic>>[];
  }

  Map<String, dynamic>? _extractSingleShipment(Map<String, dynamic> result) {
    final dynamic rawData = result['data'];

    if (rawData is Map) {
      final Map<String, dynamic> data = Map<String, dynamic>.from(rawData);

      final dynamic shipment = data['shipment'];

      if (shipment is Map) {
        return Map<String, dynamic>.from(shipment);
      }

      return data;
    }

    final dynamic shipment = result['shipment'];

    if (shipment is Map) {
      return Map<String, dynamic>.from(shipment);
    }

    return null;
  }

  List<Map<String, dynamic>> _toMapList(dynamic value) {
    if (value is! List) {
      return <Map<String, dynamic>>[];
    }

    final List<Map<String, dynamic>> result = <Map<String, dynamic>>[];

    for (final dynamic item in value) {
      if (item is Map<String, dynamic>) {
        result.add(Map<String, dynamic>.from(item));
      } else if (item is Map) {
        result.add(Map<String, dynamic>.from(item));
      }
    }

    return result;
  }

  // ============================================================
  // NORMALIZE SHIPMENT
  // ============================================================

  Map<String, dynamic> _normalizeShipment(Map<String, dynamic> raw) {
    final Map<String, dynamic> data = Map<String, dynamic>.from(raw);

    final dynamic nestedShipment = data['shipment'];

    if (nestedShipment is Map) {
      data.addAll(Map<String, dynamic>.from(nestedShipment));
    }

    final dynamic order = data['order'];

    if (order is Map) {
      final Map<String, dynamic> orderMap = Map<String, dynamic>.from(order);

      data['customer_name'] ??=
          orderMap['customer_name'] ??
          orderMap['buyer_name'] ??
          orderMap['name'];

      data['customer_phone'] ??=
          orderMap['customer_phone'] ?? orderMap['phone'];

      data['delivery_address'] ??=
          orderMap['delivery_address'] ?? orderMap['address'];

      data['order_date'] ??= orderMap['order_date'] ?? orderMap['created_at'];
    }

    final dynamic rider = data['rider'];

    if (rider is Map) {
      data['rider_id'] ??= rider['id'];

      data['rider_name'] ??= rider['name'];
    }

    final dynamic linehaulRider = data['linehaul_rider'];

    if (linehaulRider is Map) {
      data['linehaul_rider_id'] ??= linehaulRider['id'];

      data['linehaul_rider_name'] ??= linehaulRider['name'];
    }

    final dynamic originHub = data['origin_hub'];

    if (originHub is Map) {
      data['origin_hub_id'] ??= originHub['id'];

      data['origin_hub_name'] ??= originHub['name'];
    }

    final dynamic destinationHub = data['destination_hub'];

    if (destinationHub is Map) {
      data['destination_hub_id'] ??= destinationHub['id'];

      data['destination_hub_name'] ??= destinationHub['name'];
    }

    final dynamic currentHub = data['hub'];

    if (currentHub is Map) {
      data['current_hub_id'] ??= currentHub['id'];

      data['current_hub_name'] ??= currentHub['name'];
    }

    data['id'] ??= data['shipment_id'] ?? data['order_id'] ?? 0;

    data['shipment_id'] ??= data['id'];

    data['tracking_number'] ??=
        data['tracking_code'] ??
        data['tracking'] ??
        data['waybill_number'] ??
        '';

    data['customer_name'] ??=
        data['recipient_name'] ?? data['buyer_name'] ?? '';

    data['customer_phone'] ??= data['phone'] ?? data['recipient_phone'] ?? '';

    data['delivery_address'] ??=
        data['shipping_address'] ??
        data['recipient_address'] ??
        data['address'] ??
        '';

    data['pickup_address'] ??= data['seller_address'] ?? '';

    data['rider_name'] ??= '';

    data['status'] ??=
        data['shipment_status'] ?? data['delivery_status'] ?? 'pending';

    data['order_date'] ??= data['created_at'] ?? data['assigned_at'] ?? '';

    data['delivery_earnings'] ??= data['earnings'] ?? data['delivery_fee'] ?? 0;

    data['latitude'] ??= data['lat'];

    data['longitude'] ??= data['lng'] ?? data['lon'];

    return data;
  }

  // ============================================================
  // TEXT HELPERS
  // ============================================================

  String _firstText(List<dynamic> values) {
    for (final dynamic value in values) {
      final String text = value?.toString().trim() ?? '';

      if (text.isNotEmpty && text.toLowerCase() != 'null') {
        return text;
      }
    }

    return '';
  }

  String _display(String value, {String fallback = 'Not available'}) {
    final String text = value.trim();

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return fallback;
    }

    return text;
  }

  String _formatStatus(String value) {
    final String normalized = value
        .trim()
        .replaceAll('-', '_')
        .replaceAll(' ', '_');

    if (normalized.isEmpty) {
      return 'Not available';
    }

    return normalized
        .split('_')
        .where((String part) => part.isNotEmpty)
        .map(
          (String part) =>
              '${part[0].toUpperCase()}'
              '${part.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  String _routeText(ParcelModel parcel) {
    final String origin = parcel.originHubName.trim();

    final String destination = parcel.destinationHubName.trim();

    if (origin.isNotEmpty && destination.isNotEmpty) {
      return '$origin → $destination';
    }

    if (origin.isNotEmpty) {
      return 'Origin: $origin';
    }

    if (destination.isNotEmpty) {
      return 'Destination: $destination';
    }

    return 'Route not assigned';
  }

  // ============================================================
  // RESET SCANNER
  // ============================================================

  Future<void> _resetScanner() async {
    if (_isLookingUp) {
      return;
    }

    if (!mounted) {
      return;
    }

    setState(() {
      _parcel = null;

      _trackingNumber = null;
      _message = null;

      _hasScanned = false;
      _isLookingUp = false;
    });

    try {
      await _scannerController.start();
    } catch (error) {
      debugPrint('Logistics scanner restart error: $error');
    }
  }

  // ============================================================
  // TORCH
  // ============================================================

  Future<void> _toggleTorch() async {
    try {
      await _scannerController.toggleTorch();
    } catch (error) {
      debugPrint('Logistics scanner torch error: $error');
    }
  }

  // ============================================================
  // OPEN PARCEL
  // ============================================================

  Future<void> _openParcel() async {
    final ParcelModel? parcel = _parcel;

    if (parcel == null) {
      return;
    }

    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return ParcelInformation(parcel: parcel);
        },
      ),
    );

    if (!mounted) {
      return;
    }

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
        elevation: 0,
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
              'Verify parcel and hub routing',
              style: TextStyle(color: Colors.white60, fontSize: 11),
            ),
          ],
        ),
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Expanded(child: _scannerCamera()),

            _scannerControls(),

            _bottomPanel(),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // CAMERA
  // ============================================================

  Widget _scannerCamera() {
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints constraints) {
        final Size size = constraints.biggest;

        final double scanSize = size.width * 0.64;

        final Rect scanWindow = Rect.fromCenter(
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
              errorBuilder:
                  (BuildContext context, MobileScannerException error) {
                    return _cameraError(error);
                  },
            ),

            ScanWindowOverlay(
              controller: _scannerController,
              scanWindow: scanWindow,
              borderColor: LogisticsColors.primary,
              borderWidth: 3,
              borderRadius: BorderRadius.circular(18),
              color: Colors.black.withValues(alpha: 0.58),
            ),

            Positioned(
              left: 0,
              right: 0,
              top: scanWindow.bottom + 18,
              child: Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.55),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    _scannerGuideText,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }

  String get _scannerGuideText {
    if (_isLookingUp) {
      return 'Verifying parcel...';
    }

    if (_parcel != null) {
      return 'Parcel verified';
    }

    if (_hasScanned) {
      return 'Scan completed';
    }

    return 'Place the BiliHub parcel QR inside the frame';
  }

  // ============================================================
  // SCANNER CONTROLS
  // ============================================================

  Widget _scannerControls() {
    return Container(
      color: Colors.black,
      padding: const EdgeInsets.fromLTRB(18, 8, 18, 12),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          ValueListenableBuilder<MobileScannerState>(
            valueListenable: _scannerController,
            builder:
                (
                  BuildContext context,
                  MobileScannerState state,
                  Widget? child,
                ) {
                  return _controlButton(
                    icon: state.torchState == TorchState.on
                        ? Icons.flash_on_rounded
                        : Icons.flash_off_rounded,
                    label: 'Flash',
                    onTap: _toggleTorch,
                  );
                },
          ),

          const SizedBox(width: 16),

          _controlButton(
            icon: Icons.refresh_rounded,
            label: 'Reset',
            onTap: _resetScanner,
          ),
        ],
      ),
    );
  }

  Widget _controlButton({
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
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
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
            'Unable to open the scanner camera.\n'
            '${error.errorCode}',
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white70,
              fontSize: 12,
              height: 1.5,
            ),
          ),

          const SizedBox(height: 18),

          ElevatedButton.icon(
            onPressed: () async {
              try {
                await _scannerController.start();
              } catch (scanError) {
                debugPrint(
                  'Logistics camera restart error: '
                  '$scanError',
                );
              }
            },
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Try Again'),
            style: ElevatedButton.styleFrom(
              backgroundColor: LogisticsColors.primary,
              foregroundColor: Colors.white,
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
      constraints: const BoxConstraints(maxHeight: 430),
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 22),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
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
              _message ??
                  'Point your camera at the '
                      'BiliHub parcel QR code.',
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
      ),
    );
  }

  // ============================================================
  // PARCEL PREVIEW
  // ============================================================

  Widget _parcelPreview(ParcelModel parcel) {
    final bool hasRoute =
        parcel.originHubName.trim().isNotEmpty ||
        parcel.destinationHubName.trim().isNotEmpty;

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
          _previewRow('Customer', _display(parcel.customerName)),

          const SizedBox(height: 7),

          _previewRow('Status', _formatStatus(parcel.status)),

          const SizedBox(height: 7),

          _previewRow('Sorting', _formatStatus(parcel.sortingStatus)),

          if (hasRoute) ...[
            const SizedBox(height: 7),
            _previewRow('Route', _routeText(parcel)),
          ],

          if (parcel.linehaulStatus.trim().isNotEmpty) ...[
            const SizedBox(height: 7),
            _previewRow('Line Haul', _formatStatus(parcel.linehaulStatus)),
          ],

          if (parcel.currentHubName.trim().isNotEmpty) ...[
            const SizedBox(height: 7),
            _previewRow('Current Hub', parcel.currentHubName),
          ],
        ],
      ),
    );
  }

  Widget _previewRow(String title, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(width: 82, child: Text(title, style: LogisticsStyle.small)),

        const SizedBox(width: 8),

        Expanded(
          child: Text(
            value,
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
