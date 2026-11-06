import 'dart:async';

import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../models/parcel_model.dart';
import '../../services/api_services.dart';
import '../../styles/rider_style.dart';
import '../../widgets/app_address_card.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/status_chip.dart';

class RiderScanner extends StatefulWidget {
  final ParcelModel? scannedParcel;
  final VoidCallback? onOpenDeliveries;

  const RiderScanner({super.key, this.scannedParcel, this.onOpenDeliveries});

  @override
  State<RiderScanner> createState() => _RiderScannerState();
}

class _RiderScannerState extends State<RiderScanner>
    with WidgetsBindingObserver {
  static const Duration _requestTimeout = Duration(seconds: 30);

  late final MobileScannerController _scannerController;

  bool _hasScanned = false;
  bool _isProcessing = false;
  bool _isResetting = false;
  bool _isCameraRunning = true;
  bool _serverConfirmed = false;
  bool _needsStatusCheck = false;

  int _requestId = 0;

  String? _scanError;
  String? _scanMessage;
  String? _scanType;
  String? _trackingNumber;

  ParcelModel? _foundParcel;

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
        if (!_hasScanned && !_isProcessing && !_isResetting) {
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
      debugPrint('Rider camera stop error: $error');
    }
  }

  Future<void> _startCamera() async {
    if (!mounted ||
        _isCameraRunning ||
        _hasScanned ||
        _isProcessing ||
        _isResetting) {
      return;
    }

    _isCameraRunning = true;

    try {
      await _scannerController.start();
    } catch (error) {
      _isCameraRunning = false;
      debugPrint('Rider camera start error: $error');

      if (mounted) {
        setState(() {
          _scanError = 'Unable to start camera. Check camera permission.';
        });
      }
    }
  }

  Future<void> _toggleTorch() async {
    if (_isProcessing || _isResetting || _hasScanned) {
      return;
    }

    try {
      await _scannerController.toggleTorch();
    } catch (error) {
      debugPrint('Flashlight error: $error');

      _showMessage('Unable to change flashlight state.', error: true);
    }
  }

  // ============================================================
  // QR SCAN
  // ============================================================

  Future<void> _handleDetection(BarcodeCapture capture) async {
    if (!mounted || _hasScanned || _isProcessing || _isResetting) {
      return;
    }

    String? payload;

    for (final Barcode barcode in capture.barcodes) {
      final String raw = barcode.rawValue?.trim() ?? '';

      if (barcode.format == BarcodeFormat.qrCode && raw.isNotEmpty) {
        payload = raw;
        break;
      }
    }

    if (payload == null) return;

    final int requestId = ++_requestId;

    // Lock immediately to prevent duplicate requests.
    setState(() {
      _hasScanned = true;
      _isProcessing = true;
      _serverConfirmed = false;
      _needsStatusCheck = false;

      _foundParcel = null;
      _scanType = null;
      _scanMessage = null;
      _scanError = null;
      _trackingNumber = null;
    });

    await _stopCamera();

    if (!mounted || requestId != _requestId) {
      return;
    }

    await _submitScan(payload, requestId);
  }

  // ============================================================
  // SERVER-SIDE PICKUP CONFIRMATION
  // ============================================================

  Future<void> _submitScan(String payload, int requestId) async {
    try {
      // IMPORTANT:
      // This endpoint changes shipment state.
      //
      // seller_pickup -> in_transit
      // hub_pickup    -> picked_up
      //
      // Send the original QR payload unchanged.
      // Never use tracking number alone for pickup.
      final Map<String, dynamic> result = await ApiService.scanRiderWaybill(
        qrPayload: payload,
      ).timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) {
        return;
      }

      if (result['success'] != true) {
        _setScanError(
          requestId,
          _serverMessage(result, 'QR scan was rejected by BiliHub.'),
        );
        return;
      }

      final String scanType =
          result['scan_type']?.toString().trim().toLowerCase() ?? '';

      final String message = _serverMessage(
        result,
        'QR pickup confirmation completed.',
      );

      // A successful response means Laravel may
      // already have committed the status update.
      if (scanType != 'seller_pickup' && scanType != 'hub_pickup') {
        _setScanError(
          requestId,
          'The server accepted the scan, but returned '
          'an unexpected scan type. Check My Deliveries.',
          uncertain: true,
        );
        return;
      }

      final dynamic raw = result['shipment'];

      if (raw is! Map) {
        _setScanError(
          requestId,
          'Pickup may have succeeded, but shipment '
          'details were missing. Check My Deliveries.',
          uncertain: true,
        );
        return;
      }

      final ParcelModel parcel;

      try {
        parcel = ParcelModel.fromJson(
          _normalizeShipment(Map<String, dynamic>.from(raw)),
        );
      } catch (error) {
        debugPrint('Rider scan response parsing error: $error');

        _setScanError(
          requestId,
          'Pickup was accepted by the server, but '
          'shipment details could not be displayed. '
          'Open My Deliveries to confirm.',
          uncertain: true,
        );
        return;
      }

      if (parcel.id <= 0) {
        _setScanError(
          requestId,
          'Pickup was accepted, but the server '
          'returned an invalid shipment ID. '
          'Check My Deliveries.',
          uncertain: true,
        );
        return;
      }

      final String status = parcel.normalizedStatus;

      final bool expectedStatus = scanType == 'seller_pickup'
          ? status == 'in_transit'
          : status == 'picked_up';

      if (!expectedStatus) {
        _setScanError(
          requestId,
          'The scan succeeded, but the shipment '
          'status was unexpected. '
          'Check My Deliveries before continuing.',
          uncertain: true,
        );
        return;
      }

      setState(() {
        _foundParcel = parcel;
        _trackingNumber = parcel.trackingNumber;
        _scanType = scanType;
        _scanMessage = message;

        _serverConfirmed = true;
        _needsStatusCheck = false;
        _scanError = null;
        _isProcessing = false;
      });

      _showMessage(
        scanType == 'seller_pickup'
            ? 'Seller pickup confirmed successfully.'
            : 'Hub pickup confirmed successfully.',
      );
    } on TimeoutException {
      _setScanError(
        requestId,
        'The scan request timed out. '
        'Laravel may already have recorded the pickup. '
        'Check My Deliveries before scanning again.',
        uncertain: true,
      );
    } catch (error) {
      debugPrint('Rider QR scan error: $error');

      _setScanError(
        requestId,
        'Unable to confirm the scan result. '
        'Check My Deliveries before trying again.',
        uncertain: true,
      );
    }
  }

  void _setScanError(int requestId, String message, {bool uncertain = false}) {
    if (!mounted || requestId != _requestId) {
      return;
    }

    setState(() {
      _isProcessing = false;
      _serverConfirmed = false;
      _needsStatusCheck = uncertain;

      _scanError = message;
      _scanMessage = null;
      _scanType = null;
      _foundParcel = null;
    });

    _showMessage(message, error: true);
  }

  // ============================================================
  // NORMALIZE RESPONSE FOR PARCEL MODEL
  // ============================================================

  Map<String, dynamic> _normalizeShipment(Map<String, dynamic> raw) {
    final Map<String, dynamic> data = Map<String, dynamic>.from(raw);

    if (raw['shipment'] is Map) {
      data.addAll(Map<String, dynamic>.from(raw['shipment']));
    }

    final Map<String, dynamic> order = _asMap(data['order']);

    final Map<String, dynamic> rider = _asMap(data['rider']);

    final Map<String, dynamic> hub = _asMap(data['hub']);

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
        '';

    data['customer_phone'] ??=
        order['customer_phone'] ??
        order['phone'] ??
        data['recipient_phone'] ??
        '';

    data['delivery_address'] ??=
        order['delivery_address'] ??
        data['shipping_address'] ??
        data['address'] ??
        '';

    data['pickup_address'] ??= data['seller_address'] ?? '';

    data['rider_id'] ??= rider['id'];
    data['rider_name'] ??= rider['name'] ?? '';

    data['current_hub_id'] ??= hub['id'];
    data['current_hub_name'] ??= hub['name'] ?? '';

    data['status'] ??= data['shipment_status'] ?? data['delivery_status'] ?? '';

    data['order_date'] ??= data['created_at'] ?? data['assigned_at'] ?? '';

    data['delivery_earnings'] ??= data['earnings'] ?? data['delivery_fee'] ?? 0;

    data['latitude'] ??= data['lat'];
    data['longitude'] ??= data['lng'] ?? data['lon'];

    return data;
  }

  Map<String, dynamic> _asMap(dynamic raw) {
    if (raw is Map) {
      return Map<String, dynamic>.from(raw);
    }

    return <String, dynamic>{};
  }

  // ============================================================
  // RESET SCANNER
  // ============================================================

  Future<void> _resetScanner() async {
    if (!mounted || _isProcessing || _isResetting) {
      return;
    }

    if (_needsStatusCheck) {
      final bool? confirmed = await showDialog<bool>(
        context: context,
        builder: (BuildContext dialogContext) {
          return AlertDialog(
            title: const Text('Scan Result Uncertain'),
            content: const Text(
              'The previous scan may already have '
              'changed the shipment status. '
              'Check My Deliveries first to avoid '
              'scanning an already picked-up parcel.\n\n'
              'Reset the scanner anyway?',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext, false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(dialogContext, true),
                child: const Text('Reset Scanner'),
              ),
            ],
          );
        },
      );

      if (!mounted || confirmed != true) {
        return;
      }
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
        _hasScanned = false;
        _isProcessing = false;
        _serverConfirmed = false;
        _needsStatusCheck = false;

        _foundParcel = null;
        _scanType = null;
        _scanMessage = null;
        _scanError = null;
        _trackingNumber = null;
      });
    } catch (error) {
      debugPrint('Rider scanner reset error: $error');

      if (mounted) {
        _showMessage('Unable to reset scanner.', error: true);
      }
    } finally {
      if (mounted) {
        setState(() {
          _isResetting = false;
        });

        if (!_hasScanned) {
          await _startCamera();
        }
      }
    }
  }

  // ============================================================
  // NAVIGATION
  // ============================================================

  void _openMyDeliveries() {
    if (_isProcessing || _isResetting) return;

    if (widget.onOpenDeliveries != null) {
      widget.onOpenDeliveries!();
      return;
    }

    Navigator.of(context).maybePop(_foundParcel);
  }

  // ============================================================
  // HELPERS
  // ============================================================

  String _serverMessage(Map<String, dynamic> result, String fallback) {
    final String message = result['message']?.toString().trim() ?? '';

    if (message.isNotEmpty) return message;

    final dynamic errors = result['errors'];

    if (errors is Map) {
      for (final dynamic entry in errors.values) {
        if (entry is List && entry.isNotEmpty) {
          return entry.first.toString();
        }
      }
    }

    return fallback;
  }

  String _display(String value, {String fallback = 'Not available'}) {
    final String cleaned = value.trim();

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return fallback;
    }

    return cleaned;
  }

  bool get _isSellerPickup => _scanType == 'seller_pickup';

  bool get _isHubPickup => _scanType == 'hub_pickup';

  String get _scannerGuide {
    if (_isProcessing) {
      return 'Confirming pickup with BiliHub...';
    }

    if (_serverConfirmed) {
      return _isSellerPickup
          ? 'Seller pickup confirmed'
          : 'Hub pickup confirmed';
    }

    if (_needsStatusCheck) {
      return 'Check shipment status before retrying';
    }

    if (_scanError != null) {
      return 'Scan unsuccessful';
    }

    return 'Place the official BiliHub QR inside the frame';
  }

  void _showMessage(String message, {bool error = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: error ? RiderColors.danger : RiderColors.success,
          content: Text(message),
        ),
      );
  }

  // ============================================================
  // MAIN UI
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
              'Scan Waybill',
              style: TextStyle(
                color: Colors.white,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
            SizedBox(height: 3),
            Text(
              'Confirm seller or hub parcel pickup',
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

        if (size.width <= 0 || size.height <= 0) {
          return const SizedBox.shrink();
        }

        final double maxScanSize = (size.height * 0.72)
            .clamp(1.0, size.width)
            .toDouble();

        final double minimum = maxScanSize < 120 ? maxScanSize : 120.0;

        final double scanSize = (size.width * 0.64)
            .clamp(minimum, maxScanSize)
            .toDouble();

        final Rect scanWindow = Rect.fromCenter(
          center: Offset(size.width / 2, size.height * 0.44),
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
              borderColor: RiderColors.primary,
              borderWidth: 3,
              borderRadius: BorderRadius.circular(16),
              color: Colors.black.withValues(alpha: 0.58),
            ),

            Positioned(
              top: scanWindow.bottom + 14,
              left: 12,
              right: 12,
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 9,
                ),
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.70),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  _scannerGuide,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: _serverConfirmed
                        ? Colors.greenAccent
                        : _scanError != null
                        ? Colors.orangeAccent
                        : Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }

  Widget _cameraError(MobileScannerException error) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.camera_alt_outlined,
              color: Colors.white,
              size: 48,
            ),
            const SizedBox(height: 12),
            const Text(
              'Camera Unavailable',
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w800,
                fontSize: 18,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Allow camera permission and try again.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white70),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _resetScanner,
              child: const Text('Try Again'),
            ),
          ],
        ),
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
                    onPressed: _hasScanned || _isProcessing || _isResetting
                        ? null
                        : _toggleTorch,
                  );
                },
          ),
          const SizedBox(width: 16),
          _controlButton(
            icon: Icons.refresh_rounded,
            label: 'Reset',
            onPressed: _isProcessing || _isResetting ? null : _resetScanner,
          ),
        ],
      ),
    );
  }

  Widget _controlButton({
    required IconData icon,
    required String label,
    required VoidCallback? onPressed,
  }) {
    return Material(
      color: Colors.white.withValues(alpha: 0.10),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
          child: Row(
            children: [
              Icon(
                icon,
                color: onPressed == null ? Colors.white38 : Colors.white,
              ),
              const SizedBox(width: 8),
              Text(
                label,
                style: TextStyle(
                  color: onPressed == null ? Colors.white38 : Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // BOTTOM RESULT PANEL
  // ============================================================

  Widget _bottomPanel() {
    return Container(
      width: double.infinity,
      constraints: BoxConstraints(
        maxHeight: MediaQuery.sizeOf(context).height * 0.48,
      ),
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
      decoration: const BoxDecoration(
        color: RiderColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      child: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        child: _isProcessing
            ? _loadingPanel()
            : _serverConfirmed && _foundParcel != null
            ? _successPanel(_foundParcel!)
            : _scanError != null
            ? _errorPanel()
            : _waitingPanel(),
      ),
    );
  }

  Widget _loadingPanel() {
    return const Column(
      children: [
        SizedBox(
          height: 32,
          width: 32,
          child: CircularProgressIndicator(
            strokeWidth: 3,
            color: RiderColors.primary,
          ),
        ),
        SizedBox(height: 14),
        Text('Confirming Pickup...', style: RiderStyle.sectionTitle),
        SizedBox(height: 6),
        Text(
          'Checking the QR code and updating '
          'the shipment with BiliHub.',
          textAlign: TextAlign.center,
          style: RiderStyle.small,
        ),
      ],
    );
  }

  Widget _waitingPanel() {
    return Column(
      children: [
        const Icon(
          Icons.qr_code_scanner_rounded,
          size: 46,
          color: RiderColors.primary,
        ),
        const SizedBox(height: 10),
        const Text('Ready to Scan', style: RiderStyle.sectionTitle),
        const SizedBox(height: 6),
        const Text(
          'Scan the original BiliHub QR code '
          'to confirm physical pickup.',
          textAlign: TextAlign.center,
          style: RiderStyle.small,
        ),
        if (widget.scannedParcel != null) ...[
          const SizedBox(height: 12),
          Text(
            'Selected parcel: '
            '${_display(widget.scannedParcel!.trackingNumber)}',
            textAlign: TextAlign.center,
            style: RiderStyle.small,
          ),
        ],
      ],
    );
  }

  Widget _errorPanel() {
    return Column(
      children: [
        Icon(
          _needsStatusCheck
              ? Icons.warning_amber_rounded
              : Icons.error_outline_rounded,
          size: 40,
          color: RiderColors.danger,
        ),
        const SizedBox(height: 10),
        Text(
          _needsStatusCheck ? 'Scan Result Uncertain' : 'QR Scan Unsuccessful',
          style: RiderStyle.sectionTitle,
        ),
        const SizedBox(height: 8),
        Text(
          _scanError ?? 'Unable to scan the parcel.',
          textAlign: TextAlign.center,
          style: RiderStyle.small,
        ),
        const SizedBox(height: 14),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: _openMyDeliveries,
            style: RiderStyle.primaryButton,
            icon: const Icon(
              Icons.local_shipping_outlined,
              color: Colors.white,
            ),
            label: const Text(
              'CHECK MY DELIVERIES',
              style: RiderStyle.buttonText,
            ),
          ),
        ),
        if (!_needsStatusCheck) ...[
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: _resetScanner,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Scan Another QR'),
          ),
        ],
      ],
    );
  }

  // ============================================================
  // SUCCESS PANEL
  // ============================================================

  Widget _successPanel(ParcelModel parcel) {
    final bool sellerPickup = _isSellerPickup;
    final bool hubPickup = _isHubPickup;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Icon(
              Icons.check_circle_rounded,
              color: RiderColors.success,
              size: 32,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                sellerPickup
                    ? 'Seller Pickup Confirmed'
                    : 'Hub Pickup Confirmed',
                style: RiderStyle.sectionTitle,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          _scanMessage ?? 'Pickup confirmed by BiliHub.',
          style: RiderStyle.small,
        ),
        const SizedBox(height: 14),
        AppInfoRow(
          label: 'Tracking Number',
          value: _display(_trackingNumber ?? parcel.trackingNumber),
          icon: Icons.qr_code_2_rounded,
          iconColor: RiderColors.primary,
          selectable: true,
          compact: true,
        ),
        AppInfoRow(
          label: 'Scan Type',
          value: sellerPickup ? 'Seller Pickup' : 'Final-Mile Hub Pickup',
          icon: Icons.qr_code_scanner_rounded,
          iconColor: RiderColors.primary,
          compact: true,
        ),
        AppInfoRow(
          label: 'New Shipment Status',
          value: sellerPickup ? 'In Transit' : 'Picked Up',
          icon: Icons.inventory_2_outlined,
          iconColor: RiderColors.primary,
          compact: true,
        ),
        const SizedBox(height: 7),
        StatusChip(status: parcel.status),
        const SizedBox(height: 12),
        if (sellerPickup && parcel.pickupAddress.trim().isNotEmpty)
          AppInfoRow(
            label: 'Seller Pickup Address',
            value: parcel.pickupAddress,
            icon: Icons.storefront_outlined,
            iconColor: RiderColors.primary,
            compact: true,
          ),
        if (hubPickup && parcel.currentHubName.trim().isNotEmpty)
          AppInfoRow(
            label: 'Pickup Hub',
            value: parcel.currentHubName,
            icon: Icons.warehouse_outlined,
            iconColor: RiderColors.primary,
            compact: true,
          ),
        AppAddressCard(
          recipientName: parcel.customerName,
          phoneNumber: parcel.customerPhone,
          address: parcel.deliveryAddress,
          title: 'Delivery Address',
          accentColor: RiderColors.primary,
          compact: true,
        ),
        const SizedBox(height: 14),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(13),
          decoration: BoxDecoration(
            color: RiderColors.lightPurple,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: RiderColors.border),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                sellerPickup
                    ? Icons.warehouse_outlined
                    : Icons.delivery_dining_rounded,
                color: RiderColors.primary,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  sellerPickup
                      ? 'Next: Deliver this parcel to '
                            'the sorting center. In My Deliveries, '
                            'confirm Arrived at Sorting Center. '
                            'Logistics must still receive it.'
                      : 'Next: Open My Deliveries, '
                            'choose Manage Delivery, then '
                            'Mark Out for Delivery. '
                            'Upload proof after handing '
                            'the parcel to the receiver.',
                  style: RiderStyle.small,
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
            onPressed: _openMyDeliveries,
            style: RiderStyle.primaryButton,
            icon: const Icon(
              Icons.local_shipping_outlined,
              color: Colors.white,
            ),
            label: Text(
              sellerPickup ? 'GO TO SELLER PICKUPS' : 'GO TO MY DELIVERIES',
              style: RiderStyle.buttonText,
            ),
          ),
        ),
        const SizedBox(height: 10),
        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: _resetScanner,
            icon: const Icon(Icons.qr_code_scanner_rounded),
            label: const Text('Scan Another Parcel'),
            style: OutlinedButton.styleFrom(
              foregroundColor: RiderColors.primary,
              side: const BorderSide(color: RiderColors.primary),
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
