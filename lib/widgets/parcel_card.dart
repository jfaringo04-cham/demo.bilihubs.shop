import 'package:flutter/material.dart';

import '../models/parcel_model.dart';

class ParcelCard extends StatelessWidget {
  final ParcelModel parcel;

  final VoidCallback? onTap;

  final String? buttonText;

  final VoidCallback? onButtonPressed;

  const ParcelCard({
    super.key,

    required this.parcel,

    this.onTap,

    this.buttonText,

    this.onButtonPressed,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,

      child: Container(
        margin: const EdgeInsets.only(bottom: 15),

        padding: const EdgeInsets.all(16),

        decoration: BoxDecoration(
          color: Colors.white,

          borderRadius: BorderRadius.circular(20),

          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: .05),

              blurRadius: 15,

              offset: const Offset(0, 8),
            ),
          ],
        ),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            // HEADER

            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,

              children: [
                Expanded(
                  child: Text(
                    parcel.trackingNumber,

                    overflow: TextOverflow.ellipsis,

                    style: const TextStyle(
                      color: Colors.blue,

                      fontWeight: FontWeight.bold,

                      fontSize: 15,
                    ),
                  ),
                ),

                _statusChip(parcel.status),
              ],
            ),

            const SizedBox(height: 15),

            _infoRow(Icons.person_outline, parcel.customerName),

            _infoRow(Icons.location_on_outlined, parcel.deliveryAddress),

            _infoRow(Icons.motorcycle_outlined, parcel.riderName),

            const SizedBox(height: 10),

            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,

              children: [
                Text(
                  "₱${parcel.deliveryEarnings}",

                  style: const TextStyle(
                    color: Colors.orange,

                    fontWeight: FontWeight.bold,

                    fontSize: 16,
                  ),
                ),

                if (buttonText != null)
                  ElevatedButton(
                    onPressed: onButtonPressed,

                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.blue,

                      elevation: 0,

                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),

                    child: Text(
                      buttonText!,

                      style: const TextStyle(color: Colors.white, fontSize: 13),
                    ),
                  ),
              ],
            ),
          ],
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
          Icon(icon, size: 20, color: Colors.blue),

          const SizedBox(width: 10),

          Expanded(
            child: Text(
              text,

              style: const TextStyle(fontSize: 13, color: Colors.grey),
            ),
          ),
        ],
      ),
    );
  }

  Widget _statusChip(String status) {
    Color color = Colors.blue;

    if (status.toLowerCase() == "delivered") {
      color = Colors.green;
    } else if (status.toLowerCase() == "pending") {
      color = Colors.orange;
    } else if (status.toLowerCase() == "cancelled") {
      color = Colors.red;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),

      decoration: BoxDecoration(
        color: color.withValues(alpha: .1),

        borderRadius: BorderRadius.circular(20),
      ),

      child: Text(
        status,

        style: TextStyle(
          color: color,

          fontSize: 12,

          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
