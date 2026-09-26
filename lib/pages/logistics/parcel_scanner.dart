import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

import '../../models/parcel_model.dart';

class ParcelScanner extends StatefulWidget {
  final ParcelModel? scannedParcel;

  const ParcelScanner({super.key, this.scannedParcel});

  @override
  State<ParcelScanner> createState() => _ParcelScannerState();
}

class _ParcelScannerState extends State<ParcelScanner> {
  bool flashlightOn = false;

  @override
  Widget build(BuildContext context) {
    final parcel = widget.scannedParcel;

    return Scaffold(
      backgroundColor: Colors.black,

      appBar: AppBar(
        backgroundColor: Colors.black,

        elevation: 0,

        iconTheme: const IconThemeData(color: Colors.white),

        title: const Text(
          "Parcel Scanner",

          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
        ),
      ),

      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: Stack(
                alignment: Alignment.center,

                children: [
                  Container(
                    margin: const EdgeInsets.all(35),

                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.white, width: 3),

                      borderRadius: BorderRadius.circular(25),
                    ),
                  ),

                  const Column(
                    mainAxisAlignment: MainAxisAlignment.center,

                    children: [
                      Icon(
                        Icons.qr_code_scanner,

                        size: 90,

                        color: Colors.white,
                      ),

                      SizedBox(height: 15),

                      Text(
                        "Scan parcel barcode",

                        style: TextStyle(color: Colors.white, fontSize: 16),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            IconButton(
              onPressed: () {
                setState(() {
                  flashlightOn = !flashlightOn;
                });
              },

              icon: Icon(
                flashlightOn ? Icons.flash_on : Icons.flash_off,

                color: Colors.white,

                size: 35,
              ),
            ),

            Container(
              width: double.infinity,

              padding: const EdgeInsets.all(20),

              decoration: const BoxDecoration(
                color: Colors.white,

                borderRadius: BorderRadius.vertical(top: Radius.circular(30)),
              ),

              child: parcel == null
                  ? const Column(
                      children: [
                        Icon(Icons.inventory_2_outlined, size: 45),

                        SizedBox(height: 10),

                        Text("Waiting for parcel scan"),
                      ],
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,

                      children: [
                        const Text(
                          "Parcel Detected",

                          style: LogisticsStyle.sectionTitle,
                        ),

                        const SizedBox(height: 15),

                        _infoRow("Tracking Number", parcel.trackingNumber),

                        _infoRow("Customer", parcel.customerName),

                        _infoRow("Status", parcel.status),

                        const SizedBox(height: 20),

                        SizedBox(
                          width: double.infinity,

                          height: 55,

                          child: ElevatedButton(
                            onPressed: () {},

                            style: ElevatedButton.styleFrom(
                              backgroundColor: LogisticsColors.primary,

                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(16),
                              ),
                            ),

                            child: const Text(
                              "View Parcel Information",

                              style: TextStyle(
                                color: Colors.white,

                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _infoRow(String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Text(title, style: LogisticsStyle.small),

          Text(value, style: LogisticsStyle.cardTitle),
        ],
      ),
    );
  }
}
