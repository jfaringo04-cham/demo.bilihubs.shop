import 'package:flutter/material.dart';

import '../../styles/rider_style.dart';

class RiderHome extends StatelessWidget {
  const RiderHome({super.key});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: RiderColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        title: const Text("Rider Dashboard", style: RiderStyle.title),
      ),

      body: SingleChildScrollView(
        padding: EdgeInsets.all(width * 0.045),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            const Text("Good Morning, Rider", style: RiderStyle.sectionTitle),

            const SizedBox(height: 20),

            GridView.count(
              shrinkWrap: true,

              physics: const NeverScrollableScrollPhysics(),

              crossAxisCount: 2,

              crossAxisSpacing: 12,

              mainAxisSpacing: 12,

              childAspectRatio: 1.25,

              children: [
                _dashboardCard(
                  "Today's Delivery",

                  "12",

                  Icons.local_shipping_outlined,
                ),

                _dashboardCard("Completed", "8", Icons.check_circle_outline),

                _dashboardCard("Pending", "4", Icons.pending_actions),

                _dashboardCard("Earnings", "₱850", Icons.payments_outlined),
              ],
            ),

            const SizedBox(height: 25),

            Container(
              padding: const EdgeInsets.all(18),

              decoration: RiderStyle.card,

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  const Text("Today's Tasks", style: RiderStyle.sectionTitle),

                  const SizedBox(height: 15),

                  _taskItem("Parcel #001", "Deliver to Manila", "Pending"),

                  _taskItem(
                    "Parcel #002",

                    "Deliver to Quezon City",

                    "Completed",
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _dashboardCard(String title, String value, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(15),

      decoration: RiderStyle.card,

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        mainAxisAlignment: MainAxisAlignment.spaceBetween,

        children: [
          Icon(icon, color: RiderColors.primary, size: 28),

          Text(value, style: RiderStyle.title),

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

  Widget _taskItem(String title, String address, String status) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),

      padding: const EdgeInsets.all(14),

      decoration: BoxDecoration(
        color: Colors.grey.shade100,

        borderRadius: BorderRadius.circular(12),
      ),

      child: Row(
        children: [
          const Icon(Icons.inventory_2_outlined),

          const SizedBox(width: 12),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(title, style: RiderStyle.cardTitle),

                Text(
                  address,

                  maxLines: 1,

                  overflow: TextOverflow.ellipsis,

                  style: RiderStyle.small,
                ),
              ],
            ),
          ),

          const SizedBox(width: 8),

          Text(
            status,

            maxLines: 1,

            overflow: TextOverflow.ellipsis,

            style: RiderStyle.small,
          ),
        ],
      ),
    );
  }
}
