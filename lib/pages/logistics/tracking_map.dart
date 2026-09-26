import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

class TrackingMap extends StatelessWidget {
  const TrackingMap({super.key});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: LogisticsColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        iconTheme: const IconThemeData(color: Colors.black),

        title: const Text("Realtime Tracking", style: LogisticsStyle.title),
      ),

      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: Container(
                width: double.infinity,

                color: Colors.grey.shade300,

                child: const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,

                    children: [
                      Icon(Icons.map_outlined, size: 75, color: Colors.grey),

                      SizedBox(height: 15),

                      Text("Realtime Map View"),
                    ],
                  ),
                ),
              ),
            ),

            Container(
              padding: EdgeInsets.all(width * 0.05),

              decoration: const BoxDecoration(
                color: Colors.white,

                borderRadius: BorderRadius.vertical(top: Radius.circular(30)),
              ),

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  const Text(
                    "Active Rider",

                    style: LogisticsStyle.sectionTitle,
                  ),

                  const SizedBox(height: 15),

                  _riderInfo(),

                  const SizedBox(height: 20),

                  const Text(
                    "Delivery Progress",

                    style: LogisticsStyle.sectionTitle,
                  ),

                  const SizedBox(height: 15),

                  LinearProgressIndicator(
                    value: 0.65,

                    minHeight: 10,

                    borderRadius: BorderRadius.circular(10),
                  ),

                  const SizedBox(height: 10),

                  const Text("65% completed"),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _riderInfo() {
    return Container(
      padding: const EdgeInsets.all(16),

      decoration: LogisticsStyle.card,

      child: Row(
        children: [
          Container(
            height: 55,

            width: 55,

            decoration: BoxDecoration(
              shape: BoxShape.circle,

              color: Colors.blue.withValues(alpha: .1),
            ),

            child: const Icon(
              Icons.delivery_dining,

              color: Colors.blue,

              size: 35,
            ),
          ),

          const SizedBox(width: 12),

          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text("Rider Name", style: LogisticsStyle.cardTitle),

                SizedBox(height: 5),

                Text(
                  "Online • Delivering Parcel",

                  maxLines: 1,

                  overflow: TextOverflow.ellipsis,

                  style: LogisticsStyle.small,
                ),
              ],
            ),
          ),

          const SizedBox(width: 8),

          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),

            decoration: BoxDecoration(
              color: Colors.green.withValues(alpha: .1),

              borderRadius: BorderRadius.circular(20),
            ),

            child: const Text(
              "ONLINE",

              style: TextStyle(
                color: Colors.green,

                fontSize: 12,

                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
