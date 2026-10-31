import 'package:flutter/material.dart';

import '../../sevices/api_services.dart';
import '../../styles/rider_style.dart';

class Earnings extends StatefulWidget {
  const Earnings({super.key});

  @override
  State<Earnings> createState() => _EarningsState();
}

class _EarningsState extends State<Earnings> {
  bool _isLoading = true;
  bool _isRefreshing = false;

  String? _errorMessage;

  Map<String, dynamic> _earnings = <String, dynamic>{};

  List<Map<String, dynamic>> _recentDeliveries = <Map<String, dynamic>>[];

  DateTime? _fromDate;
  DateTime? _toDate;

  @override
  void initState() {
    super.initState();

    _loadEarnings();
  }

  // ============================================================
  // LOAD EARNINGS
  // ============================================================

  Future<void> _loadEarnings({bool refresh = false}) async {
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
      final Map<String, dynamic> result = await ApiService.getRiderEarnings();

      if (!mounted) {
        return;
      }

      if (result['success'] != true) {
        final String message = result['message']?.toString().trim() ?? '';

        setState(() {
          _earnings = <String, dynamic>{};

          _recentDeliveries = <Map<String, dynamic>>[];

          _isLoading = false;
          _isRefreshing = false;

          _errorMessage = message.isNotEmpty
              ? message
              : 'Unable to load rider earnings.';
        });

        return;
      }

      final Map<String, dynamic> earningsData = _extractEarningsData(result);

      final List<Map<String, dynamic>> recentDeliveries =
          _extractRecentDeliveries(earningsData);

      recentDeliveries.sort((Map<String, dynamic> a, Map<String, dynamic> b) {
        final DateTime? aDate = _extractRecordDate(a);

        final DateTime? bDate = _extractRecordDate(b);

        if (aDate != null && bDate != null) {
          return bDate.compareTo(aDate);
        }

        if (aDate != null) {
          return -1;
        }

        if (bDate != null) {
          return 1;
        }

        return 0;
      });

      setState(() {
        _earnings = earningsData;

        _recentDeliveries = recentDeliveries;

        _isLoading = false;
        _isRefreshing = false;

        _errorMessage = null;
      });
    } catch (error) {
      debugPrint('Rider earnings error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _earnings = <String, dynamic>{};

        _recentDeliveries = <Map<String, dynamic>>[];

        _isLoading = false;
        _isRefreshing = false;

        _errorMessage =
            'Unable to connect to the server. '
            'Please try again.';
      });
    }
  }

  // ============================================================
  // EXTRACT EARNINGS DATA
  // ============================================================

  Map<String, dynamic> _extractEarningsData(Map<String, dynamic> result) {
    final dynamic rawData = result['data'];

    if (rawData is Map<String, dynamic>) {
      return Map<String, dynamic>.from(rawData);
    }

    if (rawData is Map) {
      return Map<String, dynamic>.from(rawData);
    }

    final dynamic earnings = result['earnings'];

    if (earnings is Map) {
      return Map<String, dynamic>.from(earnings);
    }

    return Map<String, dynamic>.from(result);
  }

  // ============================================================
  // RECENT EARNING RECORDS
  // ============================================================

  List<Map<String, dynamic>> _extractRecentDeliveries(
    Map<String, dynamic> data,
  ) {
    final List<dynamic> candidates = <dynamic>[
      data['recent_deliveries'],
      data['deliveries'],
      data['recent_earnings'],
      data['transactions'],
      data['records'],
      data['earnings_history'],
    ];

    for (final dynamic candidate in candidates) {
      final List<Map<String, dynamic>> list = _toMapList(candidate);

      if (list.isNotEmpty) {
        return list;
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
  // VALUE HELPERS
  // ============================================================

  double? _toDouble(dynamic value) {
    if (value is num) {
      return value.toDouble();
    }

    if (value == null) {
      return null;
    }

    final String cleaned = value
        .toString()
        .replaceAll(',', '')
        .replaceAll('₱', '')
        .trim();

    return double.tryParse(cleaned);
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

  dynamic _firstValue(List<String> keys) {
    for (final String key in keys) {
      if (_earnings.containsKey(key) && _earnings[key] != null) {
        return _earnings[key];
      }
    }

    return null;
  }

  String _firstText(Map<String, dynamic> item, List<String> keys) {
    for (final String key in keys) {
      final String value = item[key]?.toString().trim() ?? '';

      if (value.isNotEmpty && value.toLowerCase() != 'null') {
        return value;
      }
    }

    return '';
  }

  String _money(dynamic value) {
    final double? amount = _toDouble(value);

    if (amount == null) {
      return '₱0.00';
    }

    return '₱${amount.toStringAsFixed(2)}';
  }

  // ============================================================
  // DATE HELPERS
  // ============================================================

  String _dateText(DateTime? date) {
    if (date == null) {
      return 'Select date';
    }

    final String month = date.month.toString().padLeft(2, '0');

    final String day = date.day.toString().padLeft(2, '0');

    return '$month/$day/${date.year}';
  }

  DateTime? _extractRecordDate(Map<String, dynamic> item) {
    final List<dynamic> candidates = <dynamic>[
      item['delivered_at'],
      item['linehaul_completed_at'],
      item['completed_at'],
      item['updated_at'],
      item['created_at'],
      item['date'],
      item['order_date'],
    ];

    for (final dynamic candidate in candidates) {
      final String value = candidate?.toString().trim() ?? '';

      if (value.isEmpty || value.toLowerCase() == 'null') {
        continue;
      }

      final DateTime? parsed = DateTime.tryParse(value);

      if (parsed != null) {
        return parsed.toLocal();
      }
    }

    return null;
  }

  String _formatDateTime(DateTime date) {
    const List<String> months = <String>[
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];

    final String month = months[date.month - 1];

    return '$month ${date.day}, ${date.year}';
  }

  // ============================================================
  // ASSIGNMENT TYPE
  // ============================================================

  String _assignmentType(Map<String, dynamic> item) {
    final String raw = _firstText(item, <String>[
      'assignment_type',
      'earning_type',
      'task_type',
      'delivery_type',
    ]);

    final String normalized = _normalize(raw);

    if (normalized.isNotEmpty) {
      return normalized;
    }

    if (_toBool(item['is_linehaul_rider'])) {
      return 'linehaul';
    }

    if (_toBool(item['is_pickup_rider'])) {
      return 'seller_pickup';
    }

    if (_toBool(item['is_final_mile_rider'])) {
      return 'final_mile';
    }

    return '';
  }

  String _assignmentLabel(Map<String, dynamic> item) {
    switch (_assignmentType(item)) {
      case 'seller_pickup':
        return 'Seller Pickup';

      case 'linehaul':
      case 'line_haul':
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
        return 'Rider Earning';
    }
  }

  IconData _assignmentIcon(Map<String, dynamic> item) {
    final String type = _assignmentType(item);

    if (type == 'seller_pickup' ||
        type == 'pickup_and_delivery' ||
        type == 'pickup_and_linehaul') {
      return Icons.inventory_2_outlined;
    }

    if (type == 'linehaul' ||
        type == 'line_haul' ||
        type == 'linehaul_and_delivery' ||
        type == 'pickup_linehaul_and_delivery') {
      return Icons.multiple_stop_rounded;
    }

    return Icons.delivery_dining_rounded;
  }

  Color _assignmentBackground(Map<String, dynamic> item) {
    final String type = _assignmentType(item);

    if (type == 'seller_pickup') {
      return RiderColors.lightOrange;
    }

    if (type == 'linehaul' ||
        type == 'line_haul' ||
        type == 'pickup_and_linehaul' ||
        type == 'linehaul_and_delivery') {
      return RiderColors.lightBlue;
    }

    return RiderColors.lightGreen;
  }

  String _routeText(Map<String, dynamic> item) {
    final String origin = _firstText(item, <String>[
      'origin_hub_name',
      'origin_hub',
    ]);

    final String destination = _firstText(item, <String>[
      'destination_hub_name',
      'destination_hub',
    ]);

    if (origin.isNotEmpty && destination.isNotEmpty) {
      return '$origin → $destination';
    }

    if (origin.isNotEmpty) {
      return origin;
    }

    if (destination.isNotEmpty) {
      return destination;
    }

    return '';
  }

  // ============================================================
  // STATUS HELPERS
  // ============================================================

  String _normalize(String value) {
    return value.trim().toLowerCase().replaceAll(' ', '_').replaceAll('-', '_');
  }

  String _formatStatus(String status) {
    final String cleaned = status
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
    switch (_normalize(status)) {
      case 'delivered':
      case 'completed':
      case 'paid':
      case 'credited':
      case 'released':
        return RiderColors.success;

      case 'cancelled':
      case 'failed':
      case 'delivery_failed':
      case 'rejected':
        return RiderColors.danger;

      case 'pending':
      case 'processing':
      case 'assigned':
      case 'awaiting_payment':
        return RiderColors.warning;

      default:
        return RiderColors.primary;
    }
  }

  bool _toBool(dynamic value) {
    if (value == null) {
      return false;
    }

    if (value is bool) {
      return value;
    }

    if (value is num) {
      return value != 0;
    }

    final String text = value.toString().trim().toLowerCase();

    return text == '1' || text == 'true' || text == 'yes' || text == 'y';
  }

  // ============================================================
  // FILTERED RECORDS
  // ============================================================

  List<Map<String, dynamic>> get _filteredRecentDeliveries {
    if (_fromDate == null && _toDate == null) {
      return _recentDeliveries;
    }

    return _recentDeliveries.where((Map<String, dynamic> item) {
      final DateTime? itemDate = _extractRecordDate(item);

      if (itemDate == null) {
        return true;
      }

      final DateTime dateOnly = DateTime(
        itemDate.year,
        itemDate.month,
        itemDate.day,
      );

      if (_fromDate != null) {
        final DateTime from = DateTime(
          _fromDate!.year,
          _fromDate!.month,
          _fromDate!.day,
        );

        if (dateOnly.isBefore(from)) {
          return false;
        }
      }

      if (_toDate != null) {
        final DateTime to = DateTime(
          _toDate!.year,
          _toDate!.month,
          _toDate!.day,
        );

        if (dateOnly.isAfter(to)) {
          return false;
        }
      }

      return true;
    }).toList();
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.of(context).size.width;

    final double horizontalPadding = width < 380 ? 14 : 18;

    return Scaffold(
      backgroundColor: RiderColors.background,
      appBar: AppBar(
        backgroundColor: RiderColors.white,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        centerTitle: false,
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Rider Earnings', style: RiderStyle.title),
            SizedBox(height: 2),
            Text('Server-calculated rider income', style: RiderStyle.small),
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
                    color: RiderColors.primary,
                  ),
                ),
              ),
            )
          else
            IconButton(
              tooltip: 'Refresh',
              onPressed: _isLoading
                  ? null
                  : () {
                      _loadEarnings(refresh: true);
                    },
              icon: const Icon(
                Icons.refresh_rounded,
                color: RiderColors.primary,
              ),
            ),
          const SizedBox(width: 5),
        ],
      ),
      body: SafeArea(
        top: false,
        child: RefreshIndicator(
          onRefresh: () {
            return _loadEarnings(refresh: true);
          },
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: EdgeInsets.fromLTRB(
              horizontalPadding,
              14,
              horizontalPadding,
              30,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (_isLoading) _loadingCard(),

                if (!_isLoading && _errorMessage != null) _errorCard(),

                if (_isLoading || _errorMessage != null)
                  const SizedBox(height: 18),

                _dateFilterCard(),

                const SizedBox(height: 18),

                _summaryCards(),

                const SizedBox(height: 22),

                _earningsTrendCard(),

                const SizedBox(height: 22),

                _collectionDetails(),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // DATE FILTER
  // ============================================================

  Widget _dateFilterCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Earnings Period', style: RiderStyle.sectionTitle),

          const SizedBox(height: 14),

          Row(
            children: [
              Expanded(
                child: _dateField(
                  label: 'From Date',
                  date: _fromDate,
                  onTap: () {
                    _pickDate(true);
                  },
                ),
              ),

              const SizedBox(width: 10),

              Expanded(
                child: _dateField(
                  label: 'To Date',
                  date: _toDate,
                  onTap: () {
                    _pickDate(false);
                  },
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _applyFilter,
                  icon: const Icon(Icons.filter_alt_outlined, size: 18),
                  label: const Text('Apply Filter'),
                  style: RiderStyle.primaryButton,
                ),
              ),

              if (_fromDate != null || _toDate != null) ...[
                const SizedBox(width: 8),

                IconButton(
                  tooltip: 'Clear dates',
                  onPressed: _clearFilter,
                  icon: const Icon(
                    Icons.filter_alt_off_outlined,
                    color: RiderColors.primary,
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  Widget _dateField({
    required String label,
    required DateTime? date,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: RiderColors.background,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: RiderColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: RiderStyle.small),

            const SizedBox(height: 6),

            Row(
              children: [
                Expanded(
                  child: Text(
                    _dateText(date),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: RiderColors.textDark,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),

                const SizedBox(width: 5),

                const Icon(
                  Icons.calendar_today_outlined,
                  color: RiderColors.primary,
                  size: 17,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _pickDate(bool from) async {
    final DateTime initial = from
        ? (_fromDate ?? DateTime.now())
        : (_toDate ?? DateTime.now());

    final DateTime? selected = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
    );

    if (selected == null || !mounted) {
      return;
    }

    setState(() {
      if (from) {
        _fromDate = selected;

        if (_toDate != null && selected.isAfter(_toDate!)) {
          _toDate = selected;
        }
      } else {
        _toDate = selected;

        if (_fromDate != null && selected.isBefore(_fromDate!)) {
          _fromDate = selected;
        }
      }
    });
  }

  void _applyFilter() {
    if (_fromDate == null && _toDate == null) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            behavior: SnackBarBehavior.floating,
            content: Text('Select a date range first.'),
          ),
        );

      return;
    }

    setState(() {});

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          content: Text(
            'Showing '
            '${_filteredRecentDeliveries.length} '
            'earning record(s) in the '
            'selected period.',
          ),
        ),
      );
  }

  void _clearFilter() {
    setState(() {
      _fromDate = null;
      _toDate = null;
    });
  }

  // ============================================================
  // SUMMARY
  // ============================================================

  Widget _summaryCards() {
    final dynamic today = _firstValue(<String>[
      'today_earnings',
      'today',
      'daily_earnings',
    ]);

    final dynamic week = _firstValue(<String>[
      'week_earnings',
      'weekly_earnings',
      'this_week',
    ]);

    final dynamic month = _firstValue(<String>[
      'month_earnings',
      'monthly_earnings',
      'this_month',
    ]);

    final dynamic total = _firstValue(<String>[
      'total_earnings',
      'earnings_total',
      'total',
    ]);

    final dynamic deliveries = _firstValue(<String>[
      'week_deliveries',
      'completed_deliveries',
      'delivery_count',
      'deliveries_count',
    ]);

    final dynamic average = _firstValue(<String>[
      'average_earning',
      'average_earnings',
      'average_per_delivery',
    ]);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Earnings Summary', style: RiderStyle.sectionTitle),

        const SizedBox(height: 12),

        GridView.count(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisCount: 2,
          crossAxisSpacing: 10,
          mainAxisSpacing: 10,
          childAspectRatio: 1.25,
          children: [
            _summaryCard(
              title: 'Today',
              value: _money(today),
              subtitle: 'Today\'s earnings',
              icon: Icons.today_outlined,
              iconBackground: RiderColors.lightPurple,
            ),

            _summaryCard(
              title: 'This Week',
              value: _money(week),
              subtitle: 'Weekly earnings',
              icon: Icons.date_range_outlined,
              iconBackground: RiderColors.lightBlue,
            ),

            _summaryCard(
              title: 'This Month',
              value: _money(month),
              subtitle: 'Monthly earnings',
              icon: Icons.calendar_month_outlined,
              iconBackground: RiderColors.lightOrange,
            ),

            _summaryCard(
              title: 'Total',
              value: _money(total),
              subtitle: 'Total rider earnings',
              icon: Icons.account_balance_wallet_outlined,
              iconBackground: RiderColors.lightGreen,
            ),
          ],
        ),

        const SizedBox(height: 10),

        Row(
          children: [
            Expanded(
              child: _smallSummaryCard(
                title: 'Completed Tasks',
                value: deliveries == null ? '0' : _toInt(deliveries).toString(),
                icon: Icons.local_shipping_outlined,
              ),
            ),

            const SizedBox(width: 10),

            Expanded(
              child: _smallSummaryCard(
                title: 'Average',
                value: _money(average),
                icon: Icons.trending_up_rounded,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _summaryCard({
    required String title,
    required String value,
    required String subtitle,
    required IconData icon,
    required Color iconBackground,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 38,
            width: 38,
            decoration: BoxDecoration(
              color: iconBackground,
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(icon, color: RiderColors.primary, size: 20),
          ),

          const Spacer(),

          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: RiderColors.textDark,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),

          const SizedBox(height: 2),

          Text(title, style: RiderStyle.cardTitle),

          const SizedBox(height: 2),

          Text(
            subtitle,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: RiderStyle.small,
          ),
        ],
      ),
    );
  }

  Widget _smallSummaryCard({
    required String title,
    required String value,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: RiderStyle.card,
      child: Row(
        children: [
          Container(
            height: 38,
            width: 38,
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
                Text(title, style: RiderStyle.small),

                const SizedBox(height: 2),

                Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: RiderStyle.cardTitle,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // EARNINGS ACTIVITY
  // ============================================================

  Widget _earningsTrendCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Earnings Activity', style: RiderStyle.sectionTitle),

          const SizedBox(height: 4),

          const Text(
            'Server-recorded rider earning activity',
            style: RiderStyle.small,
          ),

          const SizedBox(height: 18),

          Container(
            height: 145,
            width: double.infinity,
            decoration: BoxDecoration(
              color: RiderColors.background,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: RiderColors.border),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(
                  Icons.bar_chart_rounded,
                  color: RiderColors.primary,
                  size: 35,
                ),

                const SizedBox(height: 8),

                const Text('Recorded Earnings', style: RiderStyle.cardTitle),

                const SizedBox(height: 4),

                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Text(
                    _filteredRecentDeliveries.isEmpty
                        ? 'No dated earning records '
                              'are available for the '
                              'selected period.'
                        : '${_filteredRecentDeliveries.length} '
                              'earning record(s) found '
                              'for the current period.',
                    textAlign: TextAlign.center,
                    style: RiderStyle.small,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // EARNING DETAILS
  // ============================================================

  Widget _collectionDetails() {
    final List<Map<String, dynamic>> items = _filteredRecentDeliveries;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Earning Details', style: RiderStyle.sectionTitle),

        const SizedBox(height: 12),

        if (items.isEmpty)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(22),
            decoration: RiderStyle.card,
            child: const Column(
              children: [
                Icon(
                  Icons.receipt_long_outlined,
                  color: RiderColors.textGrey,
                  size: 34,
                ),
                SizedBox(height: 8),
                Text(
                  'No earnings activity available yet.',
                  textAlign: TextAlign.center,
                  style: RiderStyle.small,
                ),
              ],
            ),
          )
        else
          ...items.map((Map<String, dynamic> item) {
            return _earningItem(item);
          }),
      ],
    );
  }

  Widget _earningItem(Map<String, dynamic> item) {
    final String tracking = _firstText(item, <String>[
      'tracking_number',
      'tracking_code',
      'waybill_number',
    ]);

    final String status = _firstText(item, <String>[
      'status',
      'earning_status',
      'delivery_status',
      'shipment_status',
    ]);

    final String customer = _firstText(item, <String>[
      'customer_name',
      'recipient_name',
      'buyer_name',
    ]);

    final dynamic earningsValue =
        item['earnings'] ??
        item['delivery_earnings'] ??
        item['amount'] ??
        item['earning_amount'] ??
        item['delivery_fee'];

    final String amount = _money(earningsValue);

    final DateTime? recordDate = _extractRecordDate(item);

    final String assignment = _assignmentLabel(item);

    final String route = _routeText(item);

    final Color statusColor = _statusColor(status);

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(15),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                height: 42,
                width: 42,
                decoration: BoxDecoration(
                  color: _assignmentBackground(item),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  _assignmentIcon(item),
                  color: RiderColors.primary,
                  size: 21,
                ),
              ),

              const SizedBox(width: 11),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      tracking.isEmpty ? 'Tracking unavailable' : tracking,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: RiderStyle.cardTitle,
                    ),

                    const SizedBox(height: 3),

                    Text(
                      assignment,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: RiderStyle.small,
                    ),

                    if (customer.isNotEmpty) ...[
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

              Text(
                amount,
                style: const TextStyle(
                  color: RiderColors.earnings,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),

          if (route.isNotEmpty) ...[
            const SizedBox(height: 12),

            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  Icons.route_outlined,
                  size: 16,
                  color: RiderColors.primary,
                ),

                const SizedBox(width: 6),

                Expanded(child: Text(route, style: RiderStyle.small)),
              ],
            ),
          ],

          const SizedBox(height: 12),

          Row(
            children: [
              const Text('Status', style: RiderStyle.small),

              const Spacer(),

              Text(
                _formatStatus(status),
                style: TextStyle(
                  color: statusColor,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),

          if (recordDate != null) ...[
            const SizedBox(height: 8),

            Row(
              children: [
                const Text('Date', style: RiderStyle.small),

                const Spacer(),

                Text(_formatDateTime(recordDate), style: RiderStyle.small),
              ],
            ),
          ],
        ],
      ),
    );
  }

  // ============================================================
  // LOADING / ERROR
  // ============================================================

  Widget _loadingCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: RiderStyle.card,
      child: const Row(
        children: [
          SizedBox(
            height: 22,
            width: 22,
            child: CircularProgressIndicator(
              strokeWidth: 2.2,
              color: RiderColors.primary,
            ),
          ),

          SizedBox(width: 12),

          Expanded(child: Text('Loading earnings...', style: RiderStyle.small)),
        ],
      ),
    );
  }

  Widget _errorCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: RiderStyle.card,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.info_outline_rounded, color: RiderColors.warning),

          const SizedBox(width: 10),

          Expanded(
            child: Text(
              _errorMessage ?? 'Unable to load earnings.',
              style: RiderStyle.small,
            ),
          ),

          IconButton(
            onPressed: () {
              _loadEarnings(refresh: true);
            },
            icon: const Icon(Icons.refresh_rounded, color: RiderColors.primary),
          ),
        ],
      ),
    );
  }
}
