import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';
import '../../services/api_services.dart';

class MyVouchersPage extends StatefulWidget {
  const MyVouchersPage({super.key});

  @override
  State<MyVouchersPage> createState() => _MyVouchersPageState();
}

class _MyVouchersPageState extends State<MyVouchersPage>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  bool _isLoading = true;
  bool _hasError = false;

  List<Map<String, dynamic>> _availableVouchers = [];
  List<Map<String, dynamic>> _claimedVouchers = [];

  final Set<int> _claimingIds = {};

  @override
  void initState() {
    super.initState();

    _tabController = TabController(length: 4, vsync: this);

    _loadVouchers();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  // ============================================================
  // LOAD VOUCHERS
  // ============================================================

  Future<void> _loadVouchers({bool showLoading = true}) async {
    if (showLoading && mounted) {
      setState(() {
        _isLoading = true;
        _hasError = false;
      });
    }

    try {
      final results = await Future.wait([
        ApiService.getBuyerVouchers(),
        ApiService.getClaimedBuyerVouchers(),
      ]);

      if (!mounted) return;

      final availableResponse = results[0];
      final claimedResponse = results[1];

      final availableSuccess = availableResponse['success'] == true;
      final claimedSuccess = claimedResponse['success'] == true;

      if (!availableSuccess || !claimedSuccess) {
        setState(() {
          _hasError = true;
          _isLoading = false;
        });

        return;
      }

      setState(() {
        _availableVouchers = _parseList(availableResponse['data']);
        _claimedVouchers = _parseList(claimedResponse['data']);

        _hasError = false;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;

      setState(() {
        _hasError = true;
        _isLoading = false;
      });
    }
  }

  List<Map<String, dynamic>> _parseList(dynamic value) {
    if (value is! List) return [];

    return value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  // ============================================================
  // CLAIM VOUCHER
  // ============================================================

  Future<void> _claimVoucher(Map<String, dynamic> voucher) async {
    final int? voucherId = int.tryParse('${voucher['id'] ?? ''}');

    if (voucherId == null || voucherId <= 0) {
      _showMessage('Invalid voucher. Please try again.', isError: true);
      return;
    }

    if (_claimingIds.contains(voucherId)) return;

    setState(() {
      _claimingIds.add(voucherId);
    });

    try {
      final response = await ApiService.claimBuyerVoucher(voucherId: voucherId);

      if (!mounted) return;

      if (response['success'] == true) {
        _showMessage(response['message'] ?? 'Voucher successfully claimed!');

        await _loadVouchers(showLoading: false);

        if (mounted) {
          _tabController.animateTo(1);
        }
      } else {
        _showMessage(
          response['message'] ?? 'Unable to claim voucher.',
          isError: true,
        );

        await _loadVouchers(showLoading: false);
      }
    } catch (_) {
      if (mounted) {
        _showMessage('Something went wrong. Please try again.', isError: true);
      }
    } finally {
      if (mounted) {
        setState(() {
          _claimingIds.remove(voucherId);
        });
      }
    }
  }

  // ============================================================
  // MESSAGE
  // ============================================================

  void _showMessage(String message, {bool isError = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: isError
              ? Colors.red.shade700
              : Colors.green.shade700,
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  // ============================================================
  // VOUCHER STATUS FILTERING
  // ============================================================

  List<Map<String, dynamic>> get _usedVouchers {
    return _claimedVouchers.where((voucher) {
      return voucher['status']?.toString().toLowerCase() == 'used';
    }).toList();
  }

  List<Map<String, dynamic>> get _expiredVouchers {
    return _claimedVouchers.where((voucher) {
      final status = voucher['status']?.toString().toLowerCase();

      return ['expired', 'paused', 'exhausted'].contains(status);
    }).toList();
  }

  List<Map<String, dynamic>> get _activeClaimedVouchers {
    return _claimedVouchers.where((voucher) {
      final status = voucher['status']?.toString().toLowerCase();

      return !['used', 'expired', 'paused', 'exhausted'].contains(status);
    }).toList();
  }

  // ============================================================
  // FORMAT HELPERS
  // ============================================================

  String _money(dynamic value) {
    final minor = int.tryParse('$value') ?? 0;

    return '₱${(minor / 100).toStringAsFixed(2)}';
  }

  String _discountLabel(Map<String, dynamic> voucher) {
    final type = voucher['discount_type']?.toString();

    if (type == 'percentage') {
      final bps = int.tryParse('${voucher['discount_percent_bps'] ?? 0}') ?? 0;

      final percent = bps / 100;

      return '${percent.toStringAsFixed(percent == percent.roundToDouble() ? 0 : 2)}% OFF';
    }

    final amount = voucher['discount_value_minor'];

    return '${_money(amount)} OFF';
  }

  String _formatDate(dynamic value) {
    if (value == null) return 'No expiration date';

    final date = DateTime.tryParse(value.toString());

    if (date == null) return 'Date unavailable';

    final localDate = date.toLocal();

    const months = [
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

    return '${months[localDate.month - 1]} '
        '${localDate.day}, ${localDate.year}';
  }

  String _voucherOwner(Map<String, dynamic> voucher) {
    final owner = voucher['owner_type']?.toString().toLowerCase();

    return owner == 'seller' ? 'Seller Voucher' : 'BiliHub Voucher';
  }

  String _voucherStatus(Map<String, dynamic> voucher) {
    final status = voucher['status']?.toString().toLowerCase() ?? '';

    switch (status) {
      case 'used':
        return 'USED';
      case 'expired':
        return 'EXPIRED';
      case 'paused':
        return 'PAUSED';
      case 'scheduled':
        return 'COMING SOON';
      case 'exhausted':
        return 'FULLY REDEEMED';
      default:
        return 'CLAIMED';
    }
  }

  // ============================================================
  // MAIN UI
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        centerTitle: false,
        iconTheme: const IconThemeData(color: BuyerColors.textDark),
        title: const Text('My Vouchers', style: BuyerStyle.appBarTitle),
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          labelColor: BuyerColors.primary,
          unselectedLabelColor: BuyerColors.textGrey,
          indicatorColor: BuyerColors.primary,
          indicatorWeight: 3,
          labelStyle: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
          ),
          tabs: [
            const Tab(text: 'Available'),
            Tab(text: 'Claimed (${_activeClaimedVouchers.length})'),
            Tab(text: 'Used (${_usedVouchers.length})'),
            Tab(text: 'Expired (${_expiredVouchers.length})'),
          ],
        ),
      ),
      body: Column(
        children: [
          _buildVoucherBanner(),
          Expanded(child: _buildBody()),
        ],
      ),
    );
  }

  // ============================================================
  // BODY
  // ============================================================

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(
        child: CircularProgressIndicator(color: BuyerColors.primary),
      );
    }

    if (_hasError) {
      return _buildErrorState();
    }

    return TabBarView(
      controller: _tabController,
      children: [
        _buildVoucherList(
          vouchers: _availableVouchers,
          emptyTitle: 'No Available Vouchers',
          emptyMessage:
              'Check back later for exciting discounts '
              'and special offers from BiliHub.',
          emptyIcon: Icons.confirmation_number_outlined,
        ),
        _buildVoucherList(
          vouchers: _activeClaimedVouchers,
          emptyTitle: 'No Claimed Vouchers',
          emptyMessage:
              'Vouchers you claim will appear here. '
              'Claim vouchers to enjoy discounts!',
          emptyIcon: Icons.card_giftcard_outlined,
        ),
        _buildVoucherList(
          vouchers: _usedVouchers,
          emptyTitle: 'No Used Vouchers',
          emptyMessage:
              'Your successfully redeemed vouchers '
              'will appear here.',
          emptyIcon: Icons.receipt_long_outlined,
        ),
        _buildVoucherList(
          vouchers: _expiredVouchers,
          emptyTitle: 'No Expired Vouchers',
          emptyMessage:
              'Your expired or unavailable vouchers '
              'will appear here.',
          emptyIcon: Icons.history_outlined,
        ),
      ],
    );
  }

  // ============================================================
  // ERROR STATE
  // ============================================================

  Widget _buildErrorState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.wifi_off_rounded, size: 55, color: BuyerColors.textGrey),
            const SizedBox(height: 18),
            const Text(
              'Unable to Load Vouchers',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: BuyerColors.textDark,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Please check your internet connection '
              'and try again.',
              textAlign: TextAlign.center,
              style: TextStyle(color: BuyerColors.textGrey, fontSize: 13),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: () => _loadVouchers(),
              icon: const Icon(Icons.refresh),
              label: const Text('Retry'),
              style: ElevatedButton.styleFrom(
                backgroundColor: BuyerColors.primary,
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // VOUCHER LIST WITH PULL TO REFRESH
  // ============================================================

  Widget _buildVoucherList({
    required List<Map<String, dynamic>> vouchers,
    required String emptyTitle,
    required String emptyMessage,
    required IconData emptyIcon,
  }) {
    if (vouchers.isEmpty) {
      return RefreshIndicator(
        color: BuyerColors.primary,
        onRefresh: () => _loadVouchers(showLoading: false),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            SizedBox(
              height: 400,
              child: _buildEmptyState(
                icon: emptyIcon,
                title: emptyTitle,
                message: emptyMessage,
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      color: BuyerColors.primary,
      onRefresh: () => _loadVouchers(showLoading: false),
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 25),
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: vouchers.length,
        itemBuilder: (context, index) {
          return _buildVoucherCard(vouchers[index]);
        },
      ),
    );
  }

  // ============================================================
  // VOUCHER CARD
  // ============================================================

  Widget _buildVoucherCard(Map<String, dynamic> voucher) {
    final id = int.tryParse('${voucher['id'] ?? ''}') ?? 0;

    final isAvailable = _availableVouchers.any(
      (item) => item['id'].toString() == voucher['id'].toString(),
    );

    final isClaiming = _claimingIds.contains(id);

    final status = voucher['status']?.toString().toLowerCase() ?? '';

    final isDisabled = [
      'used',
      'expired',
      'paused',
      'exhausted',
    ].contains(status);

    final endDate = _formatDate(voucher['ends_at']);

    final minimumSpend =
        int.tryParse('${voucher['minimum_spend_minor'] ?? 0}') ?? 0;

    final maximumDiscount = voucher['maximum_discount_minor'];

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: BuyerColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: BuyerColors.primary.withValues(alpha: 0.12)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.035),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  height: 58,
                  width: 58,
                  decoration: BoxDecoration(
                    color: BuyerColors.lightPurple,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(
                    Icons.confirmation_number_outlined,
                    color: BuyerColors.primary,
                    size: 31,
                  ),
                ),
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _discountLabel(voucher),
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                          color: BuyerColors.primary,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        voucher['name']?.toString() ?? 'Special Voucher',
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: BuyerColors.textDark,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _voucherOwner(voucher),
                        style: const TextStyle(
                          fontSize: 11,
                          color: BuyerColors.textGrey,
                        ),
                      ),
                    ],
                  ),
                ),
                if (!isAvailable)
                  _buildStatusChip(
                    _voucherStatus(voucher),
                    isDisabled: isDisabled,
                  ),
              ],
            ),
          ),

          if ((voucher['description'] ?? '').toString().isNotEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  voucher['description'].toString(),
                  style: const TextStyle(
                    color: BuyerColors.textGrey,
                    fontSize: 12,
                    height: 1.5,
                  ),
                ),
              ),
            ),

          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: BuyerColors.background,
              borderRadius: const BorderRadius.vertical(
                bottom: Radius.circular(16),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(
                      Icons.shopping_bag_outlined,
                      size: 15,
                      color: BuyerColors.textGrey,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        'Minimum spend: ${_money(minimumSpend)}',
                        style: const TextStyle(
                          fontSize: 11,
                          color: BuyerColors.textGrey,
                        ),
                      ),
                    ),
                  ],
                ),

                if (maximumDiscount != null) ...[
                  const SizedBox(height: 7),
                  Row(
                    children: [
                      const Icon(
                        Icons.discount_outlined,
                        size: 15,
                        color: BuyerColors.textGrey,
                      ),
                      const SizedBox(width: 6),
                      Text(
                        'Maximum discount: ${_money(maximumDiscount)}',
                        style: const TextStyle(
                          fontSize: 11,
                          color: BuyerColors.textGrey,
                        ),
                      ),
                    ],
                  ),
                ],

                const SizedBox(height: 7),

                Row(
                  children: [
                    const Icon(
                      Icons.calendar_today_outlined,
                      size: 14,
                      color: BuyerColors.textGrey,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        'Valid until: $endDate',
                        style: const TextStyle(
                          fontSize: 11,
                          color: BuyerColors.textGrey,
                        ),
                      ),
                    ),
                  ],
                ),

                if (voucher['code'] != null) ...[
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: BuyerColors.white,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                        color: BuyerColors.primary.withValues(alpha: 0.2),
                      ),
                    ),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.local_offer_outlined,
                          size: 15,
                          color: BuyerColors.primary,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            voucher['code'].toString(),
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 1,
                              color: BuyerColors.textDark,
                            ),
                          ),
                        ),
                        if (!isAvailable)
                          const Text(
                            'CLAIMED',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                              color: BuyerColors.primary,
                            ),
                          ),
                      ],
                    ),
                  ),
                ],

                if (isAvailable) ...[
                  const SizedBox(height: 13),
                  SizedBox(
                    width: double.infinity,
                    height: 43,
                    child: ElevatedButton(
                      onPressed: isClaiming || id <= 0
                          ? null
                          : () => _claimVoucher(voucher),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: BuyerColors.primary,
                        foregroundColor: Colors.white,
                        disabledBackgroundColor: BuyerColors.textGrey,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                      child: isClaiming
                          ? const SizedBox(
                              height: 19,
                              width: 19,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text(
                              'CLAIM VOUCHER',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.5,
                              ),
                            ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // STATUS CHIP
  // ============================================================

  Widget _buildStatusChip(String status, {bool isDisabled = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: isDisabled ? Colors.grey.shade200 : BuyerColors.lightPurple,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        status,
        style: TextStyle(
          fontSize: 9,
          fontWeight: FontWeight.w800,
          color: isDisabled ? Colors.grey.shade700 : BuyerColors.primary,
        ),
      ),
    );
  }

  // ============================================================
  // VOUCHER BANNER
  // ============================================================

  Widget _buildVoucherBanner() {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [BuyerColors.primary, BuyerColors.primaryDark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Enjoy More Savings!',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 19,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 7),
                Text(
                  'Claim vouchers and get discounts '
                  'on your favorite products.',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.9),
                    fontSize: 12,
                    height: 1.5,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          const Icon(
            Icons.confirmation_number_outlined,
            size: 55,
            color: Colors.white,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // EMPTY STATE
  // ============================================================

  Widget _buildEmptyState({
    required IconData icon,
    required String title,
    required String message,
  }) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              height: 105,
              width: 105,
              decoration: BoxDecoration(
                color: BuyerColors.lightPurple,
                borderRadius: BorderRadius.circular(30),
              ),
              child: Icon(icon, size: 52, color: BuyerColors.primary),
            ),
            const SizedBox(height: 22),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: BuyerColors.textDark,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 9),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: BuyerColors.textGrey,
                fontSize: 13,
                height: 1.5,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
