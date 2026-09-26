import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

import '../../models/parcel_model.dart';

import '../../widgets/parcel_card.dart';

class ParcelList extends StatelessWidget {
  const ParcelList({super.key});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    final List<ParcelModel> parcels = [];

    return Scaffold(
      backgroundColor: LogisticsColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        iconTheme: const IconThemeData(color: Colors.black),

        title: const Text("Parcel List", style: LogisticsStyle.title),
      ),

      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: EdgeInsets.all(width * 0.045),

              child: TextField(
                decoration: InputDecoration(
                  hintText: "Search tracking number...",

                  prefixIcon: const Icon(Icons.search),

                  filled: true,

                  fillColor: Colors.white,

                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(18),

                    borderSide: BorderSide.none,
                  ),
                ),
              ),
            ),

            Expanded(
              child: parcels.isEmpty
                  ? _emptyState()
                  : ListView.builder(
                      padding: EdgeInsets.symmetric(horizontal: width * 0.045),

                      itemCount: parcels.length,

                      itemBuilder: (context, index) {
                        return ParcelCard(
                          parcel: parcels[index],

                          buttonText: "View",

                          onButtonPressed: () {
                            // Navigate ParcelInformation
                          },
                        );
                      },
                    ),
            ),
          ],
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
            Icon(Icons.inventory_2_outlined, size: 70, color: Colors.grey),

            const SizedBox(height: 15),

            const Text(
              "No parcels available",

              textAlign: TextAlign.center,

              style: TextStyle(fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}
