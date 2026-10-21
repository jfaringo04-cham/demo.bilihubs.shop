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

  Map<String, dynamic> _overview = <String, dynamic>{};

  Map<String, dynamic> _activity = <String, dynamic>{};

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

    final result = await ApiService.getLogisticsDashboard();

    if (!mounted) return;

    if (result['success'] == true) {
      final overviewData = result['overview'];

      final activityData = result['activity'];

      setState(() {
        _overview = overviewData is Map
            ? Map<String, dynamic>.from(overviewData)
            : <String, dynamic>{};

        _activity = activityData is Map
            ? Map<String, dynamic>.from(activityData)
            : <String, dynamic>{};

        _isLoading = false;
        _errorMessage = null;
      });

      return;
    }

    setState(() {
      _overview = {};
      _activity = {};

      _isLoading = false;

      _errorMessage =
          result['message']?.toString() ?? 'Unable to load parcel statistics.';
    });
  }

  // ============================================================
  // NUMBER HELPERS
  // ============================================================

  int? _toInt(dynamic value) {
    if (value == null) {
      return null;
    }

    if (value is int) {
      return value;
    }

    if (value is double) {
      return value.toInt();
    }

    return int.tryParse(value.toString());
  }

  String _number(dynamic value) {
    final parsed = _toInt(value);

    if (parsed == null) {
      return '—';
    }

    return parsed.toString();
  }

  // ============================================================
  // DERIVED VALUES
  // ============================================================

  int? get _totalParcels => _toInt(_overview['total_parcels']);

  int? get _delivered => _toInt(_overview['delivered']);

  int? get _inTransit => _toInt(_overview['in_transit']);

  int? get _waiting => _toInt(_overview['waiting_delivery']);

  int? get _scannedToday => _toInt(_activity['scanned_today']);

  int? get _remaining {
    final total = _totalParcels;

    final delivered = _delivered;

    if (total == null || delivered == null) {
      return null;
    }

    final remaining = total - delivered;

    return remaining < 0 ? 0 : remaining;
  }

  double? get _completionProgress {
    final activityCompletion = _activity['delivery_completion'];

    if (activityCompletion != null) {
      final parsed = double.tryParse(activityCompletion.toString());

      if (parsed != null) {
        if (parsed > 1) {
          return (parsed / 100).clamp(0.0, 1.0);
        }

        return parsed.clamp(0.0, 1.0);
      }
    }

    final total = _totalParcels;

    final delivered = _delivered;

    if (total == null || delivered == null || total <= 0) {
      return null;
    }

    return (delivered / total).clamp(0.0, 1.0);
  }

  String get _completionText {
    final progress = _completionProgress;

    if (progress == null) {
      return 'Completion data is not available yet.';
    }

    final percentage = (progress * 100).round();

    return '$percentage% of assigned parcels completed';
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

        elevation: 0,

        surfaceTintColor: Colors.transparent,

        iconTheme: const IconThemeData(color: Colors.black),

        title: const Text('Parcel Statistics', style: LogisticsStyle.title),

        actions: [
          IconButton(
            tooltip: 'Refresh',

            onPressed: _isLoading ? null : _loadStatistics,

            icon: const Icon(
              Icons.refresh_rounded,

              color: LogisticsColors.primary,
            ),
          ),

          const SizedBox(width: 6),
        ],
      ),

      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _loadStatistics,

          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),

            padding: const EdgeInsets.all(16),

            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                if (_isLoading) _loadingCard(),

                if (!_isLoading && _errorMessage != null) ...[
                  _errorCard(),

                  const SizedBox(height: 20),
                ],

                const Text(
                  'Delivery Performance',

                  style: LogisticsStyle.sectionTitle,
                ),

                const SizedBox(height: 15),

                GridView.count(
                  shrinkWrap: true,

                  physics: const NeverScrollableScrollPhysics(),

                  crossAxisCount: 2,

                  crossAxisSpacing: 12,

                  mainAxisSpacing: 12,

                  childAspectRatio: 1.3,

                  children: [
                    DashboardCard(
                      title: 'Assigned Parcels',

                      value: _number(_totalParcels),

                      icon: Icons.inventory_2_outlined,

                      color: Colors.blue,
                    ),

                    DashboardCard(
                      title: 'Scanned Today',

                      value: _number(_scannedToday),

                      icon: Icons.qr_code_scanner,

                      color: Colors.orange,
                    ),

                    DashboardCard(
                      title: 'Delivered',

                      value: _number(_delivered),

                      icon: Icons.check_circle_outline,

                      color: Colors.green,
                    ),

                    DashboardCard(
                      title: 'Remaining',

                      value: _number(_remaining),

                      icon: Icons.pending_actions,

                      color: Colors.red,
                    ),
                  ],
                ),

                const SizedBox(height: 30),

                // ============================================================
                // COMPLETION PROGRESS
                // ============================================================
                Container(
                  width: double.infinity,

                  padding: const EdgeInsets.all(20),

                  decoration: LogisticsStyle.card,

                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      const Text(
                        'Completion Progress',

                        style: LogisticsStyle.sectionTitle,
                      ),

                      const SizedBox(height: 20),

                      LinearProgressIndicator(
                        value: _completionProgress ?? 0,

                        minHeight: 12,

                        borderRadius: BorderRadius.circular(20),

                        backgroundColor: LogisticsColors.lightPurple,

                        color: LogisticsColors.primary,
                      ),

                      const SizedBox(height: 12),

                      Text(_completionText, style: LogisticsStyle.small),
                    ],
                  ),
                ),

                const SizedBox(height: 25),

                // ============================================================
                // TODAY'S SUMMARY
                // ============================================================
                Container(
                  width: double.infinity,

                  padding: const EdgeInsets.all(20),

                  decoration: LogisticsStyle.card,

                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      const Text(
                        "Today's Summary",

                        style: LogisticsStyle.sectionTitle,
                      ),

                      const SizedBox(height: 15),

                      _summaryRow(
                        Icons.qr_code,

                        'Parcels scanned today',

                        _number(_scannedToday),
                      ),

                      _summaryRow(
                        Icons.local_shipping,

                        'Currently in transit',

                        _number(_inTransit),
                      ),

                      _summaryRow(
                        Icons.pending_actions_rounded,

                        'Waiting delivery',

                        _number(_waiting),
                      ),

                      _summaryRow(
                        Icons.done_all,

                        'Delivered today',

                        _number(_activity['delivered_today']),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 25),
              ],
            ),
          ),
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

      margin: const EdgeInsets.only(bottom: 20),

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
              'Loading parcel statistics...',

              style: LogisticsStyle.small,
            ),
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

      padding: const EdgeInsets.all(16),

      decoration: LogisticsStyle.card,

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          const Icon(
            Icons.info_outline_rounded,

            color: LogisticsColors.warning,
          ),

          const SizedBox(width: 10),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                const Text(
                  'Statistics unavailable',

                  style: LogisticsStyle.cardTitle,
                ),

                const SizedBox(height: 4),

                Text(
                  _errorMessage ?? 'Unable to load statistics.',

                  style: LogisticsStyle.small,
                ),
              ],
            ),
          ),

          IconButton(
            tooltip: 'Retry',

            onPressed: _loadStatistics,

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
  // SUMMARY ROW
  // ============================================================

  Widget _summaryRow(IconData icon, String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15),

      child: Row(
        children: [
          Container(
            height: 38,
            width: 38,

            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,

              borderRadius: BorderRadius.circular(11),
            ),

            child: Icon(icon, color: LogisticsColors.primary, size: 20),
          ),

          const SizedBox(width: 12),

          Expanded(child: Text(title, style: LogisticsStyle.body)),

          Text(
            value,

            style: const TextStyle(
              color: LogisticsColors.primary,

              fontWeight: FontWeight.bold,

              fontSize: 15,
            ),
          ),
        ],
      ),
    );
  }
}
