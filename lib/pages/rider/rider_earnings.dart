import 'package:flutter/material.dart';

import '../../styles/rider_style.dart';

import '../../widgets/dashboard_card.dart';

class Earnings extends StatelessWidget {
  const Earnings({super.key});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    final height = MediaQuery.of(context).size.height;

    return Scaffold(
      backgroundColor: RiderColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        iconTheme: const IconThemeData(color: Colors.black),

        title: const Text("Earnings", style: RiderStyle.title),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: EdgeInsets.all(width * 0.045),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              const Text("Income Overview", style: RiderStyle.sectionTitle),

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
                    title: "Today",

                    value: "₱850",

                    icon: Icons.today_outlined,

                    color: Colors.blue,
                  ),

                  DashboardCard(
                    title: "This Week",

                    value: "₱4,200",

                    icon: Icons.date_range_outlined,

                    color: Colors.green,
                  ),

                  DashboardCard(
                    title: "This Month",

                    value: "₱18,000",

                    icon: Icons.calendar_month_outlined,

                    color: Colors.orange,
                  ),

                  DashboardCard(
                    title: "Total Earnings",

                    value: "₱75,000",

                    icon: Icons.account_balance_wallet_outlined,

                    color: Colors.purple,
                  ),
                ],
              ),

              const SizedBox(height: 30),

              const Text("Earnings Chart", style: RiderStyle.sectionTitle),

              const SizedBox(height: 15),

              Container(
                height: height * 0.25,

                width: double.infinity,

                decoration: RiderStyle.card,

                child: const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,

                    children: [
                      Icon(Icons.bar_chart, size: 60, color: Colors.blue),

                      SizedBox(height: 10),

                      Text("Earnings Graph"),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 30),

              const Text("Recent Deliveries", style: RiderStyle.sectionTitle),

              const SizedBox(height: 15),

              _earningItem("BH-2026-001245", "₱70", "Delivered"),

              _earningItem("BH-2026-001246", "₱80", "Delivered"),
            ],
          ),
        ),
      ),
    );
  }

  Widget _earningItem(String tracking, String amount, String status) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),

      padding: const EdgeInsets.all(16),

      decoration: RiderStyle.card,

      child: Row(
        children: [
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

                const SizedBox(height: 5),

                Text(status, style: RiderStyle.small),
              ],
            ),
          ),

          Text(
            amount,

            style: const TextStyle(
              color: Colors.green,

              fontWeight: FontWeight.bold,

              fontSize: 18,
            ),
          ),
        ],
      ),
    );
  }
}
