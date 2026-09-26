import 'package:flutter/material.dart';

import '../../styles/rider_style.dart';

import '../../widgets/parcel_card.dart';

import '../../models/parcel_model.dart';

class RiderDeliveries extends StatelessWidget {
  const RiderDeliveries({super.key});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    final List<ParcelModel> deliveries = [];

    return Scaffold(
      backgroundColor: RiderColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        iconTheme: const IconThemeData(color: Colors.black),

        title: const Text("My Deliveries", style: RiderStyle.title),
      ),

      body: SafeArea(
        child: deliveries.isEmpty
            ? _emptyState()
            : ListView.builder(
                padding: EdgeInsets.all(width * 0.045),

                itemCount: deliveries.length,

                itemBuilder: (context, index) {
                  return ParcelCard(
                    parcel: deliveries[index],

                    buttonText: "Scan Parcel",

                    onButtonPressed: () {
                      // Navigate to Rider Scanner
                    },
                  );
                },
              ),
      ),
    );
  }

  Widget _emptyState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(30),

        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,

          children: [
            Icon(Icons.local_shipping_outlined, size: 70, color: Colors.grey),

            const SizedBox(height: 15),

            const Text(
              "No deliveries assigned yet",

              textAlign: TextAlign.center,

              style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
            ),
          ],
        ),
      ),
    );
  }
}
