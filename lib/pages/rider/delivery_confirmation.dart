import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';

import '../../styles/rider_style.dart';

import '../../widgets/custom_button.dart';

class DeliveryConfirmation extends StatefulWidget {
  final ParcelModel parcel;

  const DeliveryConfirmation({super.key, required this.parcel});

  @override
  State<DeliveryConfirmation> createState() => _DeliveryConfirmationState();
}

class _DeliveryConfirmationState extends State<DeliveryConfirmation> {
  bool isLoading = false;

  Future<void> confirmDelivery() async {
    setState(() {
      isLoading = true;
    });

    await Future.delayed(const Duration(seconds: 1));

    if (!mounted) return;

    setState(() {
      isLoading = false;
    });

    showDialog(
      context: context,

      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),

          content: Column(
            mainAxisSize: MainAxisSize.min,

            children: [
              const Icon(Icons.check_circle, color: Colors.green, size: 70),

              const SizedBox(height: 15),

              const Text(
                "Parcel Delivered Successfully",

                textAlign: TextAlign.center,

                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
              ),
            ],
          ),

          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context);
              },

              child: const Text("Done"),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final parcel = widget.parcel;

    return Scaffold(
      backgroundColor: RiderColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        iconTheme: const IconThemeData(color: Colors.black),

        title: const Text("Confirm Delivery", style: RiderStyle.title),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(20),

                decoration: RiderStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text(
                      "Parcel Information",

                      style: RiderStyle.sectionTitle,
                    ),

                    const SizedBox(height: 20),

                    _infoRow("Tracking Number", parcel.trackingNumber),

                    _infoRow("Customer", parcel.customerName),

                    _infoRow("Address", parcel.deliveryAddress),

                    _infoRow("Status", parcel.status),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              Container(
                padding: const EdgeInsets.all(18),

                decoration: RiderStyle.card,

                child: const Row(
                  children: [
                    Icon(Icons.info_outline, color: Colors.blue),

                    SizedBox(width: 10),

                    Expanded(
                      child: Text(
                        "Make sure the parcel has been received by the customer before confirming.",
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 30),

              CustomButton(
                text: "CONFIRM DELIVERY",

                icon: Icons.check_circle_outline,

                isLoading: isLoading,

                backgroundColor: Colors.green,

                onPressed: confirmDelivery,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _infoRow(String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Text(title, style: RiderStyle.small),

          const SizedBox(height: 4),

          Text(value, style: RiderStyle.cardTitle),
        ],
      ),
    );
  }
}
