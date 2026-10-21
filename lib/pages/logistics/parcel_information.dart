import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../styles/logistics_style.dart';
import '../../widgets/status_chip.dart';

class ParcelInformation extends StatelessWidget {
  final ParcelModel parcel;

  const ParcelInformation({super.key, required this.parcel});

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: LogisticsColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        surfaceTintColor: Colors.transparent,

        iconTheme: const IconThemeData(color: Colors.black),

        title: const Text('Parcel Information', style: LogisticsStyle.title),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),

          padding: EdgeInsets.all(width * 0.045),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              // ============================================================
              // STATUS
              // ============================================================

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
                            'Current Status',

                            style: LogisticsStyle.sectionTitle,
                          ),
                        ),

                        StatusChip(
                          status: _displayValue(
                            parcel.status,
                            fallback: 'unknown',
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 18),

              // ============================================================
              // PARCEL DETAILS
              // ============================================================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(20),

                decoration: LogisticsStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text(
                      'Parcel Details',

                      style: LogisticsStyle.sectionTitle,
                    ),

                    const SizedBox(height: 18),

                    _infoItem(
                      Icons.qr_code_2_rounded,

                      'Tracking Number',

                      _displayValue(parcel.trackingNumber),
                    ),

                    _infoItem(
                      Icons.person_outline_rounded,

                      'Customer Name',

                      _displayValue(parcel.customerName),
                    ),

                    _infoItem(
                      Icons.phone_outlined,

                      'Customer Phone',

                      _displayValue(parcel.customerPhone),
                    ),

                    _infoItem(
                      Icons.location_on_outlined,

                      'Delivery Address',

                      _displayValue(parcel.deliveryAddress),
                    ),

                    _infoItem(
                      Icons.delivery_dining_rounded,

                      'Assigned Rider',

                      _displayValue(
                        parcel.riderName,
                        fallback: 'Not assigned yet',
                      ),
                    ),

                    _infoItem(
                      Icons.badge_outlined,

                      'Rider ID',

                      _displayValue(
                        parcel.riderId,
                        fallback: 'Not assigned yet',
                      ),
                    ),

                    _infoItem(
                      Icons.calendar_today_outlined,

                      'Order Date',

                      _displayValue(parcel.orderDate),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 18),

              // ============================================================
              // LOCATION INFORMATION
              // ============================================================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(20),

                decoration: LogisticsStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text(
                      'Location Information',

                      style: LogisticsStyle.sectionTitle,
                    ),

                    const SizedBox(height: 18),

                    _infoItem(
                      Icons.location_searching_rounded,

                      'Latitude',

                      _coordinateValue(parcel.latitude),
                    ),

                    _infoItem(
                      Icons.location_searching_rounded,

                      'Longitude',

                      _coordinateValue(parcel.longitude),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 18),

              // ============================================================
              // PROCESSING AVAILABILITY
              // ============================================================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(18),

                decoration: LogisticsStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Row(
                      children: [
                        Icon(
                          Icons.inventory_2_outlined,

                          color: LogisticsColors.primary,

                          size: 22,
                        ),

                        SizedBox(width: 10),

                        Expanded(
                          child: Text(
                            'Parcel Processing',

                            style: LogisticsStyle.sectionTitle,
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 10),

                    const Text(
                      'Receiving, scanning, sorting, staging, and rider assignment will become available once the Logistics backend API is enabled.',

                      style: LogisticsStyle.small,
                    ),

                    const SizedBox(height: 14),

                    Container(
                      width: double.infinity,

                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 12,
                      ),

                      decoration: BoxDecoration(
                        color: LogisticsColors.lightPurple,

                        borderRadius: BorderRadius.circular(13),

                        border: Border.all(color: LogisticsColors.softPurple),
                      ),

                      child: const Row(
                        children: [
                          Icon(
                            Icons.lock_outline_rounded,

                            color: LogisticsColors.primary,

                            size: 19,
                          ),

                          SizedBox(width: 9),

                          Expanded(
                            child: Text(
                              'Processing actions are currently unavailable.',

                              style: LogisticsStyle.small,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 18),

              // ============================================================
              // DELIVERY NOTE
              // ============================================================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(16),

                decoration: LogisticsStyle.card,

                child: const Row(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Icon(
                      Icons.info_outline_rounded,

                      color: LogisticsColors.primary,
                    ),

                    SizedBox(width: 10),

                    Expanded(
                      child: Text(
                        'The final delivered status should be recorded by the assigned rider after a successful delivery.',

                        style: LogisticsStyle.small,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // INFORMATION ITEM
  // ============================================================

  Widget _infoItem(IconData icon, String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15),

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Container(
            height: 40,
            width: 40,

            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,

              borderRadius: BorderRadius.circular(12),
            ),

            child: Icon(icon, color: LogisticsColors.primary, size: 20),
          ),

          const SizedBox(width: 12),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(title, style: LogisticsStyle.small),

                const SizedBox(height: 4),

                Text(
                  value,

                  maxLines: 4,

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

  // ============================================================
  // DISPLAY VALUE
  // ============================================================

  String _displayValue(String value, {String fallback = 'Not available'}) {
    final cleaned = value.trim();

    if (cleaned.isEmpty) {
      return fallback;
    }

    return cleaned;
  }

  // ============================================================
  // COORDINATE VALUE
  // ============================================================

  String _coordinateValue(double value) {
    if (value == 0) {
      return 'Not available';
    }

    return value.toStringAsFixed(6);
  }
}
