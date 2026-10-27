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
  bool _isLoading = false;

  // ============================================================
  // PARCEL STATUS
  // ============================================================

  String get _normalizedStatus {
    return widget.parcel.status
        .trim()
        .toLowerCase()
        .replaceAll(' ', '_')
        .replaceAll('-', '_');
  }

  bool get _isAlreadyDelivered {
    return _normalizedStatus == 'delivered' || _normalizedStatus == 'completed';
  }

  bool get _canConfirmDelivery {
    if (_isAlreadyDelivered) {
      return false;
    }

    return widget.parcel.id > 0;
  }

  // ============================================================
  // CONFIRM DELIVERY
  // ============================================================

  Future<void> _confirmDelivery() async {
    if (_isLoading) {
      return;
    }

    if (widget.parcel.id <= 0) {
      _showErrorMessage(
        'Invalid shipment ID. Please refresh the delivery list and try again.',
      );
      return;
    }

    if (_isAlreadyDelivered) {
      _showErrorMessage('This shipment is already marked as delivered.');
      return;
    }

    final bool? confirmed = await _showConfirmationDialog();

    if (!mounted || confirmed != true) {
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.updateRiderDeliveryStatus(
            shipmentId: widget.parcel.id,
            status: 'delivered',
          );

      if (!mounted) {
        return;
      }

      setState(() {
        _isLoading = false;
      });

      if (result['success'] == true) {
        final String message = result['message']?.toString().trim() ?? '';

        _showSuccessDialog(
          message.isEmpty
              ? 'The parcel has been successfully marked as delivered.'
              : message,
        );

        return;
      }

      final String errorMessage = result['message']?.toString().trim() ?? '';

      _showErrorMessage(
        errorMessage.isEmpty
            ? 'Unable to confirm delivery. Please try again.'
            : errorMessage,
      );
    } catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _isLoading = false;
      });

      debugPrint('Delivery confirmation error: $error');

      _showErrorMessage(
        'Unable to confirm delivery. Please check your connection and try again.',
      );
    }
  }

  // ============================================================
  // CONFIRMATION DIALOG
  // ============================================================

  Future<bool?> _showConfirmationDialog() {
    return showDialog<bool>(
      context: context,
      barrierDismissible: !_isLoading,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Row(
            children: [
              Icon(
                Icons.check_circle_outline_rounded,
                color: RiderColors.success,
              ),
              SizedBox(width: 10),
              Expanded(
                child: Text('Confirm Delivery', style: RiderStyle.sectionTitle),
              ),
            ],
          ),
          content: const Text(
            'Confirm only after the customer has received the parcel. '
            'This will mark the shipment as delivered.',
            style: RiderStyle.small,
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext, false);
              },
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () {
                Navigator.pop(dialogContext, true);
              },
              style: RiderStyle.primaryButton,
              child: const Text('Confirm', style: RiderStyle.buttonText),
            ),
          ],
        );
      },
    );
  }

  // ============================================================
  // SUCCESS DIALOG
  // ============================================================

  void _showSuccessDialog(String message) {
    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                height: 82,
                width: 82,
                decoration: const BoxDecoration(
                  color: RiderColors.lightGreen,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_circle_rounded,
                  color: RiderColors.success,
                  size: 54,
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'Parcel Delivered Successfully',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: RiderColors.textDark,
                  fontWeight: FontWeight.w800,
                  fontSize: 18,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: RiderStyle.small,
              ),
            ],
          ),
          actionsAlignment: MainAxisAlignment.center,
          actions: [
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.pop(dialogContext);

                  if (!mounted) {
                    return;
                  }

                  Navigator.pop(context, true);
                },
                style: RiderStyle.primaryButton,
                child: const Text('Done', style: RiderStyle.buttonText),
              ),
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
    if (!mounted) {
      return;
    }

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
    final ParcelModel parcel = widget.parcel;

    return Scaffold(
      backgroundColor: RiderColors.background,

      appBar: AppBar(
        backgroundColor: RiderColors.white,

        elevation: 0,

        surfaceTintColor: Colors.transparent,

        iconTheme: const IconThemeData(color: RiderColors.textDark),

        title: const Text('Confirm Delivery', style: RiderStyle.title),
      ),

      body: SafeArea(
        top: false,

        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),

          padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              // ==================================================
              // STATUS HEADER
              // ==================================================

              _statusHeader(),

              const SizedBox(height: 16),

              // ==================================================
              // PARCEL INFORMATION
              // ==================================================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(18),

                decoration: RiderStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text(
                      'Parcel Information',

                      style: RiderStyle.sectionTitle,
                    ),

                    const SizedBox(height: 18),

                    _infoRow(
                      icon: Icons.qr_code_2_rounded,

                      title: 'Tracking Number',

                      value: _displayValue(
                        parcel.trackingNumber,

                        fallback: 'Tracking unavailable',
                      ),
                    ),

                    _infoRow(
                      icon: Icons.person_outline_rounded,

                      title: 'Customer',

                      value: _displayValue(parcel.customerName),
                    ),

                    _infoRow(
                      icon: Icons.phone_outlined,

                      title: 'Phone Number',

                      value: _displayValue(parcel.customerPhone),
                    ),

                    _infoRow(
                      icon: Icons.location_on_outlined,

                      title: 'Delivery Address',

                      value: _displayValue(parcel.deliveryAddress),
                    ),

                    _infoRow(
                      icon: Icons.local_shipping_outlined,

                      title: 'Status',

                      value: _formatStatus(parcel.status),

                      isLast: true,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 18),

              // ==================================================
              // DELIVERY WARNING
              // ==================================================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(16),

                decoration: BoxDecoration(
                  color: _isAlreadyDelivered
                      ? RiderColors.lightGreen
                      : RiderColors.lightBlue,

                  borderRadius: BorderRadius.circular(16),

                  border: Border.all(
                    color: _isAlreadyDelivered
                        ? RiderColors.success.withValues(alpha: 0.20)
                        : RiderColors.primary.withValues(alpha: 0.20),
                  ),
                ),

                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Icon(
                      _isAlreadyDelivered
                          ? Icons.check_circle_outline_rounded
                          : Icons.info_outline_rounded,

                      color: _isAlreadyDelivered
                          ? RiderColors.success
                          : RiderColors.primary,
                    ),

                    const SizedBox(width: 10),

                    Expanded(
                      child: Text(
                        _isAlreadyDelivered
                            ? 'This parcel is already marked as delivered.'
                            : 'Make sure the customer has received the parcel before confirming delivery.',

                        style: RiderStyle.small,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // ==================================================
              // CONFIRM BUTTON
              // ==================================================
              if (_isAlreadyDelivered)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: RiderStyle.card,
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.verified_rounded, color: RiderColors.success),
                      SizedBox(width: 8),
                      Text(
                        'DELIVERY COMPLETED',
                        style: TextStyle(
                          color: RiderColors.success,
                          fontWeight: FontWeight.w800,
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                )
              else
                CustomButton(
                  text: 'CONFIRM DELIVERY',

                  icon: Icons.check_circle_outline_rounded,

                  isLoading: _isLoading,

                  backgroundColor: RiderColors.success,

                  onPressed: _canConfirmDelivery ? _confirmDelivery : null,
                ),

              const SizedBox(height: 14),

              if (!_isAlreadyDelivered)
                const Text(
                  'Once confirmed, the delivery status will be updated on the server.',

                  textAlign: TextAlign.center,

                  style: RiderStyle.small,
                ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // STATUS HEADER
  // ============================================================

  Widget _statusHeader() {
    final bool delivered = _isAlreadyDelivered;

    return Container(
      width: double.infinity,

      padding: const EdgeInsets.all(16),

      decoration: BoxDecoration(
        color: RiderColors.white,

        borderRadius: BorderRadius.circular(16),

        border: Border.all(color: RiderColors.border),
      ),

      child: Row(
        children: [
          Container(
            height: 48,
            width: 48,

            decoration: BoxDecoration(
              color: delivered
                  ? RiderColors.lightGreen
                  : RiderColors.lightPurple,

              borderRadius: BorderRadius.circular(14),
            ),

            child: Icon(
              delivered
                  ? Icons.check_circle_rounded
                  : Icons.local_shipping_rounded,

              color: delivered ? RiderColors.success : RiderColors.primary,

              size: 25,
            ),
          ),

          const SizedBox(width: 12),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(
                  delivered ? 'Delivery Completed' : 'Ready to Confirm',

                  style: RiderStyle.cardTitle,
                ),

                const SizedBox(height: 3),

                Text(
                  delivered
                      ? 'The parcel has already been delivered.'
                      : 'Review the shipment before completing the delivery.',

                  style: RiderStyle.small,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // INFO ROW
  // ============================================================

  Widget _infoRow({
    required IconData icon,
    required String title,
    required String value,
    bool isLast = false,
  }) {
    return Padding(
      padding: EdgeInsets.only(bottom: isLast ? 0 : 14),

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Container(
            height: 36,
            width: 36,

            decoration: BoxDecoration(
              color: RiderColors.lightPurple,

              borderRadius: BorderRadius.circular(10),
            ),

            alignment: Alignment.center,

            child: Icon(icon, color: RiderColors.primary, size: 18),
          ),

          const SizedBox(width: 11),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(title, style: RiderStyle.small),

                const SizedBox(height: 3),

                Text(value, style: RiderStyle.cardTitle),
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
    final String cleaned = value.trim();

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return fallback;
    }

    return cleaned;
  }

  // ============================================================
  // FORMAT STATUS
  // ============================================================

  String _formatStatus(String status) {
    final String cleaned = status
        .trim()
        .replaceAll('_', ' ')
        .replaceAll('-', ' ');

    if (cleaned.isEmpty) {
      return 'Not available';
    }

    return cleaned
        .split(RegExp(r'\s+'))
        .map((String word) {
          if (word.isEmpty) {
            return word;
          }

          return '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}';
        })
        .join(' ');
  }
}
