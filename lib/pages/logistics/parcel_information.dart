import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../services/api_services.dart';
import '../../styles/logistics_style.dart';
import '../../utils/address_formatter.dart';
import '../../utils/date_formatter.dart';
import '../../widgets/app_address_card.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_loading_state.dart';
import '../../widgets/status_chip.dart';

class ParcelInformation extends StatefulWidget {
  final ParcelModel parcel;

  const ParcelInformation({super.key, required this.parcel});

  @override
  State<ParcelInformation> createState() => _ParcelInformationState();
}

class _ParcelInformationState extends State<ParcelInformation> {
  static const Duration _requestTimeout = Duration(seconds: 30);

  late ParcelModel _parcel;

  Map<String, dynamic> _details = {};

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isFetching = false;
  bool _isVerified = false;
  bool _hasLoaded = false;

  bool _isProcessing = false;
  bool _isLoadingRiders = false;
  bool _isAssigningRider = false;

  String? _errorMessage;
  int _requestId = 0;

  @override
  void initState() {
    super.initState();
    _parcel = widget.parcel;
    _loadDetails();
  }

  @override
  void dispose() {
    _requestId++;
    super.dispose();
  }

  // ============================================================
  // API / LOAD LATEST SHIPMENT
  // ============================================================

  Future<void> _loadDetails({bool showLoading = true}) async {
    if (!mounted ||
        _isFetching ||
        _isProcessing ||
        _isLoadingRiders ||
        _isAssigningRider) {
      return;
    }

    final int requestId = ++_requestId;

    setState(() {
      _isFetching = true;
      _isLoading = !_hasLoaded && showLoading;
      _isRefreshing = _hasLoaded || !showLoading;
      _isVerified = false;
      _errorMessage = null;
    });

    if (_parcel.id <= 0) {
      _setLoadError(requestId, 'Invalid shipment ID.');
      return;
    }

    try {
      final Map<String, dynamic> response =
          await ApiService.getLogisticsShipment(_parcel.id)
              .timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) return;

      if (response['success'] != true) {
        _setLoadError(
          requestId,
          _responseMessage(response, 'Unable to load parcel information.'),
        );
        return;
      }

      final Map<String, dynamic>? raw = _extractShipment(response);

      if (raw == null) {
        _setLoadError(requestId, 'Invalid shipment response from the server.');
        return;
      }

      final Map<String, dynamic> normalized = _normalizeShipment(raw);

      final ParcelModel latest;

      try {
        latest = ParcelModel.fromJson(normalized);
      } catch (error) {
        debugPrint('Logistics parcel parsing error: $error');

        _setLoadError(requestId, 'Unable to read the latest shipment data.');
        return;
      }

      if (latest.id <= 0 || latest.id != _parcel.id) {
        _setLoadError(requestId, 'The server returned an unexpected shipment.');
        return;
      }

      if (!mounted || requestId != _requestId) return;

      setState(() {
        _parcel = latest;
        _details = normalized;
        _hasLoaded = true;
        _isVerified = true;
        _isLoading = false;
        _isRefreshing = false;
        _isFetching = false;
        _errorMessage = null;
      });
    } on TimeoutException {
      _setLoadError(requestId, 'Request timed out. Refresh the shipment.');
    } catch (error) {
      debugPrint('Logistics parcel API error: $error');

      _setLoadError(requestId, 'Unable to connect to the BiliHub server.');
    }
  }

  void _setLoadError(int requestId, String message) {
    if (!mounted || requestId != _requestId) return;

    setState(() {
      _isFetching = false;
      _isLoading = false;
      _isRefreshing = false;
      _isVerified = false;
      _errorMessage = message;
    });
  }

  Map<String, dynamic>? _extractShipment(Map<String, dynamic> response) {
    final dynamic rawData = response['data'];

    if (rawData is Map) {
      final Map<String, dynamic> data = Map<String, dynamic>.from(rawData);

      if (data['shipment'] is Map) {
        final Map<String, dynamic> shipment = Map<String, dynamic>.from(
          data['shipment'],
        );

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
      return Map<String, dynamic>.from(response['shipment']);
    }

    return null;
  }

  Map<String, dynamic> _asMap(dynamic raw) {
    if (raw is Map) {
      return Map<String, dynamic>.from(raw);
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

    final Map<String, dynamic> seller = _asMap(data['seller']);

    final Map<String, dynamic> rider = _asMap(data['rider']);

    final Map<String, dynamic> linehaulRider = _asMap(data['linehaul_rider']);

    final Map<String, dynamic> originHub = _asMap(data['origin_hub']);

    final Map<String, dynamic> destinationHub = _asMap(data['destination_hub']);

    final Map<String, dynamic> currentHub = _asMap(data['hub']);

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

    data['order_number'] ??= order['order_number'] ?? order['number'];

    data['seller_name'] ??=
        seller['name'] ?? seller['shop_name'] ?? seller['store_name'];

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

    data['delivery_address'] = _resolveDeliveryAddress(data, order);

    data['pickup_address'] = AddressFormatter.clean(
      data['pickup_address'] ?? data['seller_address'],
    );

    data['status'] ??=
        data['shipment_status'] ?? data['delivery_status'] ?? 'pending';

    data['order_date'] ??= data['created_at'] ?? order['created_at'] ?? '';

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

  String _responseMessage(Map<String, dynamic> response, String fallback) {
    final String message = _display(response['message'], fallback: '');

    if (message.isNotEmpty) return message;

    final dynamic errors = response['errors'];

    if (errors is Map) {
      for (final dynamic value in errors.values) {
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }
      }
    }

    return fallback;
  }

  // ============================================================
  // STATUS & WORKFLOW RULES
  // ============================================================

  String _normalizeStatus(String raw) {
    return raw.trim().toLowerCase().replaceAll('-', '_').replaceAll(' ', '_');
  }

  String get _shipmentStatus => _parcel.normalizedStatus;

  String get _sortingStatus {
    final String fromParcel = _parcel.normalizedSortingStatus;

    if (fromParcel.isNotEmpty) {
      return fromParcel;
    }

    final String fromDetails = _normalizeStatus(
      _display(_details['sorting_status'], fallback: ''),
    );

    return fromDetails.isEmpty ? 'pending' : fromDetails;
  }

  bool get _isFinished {
    return const <String>{
      'delivered',
      'completed',
      'cancelled',
      'canceled',
      'delivery_failed',
      'failed',
      'failed_return_at_sorting_center',
    }.contains(_shipmentStatus);
  }

  bool get _isStaged => _sortingStatus == 'staged' || _parcel.isStaged;

  bool get _isBusy =>
      _isFetching || _isProcessing || _isLoadingRiders || _isAssigningRider;

  bool get _hasFinalMileRider {
    final int riderId =
        int.tryParse(_details['rider_id']?.toString() ?? '') ?? 0;

    return riderId > 0 || _parcel.riderName.trim().isNotEmpty;
  }

  bool get _requiresLinehaul {
    return _parcel.requiresHubTransfer ||
        _parcel.isLinehaulAwaitingAssignment ||
        _parcel.isLinehaulAssigned ||
        _parcel.isLinehaulInTransit ||
        _parcel.normalizedRoutingStatus == 'in_hub_transfer';
  }

  // A seller pickup must physically reach the sorting
  // center before Logistics can mark it as received.
  //
  // UI checks are additional safeguards only.
  // Laravel must enforce this rule independently.
  bool get _hasArrivedAtSortingCenter {
    if (_parcel.receivedBySortingCenter) {
      return true;
    }

    return const <String>{
      'at_sorting_center',
      'received_at_sorting_center',
    }.contains(_shipmentStatus);
  }

  bool get _canProcess =>
      _isVerified && !_isBusy && !_isFinished && _parcel.id > 0;

  String? get _nextProcessAction {
    if (_isFinished || _isStaged) return null;

    final String status = _sortingStatus;

    if (status == 'pending' ||
        status == 'assigned' ||
        status == 'at_sorting_center' ||
        status == 'ready_for_pickup') {
      // Do not enable receiving for a parcel that
      // has never arrived at the sorting center.
      return _hasArrivedAtSortingCenter ? 'receive' : null;
    }

    if (status == 'received') return 'scan';
    if (status == 'scanned') return 'sort';
    if (status == 'sorted') return 'stage';

    return null;
  }

  bool get _canAssignFinalMileRider {
    return _canProcess &&
        _isStaged &&
        !_requiresLinehaul &&
        _parcel.isAtDestinationHub &&
        !_hasFinalMileRider;
  }

  // ============================================================
  // USER FEEDBACK
  // ============================================================

  String _display(dynamic value, {String fallback = '—'}) {
    final String text = value?.toString().trim() ?? '';

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return fallback;
    }

    return text;
  }

  String _formatStatus(String value) {
    final String status = value
        .trim()
        .replaceAll('-', '_')
        .replaceAll(' ', '_');

    if (status.isEmpty) return 'Not available';

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

  String _date(dynamic value) {
    return DateFormatter.dateTime(value, fallback: '—');
  }

  void _showMessage(String message, {bool success = false}) {
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

  Future<bool> _confirmAction({
    required String title,
    required String message,
    String confirmLabel = 'Confirm',
  }) async {
    final bool? result = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          title: Text(title),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              style: LogisticsStyle.primaryButton,
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(confirmLabel),
            ),
          ],
        );
      },
    );

    return result == true;
  }

  // ============================================================
  // LOGISTICS PROCESSING
  // RECEIVE -> SCAN -> SORT -> STAGE
  // ============================================================

  Future<void> _process(String action) async {
    if (!_canProcess || _nextProcessAction != action) {
      return;
    }

    String? deliveryZone;
    String? deliveryType;
    String? sortingArea;
    String? rackNumber;

    if (action == 'scan') {
      final Map<String, String>? data = await _showScanDialog();

      if (!mounted || data == null) return;

      deliveryZone = data['delivery_zone'];
      deliveryType = data['delivery_type'];
    }

    if (action == 'sort') {
      final Map<String, String>? data = await _showSortDialog();

      if (!mounted || data == null) return;

      sortingArea = data['sorting_area'];
      rackNumber = data['rack_number'];
    }

    if (!_canProcess || _nextProcessAction != action) {
      return;
    }

    final bool confirmed = await _confirmAction(
      title: _processTitle(action),
      message: _processConfirmation(action),
      confirmLabel: 'Confirm',
    );

    if (!mounted || !confirmed || !_canProcess) {
      return;
    }

    if (_nextProcessAction != action) return;

    setState(() {
      _isProcessing = true;
      _isVerified = false;
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
          ).timeout(_requestTimeout);

      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      final bool success = result['success'] == true;

      _showMessage(
        _responseMessage(
          result,
          success
              ? '${_processTitle(action)} completed.'
              : 'Unable to process the parcel.',
        ),
        success: success,
      );

      await _loadDetails(showLoading: false);
    } on TimeoutException {
      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      _showMessage(
        'Request timed out. Check the latest '
        'parcel status before retrying.',
      );

      await _loadDetails(showLoading: false);
    } catch (error) {
      debugPrint('Logistics process error: $error');

      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      _showMessage(
        'Unable to confirm processing. '
        'Refreshing the server state.',
      );

      await _loadDetails(showLoading: false);
    }
  }

  String _processTitle(String action) {
    switch (action) {
      case 'receive':
        return 'Receive Parcel';
      case 'scan':
        return 'Scan & Classify';
      case 'sort':
        return 'Sort Parcel';
      case 'stage':
        return 'Stage Parcel';
      default:
        return 'Process Parcel';
    }
  }

  String _processConfirmation(String action) {
    switch (action) {
      case 'receive':
        return 'Confirm that this parcel was physically '
            'handed over to the sorting center by the '
            'pickup rider.';
      case 'scan':
        return 'Confirm the selected delivery zone '
            'and delivery type.';
      case 'sort':
        return 'Confirm the sorting area and rack '
            'location for this parcel.';
      case 'stage':
        return 'Confirm that sorting is complete '
            'and this parcel is ready for routing.';
      default:
        return 'Continue processing this parcel?';
    }
  }

  // ============================================================
  // SCAN & CLASSIFY INPUT
  // ============================================================

  Future<Map<String, String>?> _showScanDialog() async {
    final TextEditingController zoneController = TextEditingController(
      text: _display(_details['delivery_zone'], fallback: ''),
    );

    String type = _normalizeStatus(
      _display(_details['delivery_type'], fallback: 'standard'),
    );

    if (!const <String>{'standard', 'same_day', 'express'}.contains(type)) {
      type = 'standard';
    }

    final Map<String, String>? result = await showDialog<Map<String, String>>(
      context: context,
      builder: (BuildContext dialogContext) {
        return StatefulBuilder(
          builder: (BuildContext context, StateSetter updateDialog) {
            return AlertDialog(
              title: const Text('Scan & Classify'),
              scrollable: true,
              content: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: zoneController,
                    maxLength: 120,
                    decoration: LogisticsStyle.inputDecoration(
                      hint: 'Delivery zone',
                      icon: Icons.location_on_outlined,
                    ),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: type,
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
                      if (value == null) return;

                      updateDialog(() {
                        type = value;
                      });
                    },
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(dialogContext),
                  child: const Text('Cancel'),
                ),
                ElevatedButton(
                  onPressed: () {
                    final String zone = zoneController.text.trim();

                    if (zone.isEmpty) {
                      ScaffoldMessenger.of(dialogContext).showSnackBar(
                        const SnackBar(
                          content: Text('Please enter a delivery zone.'),
                        ),
                      );
                      return;
                    }

                    Navigator.pop(dialogContext, <String, String>{
                      'delivery_zone': zone,
                      'delivery_type': type,
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
  // SORT INPUT
  // ============================================================

  Future<Map<String, String>?> _showSortDialog() async {
    final TextEditingController areaController = TextEditingController(
      text: _display(_details['sorting_area'], fallback: ''),
    );

    final TextEditingController rackController = TextEditingController(
      text: _display(_details['rack_number'], fallback: ''),
    );

    final Map<String, String>? result = await showDialog<Map<String, String>>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          title: const Text('Sort Parcel'),
          scrollable: true,
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: areaController,
                maxLength: 120,
                decoration: LogisticsStyle.inputDecoration(
                  hint: 'Sorting area',
                  icon: Icons.warehouse_outlined,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: rackController,
                maxLength: 120,
                decoration: LogisticsStyle.inputDecoration(
                  hint: 'Rack number',
                  icon: Icons.inventory_2_outlined,
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () {
                final String area = areaController.text.trim();

                final String rack = rackController.text.trim();

                if (area.isEmpty || rack.isEmpty) {
                  ScaffoldMessenger.of(dialogContext).showSnackBar(
                    const SnackBar(
                      content: Text(
                        'Enter the sorting area '
                        'and rack number.',
                      ),
                    ),
                  );
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
  // FINAL-MILE RIDER LOOKUP
  // ============================================================

  Future<void> _showAvailableRiders() async {
    if (!_canAssignFinalMileRider) return;

    setState(() {
      _isLoadingRiders = true;
    });

    try {
      final Map<String, dynamic> response =
          await ApiService.getLogisticsAvailableRiders(shipmentId: _parcel.id)
              .timeout(_requestTimeout);

      if (!mounted) return;

      setState(() {
        _isLoadingRiders = false;
      });

      if (response['success'] != true) {
        _showMessage(
          _responseMessage(response, 'Unable to load eligible riders.'),
        );
        return;
      }

      final List<Map<String, dynamic>> riders = _extractAvailableRiders(
        response,
      );

      if (riders.isEmpty) {
        _showMessage('No eligible final-mile riders are available.');
        return;
      }

      final Map<String, dynamic>? selected = await _showRiderPicker(riders);

      if (!mounted || selected == null) return;

      if (!_canAssignFinalMileRider) return;

      final bool confirmed = await _confirmAction(
        title: 'Assign Final-Mile Rider',
        message:
            'Assign ${_riderName(selected)} to parcel '
            '${_display(_parcel.trackingNumber)} '
            'for final-mile delivery?',
        confirmLabel: 'Assign Rider',
      );

      if (!mounted || !confirmed) return;

      if (!_canAssignFinalMileRider) return;

      await _assignFinalMileRider(selected);
    } on TimeoutException {
      if (!mounted) return;

      setState(() {
        _isLoadingRiders = false;
      });

      _showMessage('Rider lookup timed out. Please retry.');
    } catch (error) {
      debugPrint('Available riders error: $error');

      if (!mounted) return;

      setState(() {
        _isLoadingRiders = false;
      });

      _showMessage('Unable to retrieve eligible riders.');
    }
  }

  List<Map<String, dynamic>> _extractAvailableRiders(
    Map<String, dynamic> response,
  ) {
    dynamic raw = response['riders'] ?? response['data'];

    if (raw is Map) {
      raw =
          raw['riders'] ??
          raw['available_riders'] ??
          raw['eligible_riders'] ??
          raw['data'];
    }

    if (raw is! List) return [];

    return raw
        .whereType<Map>()
        .map((Map rider) => Map<String, dynamic>.from(rider))
        .where((Map<String, dynamic> rider) => _riderId(rider) > 0)
        .toList();
  }

  Future<Map<String, dynamic>?> _showRiderPicker(
    List<Map<String, dynamic>> riders,
  ) {
    int? selectedId;

    return showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (BuildContext sheetContext) {
        return StatefulBuilder(
          builder: (BuildContext context, StateSetter updateSheet) {
            return SafeArea(
              top: false,
              child: Container(
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.sizeOf(context).height * 0.80,
                ),
                decoration: const BoxDecoration(
                  color: LogisticsColors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const SizedBox(height: 16),
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 18),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          'Available Final-Mile Riders',
                          style: LogisticsStyle.sectionTitle,
                        ),
                      ),
                    ),
                    const SizedBox(height: 7),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 18),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          '${riders.length} eligible riders',
                          style: LogisticsStyle.small,
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    const Divider(height: 1),
                    Flexible(
                      child: ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: riders.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 9),
                        itemBuilder: (BuildContext context, int index) {
                          final Map<String, dynamic> rider = riders[index];

                          final int riderId = _riderId(rider);

                          final bool selected = selectedId == riderId;

                          return Material(
                            color: selected
                                ? LogisticsColors.lightPurple
                                : LogisticsColors.background,
                            borderRadius: BorderRadius.circular(13),
                            child: InkWell(
                              borderRadius: BorderRadius.circular(13),
                              onTap: () {
                                updateSheet(() {
                                  selectedId = riderId;
                                });
                              },
                              child: Container(
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(13),
                                  border: Border.all(
                                    color: selected
                                        ? LogisticsColors.primary
                                        : LogisticsColors.border,
                                  ),
                                ),
                                child: Row(
                                  children: [
                                    Icon(
                                      selected
                                          ? Icons.check_circle
                                          : Icons.person_outline,
                                      color: LogisticsColors.primary,
                                    ),
                                    const SizedBox(width: 11),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            _riderName(rider),
                                            style: LogisticsStyle.cardTitle,
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            _riderSummary(rider),
                                            style: LogisticsStyle.small,
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 18),
                      child: Row(
                        children: [
                          Expanded(
                            child: OutlinedButton(
                              onPressed: () => Navigator.pop(sheetContext),
                              child: const Text('Cancel'),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            flex: 2,
                            child: ElevatedButton(
                              style: LogisticsStyle.primaryButton,
                              onPressed: selectedId == null
                                  ? null
                                  : () {
                                      final selected = riders.firstWhere(
                                        (rider) =>
                                            _riderId(rider) == selectedId,
                                      );

                                      Navigator.pop(sheetContext, selected);
                                    },
                              child: const Text('Select Rider'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  int _riderId(Map<String, dynamic> rider) {
    return int.tryParse(
          (rider['id'] ?? rider['rider_id'] ?? rider['user_id'])?.toString() ??
              '',
        ) ??
        0;
  }

  String _riderName(Map<String, dynamic> rider) {
    final Map<String, dynamic> user = _asMap(rider['user']);

    final String direct = _display(
      rider['name'] ??
          rider['full_name'] ??
          rider['rider_name'] ??
          user['name'],
      fallback: '',
    );

    if (direct.isNotEmpty) return direct;

    final String first = _display(
      rider['first_name'] ?? user['first_name'],
      fallback: '',
    );

    final String last = _display(
      rider['last_name'] ?? user['last_name'],
      fallback: '',
    );

    final String fullName = '$first $last'.trim();

    return fullName.isNotEmpty ? fullName : 'Rider #${_riderId(rider)}';
  }

  String _riderSummary(Map<String, dynamic> rider) {
    final String availability = _formatStatus(
      _display(
        rider['availability_status'] ?? rider['status'],
        fallback: 'Unknown',
      ),
    );

    final String load = _display(rider['current_load'], fallback: '');

    final String capacity = _display(rider['max_capacity'], fallback: '');

    if (load.isNotEmpty && capacity.isNotEmpty) {
      return '$availability • Load $load / $capacity';
    }

    return availability;
  }

  Future<void> _assignFinalMileRider(Map<String, dynamic> rider) async {
    if (!_canAssignFinalMileRider) return;

    final int riderId = _riderId(rider);

    if (riderId <= 0) return;

    setState(() {
      _isAssigningRider = true;
      _isVerified = false;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.assignLogisticsFinalMileRider(
            shipmentId: _parcel.id,
            riderId: riderId,
          ).timeout(_requestTimeout);

      if (!mounted) return;

      setState(() {
        _isAssigningRider = false;
      });

      final bool success = result['success'] == true;

      _showMessage(
        _responseMessage(
          result,
          success
              ? '${_riderName(rider)} assigned successfully.'
              : 'Unable to assign rider.',
        ),
        success: success,
      );

      await _loadDetails(showLoading: false);
    } on TimeoutException {
      if (!mounted) return;

      setState(() {
        _isAssigningRider = false;
      });

      _showMessage(
        'Assignment request timed out. '
        'Refresh before attempting again.',
      );

      await _loadDetails(showLoading: false);
    } catch (error) {
      debugPrint('Final-mile assignment error: $error');

      if (!mounted) return;

      setState(() {
        _isAssigningRider = false;
      });

      _showMessage('Unable to confirm rider assignment.');

      await _loadDetails(showLoading: false);
    }
  }

  // ============================================================
  // MAIN UI
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.sizeOf(context).width;

    final double padding = width < 380 ? 14 : 18;

    return Scaffold(
      backgroundColor: LogisticsColors.background,
      appBar: AppBar(
        backgroundColor: LogisticsColors.white,
        surfaceTintColor: Colors.transparent,
        title: const Text('Parcel Information', style: LogisticsStyle.title),
        actions: [
          IconButton(
            tooltip: 'Refresh parcel',
            onPressed: _isBusy ? null : () => _loadDetails(showLoading: false),
            icon: const Icon(
              Icons.refresh_rounded,
              color: LogisticsColors.primary,
            ),
          ),
        ],
      ),
      body: RefreshIndicator(
        color: LogisticsColors.primary,
        onRefresh: () => _loadDetails(showLoading: false),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          padding: EdgeInsets.fromLTRB(padding, 16, padding, 30),
          children: [
            if (_isLoading && !_hasLoaded)
              const AppLoadingState(
                message: 'Loading parcel information...',
                color: LogisticsColors.primary,
              )
            else if (!_hasLoaded)
              AppErrorState(
                title: 'Unable to Load Parcel',
                message: _errorMessage ?? 'Please refresh to try again.',
                color: LogisticsColors.primary,
                onRetry: _loadDetails,
              )
            else ...[
              if (_isRefreshing) ...[
                const LinearProgressIndicator(color: LogisticsColors.primary),
                const SizedBox(height: 14),
              ],

              if (_errorMessage != null) _notice(_errorMessage!),

              if (!_isVerified) ...[
                _notice(
                  'Actions are disabled until the '
                  'latest data is verified with Laravel.',
                ),
                const SizedBox(height: 14),
              ],

              _statusCard(),
              const SizedBox(height: 16),

              _parcelDetailsCard(),
              const SizedBox(height: 16),

              _hubCard(),
              const SizedBox(height: 16),

              _sortingCard(),
              const SizedBox(height: 16),

              _linehaulCard(),
              const SizedBox(height: 16),

              _timelineCard(),
              const SizedBox(height: 16),

              _processingCard(),
            ],
          ],
        ),
      ),
    );
  }

  // ============================================================
  // STATUS
  // ============================================================

  Widget _statusCard() {
    return _card(
      title: 'Current Status',
      icon: Icons.inventory_2_outlined,
      children: [
        StatusChip(status: _parcel.status),
        const SizedBox(height: 12),
        _info(
          'Sorting Status',
          _formatStatus(_sortingStatus),
          Icons.sort_rounded,
        ),
        _info(
          'Routing Status',
          _formatStatus(_parcel.routingStatus),
          Icons.route_outlined,
        ),
        if (_parcel.linehaulStatus.trim().isNotEmpty)
          _info(
            'Line-Haul Status',
            _formatStatus(_parcel.linehaulStatus),
            Icons.multiple_stop_rounded,
          ),
      ],
    );
  }

  // ============================================================
  // PARCEL DETAILS
  // ============================================================

  Widget _parcelDetailsCard() {
    return _card(
      title: 'Parcel Details',
      icon: Icons.receipt_long_outlined,
      children: [
        _info(
          'Tracking Number',
          _parcel.trackingNumber,
          Icons.qr_code_2_rounded,
        ),
        _info('Order Number', _details['order_number'], Icons.receipt_outlined),
        _info('Seller', _details['seller_name'], Icons.storefront_outlined),
        if (_parcel.pickupAddress.trim().isNotEmpty)
          _info(
            'Seller Pickup Address',
            AddressFormatter.display(
              _parcel.pickupAddress,
              fallback: 'Address unavailable',
            ),
            Icons.store_outlined,
          ),
        AppAddressCard(
          title: 'Delivery Address',
          recipientName: _parcel.customerName,
          phoneNumber: _parcel.customerPhone,
          address: _parcel.deliveryAddress,
          accentColor: LogisticsColors.primary,
          compact: true,
        ),
        const SizedBox(height: 10),
        _info(
          'Final-Mile Rider',
          _parcel.riderName,
          Icons.delivery_dining_outlined,
          fallback: 'Not assigned',
        ),
        _info(
          'Order Date',
          _date(_parcel.orderDate),
          Icons.calendar_today_outlined,
        ),
        if (_parcel.parcelWeightKg > 0)
          _info(
            'Parcel Weight',
            '${_parcel.parcelWeightKg.toStringAsFixed(2)} kg',
            Icons.scale_outlined,
          ),
        if (_parcel.estimatedDistanceKm > 0)
          _info(
            'Estimated Distance',
            '${_parcel.estimatedDistanceKm.toStringAsFixed(1)} km',
            Icons.route_outlined,
          ),
      ],
    );
  }

  // ============================================================
  // HUB ROUTING
  // ============================================================

  Widget _hubCard() {
    return _card(
      title: 'Hub Routing',
      icon: Icons.hub_outlined,
      children: [
        _info(
          'Origin Hub',
          _parcel.originHubName,
          Icons.trip_origin_rounded,
          fallback: 'Not assigned',
        ),
        _info('Current Hub', _parcel.currentHubName, Icons.warehouse_outlined),
        _info(
          'Destination Hub',
          _parcel.destinationHubName,
          Icons.location_city_outlined,
          fallback: 'Not assigned',
        ),
        _info(
          'Routing Status',
          _formatStatus(_parcel.routingStatus),
          Icons.route_outlined,
        ),
      ],
    );
  }

  // ============================================================
  // SORTING INFORMATION
  // ============================================================

  Widget _sortingCard() {
    return _card(
      title: 'Sorting Information',
      icon: Icons.warehouse_outlined,
      children: [
        _info(
          'Sorting Status',
          _formatStatus(_sortingStatus),
          Icons.sort_rounded,
        ),
        _info(
          'Delivery Zone',
          _details['delivery_zone'],
          Icons.location_on_outlined,
        ),
        _info(
          'Delivery Type',
          _details['delivery_type'] == null
              ? null
              : _formatStatus(_details['delivery_type'].toString()),
          Icons.local_shipping_outlined,
        ),
        _info(
          'Sorting Area',
          _details['sorting_area'],
          Icons.warehouse_outlined,
        ),
        _info(
          'Rack Number',
          _details['rack_number'],
          Icons.inventory_2_outlined,
        ),
        _info(
          'Received by Logistics',
          _parcel.receivedBySortingCenter ? 'Yes' : 'No',
          Icons.move_to_inbox_rounded,
        ),
        _info(
          'Received At',
          _date(_details['received_at'] ?? _parcel.originHubReceivedAt),
          Icons.schedule_outlined,
        ),
        _info(
          'Scanned At',
          _date(_details['scanned_at']),
          Icons.qr_code_scanner_rounded,
        ),
        _info('Sorted At', _date(_details['sorted_at']), Icons.sort_rounded),
        _info('Staged At', _date(_parcel.stagedAt), Icons.inventory_2_outlined),
      ],
    );
  }

  // ============================================================
  // LINE-HAUL INFORMATION
  // ============================================================

  Widget _linehaulCard() {
    final bool hasData =
        _requiresLinehaul ||
        _parcel.linehaulStatus.trim().isNotEmpty ||
        _parcel.linehaulRiderName.trim().isNotEmpty;

    return _card(
      title: 'Line-Haul Transfer',
      icon: Icons.multiple_stop_rounded,
      children: [
        if (!hasData)
          const Text(
            'No inter-hub transfer is recorded.',
            style: LogisticsStyle.small,
          )
        else ...[
          _info(
            'Line-Haul Status',
            _formatStatus(_parcel.linehaulStatus),
            Icons.compare_arrows_rounded,
          ),
          _info(
            'Assigned Line-Haul Rider',
            _parcel.linehaulRiderName,
            Icons.person_outline_rounded,
            fallback: 'Not assigned',
          ),
          _info(
            'Required Vehicle',
            _parcel.requiredVehicleType,
            Icons.local_shipping_outlined,
          ),
          _notice(
            'Inter-hub dispatch and transfers follow '
            'the Laravel routing workflow.',
          ),
        ],
      ],
    );
  }

  // ============================================================
  // ROUTING TIMELINE
  // ============================================================

  Widget _timelineCard() {
    return _card(
      title: 'Routing Timeline',
      icon: Icons.timeline_outlined,
      children: [
        _timelineItem('Seller Picked Up', _parcel.pickedUpAt),
        _timelineItem('Origin Hub Received', _parcel.originHubReceivedAt),
        _timelineItem('Line-Haul Assigned', _parcel.linehaulAssignedAt),
        _timelineItem('Transfer Dispatched', _parcel.transferDispatchedAt),
        _timelineItem('Line-Haul Started', _parcel.linehaulStartedAt),
        _timelineItem('Line-Haul Completed', _parcel.linehaulCompletedAt),
        _timelineItem(
          'Destination Hub Received',
          _parcel.destinationHubReceivedAt,
          last: true,
        ),
      ],
    );
  }

  Widget _timelineItem(String title, String rawDate, {bool last = false}) {
    final bool completed =
        rawDate.trim().isNotEmpty && rawDate.trim().toLowerCase() != 'null';

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Column(
          children: [
            Icon(
              completed
                  ? Icons.check_circle_rounded
                  : Icons.radio_button_unchecked_rounded,
              color: completed
                  ? LogisticsColors.success
                  : LogisticsColors.textLight,
              size: 22,
            ),
            if (!last)
              Container(height: 25, width: 2, color: LogisticsColors.border),
          ],
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(bottom: 13),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: LogisticsStyle.cardTitle),
                const SizedBox(height: 3),
                Text(
                  completed ? _date(rawDate) : 'Pending',
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
  // PROCESSING / ASSIGNMENT ACTION
  // ============================================================

  Widget _processingCard() {
    if (_isFinished) {
      return _card(
        title: 'Parcel Processing',
        icon: Icons.check_circle_outline_rounded,
        children: [
          _notice(
            'This parcel is finalized. '
            'No additional sorting action is available.',
          ),
        ],
      );
    }

    if (_isStaged) {
      return _stagedCard();
    }

    final String? action = _nextProcessAction;

    if (action == null) {
      final bool waitingForArrival =
          !_hasArrivedAtSortingCenter &&
          const <String>{
            'pending',
            'assigned',
            'ready_for_pickup',
            'at_sorting_center',
          }.contains(_sortingStatus);

      return _card(
        title: 'Parcel Processing',
        icon: Icons.info_outline_rounded,
        children: [
          _notice(
            waitingForArrival
                ? 'Waiting for the pickup rider to '
                      'deliver this parcel to the sorting '
                      'center. Logistics cannot receive '
                      'it before the handoff.'
                : 'No processing action is available '
                      'for the current status. Refresh '
                      'to check the latest information.',
          ),
        ],
      );
    }

    String description;
    IconData icon;

    switch (action) {
      case 'receive':
        description =
            'Confirm physical handoff from the '
            'seller pickup rider.';
        icon = Icons.move_to_inbox_rounded;
        break;
      case 'scan':
        description = 'Enter the parcel delivery zone and type.';
        icon = Icons.qr_code_scanner_rounded;
        break;
      case 'sort':
        description = 'Record the sorting area and rack.';
        icon = Icons.sort_rounded;
        break;
      case 'stage':
        description = 'Confirm that the parcel is fully sorted.';
        icon = Icons.inventory_2_outlined;
        break;
      default:
        description = 'Process parcel.';
        icon = Icons.inventory_2_outlined;
    }

    return _card(
      title: 'Parcel Processing',
      icon: Icons.inventory_2_outlined,
      children: [
        Text(description, style: LogisticsStyle.small),
        const SizedBox(height: 14),
        SizedBox(
          width: double.infinity,
          height: 50,
          child: ElevatedButton.icon(
            onPressed: _canProcess && _nextProcessAction == action
                ? () => _process(action)
                : null,
            style: LogisticsStyle.primaryButton,
            icon: Icon(icon),
            label: Text(_processTitle(action)),
          ),
        ),
      ],
    );
  }

  Widget _stagedCard() {
    String title;
    String message;
    IconData icon;

    if (_requiresLinehaul) {
      title = 'Ready for Hub Transfer';
      message =
          'This parcel requires inter-hub transfer '
          'before final-mile delivery.';
      icon = Icons.multiple_stop_rounded;
    } else if (_hasFinalMileRider) {
      title = 'Final-Mile Rider Assigned';
      message =
          '${_display(_parcel.riderName, fallback: 'A rider')} '
          'has been assigned for final delivery.';
      icon = Icons.check_circle_outline_rounded;
    } else if (_parcel.isAtDestinationHub) {
      title = 'Ready for Final-Mile Assignment';
      message =
          'Select an eligible rider to deliver '
          'the parcel to the buyer.';
      icon = Icons.delivery_dining_rounded;
    } else {
      title = 'Parcel Staged';
      message =
          'Waiting for the next routing step '
          'from the Laravel backend.';
      icon = Icons.inventory_2_outlined;
    }

    return _card(
      title: 'Parcel Processing',
      icon: Icons.warehouse_outlined,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: LogisticsColors.success, size: 28),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: LogisticsStyle.cardTitle),
                  const SizedBox(height: 5),
                  Text(message, style: LogisticsStyle.small),
                ],
              ),
            ),
          ],
        ),
        if (_hasFinalMileRider) ...[
          const SizedBox(height: 12),
          _info(
            'Assigned Rider',
            _parcel.riderName,
            Icons.person_outline_rounded,
          ),
        ],
        if (!_requiresLinehaul &&
            !_hasFinalMileRider &&
            _parcel.isAtDestinationHub) ...[
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton.icon(
              onPressed: _canAssignFinalMileRider ? _showAvailableRiders : null,
              style: LogisticsStyle.primaryButton,
              icon: const Icon(Icons.person_search_rounded),
              label: const Text('Assign Final-Mile Rider'),
            ),
          ),
        ],
      ],
    );
  }

  // ============================================================
  // REUSABLE UI
  // ============================================================

  Widget _card({
    required String title,
    required IconData icon,
    required List<Widget> children,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(17),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: LogisticsColors.primary, size: 22),
              const SizedBox(width: 9),
              Expanded(child: Text(title, style: LogisticsStyle.sectionTitle)),
            ],
          ),
          const SizedBox(height: 15),
          ...children,
        ],
      ),
    );
  }

  Widget _info(
    String label,
    dynamic value,
    IconData icon, {
    String fallback = '—',
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 11),
      child: AppInfoRow(
        label: label,
        value: _display(value, fallback: fallback),
        icon: icon,
        iconColor: LogisticsColors.primary,
        compact: true,
      ),
    );
  }

  Widget _notice(String message) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: LogisticsColors.background,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: LogisticsColors.border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.info_outline_rounded,
            color: LogisticsColors.primary,
            size: 19,
          ),
          const SizedBox(width: 9),
          Expanded(child: Text(message, style: LogisticsStyle.small)),
        ],
      ),
    );
  }
}
