import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../services/api_services.dart';
import '../../styles/logistics_style.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_loading_state.dart';
import '../../widgets/dashboard_card.dart';

class ParcelStatistics extends StatefulWidget {
  const ParcelStatistics({super.key});

  @override
  State<ParcelStatistics> createState() => _ParcelStatisticsState();
}

class _ParcelStatisticsState extends State<ParcelStatistics> {
  static const Duration _requestTimeout = Duration(seconds: 25);

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _hasDashboardData = false;
  bool _hasShipmentData = false;
  bool _isFetching = false;

  String? _dashboardError;
  String? _shipmentsError;

  Map<String, dynamic> _shipments = <String, dynamic>{};
  Map<String, dynamic> _riders = <String, dynamic>{};
  Map<String, dynamic> _hubs = <String, dynamic>{};

  int? _alertsCount;

  List<ParcelModel> _parcelRecords = <ParcelModel>[];

  int _requestId = 0;

  @override
  void initState() {
    super.initState();
    _loadStatistics();
  }

  @override
  void dispose() {
    _requestId++;
    super.dispose();
  }

  // ============================================================
  // LOAD STATISTICS
  // ============================================================

  Future<void> _loadStatistics({bool refresh = false}) async {
    if (!mounted || _isFetching) return;

    final int requestId = ++_requestId;

    final bool hasPreviousData = _hasDashboardData || _hasShipmentData;

    setState(() {
      _isFetching = true;
      _isLoading = !hasPreviousData;
      _isRefreshing = hasPreviousData || refresh;
      _dashboardError = null;
      _shipmentsError = null;
    });

    try {
      final List<_StatisticsApiResult> results =
          await Future.wait<_StatisticsApiResult>([
            _fetchDashboard(),
            _fetchShipments(),
          ]);

      if (!mounted || requestId != _requestId) return;

      final _StatisticsApiResult dashboardResponse = results[0];
      final _StatisticsApiResult shipmentsResponse = results[1];

      final bool dashboardSuccess = dashboardResponse.data?['success'] == true;

      final bool shipmentsSuccess = shipmentsResponse.data?['success'] == true;

      Map<String, dynamic>? dashboardData;
      List<ParcelModel>? parsedParcels;

      String? dashboardError = dashboardResponse.error;
      String? shipmentsError = shipmentsResponse.error;

      if (dashboardSuccess) {
        try {
          dashboardData = _extractDashboardData(dashboardResponse.data!);
        } catch (error) {
          debugPrint('Dashboard parsing error: $error');
          dashboardError = 'Unable to read dashboard statistics.';
        }
      }

      if (shipmentsSuccess) {
        try {
          parsedParcels = _extractParcels(shipmentsResponse.data!);
        } catch (error) {
          debugPrint('Shipment parsing error: $error');
          shipmentsError = 'Unable to read shipment statistics.';
        }
      }

      if (!mounted || requestId != _requestId) return;

      setState(() {
        if (dashboardData != null) {
          _shipments = _map(dashboardData['shipments']);
          _riders = _map(dashboardData['riders']);
          _hubs = _map(dashboardData['hubs']);

          _alertsCount = _number(dashboardData['alerts_count']);

          _hasDashboardData = true;
          dashboardError = null;
        }

        if (parsedParcels != null) {
          _parcelRecords = parsedParcels;
          _hasShipmentData = true;
          shipmentsError = null;
        }

        _dashboardError = dashboardData != null
            ? null
            : dashboardError ??
                  'Dashboard statistics are currently unavailable.';

        _shipmentsError = parsedParcels != null
            ? null
            : shipmentsError ?? 'Shipment records are currently unavailable.';

        _isLoading = false;
        _isRefreshing = false;
        _isFetching = false;
      });
    } catch (error) {
      debugPrint('Parcel statistics loading error: $error');

      if (!mounted || requestId != _requestId) return;

      setState(() {
        _isLoading = false;
        _isRefreshing = false;
        _isFetching = false;

        _dashboardError = 'Unable to load statistics. Please try again.';

        _shipmentsError = 'Unable to refresh shipment records.';
      });
    }
  }

  // ============================================================
  // INDEPENDENT API REQUESTS
  // ============================================================

  Future<_StatisticsApiResult> _fetchDashboard() async {
    try {
      final Map<String, dynamic> result =
          await ApiService.getLogisticsDashboard().timeout(_requestTimeout);

      if (result['success'] == true) {
        return _StatisticsApiResult(data: result);
      }

      return _StatisticsApiResult(
        data: result,
        error: _responseMessage(result, 'Unable to load logistics dashboard.'),
      );
    } on TimeoutException {
      return const _StatisticsApiResult(error: 'Dashboard request timed out.');
    } catch (error) {
      debugPrint('Statistics dashboard API error: $error');

      return const _StatisticsApiResult(
        error: 'Unable to connect to the logistics dashboard API.',
      );
    }
  }

  Future<_StatisticsApiResult> _fetchShipments() async {
    try {
      final Map<String, dynamic> result =
          await ApiService.getLogisticsShipments(perPage: 100)
              .timeout(_requestTimeout);

      if (result['success'] == true) {
        return _StatisticsApiResult(data: result);
      }

      return _StatisticsApiResult(
        data: result,
        error: _responseMessage(result, 'Unable to load shipment records.'),
      );
    } on TimeoutException {
      return const _StatisticsApiResult(error: 'Shipment request timed out.');
    } catch (error) {
      debugPrint('Statistics shipments API error: $error');

      return const _StatisticsApiResult(
        error: 'Unable to connect to the logistics shipments API.',
      );
    }
  }

  String _responseMessage(Map<String, dynamic> result, String fallback) {
    final String message = result['message']?.toString().trim() ?? '';

    return message.isEmpty ? fallback : message;
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

  int? _number(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is num) return value.toInt();

    return int.tryParse(value.toString());
  }

  String _dashboardCount(Map<String, dynamic> source, String key) {
    if (!_hasDashboardData) return '—';

    final int? count = _number(source[key]);

    return count?.toString() ?? '—';
  }

  String _alertsValue() {
    if (!_hasDashboardData) return '—';

    return _alertsCount?.toString() ?? '—';
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
        debugPrint('Unable to parse statistics parcel: $error');
      }
    }

    return parcels;
  }

  List<Map<String, dynamic>> _extractShipmentMaps(Map<String, dynamic> result) {
    for (final String key in <String>['data', 'shipments', 'parcels']) {
      if (result[key] is List) {
        return _toMapList(result[key]);
      }
    }

    final Map<String, dynamic> data = _map(result['data']);

    for (final String key in <String>['data', 'shipments', 'parcels']) {
      if (data[key] is List) {
        return _toMapList(data[key]);
      }
    }

    return <Map<String, dynamic>>[];
  }

  List<Map<String, dynamic>> _toMapList(dynamic value) {
    if (value is! List) {
      return <Map<String, dynamic>>[];
    }

    return value
        .whereType<Map>()
        .map((Map item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Map<String, dynamic> _normalizeShipment(Map<String, dynamic> raw) {
    final Map<String, dynamic> data = Map<String, dynamic>.from(raw);

    final Map<String, dynamic> shipment = _map(data['shipment']);

    if (shipment.isNotEmpty) {
      data.addAll(shipment);
    }

    final Map<String, dynamic> order = _map(data['order']);

    data['customer_name'] ??=
        order['customer_name'] ?? order['buyer_name'] ?? order['name'];

    data['customer_phone'] ??= order['customer_phone'] ?? order['phone'];

    data['delivery_address'] ??= order['delivery_address'] ?? order['address'];

    data['order_date'] ??= order['order_date'] ?? order['created_at'];

    final Map<String, dynamic> rider = _map(data['rider']);

    data['rider_id'] ??= rider['id'];
    data['rider_name'] ??= rider['name'];

    final Map<String, dynamic> linehaulRider = _map(data['linehaul_rider']);

    data['linehaul_rider_id'] ??= linehaulRider['id'];
    data['linehaul_rider_name'] ??= linehaulRider['name'];

    final Map<String, dynamic> originHub = _map(data['origin_hub']);

    data['origin_hub_id'] ??= originHub['id'];
    data['origin_hub_name'] ??= originHub['name'];

    final Map<String, dynamic> destinationHub = _map(data['destination_hub']);

    data['destination_hub_id'] ??= destinationHub['id'];
    data['destination_hub_name'] ??= destinationHub['name'];

    final Map<String, dynamic> currentHub = _map(data['hub']);

    data['current_hub_id'] ??= currentHub['id'];
    data['current_hub_name'] ??= currentHub['name'];

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
  // HUB TRANSFER COUNTS FROM LOADED RECORDS
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

  String _sampleCount(int count) {
    if (!_hasShipmentData) return '—';

    return count.toString();
  }

  // ============================================================
  // DELIVERY COMPLETION
  // ============================================================

  double? get _completion {
    if (!_hasDashboardData) return null;

    final int? total = _number(_shipments['total']);
    final int? delivered = _number(_shipments['delivered']);

    if (total == null || delivered == null) {
      return null;
    }

    if (total <= 0) {
      return 0.0;
    }

    return (delivered / total).clamp(0.0, 1.0);
  }

  String get _completionLabel {
    final double? value = _completion;

    if (value == null) {
      return 'Completion rate unavailable';
    }

    if (_number(_shipments['total']) == 0) {
      return 'No shipments recorded yet';
    }

    return '${(value * 100).toStringAsFixed(1)}% '
        'of shipments delivered';
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.sizeOf(context).width;
    final double horizontalPadding = width < 380 ? 14 : 18;

    final bool hasAnyData = _hasDashboardData || _hasShipmentData;

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
          IconButton(
            tooltip: 'Refresh statistics',
            onPressed: _isFetching
                ? null
                : () => _loadStatistics(refresh: true),
            icon: _isRefreshing
                ? const SizedBox(
                    height: 19,
                    width: 19,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: LogisticsColors.primary,
                    ),
                  )
                : const Icon(
                    Icons.refresh_rounded,
                    color: LogisticsColors.primary,
                  ),
          ),
          const SizedBox(width: 5),
        ],
      ),
      body: SafeArea(
        top: false,
        child: RefreshIndicator(
          color: LogisticsColors.primary,
          onRefresh: () => _loadStatistics(refresh: true),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: EdgeInsets.fromLTRB(
              horizontalPadding,
              14,
              horizontalPadding,
              30,
            ),
            children: [
              if (_isLoading && !hasAnyData) ...[
                const SizedBox(height: 120),
                const AppLoadingState(
                  message: 'Loading parcel statistics...',
                  color: LogisticsColors.primary,
                ),
              ] else if (!hasAnyData) ...[
                const SizedBox(height: 80),
                AppErrorState(
                  title: 'Unable to Load Statistics',
                  message:
                      _dashboardError ??
                      _shipmentsError ??
                      'Unable to retrieve logistics statistics.',
                  color: LogisticsColors.primary,
                  onRetry: _loadStatistics,
                ),
              ] else ...[
                if (_isRefreshing) ...[
                  const LinearProgressIndicator(
                    color: LogisticsColors.primary,
                    minHeight: 2,
                  ),
                  const SizedBox(height: 14),
                ],

                if (_dashboardError != null) ...[
                  _warningBanner(
                    title: 'Dashboard Statistics',
                    message: _dashboardError!,
                  ),
                  const SizedBox(height: 12),
                ],

                if (_shipmentsError != null) ...[
                  _warningBanner(
                    title: 'Shipment Records',
                    message: _shipmentsError!,
                  ),
                  const SizedBox(height: 12),
                ],

                _dataSourceNotice(),

                const SizedBox(height: 24),

                const Text(
                  'Delivery Performance',
                  style: LogisticsStyle.sectionTitle,
                ),

                const SizedBox(height: 5),

                const Text(
                  'Official Laravel dashboard totals',
                  style: LogisticsStyle.small,
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
              ],
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // API WARNINGS
  // ============================================================

  Widget _warningBanner({required String title, required String message}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: LogisticsStyle.card,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.info_outline_rounded,
            color: LogisticsColors.warning,
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: LogisticsStyle.cardTitle),
                const SizedBox(height: 4),
                Text(message, style: LogisticsStyle.small),
              ],
            ),
          ),
          TextButton(
            onPressed: _isFetching
                ? null
                : () => _loadStatistics(refresh: true),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _dataSourceNotice() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: LogisticsColors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: LogisticsColors.border),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.analytics_outlined,
            color: LogisticsColors.primary,
            size: 22,
          ),
          SizedBox(width: 10),
          Expanded(
            child: Text(
              'Delivery totals, sorting statistics, and '
              'network counts use Laravel dashboard data. '
              'Hub-transfer counts use the loaded shipment '
              'records only.',
              style: LogisticsStyle.small,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // DELIVERY PERFORMANCE
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
          value: _dashboardCount(_shipments, 'total'),
          icon: Icons.inventory_2_outlined,
          color: LogisticsColors.primary,
        ),
        DashboardCard(
          title: 'Pending',
          value: _dashboardCount(_shipments, 'pending'),
          icon: Icons.pending_actions_rounded,
          color: LogisticsColors.warning,
        ),
        DashboardCard(
          title: 'Preparing',
          value: _dashboardCount(_shipments, 'preparing'),
          icon: Icons.inventory_outlined,
          color: LogisticsColors.warning,
        ),
        DashboardCard(
          title: 'In Transit',
          value: _dashboardCount(_shipments, 'in_transit'),
          icon: Icons.local_shipping_outlined,
          color: LogisticsColors.primary,
        ),
        DashboardCard(
          title: 'Out for Delivery',
          value: _dashboardCount(_shipments, 'out_for_delivery'),
          icon: Icons.delivery_dining_rounded,
          color: LogisticsColors.primary,
        ),
        DashboardCard(
          title: 'Delivered',
          value: _dashboardCount(_shipments, 'delivered'),
          icon: Icons.check_circle_outline_rounded,
          color: LogisticsColors.success,
        ),
        DashboardCard(
          title: 'Cancelled',
          value: _dashboardCount(_shipments, 'cancelled'),
          icon: Icons.cancel_outlined,
          color: LogisticsColors.danger,
        ),
        DashboardCard(
          title: 'Alerts',
          value: _alertsValue(),
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
    return _sectionCard(
      title: 'Sorting Progress',
      subtitle: 'Statistics reported by Laravel',
      icon: Icons.warehouse_outlined,
      children: [
        _statRow(
          title: 'Received',
          value: _dashboardCount(_shipments, 'received'),
          icon: Icons.move_to_inbox_rounded,
        ),
        _statRow(
          title: 'Scanned',
          value: _dashboardCount(_shipments, 'scanned'),
          icon: Icons.qr_code_scanner_rounded,
        ),
        _statRow(
          title: 'Sorted',
          value: _dashboardCount(_shipments, 'sorted'),
          icon: Icons.sort_rounded,
        ),
        _statRow(
          title: 'Staged',
          value: _dashboardCount(_shipments, 'staged'),
          icon: Icons.inventory_rounded,
        ),
      ],
    );
  }

  // ============================================================
  // HUB TRANSFER / LINE HAUL
  // ============================================================

  Widget _routingCard() {
    return _sectionCard(
      title: 'Hub Transfer & Line Haul',
      subtitle: 'Counts from loaded shipments (up to 100 records)',
      icon: Icons.multiple_stop_rounded,
      children: [
        if (!_hasShipmentData) ...[
          const AppEmptyState(
            title: 'Routing Data Unavailable',
            message:
                'Unable to retrieve shipment records '
                'for hub-transfer statistics.',
            icon: Icons.route_outlined,
            color: LogisticsColors.primary,
            compact: true,
          ),
        ] else ...[
          _statRow(
            title: 'Loaded Shipments',
            value: '${_parcelRecords.length}',
            icon: Icons.inventory_2_outlined,
          ),
          const Divider(height: 22, color: LogisticsColors.border),
          _statRow(
            title: 'Requires Hub Transfer',
            value: _sampleCount(_requiresTransferCount),
            icon: Icons.route_outlined,
          ),
          _statRow(
            title: 'Awaiting Line-Haul Rider',
            value: _sampleCount(_awaitingLinehaulCount),
            icon: Icons.person_search_outlined,
          ),
          _statRow(
            title: 'Line-Haul Assigned',
            value: _sampleCount(_linehaulAssignedCount),
            icon: Icons.assignment_ind_outlined,
          ),
          _statRow(
            title: 'Line-Haul In Transit',
            value: _sampleCount(_linehaulInTransitCount),
            icon: Icons.local_shipping_outlined,
          ),
          _statRow(
            title: 'Line-Haul Completed',
            value: _sampleCount(_linehaulCompletedCount),
            icon: Icons.check_circle_outline_rounded,
          ),
          _statRow(
            title: 'At Destination Hub',
            value: _sampleCount(_destinationHubCount),
            icon: Icons.location_city_outlined,
          ),
        ],
      ],
    );
  }

  // ============================================================
  // DELIVERY COMPLETION
  // ============================================================

  Widget _completionCard() {
    final double? completion = _completion;

    return _sectionCard(
      title: 'Delivery Completion',
      subtitle: 'Based on official dashboard totals',
      icon: Icons.trending_up_rounded,
      children: [
        LinearProgressIndicator(
          value: completion ?? 0,
          minHeight: 9,
          borderRadius: BorderRadius.circular(20),
          color: LogisticsColors.success,
          backgroundColor: LogisticsColors.lightPurple,
        ),
        const SizedBox(height: 12),
        Text(_completionLabel, style: LogisticsStyle.cardTitle),
        const SizedBox(height: 9),
        AppInfoRow(
          label: 'Total Shipments',
          value: _dashboardCount(_shipments, 'total'),
          icon: Icons.inventory_2_outlined,
          iconColor: LogisticsColors.primary,
          compact: true,
        ),
        const SizedBox(height: 8),
        AppInfoRow(
          label: 'Delivered',
          value: _dashboardCount(_shipments, 'delivered'),
          icon: Icons.check_circle_outline_rounded,
          iconColor: LogisticsColors.primary,
          compact: true,
        ),
      ],
    );
  }

  // ============================================================
  // LOGISTICS NETWORK
  // ============================================================

  Widget _networkCard() {
    return _sectionCard(
      title: 'Logistics Network',
      subtitle: 'Rider and hub statistics from Laravel',
      icon: Icons.hub_outlined,
      children: [
        _statRow(
          title: 'Total Riders',
          value: _dashboardCount(_riders, 'total'),
          icon: Icons.people_outline_rounded,
        ),
        _statRow(
          title: 'Active Riders',
          value: _dashboardCount(_riders, 'active'),
          icon: Icons.delivery_dining_rounded,
        ),
        if (_riders.containsKey('available'))
          _statRow(
            title: 'Available Riders',
            value: _dashboardCount(_riders, 'available'),
            icon: Icons.person_pin_circle_outlined,
          ),
        if (_riders.containsKey('on_delivery'))
          _statRow(
            title: 'On Delivery',
            value: _dashboardCount(_riders, 'on_delivery'),
            icon: Icons.local_shipping_outlined,
          ),
        const Divider(height: 22, color: LogisticsColors.border),
        _statRow(
          title: 'Total Hubs',
          value: _dashboardCount(_hubs, 'total'),
          icon: Icons.hub_outlined,
        ),
        _statRow(
          title: 'Active Hubs',
          value: _dashboardCount(_hubs, 'active'),
          icon: Icons.warehouse_outlined,
        ),
      ],
    );
  }

  // ============================================================
  // REUSABLE SECTION CARD
  // ============================================================

  Widget _sectionCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required List<Widget> children,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
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
          const SizedBox(height: 5),
          Text(subtitle, style: LogisticsStyle.small),
          const SizedBox(height: 16),
          ...children,
        ],
      ),
    );
  }

  // ============================================================
  // REUSABLE STAT ROW
  // ============================================================

  Widget _statRow({
    required String title,
    required String value,
    required IconData icon,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: LogisticsColors.primary, size: 20),
          ),
          const SizedBox(width: 11),
          Expanded(child: Text(title, style: LogisticsStyle.body)),
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
      ),
    );
  }
}

// ============================================================
// INDEPENDENT API RESPONSE
// ============================================================

class _StatisticsApiResult {
  final Map<String, dynamic>? data;
  final String? error;

  const _StatisticsApiResult({this.data, this.error});
}
