import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../sevices/api_services.dart';
import '../../styles/logistics_style.dart';
import '../../widgets/dashboard_card.dart';

class ParcelStatistics extends StatefulWidget {
  const ParcelStatistics({super.key});

  @override
  State<ParcelStatistics> createState() => _ParcelStatisticsState();
}

class _ParcelStatisticsState extends State<ParcelStatistics> {
  bool _isLoading = true;
  bool _isRefreshing = false;

  String? _errorMessage;

  Map<String, dynamic> _shipments = <String, dynamic>{};

  Map<String, dynamic> _riders = <String, dynamic>{};

  Map<String, dynamic> _hubs = <String, dynamic>{};

  int _alertsCount = 0;

  final List<ParcelModel> _parcelRecords = <ParcelModel>[];

  @override
  void initState() {
    super.initState();

    _loadStatistics();
  }

  // ============================================================
  // LOAD STATISTICS
  // ============================================================

  Future<void> _loadStatistics({bool refresh = false}) async {
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

      if (dashboardResult['success'] != true) {
        final String message =
            dashboardResult['message']?.toString().trim() ?? '';

        setState(() {
          _isLoading = false;
          _isRefreshing = false;

          _errorMessage = message.isNotEmpty
              ? message
              : 'Unable to load parcel statistics.';
        });

        return;
      }

      final Map<String, dynamic> data = _extractDashboardData(dashboardResult);

      final List<ParcelModel> parcels = shipmentsResult['success'] == true
          ? _extractParcels(shipmentsResult)
          : <ParcelModel>[];

      setState(() {
        _shipments = _map(data['shipments']);

        _riders = _map(data['riders']);

        _hubs = _map(data['hubs']);

        _alertsCount = _number(data['alerts_count']);

        _parcelRecords
          ..clear()
          ..addAll(parcels);

        _isLoading = false;
        _isRefreshing = false;

        _errorMessage = null;
      });
    } catch (error) {
      debugPrint('Logistics statistics error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _isLoading = false;
        _isRefreshing = false;

        _errorMessage =
            'Unable to load parcel statistics. '
            'Please try again.';
      });
    }
  }

  // ============================================================
  // DASHBOARD RESPONSE
  // ============================================================

  Map<String, dynamic> _extractDashboardData(Map<String, dynamic> result) {
    final dynamic rawData = result['data'];

    if (rawData is Map) {
      return Map<String, dynamic>.from(rawData);
    }

    return Map<String, dynamic>.from(result);
  }

  Map<String, dynamic> _map(dynamic value) {
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }

    return <String, dynamic>{};
  }

  int _number(dynamic value) {
    if (value is int) {
      return value;
    }

    if (value is num) {
      return value.toInt();
    }

    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  // ============================================================
  // PARCEL RECORDS
  // ============================================================

  List<ParcelModel> _extractParcels(Map<String, dynamic> result) {
    final List<Map<String, dynamic>> items = _extractShipmentMaps(result);

    final List<ParcelModel> parcels = <ParcelModel>[];

    for (final Map<String, dynamic> item in items) {
      try {
        parcels.add(ParcelModel.fromJson(_normalizeShipment(item)));
      } catch (error) {
        debugPrint(
          'Unable to parse statistics parcel: '
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

    data['rider_name'] ??= '';

    data['status'] ??= data['shipment_status'] ?? 'pending';

    data['order_date'] ??= data['created_at'] ?? '';

    data['delivery_earnings'] ??= data['earnings'] ?? 0;

    return data;
  }

  // ============================================================
  // DASHBOARD COUNTS
  // ============================================================

  int get _totalShipments {
    final int dashboard = _number(_shipments['total']);

    if (dashboard > 0 || _parcelRecords.isEmpty) {
      return dashboard;
    }

    return _parcelRecords.length;
  }

  int get _pendingShipments {
    return _number(_shipments['pending']);
  }

  int get _preparingShipments {
    return _number(_shipments['preparing']);
  }

  int get _inTransitShipments {
    final int dashboard = _number(_shipments['in_transit']);

    if (dashboard > 0 || _parcelRecords.isEmpty) {
      return dashboard;
    }

    return _parcelRecords.where((ParcelModel parcel) {
      return parcel.isInTransit || parcel.isLinehaulInTransit;
    }).length;
  }

  int get _outForDeliveryShipments {
    final int dashboard = _number(_shipments['out_for_delivery']);

    if (dashboard > 0 || _parcelRecords.isEmpty) {
      return dashboard;
    }

    return _parcelRecords
        .where((ParcelModel parcel) => parcel.isOutForDelivery)
        .length;
  }

  int get _deliveredShipments {
    final int dashboard = _number(_shipments['delivered']);

    if (dashboard > 0 || _parcelRecords.isEmpty) {
      return dashboard;
    }

    return _parcelRecords
        .where((ParcelModel parcel) => parcel.isDelivered)
        .length;
  }

  int get _cancelledShipments {
    return _number(_shipments['cancelled']);
  }

  // ============================================================
  // SORTING COUNTS
  // ============================================================

  int get _receivedCount {
    final int dashboard = _number(_shipments['received']);

    if (dashboard > 0 || _parcelRecords.isEmpty) {
      return dashboard;
    }

    return _parcelRecords
        .where((ParcelModel parcel) => parcel.receivedBySortingCenter)
        .length;
  }

  int get _scannedCount {
    return _number(_shipments['scanned']);
  }

  int get _sortedCount {
    final int dashboard = _number(_shipments['sorted']);

    if (dashboard > 0 || _parcelRecords.isEmpty) {
      return dashboard;
    }

    return _parcelRecords
        .where(
          (ParcelModel parcel) => parcel.normalizedSortingStatus == 'sorted',
        )
        .length;
  }

  int get _stagedCount {
    final int dashboard = _number(_shipments['staged']);

    if (dashboard > 0 || _parcelRecords.isEmpty) {
      return dashboard;
    }

    return _parcelRecords.where((ParcelModel parcel) => parcel.isStaged).length;
  }

  // ============================================================
  // ROUTING / LINE HAUL COUNTS
  // ============================================================

  int get _requiresTransferCount {
    return _parcelRecords
        .where((ParcelModel parcel) => parcel.requiresHubTransfer)
        .length;
  }

  int get _awaitingLinehaulCount {
    return _parcelRecords
        .where((ParcelModel parcel) => parcel.isLinehaulAwaitingAssignment)
        .length;
  }

  int get _linehaulAssignedCount {
    return _parcelRecords
        .where((ParcelModel parcel) => parcel.isLinehaulAssigned)
        .length;
  }

  int get _linehaulInTransitCount {
    return _parcelRecords
        .where((ParcelModel parcel) => parcel.isLinehaulInTransit)
        .length;
  }

  int get _linehaulCompletedCount {
    return _parcelRecords
        .where((ParcelModel parcel) => parcel.isLinehaulCompleted)
        .length;
  }

  int get _destinationHubCount {
    return _parcelRecords
        .where((ParcelModel parcel) => parcel.isAtDestinationHub)
        .length;
  }

  // ============================================================
  // COMPLETION RATE
  // ============================================================

  double get _completion {
    final int total = _totalShipments;

    final int delivered = _deliveredShipments;

    if (total <= 0) {
      return 0;
    }

    return (delivered / total).clamp(0.0, 1.0);
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
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
            Text('Parcel Statistics', style: LogisticsStyle.title),
            SizedBox(height: 2),
            Text(
              'Sorting, routing and delivery overview',
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
              tooltip: 'Refresh statistics',
              onPressed: _isLoading
                  ? null
                  : () {
                      _loadStatistics(refresh: true);
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
          return _loadStatistics(refresh: true);
        },
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 30),
          children: [
            if (_isLoading)
              const LinearProgressIndicator(color: LogisticsColors.primary),

            if (_errorMessage != null) ...[
              const SizedBox(height: 12),
              _errorCard(),
            ],

            const SizedBox(height: 18),

            const Text(
              'Delivery Performance',
              style: LogisticsStyle.sectionTitle,
            ),

            const SizedBox(height: 14),

            _deliveryGrid(),

            const SizedBox(height: 22),

            _sortingProgressCard(),

            const SizedBox(height: 18),

            _routingCard(),

            const SizedBox(height: 18),

            _completionCard(),

            const SizedBox(height: 18),

            _networkCard(),

            const SizedBox(height: 22),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // DELIVERY GRID
  // ============================================================

  Widget _deliveryGrid() {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      crossAxisSpacing: 12,
      mainAxisSpacing: 12,
      childAspectRatio: 1.25,
      children: [
        DashboardCard(
          title: 'Total',
          value: '$_totalShipments',
          icon: Icons.inventory_2_outlined,
          color: LogisticsColors.primary,
        ),

        DashboardCard(
          title: 'Pending',
          value: '$_pendingShipments',
          icon: Icons.pending_actions_rounded,
          color: LogisticsColors.warning,
        ),

        DashboardCard(
          title: 'Preparing',
          value: '$_preparingShipments',
          icon: Icons.inventory_outlined,
          color: LogisticsColors.warning,
        ),

        DashboardCard(
          title: 'In Transit',
          value: '$_inTransitShipments',
          icon: Icons.local_shipping_outlined,
          color: LogisticsColors.primary,
        ),

        DashboardCard(
          title: 'Out for Delivery',
          value: '$_outForDeliveryShipments',
          icon: Icons.delivery_dining_rounded,
          color: LogisticsColors.primary,
        ),

        DashboardCard(
          title: 'Delivered',
          value: '$_deliveredShipments',
          icon: Icons.check_circle_outline_rounded,
          color: LogisticsColors.success,
        ),

        DashboardCard(
          title: 'Cancelled',
          value: '$_cancelledShipments',
          icon: Icons.cancel_outlined,
          color: LogisticsColors.danger,
        ),

        DashboardCard(
          title: 'Alerts',
          value: '$_alertsCount',
          icon: Icons.notifications_active_outlined,
          color: LogisticsColors.warning,
        ),
      ],
    );
  }

  // ============================================================
  // SORTING PROGRESS
  // ============================================================

  Widget _sortingProgressCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.warehouse_outlined, color: LogisticsColors.primary),
              SizedBox(width: 9),
              Text('Sorting Progress', style: LogisticsStyle.sectionTitle),
            ],
          ),

          const SizedBox(height: 14),

          _row('Received', _receivedCount),

          _row('Scanned', _scannedCount),

          _row('Sorted', _sortedCount),

          _row('Staged', _stagedCount),
        ],
      ),
    );
  }

  // ============================================================
  // ROUTING / LINE HAUL
  // ============================================================

  Widget _routingCard() {
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
              Text(
                'Hub Transfer & Line Haul',
                style: LogisticsStyle.sectionTitle,
              ),
            ],
          ),

          const SizedBox(height: 6),

          const Text(
            'Calculated from the current shipment '
            'routing records.',
            style: LogisticsStyle.small,
          ),

          const SizedBox(height: 12),

          _row('Requires Hub Transfer', _requiresTransferCount),

          _row('Awaiting Line-Haul Rider', _awaitingLinehaulCount),

          _row('Line-Haul Assigned', _linehaulAssignedCount),

          _row('Line-Haul In Transit', _linehaulInTransitCount),

          _row('Line-Haul Completed', _linehaulCompletedCount),

          _row('At Destination Hub', _destinationHubCount),
        ],
      ),
    );
  }

  // ============================================================
  // COMPLETION RATE
  // ============================================================

  Widget _completionCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Delivery Completion', style: LogisticsStyle.sectionTitle),

          const SizedBox(height: 14),

          LinearProgressIndicator(
            value: _completion,
            minHeight: 9,
            borderRadius: BorderRadius.circular(20),
            color: LogisticsColors.success,
            backgroundColor: LogisticsColors.lightPurple,
          ),

          const SizedBox(height: 10),

          Text(
            '${(_completion * 100).round()}% '
            'of shipments delivered',
            style: LogisticsStyle.small,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // NETWORK
  // ============================================================

  Widget _networkCard() {
    final int totalRiders = _number(_riders['total']);

    final int activeRiders = _number(_riders['active']);

    final int availableRiders = _number(_riders['available']);

    final int onDeliveryRiders = _number(_riders['on_delivery']);

    final int totalHubs = _number(_hubs['total']);

    final int activeHubs = _number(_hubs['active']);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.hub_outlined, color: LogisticsColors.primary),
              SizedBox(width: 9),
              Text('Logistics Network', style: LogisticsStyle.sectionTitle),
            ],
          ),

          const SizedBox(height: 12),

          _row('Total Riders', totalRiders),

          _row('Active Riders', activeRiders),

          if (_riders.containsKey('available'))
            _row('Available Riders', availableRiders),

          if (_riders.containsKey('on_delivery'))
            _row('On Delivery', onDeliveryRiders),

          const Divider(height: 24, color: LogisticsColors.border),

          _row('Total Hubs', totalHubs),

          _row('Active Hubs', activeHubs),
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
              _errorMessage ?? 'Unable to load statistics.',
              style: LogisticsStyle.small,
            ),
          ),

          IconButton(
            tooltip: 'Retry',
            onPressed: () {
              _loadStatistics(refresh: true);
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
  // STAT ROW
  // ============================================================

  Widget _row(String title, int value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        children: [
          Expanded(child: Text(title, style: LogisticsStyle.body)),

          Text('$value', style: LogisticsStyle.cardTitle),
        ],
      ),
    );
  }
}
