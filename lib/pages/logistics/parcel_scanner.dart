import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../models/parcel_model.dart';
import '../../services/api_services.dart';
import '../../styles/logistics_style.dart';
import '../../utils/address_formatter.dart';
import '../../widgets/app_address_card.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_loading_state.dart';
import '../../widgets/status_chip.dart';

import 'parcel_information.dart';

class ParcelScanner extends StatefulWidget {
  final ParcelModel? scannedParcel;

  const ParcelScanner({super.key, this.scannedParcel});

  @override
  State<ParcelScanner> createState() => _ParcelScannerState();
}

class _ParcelScannerState extends State<ParcelScanner>
    with WidgetsBindingObserver {
  static const Duration _requestTimeout = Duration(seconds: 30);

  late final MobileScannerController _scannerController;

  ParcelModel? _parcel;

  String? _trackingNumber;
  String? _message;

  bool _hasScanned = false;
  bool _isLookingUp = false;
  bool _isResetting = false;
  bool _isCameraRunning = true;
  bool _isVerified = false;
  bool _openingParcel = false;

  int _requestId = 0;

  @override
  void initState() {
    super.initState();

    WidgetsBinding.instance.addObserver(this);

    _scannerController = MobileScannerController(
      facing: CameraFacing.back,
      torchEnabled: false,
      detectionSpeed: DetectionSpeed.noDuplicates,
      formats: const <BarcodeFormat>[BarcodeFormat.qrCode],
      autoZoom: true,
    );

    final ParcelModel? initial = widget.scannedParcel;

    if (initial != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;

        _verifyKnownParcel(initial);
      });
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);

    _requestId++;
    _scannerController.dispose();

    super.dispose();
  }

  // ============================================================
  // CAMERA LIFECYCLE
  // ============================================================

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!mounted) return;

    switch (state) {
      case AppLifecycleState.resumed:
        if (!_hasScanned && !_isLookingUp && !_isResetting && !_openingParcel) {
          _startCamera();
        }
        break;

      case AppLifecycleState.inactive:
      case AppLifecycleState.paused:
      case AppLifecycleState.hidden:
      case AppLifecycleState.detached:
        _stopCamera();
        break;
    }
  }

  Future<void> _stopCamera() async {
    if (!_isCameraRunning) return;

    _isCameraRunning = false;

    try {
      await _scannerController.stop();
    } catch (error) {
      debugPrint('Logistics camera stop error: $error');
    }
  }

  Future<void> _startCamera() async {
    if (!mounted ||
        _hasScanned ||
        _isLookingUp ||
        _isResetting ||
        _openingParcel ||
        _isCameraRunning) {
      return;
    }

    _isCameraRunning = true;

    try {
      await _scannerController.start();
    } catch (error) {
      _isCameraRunning = false;

      debugPrint('Logistics camera start error: $error');

      if (!mounted) return;

      setState(() {
        _message = 'Camera unavailable. Check camera permission.';
      });
    }
  }

  Future<void> _toggleTorch() async {
    if (_isLookingUp || _isResetting || _hasScanned || _openingParcel) {
      return;
    }

    try {
      await _scannerController.toggleTorch();
    } catch (error) {
      debugPrint('Logistics flashlight error: $error');

      _showMessage('Unable to change flashlight state.', success: false);
    }
  }

  // ============================================================
  // QR DETECTION
  // ============================================================

  Future<void> _handleDetection(BarcodeCapture capture) async {
    if (!mounted ||
        _hasScanned ||
        _isLookingUp ||
        _isResetting ||
        _openingParcel ||
        capture.barcodes.isEmpty) {
      return;
    }

    String? qrPayload;

    for (final Barcode barcode in capture.barcodes) {
      final String raw = barcode.rawValue?.trim() ?? '';

      if (barcode.format == BarcodeFormat.qrCode && raw.isNotEmpty) {
        qrPayload = raw;
        break;
      }
    }

    if (qrPayload == null) return;

    // Lock the scanner immediately to prevent
    // duplicate requests from consecutive frames.
    final int requestId = ++_requestId;

    setState(() {
      _hasScanned = true;
      _isLookingUp = true;
      _isVerified = false;

      _parcel = null;
      _trackingNumber = null;
      _message = 'Reading parcel QR code...';
    });

    await _stopCamera();

    if (!mounted || requestId != _requestId) return;

    final String? tracking = _extractTrackingNumber(qrPayload);

    if (tracking == null) {
      _setLookupError(
        requestId,
        'The scanned QR does not contain a '
        'recognizable tracking number.',
      );
      return;
    }

    setState(() {
      _trackingNumber = tracking;
      _message = 'Looking up $tracking...';
    });

    await _lookupParcel(tracking, requestId);
  }

  // ============================================================
  // EXTRACT TRACKING NUMBER
  // ============================================================

  String? _extractTrackingNumber(String raw) {
    final String value = raw.trim();

    if (value.isEmpty || value.length > 4096) {
      return null;
    }

    // JSON QR: {"tracking_number":"..."}
    if (value.startsWith('{')) {
      try {
        final dynamic decoded = jsonDecode(value);

        if (decoded is Map) {
          for (final String key in const [
            'tracking_number',
            'tracking_code',
            'tracking',
            'waybill_number',
            'waybill',
            'code',
            't',
          ]) {
            final String candidate = decoded[key]?.toString().trim() ?? '';

            if (_looksLikeTracking(candidate)) {
              return candidate;
            }
          }
        }
      } catch (error) {
        debugPrint('QR JSON parsing error: $error');
      }

      return null;
    }

    // URL QR: /tracking/BILIHUB123
    final Uri? uri = Uri.tryParse(value);

    if (uri != null && (uri.scheme == 'https' || uri.scheme == 'http')) {
      for (final String key in const [
        'tracking_number',
        'tracking',
        'tracking_code',
        'waybill',
        'code',
        't',
      ]) {
        final String candidate = uri.queryParameters[key]?.trim() ?? '';

        if (_looksLikeTracking(candidate)) {
          return candidate;
        }
      }

      final List<String> segments = uri.pathSegments
          .where((String segment) => segment.isNotEmpty)
          .toList();

      if (segments.isNotEmpty) {
        final String candidate = segments.last.trim();

        if (_looksLikeTracking(candidate)) {
          return candidate;
        }
      }

      return null;
    }

    // Plain tracking-number QR.
    if (_looksLikeTracking(value)) {
      return value;
    }

    return null;
  }

  bool _looksLikeTracking(String value) {
    final String text = value.trim();

    if (text.length < 4 || text.length > 100) {
      return false;
    }

    return RegExp(r'^[A-Za-z0-9_-]+$').hasMatch(text);
  }

  // ============================================================
  // LOOKUP PARCEL
  // ============================================================

  Future<void> _lookupParcel(String tracking, int requestId) async {
    try {
      // IMPORTANT:
      // The existing mobile API method only retrieves
      // a page of shipments. This is a lookup,
      // NOT a Logistics receiving or scanning action.
      //
      // A dedicated Laravel tracking lookup endpoint
      // is recommended to remove the 100-record limit.
      final Map<String, dynamic> result =
          await ApiService.getLogisticsShipments(perPage: 100)
              .timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) return;

      if (result['success'] != true) {
        _setLookupError(
          requestId,
          _resultMessage(result, 'Unable to retrieve shipments.'),
        );
        return;
      }

      final List<Map<String, dynamic>> shipments = _extractShipmentMaps(result);

      final String expected = tracking.toLowerCase();

      Map<String, dynamic>? match;

      for (final Map<String, dynamic> raw in shipments) {
        final Map<String, dynamic> normalized = _normalizeShipment(raw);

        final String candidate = _firstText([
          normalized['tracking_number'],
          normalized['tracking_code'],
          normalized['tracking'],
          normalized['waybill_number'],
        ]).toLowerCase();

        if (candidate == expected) {
          match = normalized;
          break;
        }
      }

      if (match == null) {
        _setLookupError(
          requestId,
          'Parcel not found in the loaded shipments. '
          'It may be on another page or outside '
          'your Logistics account. '
          'No parcel status was changed.',
        );
        return;
      }

      final ParcelModel matched;

      try {
        matched = ParcelModel.fromJson(match);
      } catch (error) {
        debugPrint('Shipment matching error: $error');

        _setLookupError(requestId, 'Matching shipment data could not be read.');
        return;
      }

      if (matched.id <= 0) {
        _setLookupError(requestId, 'The matching parcel has an invalid ID.');
        return;
      }

      // Verify the exact shipment through its own
      // protected Laravel API endpoint.
      await _fetchVerifiedParcel(
        shipmentId: matched.id,
        expectedTracking: tracking,
        requestId: requestId,
      );
    } on TimeoutException {
      _setLookupError(requestId, 'Parcel lookup timed out. Please retry.');
    } catch (error) {
      debugPrint('Logistics parcel lookup error: $error');

      _setLookupError(
        requestId,
        'Unable to look up the parcel. '
        'Please check your connection.',
      );
    }
  }

  // ============================================================
  // VERIFY KNOWN PARCEL
  // ============================================================

  Future<void> _verifyKnownParcel(ParcelModel parcel) async {
    if (!mounted || _isLookingUp || _openingParcel) {
      return;
    }

    final int requestId = ++_requestId;

    setState(() {
      _hasScanned = true;
      _isLookingUp = true;
      _isVerified = false;

      _parcel = null;
      _trackingNumber = parcel.trackingNumber;
      _message = 'Verifying selected parcel...';
    });

    await _stopCamera();

    if (!mounted || requestId != _requestId) return;

    if (parcel.id <= 0) {
      _setLookupError(requestId, 'Invalid shipment ID.');
      return;
    }

    await _fetchVerifiedParcel(
      shipmentId: parcel.id,
      expectedTracking: parcel.trackingNumber,
      requestId: requestId,
    );
  }

  // ============================================================
  // VERIFY EXACT SHIPMENT FROM LARAVEL
  // ============================================================

  Future<void> _fetchVerifiedParcel({
    required int shipmentId,
    required String expectedTracking,
    required int requestId,
  }) async {
    try {
      final Map<String, dynamic> response =
          await ApiService.getLogisticsShipment(shipmentId)
              .timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) return;

      if (response['success'] != true) {
        _setLookupError(
          requestId,
          _resultMessage(response, 'Unable to verify shipment details.'),
        );
        return;
      }

      final Map<String, dynamic>? raw = _extractSingleShipment(response);

      if (raw == null) {
        _setLookupError(
          requestId,
          'The server returned invalid shipment data.',
        );
        return;
      }

      final ParcelModel latest;

      try {
        latest = ParcelModel.fromJson(_normalizeShipment(raw));
      } catch (error) {
        debugPrint('Logistics parcel parse error: $error');

        _setLookupError(requestId, 'Unable to read verified shipment data.');
        return;
      }

      if (latest.id != shipmentId) {
        _setLookupError(requestId, 'Unexpected shipment returned by Laravel.');
        return;
      }

      final String actualTracking = latest.trackingNumber.trim();

      if (actualTracking.isEmpty ||
          (expectedTracking.trim().isNotEmpty &&
              actualTracking.toLowerCase() !=
                  expectedTracking.trim().toLowerCase())) {
        _setLookupError(
          requestId,
          'The server tracking number does not match '
          'the scanned QR code.',
        );
        return;
      }

      setState(() {
        _parcel = latest;
        _trackingNumber = actualTracking;

        _isLookingUp = false;
        _isVerified = true;
        _hasScanned = true;

        _message =
            'Shipment identified successfully. '
            'Open Parcel Information to review '
            'the allowed Logistics actions.';
      });

      _showMessage('Parcel $actualTracking identified.', success: true);
    } on TimeoutException {
      _setLookupError(requestId, 'Shipment verification timed out.');
    } catch (error) {
      debugPrint('Logistics verification error: $error');

      _setLookupError(
        requestId,
        'Unable to verify latest shipment information.',
      );
    }
  }

  void _setLookupError(int requestId, String message) {
    if (!mounted || requestId != _requestId) return;

    setState(() {
      _parcel = null;
      _isVerified = false;
      _isLookingUp = false;
      _hasScanned = true;
      _message = message;
    });
  }

  // ============================================================
  // API RESPONSE PARSING
  // ============================================================

  List<Map<String, dynamic>> _extractShipmentMaps(
    Map<String, dynamic> response,
  ) {
    for (final String key in ['data', 'shipments', 'parcels']) {
      if (response[key] is List) {
        return _toMapList(response[key]);
      }
    }

    final Map<String, dynamic> data = _asMap(response['data']);

    for (final String key in ['data', 'shipments', 'parcels']) {
      if (data[key] is List) {
        return _toMapList(data[key]);
      }
    }

    return [];
  }

  Map<String, dynamic>? _extractSingleShipment(Map<String, dynamic> response) {
    final dynamic raw = response['data'];

    if (raw is Map) {
      final Map<String, dynamic> data = Map<String, dynamic>.from(raw);

      if (data['shipment'] is Map) {
        final Map<String, dynamic> shipment = _asMap(data['shipment']);

        for (final String key in [
          'order',
          'seller',
          'rider',
          'linehaul_rider',
          'origin_hub',
          'destination_hub',
          'hub',
        ]) {
          shipment[key] ??= data[key];
        }

        return shipment;
      }

      return data;
    }

    if (response['shipment'] is Map) {
      return _asMap(response['shipment']);
    }

    return null;
  }

  List<Map<String, dynamic>> _toMapList(dynamic raw) {
    if (raw is! List) return [];

    return raw
        .whereType<Map>()
        .map((Map item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }

    return {};
  }

  Map<String, dynamic> _normalizeShipment(Map<String, dynamic> raw) {
    final Map<String, dynamic> data = Map<String, dynamic>.from(raw);

    final Map<String, dynamic> nested = _asMap(data['shipment']);

    if (nested.isNotEmpty) {
      data.addAll(nested);
    }

    final Map<String, dynamic> order = _asMap(data['order']);

    final Map<String, dynamic> rider = _asMap(data['rider']);

    final Map<String, dynamic> originHub = _asMap(data['origin_hub']);

    final Map<String, dynamic> destinationHub = _asMap(data['destination_hub']);

    final Map<String, dynamic> currentHub = _asMap(data['hub']);

    final Map<String, dynamic> linehaulRider = _asMap(data['linehaul_rider']);

    data['id'] ??= data['shipment_id'] ?? data['order_id'] ?? 0;

    data['shipment_id'] ??= data['id'];

    data['tracking_number'] ??=
        data['tracking_code'] ??
        data['tracking'] ??
        data['waybill_number'] ??
        '';

    data['customer_name'] ??=
        order['customer_name'] ??
        order['buyer_name'] ??
        data['recipient_name'] ??
        data['buyer_name'] ??
        '';

    data['customer_phone'] ??=
        order['customer_phone'] ??
        order['phone'] ??
        data['recipient_phone'] ??
        data['phone'] ??
        '';

    data['delivery_address'] = _resolveDeliveryAddress(data, order);

    data['pickup_address'] = AddressFormatter.clean(
      data['pickup_address'] ?? data['seller_address'],
    );

    data['rider_id'] ??= rider['id'];
    data['rider_name'] ??= rider['name'] ?? '';

    data['linehaul_rider_id'] ??= linehaulRider['id'];

    data['linehaul_rider_name'] ??= linehaulRider['name'];

    data['origin_hub_id'] ??= originHub['id'];
    data['origin_hub_name'] ??= originHub['name'];

    data['destination_hub_id'] ??= destinationHub['id'];

    data['destination_hub_name'] ??= destinationHub['name'];

    data['current_hub_id'] ??= currentHub['id'];
    data['current_hub_name'] ??= currentHub['name'];

    data['status'] ??=
        data['shipment_status'] ?? data['delivery_status'] ?? 'pending';

    data['order_date'] ??=
        data['created_at'] ?? data['assigned_at'] ?? order['created_at'] ?? '';

    data['delivery_earnings'] ??= data['earnings'] ?? data['delivery_fee'] ?? 0;

    data['latitude'] ??= data['lat'];
    data['longitude'] ??= data['lng'] ?? data['lon'];

    return data;
  }

  String _resolveDeliveryAddress(
    Map<String, dynamic> shipment,
    Map<String, dynamic> order,
  ) {
    for (final Map<String, dynamic> source in [shipment, order]) {
      for (final String key in [
        'delivery_address',
        'full_delivery_address',
        'shipping_address',
        'recipient_address',
        'buyer_address',
        'address',
        'delivery_address_details',
        'shipping_address_details',
        'address_details',
      ]) {
        final String value = AddressFormatter.clean(source[key]);

        if (value.isNotEmpty) return value;
      }
    }

    return '';
  }

  // ============================================================
  // DISPLAY HELPERS
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
    final String cleaned = value.trim();

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return fallback;
    }

    return cleaned;
  }

  String _formatStatus(String value) {
    final String status = value
        .trim()
        .replaceAll('-', '_')
        .replaceAll(' ', '_');

    if (status.isEmpty) {
      return 'Not available';
    }

    return status
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

    if (origin.isNotEmpty) return origin;
    if (destination.isNotEmpty) return destination;

    return 'Route not assigned';
  }

  String _resultMessage(Map<String, dynamic> result, String fallback) {
    final String message = result['message']?.toString().trim() ?? '';

    return message.isNotEmpty ? message : fallback;
  }

  String get _scannerGuideText {
    if (_isLookingUp) {
      return 'Looking up parcel...';
    }

    if (_isVerified) {
      return 'Parcel identified';
    }

    if (_hasScanned) {
      return 'Scan completed';
    }

    return 'Place BiliHub QR inside the frame';
  }

  void _showMessage(String message, {required bool success}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: success
              ? LogisticsColors.success
              : LogisticsColors.danger,
          content: Text(message),
        ),
      );
  }

  // ============================================================
  // RESET SCANNER
  // ============================================================

  Future<void> _resetScanner() async {
    if (!mounted || _isLookingUp || _isResetting || _openingParcel) {
      return;
    }

    _requestId++;

    setState(() {
      _isResetting = true;
      _hasScanned = true;
    });

    try {
      await _stopCamera();

      if (!mounted) return;

      setState(() {
        _parcel = null;
        _trackingNumber = null;
        _message = null;

        _hasScanned = false;
        _isLookingUp = false;
        _isVerified = false;
      });
    } finally {
      _isResetting = false;

      if (mounted) {
        await _startCamera();
      }
    }
  }

  // ============================================================
  // OPEN PARCEL INFORMATION
  // ============================================================

  Future<void> _openParcel() async {
    final ParcelModel? parcel = _parcel;

    if (!mounted ||
        parcel == null ||
        !_isVerified ||
        _isLookingUp ||
        _openingParcel) {
      return;
    }

    setState(() {
      _openingParcel = true;
    });

    try {
      // The Parcel Information page is responsible
      // for approved processing actions:
      // Receive -> Scan -> Sort -> Stage -> Assign.
      //
      // Opening it does not itself update the shipment.
      await Navigator.push<void>(
        context,
        MaterialPageRoute<void>(
          builder: (_) => ParcelInformation(parcel: parcel),
        ),
      );

      if (!mounted) return;

      final int requestId = ++_requestId;

      setState(() {
        _isLookingUp = true;
        _isVerified = false;
        _message = 'Refreshing parcel status...';
      });

      await _fetchVerifiedParcel(
        shipmentId: parcel.id,
        expectedTracking: parcel.trackingNumber,
        requestId: requestId,
      );
    } finally {
      if (mounted) {
        setState(() {
          _openingParcel = false;
        });
      }
    }
  }

  // ============================================================
  // MAIN SCREEN
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
            SizedBox(height: 3),
            Text(
              'Identify parcels and review hub processing',
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
  // CAMERA VIEW
  // ============================================================

  Widget _scannerCamera() {
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints constraints) {
        final Size size = constraints.biggest;

        if (size.width <= 0 || size.height <= 0) {
          return const SizedBox.shrink();
        }

        final double maxSize = (size.height * 0.70)
            .clamp(1.0, size.width)
            .toDouble();

        final double minSize = maxSize < 120 ? maxSize : 120.0;

        final double scanSize = (size.width * 0.64)
            .clamp(minSize, maxSize)
            .toDouble();

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
              top: scanWindow.bottom + 14,
              left: 12,
              right: 12,
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
                  child: Text(
                    _scannerGuideText,
                    textAlign: TextAlign.center,
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

  // ============================================================
  // CAMERA ERRORS
  // ============================================================

  Widget _cameraError(MobileScannerException error) {
    return Container(
      color: Colors.black,
      alignment: Alignment.center,
      padding: const EdgeInsets.all(24),
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
            'Unable to open the camera.\n'
            '${error.errorCode}',
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white70, fontSize: 12),
          ),
          const SizedBox(height: 18),
          ElevatedButton.icon(
            onPressed: _isLookingUp || _isResetting ? null : _resetScanner,
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
  // CAMERA CONTROLS
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
                    onTap: _hasScanned || _isLookingUp || _isResetting
                        ? null
                        : _toggleTorch,
                  );
                },
          ),
          const SizedBox(width: 16),
          _controlButton(
            icon: Icons.refresh_rounded,
            label: _isResetting ? 'Resetting' : 'Reset',
            onTap: _isLookingUp || _isResetting || _openingParcel
                ? null
                : _resetScanner,
          ),
        ],
      ),
    );
  }

  Widget _controlButton({
    required IconData icon,
    required String label,
    required VoidCallback? onTap,
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
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                icon,
                color: onTap == null ? Colors.white38 : Colors.white,
                size: 20,
              ),
              const SizedBox(width: 7),
              Text(
                label,
                style: TextStyle(
                  color: onTap == null ? Colors.white38 : Colors.white,
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
  // BOTTOM PANEL
  // ============================================================

  Widget _bottomPanel() {
    return Container(
      width: double.infinity,
      constraints: BoxConstraints(
        maxHeight: MediaQuery.sizeOf(context).height * 0.48,
      ),
      padding: const EdgeInsets.fromLTRB(18, 15, 18, 20),
      decoration: const BoxDecoration(
        color: LogisticsColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
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
            const SizedBox(height: 16),

            if (_isLookingUp)
              const AppLoadingState(
                message: 'Verifying parcel with BiliHub...',
                color: LogisticsColors.primary,
                compact: true,
              )
            else if (_isVerified && _parcel != null)
              _verifiedPanel(_parcel!)
            else if (_hasScanned)
              _errorPanel()
            else
              _waitingPanel(),
          ],
        ),
      ),
    );
  }

  Widget _waitingPanel() {
    return const Column(
      children: [
        Icon(
          Icons.qr_code_scanner_rounded,
          size: 46,
          color: LogisticsColors.primary,
        ),
        SizedBox(height: 10),
        Text('Ready to Scan', style: LogisticsStyle.sectionTitle),
        SizedBox(height: 6),
        Text(
          'Scan the BiliHub parcel QR code '
          'to identify its shipment.',
          textAlign: TextAlign.center,
          style: LogisticsStyle.small,
        ),
      ],
    );
  }

  Widget _errorPanel() {
    return Column(
      children: [
        AppErrorState(
          title: 'Parcel Lookup Unsuccessful',
          message: _message ?? 'Unable to identify the scanned parcel.',
          icon: Icons.qr_code_scanner_rounded,
          color: LogisticsColors.primary,
          compact: true,
          onRetry: _resetScanner,
        ),
        if (_trackingNumber != null) ...[
          const SizedBox(height: 10),
          Text(
            'Tracking: $_trackingNumber',
            textAlign: TextAlign.center,
            style: LogisticsStyle.small,
          ),
        ],
      ],
    );
  }

  // ============================================================
  // VERIFIED PARCEL
  // ============================================================

  Widget _verifiedPanel(ParcelModel parcel) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(
              Icons.verified_rounded,
              color: LogisticsColors.success,
              size: 28,
            ),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'Parcel Identified',
                style: LogisticsStyle.sectionTitle,
              ),
            ),
          ],
        ),

        const SizedBox(height: 7),

        Text(
          _message ??
              'Shipment information was retrieved '
                  'from BiliHub.',
          style: LogisticsStyle.small,
        ),

        const SizedBox(height: 12),

        AppInfoRow(
          label: 'Tracking Number',
          value: _display(parcel.trackingNumber),
          icon: Icons.qr_code_2_rounded,
          iconColor: LogisticsColors.primary,
          selectable: true,
          compact: true,
        ),

        const SizedBox(height: 8),

        StatusChip(status: parcel.status),

        const SizedBox(height: 12),

        AppInfoRow(
          label: 'Customer',
          value: _display(parcel.customerName),
          icon: Icons.person_outline_rounded,
          iconColor: LogisticsColors.primary,
          compact: true,
        ),

        AppInfoRow(
          label: 'Sorting Status',
          value: _formatStatus(parcel.sortingStatus),
          icon: Icons.sort_rounded,
          iconColor: LogisticsColors.primary,
          compact: true,
        ),

        if (parcel.originHubName.trim().isNotEmpty ||
            parcel.destinationHubName.trim().isNotEmpty)
          AppInfoRow(
            label: 'Hub Route',
            value: _routeText(parcel),
            icon: Icons.route_outlined,
            iconColor: LogisticsColors.primary,
            compact: true,
          ),

        if (parcel.currentHubName.trim().isNotEmpty)
          AppInfoRow(
            label: 'Current Hub',
            value: parcel.currentHubName,
            icon: Icons.warehouse_outlined,
            iconColor: LogisticsColors.primary,
            compact: true,
          ),

        if (parcel.deliveryAddress.trim().isNotEmpty) ...[
          const SizedBox(height: 10),
          AppAddressCard(
            title: 'Delivery Address',
            recipientName: parcel.customerName,
            phoneNumber: parcel.customerPhone,
            address: parcel.deliveryAddress,
            accentColor: LogisticsColors.primary,
            compact: true,
          ),
        ],

        const SizedBox(height: 12),

        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: LogisticsColors.background,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: LogisticsColors.border),
          ),
          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.info_outline_rounded,
                color: LogisticsColors.primary,
                size: 18,
              ),
              SizedBox(width: 9),
              Expanded(
                child: Text(
                  'QR lookup does not automatically '
                  'receive or sort this parcel. '
                  'Open Parcel Information to confirm '
                  'the next allowed Logistics action.',
                  style: LogisticsStyle.small,
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 16),

        SizedBox(
          width: double.infinity,
          height: 48,
          child: ElevatedButton.icon(
            onPressed: _openingParcel ? null : _openParcel,
            style: LogisticsStyle.primaryButton,
            icon: const Icon(Icons.inventory_2_outlined),
            label: const Text('OPEN PARCEL INFORMATION'),
          ),
        ),

        const SizedBox(height: 9),

        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: _resetScanner,
            icon: const Icon(Icons.qr_code_scanner_rounded),
            label: const Text('Scan Another Parcel'),
            style: OutlinedButton.styleFrom(
              foregroundColor: LogisticsColors.primary,
              side: const BorderSide(color: LogisticsColors.primary),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
