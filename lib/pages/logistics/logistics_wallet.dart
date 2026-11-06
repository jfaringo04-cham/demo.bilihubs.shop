import 'dart:async';

import 'package:flutter/material.dart';

import '../../services/api_services.dart';
import '../../styles/logistics_style.dart';
import '../../utils/money_formatter.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_loading_state.dart';
import '../../widgets/status_chip.dart';

class LogisticsWallet extends StatefulWidget {
  const LogisticsWallet({super.key});

  @override
  State<LogisticsWallet> createState() => _LogisticsWalletState();
}

class _LogisticsWalletState extends State<LogisticsWallet> {
  static const Duration _requestTimeout = Duration(seconds: 25);

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isFetching = false;
  bool _hasLoaded = false;
  bool _hasWalletData = false;
  bool _hasTransactionData = false;

  String? _errorMessage;

  Map<String, dynamic> _wallet = <String, dynamic>{};
  List<Map<String, dynamic>> _transactions = <Map<String, dynamic>>[];

  int _requestId = 0;

  @override
  void initState() {
    super.initState();
    _loadWallet();
  }

  @override
  void dispose() {
    _requestId++;
    super.dispose();
  }

  // ============================================================
  // LOAD WALLET
  // ============================================================

  Future<void> _loadWallet({bool refresh = false}) async {
    if (!mounted || _isFetching) return;

    final int requestId = ++_requestId;

    setState(() {
      _isFetching = true;
      _isLoading = !_hasLoaded;
      _isRefreshing = _hasLoaded || refresh;
      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.getLogisticsDashboard().timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) return;

      if (result['success'] != true) {
        _setLoadError(
          requestId,
          _responseMessage(result, 'Unable to retrieve logistics wallet data.'),
        );
        return;
      }

      final Map<String, dynamic> walletData = _extractWalletData(result);

      final _TransactionResult transactionResult = _extractTransactions(
        result,
        walletData,
      );

      if (!mounted || requestId != _requestId) return;

      setState(() {
        _wallet = walletData;
        _transactions = transactionResult.items;

        _hasWalletData = _containsFinancialFields(walletData);
        _hasTransactionData = transactionResult.wasProvided;
        _hasLoaded = true;

        _isLoading = false;
        _isRefreshing = false;
        _isFetching = false;
        _errorMessage = null;
      });
    } on TimeoutException {
      _setLoadError(
        requestId,
        'The wallet request timed out. '
        'Please check your connection and try again.',
      );
    } catch (error) {
      debugPrint('Logistics wallet error: $error');

      _setLoadError(
        requestId,
        'Unable to connect to the BiliHub server. '
        'Please check your internet connection.',
      );
    }
  }

  void _setLoadError(int requestId, String message) {
    if (!mounted || requestId != _requestId) return;

    setState(() {
      _isLoading = false;
      _isRefreshing = false;
      _isFetching = false;
      _errorMessage = message;
    });
  }

  String _responseMessage(Map<String, dynamic> result, String fallback) {
    final String message = result['message']?.toString().trim() ?? '';

    return message.isEmpty ? fallback : message;
  }

  // ============================================================
  // RESPONSE PARSING
  // ============================================================

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }

    return <String, dynamic>{};
  }

  Map<String, dynamic> _extractWalletData(Map<String, dynamic> result) {
    final Map<String, dynamic> data = _asMap(result['data']);

    final Map<String, dynamic> root = data.isNotEmpty ? data : result;

    final Map<String, dynamic> wallet = _asMap(root['wallet']);

    final Map<String, dynamic> earnings = _asMap(root['earnings']);

    final Map<String, dynamic> logisticsWallet = _asMap(
      root['logistics_wallet'],
    );

    // Keep financial fields only. Shipment totals, rider counts,
    // and hub counts must not be treated as money.
    return <String, dynamic>{
      ...root,
      ...earnings,
      ...wallet,
      ...logisticsWallet,
    };
  }

  bool _containsFinancialFields(Map<String, dynamic> data) {
    const List<String> financialKeys = <String>[
      'available_balance',
      'wallet_balance',
      'balance',
      'pending_balance',
      'pending_withdrawal',
      'total_earnings',
      'total_earned',
      'earnings_total',
      'lifetime_earnings',
      'today_earnings',
      'daily_earnings',
      'week_earnings',
      'weekly_earnings',
      'month_earnings',
      'monthly_earnings',
    ];

    return financialKeys.any((String key) => data[key] != null);
  }

  // ============================================================
  // TRANSACTION PARSING
  // ============================================================

  _TransactionResult _extractTransactions(
    Map<String, dynamic> response,
    Map<String, dynamic> wallet,
  ) {
    final Map<String, dynamic> data = _asMap(response['data']);

    final List<Map<String, dynamic>> sources = <Map<String, dynamic>>[
      _asMap(data['logistics_wallet']),
      _asMap(data['wallet']),
      _asMap(data['earnings']),
      wallet,
      data,
      response,
    ];

    const List<String> keys = <String>[
      'transactions',
      'wallet_transactions',
      'recent_transactions',
      'recent_earnings',
      'earnings_history',
    ];

    for (final Map<String, dynamic> source in sources) {
      for (final String key in keys) {
        final dynamic value = source[key];

        if (value is List) {
          return _TransactionResult(
            items: _toMapList(value),
            wasProvided: true,
          );
        }

        if (value is Map) {
          final Map<String, dynamic> container = _asMap(value);

          if (container['data'] is List) {
            return _TransactionResult(
              items: _toMapList(container['data']),
              wasProvided: true,
            );
          }
        }
      }
    }

    return const _TransactionResult(
      items: <Map<String, dynamic>>[],
      wasProvided: false,
    );
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
  // FINANCIAL VALUE HELPERS
  // ============================================================

  dynamic _firstValue(List<String> keys) {
    for (final String key in keys) {
      final dynamic value = _wallet[key];

      if (value == null) continue;

      if (value is String &&
          (value.trim().isEmpty || value.trim().toLowerCase() == 'null')) {
        continue;
      }

      return value;
    }

    return null;
  }

  String _money(List<String> keys) {
    if (!_hasWalletData) {
      return 'Not available';
    }

    final dynamic value = _firstValue(keys);

    if (value == null) {
      return 'Not available';
    }

    // Only known monetary fields are formatted.
    // The API must provide amounts in the units expected
    // by MoneyFormatter.peso.
    return MoneyFormatter.peso(value);
  }

  String _transactionMoney(dynamic value) {
    if (value == null) return 'Not available';

    final String text = value.toString().trim();

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return 'Not available';
    }

    return MoneyFormatter.peso(value);
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

  String _normalize(String value) {
    return value.trim().toLowerCase().replaceAll(' ', '_').replaceAll('-', '_');
  }

  String _formatStatus(String value) {
    final String cleaned = value
        .trim()
        .replaceAll('_', ' ')
        .replaceAll('-', ' ');

    if (cleaned.isEmpty) return 'Not available';

    return cleaned
        .split(RegExp(r'\s+'))
        .where((String word) => word.isNotEmpty)
        .map(
          (String word) =>
              '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  Color _statusColor(String status) {
    switch (_normalize(status)) {
      case 'paid':
      case 'completed':
      case 'credited':
      case 'released':
      case 'confirmed':
        return LogisticsColors.success;

      case 'pending':
      case 'processing':
      case 'awaiting_payment':
        return LogisticsColors.warning;

      case 'rejected':
      case 'failed':
      case 'cancelled':
      case 'canceled':
        return LogisticsColors.danger;

      default:
        return LogisticsColors.primary;
    }
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.sizeOf(context).width;

    final double horizontalPadding = width < 380 ? 14 : 18;

    return Scaffold(
      backgroundColor: LogisticsColors.background,
      appBar: AppBar(
        backgroundColor: LogisticsColors.white,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        automaticallyImplyLeading: false,
        titleSpacing: 18,
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Logistics Wallet', style: LogisticsStyle.title),
            SizedBox(height: 2),
            Text(
              'Balance, earnings and wallet activity',
              style: LogisticsStyle.small,
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Refresh wallet',
            onPressed: _isFetching ? null : () => _loadWallet(refresh: true),
            icon: _isRefreshing
                ? const SizedBox(
                    height: 19,
                    width: 19,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: LogisticsColors.primary,
                    ),
                  )
                : const Icon(
                    Icons.refresh_rounded,
                    color: LogisticsColors.primary,
                  ),
          ),
          const SizedBox(width: 5),
        ],
      ),
      body: SafeArea(
        top: false,
        child: RefreshIndicator(
          color: LogisticsColors.primary,
          onRefresh: () => _loadWallet(refresh: true),
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
              if (_isLoading && !_hasLoaded) ...[
                const SizedBox(height: 110),
                const AppLoadingState(
                  message: 'Loading logistics wallet...',
                  color: LogisticsColors.primary,
                ),
              ] else if (!_hasLoaded) ...[
                const SizedBox(height: 70),
                AppErrorState(
                  title: 'Unable to Load Wallet',
                  message:
                      _errorMessage ?? 'Unable to retrieve wallet information.',
                  color: LogisticsColors.primary,
                  onRetry: () => _loadWallet(refresh: true),
                ),
              ] else ...[
                if (_isRefreshing) ...[
                  const LinearProgressIndicator(
                    color: LogisticsColors.primary,
                    minHeight: 2,
                  ),
                  const SizedBox(height: 14),
                ],

                if (_errorMessage != null) ...[
                  _errorBanner(),
                  const SizedBox(height: 14),
                ],

                if (!_hasWalletData) ...[
                  _walletUnavailableNotice(),
                  const SizedBox(height: 14),
                ],

                _walletOverview(),

                const SizedBox(height: 22),

                _earningsSummary(),

                const SizedBox(height: 22),

                _transactionSection(),
              ],
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // ERROR WARNING
  // ============================================================

  Widget _errorBanner() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: LogisticsStyle.card,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.info_outline_rounded,
            color: LogisticsColors.warning,
            size: 21,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Showing previously loaded wallet data. '
              '${_errorMessage ?? 'Refresh failed.'}',
              style: LogisticsStyle.small,
            ),
          ),
          TextButton(
            onPressed: _isFetching ? null : () => _loadWallet(refresh: true),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _walletUnavailableNotice() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: LogisticsColors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: LogisticsColors.border),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.info_outline_rounded,
            color: LogisticsColors.warning,
            size: 21,
          ),
          SizedBox(width: 10),
          Expanded(
            child: Text(
              'The logistics dashboard responded, but '
              'did not provide confirmed earnings or '
              'wallet balances. Monetary information '
              'will remain unavailable until the '
              'appropriate wallet API data is provided.',
              style: LogisticsStyle.small,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // WALLET OVERVIEW
  // ============================================================

  Widget _walletOverview() {
    final String available = _money([
      'available_balance',
      'wallet_balance',
      'available',
      'balance',
    ]);

    final String pending = _money([
      'pending_balance',
      'pending_withdrawal',
      'pending',
    ]);

    final String totalEarned = _money([
      'total_earnings',
      'total_earned',
      'earnings_total',
      'lifetime_earnings',
    ]);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(
                Icons.account_balance_wallet_outlined,
                color: LogisticsColors.primary,
                size: 25,
              ),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Wallet Overview',
                  style: LogisticsStyle.sectionTitle,
                ),
              ),
            ],
          ),

          const SizedBox(height: 5),

          const Text(
            'Logistics earnings and balances',
            style: LogisticsStyle.small,
          ),

          const SizedBox(height: 16),

          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(17),
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(
                      Icons.account_balance_wallet_rounded,
                      color: LogisticsColors.primary,
                      size: 19,
                    ),
                    SizedBox(width: 8),
                    Text('Available Balance', style: LogisticsStyle.small),
                  ],
                ),

                const SizedBox(height: 10),

                Text(
                  available,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: LogisticsColors.primary,
                    fontSize: 27,
                    fontWeight: FontWeight.w900,
                  ),
                ),

                const SizedBox(height: 8),

                const Text(
                  'Confirmed available logistics earnings, '
                  'when provided by Laravel.',
                  style: LogisticsStyle.small,
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
                  value: pending,
                  icon: Icons.hourglass_top_rounded,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _walletStatCard(
                  title: 'Total Earned',
                  value: totalEarned,
                  icon: Icons.payments_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _walletStatCard({
    required String title,
    required String value,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: LogisticsColors.background,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: LogisticsColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 36,
            width: 36,
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: LogisticsColors.primary, size: 19),
          ),
          const SizedBox(height: 10),
          Text(title, style: LogisticsStyle.small),
          const SizedBox(height: 5),
          Text(
            value,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: LogisticsStyle.cardTitle,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // EARNINGS SUMMARY
  // ============================================================

  Widget _earningsSummary() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Earnings Summary', style: LogisticsStyle.sectionTitle),

        const SizedBox(height: 5),

        const Text(
          'Values reported by the Laravel backend',
          style: LogisticsStyle.small,
        ),

        const SizedBox(height: 12),

        GridView.count(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisCount: 2,
          crossAxisSpacing: 10,
          mainAxisSpacing: 10,
          childAspectRatio: 1.30,
          children: [
            _summaryCard(
              title: 'Today',
              value: _money(['today_earnings', 'daily_earnings', 'today']),
              icon: Icons.today_outlined,
            ),
            _summaryCard(
              title: 'This Week',
              value: _money(['week_earnings', 'weekly_earnings', 'this_week']),
              icon: Icons.date_range_outlined,
            ),
            _summaryCard(
              title: 'This Month',
              value: _money([
                'month_earnings',
                'monthly_earnings',
                'this_month',
              ]),
              icon: Icons.calendar_month_outlined,
            ),
            _summaryCard(
              title: 'Total Earnings',
              value: _money([
                'total_earnings',
                'total_earned',
                'earnings_total',
                'lifetime_earnings',
              ]),
              icon: Icons.account_balance_wallet_outlined,
            ),
          ],
        ),
      ],
    );
  }

  Widget _summaryCard({
    required String title,
    required String value,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 36,
            width: 36,
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: LogisticsColors.primary, size: 19),
          ),
          const Spacer(),
          Text(
            value,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: LogisticsColors.textDark,
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 5),
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
  // TRANSACTION HISTORY
  // ============================================================

  Widget _transactionSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Wallet Activity', style: LogisticsStyle.sectionTitle),

        const SizedBox(height: 5),

        const Text(
          'Recent recorded wallet transactions',
          style: LogisticsStyle.small,
        ),

        const SizedBox(height: 12),

        if (!_hasTransactionData)
          const AppEmptyState(
            title: 'Transaction Data Unavailable',
            message:
                'The current API response does not include '
                'a wallet transaction history.',
            icon: Icons.receipt_long_outlined,
            color: LogisticsColors.primary,
            compact: true,
          )
        else if (_transactions.isEmpty)
          const AppEmptyState(
            title: 'No Wallet Transactions',
            message: 'The server returned an empty transaction list.',
            icon: Icons.receipt_long_outlined,
            color: LogisticsColors.primary,
            compact: true,
          )
        else
          ..._transactions.map(
            (Map<String, dynamic> item) => _transactionItem(item),
          ),
      ],
    );
  }

  Widget _transactionItem(Map<String, dynamic> item) {
    final String title = _firstText(item, [
      'description',
      'transaction_type',
      'earning_type',
      'type',
    ]);

    final String status = _firstText(item, ['status', 'transaction_status']);

    final String tracking = _firstText(item, [
      'tracking_number',
      'tracking_code',
      'waybill_number',
    ]);

    final dynamic amount =
        item['amount'] ??
        item['earnings'] ??
        item['earning_amount'] ??
        item['value'];

    final Color statusColor = _statusColor(status);

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(15),
      decoration: LogisticsStyle.card,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 42,
            width: 42,
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.receipt_long_outlined,
              color: LogisticsColors.primary,
              size: 21,
            ),
          ),

          const SizedBox(width: 11),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title.isEmpty ? 'Wallet Transaction' : _formatStatus(title),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: LogisticsStyle.cardTitle,
                ),

                if (tracking.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  AppInfoRow(
                    label: 'Tracking',
                    value: tracking,
                    icon: Icons.qr_code_2_rounded,
                    iconColor: LogisticsColors.primary,
                    compact: true,
                  ),
                ],

                if (status.isNotEmpty) ...[
                  const SizedBox(height: 7),
                  StatusChip(status: status),
                ],
              ],
            ),
          ),

          const SizedBox(width: 10),

          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                _transactionMoney(amount),
                textAlign: TextAlign.right,
                style: TextStyle(
                  color: statusColor,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),

              if (status.isNotEmpty) ...[
                const SizedBox(height: 5),
                Text(
                  _formatStatus(status),
                  textAlign: TextAlign.right,
                  style: TextStyle(
                    color: statusColor,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _TransactionResult {
  final List<Map<String, dynamic>> items;
  final bool wasProvided;

  const _TransactionResult({required this.items, required this.wasProvided});
}
