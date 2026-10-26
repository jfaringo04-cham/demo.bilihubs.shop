import 'package:flutter/material.dart';

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

  String? _errorMessage;

  Map<String, dynamic> _shipments = <String, dynamic>{};

  Map<String, dynamic> _riders = <String, dynamic>{};

  Map<String, dynamic> _hubs = <String, dynamic>{};

  int _alertsCount = 0;

  @override
  void initState() {
    super.initState();

    _loadStatistics();
  }

  // ============================================================
  // LOAD STATISTICS
  // ============================================================

  Future<void> _loadStatistics() async {
    if (mounted) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final Map<String, dynamic> result =
          await ApiService.getLogisticsDashboard();

      if (!mounted) return;

      if (result['success'] != true) {
        setState(() {
          _isLoading = false;

          _errorMessage =
              result['message']?.toString() ??
              'Unable to load parcel statistics.';
        });

        return;
      }

      final Map<String, dynamic> data = _extractDashboardData(result);

      setState(() {
        _shipments = _map(data['shipments']);

        _riders = _map(data['riders']);

        _hubs = _map(data['hubs']);

        _alertsCount = _number(data['alerts_count']);

        _isLoading = false;
        _errorMessage = null;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isLoading = false;
        _errorMessage = 'Unable to load parcel statistics: $e';
      });
    }
  }

  // ============================================================
  // RESPONSE PARSING
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

    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  // ============================================================
  // COMPLETION RATE
  // ============================================================

  double get _completion {
    final int total = _number(_shipments['total']);

    final int delivered = _number(_shipments['delivered']);

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
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        iconTheme: const IconThemeData(color: LogisticsColors.textDark),
        title: const Text('Parcel Statistics', style: LogisticsStyle.title),
        actions: [
          IconButton(
            onPressed: _isLoading ? null : _loadStatistics,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadStatistics,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          padding: const EdgeInsets.all(16),
          children: [
            if (_isLoading)
              const LinearProgressIndicator(color: LogisticsColors.primary),

            if (_errorMessage != null) ...[
              const SizedBox(height: 12),
              _errorCard(),
            ],

            const SizedBox(height: 18),

            // ====================================================
            // DELIVERY PERFORMANCE
            // ====================================================
            const Text(
              'Delivery Performance',
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
                DashboardCard(
                  title: 'Total',
                  value: '${_number(_shipments['total'])}',
                  icon: Icons.inventory_2_outlined,
                  color: LogisticsColors.primary,
                ),
                DashboardCard(
                  title: 'Pending',
                  value: '${_number(_shipments['pending'])}',
                  icon: Icons.pending_actions_rounded,
                  color: LogisticsColors.warning,
                ),
                DashboardCard(
                  title: 'In Transit',
                  value: '${_number(_shipments['in_transit'])}',
                  icon: Icons.local_shipping_outlined,
                  color: LogisticsColors.primary,
                ),
                DashboardCard(
                  title: 'Delivered',
                  value: '${_number(_shipments['delivered'])}',
                  icon: Icons.check_circle_outline_rounded,
                  color: LogisticsColors.success,
                ),
                DashboardCard(
                  title: 'Cancelled',
                  value: '${_number(_shipments['cancelled'])}',
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
            ),

            const SizedBox(height: 22),

            // ====================================================
            // SORTING PROGRESS
            // ====================================================
            Container(
              padding: const EdgeInsets.all(18),
              decoration: LogisticsStyle.card,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Sorting Progress',
                    style: LogisticsStyle.sectionTitle,
                  ),

                  const SizedBox(height: 14),

                  _row('Received', _number(_shipments['received'])),

                  _row('Scanned', _number(_shipments['scanned'])),

                  _row('Sorted', _number(_shipments['sorted'])),

                  _row('Staged', _number(_shipments['staged'])),
                ],
              ),
            ),

            const SizedBox(height: 18),

            // ====================================================
            // COMPLETION RATE
            // ====================================================
            Container(
              padding: const EdgeInsets.all(18),
              decoration: LogisticsStyle.card,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Completion Rate',
                    style: LogisticsStyle.sectionTitle,
                  ),

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
                    '${(_completion * 100).round()}% of shipments delivered',
                    style: LogisticsStyle.small,
                  ),
                ],
              ),
            ),

            const SizedBox(height: 18),

            // ====================================================
            // NETWORK
            // ====================================================
            Container(
              padding: const EdgeInsets.all(18),
              decoration: LogisticsStyle.card,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Network', style: LogisticsStyle.sectionTitle),

                  const SizedBox(height: 10),

                  _row('Total Riders', _number(_riders['total'])),

                  _row('Active Riders', _number(_riders['active'])),

                  _row('Available Riders', _number(_riders['available'])),

                  _row('Total Hubs', _number(_hubs['total'])),

                  _row('Active Hubs', _number(_hubs['active'])),
                ],
              ),
            ),

            const SizedBox(height: 22),
          ],
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
