import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../../services/api_services.dart';
import '../../services/auth_storage.dart';
import '../../styles/logistics_style.dart';
import '../../utils/money_formatter.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_loading_state.dart';

class LogisticsWallet extends StatefulWidget {
  const LogisticsWallet({super.key});

  @override
  State<LogisticsWallet> createState() => _LogisticsWalletState();
}

class _LogisticsWalletState extends State<LogisticsWallet> {
  static const Duration _requestTimeout = Duration(seconds: 30);
  static const int _perPage = 20;

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isFetching = false;
  bool _isLoadingMore = false;
  bool _hasLoaded = false;

  String? _errorMessage;
  String? _paginationError;

  Map<String, dynamic> _wallet = <String, dynamic>{};

  List<Map<String, dynamic>> _transactions = <Map<String, dynamic>>[];

  List<Map<String, dynamic>> _withdrawals = <Map<String, dynamic>>[];

  int _currentPage = 1;
  int _lastPage = 1;
  int _requestId = 0;

  bool get _hasMoreTransactions => _currentPage < _lastPage;

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
  // API HELPERS
  // ============================================================

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }

    return <String, dynamic>{};
  }

  List<Map<String, dynamic>> _asMapList(dynamic value) {
    if (value is! List) {
      return <Map<String, dynamic>>[];
    }

    return value
        .whereType<Map>()
        .map((Map item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<Map<String, dynamic>> _fetchWallet({int page = 1}) async {
    final String? token = await AuthStorage.getToken();

    if (token == null || token.trim().isEmpty) {
      throw const _WalletException(
        'Your login session has expired. Please log in again.',
      );
    }

    final Uri uri = Uri.parse('${ApiService.baseUrl}/logistic/wallet').replace(
      queryParameters: <String, String>{
        'page': page.toString(),
        'per_page': _perPage.toString(),
      },
    );

    final http.Response response;

    try {
      response = await http
          .get(
            uri,
            headers: <String, String>{
              'Accept': 'application/json',
              'Authorization': 'Bearer $token',
            },
          )
          .timeout(_requestTimeout);
    } on TimeoutException {
      throw const _WalletException(
        'The wallet request timed out. '
        'Please check your internet connection.',
      );
    }

    Map<String, dynamic> decoded;

    try {
      decoded = _asMap(jsonDecode(response.body));
    } catch (_) {
      throw const _WalletException(
        'The server returned an invalid wallet response.',
      );
    }

    if (response.statusCode == 401) {
      throw const _WalletException(
        'Your login session has expired. Please log in again.',
      );
    }

    if (response.statusCode == 403) {
      throw const _WalletException(
        'This account does not have permission '
        'to access the logistics wallet.',
      );
    }

    if (response.statusCode < 200 ||
        response.statusCode >= 300 ||
        decoded['success'] != true) {
      final String message = decoded['message']?.toString().trim() ?? '';

      throw _WalletException(
        message.isNotEmpty
            ? message
            : 'Unable to retrieve the logistics wallet.',
      );
    }

    final Map<String, dynamic> data = _asMap(decoded['data']);

    if (data['wallet'] is! Map) {
      throw const _WalletException(
        'The wallet API did not provide wallet information.',
      );
    }

    if (data['transactions'] is! List) {
      throw const _WalletException(
        'The wallet API did not provide transaction records.',
      );
    }

    return data;
  }

  // ============================================================
  // LOAD WALLET
  // ============================================================

  Future<void> _loadWallet({bool refresh = false}) async {
    if (!mounted || _isFetching || _isLoadingMore) {
      return;
    }

    final int requestId = ++_requestId;

    setState(() {
      _isFetching = true;
      _isLoading = !_hasLoaded;
      _isRefreshing = _hasLoaded;
      _errorMessage = null;
      _paginationError = null;
    });

    try {
      final Map<String, dynamic> data = await _fetchWallet(page: 1);

      if (!mounted || requestId != _requestId) {
        return;
      }

      final Map<String, dynamic> pagination = _asMap(data['pagination']);

      setState(() {
        _wallet = _asMap(data['wallet']);

        _transactions = _asMapList(data['transactions']);

        _withdrawals = _asMapList(data['withdrawals']);

        _currentPage = 1;

        _lastPage = _positiveInt(pagination['last_page'], 1);

        _hasLoaded = true;
        _isLoading = false;
        _isRefreshing = false;
        _isFetching = false;
        _errorMessage = null;
      });
    } catch (error) {
      if (!mounted || requestId != _requestId) {
        return;
      }

      final String message = error is _WalletException
          ? error.message
          : 'Unable to connect to the BiliHub server. '
                'Please check your connection.';

      debugPrint('Logistics Wallet: $error');

      setState(() {
        _isLoading = false;
        _isRefreshing = false;
        _isFetching = false;
        _errorMessage = message;
      });
    }
  }

  // ============================================================
  // LOAD MORE TRANSACTIONS
  // ============================================================

  Future<void> _loadMoreTransactions() async {
    if (!mounted ||
        !_hasLoaded ||
        _isFetching ||
        _isLoadingMore ||
        !_hasMoreTransactions) {
      return;
    }

    final int requestId = _requestId;
    final int nextPage = _currentPage + 1;

    setState(() {
      _isLoadingMore = true;
      _paginationError = null;
    });

    try {
      final Map<String, dynamic> data = await _fetchWallet(page: nextPage);

      if (!mounted || requestId != _requestId) {
        return;
      }

      final List<Map<String, dynamic>> newTransactions = _asMapList(
        data['transactions'],
      );

      final Map<String, dynamic> pagination = _asMap(data['pagination']);

      setState(() {
        _transactions.addAll(newTransactions);

        _currentPage = nextPage;

        _lastPage = _positiveInt(pagination['last_page'], _lastPage);

        _isLoadingMore = false;
      });
    } catch (error) {
      if (!mounted || requestId != _requestId) {
        return;
      }

      setState(() {
        _isLoadingMore = false;
        _paginationError = error is _WalletException
            ? error.message
            : 'Unable to load more transactions.';
      });
    }
  }

  int _positiveInt(dynamic value, int fallback) {
    final int? parsed = int.tryParse(value?.toString() ?? '');

    if (parsed == null || parsed < 1) {
      return fallback;
    }

    return parsed;
  }

  // ============================================================
  // MONEY HELPERS
  // All backend minor-unit values are centavos.
  // Display values in Philippine pesos.
  // ============================================================

  int? _minorValue(String key) {
    final dynamic value = _wallet[key];

    if (value is int) {
      return value;
    }

    if (value is num) {
      return value.toInt();
    }

    return int.tryParse(value?.toString() ?? '');
  }

  String _moneyFromMinor(dynamic value) {
    final int? minor;

    if (value is int) {
      minor = value;
    } else if (value is num) {
      minor = value.toInt();
    } else {
      minor = int.tryParse(value?.toString() ?? '');
    }

    if (minor == null) {
      return 'Not available';
    }

    return MoneyFormatter.peso(minor / 100);
  }

  String _walletMoney(String key) {
    return _moneyFromMinor(_minorValue(key));
  }

  // ============================================================
  // TEXT HELPERS
  // ============================================================

  String _displayText(dynamic value, {String fallback = 'Not available'}) {
    final String text = value?.toString().trim() ?? '';

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return fallback;
    }

    return text;
  }

  String _formatLabel(dynamic value) {
    final String text = _displayText(
      value,
      fallback: '',
    ).replaceAll('_', ' ').replaceAll('-', ' ');

    if (text.isEmpty) {
      return 'Not available';
    }

    return text
        .split(RegExp(r'\s+'))
        .where((String word) => word.isNotEmpty)
        .map(
          (String word) =>
              '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  String _formatDate(dynamic value) {
    final String text = _displayText(value, fallback: '');

    if (text.isEmpty) {
      return 'Date unavailable';
    }

    final DateTime? date = DateTime.tryParse(text);

    if (date == null) {
      return text;
    }

    final DateTime local = date.toLocal();

    final String month = local.month.toString().padLeft(2, '0');

    final String day = local.day.toString().padLeft(2, '0');

    final String hour = local.hour.toString().padLeft(2, '0');

    final String minute = local.minute.toString().padLeft(2, '0');

    return '${local.year}-$month-$day $hour:$minute';
  }

  Color _statusColor(String status) {
    switch (status.toLowerCase().trim()) {
      case 'paid':
      case 'completed':
      case 'credited':
        return LogisticsColors.success;

      case 'pending':
      case 'processing':
      case 'eligible':
        return LogisticsColors.warning;

      case 'rejected':
      case 'failed':
      case 'cancelled':
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
            onPressed: _isFetching || _isLoadingMore
                ? null
                : () => _loadWallet(refresh: true),
            icon: _isRefreshing
                ? const SizedBox(
                    width: 19,
                    height: 19,
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
                _walletOverview(),
                const SizedBox(height: 22),
                _earningsSummary(),
                const SizedBox(height: 22),
                _withdrawalSummary(),
                const SizedBox(height: 22),
                _transactionSection(),
                const SizedBox(height: 22),
                _withdrawalHistorySection(),
              ],
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // ERROR BANNER
  // ============================================================

  Widget _errorBanner() {
    return Container(
      padding: const EdgeInsets.all(13),
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

  // ============================================================
  // WALLET OVERVIEW
  // ============================================================

  Widget _walletOverview() {
    final String available = _walletMoney('available_balance_minor');

    final String totalEarned = _walletMoney('total_earned_minor');

    final bool hasPendingWithdrawal = _wallet['has_pending_withdrawal'] == true;

    return Container(
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
            'Actual balances from the Laravel logistics wallet',
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
                  'Confirmed wallet balance recorded by Laravel.',
                  style: LogisticsStyle.small,
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _statCard(
                  title: 'Total Earned',
                  value: totalEarned,
                  icon: Icons.payments_outlined,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _statCard(
                  title: 'Withdrawal Status',
                  value: hasPendingWithdrawal
                      ? 'Pending'
                      : 'No pending request',
                  icon: Icons.hourglass_top_rounded,
                  moneyValue: false,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _statCard({
    required String title,
    required String value,
    required IconData icon,
    bool moneyValue = true,
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
            width: 36,
            height: 36,
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
          'Based on the same credited transactions as Logistics Web',
          style: LogisticsStyle.small,
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _statCard(
                title: "Today's Earnings",
                value: _walletMoney('today_earned_minor'),
                icon: Icons.today_outlined,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _statCard(
                title: 'Total Earnings',
                value: _walletMoney('total_earned_minor'),
                icon: Icons.account_balance_wallet_outlined,
              ),
            ),
          ],
        ),
      ],
    );
  }

  // ============================================================
  // WITHDRAWAL SUMMARY
  // ============================================================

  Widget _withdrawalSummary() {
    final bool pending = _wallet['has_pending_withdrawal'] == true;

    return Container(
      padding: const EdgeInsets.all(17),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Withdrawal Summary', style: LogisticsStyle.sectionTitle),
          const SizedBox(height: 5),
          const Text(
            'Withdrawal information from the existing Web wallet',
            style: LogisticsStyle.small,
          ),
          const SizedBox(height: 15),
          _detailRow('Total Withdrawn', _walletMoney('total_withdrawn_minor')),
          const Divider(height: 22),
          _detailRow(
            'Minimum Withdrawal',
            _walletMoney('minimum_withdrawal_minor'),
          ),
          const Divider(height: 22),
          _detailRow('Pending Request', pending ? 'Yes' : 'No'),
          const SizedBox(height: 14),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: LogisticsColors.background,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Text(
              'Withdrawal requests and COD remittance are '
              'separate financial workflows. This screen '
              'currently displays verified wallet records only.',
              style: LogisticsStyle.small,
            ),
          ),
        ],
      ),
    );
  }

  Widget _detailRow(String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: Text(label, style: LogisticsStyle.small)),
        const SizedBox(width: 12),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: LogisticsStyle.cardTitle,
          ),
        ),
      ],
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
          'Recent wallet transactions recorded by Laravel',
          style: LogisticsStyle.small,
        ),
        const SizedBox(height: 12),

        if (_transactions.isEmpty)
          const AppEmptyState(
            title: 'No Wallet Transactions',
            message: 'No wallet transactions have been recorded yet.',
            icon: Icons.receipt_long_outlined,
            color: LogisticsColors.primary,
            compact: true,
          )
        else ...[
          ..._transactions.map(
            (Map<String, dynamic> item) => _transactionItem(item),
          ),

          if (_paginationError != null) ...[
            const SizedBox(height: 10),
            Text(
              _paginationError!,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: LogisticsColors.danger,
                fontSize: 12,
              ),
            ),
          ],

          if (_hasMoreTransactions) ...[
            const SizedBox(height: 12),
            Center(
              child: OutlinedButton.icon(
                onPressed: _isLoadingMore ? null : _loadMoreTransactions,
                icon: _isLoadingMore
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.expand_more_rounded),
                label: Text(
                  _isLoadingMore ? 'Loading...' : 'Load More Transactions',
                ),
              ),
            ),
          ],
        ],
      ],
    );
  }

  Widget _transactionItem(Map<String, dynamic> item) {
    final String type = _formatLabel(item['type']);

    final String description = _displayText(
      item['description'],
      fallback: type,
    );

    final String tracking = _displayText(item['tracking_number'], fallback: '');

    final int amountMinor = _minorFromValue(item['amount_minor']);

    final Color amountColor = amountMinor < 0
        ? LogisticsColors.danger
        : LogisticsColors.success;

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(15),
      decoration: LogisticsStyle.card,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.receipt_long_outlined,
              color: LogisticsColors.primary,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  description,
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: LogisticsStyle.cardTitle,
                ),
                const SizedBox(height: 5),
                Text(type, style: LogisticsStyle.small),
                if (tracking.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text('Tracking: $tracking', style: LogisticsStyle.small),
                ],
                const SizedBox(height: 6),
                Text(
                  _formatDate(item['created_at']),
                  style: LogisticsStyle.small,
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Text(
            _moneyFromMinor(item['amount_minor']),
            textAlign: TextAlign.right,
            style: TextStyle(
              color: amountColor,
              fontWeight: FontWeight.w800,
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }

  int _minorFromValue(dynamic value) {
    if (value is int) {
      return value;
    }

    if (value is num) {
      return value.toInt();
    }

    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  // ============================================================
  // WITHDRAWAL HISTORY
  // ============================================================

  Widget _withdrawalHistorySection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Withdrawal History', style: LogisticsStyle.sectionTitle),
        const SizedBox(height: 5),
        const Text(
          'Recent withdrawal requests and their statuses',
          style: LogisticsStyle.small,
        ),
        const SizedBox(height: 12),

        if (_withdrawals.isEmpty)
          const AppEmptyState(
            title: 'No Withdrawal Requests',
            message: 'No logistics withdrawal requests have been recorded.',
            icon: Icons.account_balance_outlined,
            color: LogisticsColors.primary,
            compact: true,
          )
        else
          ..._withdrawals.map((Map<String, dynamic> withdrawal) {
            final String status = _displayText(withdrawal['status']);

            final Color color = _statusColor(status);

            return Container(
              width: double.infinity,
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(15),
              decoration: LogisticsStyle.card,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.account_balance_outlined,
                    color: LogisticsColors.primary,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _formatLabel(withdrawal['payment_method']),
                          style: LogisticsStyle.cardTitle,
                        ),
                        const SizedBox(height: 5),
                        Text(
                          _formatDate(withdrawal['created_at']),
                          style: LogisticsStyle.small,
                        ),
                        const SizedBox(height: 5),
                        Text(
                          _formatLabel(status),
                          style: TextStyle(
                            color: color,
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    _moneyFromMinor(withdrawal['amount_minor']),
                    style: LogisticsStyle.cardTitle,
                  ),
                ],
              ),
            );
          }),
      ],
    );
  }
}

class _WalletException implements Exception {
  final String message;

  const _WalletException(this.message);

  @override
  String toString() => message;
}
