import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

import '../../widgets/dashboard_card.dart';

class LogisticsHome extends StatelessWidget {
  const LogisticsHome({super.key});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: LogisticsColors.background,

      body: SafeArea(
        child: SingleChildScrollView(
          padding: EdgeInsets.all(width * 0.045),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,

                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      const Text("Welcome Back", style: LogisticsStyle.small),

                      Text("Logistics Staff", style: LogisticsStyle.title),
                    ],
                  ),

                  Container(
                    height: 48,

                    width: 48,

                    decoration: BoxDecoration(
                      shape: BoxShape.circle,

                      color: Colors.blue.withValues(alpha: .1),
                    ),

                    child: const Icon(
                      Icons.warehouse_outlined,

                      color: Colors.blue,
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 25),

              const Text("Parcel Overview", style: LogisticsStyle.sectionTitle),

              const SizedBox(height: 15),

              GridView.count(
                shrinkWrap: true,

                physics: const NeverScrollableScrollPhysics(),

                crossAxisCount: 2,

                crossAxisSpacing: 12,

                mainAxisSpacing: 12,

                childAspectRatio: 1.15,

                children: [
                  DashboardCard(
                    title: "Total Parcels",

                    value: "500",

                    icon: Icons.inventory_2_outlined,

                    color: Colors.blue,
                  ),

                  DashboardCard(
                    title: "Waiting Delivery",

                    value: "80",

                    icon: Icons.pending_actions,

                    color: Colors.orange,
                  ),

                  DashboardCard(
                    title: "In Transit",

                    value: "120",

                    icon: Icons.local_shipping_outlined,

                    color: Colors.purple,
                  ),

                  DashboardCard(
                    title: "Delivered",

                    value: "300",

                    icon: Icons.check_circle_outline,

                    color: Colors.green,
                  ),
                ],
              ),

              const SizedBox(height: 30),

              Container(
                padding: const EdgeInsets.all(18),

                decoration: LogisticsStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text(
                      "Today's Activity",

                      style: LogisticsStyle.sectionTitle,
                    ),

                    const SizedBox(height: 15),

                    _activityRow(
                      Icons.qr_code_scanner,

                      "Parcels Scanned Today",

                      "45",
                    ),

                    _activityRow(Icons.percent, "Delivery Completion", "85%"),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(20),

                decoration: BoxDecoration(
                  color: LogisticsColors.primary,

                  borderRadius: BorderRadius.circular(22),
                ),

                child: const Row(
                  children: [
                    Icon(Icons.qr_code_scanner, color: Colors.white, size: 38),

                    SizedBox(width: 15),

                    Expanded(
                      child: Text(
                        "Scan parcels to update delivery status",

                        style: TextStyle(
                          color: Colors.white,

                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _activityRow(IconData icon, String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15),

      child: Row(
        children: [
          Container(
            height: 40,

            width: 40,

            decoration: BoxDecoration(
              color: Colors.blue.withValues(alpha: .1),

              borderRadius: BorderRadius.circular(12),
            ),

            child: Icon(icon, color: Colors.blue),
          ),

          const SizedBox(width: 12),

          Expanded(
            child: Text(title, maxLines: 2, overflow: TextOverflow.ellipsis),
          ),

          Text(value, style: const TextStyle(fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}
