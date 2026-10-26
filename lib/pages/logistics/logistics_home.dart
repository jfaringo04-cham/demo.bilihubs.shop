import 'package:flutter/material.dart';

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

  String? _errorMessage;

  String _savedUserName = 'Logistics Staff';
  String _companyName = 'Logistics';

  int _totalParcels = 0;
  int _pendingParcels = 0;
  int _inTransitParcels = 0;
  int _deliveredParcels = 0;

  int _receivedParcels = 0;
  int _scannedParcels = 0;
  int _sortedParcels = 0;
  int _stagedParcels = 0;

  int _activeRiders = 0;
  int _availableRiders = 0;
  int _activeHubs = 0;
  int _alertsCount = 0;

  List<Map<String, dynamic>> _recentShipments = [];

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

      final result = await ApiService.getLogisticsDashboard();

      if (!mounted) return;

      final String? savedName = savedUser?['name']?.toString().trim();

      if (savedName != null && savedName.isNotEmpty) {
        _savedUserName = savedName;
      }

      if (result['success'] != true) {
        setState(() {
          _isLoading = false;
          _errorMessage =
              result['message']?.toString() ??
              'Unable to load logistics dashboard.';
        });

        return;
      }

      final Map<String, dynamic> data = _extractDashboardData(result);

      final Map<String, dynamic> logistic = _asMap(data['logistic']);

      final Map<String, dynamic> shipments = _asMap(data['shipments']);

      final Map<String, dynamic> riders = _asMap(data['riders']);

      final Map<String, dynamic> hubs = _asMap(data['hubs']);

      final List<dynamic> recent = data['recent_shipments'] is List
          ? data['recent_shipments'] as List
          : <dynamic>[];

      setState(() {
        _companyName =
            logistic['company_name']?.toString().trim().isNotEmpty == true
            ? logistic['company_name'].toString().trim()
            : 'Logistics';

        _totalParcels = _toInt(shipments['total']);

        _pendingParcels = _toInt(shipments['pending']);

        _inTransitParcels = _toInt(shipments['in_transit']);

        _deliveredParcels = _toInt(shipments['delivered']);

        _receivedParcels = _toInt(shipments['received']);

        _scannedParcels = _toInt(shipments['scanned']);

        _sortedParcels = _toInt(shipments['sorted']);

        _stagedParcels = _toInt(shipments['staged']);

        _activeRiders = _toInt(riders['active']);

        _availableRiders = _toInt(riders['available']);

        _activeHubs = _toInt(hubs['active']);

        _alertsCount = _toInt(data['alerts_count']);

        _recentShipments = recent
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();

        _isLoading = false;
        _errorMessage = null;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isLoading = false;
        _errorMessage = 'Unable to load logistics dashboard: $e';
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

    return int.tryParse(value?.toString() ?? '') ?? 0;
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
  // OPEN SCANNER
  // ============================================================

  void _openScanner() {
    final callback = widget.onOpenScanner;

    if (callback != null) {
      callback();
      return;
    }

    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        behavior: SnackBarBehavior.floating,
        content: Text('Unable to open parcel scanner.'),
      ),
    );
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: LogisticsColors.background,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _loadDashboard,
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: EdgeInsets.all(width * 0.045),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // ============================================================
                // HEADER
                // ============================================================

                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Welcome Back',
                            style: LogisticsStyle.small,
                          ),
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
                ),

                if (_isLoading) ...[const SizedBox(height: 18), _loadingCard()],

                if (!_isLoading && _errorMessage != null) ...[
                  const SizedBox(height: 18),
                  _errorCard(),
                ],

                const SizedBox(height: 28),

                // ============================================================
                // PARCEL OVERVIEW
                // ============================================================
                const Text(
                  'Parcel Overview',
                  style: LogisticsStyle.sectionTitle,
                ),

                const SizedBox(height: 14),

                GridView.count(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  crossAxisCount: 2,
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 12,
                  childAspectRatio: 1.25,
                  children: [
                    _overviewCard(
                      title: 'Total Parcels',
                      value: _totalParcels.toString(),
                      icon: Icons.inventory_2_outlined,
                    ),
                    _overviewCard(
                      title: 'Pending',
                      value: _pendingParcels.toString(),
                      icon: Icons.pending_actions_rounded,
                    ),
                    _overviewCard(
                      title: 'In Transit',
                      value: _inTransitParcels.toString(),
                      icon: Icons.local_shipping_outlined,
                    ),
                    _overviewCard(
                      title: 'Delivered',
                      value: _deliveredParcels.toString(),
                      icon: Icons.check_circle_outline_rounded,
                    ),
                  ],
                ),

                const SizedBox(height: 28),

                // ============================================================
                // SORTING CENTER
                // ============================================================
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(18),
                  decoration: LogisticsStyle.card,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Sorting Center',
                        style: LogisticsStyle.sectionTitle,
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
                ),

                const SizedBox(height: 18),

                // ============================================================
                // OPERATIONS
                // ============================================================
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(18),
                  decoration: LogisticsStyle.card,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Operations',
                        style: LogisticsStyle.sectionTitle,
                      ),
                      const SizedBox(height: 18),
                      _activityRow(
                        icon: Icons.delivery_dining_rounded,
                        title: 'Active Riders',
                        value: _activeRiders.toString(),
                      ),
                      const SizedBox(height: 12),
                      _activityRow(
                        icon: Icons.person_pin_circle_outlined,
                        title: 'Available Riders',
                        value: _availableRiders.toString(),
                      ),
                      const SizedBox(height: 12),
                      _activityRow(
                        icon: Icons.warehouse_outlined,
                        title: 'Active Hubs',
                        value: _activeHubs.toString(),
                      ),
                      const SizedBox(height: 12),
                      _activityRow(
                        icon: Icons.notifications_active_outlined,
                        title: 'Alerts',
                        value: _alertsCount.toString(),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 28),

                // ============================================================
                // SCAN PARCEL
                // ============================================================
                Material(
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
                          colors: [
                            LogisticsColors.primary,
                            LogisticsColors.primaryDark,
                          ],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(22),
                        boxShadow: [
                          BoxShadow(
                            color: LogisticsColors.primary.withValues(
                              alpha: 0.18,
                            ),
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
                                  'Scan a parcel QR code and verify its shipment.',
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
                ),

                const SizedBox(height: 28),

                // ============================================================
                // RECENT SHIPMENTS
                // ============================================================
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Recent Shipments',
                      style: LogisticsStyle.sectionTitle,
                    ),
                    Text(
                      '${_recentShipments.length}',
                      style: const TextStyle(
                        color: LogisticsColors.primary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 12),

                if (!_isLoading && _recentShipments.isEmpty)
                  _emptyRecentShipments()
                else
                  ..._recentShipments.take(5).map(_recentShipmentCard),

                const SizedBox(height: 20),
              ],
            ),
          ),
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
            onPressed: _loadDashboard,
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
        boxShadow: [
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
  // RECENT SHIPMENT CARD
  // ============================================================

  Widget _recentShipmentCard(Map<String, dynamic> shipment) {
    final String tracking =
        shipment['tracking_number']?.toString() ?? 'No tracking number';

    final String customer =
        shipment['customer_name']?.toString() ?? 'Unknown customer';

    final String status = shipment['status']?.toString() ?? 'unknown';

    final String sortingStatus =
        shipment['sorting_status']?.toString() ?? 'pending';

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(15),
      decoration: LogisticsStyle.card,
      child: Row(
        children: [
          Container(
            height: 44,
            width: 44,
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(13),
            ),
            child: const Icon(
              Icons.inventory_2_outlined,
              color: LogisticsColors.primary,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  tracking,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: LogisticsStyle.cardTitle,
                ),
                const SizedBox(height: 3),
                Text(
                  customer,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: LogisticsStyle.small,
                ),
                const SizedBox(height: 3),
                Text(
                  'Status: $status • $sortingStatus',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: LogisticsStyle.small,
                ),
              ],
            ),
          ),
        ],
      ),
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
