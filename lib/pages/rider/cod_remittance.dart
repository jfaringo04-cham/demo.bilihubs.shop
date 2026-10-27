import 'package:flutter/material.dart';

import '../../sevices/api_services.dart';
import '../../styles/rider_style.dart';

class CodRemittance extends StatefulWidget {
  const CodRemittance({super.key});

  @override
  State<CodRemittance> createState() => _CodRemittanceState();
}

class _CodRemittanceState extends State<CodRemittance> {
  bool _isLoading = true;
  bool _isRefreshing = false;

  String? _errorMessage;

  List<Map<String, dynamic>> _availableCollections = <Map<String, dynamic>>[];

  List<Map<String, dynamic>> _remittanceHistory = <Map<String, dynamic>>[];

  final Set<int> _selectedPaymentIds = <int>{};

  @override
  void initState() {
    super.initState();

    _loadRemittanceData();
  }

  // ============================================================
  // LOAD REMITTANCE DATA
  // ============================================================

  Future<void> _loadRemittanceData({bool refresh = false}) async {
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
      final Map<String, dynamic> result = await ApiService.getRiderEarnings();

      if (!mounted) return;

      if (result['success'] != true) {
        setState(() {
          _availableCollections = <Map<String, dynamic>>[];

          _remittanceHistory = <Map<String, dynamic>>[];

          _selectedPaymentIds.clear();

          _isLoading = false;
          _isRefreshing = false;

          final String message = result['message']?.toString().trim() ?? '';

          _errorMessage = message.isNotEmpty
              ? message
              : 'Unable to load COD remittance data.';
        });

        return;
      }

      final Map<String, dynamic> data = _extractDataMap(result);

      final List<Map<String, dynamic>> collections = _extractCollections(data);

      final List<Map<String, dynamic>> history = _extractHistory(data);

      setState(() {
        _availableCollections = collections;
        _remittanceHistory = history;

        _selectedPaymentIds.removeWhere(
          (int id) => !_availableCollections.any(
            (Map<String, dynamic> item) => _toInt(item['id']) == id,
          ),
        );

        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = null;
      });
    } catch (error) {
      debugPrint('COD remittance load error: $error');

      if (!mounted) return;

      setState(() {
        _availableCollections = <Map<String, dynamic>>[];

        _remittanceHistory = <Map<String, dynamic>>[];

        _selectedPaymentIds.clear();

        _isLoading = false;
        _isRefreshing = false;

        _errorMessage = 'Unable to connect to the server. Please try again.';
      });
    }
  }

  // ============================================================
  // EXTRACT DATA
  // ============================================================

  Map<String, dynamic> _extractDataMap(Map<String, dynamic> result) {
    final dynamic rawData = result['data'];

    if (rawData is Map<String, dynamic>) {
      return Map<String, dynamic>.from(rawData);
    }

    if (rawData is Map) {
      return Map<String, dynamic>.from(rawData);
    }

    return Map<String, dynamic>.from(result);
  }

  // ============================================================
  // EXTRACT COLLECTIONS
  // ============================================================

  List<Map<String, dynamic>> _extractCollections(Map<String, dynamic> data) {
    final List<dynamic> candidates = <dynamic>[
      data['available_cod_collections'],
      data['available_collections'],
      data['cod_collections'],
      data['collections'],
      data['unremitted_payments'],
      data['available_payments'],
    ];

    for (final dynamic candidate in candidates) {
      final List<Map<String, dynamic>> parsed = _toMapList(candidate);

      if (parsed.isNotEmpty) {
        return parsed.map(_normalizeCollection).toList();
      }
    }

    return <Map<String, dynamic>>[];
  }

  // ============================================================
  // EXTRACT HISTORY
  // ============================================================

  List<Map<String, dynamic>> _extractHistory(Map<String, dynamic> data) {
    final List<dynamic> candidates = <dynamic>[
      data['remittance_history'],
      data['remittances'],
      data['cod_remittances'],
      data['history'],
    ];

    for (final dynamic candidate in candidates) {
      final List<Map<String, dynamic>> parsed = _toMapList(candidate);

      if (parsed.isNotEmpty) {
        return parsed.map(_normalizeHistory).toList();
      }
    }

    return <Map<String, dynamic>>[];
  }

  // ============================================================
  // TO MAP LIST
  // ============================================================

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
  // NORMALIZE COLLECTION
  // ============================================================

  Map<String, dynamic> _normalizeCollection(Map<String, dynamic> raw) {
    final Map<String, dynamic> map = Map<String, dynamic>.from(raw);

    map['id'] ??=
        map['payment_id'] ?? map['collection_id'] ?? map['shipment_id'] ?? 0;

    map['tracking_number'] ??=
        map['tracking_code'] ?? map['waybill_number'] ?? map['shipment'] ?? '';

    map['payment_reference'] ??=
        map['reference'] ?? map['payment_ref'] ?? map['payment'] ?? '';

    map['amount'] ??=
        map['cod_amount'] ?? map['collected_amount'] ?? map['cash_amount'] ?? 0;

    map['logistics_name'] ??= map['logistics'] ?? map['courier_name'] ?? '';

    map['collected_at'] ??=
        map['paid_at'] ?? map['delivered_at'] ?? map['created_at'] ?? '';

    return map;
  }

  // ============================================================
  // NORMALIZE HISTORY
  // ============================================================

  Map<String, dynamic> _normalizeHistory(Map<String, dynamic> raw) {
    final Map<String, dynamic> map = Map<String, dynamic>.from(raw);

    map['reference'] ??=
        map['remittance_reference'] ??
        map['reference_number'] ??
        map['code'] ??
        '';

    map['status'] ??= map['remittance_status'] ?? 'pending';

    map['submitted_at'] ??= map['created_at'] ?? map['date'] ?? '';

    map['payments_count'] ??=
        map['payment_count'] ?? map['collections_count'] ?? 0;

    map['amount'] ??= map['total_amount'] ?? map['remittance_amount'] ?? 0;

    return map;
  }

  // ============================================================
  // SUMMARY VALUES
  // ============================================================

  String get _availableCashText {
    double total = 0;

    for (final Map<String, dynamic> item in _availableCollections) {
      final double? amount = _toDouble(item['amount']);

      if (amount != null) {
        total += amount;
      }
    }

    return _money(total);
  }

  String get _availablePaymentsText {
    return _availableCollections.length.toString();
  }

  String get _pendingRemittancesText {
    int pendingCount = 0;

    for (final Map<String, dynamic> item in _remittanceHistory) {
      final String status =
          item['status']?.toString().trim().toLowerCase().replaceAll(
            ' ',
            '_',
          ) ??
          '';

      if (status == 'pending' ||
          status == 'submitted' ||
          status == 'for_verification') {
        pendingCount++;
      }
    }

    return pendingCount.toString();
  }

  String get _selectedAmountText {
    double total = 0;

    for (final Map<String, dynamic> item in _availableCollections) {
      final int? id = _toInt(item['id']);

      if (id == null || !_selectedPaymentIds.contains(id)) {
        continue;
      }

      final double? amount = _toDouble(item['amount']);

      if (amount != null) {
        total += amount;
      }
    }

    return _money(total);
  }

  // ============================================================
  // HELPERS
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

  int? _toInt(dynamic value) {
    if (value is int) {
      return value;
    }

    if (value is num) {
      return value.toInt();
    }

    return int.tryParse(value?.toString() ?? '');
  }

  String _money(dynamic value) {
    final double? amount = _toDouble(value);

    if (amount == null) {
      return '₱0.00';
    }

    return '₱${amount.toStringAsFixed(2)}';
  }

  String _displayValue(dynamic value, {String fallback = 'Not available'}) {
    final String text = value?.toString().trim() ?? '';

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return fallback;
    }

    return text;
  }

  String _formatStatus(dynamic value) {
    final String text = value?.toString().trim() ?? '';

    if (text.isEmpty) {
      return 'Unknown';
    }

    final String cleaned = text.replaceAll('_', ' ').replaceAll('-', ' ');

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

  String _formatDate(dynamic value) {
    final String raw = value?.toString().trim() ?? '';

    if (raw.isEmpty) {
      return 'Not available';
    }

    final DateTime? parsed = DateTime.tryParse(raw);

    if (parsed == null) {
      return raw;
    }

    final DateTime local = parsed.toLocal();

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

    final String month = months[local.month - 1];

    return '$month ${local.day}, ${local.year}';
  }

  Color _statusColor(dynamic value) {
    final String status =
        value?.toString().trim().toLowerCase().replaceAll(' ', '_') ?? '';

    switch (status) {
      case 'confirmed':
      case 'approved':
      case 'completed':
      case 'verified':
        return RiderColors.success;

      case 'rejected':
      case 'failed':
      case 'cancelled':
        return RiderColors.danger;

      case 'pending':
      case 'submitted':
      case 'for_verification':
        return RiderColors.warning;

      default:
        return RiderColors.textGrey;
    }
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

        iconTheme: const IconThemeData(color: RiderColors.textDark),

        titleSpacing: 18,

        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            Text('COD Cash Remittance', style: RiderStyle.title),

            SizedBox(height: 2),

            Text(
              'Submit collected cash for verification',
              style: RiderStyle.small,
            ),
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
                      _loadRemittanceData(refresh: true);
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
            return _loadRemittanceData(refresh: true);
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
                  const SizedBox(height: 16),

                _securityBadge(),

                const SizedBox(height: 16),

                _summaryCards(),

                const SizedBox(height: 22),

                _availableCollectionsSection(),

                const SizedBox(height: 22),

                _remittanceHistorySection(),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // SECURITY BADGE
  // ============================================================

  Widget _securityBadge() {
    return Align(
      alignment: Alignment.centerRight,

      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),

        decoration: BoxDecoration(
          color: RiderColors.white,

          borderRadius: BorderRadius.circular(20),

          border: Border.all(color: RiderColors.border),
        ),

        child: const Row(
          mainAxisSize: MainAxisSize.min,

          children: [
            Icon(
              Icons.verified_user_outlined,

              color: RiderColors.primary,

              size: 15,
            ),

            SizedBox(width: 5),

            Text(
              'Secure Cash Tracking',

              style: TextStyle(
                color: RiderColors.textDark,

                fontSize: 10,

                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // SUMMARY CARDS
  // ============================================================

  Widget _summaryCards() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        const Text('Remittance Summary', style: RiderStyle.sectionTitle),

        const SizedBox(height: 12),

        Row(
          children: [
            Expanded(
              child: _summaryCard(
                title: 'Available Cash',

                value: _availableCashText,

                subtitle: 'Eligible for remittance',

                icon: Icons.payments_outlined,

                background: RiderColors.lightGreen,

                iconColor: RiderColors.success,
              ),
            ),

            const SizedBox(width: 10),

            Expanded(
              child: _summaryCard(
                title: 'Available Payments',

                value: _availablePaymentsText,

                subtitle: 'Delivered COD payments',

                icon: Icons.receipt_long_outlined,

                background: RiderColors.lightBlue,

                iconColor: RiderColors.primary,
              ),
            ),
          ],
        ),

        const SizedBox(height: 10),

        _summaryCard(
          title: 'Pending Remittances',

          value: _pendingRemittancesText,

          subtitle: 'Waiting for verification',

          icon: Icons.hourglass_top_rounded,

          background: RiderColors.lightOrange,

          iconColor: RiderColors.warning,

          wide: true,
        ),
      ],
    );
  }

  Widget _summaryCard({
    required String title,
    required String value,
    required String subtitle,
    required IconData icon,
    required Color background,
    required Color iconColor,
    bool wide = false,
  }) {
    return Container(
      width: double.infinity,

      constraints: BoxConstraints(minHeight: wide ? 92 : 120),

      padding: const EdgeInsets.all(14),

      decoration: RiderStyle.card,

      child: wide
          ? Row(
              children: [
                Container(
                  height: 42,
                  width: 42,

                  decoration: BoxDecoration(
                    color: background,

                    borderRadius: BorderRadius.circular(12),
                  ),

                  child: Icon(icon, color: iconColor, size: 21),
                ),

                const SizedBox(width: 12),

                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      Text(title, style: RiderStyle.small),

                      const SizedBox(height: 3),

                      Text(
                        value,

                        maxLines: 1,

                        overflow: TextOverflow.ellipsis,

                        style: const TextStyle(
                          color: RiderColors.textDark,

                          fontSize: 20,

                          fontWeight: FontWeight.w800,
                        ),
                      ),

                      const SizedBox(height: 2),

                      Text(subtitle, style: RiderStyle.small),
                    ],
                  ),
                ),
              ],
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Container(
                  height: 38,
                  width: 38,

                  decoration: BoxDecoration(
                    color: background,

                    borderRadius: BorderRadius.circular(11),
                  ),

                  child: Icon(icon, color: iconColor, size: 20),
                ),

                const Spacer(),

                Text(
                  title,

                  maxLines: 2,

                  overflow: TextOverflow.ellipsis,

                  style: RiderStyle.small,
                ),

                const SizedBox(height: 3),

                Text(
                  value,

                  maxLines: 1,

                  overflow: TextOverflow.ellipsis,

                  style: const TextStyle(
                    color: RiderColors.textDark,

                    fontSize: 19,

                    fontWeight: FontWeight.w800,
                  ),
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

  // ============================================================
  // AVAILABLE COLLECTIONS
  // ============================================================

  Widget _availableCollectionsSection() {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.all(16),

      decoration: RiderStyle.card,

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          const Text(
            'Available COD Collections',

            style: RiderStyle.sectionTitle,
          ),

          const SizedBox(height: 4),

          const Text(
            'Select delivered COD payments to prepare for remittance.',

            style: RiderStyle.small,
          ),

          const SizedBox(height: 16),

          if (_availableCollections.isEmpty)
            _emptyCollectionsState()
          else
            ..._availableCollections.map((Map<String, dynamic> item) {
              return _collectionCard(item);
            }),

          if (_availableCollections.isNotEmpty) ...[
            const SizedBox(height: 12),

            Container(
              width: double.infinity,

              padding: const EdgeInsets.all(13),

              decoration: BoxDecoration(
                color: RiderColors.background,

                borderRadius: BorderRadius.circular(13),

                border: Border.all(color: RiderColors.border),
              ),

              child: Row(
                children: [
                  const Expanded(
                    child: Text('Selected Amount', style: RiderStyle.cardTitle),
                  ),

                  Text(
                    _selectedAmountText,

                    style: const TextStyle(
                      color: RiderColors.primary,

                      fontSize: 17,

                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 12),

            SizedBox(
              width: double.infinity,

              height: 48,

              child: ElevatedButton.icon(
                onPressed: _selectedPaymentIds.isEmpty
                    ? null
                    : _submitRemittance,

                icon: const Icon(Icons.send_rounded, size: 18),

                label: const Text('Submit Remittance'),

                style: RiderStyle.primaryButton,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _emptyCollectionsState() {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.symmetric(vertical: 28, horizontal: 16),

      decoration: BoxDecoration(
        color: RiderColors.background,

        borderRadius: BorderRadius.circular(15),

        border: Border.all(color: RiderColors.border),
      ),

      child: const Column(
        children: [
          Icon(
            Icons.check_circle_outline_rounded,

            color: RiderColors.textGrey,

            size: 38,
          ),

          SizedBox(height: 9),

          Text(
            'No COD collections available for remittance.',

            textAlign: TextAlign.center,

            style: RiderStyle.small,
          ),
        ],
      ),
    );
  }

  Widget _collectionCard(Map<String, dynamic> item) {
    final int id = _toInt(item['id']) ?? 0;

    final bool selected = _selectedPaymentIds.contains(id);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),

      padding: const EdgeInsets.all(13),

      decoration: BoxDecoration(
        color: selected ? RiderColors.lightPurple : RiderColors.background,

        borderRadius: BorderRadius.circular(14),

        border: Border.all(
          color: selected ? RiderColors.primary : RiderColors.border,
        ),
      ),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Row(
            children: [
              Checkbox(
                value: selected,

                onChanged: id <= 0
                    ? null
                    : (bool? value) {
                        setState(() {
                          if (value == true) {
                            _selectedPaymentIds.add(id);
                          } else {
                            _selectedPaymentIds.remove(id);
                          }
                        });
                      },
              ),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Text(
                      _displayValue(
                        item['tracking_number'],

                        fallback: 'Shipment unavailable',
                      ),

                      maxLines: 1,

                      overflow: TextOverflow.ellipsis,

                      style: RiderStyle.cardTitle,
                    ),

                    const SizedBox(height: 3),

                    Text(
                      _displayValue(
                        item['payment_reference'],

                        fallback: 'Payment reference unavailable',
                      ),

                      style: RiderStyle.small,
                    ),
                  ],
                ),
              ),

              const SizedBox(width: 8),

              Text(
                _money(item['amount']),

                style: const TextStyle(
                  color: RiderColors.primary,

                  fontSize: 16,

                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),

          const SizedBox(height: 10),

          _detailRow('Logistics', _displayValue(item['logistics_name'])),

          _detailRow('Collected', _formatDate(item['collected_at'])),
        ],
      ),
    );
  }

  // ============================================================
  // HISTORY
  // ============================================================

  Widget _remittanceHistorySection() {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.all(16),

      decoration: RiderStyle.card,

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          const Text('Remittance History', style: RiderStyle.sectionTitle),

          const SizedBox(height: 4),

          const Text(
            'Track submitted, confirmed, and rejected cash remittances.',

            style: RiderStyle.small,
          ),

          const SizedBox(height: 16),

          if (_remittanceHistory.isEmpty)
            _emptyHistoryState()
          else
            ..._remittanceHistory.map((Map<String, dynamic> item) {
              return _historyCard(item);
            }),
        ],
      ),
    );
  }

  Widget _emptyHistoryState() {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.symmetric(vertical: 26, horizontal: 16),

      decoration: BoxDecoration(
        color: RiderColors.background,

        borderRadius: BorderRadius.circular(15),

        border: Border.all(color: RiderColors.border),
      ),

      child: const Column(
        children: [
          Icon(Icons.history_rounded, color: RiderColors.textGrey, size: 38),

          SizedBox(height: 9),

          Text(
            'No remittance history yet.',

            textAlign: TextAlign.center,

            style: RiderStyle.small,
          ),
        ],
      ),
    );
  }

  Widget _historyCard(Map<String, dynamic> item) {
    final String status = _formatStatus(item['status']);

    final Color statusColor = _statusColor(item['status']);

    return Container(
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
            children: [
              Expanded(
                child: Text(
                  _displayValue(
                    item['reference'],

                    fallback: 'Reference unavailable',
                  ),

                  maxLines: 1,

                  overflow: TextOverflow.ellipsis,

                  style: RiderStyle.cardTitle,
                ),
              ),

              const SizedBox(width: 8),

              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),

                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.10),

                  borderRadius: BorderRadius.circular(20),
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

          const SizedBox(height: 12),

          _detailRow('Submitted', _formatDate(item['submitted_at'])),

          _detailRow(
            'Payments',
            _displayValue(item['payments_count'], fallback: '0'),
          ),

          _detailRow('Amount', _money(item['amount'])),
        ],
      ),
    );
  }

  // ============================================================
  // DETAIL ROW
  // ============================================================

  Widget _detailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 7),

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          SizedBox(width: 82, child: Text(label, style: RiderStyle.small)),

          const SizedBox(width: 8),

          Expanded(child: Text(value, style: RiderStyle.cardTitle)),
        ],
      ),
    );
  }

  // ============================================================
  // SUBMIT REMITTANCE
  // ============================================================

  void _submitRemittance() {
    if (_selectedPaymentIds.isEmpty) {
      return;
    }

    showDialog<void>(
      context: context,

      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),

          title: const Text(
            'Submit Remittance',

            style: RiderStyle.sectionTitle,
          ),

          content: Text(
            'You selected ${_selectedPaymentIds.length} payment(s) '
            'with a total amount of $_selectedAmountText.\n\n'
            'The final remittance submission will be enabled once the Laravel Rider remittance endpoint is connected.',

            style: RiderStyle.small,
          ),

          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);
              },

              child: const Text('Close'),
            ),
          ],
        );
      },
    );
  }

  // ============================================================
  // LOADING
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

          Expanded(
            child: Text(
              'Loading COD remittance data...',

              style: RiderStyle.small,
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

      decoration: RiderStyle.card,

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          const Icon(Icons.info_outline_rounded, color: RiderColors.warning),

          const SizedBox(width: 10),

          Expanded(
            child: Text(
              _errorMessage ?? 'Unable to load COD remittance data.',

              style: RiderStyle.small,
            ),
          ),

          IconButton(
            onPressed: () {
              _loadRemittanceData(refresh: true);
            },

            icon: const Icon(Icons.refresh_rounded, color: RiderColors.primary),
          ),
        ],
      ),
    );
  }
}
