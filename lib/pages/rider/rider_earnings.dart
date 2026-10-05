import 'package:flutter/material.dart';

import '../../styles/rider_style.dart';

class Earnings extends StatelessWidget {
  const Earnings({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderColors.background,

      // ----------------------------------------------------------
      // APP BAR
      // ----------------------------------------------------------
      appBar: AppBar(
        backgroundColor: RiderColors.white,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        centerTitle: false,
        title: const Text('Earnings', style: RiderStyle.appBarTitle),
        leading: IconButton(
          onPressed: () => Navigator.pop(context),
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: RiderColors.textDark,
            size: 20,
          ),
        ),
      ),

      // ----------------------------------------------------------
      // BODY
      // ----------------------------------------------------------
      body: SafeArea(
        top: false,
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 14, 18, 30),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _totalEarningsCard(),

              const SizedBox(height: 22),

              const Text('Income Overview', style: RiderStyle.sectionTitle),

              const SizedBox(height: 12),

              _incomeGrid(),

              const SizedBox(height: 24),

              _earningsActivity(),

              const SizedBox(height: 24),

              _recentDeliveries(),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // TOTAL EARNINGS
  // ============================================================

  Widget _totalEarningsCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [RiderColors.primary, RiderColors.primaryDark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: RiderColors.primary.withValues(alpha: 0.20),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Stack(
        children: [
          // Decorative circle
          Positioned(
            right: -45,
            top: -55,
            child: Container(
              height: 145,
              width: 145,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.07),
              ),
            ),
          ),

          // Decorative circle
          Positioned(
            right: 30,
            bottom: -70,
            child: Container(
              height: 110,
              width: 110,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.05),
              ),
            ),
          ),

          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    height: 46,
                    width: 46,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(
                      Icons.account_balance_wallet_outlined,
                      color: Colors.white,
                      size: 24,
                    ),
                  ),

                  const SizedBox(width: 12),

                  const Expanded(
                    child: Text(
                      'Total Earnings',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),

                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.trending_up_rounded,
                          color: Colors.white,
                          size: 14,
                        ),
                        SizedBox(width: 4),
                        Text(
                          'Active',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 18),

              const Text(
                '₱75,000.00',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 30,
                  fontWeight: FontWeight.w800,
                  height: 1.1,
                ),
              ),

              const SizedBox(height: 5),

              const Text(
                'Your total earnings from deliveries',
                style: TextStyle(color: Colors.white70, fontSize: 12),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ============================================================
  // INCOME GRID
  // ============================================================

  Widget _incomeGrid() {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      crossAxisSpacing: 12,
      mainAxisSpacing: 12,

      // Slightly taller cards for mobile screens.
      childAspectRatio: 1.22,

      children: [
        _incomeCard(
          title: 'Today',
          value: '₱850',
          icon: Icons.today_outlined,
          iconBackground: RiderColors.lightPurple,
          iconColor: RiderColors.primary,
        ),

        _incomeCard(
          title: 'This Week',
          value: '₱4,200',
          icon: Icons.date_range_outlined,
          iconBackground: RiderColors.lightBlue,
          iconColor: RiderColors.delivering,
        ),

        _incomeCard(
          title: 'This Month',
          value: '₱18,000',
          icon: Icons.calendar_month_outlined,
          iconBackground: RiderColors.lightOrange,
          iconColor: RiderColors.warning,
        ),

        _incomeCard(
          title: 'Total Earnings',
          value: '₱75,000',
          icon: Icons.account_balance_wallet_outlined,
          iconBackground: RiderColors.lightGreen,
          iconColor: RiderColors.success,
        ),
      ],
    );
  }

  Widget _incomeCard({
    required String title,
    required String value,
    required IconData icon,
    required Color iconBackground,
    required Color iconColor,
  }) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 42,
            width: 42,
            decoration: BoxDecoration(
              color: iconBackground,
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(icon, color: iconColor, size: 21),
          ),

          const Spacer(),

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

          const SizedBox(height: 3),

          Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: RiderStyle.small,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // EARNINGS ACTIVITY
  // ============================================================

  Widget _earningsActivity() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Earnings Activity', style: RiderStyle.sectionTitle),
                    SizedBox(height: 3),
                    Text(
                      'Your recent earnings activity',
                      style: RiderStyle.small,
                    ),
                  ],
                ),
              ),

              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 7,
                ),
                decoration: BoxDecoration(
                  color: RiderColors.lightPurple,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: const Text(
                  'This Week',
                  style: TextStyle(
                    color: RiderColors.primary,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 20),

          // Visual earnings placeholder.
          // This can later be replaced with real API chart data.
          Container(
            height: 150,
            width: double.infinity,
            decoration: BoxDecoration(
              color: RiderColors.background,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: RiderColors.border),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  height: 50,
                  width: 50,
                  decoration: BoxDecoration(
                    color: RiderColors.lightPurple,
                    borderRadius: BorderRadius.circular(15),
                  ),
                  child: const Icon(
                    Icons.bar_chart_rounded,
                    color: RiderColors.primary,
                    size: 28,
                  ),
                ),

                const SizedBox(height: 10),

                const Text('Earnings Graph', style: RiderStyle.cardTitle),

                const SizedBox(height: 3),

                const Text(
                  'Chart data will be connected to the API',
                  textAlign: TextAlign.center,
                  style: RiderStyle.small,
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),

          Row(
            children: [
              Expanded(
                child: _activitySummary(
                  title: 'Deliveries',
                  value: '28',
                  icon: Icons.local_shipping_outlined,
                ),
              ),

              const SizedBox(width: 10),

              Expanded(
                child: _activitySummary(
                  title: 'Average',
                  value: '₱150',
                  icon: Icons.trending_up_rounded,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _activitySummary({
    required String title,
    required String value,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: RiderColors.lightPurple,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Container(
            height: 34,
            width: 34,
            decoration: BoxDecoration(
              color: RiderColors.white,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: RiderColors.primary, size: 18),
          ),

          const SizedBox(width: 9),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: RiderStyle.small),
                const SizedBox(height: 2),
                Text(value, style: RiderStyle.cardTitle),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // RECENT DELIVERIES
  // ============================================================

  Widget _recentDeliveries() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text('Recent Deliveries', style: RiderStyle.sectionTitle),
            ),

            InkWell(
              onTap: () {},
              borderRadius: BorderRadius.circular(10),
              child: const Padding(
                padding: EdgeInsets.symmetric(horizontal: 5, vertical: 5),
                child: Row(
                  children: [
                    Text(
                      'View All',
                      style: TextStyle(
                        color: RiderColors.primary,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    SizedBox(width: 4),
                    Icon(
                      Icons.arrow_forward_ios_rounded,
                      color: RiderColors.primary,
                      size: 11,
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),

        const SizedBox(height: 12),

        _earningItem(
          tracking: 'BH-2026-001245',
          amount: '₱70',
          status: 'Delivered',
        ),

        _earningItem(
          tracking: 'BH-2026-001246',
          amount: '₱80',
          status: 'Delivered',
        ),

        _earningItem(
          tracking: 'BH-2026-001247',
          amount: '₱100',
          status: 'Delivered',
        ),
      ],
    );
  }

  Widget _earningItem({
    required String tracking,
    required String amount,
    required String status,
  }) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(15),
      decoration: RiderStyle.card,
      child: Row(
        children: [
          // Delivery icon
          Container(
            height: 46,
            width: 46,
            decoration: BoxDecoration(
              color: RiderColors.lightGreen,
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(
              Icons.local_shipping_outlined,
              color: RiderColors.success,
              size: 22,
            ),
          ),

          const SizedBox(width: 12),

          // Tracking information
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  tracking,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: RiderStyle.cardTitle,
                ),

                const SizedBox(height: 4),

                Row(
                  children: [
                    const Icon(
                      Icons.check_circle_outline_rounded,
                      color: RiderColors.success,
                      size: 14,
                    ),

                    const SizedBox(width: 4),

                    Text(
                      status,
                      style: const TextStyle(
                        color: RiderColors.success,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          const SizedBox(width: 10),

          // Earnings amount
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              const Text('Earned', style: RiderStyle.small),

              const SizedBox(height: 2),

              Text(
                amount,
                style: const TextStyle(
                  color: RiderColors.primary,
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
