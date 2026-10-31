import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../sevices/api_services.dart';
import '../../sevices/auth_storage.dart';
import '../../styles/logistics_style.dart';

class LogisticsHome extends StatefulWidget {
  final VoidCallback? onOpenScanner;

  const LogisticsHome({super.key, this.onOpenScanner});

  @override
  State<LogisticsHome> createState() => _LogisticsHomeState();
}

class _LogisticsHomeState extends State<LogisticsHome> {
  bool _isLoading = true;
  bool _isRefreshing = false;

  String? _errorMessage;

  String _savedUserName = 'Logistics Staff';
  String _companyName = 'Logistics';

  // ============================================================
  // OFFICIAL DASHBOARD COUNTS
  // ============================================================

  int _totalParcels = 0;
  int _pendingParcels = 0;
  int _preparingParcels = 0;
  int _inTransitParcels = 0;
  int _outForDeliveryParcels = 0;
  int _deliveredParcels = 0;
  int _cancelledParcels = 0;

  int _receivedParcels = 0;
  int _scannedParcels = 0;
  int _sortedParcels = 0;
  int _stagedParcels = 0;

  int _totalRiders = 0;
  int _activeRiders = 0;
  int _availableRiders = 0;
  int _onDeliveryRiders = 0;

  int _totalHubs = 0;
  int _activeHubs = 0;

  int _alertsCount = 0;

  // ============================================================
  // CURRENT SHIPMENT RECORDS
  // ============================================================

  final List<ParcelModel> _currentShipments = <ParcelModel>[];

  @override
  void initState() {
    super.initState();

    _loadDashboard();
  }

  // ============================================================
  // LOAD DASHBOARD
  // ============================================================

  Future<void> _loadDashboard({bool refresh = false}) async {
    if (!mounted) {
      return;
    }

    setState(() {
      if (refresh) {
        _isRefreshing = true;
      } else {
        _isLoading = true;
      }

      _errorMessage = null;
    });

    try {
      final Map<String, dynamic>? savedUser = await AuthStorage.getUser();

      final List<Map<String, dynamic>> results = await Future.wait(
        <Future<Map<String, dynamic>>>[
          ApiService.getLogisticsDashboard(),
          ApiService.getLogisticsShipments(perPage: 100),
        ],
      );

      if (!mounted) {
        return;
      }

      final Map<String, dynamic> dashboardResult = results[0];

      final Map<String, dynamic> shipmentsResult = results[1];

      final String savedName = savedUser?['name']?.toString().trim() ?? '';

      if (savedName.isNotEmpty) {
        _savedUserName = savedName;
      }

      if (dashboardResult['success'] != true) {
        final String message =
            dashboardResult['message']?.toString().trim() ?? '';

        setState(() {
          _isLoading = false;
          _isRefreshing = false;

          _errorMessage = message.isNotEmpty
              ? message
              : 'Unable to load logistics dashboard.';
        });

        return;
      }

      final Map<String, dynamic> data = _extractDashboardData(dashboardResult);

      final Map<String, dynamic> logistic = _asMap(data['logistic']);

      final Map<String, dynamic> shipments = _asMap(data['shipments']);

      final Map<String, dynamic> riders = _asMap(data['riders']);

      final Map<String, dynamic> hubs = _asMap(data['hubs']);

      final List<ParcelModel> currentShipments =
          shipmentsResult['success'] == true
          ? _extractParcels(shipmentsResult)
          : <ParcelModel>[];

      currentShipments.sort((ParcelModel a, ParcelModel b) {
        final DateTime? aDate = _activityDate(a);

        final DateTime? bDate = _activityDate(b);

        if (aDate != null && bDate != null) {
          return bDate.compareTo(aDate);
        }

        if (aDate != null) {
          return -1;
        }

        if (bDate != null) {
          return 1;
        }

        return b.id.compareTo(a.id);
      });

      setState(() {
        final String company =
            logistic['company_name']?.toString().trim() ?? '';

        _companyName = company.isNotEmpty ? company : 'Logistics';

        _totalParcels = _toInt(shipments['total']);

        _pendingParcels = _toInt(shipments['pending']);

        _preparingParcels = _toInt(shipments['preparing']);

        _inTransitParcels = _toInt(shipments['in_transit']);

        _outForDeliveryParcels = _toInt(shipments['out_for_delivery']);

        _deliveredParcels = _toInt(shipments['delivered']);

        _cancelledParcels = _toInt(shipments['cancelled']);

        _receivedParcels = _toInt(shipments['received']);

        _scannedParcels = _toInt(shipments['scanned']);

        _sortedParcels = _toInt(shipments['sorted']);

        _stagedParcels = _toInt(shipments['staged']);

        _totalRiders = _toInt(riders['total']);

        _activeRiders = _toInt(riders['active']);

        _availableRiders = _toInt(riders['available']);

        _onDeliveryRiders = _toInt(riders['on_delivery']);

        _totalHubs = _toInt(hubs['total']);

        _activeHubs = _toInt(hubs['active']);

        _alertsCount = _toInt(data['alerts_count']);

        _currentShipments
          ..clear()
          ..addAll(currentShipments);

        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = null;
      });
    } catch (error) {
      debugPrint('Logistics dashboard error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _isLoading = false;
        _isRefreshing = false;

        _errorMessage =
            'Unable to load logistics dashboard. '
            'Please try again.';
      });
    }
  }

  // ============================================================
  // DASHBOARD PARSING
  // ============================================================

  Map<String, dynamic> _extractDashboardData(Map<String, dynamic> result) {
    final dynamic rawData = result['data'];

    if (rawData is Map) {
      return Map<String, dynamic>.from(rawData);
    }

    return Map<String, dynamic>.from(result);
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }

    return <String, dynamic>{};
  }

  int _toInt(dynamic value) {
    if (value is int) {
      return value;
    }

    if (value is num) {
      return value.toInt();
    }

    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  // ============================================================
  // PARCEL EXTRACTION
  // ============================================================

  List<ParcelModel> _extractParcels(Map<String, dynamic> result) {
    final List<Map<String, dynamic>> maps = _extractShipmentMaps(result);

    final List<ParcelModel> parcels = <ParcelModel>[];

    for (final Map<String, dynamic> item in maps) {
      try {
        parcels.add(ParcelModel.fromJson(_normalizeShipment(item)));
      } catch (error) {
        debugPrint(
          'Unable to parse logistics dashboard parcel: '
          '$error',
        );
      }
    }

    return parcels;
  }

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

  List<Map<String, dynamic>> _toMapList(dynamic value) {
    if (value is! List) {
      return <Map<String, dynamic>>[];
    }

    final List<Map<String, dynamic>> list = <Map<String, dynamic>>[];

    for (final dynamic item in value) {
      if (item is Map<String, dynamic>) {
        list.add(Map<String, dynamic>.from(item));
      } else if (item is Map) {
        list.add(Map<String, dynamic>.from(item));
      }
    }

    return list;
  }

  // ============================================================
  // NORMALIZE SHIPMENT
  // ============================================================

  Map<String, dynamic> _normalizeShipment(Map<String, dynamic> raw) {
    final Map<String, dynamic> data = Map<String, dynamic>.from(raw);

    final dynamic shipment = data['shipment'];

    if (shipment is Map) {
      data.addAll(Map<String, dynamic>.from(shipment));
    }

    final dynamic order = data['order'];

    if (order is Map) {
      data['customer_name'] ??=
          order['customer_name'] ?? order['buyer_name'] ?? order['name'];

      data['customer_phone'] ??= order['customer_phone'] ?? order['phone'];

      data['delivery_address'] ??=
          order['delivery_address'] ?? order['address'];

      data['order_date'] ??= order['order_date'] ?? order['created_at'];
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

    data['tracking_number'] ??= data['tracking_code'] ?? data['tracking'] ?? '';

    data['customer_name'] ??= '';

    data['customer_phone'] ??= '';

    data['delivery_address'] ??= '';

    data['pickup_address'] ??= data['seller_address'] ?? '';

    data['rider_name'] ??= '';

    data['status'] ??=
        data['shipment_status'] ?? data['delivery_status'] ?? 'pending';

    data['order_date'] ??= data['created_at'] ?? '';

    data['delivery_earnings'] ??= data['earnings'] ?? 0;

    return data;
  }

  // ============================================================
  // ACTIVITY DATE
  // ============================================================

  DateTime? _activityDate(ParcelModel parcel) {
    final List<String> candidates = <String>[
      parcel.updatedAt,
      parcel.destinationHubReceivedAt,
      parcel.linehaulCompletedAt,
      parcel.linehaulStartedAt,
      parcel.transferDispatchedAt,
      parcel.linehaulAssignedAt,
      parcel.originHubReceivedAt,
      parcel.pickedUpAt,
      parcel.createdAt,
      parcel.orderDate,
    ];

    for (final String value in candidates) {
      final String text = value.trim();

      if (text.isEmpty || text.toLowerCase() == 'null') {
        continue;
      }

      final DateTime? parsed = DateTime.tryParse(text);

      if (parsed != null) {
        return parsed;
      }
    }

    return null;
  }

  // ============================================================
  // USER NAME
  // ============================================================

  String get _firstName {
    final String cleaned = _savedUserName.trim();

    if (cleaned.isEmpty) {
      return 'Logistics Staff';
    }

    return cleaned.split(RegExp(r'\s+')).first;
  }

  // ============================================================
  // ROUTING COUNTS
  // ============================================================

  int get _requiresTransferCount {
    return _currentShipments.where((ParcelModel parcel) {
      return parcel.requiresHubTransfer;
    }).length;
  }

  int get _awaitingLinehaulCount {
    return _currentShipments.where((ParcelModel parcel) {
      return parcel.isLinehaulAwaitingAssignment;
    }).length;
  }

  int get _linehaulAssignedCount {
    return _currentShipments.where((ParcelModel parcel) {
      return parcel.isLinehaulAssigned;
    }).length;
  }

  int get _linehaulInTransitCount {
    return _currentShipments.where((ParcelModel parcel) {
      return parcel.isLinehaulInTransit;
    }).length;
  }

  int get _destinationHubCount {
    return _currentShipments.where((ParcelModel parcel) {
      return parcel.isAtDestinationHub;
    }).length;
  }

  // ============================================================
  // FALLBACK TOTALS
  // ============================================================

  int get _displayTotalParcels {
    if (_totalParcels > 0 || _currentShipments.isEmpty) {
      return _totalParcels;
    }

    return _currentShipments.length;
  }

  int get _displayInTransitParcels {
    if (_inTransitParcels > 0 || _currentShipments.isEmpty) {
      return _inTransitParcels;
    }

    return _currentShipments.where((ParcelModel parcel) {
      return parcel.isInTransit || parcel.isLinehaulInTransit;
    }).length;
  }

  int get _displayDeliveredParcels {
    if (_deliveredParcels > 0 || _currentShipments.isEmpty) {
      return _deliveredParcels;
    }

    return _currentShipments.where((ParcelModel parcel) {
      return parcel.isDelivered;
    }).length;
  }

  // ============================================================
  // OPEN SCANNER
  // ============================================================

  void _openScanner() {
    final VoidCallback? callback = widget.onOpenScanner;

    if (callback != null) {
      callback();

      return;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(
          behavior: SnackBarBehavior.floating,
          content: Text('Unable to open parcel scanner.'),
        ),
      );
  }

  // ============================================================
  // FORMAT HELPERS
  // ============================================================

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

    return '';
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.of(context).size.width;

    final double horizontalPadding = width < 380 ? 14 : 18;

    return Scaffold(
      backgroundColor: LogisticsColors.background,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () {
            return _loadDashboard(refresh: true);
          },
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: EdgeInsets.fromLTRB(
              horizontalPadding,
              18,
              horizontalPadding,
              30,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _header(),

                if (_isRefreshing) ...[
                  const SizedBox(height: 14),
                  const LinearProgressIndicator(color: LogisticsColors.primary),
                ],

                if (_isLoading) ...[const SizedBox(height: 18), _loadingCard()],

                if (!_isLoading && _errorMessage != null) ...[
                  const SizedBox(height: 18),
                  _errorCard(),
                ],

                const SizedBox(height: 28),

                const Text(
                  'Parcel Overview',
                  style: LogisticsStyle.sectionTitle,
                ),

                const SizedBox(height: 14),

                _parcelOverview(),

                const SizedBox(height: 28),

                _sortingCenterCard(),

                const SizedBox(height: 18),

                _hubTransferCard(),

                const SizedBox(height: 18),

                _operationsCard(),

                const SizedBox(height: 28),

                _scannerCard(),

                const SizedBox(height: 28),

                _recentShipmentsSection(),

                const SizedBox(height: 20),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // HEADER
  // ============================================================

  Widget _header() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Welcome Back', style: LogisticsStyle.small),

              const SizedBox(height: 4),

              Text(
                _firstName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: LogisticsStyle.title,
              ),

              const SizedBox(height: 4),

              Text(
                _companyName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: LogisticsStyle.subtitle,
              ),
            ],
          ),
        ),

        Container(
          height: 52,
          width: 52,
          decoration: BoxDecoration(
            color: LogisticsColors.lightPurple,
            borderRadius: BorderRadius.circular(16),
          ),
          child: const Icon(
            Icons.warehouse_outlined,
            color: LogisticsColors.primary,
            size: 27,
          ),
        ),
      ],
    );
  }

  // ============================================================
  // PARCEL OVERVIEW
  // ============================================================

  Widget _parcelOverview() {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      crossAxisSpacing: 12,
      mainAxisSpacing: 12,
      childAspectRatio: 1.25,
      children: [
        _overviewCard(
          title: 'Total Parcels',
          value: _displayTotalParcels.toString(),
          icon: Icons.inventory_2_outlined,
        ),

        _overviewCard(
          title: 'Pending',
          value: _pendingParcels.toString(),
          icon: Icons.pending_actions_rounded,
        ),

        _overviewCard(
          title: 'In Transit',
          value: _displayInTransitParcels.toString(),
          icon: Icons.local_shipping_outlined,
        ),

        _overviewCard(
          title: 'Delivered',
          value: _displayDeliveredParcels.toString(),
          icon: Icons.check_circle_outline_rounded,
        ),

        _overviewCard(
          title: 'Preparing',
          value: _preparingParcels.toString(),
          icon: Icons.inventory_outlined,
        ),

        _overviewCard(
          title: 'Out for Delivery',
          value: _outForDeliveryParcels.toString(),
          icon: Icons.delivery_dining_rounded,
        ),

        _overviewCard(
          title: 'Cancelled',
          value: _cancelledParcels.toString(),
          icon: Icons.cancel_outlined,
        ),

        _overviewCard(
          title: 'Alerts',
          value: _alertsCount.toString(),
          icon: Icons.notifications_active_outlined,
        ),
      ],
    );
  }

  // ============================================================
  // SORTING CENTER
  // ============================================================

  Widget _sortingCenterCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.warehouse_outlined, color: LogisticsColors.primary),
              SizedBox(width: 9),
              Text('Sorting Center', style: LogisticsStyle.sectionTitle),
            ],
          ),

          const SizedBox(height: 5),

          const Text(
            'Current parcel processing status',
            style: LogisticsStyle.small,
          ),

          const SizedBox(height: 18),

          _activityRow(
            icon: Icons.move_to_inbox_rounded,
            title: 'Received',
            value: _receivedParcels.toString(),
          ),

          const SizedBox(height: 12),

          _activityRow(
            icon: Icons.qr_code_scanner_rounded,
            title: 'Scanned',
            value: _scannedParcels.toString(),
          ),

          const SizedBox(height: 12),

          _activityRow(
            icon: Icons.sort_rounded,
            title: 'Sorted',
            value: _sortedParcels.toString(),
          ),

          const SizedBox(height: 12),

          _activityRow(
            icon: Icons.inventory_rounded,
            title: 'Staged',
            value: _stagedParcels.toString(),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // HUB TRANSFER / LINE HAUL
  // ============================================================

  Widget _hubTransferCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.multiple_stop_rounded, color: LogisticsColors.primary),
              SizedBox(width: 9),
              Text(
                'Hub Transfer & Line Haul',
                style: LogisticsStyle.sectionTitle,
              ),
            ],
          ),

          const SizedBox(height: 5),

          const Text(
            'Current inter-hub routing activity',
            style: LogisticsStyle.small,
          ),

          const SizedBox(height: 18),

          _activityRow(
            icon: Icons.route_outlined,
            title: 'Requires Hub Transfer',
            value: _requiresTransferCount.toString(),
          ),

          const SizedBox(height: 12),

          _activityRow(
            icon: Icons.person_search_outlined,
            title: 'Awaiting Line-Haul Rider',
            value: _awaitingLinehaulCount.toString(),
          ),

          const SizedBox(height: 12),

          _activityRow(
            icon: Icons.assignment_ind_outlined,
            title: 'Line-Haul Assigned',
            value: _linehaulAssignedCount.toString(),
          ),

          const SizedBox(height: 12),

          _activityRow(
            icon: Icons.local_shipping_outlined,
            title: 'Line-Haul In Transit',
            value: _linehaulInTransitCount.toString(),
          ),

          const SizedBox(height: 12),

          _activityRow(
            icon: Icons.location_city_outlined,
            title: 'At Destination Hub',
            value: _destinationHubCount.toString(),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // OPERATIONS
  // ============================================================

  Widget _operationsCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Operations', style: LogisticsStyle.sectionTitle),

          const SizedBox(height: 18),

          _activityRow(
            icon: Icons.people_outline_rounded,
            title: 'Total Riders',
            value: _totalRiders.toString(),
          ),

          const SizedBox(height: 12),

          _activityRow(
            icon: Icons.delivery_dining_rounded,
            title: 'Active Riders',
            value: _activeRiders.toString(),
          ),

          if (_availableRiders > 0) ...[
            const SizedBox(height: 12),
            _activityRow(
              icon: Icons.person_pin_circle_outlined,
              title: 'Available Riders',
              value: _availableRiders.toString(),
            ),
          ],

          if (_onDeliveryRiders > 0) ...[
            const SizedBox(height: 12),
            _activityRow(
              icon: Icons.local_shipping_outlined,
              title: 'Riders on Delivery',
              value: _onDeliveryRiders.toString(),
            ),
          ],

          const SizedBox(height: 12),

          _activityRow(
            icon: Icons.hub_outlined,
            title: 'Total Hubs',
            value: _totalHubs.toString(),
          ),

          const SizedBox(height: 12),

          _activityRow(
            icon: Icons.warehouse_outlined,
            title: 'Active Hubs',
            value: _activeHubs.toString(),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // SCANNER CARD
  // ============================================================

  Widget _scannerCard() {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(22),
      child: InkWell(
        onTap: _openScanner,
        borderRadius: BorderRadius.circular(22),
        child: Ink(
          width: double.infinity,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: <Color>[
                LogisticsColors.primary,
                LogisticsColors.primaryDark,
              ],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(22),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: LogisticsColors.primary.withValues(alpha: 0.18),
                blurRadius: 18,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                height: 52,
                width: 52,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(
                  Icons.qr_code_scanner_rounded,
                  color: Colors.white,
                  size: 29,
                ),
              ),

              const SizedBox(width: 14),

              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Scan Parcel',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Verify a parcel QR code, '
                      'sorting status, and hub route.',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),

              const Icon(
                Icons.arrow_forward_ios_rounded,
                color: Colors.white,
                size: 16,
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // RECENT SHIPMENTS
  // ============================================================

  Widget _recentShipmentsSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text('Recent Shipments', style: LogisticsStyle.sectionTitle),
            Text(
              '${_currentShipments.length}',
              style: const TextStyle(
                color: LogisticsColors.primary,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),

        const SizedBox(height: 12),

        if (!_isLoading && _currentShipments.isEmpty)
          _emptyRecentShipments()
        else
          ..._currentShipments.take(5).map(_recentShipmentCard),
      ],
    );
  }

  Widget _recentShipmentCard(ParcelModel parcel) {
    final String route = _routeText(parcel);

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(15),
      decoration: LogisticsStyle.card,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 44,
            width: 44,
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(
              parcel.isLinehaulInTransit
                  ? Icons.multiple_stop_rounded
                  : parcel.isOutForDelivery
                  ? Icons.delivery_dining_rounded
                  : Icons.inventory_2_outlined,
              color: LogisticsColors.primary,
            ),
          ),

          const SizedBox(width: 12),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  parcel.trackingNumber.trim().isEmpty
                      ? 'Tracking unavailable'
                      : parcel.trackingNumber,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: LogisticsStyle.cardTitle,
                ),

                const SizedBox(height: 3),

                Text(
                  parcel.customerName.trim().isEmpty
                      ? 'Customer unavailable'
                      : parcel.customerName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: LogisticsStyle.small,
                ),

                const SizedBox(height: 4),

                Text(
                  'Status: '
                  '${_formatStatus(parcel.status)}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: LogisticsStyle.small,
                ),

                if (parcel.sortingStatus.trim().isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    'Sorting: '
                    '${_formatStatus(parcel.sortingStatus)}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: LogisticsStyle.small,
                  ),
                ],

                if (route.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    route,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: LogisticsStyle.small,
                  ),
                ],

                if (parcel.linehaulStatus.trim().isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    'Line Haul: '
                    '${_formatStatus(parcel.linehaulStatus)}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: LogisticsStyle.small,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // ERROR CARD
  // ============================================================

  Widget _errorCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: LogisticsStyle.card,
      child: Column(
        children: [
          const Icon(
            Icons.cloud_off_outlined,
            color: LogisticsColors.warning,
            size: 30,
          ),

          const SizedBox(height: 8),

          Text(
            _errorMessage ?? 'Unable to load dashboard.',
            textAlign: TextAlign.center,
            style: LogisticsStyle.small,
          ),

          const SizedBox(height: 10),

          TextButton.icon(
            onPressed: () {
              _loadDashboard();
            },
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // LOADING
  // ============================================================

  Widget _loadingCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: LogisticsStyle.card,
      child: const Row(
        children: [
          SizedBox(
            height: 22,
            width: 22,
            child: CircularProgressIndicator(
              strokeWidth: 2.2,
              color: LogisticsColors.primary,
            ),
          ),
          SizedBox(width: 12),
          Expanded(
            child: Text(
              'Loading logistics dashboard...',
              style: LogisticsStyle.small,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // OVERVIEW CARD
  // ============================================================

  Widget _overviewCard({
    required String title,
    required String value,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: LogisticsColors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: LogisticsColors.border),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.035),
            blurRadius: 12,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 40,
            width: 40,
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: LogisticsColors.primary, size: 21),
          ),

          const Spacer(),

          Text(
            value,
            style: const TextStyle(
              color: LogisticsColors.primary,
              fontSize: 23,
              fontWeight: FontWeight.w800,
            ),
          ),

          const SizedBox(height: 2),

          Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: LogisticsStyle.small,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // ACTIVITY ROW
  // ============================================================

  Widget _activityRow({
    required IconData icon,
    required String title,
    required String value,
  }) {
    return Row(
      children: [
        Container(
          height: 42,
          width: 42,
          decoration: BoxDecoration(
            color: LogisticsColors.lightPurple,
            borderRadius: BorderRadius.circular(13),
          ),
          child: Icon(icon, color: LogisticsColors.primary, size: 21),
        ),

        const SizedBox(width: 12),

        Expanded(
          child: Text(
            title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: LogisticsStyle.body,
          ),
        ),

        const SizedBox(width: 10),

        Text(
          value,
          style: const TextStyle(
            color: LogisticsColors.primary,
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }

  // ============================================================
  // EMPTY RECENT SHIPMENTS
  // ============================================================

  Widget _emptyRecentShipments() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: LogisticsStyle.card,
      child: const Column(
        children: [
          Icon(
            Icons.inventory_2_outlined,
            color: LogisticsColors.textLight,
            size: 34,
          ),
          SizedBox(height: 8),
          Text('No recent shipments.', style: LogisticsStyle.small),
        ],
      ),
    );
  }
}
