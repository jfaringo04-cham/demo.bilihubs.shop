import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../sevices/api_services.dart';
import '../../styles/logistics_style.dart';
import '../../widgets/status_chip.dart';

class ParcelInformation extends StatefulWidget {
  final ParcelModel parcel;

  const ParcelInformation({super.key, required this.parcel});

  @override
  State<ParcelInformation> createState() => _ParcelInformationState();
}

class _ParcelInformationState extends State<ParcelInformation> {
  late ParcelModel _parcel;

  Map<String, dynamic> _details = <String, dynamic>{};

  bool _isLoading = true;
  bool _isProcessing = false;

  String? _errorMessage;

  @override
  void initState() {
    super.initState();

    _parcel = widget.parcel;

    _loadDetails();
  }

  // ============================================================
  // LOAD DETAILS
  // ============================================================

  Future<void> _loadDetails({bool showLoading = true}) async {
    if (showLoading && mounted) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final Map<String, dynamic> result = await ApiService.getLogisticsShipment(
        _parcel.id,
      );

      if (!mounted) return;

      if (result['success'] != true) {
        setState(() {
          _isLoading = false;

          _errorMessage =
              result['message']?.toString() ?? 'Unable to load parcel details.';
        });

        return;
      }

      final dynamic rawData = result['data'];

      if (rawData is! Map) {
        setState(() {
          _isLoading = false;
          _errorMessage = 'Invalid parcel response received.';
        });

        return;
      }

      final Map<String, dynamic> details = Map<String, dynamic>.from(rawData);

      details['order_date'] ??= details['created_at'] ?? '';

      details['tracking_number'] ??= details['tracking_code'] ?? '';

      details['customer_name'] ??= '';
      details['customer_phone'] ??= '';
      details['delivery_address'] ??= '';
      details['rider_name'] ??= '';
      details['status'] ??= 'pending';

      ParcelModel parsedParcel;

      try {
        parsedParcel = ParcelModel.fromJson(details);
      } catch (e) {
        setState(() {
          _isLoading = false;
          _errorMessage = 'Unable to read parcel data: $e';
        });

        return;
      }

      setState(() {
        _details = details;
        _parcel = parsedParcel;

        _isLoading = false;
        _errorMessage = null;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isLoading = false;
        _errorMessage = 'Unable to load parcel details: $e';
      });
    }
  }

  // ============================================================
  // SORTING STATUS
  // ============================================================

  String get _sortingStatus {
    final String value =
        _details['sorting_status']?.toString().trim().toLowerCase() ?? '';

    if (value.isEmpty) {
      return 'pending';
    }

    return value;
  }

  // ============================================================
  // FINISHED CHECK
  // ============================================================

  bool get _isFinished {
    final String status = _parcel.normalizedStatus;

    return status == 'delivered' ||
        status == 'cancelled' ||
        _sortingStatus == 'completed';
  }

  // ============================================================
  // DISPLAY
  // ============================================================

  String _display(dynamic value, {String fallback = '—'}) {
    final String text = value?.toString().trim() ?? '';

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return fallback;
    }

    return text;
  }

  // ============================================================
  // PROCESS PARCEL
  // ============================================================

  Future<void> _process(String action) async {
    if (_isProcessing || _isFinished) {
      return;
    }

    String? deliveryZone;
    String? deliveryType;

    String? sortingArea;
    String? rackNumber;

    if (action == 'scan') {
      final Map<String, String>? values = await _showScanDialog();

      if (values == null) {
        return;
      }

      deliveryZone = values['delivery_zone'];

      deliveryType = values['delivery_type'];
    }

    if (action == 'sort') {
      final Map<String, String>? values = await _showSortDialog();

      if (values == null) {
        return;
      }

      sortingArea = values['sorting_area'];

      rackNumber = values['rack_number'];
    }

    if (!mounted) return;

    setState(() {
      _isProcessing = true;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.processLogisticsShipment(
            shipmentId: _parcel.id,
            action: action,
            deliveryZone: deliveryZone,
            deliveryType: deliveryType,
            sortingArea: sortingArea,
            rackNumber: rackNumber,
          );

      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      final String message =
          result['message']?.toString() ??
          (result['success'] == true
              ? 'Parcel updated successfully.'
              : 'Unable to update parcel.');

      ScaffoldMessenger.of(context).hideCurrentSnackBar();

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          content: Text(message),
          backgroundColor: result['success'] == true
              ? LogisticsColors.success
              : LogisticsColors.danger,
        ),
      );

      if (result['success'] == true) {
        await _loadDetails(showLoading: false);
      }
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      ScaffoldMessenger.of(context).hideCurrentSnackBar();

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: LogisticsColors.danger,
          content: Text('Unable to process parcel: $e'),
        ),
      );
    }
  }

  // ============================================================
  // SCAN DIALOG
  // ============================================================

  Future<Map<String, String>?> _showScanDialog() async {
    final TextEditingController zoneController = TextEditingController(
      text: _details['delivery_zone']?.toString() ?? '',
    );

    String deliveryType =
        _details['delivery_type']?.toString().trim().toLowerCase() ??
        'standard';

    const Set<String> allowedTypes = {'standard', 'same_day', 'express'};

    if (!allowedTypes.contains(deliveryType)) {
      deliveryType = 'standard';
    }

    final Map<String, String>? result = await showDialog<Map<String, String>>(
      context: context,
      builder: (BuildContext dialogContext) {
        return StatefulBuilder(
          builder: (BuildContext context, StateSetter setDialogState) {
            return AlertDialog(
              title: const Text('Scan Parcel'),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: zoneController,
                    decoration: LogisticsStyle.inputDecoration(
                      hint: 'Delivery zone',
                      icon: Icons.location_on_outlined,
                    ),
                  ),

                  const SizedBox(height: 14),

                  DropdownButtonFormField<String>(
                    initialValue: deliveryType,
                    decoration: LogisticsStyle.inputDecoration(
                      hint: 'Delivery type',
                      icon: Icons.local_shipping_outlined,
                    ),
                    items: const [
                      DropdownMenuItem(
                        value: 'standard',
                        child: Text('Standard'),
                      ),
                      DropdownMenuItem(
                        value: 'same_day',
                        child: Text('Same Day'),
                      ),
                      DropdownMenuItem(
                        value: 'express',
                        child: Text('Express'),
                      ),
                    ],
                    onChanged: (String? value) {
                      if (value == null) {
                        return;
                      }

                      setDialogState(() {
                        deliveryType = value;
                      });
                    },
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () {
                    Navigator.pop(dialogContext);
                  },
                  child: const Text('Cancel'),
                ),
                ElevatedButton(
                  onPressed: () {
                    final String zone = zoneController.text.trim();

                    if (zone.isEmpty) {
                      return;
                    }

                    Navigator.pop(dialogContext, <String, String>{
                      'delivery_zone': zone,
                      'delivery_type': deliveryType,
                    });
                  },
                  child: const Text('Continue'),
                ),
              ],
            );
          },
        );
      },
    );

    zoneController.dispose();

    return result;
  }

  // ============================================================
  // SORT DIALOG
  // ============================================================

  Future<Map<String, String>?> _showSortDialog() async {
    final TextEditingController areaController = TextEditingController(
      text: _details['sorting_area']?.toString() ?? '',
    );

    final TextEditingController rackController = TextEditingController(
      text: _details['rack_number']?.toString() ?? '',
    );

    final Map<String, String>? result = await showDialog<Map<String, String>>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          title: const Text('Sort Parcel'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: areaController,
                decoration: LogisticsStyle.inputDecoration(
                  hint: 'Sorting area',
                  icon: Icons.warehouse_outlined,
                ),
              ),

              const SizedBox(height: 14),

              TextField(
                controller: rackController,
                decoration: LogisticsStyle.inputDecoration(
                  hint: 'Rack number',
                  icon: Icons.inventory_2_outlined,
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);
              },
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () {
                final String area = areaController.text.trim();

                final String rack = rackController.text.trim();

                if (area.isEmpty || rack.isEmpty) {
                  return;
                }

                Navigator.pop(dialogContext, <String, String>{
                  'sorting_area': area,
                  'rack_number': rack,
                });
              },
              child: const Text('Continue'),
            ),
          ],
        );
      },
    );

    areaController.dispose();
    rackController.dispose();

    return result;
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: LogisticsColors.background,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        iconTheme: const IconThemeData(color: LogisticsColors.textDark),
        title: const Text('Parcel Information', style: LogisticsStyle.title),
        actions: [
          IconButton(
            onPressed: _isLoading
                ? null
                : () {
                    _loadDetails();
                  },
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadDetails,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          padding: EdgeInsets.all(width * 0.045),
          children: [
            if (_isLoading)
              const LinearProgressIndicator(color: LogisticsColors.primary),

            if (_errorMessage != null) ...[
              const SizedBox(height: 12),
              _errorCard(),
            ],

            const SizedBox(height: 12),

            // ====================================================
            // CURRENT STATUS
            // ====================================================
            Container(
              padding: const EdgeInsets.all(18),
              decoration: LogisticsStyle.card,
              child: Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Current Status',
                      style: LogisticsStyle.sectionTitle,
                    ),
                  ),
                  StatusChip(
                    status: _display(_parcel.status, fallback: 'unknown'),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // ====================================================
            // PARCEL DETAILS
            // ====================================================
            Container(
              padding: const EdgeInsets.all(18),
              decoration: LogisticsStyle.card,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Parcel Details',
                    style: LogisticsStyle.sectionTitle,
                  ),

                  const SizedBox(height: 14),

                  _info('Tracking Number', _parcel.trackingNumber),

                  _info('Order Number', _details['order_number']),

                  _info('Customer', _parcel.customerName),

                  _info('Phone', _parcel.customerPhone),

                  _info('Delivery Address', _parcel.deliveryAddress),

                  _info('Pickup Address', _details['pickup_address']),

                  _info('Seller', _details['seller_name']),

                  _info('Hub', _details['hub_name']),

                  _info('Rider', _parcel.riderName, fallback: 'Not assigned'),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // ====================================================
            // SORTING INFO
            // ====================================================
            Container(
              padding: const EdgeInsets.all(18),
              decoration: LogisticsStyle.card,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Sorting Information',
                    style: LogisticsStyle.sectionTitle,
                  ),

                  const SizedBox(height: 14),

                  _info('Sorting Status', _sortingStatus.replaceAll('_', ' ')),

                  _info('Delivery Zone', _details['delivery_zone']),

                  _info('Delivery Type', _details['delivery_type']),

                  _info('Sorting Area', _details['sorting_area']),

                  _info('Rack Number', _details['rack_number']),

                  _info('Received At', _details['received_at']),

                  _info('Scanned At', _details['scanned_at']),

                  _info('Sorted At', _details['sorted_at']),

                  _info('Staged At', _details['staged_at']),
                ],
              ),
            ),

            const SizedBox(height: 16),

            _processingCard(),

            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // ERROR CARD
  // ============================================================

  Widget _errorCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: LogisticsColors.danger.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: LogisticsColors.danger.withValues(alpha: 0.25),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.error_outline_rounded,
            color: LogisticsColors.danger,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              _errorMessage ?? 'Unable to load parcel.',
              style: LogisticsStyle.small,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PROCESSING CARD
  // ============================================================

  Widget _processingCard() {
    String action;
    String label;
    IconData icon;

    if (_isFinished) {
      return Container(
        padding: const EdgeInsets.all(18),
        decoration: LogisticsStyle.card,
        child: const Row(
          children: [
            Icon(
              Icons.check_circle_outline_rounded,
              color: LogisticsColors.success,
            ),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'No further sorting-center action is required for this parcel.',
                style: LogisticsStyle.small,
              ),
            ),
          ],
        ),
      );
    }

    switch (_sortingStatus) {
      case 'received':
        action = 'scan';
        label = 'Scan & Classify Parcel';
        icon = Icons.qr_code_scanner_rounded;
        break;

      case 'scanned':
        action = 'sort';
        label = 'Sort Parcel';
        icon = Icons.sort_rounded;
        break;

      case 'sorted':
        action = 'stage';
        label = 'Stage Parcel';
        icon = Icons.inventory_2_outlined;
        break;

      case 'staged':
        return Container(
          padding: const EdgeInsets.all(18),
          decoration: LogisticsStyle.card,
          child: const Row(
            children: [
              Icon(Icons.inventory_2_outlined, color: LogisticsColors.success),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Parcel is staged and ready for rider assignment.',
                  style: LogisticsStyle.small,
                ),
              ),
            ],
          ),
        );

      default:
        action = 'receive';
        label = 'Receive Parcel';
        icon = Icons.move_to_inbox_rounded;
    }

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Parcel Processing', style: LogisticsStyle.sectionTitle),

          const SizedBox(height: 8),

          Text(
            'Next sorting-center step: ${label.replaceAll('&', 'and')}.',
            style: LogisticsStyle.small,
          ),

          const SizedBox(height: 14),

          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton.icon(
              onPressed: _isProcessing
                  ? null
                  : () {
                      _process(action);
                    },
              icon: _isProcessing
                  ? const SizedBox(
                      height: 18,
                      width: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : Icon(icon),
              label: Text(_isProcessing ? 'Processing...' : label),
              style: LogisticsStyle.primaryButton,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // INFO ROW
  // ============================================================

  Widget _info(String label, dynamic value, {String fallback = '—'}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 115, child: Text(label, style: LogisticsStyle.small)),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              _display(value, fallback: fallback),
              style: LogisticsStyle.cardTitle,
            ),
          ),
        ],
      ),
    );
  }
}
