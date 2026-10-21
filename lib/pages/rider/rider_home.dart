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
  bool _hasDashboardData = false;

  String? _errorMessage;
  String _savedUserName = 'Rider';

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

  Future<void> _loadDashboard() async {
    if (mounted) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final savedUser = await AuthStorage.getUser();

      if (savedUser != null) {
        final savedName = savedUser['name']?.toString().trim();

        if (savedName != null && savedName.isNotEmpty) {
          _savedUserName = savedName;
        }
      }

      final result = await ApiService.getRiderDashboard();

      if (!mounted) return;

      if (result['success'] == true) {
        final riderData = result['rider'];
        final overviewData = result['overview'];
        final taskData = result['tasks'];

        final parsedTasks = <Map<String, dynamic>>[];

        if (taskData is List) {
          for (final task in taskData) {
            if (task is Map<String, dynamic>) {
              parsedTasks.add(task);
            } else if (task is Map) {
              parsedTasks.add(Map<String, dynamic>.from(task));
            }
          }
        }

        setState(() {
          _rider = riderData is Map
              ? Map<String, dynamic>.from(riderData)
              : <String, dynamic>{};

          _overview = overviewData is Map
              ? Map<String, dynamic>.from(overviewData)
              : <String, dynamic>{};

          _tasks = parsedTasks;

          _hasDashboardData = true;
          _errorMessage = null;
          _isLoading = false;
        });

        return;
      }

      setState(() {
        _hasDashboardData = false;

        _errorMessage =
            result['message']?.toString() ?? 'Unable to load rider dashboard.';

        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _hasDashboardData = false;
        _isLoading = false;

        _errorMessage = 'Unable to load rider dashboard: $e';
      });
    }
  }

  // ============================================================
  // NAVIGATION
  // ============================================================

  void _goToTab(int index) {
    widget.onNavigateTab?.call(index);
  }

  // ============================================================
  // DATA HELPERS
  // ============================================================

  String get _riderName {
    final apiName = _rider['name']?.toString().trim();

    if (apiName != null && apiName.isNotEmpty) {
      return apiName;
    }

    return _savedUserName;
  }

  String get _firstName {
    final name = _riderName.trim();

    if (name.isEmpty) {
      return 'Rider';
    }

    return name.split(RegExp(r'\s+')).first;
  }

  String get _availabilityStatus {
    final raw = _rider['availability_status']?.toString().trim();

    if (raw == null || raw.isEmpty) {
      return 'Unavailable';
    }

    return _formatStatus(raw);
  }

  bool get _isOnline {
    final status = _rider['availability_status']
        ?.toString()
        .trim()
        .toLowerCase();

    return status == 'online' || status == 'available' || status == 'active';
  }

  String get _vehicleType {
    final value = _rider['vehicle_type']?.toString().trim();

    if (value == null || value.isEmpty) {
      return 'Vehicle not specified';
    }

    return _formatStatus(value);
  }

  String _overviewValue(String key) {
    if (!_hasDashboardData) {
      return '—';
    }

    final value = _overview[key];

    if (value == null) {
      return '—';
    }

    return value.toString();
  }

  String _formatStatus(String value) {
    final cleaned = value.trim().replaceAll('_', ' ');

    if (cleaned.isEmpty) {
      return 'Unknown';
    }

    return cleaned
        .split(RegExp(r'\s+'))
        .map(
          (word) => word.isEmpty
              ? word
              : '${word[0].toUpperCase()}${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  Color _statusColor(String status) {
    final normalized = status.trim().toLowerCase();

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
    final screenWidth = MediaQuery.of(context).size.width;

    final horizontalPadding = screenWidth < 380 ? 14.0 : 18.0;

    return Scaffold(
      backgroundColor: RiderColors.background,

      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _loadDashboard,

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

        const SizedBox(width: 8),

        _statusBadge(),

        const SizedBox(width: 8),

        _notificationButton(),
      ],
    );
  }

  Widget _statusBadge() {
    return Container(
      constraints: const BoxConstraints(maxWidth: 95),

      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),

      decoration: BoxDecoration(
        color: _isOnline ? RiderColors.lightGreen : RiderColors.lightOrange,

        borderRadius: BorderRadius.circular(20),
      ),

      child: Row(
        mainAxisSize: MainAxisSize.min,

        children: [
          Container(
            height: 7,
            width: 7,

            decoration: BoxDecoration(
              color: _isOnline ? RiderColors.success : RiderColors.warning,

              shape: BoxShape.circle,
            ),
          ),

          const SizedBox(width: 5),

          Flexible(
            child: Text(
              _availabilityStatus,

              maxLines: 1,

              overflow: TextOverflow.ellipsis,

              style: TextStyle(
                color: _isOnline ? RiderColors.success : RiderColors.warning,

                fontSize: 9,

                fontWeight: FontWeight.w800,
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
    final compact = screenWidth < 380;

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
                value: _overviewValue('today_delivery'),
                icon: Icons.inventory_2_outlined,
                background: RiderColors.lightBlue,
                compact: compact,
              ),
            ),

            const SizedBox(width: 10),

            Expanded(
              child: _overviewCard(
                title: 'Completed',
                value: _overviewValue('completed'),
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
          value: _overviewValue('pending'),
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
          else if (!_hasDashboardData)
            _emptyShipmentState('Shipment data is unavailable.')
          else if (_tasks.isEmpty)
            _emptyShipmentState('No assigned shipments.')
          else
            ..._tasks.take(4).map((task) => _shipmentItem(task)),
        ],
      ),
    );
  }

  Widget _shipmentItem(Map<String, dynamic> task) {
    final tracking = task['tracking_number']?.toString().trim();

    final customer = task['customer_name']?.toString().trim();

    final address = task['delivery_address']?.toString().trim();

    final rawStatus = task['status']?.toString().trim() ?? '';

    final status = _formatStatus(rawStatus);

    final statusColor = _statusColor(rawStatus);

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
                      tracking == null || tracking.isEmpty
                          ? 'Tracking unavailable'
                          : tracking,

                      maxLines: 1,

                      overflow: TextOverflow.ellipsis,

                      style: RiderStyle.cardTitle,
                    ),

                    if (customer != null && customer.isNotEmpty) ...[
                      const SizedBox(height: 3),

                      Text(
                        customer,

                        maxLines: 1,

                        overflow: TextOverflow.ellipsis,

                        style: RiderStyle.small,
                      ),
                    ],
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
                  address == null || address.isEmpty
                      ? 'Delivery address unavailable'
                      : address,

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
                  'Dashboard data unavailable',

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
            onPressed: _loadDashboard,

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

              content: Text('Rider notifications are not available yet.'),
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
