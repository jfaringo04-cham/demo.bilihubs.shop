import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../sevices/api_services.dart';
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

  // ============================================================
  // CONFIRM DELIVERY
  // ============================================================

  Future<void> confirmDelivery() async {
    if (isLoading) return;

    setState(() {
      isLoading = true;
    });

    final result = await ApiService.updateRiderDeliveryStatus(
      orderId: widget.parcel.id,
      status: 'delivered',
    );

    if (!mounted) return;

    setState(() {
      isLoading = false;
    });

    if (result['success'] == true) {
      _showSuccessDialog();

      return;
    }

    _showErrorMessage(
      result['message']?.toString() ?? 'Unable to confirm delivery.',
    );
  }

  // ============================================================
  // SUCCESS DIALOG
  // ============================================================

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),

          content: const Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.check_circle, color: Colors.green, size: 70),

              SizedBox(height: 15),

              Text(
                'Parcel Delivered Successfully',
                textAlign: TextAlign.center,
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
              ),

              SizedBox(height: 8),

              Text(
                'The parcel status has been updated.',
                textAlign: TextAlign.center,
                style: RiderStyle.small,
              ),
            ],
          ),

          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);

                Navigator.pop(context, true);
              },

              child: const Text('Done'),
            ),
          ],
        );
      },
    );
  }

  // ============================================================
  // ERROR
  // ============================================================

  void _showErrorMessage(String message) {
    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: RiderColors.danger,
        content: Text(message),
      ),
    );
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final parcel = widget.parcel;

    return Scaffold(
      backgroundColor: RiderColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        iconTheme: const IconThemeData(color: Colors.black),
        title: const Text('Confirm Delivery', style: RiderStyle.title),
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
                      'Parcel Information',
                      style: RiderStyle.sectionTitle,
                    ),

                    const SizedBox(height: 20),

                    _infoRow(
                      'Tracking Number',
                      _displayValue(parcel.trackingNumber),
                    ),

                    _infoRow('Customer', _displayValue(parcel.customerName)),

                    _infoRow(
                      'Phone Number',
                      _displayValue(parcel.customerPhone),
                    ),

                    _infoRow('Address', _displayValue(parcel.deliveryAddress)),

                    _infoRow('Status', _formatStatus(parcel.status)),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              Container(
                padding: const EdgeInsets.all(18),

                decoration: RiderStyle.card,

                child: const Row(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Icon(Icons.info_outline, color: Colors.blue),

                    SizedBox(width: 10),

                    Expanded(
                      child: Text(
                        'Make sure the parcel has been received by the customer before confirming.',
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 30),

              CustomButton(
                text: 'CONFIRM DELIVERY',

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

  // ============================================================
  // INFO ROW
  // ============================================================

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

  // ============================================================
  // HELPERS
  // ============================================================

  String _displayValue(String value) {
    if (value.trim().isEmpty) {
      return 'Not available';
    }

    return value.trim();
  }

  String _formatStatus(String status) {
    if (status.trim().isEmpty) {
      return 'Not available';
    }

    final clean = status.replaceAll('_', ' ').trim();

    return clean
        .split(RegExp(r'\s+'))
        .map(
          (word) => word.isEmpty
              ? word
              : '${word[0].toUpperCase()}${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }
}
