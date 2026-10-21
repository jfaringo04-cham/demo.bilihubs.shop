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

  Future<void> _loadEarnings() async {
    if (mounted) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    final result = await ApiService.getRiderEarnings();

    if (!mounted) return;

    if (result['success'] == true) {
      final data = result['data'];

      Map<String, dynamic> earningsData = <String, dynamic>{};

      if (data is Map<String, dynamic>) {
        earningsData = data;
      } else if (data is Map) {
        earningsData = Map<String, dynamic>.from(data);
      }

      final recentData = earningsData['recent_deliveries'];

      final List<Map<String, dynamic>> parsedRecent = <Map<String, dynamic>>[];

      if (recentData is List) {
        for (final item in recentData) {
          if (item is Map<String, dynamic>) {
            parsedRecent.add(item);
          } else if (item is Map) {
            parsedRecent.add(Map<String, dynamic>.from(item));
          }
        }
      }

      setState(() {
        _earnings = earningsData;

        _recentDeliveries = parsedRecent;

        _isLoading = false;

        _errorMessage = null;
      });

      return;
    }

    setState(() {
      _earnings = {};

      _recentDeliveries = [];

      _isLoading = false;

      _errorMessage =
          result['message']?.toString() ?? 'Unable to load rider earnings.';
    });
  }

  // ============================================================
  // HELPERS
  // ============================================================

  double? _toDouble(dynamic value) {
    if (value is num) {
      return value.toDouble();
    }

    return double.tryParse(value?.toString() ?? '');
  }

  int _toInt(dynamic value) {
    if (value is int) {
      return value;
    }

    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  String _money(dynamic value) {
    final amount = _toDouble(value);

    if (amount == null) {
      return '—';
    }

    return '₱${amount.toStringAsFixed(2)}';
  }

  String _dateText(DateTime? date) {
    if (date == null) {
      return 'Select date';
    }

    final month = date.month.toString().padLeft(2, '0');

    final day = date.day.toString().padLeft(2, '0');

    return '$month/$day/${date.year}';
  }

  String _formatStatus(String status) {
    final cleaned = status.trim().replaceAll('_', ' ');

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

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    final horizontalPadding = width < 380 ? 14.0 : 18.0;

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

            Text('Delivery income summary', style: RiderStyle.small),
          ],
        ),

        actions: [
          IconButton(
            tooltip: 'Refresh',

            onPressed: _isLoading ? null : _loadEarnings,

            icon: const Icon(Icons.refresh_rounded, color: RiderColors.primary),
          ),

          const SizedBox(width: 5),
        ],
      ),

      body: SafeArea(
        top: false,

        child: RefreshIndicator(
          onRefresh: _loadEarnings,

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

          SizedBox(
            width: double.infinity,

            height: 46,

            child: ElevatedButton.icon(
              onPressed: _applyFilter,

              icon: const Icon(Icons.filter_alt_outlined, size: 18),

              label: const Text('Apply Filter'),

              style: RiderStyle.primaryButton,
            ),
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
    final initial = from
        ? (_fromDate ?? DateTime.now())
        : (_toDate ?? DateTime.now());

    final selected = await showDatePicker(
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
      } else {
        _toDate = selected;
      }
    });
  }

  void _applyFilter() {
    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        behavior: SnackBarBehavior.floating,

        content: Text(
          'Date filtering will be connected once the earnings API supports date parameters.',
        ),
      ),
    );
  }

  // ============================================================
  // SUMMARY
  // ============================================================

  Widget _summaryCards() {
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

              value: _money(_earnings['today_earnings']),

              subtitle: 'Today\'s earnings',

              icon: Icons.today_outlined,

              iconBackground: RiderColors.lightPurple,
            ),

            _summaryCard(
              title: 'This Week',

              value: _money(_earnings['week_earnings']),

              subtitle: 'Weekly earnings',

              icon: Icons.date_range_outlined,

              iconBackground: RiderColors.lightBlue,
            ),

            _summaryCard(
              title: 'This Month',

              value: _money(_earnings['month_earnings']),

              subtitle: 'Monthly earnings',

              icon: Icons.calendar_month_outlined,

              iconBackground: RiderColors.lightOrange,
            ),

            _summaryCard(
              title: 'Total',

              value: _money(_earnings['total_earnings']),

              subtitle: 'Total earnings',

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
                title: 'Deliveries',

                value: _earnings.containsKey('week_deliveries')
                    ? _toInt(_earnings['week_deliveries']).toString()
                    : '—',

                icon: Icons.local_shipping_outlined,
              ),
            ),

            const SizedBox(width: 10),

            Expanded(
              child: _smallSummaryCard(
                title: 'Average',

                value: _money(_earnings['average_earning']),

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
  // TREND
  // ============================================================

  Widget _earningsTrendCard() {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.all(18),

      decoration: RiderStyle.card,

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          const Text('Daily Earnings Trend', style: RiderStyle.sectionTitle),

          const SizedBox(height: 4),

          const Text(
            'Your delivery earnings activity',

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

            child: const Column(
              mainAxisAlignment: MainAxisAlignment.center,

              children: [
                Icon(
                  Icons.bar_chart_rounded,

                  color: RiderColors.primary,

                  size: 35,
                ),

                SizedBox(height: 8),

                Text('Earnings Trend', style: RiderStyle.cardTitle),

                SizedBox(height: 4),

                Padding(
                  padding: EdgeInsets.symmetric(horizontal: 20),

                  child: Text(
                    'Daily chart will appear when the API provides dated earnings records.',

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
  // COLLECTION / EARNING DETAILS
  // ============================================================

  Widget _collectionDetails() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        const Text('Earning Details', style: RiderStyle.sectionTitle),

        const SizedBox(height: 12),

        if (_recentDeliveries.isEmpty)
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
          ..._recentDeliveries.map((item) => _earningItem(item)),
      ],
    );
  }

  Widget _earningItem(Map<String, dynamic> item) {
    final tracking = item['tracking_number']?.toString().trim() ?? '';

    final status = item['status']?.toString().trim() ?? '';

    final customer = item['customer_name']?.toString().trim() ?? '';

    final amount = _money(item['earnings']);

    return Container(
      width: double.infinity,

      margin: const EdgeInsets.only(bottom: 12),

      padding: const EdgeInsets.all(15),

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
                  color: RiderColors.lightGreen,

                  borderRadius: BorderRadius.circular(12),
                ),

                child: const Icon(
                  Icons.local_shipping_outlined,

                  color: RiderColors.success,

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
                  color: RiderColors.primary,

                  fontSize: 16,

                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          Row(
            children: [
              const Text('Status', style: RiderStyle.small),

              const Spacer(),

              Text(
                _formatStatus(status),

                style: const TextStyle(
                  color: RiderColors.success,

                  fontSize: 11,

                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
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
            onPressed: _loadEarnings,

            icon: const Icon(Icons.refresh_rounded, color: RiderColors.primary),
          ),
        ],
      ),
    );
  }
}
