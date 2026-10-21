import 'package:flutter/material.dart';

import '../../styles/rider_style.dart';

class CodRemittance extends StatefulWidget {
  const CodRemittance({super.key});

  @override
  State<CodRemittance> createState() =>
      _CodRemittanceState();
}

class _CodRemittanceState
    extends State<CodRemittance> {
  bool _isLoading = false;

  final List<Map<String, dynamic>> _availableCollections =
      <Map<String, dynamic>>[];

  final List<Map<String, dynamic>> _remittanceHistory =
      <Map<String, dynamic>>[];

  final Set<int> _selectedPaymentIds = <int>{};

  // ============================================================
  // SUMMARY VALUES
  // ============================================================

  String get _availableCashText {
    if (_availableCollections.isEmpty) {
      return '—';
    }

    double total = 0;

    for (final item in _availableCollections) {
      final amount = _toDouble(
        item['amount'],
      );

      if (amount != null) {
        total += amount;
      }
    }

    return _money(total);
  }

  String get _availablePaymentsText {
    if (_availableCollections.isEmpty) {
      return '—';
    }

    return _availableCollections.length.toString();
  }

  String get _pendingRemittancesText {
    if (_remittanceHistory.isEmpty) {
      return '—';
    }

    final pendingCount =
        _remittanceHistory.where(
      (item) {
        final status =
            item['status']
                ?.toString()
                .trim()
                .toLowerCase();

        return status == 'pending' ||
            status == 'submitted' ||
            status == 'for_verification';
      },
    ).length;

    return pendingCount.toString();
  }

  String get _selectedAmountText {
    if (_selectedPaymentIds.isEmpty) {
      return '₱0.00';
    }

    double total = 0;

    for (final item in _availableCollections) {
      final id = _toInt(
        item['id'],
      );

      if (id != null &&
          _selectedPaymentIds.contains(id)) {
        final amount = _toDouble(
          item['amount'],
        );

        if (amount != null) {
          total += amount;
        }
      }
    }

    return _money(total);
  }

  // ============================================================
  // HELPERS
  // ============================================================

  double? _toDouble(
    dynamic value,
  ) {
    if (value is num) {
      return value.toDouble();
    }

    return double.tryParse(
      value?.toString() ?? '',
    );
  }

  int? _toInt(
    dynamic value,
  ) {
    if (value is int) {
      return value;
    }

    return int.tryParse(
      value?.toString() ?? '',
    );
  }

  String _money(
    dynamic value,
  ) {
    final amount =
        _toDouble(value);

    if (amount == null) {
      return '—';
    }

    return '₱${amount.toStringAsFixed(2)}';
  }

  String _displayValue(
    dynamic value, {
    String fallback = 'Not available',
  }) {
    final text =
        value?.toString().trim() ?? '';

    return text.isEmpty
        ? fallback
        : text;
  }

  String _formatStatus(
    dynamic value,
  ) {
    final text =
        value?.toString().trim() ?? '';

    if (text.isEmpty) {
      return 'Unknown';
    }

    return text
        .replaceAll('_', ' ')
        .split(RegExp(r'\s+'))
        .map(
          (word) =>
              word.isEmpty
                  ? word
                  : '${word[0].toUpperCase()}${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  Color _statusColor(
    dynamic value,
  ) {
    final status =
        value
            ?.toString()
            .trim()
            .toLowerCase();

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
  // REFRESH
  // ============================================================

  Future<void> _refresh() async {
    if (!mounted) return;

    setState(() {
      _isLoading = true;
    });

    await Future<void>.delayed(
      const Duration(milliseconds: 250),
    );

    if (!mounted) return;

    setState(() {
      _isLoading = false;
    });

    ScaffoldMessenger.of(context)
        .hideCurrentSnackBar();

    ScaffoldMessenger.of(context)
        .showSnackBar(
      const SnackBar(
        behavior:
            SnackBarBehavior.floating,

        content: Text(
          'COD Remittance API is not connected yet.',
        ),
      ),
    );
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(
    BuildContext context,
  ) {
    final width =
        MediaQuery.of(context).size.width;

    final horizontalPadding =
        width < 380 ? 14.0 : 18.0;

    return Scaffold(
      backgroundColor:
          RiderColors.background,

      appBar: AppBar(
        backgroundColor:
            RiderColors.white,

        elevation: 0,

        surfaceTintColor:
            Colors.transparent,

        iconTheme:
            const IconThemeData(
          color:
              RiderColors.textDark,
        ),

        titleSpacing: 18,

        title: const Column(
          crossAxisAlignment:
              CrossAxisAlignment.start,

          children: [
            Text(
              'COD Cash Remittance',
              style: RiderStyle.title,
            ),

            SizedBox(height: 2),

            Text(
              'Submit collected cash for verification',
              style: RiderStyle.small,
            ),
          ],
        ),

        actions: [
          IconButton(
            tooltip: 'Refresh',

            onPressed:
                _isLoading
                    ? null
                    : _refresh,

            icon: const Icon(
              Icons.refresh_rounded,

              color:
                  RiderColors.primary,
            ),
          ),

          const SizedBox(width: 5),
        ],
      ),

      body: SafeArea(
        top: false,

        child: RefreshIndicator(
          onRefresh:
              _refresh,

          child:
              SingleChildScrollView(
            physics:
                const AlwaysScrollableScrollPhysics(
              parent:
                  BouncingScrollPhysics(),
            ),

            padding:
                EdgeInsets.fromLTRB(
              horizontalPadding,
              14,
              horizontalPadding,
              30,
            ),

            child: Column(
              crossAxisAlignment:
                  CrossAxisAlignment.start,

              children: [
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
      alignment:
          Alignment.centerRight,

      child: Container(
        padding:
            const EdgeInsets.symmetric(
          horizontal: 11,
          vertical: 7,
        ),

        decoration:
            BoxDecoration(
          color:
              RiderColors.white,

          borderRadius:
              BorderRadius.circular(
            20,
          ),

          border:
              Border.all(
            color:
                RiderColors.border,
          ),
        ),

        child: const Row(
          mainAxisSize:
              MainAxisSize.min,

          children: [
            Icon(
              Icons.verified_user_outlined,

              color:
                  RiderColors.primary,

              size: 15,
            ),

            SizedBox(width: 5),

            Text(
              'Secure Cash Tracking',

              style: TextStyle(
                color:
                    RiderColors.textDark,

                fontSize: 10,

                fontWeight:
                    FontWeight.w700,
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
      crossAxisAlignment:
          CrossAxisAlignment.start,

      children: [
        const Text(
          'Remittance Summary',

          style:
              RiderStyle.sectionTitle,
        ),

        const SizedBox(height: 12),

        Row(
          children: [
            Expanded(
              child: _summaryCard(
                title:
                    'Available Cash',

                value:
                    _availableCashText,

                subtitle:
                    'Eligible for remittance',

                icon:
                    Icons.payments_outlined,

                background:
                    RiderColors.lightGreen,

                iconColor:
                    RiderColors.success,
              ),
            ),

            const SizedBox(width: 10),

            Expanded(
              child: _summaryCard(
                title:
                    'Available Payments',

                value:
                    _availablePaymentsText,

                subtitle:
                    'Delivered COD payments',

                icon:
                    Icons.receipt_long_outlined,

                background:
                    RiderColors.lightBlue,

                iconColor:
                    RiderColors.primary,
              ),
            ),
          ],
        ),

        const SizedBox(height: 10),

        _summaryCard(
          title:
              'Pending Remittances',

          value:
              _pendingRemittancesText,

          subtitle:
              'Waiting for verification',

          icon:
              Icons.hourglass_top_rounded,

          background:
              RiderColors.lightOrange,

          iconColor:
              RiderColors.warning,

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
      width:
          double.infinity,

      constraints:
          BoxConstraints(
        minHeight:
            wide ? 92 : 120,
      ),

      padding:
          const EdgeInsets.all(
        14,
      ),

      decoration:
          RiderStyle.card,

      child:
          wide
              ? Row(
                  children: [
                    Container(
                      height: 42,
                      width: 42,

                      decoration:
                          BoxDecoration(
                        color:
                            background,

                        borderRadius:
                            BorderRadius.circular(
                          12,
                        ),
                      ),

                      child: Icon(
                        icon,
                        color: iconColor,
                        size: 21,
                      ),
                    ),

                    const SizedBox(width: 12),

                    Expanded(
                      child: Column(
                        crossAxisAlignment:
                            CrossAxisAlignment.start,

                        children: [
                          Text(
                            title,
                            style:
                                RiderStyle.small,
                          ),

                          const SizedBox(height: 3),

                          Text(
                            value,

                            maxLines: 1,

                            overflow:
                                TextOverflow.ellipsis,

                            style:
                                const TextStyle(
                              color:
                                  RiderColors.textDark,

                              fontSize: 20,

                              fontWeight:
                                  FontWeight.w800,
                            ),
                          ),

                          const SizedBox(height: 2),

                          Text(
                            subtitle,

                            style:
                                RiderStyle.small,
                          ),
                        ],
                      ),
                    ),
                  ],
                )
              : Column(
                  crossAxisAlignment:
                      CrossAxisAlignment.start,

                  children: [
                    Container(
                      height: 38,
                      width: 38,

                      decoration:
                          BoxDecoration(
                        color:
                            background,

                        borderRadius:
                            BorderRadius.circular(
                          11,
                        ),
                      ),

                      child: Icon(
                        icon,
                        color: iconColor,
                        size: 20,
                      ),
                    ),

                    const Spacer(),

                    Text(
                      title,

                      maxLines: 2,

                      overflow:
                          TextOverflow.ellipsis,

                      style:
                          RiderStyle.small,
                    ),

                    const SizedBox(height: 3),

                    Text(
                      value,

                      maxLines: 1,

                      overflow:
                          TextOverflow.ellipsis,

                      style:
                          const TextStyle(
                        color:
                            RiderColors.textDark,

                        fontSize: 19,

                        fontWeight:
                            FontWeight.w800,
                      ),
                    ),

                    const SizedBox(height: 2),

                    Text(
                      subtitle,

                      maxLines: 1,

                      overflow:
                          TextOverflow.ellipsis,

                      style:
                          RiderStyle.small,
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
      width:
          double.infinity,

      padding:
          const EdgeInsets.all(
        16,
      ),

      decoration:
          RiderStyle.card,

      child: Column(
        crossAxisAlignment:
            CrossAxisAlignment.start,

        children: [
          const Text(
            'Available COD Collections',

            style:
                RiderStyle.sectionTitle,
          ),

          const SizedBox(height: 4),

          const Text(
            'Select payments belonging to the same logistics company.',

            style:
                RiderStyle.small,
          ),

          const SizedBox(height: 16),

          if (_availableCollections.isEmpty)
            _emptyCollectionsState()
          else
            ..._availableCollections.map(
              (item) =>
                  _collectionCard(
                item,
              ),
            ),

          if (_availableCollections.isNotEmpty) ...[
            const SizedBox(height: 12),

            Container(
              width:
                  double.infinity,

              padding:
                  const EdgeInsets.all(
                13,
              ),

              decoration:
                  BoxDecoration(
                color:
                    RiderColors.background,

                borderRadius:
                    BorderRadius.circular(
                  13,
                ),

                border:
                    Border.all(
                  color:
                      RiderColors.border,
                ),
              ),

              child: Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Selected Amount',

                      style:
                          RiderStyle.cardTitle,
                    ),
                  ),

                  Text(
                    _selectedAmountText,

                    style:
                        const TextStyle(
                      color:
                          RiderColors.primary,

                      fontSize: 17,

                      fontWeight:
                          FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 12),

            SizedBox(
              width:
                  double.infinity,

              height:
                  48,

              child:
                  ElevatedButton.icon(
                onPressed:
                    _selectedPaymentIds.isEmpty
                        ? null
                        : _submitRemittance,

                icon:
                    const Icon(
                  Icons.send_rounded,

                  size: 18,
                ),

                label:
                    const Text(
                  'Submit Remittance',
                ),

                style:
                    RiderStyle.primaryButton,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _emptyCollectionsState() {
    return Container(
      width:
          double.infinity,

      padding:
          const EdgeInsets.symmetric(
        vertical: 28,
        horizontal: 16,
      ),

      decoration:
          BoxDecoration(
        color:
            RiderColors.background,

        borderRadius:
            BorderRadius.circular(
          15,
        ),

        border:
            Border.all(
          color:
              RiderColors.border,
        ),
      ),

      child: const Column(
        children: [
          Icon(
            Icons.check_circle_outline_rounded,

            color:
                RiderColors.textGrey,

            size: 38,
          ),

          SizedBox(height: 9),

          Text(
            'No COD collections available for remittance.',

            textAlign:
                TextAlign.center,

            style:
                RiderStyle.small,
          ),
        ],
      ),
    );
  }

  Widget _collectionCard(
    Map<String, dynamic> item,
  ) {
    final id =
        _toInt(
          item['id'],
        ) ??
        0;

    final selected =
        _selectedPaymentIds.contains(
      id,
    );

    return Container(
      margin:
          const EdgeInsets.only(
        bottom: 10,
      ),

      padding:
          const EdgeInsets.all(
        13,
      ),

      decoration:
          BoxDecoration(
        color:
            selected
                ? RiderColors.lightPurple
                : RiderColors.background,

        borderRadius:
            BorderRadius.circular(
          14,
        ),

        border:
            Border.all(
          color:
              selected
                  ? RiderColors.primary
                  : RiderColors.border,
        ),
      ),

      child: Column(
        crossAxisAlignment:
            CrossAxisAlignment.start,

        children: [
          Row(
            children: [
              Checkbox(
                value:
                    selected,

                onChanged:
                    (value) {
                  setState(() {
                    if (value == true) {
                      _selectedPaymentIds.add(
                        id,
                      );
                    } else {
                      _selectedPaymentIds.remove(
                        id,
                      );
                    }
                  });
                },
              ),

              Expanded(
                child: Column(
                  crossAxisAlignment:
                      CrossAxisAlignment.start,

                  children: [
                    Text(
                      _displayValue(
                        item['tracking_number'] ??
                            item['shipment'],
                        fallback:
                            'Shipment unavailable',
                      ),

                      maxLines:
                          1,

                      overflow:
                          TextOverflow.ellipsis,

                      style:
                          RiderStyle.cardTitle,
                    ),

                    const SizedBox(height: 3),

                    Text(
                      _displayValue(
                        item['payment_reference'] ??
                            item['payment'],
                        fallback:
                            'Payment reference unavailable',
                      ),

                      style:
                          RiderStyle.small,
                    ),
                  ],
                ),
              ),

              const SizedBox(width: 8),

              Text(
                _money(
                  item['amount'],
                ),

                style:
                    const TextStyle(
                  color:
                      RiderColors.primary,

                  fontSize: 16,

                  fontWeight:
                      FontWeight.w800,
                ),
              ),
            ],
          ),

          const SizedBox(height: 10),

          _detailRow(
            'Logistics',
            _displayValue(
              item['logistics_name'] ??
                  item['logistics'],
            ),
          ),

          _detailRow(
            'Collected',
            _displayValue(
              item['collected_at'],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // REMITTANCE HISTORY
  // ============================================================

  Widget _remittanceHistorySection() {
    return Container(
      width:
          double.infinity,

      padding:
          const EdgeInsets.all(
        16,
      ),

      decoration:
          RiderStyle.card,

      child: Column(
        crossAxisAlignment:
            CrossAxisAlignment.start,

        children: [
          const Text(
            'Remittance History',

            style:
                RiderStyle.sectionTitle,
          ),

          const SizedBox(height: 4),

          const Text(
            'Track submitted, confirmed, and rejected cash remittances.',

            style:
                RiderStyle.small,
          ),

          const SizedBox(height: 16),

          if (_remittanceHistory.isEmpty)
            _emptyHistoryState()
          else
            ..._remittanceHistory.map(
              (item) =>
                  _historyCard(
                item,
              ),
            ),
        ],
      ),
    );
  }

  Widget _emptyHistoryState() {
    return Container(
      width:
          double.infinity,

      padding:
          const EdgeInsets.symmetric(
        vertical: 26,
        horizontal: 16,
      ),

      decoration:
          BoxDecoration(
        color:
            RiderColors.background,

        borderRadius:
            BorderRadius.circular(
          15,
        ),

        border:
            Border.all(
          color:
              RiderColors.border,
        ),
      ),

      child: const Column(
        children: [
          Icon(
            Icons.history_rounded,

            color:
                RiderColors.textGrey,

            size: 38,
          ),

          SizedBox(height: 9),

          Text(
            'No remittance history yet.',

            textAlign:
                TextAlign.center,

            style:
                RiderStyle.small,
          ),
        ],
      ),
    );
  }

  Widget _historyCard(
    Map<String, dynamic> item,
  ) {
    final status =
        _formatStatus(
      item['status'],
    );

    final statusColor =
        _statusColor(
      item['status'],
    );

    return Container(
      margin:
          const EdgeInsets.only(
        bottom: 10,
      ),

      padding:
          const EdgeInsets.all(
        13,
      ),

      decoration:
          BoxDecoration(
        color:
            RiderColors.background,

        borderRadius:
            BorderRadius.circular(
          14,
        ),

        border:
            Border.all(
          color:
              RiderColors.border,
        ),
      ),

      child: Column(
        crossAxisAlignment:
            CrossAxisAlignment.start,

        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  _displayValue(
                    item['reference'],
                    fallback:
                        'Reference unavailable',
                  ),

                  maxLines: 1,

                  overflow:
                      TextOverflow.ellipsis,

                  style:
                      RiderStyle.cardTitle,
                ),
              ),

              const SizedBox(width: 8),

              Container(
                padding:
                    const EdgeInsets.symmetric(
                  horizontal: 9,
                  vertical: 5,
                ),

                decoration:
                    BoxDecoration(
                  color:
                      statusColor.withValues(
                    alpha: 0.10,
                  ),

                  borderRadius:
                      BorderRadius.circular(
                    20,
                  ),
                ),

                child: Text(
                  status,

                  style: TextStyle(
                    color:
                        statusColor,

                    fontSize: 9,

                    fontWeight:
                        FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          _detailRow(
            'Submitted',
            _displayValue(
              item['submitted_at'],
            ),
          ),

          _detailRow(
            'Payments',
            _displayValue(
              item['payments_count'] ??
                  item['payments'],
            ),
          ),

          _detailRow(
            'Amount',
            _money(
              item['amount'],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // DETAIL ROW
  // ============================================================

  Widget _detailRow(
    String label,
    String value,
  ) {
    return Padding(
      padding:
          const EdgeInsets.only(
        bottom: 7,
      ),

      child: Row(
        crossAxisAlignment:
            CrossAxisAlignment.start,

        children: [
          SizedBox(
            width: 82,

            child: Text(
              label,

              style:
                  RiderStyle.small,
            ),
          ),

          const SizedBox(width: 8),

          Expanded(
            child: Text(
              value,

              style:
                  RiderStyle.cardTitle,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // SUBMIT
  // ============================================================

  void _submitRemittance() {
    ScaffoldMessenger.of(context)
        .hideCurrentSnackBar();

    ScaffoldMessenger.of(context)
        .showSnackBar(
      const SnackBar(
        behavior:
            SnackBarBehavior.floating,

        content: Text(
          'COD remittance submission will be enabled once the Rider remittance API is connected.',
        ),
      ),
    );
  }
}