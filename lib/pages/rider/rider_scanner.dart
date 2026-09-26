import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';

import '../../styles/rider_style.dart';

class RiderScanner extends StatefulWidget {
  final ParcelModel? scannedParcel;

  const RiderScanner({super.key, this.scannedParcel});

  @override
  State<RiderScanner> createState() => _RiderScannerState();
}

class _RiderScannerState extends State<RiderScanner> {
  bool flashlightOn = false;

  @override
  Widget build(BuildContext context) {
    final parcel = widget.scannedParcel;

    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: Colors.black,

      appBar: AppBar(
        backgroundColor: Colors.black,

        elevation: 0,

        iconTheme: const IconThemeData(color: Colors.white),

        title: const Text(
          "Scan Parcel",

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
                    margin: EdgeInsets.all(width * 0.08),

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

                        size: 75,

                        color: Colors.white,
                      ),

                      SizedBox(height: 15),

                      Text(
                        "Scan delivery QR code",

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

                size: 32,
              ),
            ),

            Container(
              width: double.infinity,

              padding: EdgeInsets.all(width * 0.05),

              decoration: const BoxDecoration(
                color: Colors.white,

                borderRadius: BorderRadius.vertical(top: Radius.circular(30)),
              ),

              child: parcel == null
                  ? const Column(
                      children: [
                        Icon(Icons.inventory_2_outlined, size: 45),

                        SizedBox(height: 10),

                        Text("Waiting for scan..."),
                      ],
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,

                      children: [
                        const Text(
                          "Parcel Found",

                          style: RiderStyle.sectionTitle,
                        ),

                        const SizedBox(height: 15),

                        _infoRow("Tracking Number", parcel.trackingNumber),

                        _infoRow("Customer", parcel.customerName),

                        _infoRow("Address", parcel.deliveryAddress),

                        _infoRow("Status", parcel.status),

                        const SizedBox(height: 20),

                        SizedBox(
                          width: double.infinity,

                          height: 55,

                          child: ElevatedButton(
                            onPressed: () {
                              // Navigate DeliveryConfirmation
                            },

                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.blue,

                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(16),
                              ),
                            ),

                            child: const Text(
                              "Continue Delivery",

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
          Text(title, style: RiderStyle.small),

          Text(value, softWrap: true, style: RiderStyle.cardTitle),
        ],
      ),
    );
  }
}
