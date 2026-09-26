import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';

import '../../styles/logistics_style.dart';

import '../../widgets/custom_button.dart';

import '../../widgets/status_chip.dart';

class ParcelInformation extends StatefulWidget {
  final ParcelModel parcel;

  const ParcelInformation({super.key, required this.parcel});

  @override
  State<ParcelInformation> createState() => _ParcelInformationState();
}

class _ParcelInformationState extends State<ParcelInformation> {
  late String selectedStatus;

  final List<String> statuses = [
    "Pending",

    "Preparing",

    "In Transit",

    "Out for Delivery",

    "Delivered",

    "Cancelled",
  ];

  @override
  void initState() {
    super.initState();

    selectedStatus = widget.parcel.status;
  }

  void updateStatus() {
    ScaffoldMessenger.of(context)
        .showSnackBar(const SnackBar(content: Text("Parcel status updated")));
  }

  @override
  Widget build(BuildContext context) {
    final parcel = widget.parcel;

    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: LogisticsColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        iconTheme: const IconThemeData(color: Colors.black),

        title: const Text("Parcel Information", style: LogisticsStyle.title),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: EdgeInsets.all(width * 0.045),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(20),

                decoration: LogisticsStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            "Current Status",

                            style: LogisticsStyle.sectionTitle,
                          ),
                        ),

                        StatusChip(status: parcel.status),
                      ],
                    ),

                    const SizedBox(height: 20),

                    _infoItem(
                      Icons.qr_code_2,

                      "Tracking Number",

                      parcel.trackingNumber,
                    ),

                    _infoItem(
                      Icons.person_outline,

                      "Customer Name",

                      parcel.customerName,
                    ),

                    _infoItem(
                      Icons.location_on_outlined,

                      "Delivery Address",

                      parcel.deliveryAddress,
                    ),

                    _infoItem(
                      Icons.delivery_dining,

                      "Assigned Rider",

                      parcel.riderName,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              Container(
                padding: const EdgeInsets.all(18),

                decoration: LogisticsStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text(
                      "Update Parcel Status",

                      style: LogisticsStyle.sectionTitle,
                    ),

                    const SizedBox(height: 15),

                    DropdownButtonFormField<String>(
                      initialValue: selectedStatus,

                      decoration: InputDecoration(
                        filled: true,

                        fillColor: Colors.white,

                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(15),

                          borderSide: BorderSide.none,
                        ),
                      ),

                      items: statuses.map((status) {
                        return DropdownMenuItem<String>(
                          value: status,

                          child: Text(status),
                        );
                      }).toList(),

                      onChanged: (value) {
                        if (value == null) return;

                        setState(() {
                          selectedStatus = value;
                        });
                      },
                    ),

                    const SizedBox(height: 20),

                    CustomButton(
                      text: "Update Status",

                      icon: Icons.update,

                      backgroundColor: LogisticsColors.primary,

                      onPressed: updateStatus,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _infoItem(IconData icon, String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15),

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Icon(icon, color: LogisticsColors.primary),

          const SizedBox(width: 12),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(title, style: LogisticsStyle.small),

                const SizedBox(height: 4),

                Text(
                  value,

                  maxLines: 3,

                  overflow: TextOverflow.ellipsis,

                  style: LogisticsStyle.cardTitle,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
