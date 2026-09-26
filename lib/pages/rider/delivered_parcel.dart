import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../styles/rider_style.dart';

class DeliveredParcels extends StatelessWidget {
  final List<ParcelModel> parcels;

  const DeliveredParcels({super.key, required this.parcels});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        iconTheme: const IconThemeData(color: Colors.black),

        title: const Text("Delivered Parcels", style: RiderStyle.title),
      ),

      body: SafeArea(
        child: parcels.isEmpty
            ? _emptyState()
            : ListView.builder(
                padding: const EdgeInsets.all(16),

                itemCount: parcels.length,

                itemBuilder: (context, index) {
                  final parcel = parcels[index];

                  return Container(
                    margin: const EdgeInsets.only(bottom: 15),

                    padding: const EdgeInsets.all(16),

                    decoration: RiderStyle.card,

                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,

                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,

                          children: [
                            Text(
                              parcel.trackingNumber,

                              style: TextStyle(
                                fontWeight: FontWeight.bold,

                                color: RiderColors.primary,
                              ),
                            ),

                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,

                                vertical: 5,
                              ),

                              decoration: BoxDecoration(
                                color: RiderColors.online.withValues(alpha: .1),

                                borderRadius: BorderRadius.circular(20),
                              ),

                              child: Text(
                                "Delivered",

                                style: TextStyle(
                                  color: RiderColors.online,

                                  fontSize: 12,

                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ],
                        ),

                        const SizedBox(height: 15),

                        _infoRow(Icons.person_outline, parcel.customerName),

                        _infoRow(
                          Icons.location_on_outlined,

                          parcel.deliveryAddress,
                        ),

                        _infoRow(
                          Icons.calendar_today_outlined,

                          parcel.orderDate,
                        ),

                        const Divider(),

                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,

                          children: [
                            const Text(
                              "Delivery Earnings",

                              style: RiderStyle.small,
                            ),

                            Text(
                              "₱${parcel.deliveryEarnings}",

                              style: TextStyle(
                                color: RiderColors.earnings,

                                fontWeight: FontWeight.bold,

                                fontSize: 16,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  );
                },
              ),
      ),
    );
  }

  Widget _infoRow(IconData icon, String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Icon(icon, size: 20, color: RiderColors.primary),

          const SizedBox(width: 10),

          Expanded(child: Text(text, style: RiderStyle.small)),
        ],
      ),
    );
  }

  Widget _emptyState() {
    return const Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,

        children: [
          Icon(Icons.check_circle_outline, size: 80),

          SizedBox(height: 15),

          Text("No delivered parcels yet"),
        ],
      ),
    );
  }
}
