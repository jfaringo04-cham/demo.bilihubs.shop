import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';

class OrderTracking extends StatelessWidget {
  final Map<String, dynamic> order;

  const OrderTracking({super.key, required this.order});

  @override
  Widget build(BuildContext context) {
    final List<Map<String, dynamic>> sellerOrders = _mapList(
      order['seller_orders'],
    );

    final Map<String, dynamic>? shipment = _extractShipment(sellerOrders);

    final String orderNumber = order['order_number']?.toString().trim() ?? '';

    final String trackingNumber =
        shipment?['tracking_number']?.toString().trim() ?? '';

    final String currentStatus = _normalizeStatus(
      shipment?['status']?.toString() ??
          order['delivery_status']?.toString() ??
          order['status']?.toString() ??
          'pending',
    );

    final String pickedUpAt = _formatDate(
      shipment?['picked_up_at']?.toString() ?? '',
    );

    final String deliveredAt = _formatDate(
      shipment?['delivered_at']?.toString() ??
          order['delivered_at']?.toString() ??
          '',
    );

    final List<OrderTrackingStep> steps = _buildTrackingSteps(
      currentStatus: currentStatus,
      createdAt: _formatDate(order['created_at']?.toString() ?? ''),
      confirmedAt: _formatDate(order['confirmed_at']?.toString() ?? ''),
      pickedUpAt: pickedUpAt,
      deliveredAt: deliveredAt,
    );

    final bool isCancelled =
        currentStatus == 'cancelled' || currentStatus == 'canceled';

    final bool isFailed = currentStatus == 'failed';

    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        leading: IconButton(
          tooltip: 'Back',
          onPressed: () {
            Navigator.pop(context);
          },
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: BuyerColors.textDark,
            size: 20,
          ),
        ),
        title: const Text('Order Tracking', style: BuyerStyle.appBarTitle),
      ),
      body: SafeArea(
        top: false,
        child: ListView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 20, 18, 30),
          children: [
            // ============================================================
            // TRACKING HEADER
            // ============================================================

            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(18),
              decoration: BuyerStyle.elevatedCard,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        height: 50,
                        width: 50,
                        decoration: BuyerStyle.iconContainer(),
                        child: const Icon(
                          Icons.local_shipping_outlined,
                          color: BuyerColors.primary,
                          size: 26,
                        ),
                      ),

                      const SizedBox(width: 13),

                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Current Status',
                              style: BuyerStyle.small,
                            ),

                            const SizedBox(height: 4),

                            Text(
                              _displayStatus(currentStatus),
                              style: BuyerStyle.cardTitle,
                            ),
                          ],
                        ),
                      ),

                      _statusIcon(currentStatus),
                    ],
                  ),

                  if (orderNumber.isNotEmpty) ...[
                    const SizedBox(height: 16),

                    const Divider(height: 1, color: BuyerColors.border),

                    const SizedBox(height: 14),

                    _infoRow(
                      icon: Icons.receipt_long_outlined,
                      label: 'Order Number',
                      value: orderNumber,
                    ),
                  ],

                  if (trackingNumber.isNotEmpty) ...[
                    const SizedBox(height: 12),

                    _infoRow(
                      icon: Icons.qr_code_2_rounded,
                      label: 'Tracking Number',
                      value: trackingNumber,
                    ),
                  ],
                ],
              ),
            ),

            const SizedBox(height: 22),

            // ============================================================
            // CANCELLED / FAILED WARNING
            // ============================================================
            if (isCancelled || isFailed) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(15),
                decoration: BoxDecoration(
                  color: BuyerColors.cancelledBackground,
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(
                      Icons.warning_amber_rounded,
                      color: BuyerColors.cancelledText,
                    ),

                    const SizedBox(width: 10),

                    Expanded(
                      child: Text(
                        isCancelled ? 'This order has been cancelled.' : 'The delivery attempt for this order was unsuccessful.',
                        style: const TextStyle(
                          color: BuyerColors.cancelledText,
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                          height: 1.4,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 22),
            ],

            // ============================================================
            // DELIVERY PROGRESS
            // ============================================================
            const Text('Delivery Progress', style: BuyerStyle.sectionTitle),

            const SizedBox(height: 14),

            for (int index = 0; index < steps.length; index++)
              _TrackingStepItem(
                step: steps[index],
                isLast: index == steps.length - 1,
              ),

            const SizedBox(height: 8),

            // ============================================================
            // INFO NOTE
            // ============================================================
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: BuyerColors.lightPurple,
                borderRadius: BorderRadius.circular(16),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.info_outline_rounded,
                    color: BuyerColors.primary,
                    size: 20,
                  ),

                  SizedBox(width: 10),

                  Expanded(
                    child: Text(
                      'Tracking progress is based on the latest '
                      'shipment status recorded by BiliHub.',
                      style: TextStyle(
                        fontSize: 12,
                        height: 1.45,
                        color: BuyerColors.textGrey,
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

  // ================================================================
  // TRACKING STEPS
  // ================================================================

  List<OrderTrackingStep> _buildTrackingSteps({
    required String currentStatus,
    required String createdAt,
    required String confirmedAt,
    required String pickedUpAt,
    required String deliveredAt,
  }) {
    final int currentIndex = _statusIndex(currentStatus);

    return [
      OrderTrackingStep(
        title: 'Order Placed',
        subtitle: createdAt.isNotEmpty
            ? 'Order placed on $createdAt'
            : 'Your order has been received',
        done: currentIndex >= 0,
        active: currentIndex == 0,
      ),
      OrderTrackingStep(
        title: 'Confirmed',
        subtitle: confirmedAt.isNotEmpty
            ? 'Order confirmed on $confirmedAt'
            : 'Seller confirmed your order',
        done: currentIndex >= 1,
        active: currentIndex == 1,
      ),
      OrderTrackingStep(
        title: 'Preparing',
        subtitle: 'Seller is preparing your item for shipment',
        done: currentIndex >= 2,
        active: currentIndex == 2,
      ),
      OrderTrackingStep(
        title: 'In Transit',
        subtitle: pickedUpAt.isNotEmpty
            ? 'Parcel picked up on $pickedUpAt'
            : 'Parcel has been picked up and is moving through delivery',
        done: currentIndex >= 3,
        active: currentIndex == 3,
      ),
      OrderTrackingStep(
        title: 'Out for Delivery',
        subtitle: 'Rider is delivering your package',
        done: currentIndex >= 4,
        active: currentIndex == 4,
      ),
      OrderTrackingStep(
        title: 'Delivered',
        subtitle: deliveredAt.isNotEmpty
            ? 'Package delivered on $deliveredAt'
            : 'Package received by the buyer',
        done: currentIndex >= 5,
        active: currentIndex == 5,
      ),
    ];
  }

  int _statusIndex(String status) {
    switch (_normalizeStatus(status)) {
      case 'pending':
        return 0;

      case 'confirmed':
        return 1;

      case 'processing':
      case 'preparing':
        return 2;

      case 'shipped':
      case 'in_transit':
      case 'in transit':
      case 'picked_up':
      case 'picked up':
        return 3;

      case 'out_for_delivery':
      case 'out for delivery':
        return 4;

      case 'delivered':
        return 5;

      case 'cancelled':
      case 'canceled':
      case 'failed':
        return -1;

      default:
        return 0;
    }
  }

  // ================================================================
  // SHIPMENT
  // ================================================================

  Map<String, dynamic>? _extractShipment(
    List<Map<String, dynamic>> sellerOrders,
  ) {
    for (final Map<String, dynamic> sellerOrder in sellerOrders) {
      final dynamic rawShipment = sellerOrder['shipment'];

      if (rawShipment is Map) {
        return Map<String, dynamic>.from(rawShipment);
      }
    }

    return null;
  }

  // ================================================================
  // HEADER INFO
  // ================================================================

  Widget _infoRow({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: BuyerColors.primary),

        const SizedBox(width: 9),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: BuyerStyle.small),

              const SizedBox(height: 3),

              Text(value, style: BuyerStyle.cardTitle),
            ],
          ),
        ),
      ],
    );
  }

  Widget _statusIcon(String status) {
    IconData icon;
    Color color;

    switch (_normalizeStatus(status)) {
      case 'delivered':
        icon = Icons.check_circle_outline_rounded;
        color = BuyerColors.deliveredText;
        break;

      case 'cancelled':
      case 'canceled':
      case 'failed':
        icon = Icons.cancel_outlined;
        color = BuyerColors.cancelledText;
        break;

      case 'out_for_delivery':
      case 'out for delivery':
      case 'in_transit':
      case 'in transit':
      case 'shipped':
        icon = Icons.local_shipping_outlined;
        color = BuyerColors.shippedText;
        break;

      default:
        icon = Icons.schedule_rounded;
        color = BuyerColors.processingText;
    }

    return Icon(icon, color: color, size: 26);
  }

  // ================================================================
  // HELPERS
  // ================================================================

  List<Map<String, dynamic>> _mapList(dynamic value) {
    if (value is! List) {
      return <Map<String, dynamic>>[];
    }

    return value
        .whereType<Map>()
        .map((Map item) => Map<String, dynamic>.from(item))
        .toList();
  }

  String _normalizeStatus(String status) {
    return status.trim().toLowerCase();
  }

  String _displayStatus(String status) {
    final String normalized = status
        .trim()
        .replaceAll('_', ' ')
        .replaceAll('-', ' ');

    if (normalized.isEmpty) {
      return 'Unknown';
    }

    return normalized
        .split(' ')
        .where((String word) => word.isNotEmpty)
        .map(
          (String word) =>
              '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  String _formatDate(String rawDate) {
    if (rawDate.trim().isEmpty) {
      return '';
    }

    try {
      final DateTime parsed = DateTime.parse(rawDate).toLocal();

      const List<String> months = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
      ];

      final int hour12 = parsed.hour == 0
          ? 12
          : parsed.hour > 12
          ? parsed.hour - 12
          : parsed.hour;

      final String minute = parsed.minute.toString().padLeft(2, '0');

      final String period = parsed.hour >= 12 ? 'PM' : 'AM';

      return '${months[parsed.month - 1]} '
          '${parsed.day}, ${parsed.year} • '
          '$hour12:$minute $period';
    } catch (_) {
      return rawDate;
    }
  }
}

// ===================================================================
// TRACKING STEP MODEL
// ===================================================================

class OrderTrackingStep {
  final String title;
  final String subtitle;
  final bool done;
  final bool active;

  const OrderTrackingStep({
    required this.title,
    required this.subtitle,
    required this.done,
    required this.active,
  });
}

// ===================================================================
// TRACKING STEP ITEM
// ===================================================================

class _TrackingStepItem extends StatelessWidget {
  final OrderTrackingStep step;
  final bool isLast;

  const _TrackingStepItem({required this.step, required this.isLast});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _TimelineIndicator(
          done: step.done,
          active: step.active,
          isLast: isLast,
        ),

        const SizedBox(width: 14),

        Expanded(
          child: Container(
            margin: const EdgeInsets.only(bottom: 18),
            padding: const EdgeInsets.all(16),
            decoration: step.active
                ? BoxDecoration(
                    color: BuyerColors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: BuyerColors.primary, width: 1.3),
                  )
                : BuyerStyle.card,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(step.title, style: BuyerStyle.cardTitle),
                    ),

                    if (step.active)
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: BuyerColors.lightPurple,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Text(
                          'Current',
                          style: TextStyle(
                            color: BuyerColors.primary,
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                  ],
                ),

                const SizedBox(height: 5),

                Text(step.subtitle, style: BuyerStyle.small),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

// ===================================================================
// TIMELINE INDICATOR
// ===================================================================

class _TimelineIndicator extends StatelessWidget {
  final bool done;
  final bool active;
  final bool isLast;

  const _TimelineIndicator({
    required this.done,
    required this.active,
    required this.isLast,
  });

  @override
  Widget build(BuildContext context) {
    final Color circleColor = done
        ? BuyerColors.success
        : active
        ? BuyerColors.primary
        : BuyerColors.border;

    final Color lineColor = done ? BuyerColors.success : BuyerColors.border;

    return Column(
      children: [
        Container(
          height: 36,
          width: 36,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: circleColor,
            border: active && !done
                ? Border.all(color: BuyerColors.primary, width: 2)
                : null,
          ),
          child: Icon(
            done
                ? Icons.check_rounded
                : active
                ? Icons.radio_button_checked_rounded
                : Icons.circle_outlined,
            color: done || active ? BuyerColors.white : BuyerColors.textGrey,
            size: 20,
          ),
        ),

        if (!isLast) Container(height: 65, width: 2, color: lineColor),
      ],
    );
  }
}
