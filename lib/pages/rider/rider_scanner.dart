import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../models/parcel_model.dart';
import '../../sevices/api_services.dart';
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
  bool _isProcessingScan = false;

  String? _scanError;
  String? _scanMessage;
  String? _scanType;

  ParcelModel? _foundParcel;

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

    _foundParcel = widget.scannedParcel;
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
            'Scan Waybill',
            style: TextStyle(
              color: Colors.white,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          SizedBox(height: 2),
          Text(
            'Scan the BiliHub parcel or website QR code',
            style: TextStyle(color: Colors.white60, fontSize: 11),
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
              borderColor: RiderColors.primary,
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
                      _scannerGuideText,
                      style: TextStyle(
                        color: _foundParcel != null
                            ? Colors.greenAccent
                            : _scanError != null
                            ? Colors.orangeAccent
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
                        _displayQrValue(_scannedCode!),
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

  String get _scannerGuideText {
    if (_isProcessingScan) {
      return 'Verifying BiliHub QR...';
    }

    if (_foundParcel != null) {
      return 'Waybill verified';
    }

    if (_scanError != null) {
      return 'Unable to verify QR code';
    }

    if (_hasScanned) {
      return 'QR code detected';
    }

    return 'Place the BiliHub QR code inside the frame';
  }

  // ============================================================
  // QR DETECTION
  // ============================================================

  Future<void> _handleDetection(BarcodeCapture capture) async {
    if (_hasScanned || _isProcessingScan || capture.barcodes.isEmpty) {
      return;
    }

    final Barcode barcode = capture.barcodes.first;

    if (barcode.format != BarcodeFormat.qrCode) {
      return;
    }

    final String? rawValue = barcode.rawValue;

    if (rawValue == null || rawValue.trim().isEmpty) {
      return;
    }

    final String qrPayload = rawValue.trim();

    setState(() {
      _scannedCode = qrPayload;

      _hasScanned = true;
      _isProcessingScan = true;

      _scanError = null;
      _scanMessage = null;
      _scanType = null;

      _foundParcel = null;
    });

    try {
      await _scannerController.stop();
    } catch (error) {
      debugPrint('Unable to stop scanner: $error');
    }

    // IMPORTANT:
    // Send the COMPLETE QR payload to Laravel.
    //
    // Do not extract only the tracking number.
    //
    // Laravel ScannerController verifies:
    // t  = tracking_number
    // q  = qr_token
    // so = seller_order_id
    await _processScannedWaybill(qrPayload);
  }

  // ============================================================
  // SEND QR TO LARAVEL
  // ============================================================

  Future<void> _processScannedWaybill(String qrPayload) async {
    try {
      final Map<String, dynamic> result = await ApiService.scanRiderWaybill(
        qrPayload: qrPayload,
      );

      if (!mounted) {
        return;
      }

      if (result['success'] != true) {
        final String message = result['message']?.toString().trim() ?? '';

        setState(() {
          _isProcessingScan = false;
          _foundParcel = null;

          _scanError = message.isNotEmpty
              ? message
              : 'Unable to verify the scanned BiliHub QR code.';
        });

        _showScanError(_scanError!);

        return;
      }

      final dynamic rawShipment = result['shipment'];

      if (rawShipment is! Map) {
        setState(() {
          _isProcessingScan = false;

          _foundParcel = null;

          _scanError = 'The QR code was verified but the server returned invalid shipment information.';
        });

        _showScanError(_scanError!);

        return;
      }

      final Map<String, dynamic> shipmentData = _normalizeShipment(
        Map<String, dynamic>.from(rawShipment),
      );

      final ParcelModel parcel = ParcelModel.fromJson(shipmentData);

      final String message = result['message']?.toString().trim() ?? '';

      final String scanType = result['scan_type']?.toString().trim() ?? '';

      setState(() {
        _foundParcel = parcel;

        _isProcessingScan = false;

        _scanError = null;

        _scanMessage = message.isNotEmpty
            ? message
            : 'Waybill verified successfully.';

        _scanType = scanType.isNotEmpty ? scanType : null;
      });

      _showScanSuccess(parcel.trackingNumber, _scanMessage!);
    } catch (error) {
      debugPrint('Rider scanner error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _isProcessingScan = false;

        _foundParcel = null;

        _scanError = 'Unable to process the scanned QR code. Please try again.';
      });

      _showScanError(_scanError!);
    }
  }

  // ============================================================
  // NORMALIZE SHIPMENT RESPONSE
  // ============================================================

  Map<String, dynamic> _normalizeShipment(Map<String, dynamic> raw) {
    final Map<String, dynamic> data = Map<String, dynamic>.from(raw);

    final dynamic nestedShipment = raw['shipment'];

    if (nestedShipment is Map) {
      data.addAll(Map<String, dynamic>.from(nestedShipment));
    }

    final dynamic order = raw['order'];

    if (order is Map) {
      final Map<String, dynamic> orderMap = Map<String, dynamic>.from(order);

      data['customer_name'] ??=
          orderMap['customer_name'] ??
          orderMap['buyer_name'] ??
          orderMap['name'];

      data['customer_phone'] ??=
          orderMap['customer_phone'] ??
          orderMap['phone'] ??
          orderMap['mobile_number'];

      data['delivery_address'] ??=
          orderMap['delivery_address'] ?? orderMap['address'];

      data['order_date'] ??= orderMap['order_date'] ?? orderMap['created_at'];
    }

    data['id'] ??= data['shipment_id'] ?? data['order_id'] ?? 0;

    data['tracking_number'] ??=
        data['tracking'] ??
        data['tracking_code'] ??
        data['waybill_number'] ??
        '';

    data['customer_name'] ??=
        data['recipient_name'] ?? data['buyer_name'] ?? '';

    data['customer_phone'] ??=
        data['phone'] ?? data['mobile_number'] ?? data['recipient_phone'] ?? '';

    data['delivery_address'] ??=
        data['address'] ??
        data['shipping_address'] ??
        data['recipient_address'] ??
        '';

    final dynamic rider = data['rider'];

    if (rider is Map) {
      data['rider_name'] ??= rider['name'];

      data['rider_id'] ??= rider['id'];
    }

    data['rider_name'] ??= '';

    data['status'] ??= data['shipment_status'] ?? data['delivery_status'] ?? '';

    data['order_date'] ??= data['created_at'] ?? data['assigned_at'] ?? '';

    data['delivery_earnings'] ??= data['earnings'] ?? data['delivery_fee'] ?? 0;

    data['latitude'] ??= data['lat'];

    data['longitude'] ??= data['lng'] ?? data['lon'];

    return data;
  }

  // ============================================================
  // QR DISPLAY
  // ============================================================

  String _displayQrValue(String rawValue) {
    final String value = rawValue.trim();

    if (value.isEmpty) {
      return 'BiliHub QR';
    }

    // This is display-only.
    // The complete original payload
    // is still sent to Laravel.
    try {
      final Uri? uri = Uri.tryParse(value);

      if (uri != null &&
          (uri.scheme.toLowerCase() == 'http' ||
              uri.scheme.toLowerCase() == 'https')) {
        return 'BiliHub Website QR';
      }
    } catch (_) {
      // Keep original display handling below.
    }

    if (value.startsWith('{') && value.endsWith('}')) {
      return 'BiliHub Waybill QR';
    }

    return value;
  }

  // ============================================================
  // SUCCESS MESSAGE
  // ============================================================

  void _showScanSuccess(String trackingNumber, String message) {
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
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    trackingNumber.trim().isNotEmpty
                        ? trackingNumber
                        : 'BiliHub Waybill',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    message,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Colors.white70, fontSize: 11),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // ERROR MESSAGE
  // ============================================================

  void _showScanError(String message) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: RiderColors.danger,
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
            onPressed: () async {
              try {
                await _scannerController.start();
              } catch (scanError) {
                debugPrint('Scanner start error: $scanError');
              }
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
            onTap: _toggleTorch,
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

  Future<void> _toggleTorch() async {
    try {
      await _scannerController.toggleTorch();

      if (mounted) {
        setState(() {});
      }
    } catch (error) {
      debugPrint('Torch error: $error');
    }
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

  Future<void> _resetScanner() async {
    if (_isProcessingScan) {
      return;
    }

    if (!mounted) {
      return;
    }

    setState(() {
      _scannedCode = null;

      _hasScanned = false;

      _foundParcel = null;

      _scanError = null;
      _scanMessage = null;
      _scanType = null;

      _isProcessingScan = false;
    });

    try {
      await _scannerController.start();
    } catch (error) {
      debugPrint('Scanner reset error: $error');
    }
  }

  // ============================================================
  // BOTTOM PANEL
  // ============================================================

  Widget _bottomPanel() {
    return Container(
      width: double.infinity,
      constraints: const BoxConstraints(maxHeight: 350),
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
      decoration: const BoxDecoration(
        color: RiderColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      child: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        child: _isProcessingScan
            ? _checkingPanel()
            : _foundParcel != null
            ? _parcelFoundPanel(_foundParcel!)
            : _waitingPanel(),
      ),
    );
  }

  // ============================================================
  // CHECKING PANEL
  // ============================================================

  Widget _checkingPanel() {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 18),
      child: Column(
        children: [
          CircularProgressIndicator(color: RiderColors.primary),
          SizedBox(height: 12),
          Text('Verifying BiliHub QR...', style: RiderStyle.cardTitle),
          SizedBox(height: 3),
          Text(
            'Please wait while BiliHub securely verifies the waybill and Rider assignment.',
            textAlign: TextAlign.center,
            style: RiderStyle.small,
          ),
        ],
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
          child: Icon(
            _scanError != null
                ? Icons.error_outline_rounded
                : Icons.qr_code_scanner_rounded,
            color: _scanError != null
                ? RiderColors.danger
                : RiderColors.primary,
            size: 27,
          ),
        ),
        const SizedBox(height: 9),
        Text(
          _scanError != null ? 'Unable to Scan' : 'Ready to Scan',
          style: RiderStyle.cardTitle,
        ),
        const SizedBox(height: 3),
        Text(
          _scanError ??
              'Point your camera at the original BiliHub waybill QR code.',
          textAlign: TextAlign.center,
          style: RiderStyle.small,
        ),
        if (_scannedCode != null) ...[
          const SizedBox(height: 10),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: RiderColors.background,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              children: [
                const Text('Scanned QR', style: RiderStyle.cardTitle),
                const SizedBox(height: 4),
                Text(
                  _displayQrValue(_scannedCode!),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: RiderStyle.small,
                ),
              ],
            ),
          ),
        ],
        if (_scanError != null) ...[
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton.icon(
              onPressed: _resetScanner,
              style: RiderStyle.primaryButton,
              icon: const Icon(
                Icons.qr_code_scanner_rounded,
                color: Colors.white,
              ),
              label: const Text('Scan Again', style: RiderStyle.buttonText),
            ),
          ),
        ],
      ],
    );
  }

  // ============================================================
  // PARCEL VERIFIED
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
        const Row(
          children: [
            Icon(
              Icons.check_circle_outline_rounded,
              color: RiderColors.success,
              size: 28,
            ),
            SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Waybill Verified', style: RiderStyle.sectionTitle),
                  SizedBox(height: 2),
                  Text(
                    'The secure BiliHub QR has been verified successfully.',
                    style: RiderStyle.small,
                  ),
                ],
              ),
            ),
          ],
        ),
        if (_scanMessage != null) ...[
          const SizedBox(height: 12),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(11),
            decoration: BoxDecoration(
              color: RiderColors.lightPurple,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(_scanMessage!, style: RiderStyle.small),
          ),
        ],
        const SizedBox(height: 14),
        _infoRow('Tracking Number', parcel.trackingNumber),
        _infoRow(
          'Customer',
          parcel.customerName.isNotEmpty
              ? parcel.customerName
              : 'Not available',
        ),
        _infoRow(
          'Address',
          parcel.deliveryAddress.isNotEmpty
              ? parcel.deliveryAddress
              : 'Not available',
        ),
        _infoRow('Status', _formatStatus(parcel.status)),
        if (_scanType != null)
          _infoRow('Scan Type', _formatScanType(_scanType!)),
        const SizedBox(height: 8),
        SizedBox(
          width: double.infinity,
          height: 50,
          child: ElevatedButton.icon(
            onPressed: _resetScanner,
            style: RiderStyle.primaryButton,
            icon: const Icon(
              Icons.qr_code_scanner_rounded,
              color: Colors.white,
            ),
            label: const Text(
              'Scan Another Parcel',
              style: RiderStyle.buttonText,
            ),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // STATUS FORMATTER
  // ============================================================

  String _formatStatus(String status) {
    final String value = status.trim().replaceAll('-', '_');

    if (value.isEmpty) {
      return 'Not available';
    }

    return value
        .split('_')
        .where((String part) => part.trim().isNotEmpty)
        .map(
          (String part) =>
              '${part[0].toUpperCase()}'
              '${part.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  // ============================================================
  // SCAN TYPE FORMATTER
  // ============================================================

  String _formatScanType(String type) {
    switch (type.trim().toLowerCase()) {
      case 'seller_pickup':
        return 'Seller Pickup';

      case 'hub_pickup':
        return 'Hub Pickup';

      default:
        return _formatStatus(type);
    }
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
            child: Text(
              value.trim().isNotEmpty ? value : 'Not available',
              softWrap: true,
              style: RiderStyle.cardTitle,
            ),
          ),
        ],
      ),
    );
  }
}
