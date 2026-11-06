import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../services/api_services.dart';
import '../../services/auth_storage.dart';
import '../../styles/rider_style.dart';
import '../../utils/address_formatter.dart';
import '../../utils/date_formatter.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_skeleton.dart';
import '../../widgets/status_chip.dart';

import 'available_pickups.dart';

class RiderHome extends StatefulWidget {
  final ValueChanged<int>? onNavigateTab;

  const RiderHome({super.key, this.onNavigateTab});

  @override
  State<RiderHome> createState() => _RiderHomeState();
}

class _RiderHomeState extends State<RiderHome> {
  static const Duration _requestTimeout = Duration(seconds: 25);

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _requestInProgress = false;

  bool _hasDashboardData = false;
  bool _hasTaskData = false;

  bool? _localAvailabilityOverride;

  String? _errorMessage;
  String? _taskErrorMessage;

  String _savedUserName = 'Rider';

  Map<String, dynamic> _savedUser = {};
  Map<String, dynamic> _rider = {};
  Map<String, dynamic> _overview = {};

  List<Map<String, dynamic>> _tasks = [];

  int _requestId = 0;

  bool get _hasAnyData => _hasDashboardData || _hasTaskData;

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
  // DASHBOARD API
  // ============================================================

  Future<void> _loadDashboard({bool refresh = false}) async {
    if (!mounted || _requestInProgress) return;

    _requestInProgress = true;
    final int requestId = ++_requestId;

    setState(() {
      _isLoading = !_hasAnyData;
      _isRefreshing = _hasAnyData;
      _errorMessage = null;
      _taskErrorMessage = null;
    });

    try {
      final Future<Map<String, dynamic>?> userFuture = _fetchSavedUser();

      final Future<_RiderApiResult> dashboardFuture = _fetchDashboard();

      final Future<_RiderApiResult> deliveriesFuture = _fetchDeliveries();

      final List<_RiderApiResult> responses =
          await Future.wait<_RiderApiResult>([
            dashboardFuture,
            deliveriesFuture,
          ]);

      final Map<String, dynamic>? savedUser = await userFuture;

      if (!mounted || requestId != _requestId) return;

      final _RiderApiResult dashboardResponse = responses[0];
      final _RiderApiResult deliveriesResponse = responses[1];

      final bool dashboardSuccess = dashboardResponse.data?['success'] == true;

      final bool deliveriesSuccess =
          deliveriesResponse.data?['success'] == true;

      final Map<String, dynamic> riderData = dashboardSuccess
          ? _extractRiderData(dashboardResponse.data!)
          : {};

      final Map<String, dynamic> overviewData = dashboardSuccess
          ? _extractOverviewData(dashboardResponse.data!)
          : {};

      final List<Map<String, dynamic>> dashboardTasks = dashboardSuccess
          ? _extractDashboardTasks(dashboardResponse.data!)
          : [];

      final List<Map<String, dynamic>> deliveryTasks = deliveriesSuccess
          ? _extractDeliveryTasks(deliveriesResponse.data!)
          : [];

      // The delivery endpoint is the preferred source of
      // currently assigned shipments.
      final List<Map<String, dynamic>> finalTasks = deliveriesSuccess
          ? deliveryTasks
          : dashboardSuccess
          ? dashboardTasks
          : _tasks;

      final List<Map<String, dynamic>> sortedTasks =
          List<Map<String, dynamic>>.from(finalTasks)..sort(_compareTasks);

      final Map<String, dynamic> computedOverview = _buildOverviewFromTasks(
        sortedTasks,
      );

      if (!mounted || requestId != _requestId) return;

      setState(() {
        if (savedUser != null) {
          _savedUser = savedUser;

          final String name = savedUser['name']?.toString().trim() ?? '';

          if (name.isNotEmpty) {
            _savedUserName = name;
          }
        }

        if (dashboardSuccess) {
          _hasDashboardData = true;

          _overview = {
            ...overviewData,
            if (deliveriesSuccess || dashboardTasks.isNotEmpty)
              ...computedOverview,
          };
        } else if (deliveriesSuccess) {
          // These are counts from loaded assignments,
          // not official full-database statistics.
          _overview = computedOverview;
        }

        if (riderData.isNotEmpty) {
          _rider = riderData;
        } else if (_rider.isEmpty) {
          _rider = _extractSavedRider();
        }

        if (dashboardSuccess || deliveriesSuccess) {
          _tasks = sortedTasks;
          _hasTaskData = true;
        }

        _errorMessage = dashboardSuccess
            ? null
            : dashboardResponse.error ??
                  'Dashboard statistics are temporarily unavailable.';

        _taskErrorMessage = deliveriesSuccess
            ? null
            : deliveriesResponse.error ??
                  'Unable to refresh delivery assignments.';

        _isLoading = false;
        _isRefreshing = false;
      });
    } catch (error) {
      debugPrint('Rider dashboard load error: $error');

      if (!mounted || requestId != _requestId) return;

      setState(() {
        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = 'Unable to load rider dashboard. Please retry.';
      });
    } finally {
      _requestInProgress = false;
    }
  }

  Future<Map<String, dynamic>?> _fetchSavedUser() async {
    try {
      return await AuthStorage.getUser().timeout(_requestTimeout);
    } catch (error) {
      debugPrint('Rider saved user error: $error');
      return null;
    }
  }

  Future<_RiderApiResult> _fetchDashboard() async {
    try {
      final Map<String, dynamic> response = await ApiService.getRiderDashboard()
          .timeout(_requestTimeout);

      if (response['success'] == true) {
        return _RiderApiResult(data: response);
      }

      return _RiderApiResult(
        data: response,
        error: _apiMessage(response, 'Unable to load rider dashboard.'),
      );
    } on TimeoutException {
      return const _RiderApiResult(error: 'Dashboard request timed out.');
    } catch (error) {
      debugPrint('Rider dashboard API error: $error');

      return const _RiderApiResult(
        error: 'Unable to connect to the rider dashboard API.',
      );
    }
  }

  Future<_RiderApiResult> _fetchDeliveries() async {
    try {
      final Map<String, dynamic> response =
          await ApiService.getRiderDeliveries().timeout(_requestTimeout);

      if (response['success'] == true) {
        return _RiderApiResult(data: response);
      }

      return _RiderApiResult(
        data: response,
        error: _apiMessage(response, 'Unable to load rider deliveries.'),
      );
    } on TimeoutException {
      return const _RiderApiResult(
        error: 'Delivery assignments request timed out.',
      );
    } catch (error) {
      debugPrint('Rider deliveries API error: $error');

      return const _RiderApiResult(
        error: 'Unable to connect to the rider deliveries API.',
      );
    }
  }

  String _apiMessage(Map<String, dynamic> response, String fallback) {
    final String message = response['message']?.toString().trim() ?? '';

    return message.isEmpty ? fallback : message;
  }

  // ============================================================
  // RIDER DATA PARSING
  // ============================================================

  Map<String, dynamic> _extractRiderData(Map<String, dynamic> result) {
    if (result['rider'] is Map) {
      return Map<String, dynamic>.from(result['rider']);
    }

    final dynamic data = result['data'];

    if (data is Map) {
      if (data['rider'] is Map) {
        return Map<String, dynamic>.from(data['rider']);
      }

      final Map<String, dynamic> parsed = Map<String, dynamic>.from(data);

      if (_looksLikeRider(parsed)) {
        return parsed;
      }
    }

    return {};
  }

  Map<String, dynamic> _extractSavedRider() {
    if (_savedUser['rider'] is Map) {
      return Map<String, dynamic>.from(_savedUser['rider']);
    }

    final dynamic profile = _savedUser['profile'];

    if (profile is Map && profile['rider'] is Map) {
      return Map<String, dynamic>.from(profile['rider']);
    }

    return {
      if (_savedUser['name'] != null) 'name': _savedUser['name'],
      if (_savedUser['vehicle_type'] != null)
        'vehicle_type': _savedUser['vehicle_type'],
      if (_savedUser['availability_status'] != null)
        'availability_status': _savedUser['availability_status'],
      if (_savedUser['status'] != null) 'status': _savedUser['status'],
    };
  }

  bool _looksLikeRider(Map<String, dynamic> data) {
    return data.containsKey('vehicle_type') ||
        data.containsKey('availability_status') ||
        data.containsKey('license_number') ||
        data.containsKey('driver_license_number');
  }

  Map<String, dynamic> _extractOverviewData(Map<String, dynamic> result) {
    if (result['overview'] is Map) {
      return Map<String, dynamic>.from(result['overview']);
    }

    final dynamic data = result['data'];

    if (data is Map) {
      for (final String key in ['overview', 'statistics', 'stats']) {
        if (data[key] is Map) {
          return Map<String, dynamic>.from(data[key]);
        }
      }
    }

    return {};
  }

  // ============================================================
  // ASSIGNMENT PARSING
  // ============================================================

  List<Map<String, dynamic>> _extractDashboardTasks(
    Map<String, dynamic> result,
  ) {
    if (result['tasks'] is List) {
      return _toMapList(result['tasks']);
    }

    final dynamic data = result['data'];

    if (data is Map) {
      for (final String key in [
        'tasks',
        'deliveries',
        'orders',
        'shipments',
        'recent_tasks',
      ]) {
        if (data[key] is List) {
          return _toMapList(data[key]);
        }
      }
    }

    return [];
  }

  List<Map<String, dynamic>> _extractDeliveryTasks(
    Map<String, dynamic> result,
  ) {
    for (final String key in ['data', 'deliveries', 'orders', 'shipments']) {
      if (result[key] is List) {
        return _toMapList(result[key]);
      }
    }

    final dynamic data = result['data'];

    if (data is Map) {
      for (final String key in ['data', 'deliveries', 'orders', 'shipments']) {
        if (data[key] is List) {
          return _toMapList(data[key]);
        }
      }
    }

    return [];
  }

  List<Map<String, dynamic>> _toMapList(dynamic value) {
    if (value is! List) return [];

    return value
        .whereType<Map>()
        .map((Map item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Map<String, dynamic> _normalizeTask(Map<String, dynamic> raw) {
    final Map<String, dynamic> map = Map<String, dynamic>.from(raw);

    if (raw['shipment'] is Map) {
      map.addAll(Map<String, dynamic>.from(raw['shipment']));
    }

    if (raw['order'] is Map) {
      final Map<String, dynamic> order = Map<String, dynamic>.from(
        raw['order'],
      );

      map['customer_name'] ??= order['customer_name'] ?? order['buyer_name'];

      map['customer_phone'] ??= order['customer_phone'] ?? order['phone'];

      map['delivery_address'] ??= order['delivery_address'] ?? order['address'];

      map['order_date'] ??= order['created_at'];
    }

    map['id'] ??= map['shipment_id'] ?? map['order_id'] ?? 0;

    map['shipment_id'] ??= map['id'];

    map['tracking_number'] ??=
        map['tracking_code'] ?? map['waybill_number'] ?? '';

    map['customer_name'] ??= map['recipient_name'] ?? map['buyer_name'] ?? '';

    map['customer_phone'] ??= map['phone'] ?? map['recipient_phone'] ?? '';

    map['delivery_address'] ??= map['address'] ?? map['shipping_address'] ?? '';

    map['pickup_address'] ??= map['seller_address'] ?? '';

    map['rider_name'] ??= map['driver_name'] ?? '';
    map['rider_id'] ??= map['driver_id'];

    map['status'] ??= map['shipment_status'] ?? map['delivery_status'] ?? '';

    map['order_date'] ??= map['created_at'] ?? map['assigned_at'] ?? '';

    map['delivery_earnings'] ??= map['earnings'] ?? map['delivery_fee'] ?? 0;

    map['latitude'] ??= map['rider_latitude'];
    map['longitude'] ??= map['rider_longitude'];

    return map;
  }

  Map<String, dynamic> _buildOverviewFromTasks(
    List<Map<String, dynamic>> tasks,
  ) {
    int assigned = 0;
    int active = 0;
    int completed = 0;
    int pending = 0;

    int sellerPickup = 0;
    int linehaul = 0;
    int finalMile = 0;

    for (final Map<String, dynamic> raw in tasks) {
      try {
        final ParcelModel parcel = ParcelModel.fromJson(_normalizeTask(raw));

        if (parcel.isSellerPickupAssignment) {
          sellerPickup++;
        }

        if (parcel.isLinehaulAssignment) {
          linehaul++;
        }

        if (parcel.isFinalMileAssignment) {
          finalMile++;
        }

        final String status = parcel.normalizedStatus;

        if (parcel.isDelivered || status == 'completed') {
          completed++;
          continue;
        }

        if (parcel.isFailed || status == 'cancelled' || status == 'canceled') {
          continue;
        }

        assigned++;
        active++;

        if ({
          'pending',
          'assigned',
          'ready_for_pickup',
          'staged',
          'at_sorting_center',
        }.contains(status)) {
          pending++;
        }
      } catch (error) {
        debugPrint('Rider task stats parse error: $error');
      }
    }

    return {
      'assigned': assigned,
      'active': active,
      'completed': completed,
      'pending': pending,
      'seller_pickup': sellerPickup,
      'linehaul': linehaul,
      'final_mile': finalMile,
    };
  }

  DateTime? _taskActivityDate(Map<String, dynamic> task) {
    for (final dynamic value in [
      task['updated_at'],
      task['linehaul_started_at'],
      task['linehaul_assigned_at'],
      task['assigned_at'],
      task['picked_up_at'],
      task['created_at'],
      task['order_date'],
    ]) {
      final String text = value?.toString().trim() ?? '';

      if (text.isEmpty || text.toLowerCase() == 'null') {
        continue;
      }

      final DateTime? date = DateFormatter.value(text);

      if (date != null) return date;
    }

    return null;
  }

  int _compareTasks(Map<String, dynamic> a, Map<String, dynamic> b) {
    final DateTime? aDate = _taskActivityDate(a);
    final DateTime? bDate = _taskActivityDate(b);

    if (aDate != null && bDate != null) {
      final int result = bDate.compareTo(aDate);

      if (result != 0) return result;
    } else if (aDate != null) {
      return -1;
    } else if (bDate != null) {
      return 1;
    }

    final int aId = int.tryParse(a['id']?.toString() ?? '') ?? 0;
    final int bId = int.tryParse(b['id']?.toString() ?? '') ?? 0;

    return bId.compareTo(aId);
  }

  // ============================================================
  // RIDER INFORMATION
  // ============================================================

  String get _riderName {
    for (final dynamic value in [
      _rider['name'],
      _rider['full_name'],
      _savedUser['name'],
    ]) {
      final String text = value?.toString().trim() ?? '';

      if (text.isNotEmpty) return text;
    }

    final String firstName = _rider['first_name']?.toString().trim() ?? '';

    final String lastName = _rider['last_name']?.toString().trim() ?? '';

    final String combined = [
      firstName,
      lastName,
    ].where((String name) => name.isNotEmpty).join(' ');

    return combined.isNotEmpty ? combined : _savedUserName;
  }

  String get _firstName {
    final String name = _riderName.trim();

    return name.isEmpty ? 'Rider' : name.split(RegExp(r'\s+')).first;
  }

  String get _vehicleType {
    for (final dynamic value in [
      _rider['vehicle_type'],
      _rider['vehicle'],
      _savedUser['vehicle_type'],
    ]) {
      final String text = value?.toString().trim() ?? '';

      if (text.isNotEmpty) {
        return _formatStatus(text);
      }
    }

    return 'Vehicle not specified';
  }

  bool? get _serverAvailability {
    final dynamic raw = _rider['is_available'] ?? _rider['available'];

    if (raw is bool) return raw;

    if (raw != null) {
      final String text = raw.toString().trim().toLowerCase();

      if (['true', '1', 'yes'].contains(text)) {
        return true;
      }

      if (['false', '0', 'no'].contains(text)) {
        return false;
      }
    }

    final String status =
        (_rider['availability_status'] ??
                _rider['availability'] ??
                _savedUser['availability_status'] ??
                '')
            .toString()
            .trim()
            .toLowerCase();

    if (['available', 'online', 'active'].contains(status)) {
      return true;
    }

    if (['unavailable', 'offline', 'inactive'].contains(status)) {
      return false;
    }

    return null;
  }

  bool get _isAvailable =>
      _localAvailabilityOverride ?? _serverAvailability ?? false;

  String get _availabilityStatus {
    if (_localAvailabilityOverride != null) {
      return _isAvailable ? 'Available (Local)' : 'Unavailable (Local)';
    }

    if (_serverAvailability == null) {
      return 'Not available';
    }

    return _isAvailable ? 'Available' : 'Unavailable';
  }

  void _toggleAvailability() {
    setState(() {
      _localAvailabilityOverride = !_isAvailable;
    });

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(
          behavior: SnackBarBehavior.floating,
          content: Text(
            'Availability was changed on this screen only. '
            'It has not been saved to the BiliHub server.',
          ),
        ),
      );
  }

  // ============================================================
  // NAVIGATION
  // ============================================================

  void _goToTab(int index) {
    widget.onNavigateTab?.call(index);
  }

  Future<void> _openPickupOffers() async {
    if (!mounted) return;

    bool pickupAccepted = false;

    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return AvailablePickups(
            onPickupAccepted: () {
              pickupAccepted = true;
            },
          );
        },
      ),
    );

    if (!mounted) return;

    // Reload after returning from the offer inbox so
    // accepted pickups can appear in Latest Assignments.
    await _loadDashboard(refresh: true);

    if (!mounted || !pickupAccepted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          content: const Text(
            'Pickup accepted! Check My Deliveries '
            'for your assigned parcel.',
          ),
          action: SnackBarAction(
            label: 'DELIVERIES',
            onPressed: () => _goToTab(1),
          ),
        ),
      );
  }

  // ============================================================
  // DISPLAY HELPERS
  // ============================================================

  String _overviewValue(List<String> keys) {
    for (final String key in keys) {
      final dynamic value = _overview[key];

      if (value != null) {
        return value.toString();
      }
    }

    return '—';
  }

  String _normalizeStatus(String value) {
    return value.trim().toLowerCase().replaceAll(' ', '_').replaceAll('-', '_');
  }

  String _formatStatus(String value) {
    final String normalized = value
        .trim()
        .replaceAll('_', ' ')
        .replaceAll('-', ' ');

    if (normalized.isEmpty) return 'Unknown';

    return normalized
        .split(RegExp(r'\s+'))
        .where((String word) => word.isNotEmpty)
        .map(
          (String word) =>
              '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  String _firstNonEmpty(List<dynamic> values, {String fallback = ''}) {
    for (final dynamic value in values) {
      final String text = value?.toString().trim() ?? '';

      if (text.isNotEmpty && text.toLowerCase() != 'null') {
        return text;
      }
    }

    return fallback;
  }

  String _displayValue(String value, {String fallback = 'Not available'}) {
    final String cleaned = value.trim();

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return fallback;
    }

    return cleaned;
  }

  // ============================================================
  // MAIN UI
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double screenWidth = MediaQuery.sizeOf(context).width;
    final double horizontalPadding = screenWidth < 380 ? 14 : 18;

    return Scaffold(
      backgroundColor: RiderColors.background,
      body: SafeArea(
        child: RefreshIndicator(
          color: RiderColors.primary,
          onRefresh: () => _loadDashboard(refresh: true),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: EdgeInsets.fromLTRB(
              horizontalPadding,
              16,
              horizontalPadding,
              30,
            ),
            children: [
              _header(),

              const SizedBox(height: 18),

              // Always accessible, even when dashboard
              // statistics fail to load.
              _pickupOffersShortcut(),

              const SizedBox(height: 18),

              if (_isLoading && !_hasAnyData)
                _buildDashboardSkeleton()
              else if (!_hasAnyData)
                AppErrorState(
                  title: 'Unable to Load Rider Dashboard',
                  message:
                      _errorMessage ??
                      _taskErrorMessage ??
                      'Please check your connection and retry.',
                  color: RiderColors.primary,
                  onRetry: () => _loadDashboard(),
                )
              else ...[
                if (_isRefreshing) ...[
                  const LinearProgressIndicator(
                    color: RiderColors.primary,
                    minHeight: 2,
                  ),
                  const SizedBox(height: 14),
                ],

                if (_errorMessage != null) ...[
                  _errorBanner(_errorMessage!),
                  const SizedBox(height: 14),
                ],

                _availabilityCard(),

                const SizedBox(height: 14),

                _riderInfoCard(),

                const SizedBox(height: 20),

                _deliveryOverview(screenWidth),

                const SizedBox(height: 20),

                _assignmentOverview(screenWidth),

                const SizedBox(height: 20),

                _assignedShipments(),
              ],
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // PICKUP OFFERS SHORTCUT
  // ============================================================

  Widget _pickupOffersShortcut() {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: _openPickupOffers,
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          width: double.infinity,
          padding: const EdgeInsets.all(17),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [RiderColors.primary, RiderColors.primaryDark],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(18),
          ),
          child: Row(
            children: [
              Container(
                height: 50,
                width: 50,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.notifications_active_outlined,
                  color: Colors.white,
                  size: 27,
                ),
              ),

              const SizedBox(width: 13),

              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Pickup Offers',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),

                    SizedBox(height: 5),

                    Text(
                      'View, accept or decline new '
                      'seller pickup requests.',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(width: 8),

              const Icon(
                Icons.chevron_right_rounded,
                color: Colors.white,
                size: 24,
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // SKELETON LOADING
  // ============================================================

  Widget _buildDashboardSkeleton() {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Delivery Overview', style: RiderStyle.sectionTitle),
        SizedBox(height: 12),
        AppSkeleton(
          type: AppSkeletonType.dashboard,
          itemCount: 4,
          color: RiderColors.primary,
        ),
        SizedBox(height: 24),
        Text('Assignment Types', style: RiderStyle.sectionTitle),
        SizedBox(height: 12),
        AppSkeleton(
          type: AppSkeletonType.dashboard,
          itemCount: 2,
          color: RiderColors.primary,
        ),
        SizedBox(height: 24),
        Text('Latest Assignments', style: RiderStyle.sectionTitle),
        SizedBox(height: 12),
        AppSkeleton(
          type: AppSkeletonType.parcel,
          itemCount: 2,
          color: RiderColors.primary,
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
              const Text('Rider Dashboard', style: RiderStyle.title),
              const SizedBox(height: 4),
              Text('Welcome, $_firstName', style: RiderStyle.small),
            ],
          ),
        ),

        IconButton(
          tooltip: 'Refresh dashboard',
          onPressed: _requestInProgress
              ? null
              : () => _loadDashboard(refresh: true),
          icon: _isRefreshing
              ? const SizedBox(
                  width: 19,
                  height: 19,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: RiderColors.primary,
                  ),
                )
              : const Icon(Icons.refresh_rounded, color: RiderColors.primary),
        ),

        _notificationButton(),
      ],
    );
  }

  Widget _notificationButton() {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: _openPickupOffers,
        borderRadius: BorderRadius.circular(13),
        child: Container(
          height: 40,
          width: 40,
          decoration: BoxDecoration(
            color: RiderColors.white,
            borderRadius: BorderRadius.circular(13),
            border: Border.all(color: RiderColors.border),
          ),
          child: const Icon(
            Icons.notifications_none_rounded,
            color: RiderColors.textDark,
            size: 20,
          ),
        ),
      ),
    );
  }

  // ============================================================
  // ERROR BANNER
  // ============================================================

  Widget _errorBanner(String message) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: RiderColors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: RiderColors.border),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.info_outline_rounded,
            color: RiderColors.warning,
            size: 21,
          ),
          const SizedBox(width: 10),
          Expanded(child: Text(message, style: RiderStyle.small)),
          TextButton(
            onPressed: _requestInProgress
                ? null
                : () => _loadDashboard(refresh: true),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // AVAILABILITY CARD
  // ============================================================

  Widget _availabilityCard() {
    final bool available = _isAvailable;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: available
                      ? RiderColors.lightGreen
                      : RiderColors.lightOrange,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(
                  available
                      ? Icons.check_circle_outline_rounded
                      : Icons.pause_circle_outline_rounded,
                  color: available ? RiderColors.success : RiderColors.warning,
                  size: 25,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Availability', style: RiderStyle.cardTitle),
                    const SizedBox(height: 3),
                    Text(_availabilityStatus, style: RiderStyle.small),
                  ],
                ),
              ),
              Switch(
                value: available,
                activeThumbColor: RiderColors.success,
                onChanged: (_) => _toggleAvailability(),
              ),
            ],
          ),
          const SizedBox(height: 9),
          const Text(
            'Availability changes here are local only. '
            'They do not change your actual rider status '
            'on the Laravel server.',
            style: RiderStyle.small,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // RIDER INFORMATION
  // ============================================================

  Widget _riderInfoCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Rider Information', style: RiderStyle.sectionTitle),
          const SizedBox(height: 14),
          Row(
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: const BoxDecoration(
                  color: RiderColors.primary,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.two_wheeler_rounded,
                  color: Colors.white,
                  size: 26,
                ),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _riderName,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: RiderStyle.cardTitle,
                    ),
                    const SizedBox(height: 6),
                    AppInfoRow(
                      label: 'Vehicle Type',
                      value: _vehicleType,
                      icon: Icons.directions_bike_outlined,
                      iconColor: RiderColors.primary,
                      compact: true,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ============================================================
  // DELIVERY OVERVIEW
  // ============================================================

  Widget _deliveryOverview(double screenWidth) {
    final bool compact = screenWidth < 380;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Delivery Overview', style: RiderStyle.sectionTitle),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _overviewCard(
                title: 'Active',
                value: _overviewValue(['active', 'assigned', 'today_delivery']),
                icon: Icons.inventory_2_outlined,
                background: RiderColors.lightBlue,
                compact: compact,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _overviewCard(
                title: 'Completed',
                value: _overviewValue([
                  'completed',
                  'delivered',
                  'completed_delivery',
                ]),
                icon: Icons.check_circle_outline_rounded,
                background: RiderColors.lightGreen,
                compact: compact,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        _wideOverviewCard(
          title: 'Pending Assignments',
          value: _overviewValue([
            'pending',
            'pending_delivery',
            'pending_deliveries',
          ]),
          icon: Icons.pending_actions_outlined,
          background: RiderColors.lightOrange,
        ),
      ],
    );
  }

  // ============================================================
  // ASSIGNMENT TYPES
  // ============================================================

  Widget _assignmentOverview(double screenWidth) {
    final bool compact = screenWidth < 380;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Assignment Types', style: RiderStyle.sectionTitle),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _overviewCard(
                title: 'Seller Pickup',
                value: _overviewValue(['seller_pickup']),
                icon: Icons.inventory_2_outlined,
                background: RiderColors.lightOrange,
                compact: compact,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _overviewCard(
                title: 'Line Haul',
                value: _overviewValue(['linehaul']),
                icon: Icons.multiple_stop_rounded,
                background: RiderColors.lightBlue,
                compact: compact,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        _wideOverviewCard(
          title: 'Final-Mile Delivery',
          value: _overviewValue(['final_mile']),
          icon: Icons.delivery_dining_rounded,
          background: RiderColors.lightGreen,
        ),
      ],
    );
  }

  // ============================================================
  // STAT CARDS
  // ============================================================

  Widget _overviewCard({
    required String title,
    required String value,
    required IconData icon,
    required Color background,
    required bool compact,
  }) {
    return Container(
      padding: EdgeInsets.all(compact ? 12 : 14),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _statIcon(icon, background),
          const SizedBox(height: 12),
          Text(
            title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: RiderStyle.small,
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              color: RiderColors.textDark,
              fontSize: 21,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  Widget _wideOverviewCard({
    required String title,
    required String value,
    required IconData icon,
    required Color background,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: RiderStyle.card,
      child: Row(
        children: [
          _statIcon(icon, background),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: RiderStyle.small),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: const TextStyle(
                    color: RiderColors.textDark,
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _statIcon(IconData icon, Color background) {
    return Container(
      height: 38,
      width: 38,
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(11),
      ),
      child: Icon(icon, color: RiderColors.primary, size: 20),
    );
  }

  // ============================================================
  // LATEST ASSIGNMENTS
  // ============================================================

  Widget _assignedShipments() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Latest Assignments',
                  style: RiderStyle.sectionTitle,
                ),
              ),
              TextButton(
                onPressed: widget.onNavigateTab == null
                    ? null
                    : () => _goToTab(1),
                child: const Text('View All'),
              ),
            ],
          ),
          const SizedBox(height: 10),

          if (_taskErrorMessage != null) ...[
            Text(
              _hasTaskData
                  ? 'Showing available assignments. '
                        'Latest refresh was unsuccessful.'
                  : _taskErrorMessage!,
              style: RiderStyle.small,
            ),
            const SizedBox(height: 12),
          ],

          if (!_hasTaskData && _requestInProgress)
            const AppSkeleton(
              type: AppSkeletonType.parcel,
              itemCount: 2,
              color: RiderColors.primary,
            )
          else if (!_hasTaskData)
            AppErrorState(
              title: 'Assignments Unavailable',
              message:
                  _taskErrorMessage ??
                  'Unable to confirm your latest assignments.',
              color: RiderColors.primary,
              compact: true,
              onRetry: () => _loadDashboard(refresh: true),
            )
          else if (_tasks.isEmpty)
            const AppEmptyState(
              title: 'No Assigned Shipments',
              message:
                  'Your Seller Pickup, Line-Haul, and '
                  'Final-Mile assignments will appear here.',
              icon: Icons.inventory_2_outlined,
              color: RiderColors.primary,
              compact: true,
            )
          else
            ..._tasks.take(4).map(_shipmentItem),
        ],
      ),
    );
  }

  // ============================================================
  // ASSIGNMENT CARD
  // ============================================================

  Widget _shipmentItem(Map<String, dynamic> raw) {
    final Map<String, dynamic> normalized = _normalizeTask(raw);

    ParcelModel? parcel;

    try {
      parcel = ParcelModel.fromJson(normalized);
    } catch (error) {
      debugPrint('Rider dashboard parcel error: $error');
    }

    final String tracking = parcel != null
        ? _displayValue(parcel.trackingNumber, fallback: 'Tracking unavailable')
        : _firstNonEmpty([
            normalized['tracking_number'],
            normalized['tracking_code'],
            normalized['waybill_number'],
          ], fallback: 'Tracking unavailable');

    final String rawStatus =
        parcel?.status ?? normalized['status']?.toString() ?? '';

    final String assignment =
        parcel?.assignmentLabel ??
        _formatAssignment(normalized['assignment_type']?.toString() ?? '');

    final bool sellerPickup = parcel?.isSellerPickupAssignment ?? false;

    final bool linehaul = parcel?.isLinehaulAssignment ?? false;

    final bool finalMile = parcel?.isFinalMileAssignment ?? false;

    String primaryLocation = '';

    if (linehaul && parcel != null) {
      final String origin = parcel.originHubName.trim();
      final String destination = parcel.destinationHubName.trim();

      if (origin.isNotEmpty && destination.isNotEmpty) {
        primaryLocation = '$origin → $destination';
      } else {
        primaryLocation = origin.isNotEmpty ? origin : destination;
      }
    }

    if (primaryLocation.isEmpty && sellerPickup) {
      primaryLocation = parcel?.pickupAddress.trim() ?? '';
    }

    if (primaryLocation.isEmpty && finalMile) {
      primaryLocation = parcel?.deliveryAddress.trim() ?? '';
    }

    if (primaryLocation.isEmpty) {
      primaryLocation = _firstNonEmpty([
        normalized['delivery_address'],
        normalized['pickup_address'],
        normalized['address'],
      ], fallback: 'Location unavailable');
    }

    primaryLocation = AddressFormatter.clean(primaryLocation);

    final IconData icon = linehaul
        ? Icons.multiple_stop_rounded
        : sellerPickup
        ? Icons.inventory_2_outlined
        : Icons.local_shipping_outlined;

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: RiderColors.background,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: RiderColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                height: 40,
                width: 40,
                decoration: BoxDecoration(
                  color: RiderColors.lightPurple,
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Icon(icon, color: RiderColors.primary, size: 20),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      tracking,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: RiderStyle.cardTitle,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      assignment.isNotEmpty ? assignment : 'Rider Assignment',
                      style: RiderStyle.small,
                    ),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 10),

          StatusChip(status: rawStatus),

          const SizedBox(height: 10),

          AppInfoRow(
            label: linehaul
                ? 'Hub Route'
                : sellerPickup
                ? 'Pickup Address'
                : 'Delivery Address',
            value: primaryLocation.isNotEmpty
                ? primaryLocation
                : 'Location unavailable',
            icon: linehaul
                ? Icons.route_outlined
                : sellerPickup
                ? Icons.storefront_outlined
                : Icons.location_on_outlined,
            iconColor: RiderColors.primary,
            compact: true,
          ),

          if (linehaul &&
              parcel != null &&
              parcel.linehaulStatus.trim().isNotEmpty) ...[
            const SizedBox(height: 8),
            AppInfoRow(
              label: 'Line-Haul Status',
              value: _formatStatus(parcel.linehaulStatus),
              icon: Icons.compare_arrows_rounded,
              iconColor: RiderColors.primary,
              compact: true,
            ),
          ],
        ],
      ),
    );
  }

  String _formatAssignment(String value) {
    switch (_normalizeStatus(value)) {
      case 'seller_pickup':
        return 'Seller Pickup';
      case 'linehaul':
        return 'Line Haul';
      case 'final_mile':
        return 'Final-Mile Delivery';
      case 'pickup_and_delivery':
        return 'Pickup & Final-Mile';
      case 'pickup_and_linehaul':
        return 'Pickup & Line Haul';
      case 'linehaul_and_delivery':
        return 'Line Haul & Final-Mile';
      case 'pickup_linehaul_and_delivery':
        return 'Pickup / Line Haul / Final-Mile';
      default:
        return '';
    }
  }
}

class _RiderApiResult {
  final Map<String, dynamic>? data;
  final String? error;

  const _RiderApiResult({this.data, this.error});
}
