import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

import '../../widgets/dashboard_card.dart';

class ParcelStatistics extends StatelessWidget {
  const ParcelStatistics({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: LogisticsColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        iconTheme: const IconThemeData(color: Colors.black),

        title: const Text("Parcel Statistics", style: LogisticsStyle.title),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              const Text(
                "Delivery Performance",

                style: LogisticsStyle.sectionTitle,
              ),

              const SizedBox(height: 15),

              GridView.count(
                shrinkWrap: true,

                physics: const NeverScrollableScrollPhysics(),

                crossAxisCount: 2,

                crossAxisSpacing: 12,

                mainAxisSpacing: 12,

                childAspectRatio: 1.3,

                children: [
                  DashboardCard(
                    title: "Assigned Parcels",

                    value: "500",

                    icon: Icons.inventory_2_outlined,

                    color: Colors.blue,
                  ),

                  DashboardCard(
                    title: "Scanned Parcels",

                    value: "350",

                    icon: Icons.qr_code_scanner,

                    color: Colors.orange,
                  ),

                  DashboardCard(
                    title: "Delivered",

                    value: "300",

                    icon: Icons.check_circle_outline,

                    color: Colors.green,
                  ),

                  DashboardCard(
                    title: "Remaining",

                    value: "200",

                    icon: Icons.pending_actions,

                    color: Colors.red,
                  ),
                ],
              ),

              const SizedBox(height: 30),

              Container(
                padding: const EdgeInsets.all(20),

                decoration: LogisticsStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text(
                      "Completion Progress",

                      style: LogisticsStyle.sectionTitle,
                    ),

                    const SizedBox(height: 20),

                    LinearProgressIndicator(
                      value: 0.60,

                      minHeight: 12,

                      borderRadius: BorderRadius.circular(20),
                    ),

                    const SizedBox(height: 12),

                    const Text(
                      "60% of assigned parcels completed",

                      style: LogisticsStyle.small,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              Container(
                padding: const EdgeInsets.all(20),

                decoration: LogisticsStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text(
                      "Today's Summary",

                      style: LogisticsStyle.sectionTitle,
                    ),

                    const SizedBox(height: 15),

                    _summaryRow(Icons.qr_code, "Parcels scanned today", "45"),

                    _summaryRow(
                      Icons.local_shipping,

                      "Currently in transit",

                      "120",
                    ),

                    _summaryRow(Icons.done_all, "Delivered today", "80"),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _summaryRow(IconData icon, String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15),

      child: Row(
        children: [
          Icon(icon, color: LogisticsColors.primary),

          const SizedBox(width: 12),

          Expanded(child: Text(title)),

          Text(value, style: const TextStyle(fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}
