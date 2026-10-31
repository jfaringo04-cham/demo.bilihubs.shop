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
  bool _isRefreshing = false;
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
    if (!mounted) {
      return;
    }

    setState(() {
      if (showLoading) {
        _isLoading = true;
      } else {
        _isRefreshing = true;
      }

      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> result = await ApiService.getLogisticsShipment(
        _parcel.id,
      );

      if (!mounted) {
        return;
      }

      if (result['success'] != true) {
        final String message = result['message']?.toString().trim() ?? '';

        setState(() {
          _isLoading = false;
          _isRefreshing = false;

          _errorMessage = message.isNotEmpty
              ? message
              : 'Unable to load parcel details.';
        });

        return;
      }

      final Map<String, dynamic>? details = _extractShipmentMap(result);

      if (details == null) {
        setState(() {
          _isLoading = false;
          _isRefreshing = false;

          _errorMessage = 'Invalid parcel response received.';
        });

        return;
      }

      final Map<String, dynamic> normalized = _normalizeShipment(details);

      ParcelModel parsedParcel;

      try {
        parsedParcel = ParcelModel.fromJson(normalized);
      } catch (error) {
        setState(() {
          _isLoading = false;
          _isRefreshing = false;

          _errorMessage = 'Unable to read parcel data: $error';
        });

        return;
      }

      setState(() {
        _details = normalized;
        _parcel = parsedParcel;

        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = null;
      });
    } catch (error) {
      debugPrint('Logistics parcel detail error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _isLoading = false;
        _isRefreshing = false;

        _errorMessage =
            'Unable to load parcel details. '
            'Please check your connection and try again.';
      });
    }
  }

  // ============================================================
  // EXTRACT RESPONSE
  // ============================================================

  Map<String, dynamic>? _extractShipmentMap(Map<String, dynamic> result) {
    final dynamic rawData = result['data'];

    if (rawData is Map) {
      final Map<String, dynamic> data = Map<String, dynamic>.from(rawData);

      final dynamic nestedShipment = data['shipment'];

      if (nestedShipment is Map) {
        return Map<String, dynamic>.from(nestedShipment);
      }

      return data;
    }

    final dynamic directShipment = result['shipment'];

    if (directShipment is Map) {
      return Map<String, dynamic>.from(directShipment);
    }

    return null;
  }

  // ============================================================
  // NORMALIZE SHIPMENT
  // ============================================================

  Map<String, dynamic> _normalizeShipment(Map<String, dynamic> raw) {
    final Map<String, dynamic> data = Map<String, dynamic>.from(raw);

    final dynamic nestedShipment = raw['shipment'];

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

      data['order_number'] ??= orderMap['order_number'] ?? orderMap['number'];

      data['order_date'] ??= orderMap['created_at'];
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
  // STATUS HELPERS
  // ============================================================

  String get _sortingStatus {
    final String value = _parcel.normalizedSortingStatus;

    if (value.isNotEmpty) {
      return value;
    }

    final String fallback =
        _details['sorting_status']
            ?.toString()
            .trim()
            .toLowerCase()
            .replaceAll(' ', '_')
            .replaceAll('-', '_') ??
        '';

    return fallback.isEmpty ? 'pending' : fallback;
  }

  bool get _isFinished {
    final String status = _parcel.normalizedStatus;

    return status == 'delivered' ||
        status == 'completed' ||
        status == 'cancelled' ||
        status == 'delivery_failed' ||
        status == 'failed';
  }

  bool get _isStaged {
    return _sortingStatus == 'staged' || _parcel.isStaged;
  }

  bool get _requiresLinehaul {
    return _parcel.requiresHubTransfer ||
        _parcel.isLinehaulAwaitingAssignment ||
        _parcel.isLinehaulAssigned ||
        _parcel.isLinehaulInTransit ||
        _parcel.normalizedRoutingStatus == 'in_hub_transfer';
  }

  // ============================================================
  // DISPLAY HELPERS
  // ============================================================

  String _display(dynamic value, {String fallback = '—'}) {
    final String text = value?.toString().trim() ?? '';

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return fallback;
    }

    return text;
  }

  String _formatStatus(String value) {
    final String cleaned = value
        .trim()
        .replaceAll('-', '_')
        .replaceAll(' ', '_');

    if (cleaned.isEmpty) {
      return 'Not available';
    }

    return cleaned
        .split('_')
        .where((String word) => word.isNotEmpty)
        .map(
          (String word) =>
              '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  String _formatDate(String value) {
    final String text = value.trim();

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return '—';
    }

    final DateTime? parsed = DateTime.tryParse(text);

    if (parsed == null) {
      return text;
    }

    final DateTime local = parsed.toLocal();

    const List<String> months = <String>[
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];

    final String month = months[local.month - 1];

    final int hour = local.hour == 0
        ? 12
        : local.hour > 12
        ? local.hour - 12
        : local.hour;

    final String minute = local.minute.toString().padLeft(2, '0');

    final String period = local.hour >= 12 ? 'PM' : 'AM';

    return '$month ${local.day}, ${local.year} '
        '$hour:$minute $period';
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

    if (!mounted) {
      return;
    }

    final bool? confirmed = await _confirmProcessAction(action);

    if (!mounted || confirmed != true) {
      return;
    }

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

      if (!mounted) {
        return;
      }

      setState(() {
        _isProcessing = false;
      });

      final String message = result['message']?.toString().trim() ?? '';

      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            behavior: SnackBarBehavior.floating,
            content: Text(
              message.isNotEmpty
                  ? message
                  : result['success'] == true
                  ? 'Parcel updated successfully.'
                  : 'Unable to update parcel.',
            ),
            backgroundColor: result['success'] == true
                ? LogisticsColors.success
                : LogisticsColors.danger,
          ),
        );

      if (result['success'] == true) {
        await _loadDetails(showLoading: false);
      }
    } catch (error) {
      debugPrint('Process logistics parcel error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _isProcessing = false;
      });

      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            behavior: SnackBarBehavior.floating,
            backgroundColor: LogisticsColors.danger,
            content: Text(
              'Unable to process parcel. '
              'Please try again.',
            ),
          ),
        );
    }
  }

  Future<bool?> _confirmProcessAction(String action) {
    String title;
    String message;

    switch (action) {
      case 'receive':
        title = 'Receive Parcel';
        message =
            'Confirm that this parcel has '
            'physically arrived at the sorting center.';
        break;

      case 'scan':
        title = 'Scan & Classify';
        message =
            'Confirm the parcel classification '
            'and delivery routing information.';
        break;

      case 'sort':
        title = 'Sort Parcel';
        message =
            'Confirm the sorting area and '
            'rack location for this parcel.';
        break;

      case 'stage':
        title = 'Stage Parcel';
        message =
            'Confirm that this parcel is fully sorted '
            'and ready for its next routing step.';
        break;

      default:
        title = 'Process Parcel';
        message = 'Continue with this parcel operation?';
    }

    return showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          title: Text(title),
          content: Text(message, style: LogisticsStyle.small),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext, false);
              },
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () {
                Navigator.pop(dialogContext, true);
              },
              style: LogisticsStyle.primaryButton,
              child: const Text('Confirm'),
            ),
          ],
        );
      },
    );
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

    const Set<String> allowedTypes = <String>{
      'standard',
      'same_day',
      'express',
    };

    if (!allowedTypes.contains(deliveryType)) {
      deliveryType = 'standard';
    }

    final Map<String, String>? result = await showDialog<Map<String, String>>(
      context: context,
      builder: (BuildContext dialogContext) {
        return StatefulBuilder(
          builder: (BuildContext context, StateSetter setDialogState) {
            return AlertDialog(
              title: const Text('Scan & Classify Parcel'),
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
                    items: const <DropdownMenuItem<String>>[
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

    final double padding = width < 380 ? 14 : 18;

    return Scaffold(
      backgroundColor: LogisticsColors.background,
      appBar: AppBar(
        backgroundColor: LogisticsColors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: LogisticsColors.textDark),
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Parcel Information', style: LogisticsStyle.title),
            SizedBox(height: 2),
            Text(
              'Sorting and hub routing details',
              style: LogisticsStyle.small,
            ),
          ],
        ),
        actions: [
          if (_isRefreshing)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 16),
              child: Center(
                child: SizedBox(
                  height: 19,
                  width: 19,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: LogisticsColors.primary,
                  ),
                ),
              ),
            )
          else
            IconButton(
              tooltip: 'Refresh parcel',
              onPressed: _isLoading
                  ? null
                  : () {
                      _loadDetails(showLoading: false);
                    },
              icon: const Icon(
                Icons.refresh_rounded,
                color: LogisticsColors.primary,
              ),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () {
          return _loadDetails(showLoading: false);
        },
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          padding: EdgeInsets.fromLTRB(padding, 14, padding, 30),
          children: [
            if (_isLoading)
              const LinearProgressIndicator(color: LogisticsColors.primary),

            if (_errorMessage != null) ...[
              const SizedBox(height: 12),
              _errorCard(),
            ],

            const SizedBox(height: 12),

            _statusCard(),

            const SizedBox(height: 16),

            _parcelDetailsCard(),

            const SizedBox(height: 16),

            _hubRoutingCard(),

            const SizedBox(height: 16),

            _linehaulCard(),

            const SizedBox(height: 16),

            _sortingInformationCard(),

            const SizedBox(height: 16),

            _timelineCard(),

            const SizedBox(height: 16),

            _processingCard(),

            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // STATUS CARD
  // ============================================================

  Widget _statusCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Current Status',
                  style: LogisticsStyle.sectionTitle,
                ),
              ),
              StatusChip(status: _display(_parcel.status, fallback: 'unknown')),
            ],
          ),

          if (_parcel.routingStatus.trim().isNotEmpty) ...[
            const SizedBox(height: 14),
            _compactStatusRow(
              icon: Icons.route_outlined,
              label: 'Routing Status',
              value: _formatStatus(_parcel.routingStatus),
            ),
          ],

          if (_parcel.linehaulStatus.trim().isNotEmpty) ...[
            const SizedBox(height: 9),
            _compactStatusRow(
              icon: Icons.multiple_stop_rounded,
              label: 'Line-Haul Status',
              value: _formatStatus(_parcel.linehaulStatus),
            ),
          ],
        ],
      ),
    );
  }

  Widget _compactStatusRow({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Row(
      children: [
        Icon(icon, size: 17, color: LogisticsColors.primary),
        const SizedBox(width: 8),
        Expanded(child: Text(label, style: LogisticsStyle.small)),
        const SizedBox(width: 8),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: LogisticsStyle.cardTitle,
          ),
        ),
      ],
    );
  }

  // ============================================================
  // PARCEL DETAILS
  // ============================================================

  Widget _parcelDetailsCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Parcel Details', style: LogisticsStyle.sectionTitle),

          const SizedBox(height: 14),

          _info('Tracking Number', _parcel.trackingNumber),

          _info('Order Number', _details['order_number']),

          _info('Customer', _parcel.customerName),

          _info('Phone', _parcel.customerPhone),

          _info('Delivery Address', _parcel.deliveryAddress),

          _info('Pickup Address', _parcel.pickupAddress),

          _info('Seller', _details['seller_name']),

          _info(
            'Final-Mile Rider',
            _parcel.riderName,
            fallback: 'Not assigned',
          ),

          if (_parcel.parcelWeightKg > 0)
            _info(
              'Parcel Weight',
              '${_parcel.parcelWeightKg.toStringAsFixed(2)} kg',
            ),

          if (_parcel.estimatedDistanceKm > 0)
            _info(
              'Est. Distance',
              '${_parcel.estimatedDistanceKm.toStringAsFixed(1)} km',
            ),

          _info('Order Date', _formatDate(_parcel.orderDate)),
        ],
      ),
    );
  }

  // ============================================================
  // HUB ROUTING
  // ============================================================

  Widget _hubRoutingCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Hub Routing', style: LogisticsStyle.sectionTitle),

          const SizedBox(height: 14),

          _routeHubTile(
            icon: Icons.trip_origin_rounded,
            title: 'Origin Hub',
            value: _display(_parcel.originHubName, fallback: 'Not assigned'),
          ),

          _routeConnector(),

          _routeHubTile(
            icon: Icons.multiple_stop_rounded,
            title: 'Current Hub',
            value: _display(_parcel.currentHubName, fallback: 'Not available'),
          ),

          _routeConnector(),

          _routeHubTile(
            icon: Icons.location_city_outlined,
            title: 'Destination Hub',
            value: _display(
              _parcel.destinationHubName,
              fallback: 'Not assigned',
            ),
          ),

          const SizedBox(height: 14),

          _info(
            'Routing Status',
            _formatStatus(_parcel.routingStatus),
            fallback: 'Not available',
          ),
        ],
      ),
    );
  }

  Widget _routeHubTile({
    required IconData icon,
    required String title,
    required String value,
  }) {
    return Row(
      children: [
        Container(
          height: 38,
          width: 38,
          decoration: BoxDecoration(
            color: LogisticsColors.lightPurple,
            borderRadius: BorderRadius.circular(11),
          ),
          alignment: Alignment.center,
          child: Icon(icon, color: LogisticsColors.primary, size: 19),
        ),

        const SizedBox(width: 11),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: LogisticsStyle.small),
              const SizedBox(height: 2),
              Text(value, style: LogisticsStyle.cardTitle),
            ],
          ),
        ),
      ],
    );
  }

  Widget _routeConnector() {
    return Container(
      height: 22,
      width: 2,
      margin: const EdgeInsets.only(left: 18),
      color: LogisticsColors.border,
    );
  }

  // ============================================================
  // LINE HAUL
  // ============================================================

  Widget _linehaulCard() {
    final bool hasLinehaulData =
        _requiresLinehaul ||
        _parcel.linehaulStatus.trim().isNotEmpty ||
        _parcel.linehaulRiderName.trim().isNotEmpty ||
        _parcel.requiredVehicleType.trim().isNotEmpty;

    if (!hasLinehaulData) {
      return Container(
        padding: const EdgeInsets.all(18),
        decoration: LogisticsStyle.card,
        child: const Row(
          children: [
            Icon(Icons.local_shipping_outlined, color: LogisticsColors.success),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'No active inter-hub line-haul '
                'transfer is shown for this parcel.',
                style: LogisticsStyle.small,
              ),
            ),
          ],
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.multiple_stop_rounded, color: LogisticsColors.primary),
              SizedBox(width: 9),
              Text('Line-Haul Transfer', style: LogisticsStyle.sectionTitle),
            ],
          ),

          const SizedBox(height: 14),

          _info('Status', _formatStatus(_parcel.linehaulStatus)),

          _info(
            'Assigned Rider',
            _parcel.linehaulRiderName,
            fallback: 'Not assigned',
          ),

          _info(
            'Required Vehicle',
            _formatStatus(_parcel.requiredVehicleType),
            fallback: 'Not specified',
          ),

          if (_parcel.parcelWeightKg > 0)
            _info('Weight', '${_parcel.parcelWeightKg.toStringAsFixed(2)} kg'),

          if (_parcel.estimatedDistanceKm > 0)
            _info(
              'Distance',
              '${_parcel.estimatedDistanceKm.toStringAsFixed(1)} km',
            ),

          const SizedBox(height: 4),

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
                  size: 18,
                  color: LogisticsColors.primary,
                ),
                SizedBox(width: 9),
                Expanded(
                  child: Text(
                    'Line-haul assignment and transfer '
                    'state are controlled by the backend '
                    'routing workflow. No unsupported '
                    'manual transfer action is performed here.',
                    style: LogisticsStyle.small,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // SORTING INFORMATION
  // ============================================================

  Widget _sortingInformationCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Sorting Information', style: LogisticsStyle.sectionTitle),

          const SizedBox(height: 14),

          _info('Sorting Status', _formatStatus(_sortingStatus)),

          _info('Delivery Zone', _details['delivery_zone']),

          _info(
            'Delivery Type',
            _formatStatus(_details['delivery_type']?.toString() ?? ''),
          ),

          _info('Sorting Area', _details['sorting_area']),

          _info('Rack Number', _details['rack_number']),

          _info('Received', _parcel.receivedBySortingCenter ? 'Yes' : 'No'),

          _info(
            'Received At',
            _formatDate(
              _details['received_at']?.toString() ??
                  _parcel.originHubReceivedAt,
            ),
          ),

          _info(
            'Scanned At',
            _formatDate(_details['scanned_at']?.toString() ?? ''),
          ),

          _info(
            'Sorted At',
            _formatDate(_details['sorted_at']?.toString() ?? ''),
          ),

          _info('Staged At', _formatDate(_parcel.stagedAt)),
        ],
      ),
    );
  }

  // ============================================================
  // TIMELINE
  // ============================================================

  Widget _timelineCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Routing Timeline', style: LogisticsStyle.sectionTitle),

          const SizedBox(height: 14),

          _timelineItem(title: 'Seller Pickup', value: _parcel.pickedUpAt),

          _timelineItem(
            title: 'Origin Hub Received',
            value: _parcel.originHubReceivedAt,
          ),

          _timelineItem(
            title: 'Line-Haul Assigned',
            value: _parcel.linehaulAssignedAt,
          ),

          _timelineItem(
            title: 'Transfer Dispatched',
            value: _parcel.transferDispatchedAt,
          ),

          _timelineItem(
            title: 'Line-Haul Started',
            value: _parcel.linehaulStartedAt,
          ),

          _timelineItem(
            title: 'Line-Haul Completed',
            value: _parcel.linehaulCompletedAt,
          ),

          _timelineItem(
            title: 'Destination Hub Received',
            value: _parcel.destinationHubReceivedAt,
            isLast: true,
          ),
        ],
      ),
    );
  }

  Widget _timelineItem({
    required String title,
    required String value,
    bool isLast = false,
  }) {
    final bool completed =
        value.trim().isNotEmpty && value.trim().toLowerCase() != 'null';

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Column(
          children: [
            Container(
              height: 22,
              width: 22,
              decoration: BoxDecoration(
                color: completed
                    ? LogisticsColors.lightGreen
                    : LogisticsColors.background,
                shape: BoxShape.circle,
                border: Border.all(
                  color: completed
                      ? LogisticsColors.success
                      : LogisticsColors.border,
                ),
              ),
              child: Icon(
                completed ? Icons.check_rounded : Icons.circle_outlined,
                size: 13,
                color: completed
                    ? LogisticsColors.success
                    : LogisticsColors.textLight,
              ),
            ),

            if (!isLast)
              Container(height: 30, width: 2, color: LogisticsColors.border),
          ],
        ),

        const SizedBox(width: 10),

        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: LogisticsStyle.cardTitle),
                const SizedBox(height: 2),
                Text(
                  completed ? _formatDate(value) : 'Pending',
                  style: LogisticsStyle.small,
                ),
              ],
            ),
          ),
        ),
      ],
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

          IconButton(
            tooltip: 'Retry',
            onPressed: () {
              _loadDetails();
            },
            icon: const Icon(
              Icons.refresh_rounded,
              color: LogisticsColors.primary,
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
                'No further sorting-center action '
                'is required for this parcel.',
                style: LogisticsStyle.small,
              ),
            ),
          ],
        ),
      );
    }

    if (_isStaged) {
      return _stagedParcelCard();
    }

    String action;
    String label;
    IconData icon;
    String description;

    switch (_sortingStatus) {
      case 'received':
        action = 'scan';
        label = 'Scan & Classify Parcel';
        icon = Icons.qr_code_scanner_rounded;
        description =
            'Parcel has been received. '
            'Classify its delivery routing next.';
        break;

      case 'scanned':
        action = 'sort';
        label = 'Sort Parcel';
        icon = Icons.sort_rounded;
        description =
            'Parcel classification is complete. '
            'Assign its sorting area and rack.';
        break;

      case 'sorted':
        action = 'stage';
        label = 'Stage Parcel';
        icon = Icons.inventory_2_outlined;
        description =
            'Parcel has been sorted and can '
            'now be staged for routing.';
        break;

      default:
        action = 'receive';
        label = 'Receive Parcel';
        icon = Icons.move_to_inbox_rounded;
        description =
            'Confirm physical receipt of the '
            'parcel at this sorting center.';
    }

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Parcel Processing', style: LogisticsStyle.sectionTitle),

          const SizedBox(height: 8),

          Text(description, style: LogisticsStyle.small),

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

  Widget _stagedParcelCard() {
    String title;
    String description;
    IconData icon;

    if (_requiresLinehaul) {
      title = 'Ready for Hub Transfer';

      description = _parcel.isLinehaulAssigned
          ? 'Parcel is staged and already has '
                'a line-haul assignment. Follow the '
                'server-managed hub transfer workflow.'
          : 'Parcel is staged and requires an '
                'inter-hub transfer before final-mile '
                'delivery.';

      icon = Icons.multiple_stop_rounded;
    } else if (_parcel.isAtDestinationHub) {
      title = 'Ready for Final-Mile Assignment';

      description =
          'Parcel has reached its destination hub '
          'and can proceed to final-mile delivery.';
      icon = Icons.delivery_dining_rounded;
    } else {
      title = 'Parcel Staged';

      description =
          'Parcel is fully sorted and staged. '
          'The backend routing workflow will '
          'determine its next assignment.';
      icon = Icons.inventory_2_outlined;
    }

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 44,
            width: 44,
            decoration: BoxDecoration(
              color: LogisticsColors.lightGreen,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: LogisticsColors.success),
          ),

          const SizedBox(width: 11),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: LogisticsStyle.cardTitle),

                const SizedBox(height: 4),

                Text(description, style: LogisticsStyle.small),
              ],
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
    String displayValue;

    if (value is String &&
        (value.trim().isEmpty || value.trim().toLowerCase() == 'null')) {
      displayValue = fallback;
    } else {
      displayValue = _display(value, fallback: fallback);
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 120, child: Text(label, style: LogisticsStyle.small)),

          const SizedBox(width: 8),

          Expanded(child: Text(displayValue, style: LogisticsStyle.cardTitle)),
        ],
      ),
    );
  }
}
