import 'package:flutter/material.dart';

import '../../sevices/api_services.dart';
import '../../sevices/auth_storage.dart';
import '../../styles/rider_style.dart';

class RiderHome extends StatefulWidget {
  final ValueChanged<int>? onNavigateTab;

  const RiderHome({super.key, this.onNavigateTab});

  @override
  State<RiderHome> createState() => _RiderHomeState();
}

class _RiderHomeState extends State<RiderHome> {
  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _hasDashboardData = false;

  bool? _localAvailabilityOverride;

  String? _errorMessage;

  String _savedUserName = 'Rider';

  Map<String, dynamic> _savedUser = <String, dynamic>{};
  Map<String, dynamic> _rider = <String, dynamic>{};
  Map<String, dynamic> _overview = <String, dynamic>{};

  List<Map<String, dynamic>> _tasks = <Map<String, dynamic>>[];

  @override
  void initState() {
    super.initState();

    _loadDashboard();
  }

  // ============================================================
  // LOAD DASHBOARD
  // ============================================================

  Future<void> _loadDashboard({bool refresh = false}) async {
    if (!mounted) return;

    setState(() {
      if (refresh) {
        _isRefreshing = true;
      } else {
        _isLoading = true;
      }

      _errorMessage = null;
    });

    try {
      await _loadSavedUser();

      final Map<String, dynamic> dashboardResult =
          await ApiService.getRiderDashboard();

      final Map<String, dynamic> deliveryResult =
          await ApiService.getRiderDeliveries();

      if (!mounted) return;

      final Map<String, dynamic> riderData = _extractRiderData(dashboardResult);

      final Map<String, dynamic> overviewData = _extractOverviewData(
        dashboardResult,
      );

      final List<Map<String, dynamic>> dashboardTasks = _extractDashboardTasks(
        dashboardResult,
      );

      final List<Map<String, dynamic>> deliveryTasks = _extractDeliveryTasks(
        deliveryResult,
      );

      final List<Map<String, dynamic>> finalTasks = dashboardTasks.isNotEmpty
          ? dashboardTasks
          : deliveryTasks;

      final Map<String, dynamic> finalRider = riderData.isNotEmpty
          ? riderData
          : _extractSavedRider();

      final Map<String, dynamic> finalOverview = overviewData.isNotEmpty
          ? overviewData
          : _buildOverviewFromTasks(finalTasks);

      final bool dashboardSuccess = dashboardResult['success'] == true;

      final bool deliveriesSuccess = deliveryResult['success'] == true;

      final bool hasUsefulData =
          finalRider.isNotEmpty ||
          finalOverview.isNotEmpty ||
          finalTasks.isNotEmpty ||
          dashboardSuccess ||
          deliveriesSuccess;

      String? errorMessage;

      if (!dashboardSuccess && !deliveriesSuccess) {
        errorMessage = dashboardResult['message']?.toString().trim();

        if (errorMessage == null || errorMessage.isEmpty) {
          errorMessage = deliveryResult['message']?.toString().trim();
        }

        if (errorMessage == null || errorMessage.isEmpty) {
          errorMessage = 'Unable to load rider information.';
        }
      }

      setState(() {
        _rider = finalRider;
        _overview = finalOverview;
        _tasks = finalTasks;

        _hasDashboardData = hasUsefulData;

        _errorMessage = hasUsefulData ? null : errorMessage;

        _isLoading = false;
        _isRefreshing = false;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isLoading = false;
        _isRefreshing = false;
        _hasDashboardData = false;

        _errorMessage = 'Unable to load rider dashboard: $e';
      });
    }
  }

  // ============================================================
  // LOAD SAVED USER
  // ============================================================

  Future<void> _loadSavedUser() async {
    final Map<String, dynamic>? saved = await AuthStorage.getUser();

    if (saved == null) {
      return;
    }

    _savedUser = Map<String, dynamic>.from(saved);

    final String? savedName = saved['name']?.toString().trim();

    if (savedName != null && savedName.isNotEmpty) {
      _savedUserName = savedName;
    }
  }

  // ============================================================
  // EXTRACT RIDER DATA
  // ============================================================

  Map<String, dynamic> _extractRiderData(Map<String, dynamic> result) {
    final dynamic directRider = result['rider'];

    if (directRider is Map) {
      return Map<String, dynamic>.from(directRider);
    }

    final dynamic rawData = result['data'];

    if (rawData is Map) {
      final Map<String, dynamic> data = Map<String, dynamic>.from(rawData);

      final dynamic nestedRider = data['rider'];

      if (nestedRider is Map) {
        return Map<String, dynamic>.from(nestedRider);
      }

      if (_looksLikeRider(data)) {
        return data;
      }
    }

    return <String, dynamic>{};
  }

  // ============================================================
  // SAVED RIDER
  // ============================================================

  Map<String, dynamic> _extractSavedRider() {
    final dynamic savedRider = _savedUser['rider'];

    if (savedRider is Map) {
      return Map<String, dynamic>.from(savedRider);
    }

    final dynamic profile = _savedUser['profile'];

    if (profile is Map) {
      final Map<String, dynamic> profileMap = Map<String, dynamic>.from(
        profile,
      );

      final dynamic profileRider = profileMap['rider'];

      if (profileRider is Map) {
        return Map<String, dynamic>.from(profileRider);
      }
    }

    final Map<String, dynamic> fallback = <String, dynamic>{};

    final dynamic name = _savedUser['name'];

    final dynamic vehicleType = _savedUser['vehicle_type'];

    final dynamic availabilityStatus = _savedUser['availability_status'];

    final dynamic status = _savedUser['status'];

    if (name != null) {
      fallback['name'] = name;
    }

    if (vehicleType != null) {
      fallback['vehicle_type'] = vehicleType;
    }

    if (availabilityStatus != null) {
      fallback['availability_status'] = availabilityStatus;
    }

    if (status != null) {
      fallback['status'] = status;
    }

    return fallback;
  }

  // ============================================================
  // RIDER MAP CHECK
  // ============================================================

  bool _looksLikeRider(Map<String, dynamic> data) {
    return data.containsKey('vehicle_type') ||
        data.containsKey('availability_status') ||
        data.containsKey('license_number') ||
        data.containsKey('driver_license_number');
  }

  // ============================================================
  // EXTRACT OVERVIEW
  // ============================================================

  Map<String, dynamic> _extractOverviewData(Map<String, dynamic> result) {
    final dynamic directOverview = result['overview'];

    if (directOverview is Map) {
      return Map<String, dynamic>.from(directOverview);
    }

    final dynamic rawData = result['data'];

    if (rawData is Map) {
      final dynamic nestedOverview = rawData['overview'];

      if (nestedOverview is Map) {
        return Map<String, dynamic>.from(nestedOverview);
      }

      final dynamic statistics = rawData['statistics'];

      if (statistics is Map) {
        return Map<String, dynamic>.from(statistics);
      }
    }

    return <String, dynamic>{};
  }

  // ============================================================
  // EXTRACT DASHBOARD TASKS
  // ============================================================

  List<Map<String, dynamic>> _extractDashboardTasks(
    Map<String, dynamic> result,
  ) {
    final dynamic directTasks = result['tasks'];

    final List<Map<String, dynamic>> parsedDirect = _toMapList(directTasks);

    if (parsedDirect.isNotEmpty) {
      return parsedDirect;
    }

    final dynamic rawData = result['data'];

    if (rawData is Map) {
      final dynamic tasks = rawData['tasks'];

      final List<Map<String, dynamic>> parsedTasks = _toMapList(tasks);

      if (parsedTasks.isNotEmpty) {
        return parsedTasks;
      }

      final dynamic deliveries = rawData['deliveries'];

      final List<Map<String, dynamic>> parsedDeliveries = _toMapList(
        deliveries,
      );

      if (parsedDeliveries.isNotEmpty) {
        return parsedDeliveries;
      }

      final dynamic orders = rawData['orders'];

      final List<Map<String, dynamic>> parsedOrders = _toMapList(orders);

      if (parsedOrders.isNotEmpty) {
        return parsedOrders;
      }

      final dynamic shipments = rawData['shipments'];

      final List<Map<String, dynamic>> parsedShipments = _toMapList(shipments);

      if (parsedShipments.isNotEmpty) {
        return parsedShipments;
      }
    }

    return <Map<String, dynamic>>[];
  }

  // ============================================================
  // EXTRACT DELIVERIES
  // ============================================================

  List<Map<String, dynamic>> _extractDeliveryTasks(
    Map<String, dynamic> result,
  ) {
    if (result['success'] != true) {
      return <Map<String, dynamic>>[];
    }

    final dynamic rawData = result['data'];

    return _toMapList(rawData);
  }

  // ============================================================
  // CONVERT LIST
  // ============================================================

  List<Map<String, dynamic>> _toMapList(dynamic value) {
    if (value is! List) {
      return <Map<String, dynamic>>[];
    }

    final List<Map<String, dynamic>> result = <Map<String, dynamic>>[];

    for (final dynamic item in value) {
      if (item is Map<String, dynamic>) {
        result.add(Map<String, dynamic>.from(item));
      } else if (item is Map) {
        result.add(Map<String, dynamic>.from(item));
      }
    }

    return result;
  }

  // ============================================================
  // BUILD OVERVIEW FROM TASKS
  // ============================================================

  Map<String, dynamic> _buildOverviewFromTasks(
    List<Map<String, dynamic>> tasks,
  ) {
    int assigned = 0;
    int completed = 0;
    int pending = 0;

    for (final Map<String, dynamic> task in tasks) {
      final String status = _normalizeStatus(task['status']?.toString() ?? '');

      if (status == 'delivered' || status == 'completed') {
        completed++;
        continue;
      }

      if (status == 'pending' ||
          status == 'assigned' ||
          status == 'staged' ||
          status == 'ready_for_pickup') {
        pending++;
      }

      assigned++;
    }

    return <String, dynamic>{
      'today_delivery': assigned,
      'assigned': assigned,
      'completed': completed,
      'pending': pending,
    };
  }

  // ============================================================
  // NAVIGATION
  // ============================================================

  void _goToTab(int index) {
    widget.onNavigateTab?.call(index);
  }

  // ============================================================
  // RIDER NAME
  // ============================================================

  String get _riderName {
    final List<dynamic> candidates = <dynamic>[
      _rider['name'],
      _rider['full_name'],
      _savedUser['name'],
    ];

    for (final dynamic candidate in candidates) {
      final String value = candidate?.toString().trim() ?? '';

      if (value.isNotEmpty) {
        return value;
      }
    }

    final String firstName = _rider['first_name']?.toString().trim() ?? '';

    final String lastName = _rider['last_name']?.toString().trim() ?? '';

    final String completeName = <String>[
      firstName,
      lastName,
    ].where((String part) => part.isNotEmpty).join(' ');

    if (completeName.isNotEmpty) {
      return completeName;
    }

    return _savedUserName;
  }

  String get _firstName {
    final String name = _riderName.trim();

    if (name.isEmpty) {
      return 'Rider';
    }

    return name.split(RegExp(r'\s+')).first;
  }

  // ============================================================
  // AVAILABILITY
  // ============================================================

  bool get _isAvailable {
    final bool? override = _localAvailabilityOverride;

    if (override != null) {
      return override;
    }

    final dynamic boolAvailable = _rider['is_available'] ?? _rider['available'];

    if (boolAvailable is bool) {
      return boolAvailable;
    }

    if (boolAvailable != null) {
      final String value = boolAvailable.toString().trim().toLowerCase();

      if (value == '1' || value == 'true' || value == 'yes') {
        return true;
      }

      if (value == '0' || value == 'false' || value == 'no') {
        return false;
      }
    }

    final String availability =
        (_rider['availability_status'] ??
                _rider['availability'] ??
                _savedUser['availability_status'] ??
                '')
            .toString()
            .trim()
            .toLowerCase();

    if (availability == 'available' ||
        availability == 'online' ||
        availability == 'active') {
      return true;
    }

    if (availability == 'unavailable' ||
        availability == 'offline' ||
        availability == 'inactive') {
      return false;
    }

    return false;
  }

  String get _availabilityStatus {
    return _isAvailable ? 'Available' : 'Unavailable';
  }

  void _toggleAvailability() {
    final bool newValue = !_isAvailable;

    setState(() {
      _localAvailabilityOverride = newValue;
    });

    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        content: Text(
          newValue
              ? 'You are now shown as Available on this device.'
              : 'You are now shown as Unavailable on this device.',
        ),
      ),
    );
  }

  // ============================================================
  // VEHICLE TYPE
  // ============================================================

  String get _vehicleType {
    final List<dynamic> candidates = <dynamic>[
      _rider['vehicle_type'],
      _rider['vehicle'],
      _savedUser['vehicle_type'],
    ];

    for (final dynamic candidate in candidates) {
      final String value = candidate?.toString().trim() ?? '';

      if (value.isNotEmpty) {
        return _formatStatus(value);
      }
    }

    return 'Vehicle not specified';
  }

  // ============================================================
  // OVERVIEW VALUE
  // ============================================================

  String _overviewValue(List<String> keys) {
    for (final String key in keys) {
      final dynamic value = _overview[key];

      if (value != null) {
        return value.toString();
      }
    }

    return '0';
  }

  // ============================================================
  // STATUS HELPERS
  // ============================================================

  String _normalizeStatus(String value) {
    return value.trim().toLowerCase().replaceAll(' ', '_').replaceAll('-', '_');
  }

  String _formatStatus(String value) {
    final String cleaned = value
        .trim()
        .replaceAll('_', ' ')
        .replaceAll('-', ' ');

    if (cleaned.isEmpty) {
      return 'Unknown';
    }

    return cleaned
        .split(RegExp(r'\s+'))
        .map((String word) {
          if (word.isEmpty) {
            return word;
          }

          return '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}';
        })
        .join(' ');
  }

  Color _statusColor(String status) {
    final String normalized = _normalizeStatus(status);

    switch (normalized) {
      case 'delivered':
      case 'completed':
        return RiderColors.success;

      case 'cancelled':
      case 'failed':
      case 'delivery_failed':
        return RiderColors.danger;

      case 'pending':
      case 'assigned':
      case 'staged':
      case 'ready_for_pickup':
        return RiderColors.warning;

      case 'picked_up':
      case 'in_transit':
      case 'out_for_delivery':
      case 'at_sorting_center':
      case 'sorted':
        return RiderColors.primary;

      default:
        return RiderColors.textGrey;
    }
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double screenWidth = MediaQuery.of(context).size.width;

    final double horizontalPadding = screenWidth < 380 ? 14 : 18;

    return Scaffold(
      backgroundColor: RiderColors.background,

      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () {
            return _loadDashboard(refresh: true);
          },

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

              if (_isLoading) ...[_loadingCard(), const SizedBox(height: 14)],

              if (!_isLoading && _errorMessage != null) ...[
                _errorCard(),
                const SizedBox(height: 14),
              ],

              _availabilityCard(),

              const SizedBox(height: 14),

              _riderInfoCard(),

              const SizedBox(height: 20),

              _deliveryOverview(screenWidth),

              const SizedBox(height: 20),

              _assignedShipments(),
            ],
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
              const Text('Rider Dashboard', style: RiderStyle.title),

              const SizedBox(height: 4),

              Text('Welcome, $_firstName', style: RiderStyle.small),
            ],
          ),
        ),

        if (_isRefreshing)
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 10),
            child: SizedBox(
              height: 19,
              width: 19,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: RiderColors.primary,
              ),
            ),
          )
        else
          IconButton(
            tooltip: 'Refresh',
            onPressed: () {
              _loadDashboard(refresh: true);
            },
            icon: const Icon(Icons.refresh_rounded, color: RiderColors.primary),
          ),

        _notificationButton(),
      ],
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

      child: Row(
        children: [
          Container(
            height: 46,
            width: 46,

            decoration: BoxDecoration(
              color: available
                  ? RiderColors.lightGreen
                  : RiderColors.lightOrange,

              borderRadius: BorderRadius.circular(14),
            ),

            alignment: Alignment.center,

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

                Text(
                  available
                      ? 'You are available for delivery assignments.'
                      : 'You are currently unavailable for new assignments.',

                  style: RiderStyle.small,
                ),
              ],
            ),
          ),

          const SizedBox(width: 10),

          GestureDetector(
            onTap: _toggleAvailability,

            child: AnimatedContainer(
              duration: const Duration(milliseconds: 180),

              width: 54,
              height: 30,

              padding: const EdgeInsets.all(3),

              decoration: BoxDecoration(
                color: available ? RiderColors.success : RiderColors.border,

                borderRadius: BorderRadius.circular(20),
              ),

              child: AnimatedAlign(
                duration: const Duration(milliseconds: 180),

                alignment: available
                    ? Alignment.centerRight
                    : Alignment.centerLeft,

                child: Container(
                  height: 24,
                  width: 24,

                  decoration: const BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
            ),
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
                height: 50,
                width: 50,

                decoration: const BoxDecoration(
                  color: RiderColors.primary,
                  shape: BoxShape.circle,
                ),

                alignment: Alignment.center,

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

                      maxLines: 1,

                      overflow: TextOverflow.ellipsis,

                      style: RiderStyle.cardTitle,
                    ),

                    const SizedBox(height: 5),

                    Row(
                      children: [
                        const Icon(
                          Icons.directions_bike_outlined,
                          size: 14,
                          color: RiderColors.textGrey,
                        ),

                        const SizedBox(width: 5),

                        Expanded(
                          child: Text(
                            _vehicleType,

                            maxLines: 1,

                            overflow: TextOverflow.ellipsis,

                            style: RiderStyle.small,
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 5),

                    Row(
                      children: [
                        Container(
                          height: 7,
                          width: 7,

                          decoration: BoxDecoration(
                            color: _isAvailable
                                ? RiderColors.success
                                : RiderColors.warning,

                            shape: BoxShape.circle,
                          ),
                        ),

                        const SizedBox(width: 6),

                        Text(
                          _availabilityStatus,

                          style: TextStyle(
                            color: _isAvailable
                                ? RiderColors.success
                                : RiderColors.warning,

                            fontSize: 11,

                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
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
                title: 'Assigned',

                value: _overviewValue(<String>[
                  'today_delivery',
                  'assigned',
                  'total',
                ]),

                icon: Icons.inventory_2_outlined,

                background: RiderColors.lightBlue,

                compact: compact,
              ),
            ),

            const SizedBox(width: 10),

            Expanded(
              child: _overviewCard(
                title: 'Completed',

                value: _overviewValue(<String>[
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
          title: 'Pending Deliveries',

          value: _overviewValue(<String>[
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
        mainAxisSize: MainAxisSize.min,

        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          _statIcon(icon, background),

          const SizedBox(height: 12),

          Text(
            title,

            maxLines: 1,

            overflow: TextOverflow.ellipsis,

            style: RiderStyle.small,
          ),

          const SizedBox(height: 4),

          Text(
            value,

            maxLines: 1,

            overflow: TextOverflow.ellipsis,

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
              mainAxisSize: MainAxisSize.min,

              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(title, style: RiderStyle.small),

                const SizedBox(height: 4),

                Text(
                  value,

                  maxLines: 1,

                  overflow: TextOverflow.ellipsis,

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

      alignment: Alignment.center,

      child: Icon(icon, color: RiderColors.primary, size: 20),
    );
  }

  // ============================================================
  // ASSIGNED SHIPMENTS
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
                  'Assigned Shipments',
                  style: RiderStyle.sectionTitle,
                ),
              ),

              TextButton(
                onPressed: () => _goToTab(1),

                child: const Text('View All'),
              ),
            ],
          ),

          const SizedBox(height: 10),

          if (_isLoading && !_hasDashboardData)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 20),

              child: Center(
                child: CircularProgressIndicator(color: RiderColors.primary),
              ),
            )
          else if (_tasks.isEmpty)
            _emptyShipmentState('No assigned shipments.')
          else
            ..._tasks.take(4).map((Map<String, dynamic> task) {
              return _shipmentItem(task);
            }),
        ],
      ),
    );
  }

  Widget _shipmentItem(Map<String, dynamic> task) {
    final String tracking = _firstNonEmpty(<dynamic>[
      task['tracking_number'],
      task['tracking_code'],
      task['waybill_number'],
    ], fallback: 'Tracking unavailable');

    final String customer = _firstNonEmpty(<dynamic>[
      task['customer_name'],
      task['recipient_name'],
      task['buyer_name'],
    ], fallback: 'Customer unavailable');

    final String address = _firstNonEmpty(<dynamic>[
      task['delivery_address'],
      task['address'],
    ], fallback: 'Delivery address unavailable');

    final String rawStatus = task['status']?.toString().trim() ?? '';

    final String status = _formatStatus(rawStatus);

    final Color statusColor = _statusColor(rawStatus);

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

                alignment: Alignment.center,

                child: const Icon(
                  Icons.inventory_2_outlined,

                  color: RiderColors.primary,

                  size: 20,
                ),
              ),

              const SizedBox(width: 10),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Text(
                      tracking,

                      maxLines: 1,

                      overflow: TextOverflow.ellipsis,

                      style: RiderStyle.cardTitle,
                    ),

                    const SizedBox(height: 3),

                    Text(
                      customer,

                      maxLines: 1,

                      overflow: TextOverflow.ellipsis,

                      style: RiderStyle.small,
                    ),
                  ],
                ),
              ),

              const SizedBox(width: 8),

              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),

                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.10),

                  borderRadius: BorderRadius.circular(18),
                ),

                child: Text(
                  status,

                  style: TextStyle(
                    color: statusColor,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 10),

          Row(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              const Icon(
                Icons.location_on_outlined,

                color: RiderColors.textGrey,

                size: 15,
              ),

              const SizedBox(width: 5),

              Expanded(
                child: Text(
                  address,

                  maxLines: 2,

                  overflow: TextOverflow.ellipsis,

                  style: RiderStyle.small,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _firstNonEmpty(List<dynamic> values, {required String fallback}) {
    for (final dynamic value in values) {
      final String cleaned = value?.toString().trim() ?? '';

      if (cleaned.isNotEmpty && cleaned.toLowerCase() != 'null') {
        return cleaned;
      }
    }

    return fallback;
  }

  Widget _emptyShipmentState(String message) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 22),

      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,

          children: [
            const Icon(
              Icons.inventory_2_outlined,

              color: RiderColors.textGrey,

              size: 34,
            ),

            const SizedBox(height: 8),

            Text(message, textAlign: TextAlign.center, style: RiderStyle.small),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // LOADING
  // ============================================================

  Widget _loadingCard() {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.all(15),

      decoration: RiderStyle.card,

      child: const Row(
        children: [
          SizedBox(
            height: 21,
            width: 21,

            child: CircularProgressIndicator(
              strokeWidth: 2.2,

              color: RiderColors.primary,
            ),
          ),

          SizedBox(width: 11),

          Expanded(
            child: Text('Loading rider dashboard...', style: RiderStyle.small),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // ERROR
  // ============================================================

  Widget _errorCard() {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.all(15),

      decoration: RiderStyle.card,

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          const Icon(
            Icons.info_outline_rounded,

            color: RiderColors.warning,

            size: 21,
          ),

          const SizedBox(width: 10),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                const Text(
                  'Unable to load rider data',

                  style: RiderStyle.cardTitle,
                ),

                const SizedBox(height: 4),

                Text(
                  _errorMessage ?? 'Unable to load rider dashboard.',

                  style: RiderStyle.small,
                ),
              ],
            ),
          ),

          IconButton(
            onPressed: () {
              _loadDashboard(refresh: true);
            },

            icon: const Icon(Icons.refresh_rounded, color: RiderColors.primary),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // NOTIFICATION
  // ============================================================

  Widget _notificationButton() {
    return Material(
      color: Colors.transparent,

      child: InkWell(
        onTap: () {
          ScaffoldMessenger.of(context).hideCurrentSnackBar();

          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              behavior: SnackBarBehavior.floating,

              content: Text('No new rider notifications.'),
            ),
          );
        },

        borderRadius: BorderRadius.circular(13),

        child: Container(
          height: 40,
          width: 40,

          decoration: BoxDecoration(
            color: RiderColors.white,

            borderRadius: BorderRadius.circular(13),

            border: Border.all(color: RiderColors.border),
          ),

          alignment: Alignment.center,

          child: const Icon(
            Icons.notifications_none_rounded,

            color: RiderColors.textDark,

            size: 20,
          ),
        ),
      ),
    );
  }
}
