import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

class LogisticsHome extends StatelessWidget {
  const LogisticsHome({super.key});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: LogisticsColors.background,
      body: SafeArea(
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          padding: EdgeInsets.all(width * 0.045),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ============================================================
              // HEADER
              // ============================================================

              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Welcome Back', style: LogisticsStyle.small),
                        SizedBox(height: 4),
                        Text('Logistics Staff', style: LogisticsStyle.title),
                        SizedBox(height: 4),
                        Text(
                          'Manage and monitor parcel deliveries.',
                          style: LogisticsStyle.subtitle,
                        ),
                      ],
                    ),
                  ),

                  Container(
                    height: 52,
                    width: 52,
                    decoration: BoxDecoration(
                      color: LogisticsColors.lightPurple,
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: const Icon(
                      Icons.warehouse_outlined,
                      color: LogisticsColors.primary,
                      size: 27,
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 28),

              // ============================================================
              // PARCEL OVERVIEW
              // ============================================================
              const Text('Parcel Overview', style: LogisticsStyle.sectionTitle),

              const SizedBox(height: 14),

              GridView.count(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                crossAxisCount: 2,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
                childAspectRatio: 1.25,
                children: [
                  _overviewCard(
                    title: 'Total Parcels',
                    value: '500',
                    icon: Icons.inventory_2_outlined,
                  ),
                  _overviewCard(
                    title: 'Waiting Delivery',
                    value: '80',
                    icon: Icons.pending_actions_rounded,
                  ),
                  _overviewCard(
                    title: 'In Transit',
                    value: '120',
                    icon: Icons.local_shipping_outlined,
                  ),
                  _overviewCard(
                    title: 'Delivered',
                    value: '300',
                    icon: Icons.check_circle_outline_rounded,
                  ),
                ],
              ),

              const SizedBox(height: 28),

              // ============================================================
              // TODAY'S ACTIVITY
              // ============================================================
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(18),
                decoration: LogisticsStyle.card,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      "Today's Activity",
                      style: LogisticsStyle.sectionTitle,
                    ),

                    const SizedBox(height: 5),

                    const Text(
                      'Your logistics activity for today',
                      style: LogisticsStyle.small,
                    ),

                    const SizedBox(height: 18),

                    _activityRow(
                      icon: Icons.qr_code_scanner_rounded,
                      title: 'Parcels Scanned Today',
                      value: '45',
                    ),

                    const SizedBox(height: 12),

                    _activityRow(
                      icon: Icons.percent_rounded,
                      title: 'Delivery Completion',
                      value: '85%',
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // ============================================================
              // SCAN PARCEL BANNER
              // ============================================================
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [
                      LogisticsColors.primary,
                      LogisticsColors.primaryDark,
                    ],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(22),
                  boxShadow: [
                    BoxShadow(
                      color: LogisticsColors.primary.withValues(alpha: 0.18),
                      blurRadius: 18,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    Container(
                      height: 52,
                      width: 52,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: const Icon(
                        Icons.qr_code_scanner_rounded,
                        color: Colors.white,
                        size: 29,
                      ),
                    ),

                    const SizedBox(width: 14),

                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Scan Parcel',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          SizedBox(height: 4),
                          Text(
                            'Scan parcels to update delivery status.',
                            style: TextStyle(
                              color: Colors.white70,
                              fontSize: 12,
                              height: 1.35,
                            ),
                          ),
                        ],
                      ),
                    ),

                    const Icon(
                      Icons.arrow_forward_ios_rounded,
                      color: Colors.white,
                      size: 16,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // PARCEL OVERVIEW CARD
  // ============================================================

  Widget _overviewCard({
    required String title,
    required String value,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: LogisticsColors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: LogisticsColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.035),
            blurRadius: 12,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 40,
            width: 40,
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: LogisticsColors.primary, size: 21),
          ),

          const Spacer(),

          Text(
            value,
            style: const TextStyle(
              color: LogisticsColors.primary,
              fontSize: 23,
              fontWeight: FontWeight.w800,
            ),
          ),

          const SizedBox(height: 2),

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
  // ACTIVITY ROW
  // ============================================================

  Widget _activityRow({
    required IconData icon,
    required String title,
    required String value,
  }) {
    return Row(
      children: [
        Container(
          height: 42,
          width: 42,
          decoration: BoxDecoration(
            color: LogisticsColors.lightPurple,
            borderRadius: BorderRadius.circular(13),
          ),
          child: Icon(icon, color: LogisticsColors.primary, size: 21),
        ),

        const SizedBox(width: 12),

        Expanded(
          child: Text(
            title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: LogisticsStyle.body,
          ),
        ),

        const SizedBox(width: 10),

        Text(
          value,
          style: const TextStyle(
            color: LogisticsColors.primary,
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}
