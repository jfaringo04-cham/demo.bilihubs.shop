import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../services/api_services.dart';
import '../../services/auth_storage.dart';
import '../../styles/logistics_style.dart';
import '../../utils/address_formatter.dart';
import '../../utils/date_formatter.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_skeleton.dart';
import '../../widgets/status_chip.dart';

import 'parcel_information.dart';

class LogisticsHome extends StatefulWidget {
  final VoidCallback? onOpenScanner;

  const LogisticsHome({super.key, this.onOpenScanner});

  @override
  State<LogisticsHome> createState() => _LogisticsHomeState();
}

class _LogisticsHomeState extends State<LogisticsHome> {
  static const Duration _requestTimeout = Duration(seconds: 25);

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isRequestInProgress = false;

  bool _hasDashboardData = false;
  bool _hasShipmentData = false;

  String? _dashboardError;
  String? _shipmentsError;

  String _savedUserName = 'Logistics Staff';
  String _companyName = 'Logistics';

  Map<String, dynamic> _shipmentStats = <String, dynamic>{};
  Map<String, dynamic> _riderStats = <String, dynamic>{};
  Map<String, dynamic> _hubStats = <String, dynamic>{};

  dynamic _alertsCount;

  List<ParcelModel> _currentShipments = <ParcelModel>[];

  int _requestId = 0;

  @override
  void initState() {
    super.initState();
    _loadDashboard();
  }

  @override
  void dispose() {
    _requestId++;
    super.dispose();
  }

  // ============================================================
  // DASHBOARD LOADING
  // ============================================================

  Future<void> _loadDashboard({bool refresh = false}) async {
    if (!mounted || _isRequestInProgress) return;

    _isRequestInProgress = true;

    final int requestId = ++_requestId;

    final bool hasExistingData = _hasDashboardData || _hasShipmentData;

    setState(() {
      _isLoading = !hasExistingData;
      _isRefreshing = hasExistingData;
      _dashboardError = null;
      _shipmentsError = null;
    });

    try {
      // All three independent reads can start together.
      // The saved user is optional and does not determine
      // whether the dashboard API succeeds.
      final Future<String?> userFuture = _fetchSavedUserName();

      final Future<_LogisticsApiResult> dashboardFuture = _fetchDashboard();

      final Future<_LogisticsApiResult> shipmentsFuture = _fetchShipments();

      final _LogisticsApiResult dashboardResponse = await dashboardFuture;

      final _LogisticsApiResult shipmentsResponse = await shipmentsFuture;

      final String? savedName = await userFuture;

      if (!mounted || requestId != _requestId) return;

      final bool dashboardSuccess = dashboardResponse.data?['success'] == true;

      final bool shipmentsSuccess = shipmentsResponse.data?['success'] == true;

      Map<String, dynamic>? newShipmentStats;
      Map<String, dynamic>? newRiderStats;
      Map<String, dynamic>? newHubStats;

      String? newCompanyName;
      dynamic newAlertsCount;

      List<ParcelModel>? newShipments;

      if (dashboardSuccess) {
        final Map<String, dynamic> data = _extractDashboardData(
          dashboardResponse.data!,
        );

        final Map<String, dynamic> logistic = _asMap(data['logistic']);

        final String company =
            logistic['company_name']?.toString().trim() ?? '';

        if (company.isNotEmpty) {
          newCompanyName = company;
        }

        newShipmentStats = _asMap(data['shipments']);
        newRiderStats = _asMap(data['riders']);
        newHubStats = _asMap(data['hubs']);

        newAlertsCount = data['alerts_count'];
      }

      if (shipmentsSuccess) {
        newShipments = _extractParcels(shipmentsResponse.data!);

        newShipments.sort(_compareParcels);
      }

      setState(() {
        if (savedName != null && savedName.isNotEmpty) {
          _savedUserName = savedName;
        }

        if (dashboardSuccess) {
          if (newCompanyName != null) {
            _companyName = newCompanyName;
          }

          _shipmentStats = newShipmentStats!;
          _riderStats = newRiderStats!;
          _hubStats = newHubStats!;
          _alertsCount = newAlertsCount;

          _hasDashboardData = true;
        }

        if (shipmentsSuccess) {
          _currentShipments = newShipments!;
          _hasShipmentData = true;
        }

        _dashboardError = dashboardSuccess
            ? null
            : dashboardResponse.error ??
                  'Unable to retrieve dashboard statistics.';

        _shipmentsError = shipmentsSuccess
            ? null
            : shipmentsResponse.error ?? 'Unable to retrieve shipment records.';

        _isLoading = false;
        _isRefreshing = false;
      });
    } catch (error) {
      debugPrint('Logistics home load error: $error');

      if (!mounted || requestId != _requestId) return;

      setState(() {
        _isLoading = false;
        _isRefreshing = false;

        _dashboardError = 'Unable to load logistics information. Please retry.';
      });
    } finally {
      _isRequestInProgress = false;
    }
  }

  Future<String?> _fetchSavedUserName() async {
    try {
      final Map<String, dynamic>? user = await AuthStorage.getUser().timeout(
        _requestTimeout,
      );

      final String name = user?['name']?.toString().trim() ?? '';

      return name.isEmpty ? null : name;
    } catch (error) {
      debugPrint('Logistics saved user error: $error');
      return null;
    }
  }

  Future<_LogisticsApiResult> _fetchDashboard() async {
    try {
      final Map<String, dynamic> response =
          await ApiService.getLogisticsDashboard().timeout(_requestTimeout);

      if (response['success'] == true) {
        return _LogisticsApiResult(data: response);
      }

      return _LogisticsApiResult(
        data: response,
        error: _responseMessage(
          response,
          'Unable to load logistics dashboard.',
        ),
      );
    } on TimeoutException {
      return const _LogisticsApiResult(
        error: 'The dashboard request timed out.',
      );
    } catch (error) {
      debugPrint('Logistics dashboard API error: $error');

      return const _LogisticsApiResult(
        error: 'Unable to connect to the dashboard API.',
      );
    }
  }

  Future<_LogisticsApiResult> _fetchShipments() async {
    try {
      final Map<String, dynamic> response =
          await ApiService.getLogisticsShipments(perPage: 100)
              .timeout(_requestTimeout);

      if (response['success'] == true) {
        return _LogisticsApiResult(data: response);
      }

      return _LogisticsApiResult(
        data: response,
        error: _responseMessage(response, 'Unable to load current shipments.'),
      );
    } on TimeoutException {
      return const _LogisticsApiResult(
        error: 'The shipment request timed out.',
      );
    } catch (error) {
      debugPrint('Logistics shipment API error: $error');

      return const _LogisticsApiResult(
        error: 'Unable to connect to the shipment API.',
      );
    }
  }

  String _responseMessage(Map<String, dynamic> response, String fallback) {
    final String message = response['message']?.toString().trim() ?? '';

    return message.isEmpty ? fallback : message;
  }

  // ============================================================
  // DASHBOARD RESPONSE HELPERS
  // ============================================================

  Map<String, dynamic> _extractDashboardData(Map<String, dynamic> result) {
    final dynamic data = result['data'];

    if (data is Map) {
      return Map<String, dynamic>.from(data);
    }

    return Map<String, dynamic>.from(result);
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }

    return <String, dynamic>{};
  }

  int? _toInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is num) return value.toInt();

    return int.tryParse(value.toString());
  }

  String _statValue(Map<String, dynamic> stats, String key) {
    if (!_hasDashboardData) return '—';

    return _toInt(stats[key])?.toString() ?? '—';
  }

  String _alertsValue() {
    if (!_hasDashboardData) return '—';

    return _toInt(_alertsCount)?.toString() ?? '—';
  }

  // ============================================================
  // SHIPMENT PARSING
  // ============================================================

  List<ParcelModel> _extractParcels(Map<String, dynamic> response) {
    final List<Map<String, dynamic>> records = _extractShipmentMaps(response);

    final List<ParcelModel> parcels = <ParcelModel>[];

    for (final Map<String, dynamic> record in records) {
      try {
        parcels.add(ParcelModel.fromJson(_normalizeShipment(record)));
      } catch (error) {
        debugPrint('Logistics parcel parsing error: $error');
      }
    }

    return parcels;
  }

  List<Map<String, dynamic>> _extractShipmentMaps(
    Map<String, dynamic> response,
  ) {
    for (final String key in ['data', 'shipments', 'parcels']) {
      if (response[key] is List) {
        return _toMapList(response[key]);
      }
    }

    final dynamic data = response['data'];

    if (data is Map) {
      for (final String key in ['data', 'shipments', 'parcels']) {
        if (data[key] is List) {
          return _toMapList(data[key]);
        }
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

    final dynamic nestedShipment = raw['shipment'];

    if (nestedShipment is Map) {
      data.addAll(Map<String, dynamic>.from(nestedShipment));
    }

    final Map<String, dynamic> order = _asMap(raw['order']);

    data['customer_name'] ??=
        order['customer_name'] ?? order['buyer_name'] ?? order['name'];

    data['customer_phone'] ??= order['customer_phone'] ?? order['phone'];

    data['order_date'] ??= order['order_date'] ?? order['created_at'];

    final Map<String, dynamic> rider = _asMap(data['rider']);

    data['rider_id'] ??= rider['id'];
    data['rider_name'] ??= rider['name'];

    final Map<String, dynamic> linehaulRider = _asMap(data['linehaul_rider']);

    data['linehaul_rider_id'] ??= linehaulRider['id'];
    data['linehaul_rider_name'] ??= linehaulRider['name'];

    final Map<String, dynamic> originHub = _asMap(data['origin_hub']);

    data['origin_hub_id'] ??= originHub['id'];
    data['origin_hub_name'] ??= originHub['name'];

    final Map<String, dynamic> destinationHub = _asMap(data['destination_hub']);

    data['destination_hub_id'] ??= destinationHub['id'];
    data['destination_hub_name'] ??= destinationHub['name'];

    final Map<String, dynamic> currentHub = _asMap(data['hub']);

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

    data['delivery_address'] = _resolveDeliveryAddress(data, raw, order);

    data['pickup_address'] = AddressFormatter.clean(
      data['pickup_address'] ?? data['seller_address'],
    );

    data['rider_name'] ??= '';

    data['status'] ??=
        data['shipment_status'] ?? data['delivery_status'] ?? 'pending';

    data['order_date'] ??= data['created_at'] ?? data['assigned_at'] ?? '';

    data['delivery_earnings'] ??= data['earnings'] ?? data['delivery_fee'] ?? 0;

    data['latitude'] ??= data['lat'];
    data['longitude'] ??= data['lng'] ?? data['lon'];

    return data;
  }

  String _resolveDeliveryAddress(
    Map<String, dynamic> shipment,
    Map<String, dynamic> raw,
    Map<String, dynamic> order,
  ) {
    for (final Map<String, dynamic> source in [shipment, raw, order]) {
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
        final String address = AddressFormatter.clean(source[key]);

        if (address.isNotEmpty) return address;
      }
    }

    return '';
  }

  // ============================================================
  // DATE SORTING
  // ============================================================

  DateTime? _activityDate(ParcelModel parcel) {
    final List<String> candidates = [
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

      final DateTime? parsed = DateFormatter.value(text);

      if (parsed != null) return parsed;
    }

    return null;
  }

  int _compareParcels(ParcelModel a, ParcelModel b) {
    final DateTime? aDate = _activityDate(a);
    final DateTime? bDate = _activityDate(b);

    if (aDate != null && bDate != null) {
      final int comparison = bDate.compareTo(aDate);
      if (comparison != 0) return comparison;
    } else if (aDate != null) {
      return -1;
    } else if (bDate != null) {
      return 1;
    }

    return b.id.compareTo(a.id);
  }

  // ============================================================
  // SHIPMENT SAMPLE COUNTS
  // ============================================================

  int get _requiresTransferCount =>
      _currentShipments.where((ParcelModel p) => p.requiresHubTransfer).length;

  int get _awaitingLinehaulCount => _currentShipments
      .where((ParcelModel p) => p.isLinehaulAwaitingAssignment)
      .length;

  int get _linehaulAssignedCount =>
      _currentShipments.where((ParcelModel p) => p.isLinehaulAssigned).length;

  int get _linehaulInTransitCount =>
      _currentShipments.where((ParcelModel p) => p.isLinehaulInTransit).length;

  int get _destinationHubCount =>
      _currentShipments.where((ParcelModel p) => p.isAtDestinationHub).length;

  String _sampleCount(int count) {
    return _hasShipmentData ? count.toString() : '—';
  }

  // ============================================================
  // UI HELPERS
  // ============================================================

  String get _firstName {
    final String name = _savedUserName.trim();

    if (name.isEmpty) return 'Logistics Staff';

    return name.split(RegExp(r'\s+')).first;
  }

  String _formatStatus(String value) {
    final String normalized = value
        .trim()
        .replaceAll('-', '_')
        .replaceAll(' ', '_');

    if (normalized.isEmpty) return 'Not available';

    return normalized
        .split('_')
        .where((String word) => word.isNotEmpty)
        .map(
          (String word) =>
              '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  String _routeText(ParcelModel parcel) {
    final String origin = parcel.originHubName.trim();
    final String destination = parcel.destinationHubName.trim();

    if (origin.isNotEmpty && destination.isNotEmpty) {
      return '$origin → $destination';
    }

    if (origin.isNotEmpty) return 'Origin: $origin';

    if (destination.isNotEmpty) {
      return 'Destination: $destination';
    }

    return '';
  }

  void _openScanner() {
    if (widget.onOpenScanner != null) {
      widget.onOpenScanner!();
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

  Future<void> _openParcel(ParcelModel parcel) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return ParcelInformation(parcel: parcel);
        },
      ),
    );

    if (!mounted) return;

    await _loadDashboard(refresh: true);
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.sizeOf(context).width;
    final double padding = width < 380 ? 14 : 18;

    final bool hasAnyData = _hasDashboardData || _hasShipmentData;

    return Scaffold(
      backgroundColor: LogisticsColors.background,
      body: SafeArea(
        child: RefreshIndicator(
          color: LogisticsColors.primary,
          onRefresh: () => _loadDashboard(refresh: true),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: EdgeInsets.fromLTRB(padding, 18, padding, 30),
            children: [
              _header(),

              const SizedBox(height: 18),

              if (_isLoading && !hasAnyData)
                _buildDashboardSkeleton()
              else if (!hasAnyData)
                AppErrorState(
                  title: 'Unable to Load Logistics Dashboard',
                  message:
                      _dashboardError ??
                      _shipmentsError ??
                      'Unable to retrieve logistics information.',
                  color: LogisticsColors.primary,
                  onRetry: _loadDashboard,
                )
              else ...[
                if (_isRefreshing) ...[
                  const LinearProgressIndicator(
                    color: LogisticsColors.primary,
                    minHeight: 2,
                  ),
                  const SizedBox(height: 16),
                ],

                if (_dashboardError != null) ...[
                  _warningBanner('Dashboard statistics', _dashboardError!),
                  const SizedBox(height: 12),
                ],

                if (_shipmentsError != null) ...[
                  _warningBanner('Shipment records', _shipmentsError!),
                  const SizedBox(height: 12),
                ],

                const SizedBox(height: 12),

                const Text(
                  'Parcel Overview',
                  style: LogisticsStyle.sectionTitle,
                ),

                const SizedBox(height: 6),

                const Text(
                  'Official Laravel dashboard statistics',
                  style: LogisticsStyle.small,
                ),

                const SizedBox(height: 14),

                if (_hasDashboardData)
                  _parcelOverview()
                else
                  const AppSkeleton(
                    type: AppSkeletonType.dashboard,
                    itemCount: 4,
                    color: LogisticsColors.primary,
                  ),

                const SizedBox(height: 26),

                _sortingCenterCard(),

                const SizedBox(height: 18),

                _hubTransferCard(),

                const SizedBox(height: 18),

                _operationsCard(),

                const SizedBox(height: 26),

                _scannerCard(),

                const SizedBox(height: 26),

                _recentShipmentsSection(),
              ],
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // DASHBOARD SKELETON
  // ============================================================

  Widget _buildDashboardSkeleton() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Parcel Overview', style: LogisticsStyle.sectionTitle),

        const SizedBox(height: 6),

        const Text(
          'Loading logistics statistics...',
          style: LogisticsStyle.small,
        ),

        const SizedBox(height: 14),

        const AppSkeleton(
          type: AppSkeletonType.dashboard,
          itemCount: 8,
          color: LogisticsColors.primary,
        ),

        const SizedBox(height: 26),

        const Text('Recent Shipments', style: LogisticsStyle.sectionTitle),

        const SizedBox(height: 12),

        const AppSkeleton(
          type: AppSkeletonType.parcel,
          itemCount: 2,
          color: LogisticsColors.primary,
        ),
      ],
    );
  }

  // ============================================================
  // HEADER
  // ============================================================

  Widget _header() {
    return Row(
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

        IconButton(
          tooltip: 'Refresh logistics dashboard',
          onPressed: _isRequestInProgress
              ? null
              : () {
                  _loadDashboard(refresh: true);
                },
          icon: _isRefreshing
              ? const SizedBox(
                  height: 20,
                  width: 20,
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

        Container(
          height: 48,
          width: 48,
          decoration: BoxDecoration(
            color: LogisticsColors.lightPurple,
            borderRadius: BorderRadius.circular(15),
          ),
          child: const Icon(
            Icons.warehouse_outlined,
            size: 25,
            color: LogisticsColors.primary,
          ),
        ),
      ],
    );
  }

  // ============================================================
  // ERROR WARNING
  // ============================================================

  Widget _warningBanner(String title, String message) {
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
                Text('$title unavailable', style: LogisticsStyle.cardTitle),

                const SizedBox(height: 3),

                Text(message, style: LogisticsStyle.small),
              ],
            ),
          ),

          TextButton(
            onPressed: _isRequestInProgress
                ? null
                : () => _loadDashboard(refresh: true),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PARCEL OVERVIEW
  // ============================================================

  Widget _parcelOverview() {
    final List<_OverviewItem> items = [
      _OverviewItem(
        title: 'Total Parcels',
        value: _statValue(_shipmentStats, 'total'),
        icon: Icons.inventory_2_outlined,
      ),
      _OverviewItem(
        title: 'Pending',
        value: _statValue(_shipmentStats, 'pending'),
        icon: Icons.pending_actions_rounded,
      ),
      _OverviewItem(
        title: 'In Transit',
        value: _statValue(_shipmentStats, 'in_transit'),
        icon: Icons.local_shipping_outlined,
      ),
      _OverviewItem(
        title: 'Delivered',
        value: _statValue(_shipmentStats, 'delivered'),
        icon: Icons.check_circle_outline_rounded,
      ),
      _OverviewItem(
        title: 'Preparing',
        value: _statValue(_shipmentStats, 'preparing'),
        icon: Icons.inventory_outlined,
      ),
      _OverviewItem(
        title: 'Out for Delivery',
        value: _statValue(_shipmentStats, 'out_for_delivery'),
        icon: Icons.delivery_dining_rounded,
      ),
      _OverviewItem(
        title: 'Cancelled',
        value: _statValue(_shipmentStats, 'cancelled'),
        icon: Icons.cancel_outlined,
      ),
      _OverviewItem(
        title: 'Alerts',
        value: _alertsValue(),
        icon: Icons.notifications_active_outlined,
      ),
    ];

    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints constraints) {
        final int columns = constraints.maxWidth < 300 ? 1 : 2;

        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: items.length,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: columns == 1 ? 2.4 : 1.25,
          ),
          itemBuilder: (BuildContext context, int index) {
            final _OverviewItem item = items[index];

            return _overviewCard(
              title: item.title,
              value: item.value,
              icon: item.icon,
            );
          },
        );
      },
    );
  }

  Widget _overviewCard({
    required String title,
    required String value,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: LogisticsColors.primary, size: 21),
          ),

          const Spacer(),

          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: LogisticsColors.primary,
              fontSize: 23,
              fontWeight: FontWeight.w800,
            ),
          ),

          const SizedBox(height: 3),

          Text(
            title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: LogisticsStyle.small,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // SORTING CENTER
  // ============================================================

  Widget _sortingCenterCard() {
    return _sectionCard(
      title: 'Sorting Center',
      subtitle: 'Parcel processing statistics from Laravel',
      icon: Icons.warehouse_outlined,
      children: [
        _activityRow(
          title: 'Received',
          value: _statValue(_shipmentStats, 'received'),
          icon: Icons.move_to_inbox_rounded,
        ),
        _activityRow(
          title: 'Scanned',
          value: _statValue(_shipmentStats, 'scanned'),
          icon: Icons.qr_code_scanner_rounded,
        ),
        _activityRow(
          title: 'Sorted',
          value: _statValue(_shipmentStats, 'sorted'),
          icon: Icons.sort_rounded,
        ),
        _activityRow(
          title: 'Staged',
          value: _statValue(_shipmentStats, 'staged'),
          icon: Icons.inventory_rounded,
        ),
      ],
    );
  }

  // ============================================================
  // HUB TRANSFER
  // ============================================================

  Widget _hubTransferCard() {
    return _sectionCard(
      title: 'Hub Transfer & Line Haul',
      subtitle: 'Counts from loaded shipments (up to 100 records)',
      icon: Icons.multiple_stop_rounded,
      children: [
        _activityRow(
          title: 'Requires Hub Transfer',
          value: _sampleCount(_requiresTransferCount),
          icon: Icons.route_outlined,
        ),
        _activityRow(
          title: 'Awaiting Line-Haul Rider',
          value: _sampleCount(_awaitingLinehaulCount),
          icon: Icons.person_search_outlined,
        ),
        _activityRow(
          title: 'Line-Haul Assigned',
          value: _sampleCount(_linehaulAssignedCount),
          icon: Icons.assignment_ind_outlined,
        ),
        _activityRow(
          title: 'Line-Haul In Transit',
          value: _sampleCount(_linehaulInTransitCount),
          icon: Icons.local_shipping_outlined,
        ),
        _activityRow(
          title: 'At Destination Hub',
          value: _sampleCount(_destinationHubCount),
          icon: Icons.location_city_outlined,
        ),
      ],
    );
  }

  // ============================================================
  // OPERATIONS
  // ============================================================

  Widget _operationsCard() {
    return _sectionCard(
      title: 'Operations',
      subtitle: 'Rider and hub statistics',
      icon: Icons.hub_outlined,
      children: [
        _activityRow(
          title: 'Total Riders',
          value: _statValue(_riderStats, 'total'),
          icon: Icons.people_outline_rounded,
        ),
        _activityRow(
          title: 'Active Riders',
          value: _statValue(_riderStats, 'active'),
          icon: Icons.delivery_dining_rounded,
        ),
        if (_riderStats.containsKey('available'))
          _activityRow(
            title: 'Available Riders',
            value: _statValue(_riderStats, 'available'),
            icon: Icons.person_pin_circle_outlined,
          ),
        if (_riderStats.containsKey('on_delivery'))
          _activityRow(
            title: 'Riders on Delivery',
            value: _statValue(_riderStats, 'on_delivery'),
            icon: Icons.local_shipping_outlined,
          ),
        _activityRow(
          title: 'Total Hubs',
          value: _statValue(_hubStats, 'total'),
          icon: Icons.hub_outlined,
        ),
        _activityRow(
          title: 'Active Hubs',
          value: _statValue(_hubStats, 'active'),
          icon: Icons.warehouse_outlined,
        ),
      ],
    );
  }

  // ============================================================
  // SHARED STATISTICS WIDGETS
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
              Icon(icon, color: LogisticsColors.primary),
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

  Widget _activityRow({
    required IconData icon,
    required String title,
    required String value,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 13),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(icon, color: LogisticsColors.primary, size: 21),
          ),

          const SizedBox(width: 12),

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

  // ============================================================
  // SCANNER SHORTCUT
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
              colors: [LogisticsColors.primary, LogisticsColors.primaryDark],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(22),
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
                      'Verify parcel QR codes, sorting '
                      'status, and hub routes.',
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
          children: [
            const Expanded(
              child: Text(
                'Recent Shipments',
                style: LogisticsStyle.sectionTitle,
              ),
            ),

            if (_hasShipmentData)
              Text(
                '${_currentShipments.length} loaded',
                style: LogisticsStyle.small,
              ),
          ],
        ),

        const SizedBox(height: 5),

        const Text(
          'Latest records from the shipment API',
          style: LogisticsStyle.small,
        ),

        const SizedBox(height: 12),

        if (!_hasShipmentData && _isRequestInProgress)
          const AppSkeleton(
            type: AppSkeletonType.parcel,
            itemCount: 3,
            color: LogisticsColors.primary,
          )
        else if (!_hasShipmentData)
          AppErrorState(
            title: 'Shipments Unavailable',
            message: _shipmentsError ?? 'Unable to load shipment records.',
            color: LogisticsColors.primary,
            compact: true,
            onRetry: () => _loadDashboard(refresh: true),
          )
        else if (_currentShipments.isEmpty)
          const AppEmptyState(
            title: 'No Recent Shipments',
            message: 'Shipment records will appear here when available.',
            icon: Icons.inventory_2_outlined,
            color: LogisticsColors.primary,
            compact: true,
          )
        else
          ..._currentShipments
              .take(5)
              .map((ParcelModel parcel) => _recentShipmentCard(parcel)),
      ],
    );
  }

  Widget _recentShipmentCard(ParcelModel parcel) {
    final String route = _routeText(parcel);

    final String tracking = parcel.trackingNumber.trim().isNotEmpty
        ? parcel.trackingNumber
        : 'Tracking unavailable';

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => _openParcel(parcel),
          borderRadius: BorderRadius.circular(16),
          child: Ink(
            width: double.infinity,
            padding: const EdgeInsets.all(15),
            decoration: LogisticsStyle.card,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 44,
                      height: 44,
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
                        size: 23,
                      ),
                    ),

                    const SizedBox(width: 12),

                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            tracking,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: LogisticsStyle.cardTitle,
                          ),

                          const SizedBox(height: 5),

                          Text(
                            parcel.customerName.trim().isNotEmpty
                                ? parcel.customerName
                                : 'Customer unavailable',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: LogisticsStyle.small,
                          ),
                        ],
                      ),
                    ),

                    const Icon(
                      Icons.chevron_right_rounded,
                      color: LogisticsColors.primary,
                    ),
                  ],
                ),

                const SizedBox(height: 12),

                StatusChip(status: parcel.status),

                if (parcel.sortingStatus.trim().isNotEmpty) ...[
                  const SizedBox(height: 8),
                  AppInfoRow(
                    label: 'Sorting Status',
                    value: _formatStatus(parcel.sortingStatus),
                    icon: Icons.sort_rounded,
                    iconColor: LogisticsColors.primary,
                    compact: true,
                  ),
                ],

                if (route.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  AppInfoRow(
                    label: 'Hub Route',
                    value: route,
                    icon: Icons.route_outlined,
                    iconColor: LogisticsColors.primary,
                    compact: true,
                  ),
                ],

                if (parcel.linehaulStatus.trim().isNotEmpty) ...[
                  const SizedBox(height: 8),
                  AppInfoRow(
                    label: 'Line-Haul Status',
                    value: _formatStatus(parcel.linehaulStatus),
                    icon: Icons.multiple_stop_rounded,
                    iconColor: LogisticsColors.primary,
                    compact: true,
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _LogisticsApiResult {
  final Map<String, dynamic>? data;
  final String? error;

  const _LogisticsApiResult({this.data, this.error});
}

class _OverviewItem {
  final String title;
  final String value;
  final IconData icon;

  const _OverviewItem({
    required this.title,
    required this.value,
    required this.icon,
  });
}
