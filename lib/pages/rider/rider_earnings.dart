import 'dart:async';

import 'package:flutter/material.dart';

import '../../services/api_services.dart';
import '../../styles/rider_style.dart';
import '../../utils/date_formatter.dart';
import '../../utils/money_formatter.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_loading_state.dart';
import '../../widgets/status_chip.dart';

class Earnings extends StatefulWidget {
  const Earnings({super.key});

  @override
  State<Earnings> createState() => _EarningsState();
}

class _EarningsState extends State<Earnings> {
  static const Duration _requestTimeout = Duration(seconds: 25);

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _hasLoaded = false;

  String? _errorMessage;

  Map<String, dynamic> _earnings = <String, dynamic>{};

  List<Map<String, dynamic>> _recentDeliveries = <Map<String, dynamic>>[];

  DateTime? _fromDate;
  DateTime? _toDate;

  DateTime? _appliedFromDate;
  DateTime? _appliedToDate;

  int _requestId = 0;

  @override
  void initState() {
    super.initState();
    _loadEarnings();
  }

  @override
  void dispose() {
    _requestId++;
    super.dispose();
  }

  // ============================================================
  // LOAD RIDER EARNINGS
  // ============================================================

  Future<void> _loadEarnings({bool refresh = false}) async {
    if (!mounted || _isRefreshing) return;

    final int requestId = ++_requestId;

    setState(() {
      _isLoading = !_hasLoaded;
      _isRefreshing = refresh || _hasLoaded;
      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> result = await ApiService.getRiderEarnings()
          .timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) return;

      if (result['success'] != true) {
        final String message = result['message']?.toString().trim() ?? '';

        _setLoadError(
          requestId,
          message.isNotEmpty ? message : 'Unable to load rider earnings.',
        );

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

        if (aDate != null) return -1;
        if (bDate != null) return 1;

        return 0;
      });

      if (!mounted || requestId != _requestId) return;

      setState(() {
        _earnings = earningsData;
        _recentDeliveries = recentDeliveries;

        _isLoading = false;
        _isRefreshing = false;
        _hasLoaded = true;
        _errorMessage = null;
      });
    } on TimeoutException {
      _setLoadError(
        requestId,
        'The server is taking too long to respond. '
        'Please check your connection and try again.',
      );
    } catch (error) {
      debugPrint('Rider earnings error: $error');

      _setLoadError(
        requestId,
        'Unable to connect to the server. '
        'Please check your internet connection and try again.',
      );
    }
  }

  void _setLoadError(int requestId, String message) {
    if (!mounted || requestId != _requestId) return;

    setState(() {
      _isLoading = false;
      _isRefreshing = false;
      _errorMessage = message;
    });
  }

  // ============================================================
  // EXTRACT API DATA
  // ============================================================

  Map<String, dynamic> _extractEarningsData(Map<String, dynamic> result) {
    final dynamic rawData = result['data'];

    if (rawData is Map) {
      return Map<String, dynamic>.from(rawData);
    }

    final dynamic earnings = result['earnings'];

    if (earnings is Map) {
      return Map<String, dynamic>.from(earnings);
    }

    return Map<String, dynamic>.from(result);
  }

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
      if (candidate is List) {
        return _toMapList(candidate);
      }

      if (candidate is Map && candidate['data'] is List) {
        return _toMapList(candidate['data']);
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

  // ============================================================
  // VALUE HELPERS
  // ============================================================

  int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();

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

  String _moneyValue(dynamic value) {
    if (value == null) return 'Not available';

    final double? amount = MoneyFormatter.value(value);

    if (amount == null) return 'Not available';

    return MoneyFormatter.peso(amount);
  }

  String _countValue(dynamic value) {
    if (value == null) return 'Not available';
    return _toInt(value).toString();
  }

  // ============================================================
  // DATE HELPERS
  // ============================================================

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

      final DateTime? parsed = DateFormatter.value(value);

      if (parsed != null) {
        return parsed.toLocal();
      }
    }

    return null;
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

    if (normalized.isNotEmpty) return normalized;

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

    if (origin.isNotEmpty) return origin;
    if (destination.isNotEmpty) return destination;

    return '';
  }

  String _normalize(String value) {
    return value.trim().toLowerCase().replaceAll(' ', '_').replaceAll('-', '_');
  }

  bool _toBool(dynamic value) {
    if (value == null) return false;
    if (value is bool) return value;
    if (value is num) return value != 0;

    final String text = value.toString().trim().toLowerCase();

    return text == '1' || text == 'true' || text == 'yes' || text == 'y';
  }

  // ============================================================
  // FILTERED EARNING RECORDS
  // ============================================================

  List<Map<String, dynamic>> get _filteredRecentDeliveries {
    if (_appliedFromDate == null && _appliedToDate == null) {
      return _recentDeliveries;
    }

    return _recentDeliveries.where((Map<String, dynamic> item) {
      final DateTime? itemDate = _extractRecordDate(item);

      // Records with no date cannot be verified as
      // belonging to the selected date range.
      if (itemDate == null) return false;

      final DateTime dateOnly = DateTime(
        itemDate.year,
        itemDate.month,
        itemDate.day,
      );

      if (_appliedFromDate != null) {
        final DateTime from = DateTime(
          _appliedFromDate!.year,
          _appliedFromDate!.month,
          _appliedFromDate!.day,
        );

        if (dateOnly.isBefore(from)) return false;
      }

      if (_appliedToDate != null) {
        final DateTime to = DateTime(
          _appliedToDate!.year,
          _appliedToDate!.month,
          _appliedToDate!.day,
        );

        if (dateOnly.isAfter(to)) return false;
      }

      return true;
    }).toList();
  }

  bool get _hasActiveFilter {
    return _appliedFromDate != null || _appliedToDate != null;
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.sizeOf(context).width;

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
            Text('Rider Wallet', style: RiderStyle.title),
            SizedBox(height: 2),
            Text('Wallet balance and rider earnings', style: RiderStyle.small),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Refresh earnings',
            onPressed: _isLoading || _isRefreshing
                ? null
                : () => _loadEarnings(refresh: true),
            icon: _isRefreshing
                ? const SizedBox(
                    height: 19,
                    width: 19,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: RiderColors.primary,
                    ),
                  )
                : const Icon(Icons.refresh_rounded, color: RiderColors.primary),
          ),
          const SizedBox(width: 5),
        ],
      ),
      body: SafeArea(
        top: false,
        child: RefreshIndicator(
          color: RiderColors.primary,
          onRefresh: () => _loadEarnings(refresh: true),
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
            child: _buildContent(),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // CONTENT
  // ============================================================

  Widget _buildContent() {
    if (_isLoading && !_hasLoaded) {
      return const Padding(
        padding: EdgeInsets.only(top: 150),
        child: AppLoadingState(
          message: 'Loading rider wallet...',
          color: RiderColors.primary,
        ),
      );
    }

    if (!_hasLoaded) {
      return Padding(
        padding: const EdgeInsets.only(top: 90),
        child: AppErrorState(
          title: 'Unable to Load Rider Wallet',
          message:
              _errorMessage ?? 'Unable to retrieve your earnings information.',
          color: RiderColors.primary,
          onRetry: _loadEarnings,
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (_isRefreshing) ...[
          const LinearProgressIndicator(
            color: RiderColors.primary,
            minHeight: 2,
          ),
          const SizedBox(height: 14),
        ],

        if (_errorMessage != null) ...[
          _refreshWarning(_errorMessage!),
          const SizedBox(height: 14),
        ],

        _walletOverview(),

        const SizedBox(height: 18),

        _dateFilterCard(),

        const SizedBox(height: 18),

        _summaryCards(),

        const SizedBox(height: 22),

        _earningsTrendCard(),

        const SizedBox(height: 22),

        _collectionDetails(),
      ],
    );
  }

  Widget _refreshWarning(String message) {
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
            Icons.wifi_off_rounded,
            color: RiderColors.primary,
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Showing previously loaded data. $message',
              style: RiderStyle.small,
            ),
          ),
          TextButton(
            onPressed: _isRefreshing
                ? null
                : () => _loadEarnings(refresh: true),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // WALLET OVERVIEW
  // ============================================================

  Widget _walletOverview() {
    final dynamic available = _firstValue(<String>[
      'available_balance',
      'wallet_balance',
      'available',
      'balance',
    ]);

    final dynamic pending = _firstValue(<String>[
      'pending_balance',
      'pending_withdrawal',
      'pending',
    ]);

    final dynamic total = _firstValue(<String>[
      'total_earnings',
      'earnings_total',
      'total',
    ]);

    final double? pendingAmount = MoneyFormatter.value(pending);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                height: 42,
                width: 42,
                decoration: BoxDecoration(
                  color: RiderColors.lightPurple,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.account_balance_wallet_outlined,
                  color: RiderColors.primary,
                  size: 22,
                ),
              ),
              const SizedBox(width: 11),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Rider Wallet', style: RiderStyle.sectionTitle),
                    SizedBox(height: 2),
                    Text(
                      'Server-recorded wallet balances',
                      style: RiderStyle.small,
                    ),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 18),

          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: RiderColors.lightPurple,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Available Balance', style: RiderStyle.small),

                const SizedBox(height: 5),

                Text(
                  _moneyValue(available),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: RiderColors.primary,
                    fontSize: 28,
                    fontWeight: FontWeight.w900,
                  ),
                ),

                const SizedBox(height: 7),

                const Text(
                  'Amount currently available in your rider wallet.',
                  style: RiderStyle.small,
                ),
              ],
            ),
          ),

          const SizedBox(height: 12),

          Row(
            children: [
              Expanded(
                child: _walletStatCard(
                  title: 'Pending',
                  value: _moneyValue(pending),
                  icon: Icons.hourglass_top_rounded,
                  background: RiderColors.lightOrange,
                ),
              ),

              const SizedBox(width: 10),

              Expanded(
                child: _walletStatCard(
                  title: 'Total Earned',
                  value: _moneyValue(total),
                  icon: Icons.payments_outlined,
                  background: RiderColors.lightGreen,
                ),
              ),
            ],
          ),

          if (pendingAmount != null && pendingAmount > 0) ...[
            const SizedBox(height: 12),

            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: RiderColors.background,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: RiderColors.border),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.info_outline_rounded,
                    color: RiderColors.warning,
                    size: 18,
                  ),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Pending wallet funds are shown separately '
                      'until the server makes them available.',
                      style: RiderStyle.small,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _walletStatCard({
    required String title,
    required String value,
    required IconData icon,
    required Color background,
  }) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: RiderColors.background,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: RiderColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 34,
            width: 34,
            decoration: BoxDecoration(
              color: background,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: RiderColors.primary, size: 18),
          ),

          const SizedBox(height: 11),

          Text(title, style: RiderStyle.small),

          const SizedBox(height: 3),

          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: RiderStyle.cardTitle,
          ),
        ],
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

          const SizedBox(height: 5),

          const Text(
            'Filter the loaded earning records by date. '
            'Wallet totals remain unchanged.',
            style: RiderStyle.small,
          ),

          const SizedBox(height: 14),

          Row(
            children: [
              Expanded(
                child: _dateField(
                  label: 'From Date',
                  date: _fromDate,
                  onTap: () => _pickDate(true),
                ),
              ),

              const SizedBox(width: 10),

              Expanded(
                child: _dateField(
                  label: 'To Date',
                  date: _toDate,
                  onTap: () => _pickDate(false),
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

              if (_fromDate != null || _toDate != null || _hasActiveFilter) ...[
                const SizedBox(width: 8),

                IconButton(
                  tooltip: 'Clear date filter',
                  onPressed: _clearFilter,
                  icon: const Icon(
                    Icons.filter_alt_off_outlined,
                    color: RiderColors.primary,
                  ),
                ),
              ],
            ],
          ),

          if (_hasActiveFilter) ...[
            const SizedBox(height: 10),

            Text(
              'Showing ${_filteredRecentDeliveries.length} '
              'record(s) in the selected period.',
              style: RiderStyle.small,
            ),
          ],
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
                    DateFormatter.shortDate(date, fallback: 'Select date'),
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
    final DateTime now = DateTime.now();

    final DateTime initial = from ? (_fromDate ?? now) : (_toDate ?? now);

    final DateTime? selected = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: now,
    );

    if (selected == null || !mounted) return;

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

    setState(() {
      _appliedFromDate = _fromDate;
      _appliedToDate = _toDate;
    });

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          content: Text(
            'Showing ${_filteredRecentDeliveries.length} '
            'earning record(s) in the selected period.',
          ),
        ),
      );
  }

  void _clearFilter() {
    setState(() {
      _fromDate = null;
      _toDate = null;
      _appliedFromDate = null;
      _appliedToDate = null;
    });
  }

  // ============================================================
  // EARNINGS SUMMARY
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
              value: _moneyValue(today),
              subtitle: "Today's earnings",
              icon: Icons.today_outlined,
              iconBackground: RiderColors.lightPurple,
            ),

            _summaryCard(
              title: 'This Week',
              value: _moneyValue(week),
              subtitle: 'Weekly earnings',
              icon: Icons.date_range_outlined,
              iconBackground: RiderColors.lightBlue,
            ),

            _summaryCard(
              title: 'This Month',
              value: _moneyValue(month),
              subtitle: 'Monthly earnings',
              icon: Icons.calendar_month_outlined,
              iconBackground: RiderColors.lightOrange,
            ),

            _summaryCard(
              title: 'Total',
              value: _moneyValue(total),
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
                value: _countValue(deliveries),
                icon: Icons.local_shipping_outlined,
              ),
            ),

            const SizedBox(width: 10),

            Expanded(
              child: _smallSummaryCard(
                title: 'Average',
                value: _moneyValue(average),
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
      padding: const EdgeInsets.all(13),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 36,
            width: 36,
            decoration: BoxDecoration(
              color: iconBackground,
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(icon, color: RiderColors.primary, size: 19),
          ),

          const Spacer(),

          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: RiderColors.textDark,
              fontSize: 17,
              fontWeight: FontWeight.w800,
            ),
          ),

          const SizedBox(height: 3),

          Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: RiderStyle.cardTitle,
          ),

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
    final int recordCount = _filteredRecentDeliveries.length;

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
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 22, horizontal: 16),
            decoration: BoxDecoration(
              color: RiderColors.background,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: RiderColors.border),
            ),
            child: Column(
              children: [
                const Icon(
                  Icons.bar_chart_rounded,
                  color: RiderColors.primary,
                  size: 35,
                ),

                const SizedBox(height: 8),

                const Text('Recorded Earnings', style: RiderStyle.cardTitle),

                const SizedBox(height: 5),

                Text(
                  recordCount == 0
                      ? _hasActiveFilter
                            ? 'No dated earning records match '
                                  'the selected period.'
                            : 'No earning records are available yet.'
                      : '$recordCount earning record(s) '
                            'available for the selected period.',
                  textAlign: TextAlign.center,
                  style: RiderStyle.small,
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
            padding: const EdgeInsets.all(10),
            decoration: RiderStyle.card,
            child: AppEmptyState(
              title: _hasActiveFilter
                  ? 'No Matching Earnings'
                  : 'No Earnings Activity',
              message: _hasActiveFilter
                  ? 'No dated earning records match '
                        'your selected date range.'
                  : 'Your rider earning records will '
                        'appear here when available.',
              icon: Icons.receipt_long_outlined,
              color: RiderColors.primary,
              compact: true,
              actionLabel: _hasActiveFilter ? 'Clear Filter' : null,
              onAction: _hasActiveFilter ? _clearFilter : null,
            ),
          )
        else
          ...items.map((Map<String, dynamic> item) => _earningItem(item)),
      ],
    );
  }

  // ============================================================
  // EARNING ITEM
  // ============================================================

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

    final String amount = _moneyValue(earningsValue);

    final DateTime? recordDate = _extractRecordDate(item);

    final String assignment = _assignmentLabel(item);

    final String route = _routeText(item);

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
                width: 42,
                height: 42,
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
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: RiderStyle.cardTitle,
                    ),

                    const SizedBox(height: 3),

                    Text(
                      assignment,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: RiderStyle.small,
                    ),

                    if (customer.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        customer,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: RiderStyle.small,
                      ),
                    ],
                  ],
                ),
              ),

              const SizedBox(width: 8),

              Flexible(
                child: Text(
                  amount,
                  textAlign: TextAlign.end,
                  style: const TextStyle(
                    color: RiderColors.earnings,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),

          if (route.isNotEmpty) ...[
            const SizedBox(height: 12),

            AppInfoRow(
              label: 'Route',
              value: route,
              icon: Icons.route_outlined,
              iconColor: RiderColors.primary,
              compact: true,
            ),
          ],

          const SizedBox(height: 12),

          Row(
            children: [
              const Expanded(child: Text('Status', style: RiderStyle.small)),

              StatusChip(status: status.isEmpty ? 'Unknown' : status),
            ],
          ),

          if (recordDate != null) ...[
            const SizedBox(height: 9),

            Row(
              children: [
                const Expanded(child: Text('Date', style: RiderStyle.small)),

                Text(
                  DateFormatter.dateOnly(recordDate),
                  style: RiderStyle.small,
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
