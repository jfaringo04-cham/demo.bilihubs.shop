import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';
import '../../utils/date_formatter.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/status_chip.dart';

class OrderTracking extends StatelessWidget {
  final Map<String, dynamic> order;

  const OrderTracking({super.key, required this.order});

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final List<Map<String, dynamic>> sellerOrders = _mapList(
      order['seller_orders'],
    );

    final Map<String, dynamic>? shipment = _extractShipment(sellerOrders);

    final String orderNumber = _cleanText(order['order_number']);

    final String trackingNumber = _cleanText(shipment?['tracking_number']);

    final String currentStatus = _normalizeStatus(
      _firstNonEmpty(<dynamic>[
        shipment?['status'],
        order['delivery_status'],
        order['status'],
        'pending',
      ]),
    );

    final String createdAt = DateFormatter.dateTime(
      order['created_at'],
      fallback: '',
    );

    final String confirmedAt = DateFormatter.dateTime(
      order['confirmed_at'],
      fallback: '',
    );

    final String pickedUpAt = DateFormatter.dateTime(
      shipment?['picked_up_at'],
      fallback: '',
    );

    final String deliveredAt = DateFormatter.dateTime(
      shipment?['delivered_at'] ?? order['delivered_at'],
      fallback: '',
    );

    final bool isCancelled =
        currentStatus == 'cancelled' || currentStatus == 'canceled';

    final bool isFailed =
        currentStatus == 'failed' ||
        currentStatus == 'delivery_failed' ||
        currentStatus == 'failed_return_at_sorting_center';

    final bool isTerminal = isCancelled || isFailed;

    final List<OrderTrackingStep> steps = _buildTrackingSteps(
      currentStatus: currentStatus,
      createdAt: createdAt,
      confirmedAt: confirmedAt,
      pickedUpAt: pickedUpAt,
      deliveredAt: deliveredAt,
    );

    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        title: const Text('Order Tracking', style: BuyerStyle.appBarTitle),
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
      ),
      body: SafeArea(
        top: false,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
          children: [
            // ==================================================
            // TRACKING HEADER
            // ==================================================

            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BuyerStyle.elevatedCard,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Container(
                        width: 50,
                        height: 50,
                        decoration: BuyerStyle.iconContainer(),
                        child: const Icon(
                          Icons.local_shipping_outlined,
                          color: BuyerColors.primary,
                          size: 26,
                        ),
                      ),

                      const SizedBox(width: 12),

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
                              maxLines: 3,
                              overflow: TextOverflow.ellipsis,
                              style: BuyerStyle.cardTitle,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 14),

                  StatusChip(status: currentStatus),

                  if (orderNumber.isNotEmpty || trackingNumber.isNotEmpty) ...[
                    const SizedBox(height: 14),

                    const Divider(height: 1, color: BuyerColors.border),
                  ],

                  if (orderNumber.isNotEmpty) ...[
                    const SizedBox(height: 6),

                    AppInfoRow(
                      label: 'Order Number',
                      value: orderNumber,
                      icon: Icons.receipt_long_outlined,
                      iconColor: BuyerColors.primary,
                      selectable: true,
                      showIconBackground: true,
                    ),
                  ],

                  if (orderNumber.isNotEmpty && trackingNumber.isNotEmpty)
                    const Divider(height: 1, color: BuyerColors.border),

                  if (trackingNumber.isNotEmpty) ...[
                    const SizedBox(height: 6),

                    AppInfoRow(
                      label: 'Tracking Number',
                      value: trackingNumber,
                      icon: Icons.qr_code_2_rounded,
                      iconColor: BuyerColors.primary,
                      selectable: true,
                      showIconBackground: true,
                    ),
                  ],
                ],
              ),
            ),

            const SizedBox(height: 22),

            // ==================================================
            // CANCELLED / FAILED WARNING
            // ==================================================
            if (isTerminal) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(15),
                decoration: BoxDecoration(
                  color: BuyerColors.cancelledBackground,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(
                    color: BuyerColors.cancelledText.withValues(alpha: 0.15),
                  ),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(
                      Icons.warning_amber_rounded,
                      color: BuyerColors.cancelledText,
                      size: 23,
                    ),

                    const SizedBox(width: 10),

                    Expanded(
                      child: Text(
                        isCancelled
                            ? 'This order has been cancelled.'
                            : 'The delivery attempt for this '
                                  'order was unsuccessful.',
                        style: const TextStyle(
                          color: BuyerColors.cancelledText,
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                          height: 1.45,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 22),
            ],

            // ==================================================
            // DELIVERY PROGRESS
            // ==================================================
            const Text('Delivery Progress', style: BuyerStyle.sectionTitle),

            const SizedBox(height: 6),

            Text(
              isTerminal
                  ? 'The current shipment status is shown above.'
                  : 'Follow the progress of your order '
                        'from placement to delivery.',
              style: BuyerStyle.small,
            ),

            const SizedBox(height: 18),

            for (int index = 0; index < steps.length; index++)
              _TrackingStepItem(
                step: steps[index],
                isLast: index == steps.length - 1,
              ),

            // ==================================================
            // INFORMATION NOTE
            // ==================================================
            const SizedBox(height: 10),

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
                      'Tracking progress is based on the '
                      'latest shipment status recorded by '
                      'BiliHub. Updates may take time to '
                      'appear after a shipment changes status.',
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

  // ============================================================
  // TRACKING STEPS
  // ============================================================

  List<OrderTrackingStep> _buildTrackingSteps({
    required String currentStatus,
    required String createdAt,
    required String confirmedAt,
    required String pickedUpAt,
    required String deliveredAt,
  }) {
    final int currentIndex = _statusIndex(currentStatus);

    final bool terminal =
        currentStatus == 'cancelled' ||
        currentStatus == 'canceled' ||
        currentStatus == 'failed' ||
        currentStatus == 'delivery_failed' ||
        currentStatus == 'failed_return_at_sorting_center';

    final List<String> titles = <String>[
      'Order Placed',
      'Confirmed',
      'Preparing',
      'In Transit',
      'Out for Delivery',
      'Delivered',
    ];

    final List<String> subtitles = <String>[
      createdAt.isNotEmpty
          ? 'Order placed on $createdAt'
          : 'Your order has been received',

      confirmedAt.isNotEmpty
          ? 'Order confirmed on $confirmedAt'
          : 'Seller confirmation',

      'Seller is preparing your item for shipment',

      pickedUpAt.isNotEmpty
          ? 'Parcel picked up on $pickedUpAt'
          : 'Parcel is moving through the delivery network',

      'Rider is delivering your package',

      deliveredAt.isNotEmpty
          ? 'Package delivered on $deliveredAt'
          : 'Package received by the buyer',
    ];

    final List<OrderTrackingStep> steps = <OrderTrackingStep>[];

    for (int index = 0; index < titles.length; index++) {
      bool done = false;
      bool active = false;

      if (terminal) {
        // A cancelled/failed shipment does not imply
        // that all normal delivery steps were completed.
        if (index == 0 && createdAt.isNotEmpty) {
          done = true;
        }

        if (index == 1 && confirmedAt.isNotEmpty) {
          done = true;
        }

        if (index == 3 && pickedUpAt.isNotEmpty) {
          done = true;
        }

        if (index == 5 && deliveredAt.isNotEmpty) {
          done = true;
        }
      } else if (currentIndex >= 0) {
        done = index < currentIndex || (currentIndex == 5 && index == 5);

        active = index == currentIndex;
      } else {
        // Unknown statuses should not display
        // unverified progress as completed.
        done = index == 0 && createdAt.isNotEmpty;
      }

      steps.add(
        OrderTrackingStep(
          title: titles[index],
          subtitle: subtitles[index],
          done: done,
          active: active,
        ),
      );
    }

    return steps;
  }

  // ============================================================
  // STATUS INDEX
  // ============================================================

  int _statusIndex(String status) {
    switch (_normalizeStatus(status)) {
      // ========================================================
      // ORDER PLACED
      // ========================================================

      case 'pending':
        return 0;

      // ========================================================
      // CONFIRMED
      // ========================================================

      case 'confirmed':
        return 1;

      // ========================================================
      // PREPARING
      // ========================================================

      case 'processing':
      case 'preparing':
      case 'assigned':
      case 'ready_for_pickup':
        return 2;

      // ========================================================
      // PICKUP / TRANSIT / HUB
      // ========================================================

      case 'shipped':
      case 'picked_up':
      case 'in_transit':
      case 'linehaul_assigned':
      case 'linehaul_in_transit':
      case 'in_hub_transfer':
      case 'at_origin_hub':
      case 'at_destination_hub':
      case 'at_sorting_center':
      case 'sorting':
      case 'sorted':
      case 'staged':
      case 'received':
      case 'scanned':
        return 3;

      // ========================================================
      // OUT FOR DELIVERY
      // ========================================================

      case 'out_for_delivery':
        return 4;

      // ========================================================
      // DELIVERED
      // ========================================================

      case 'delivered':
      case 'completed':
        return 5;

      // ========================================================
      // CANCELLED / FAILED
      // ========================================================

      case 'cancelled':
      case 'canceled':
      case 'failed':
      case 'delivery_failed':
      case 'failed_return_at_sorting_center':
        return -1;

      // ========================================================
      // UNKNOWN
      // ========================================================

      default:
        return -1;
    }
  }

  // ============================================================
  // SHIPMENT EXTRACTION
  // ============================================================

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

  // ============================================================
  // MAP LIST
  // ============================================================

  List<Map<String, dynamic>> _mapList(dynamic value) {
    if (value is! List) {
      return <Map<String, dynamic>>[];
    }

    return value
        .whereType<Map>()
        .map((Map item) => Map<String, dynamic>.from(item))
        .toList();
  }

  // ============================================================
  // STATUS HELPERS
  // ============================================================

  String _normalizeStatus(String status) {
    return status
        .trim()
        .toLowerCase()
        .replaceAll('-', '_')
        .replaceAll(RegExp(r'\s+'), '_');
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

  String _cleanText(dynamic value) {
    final String cleaned = value?.toString().trim() ?? '';

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return '';
    }

    return cleaned;
  }

  String _firstNonEmpty(List<dynamic> values) {
    for (final dynamic value in values) {
      final String cleaned = _cleanText(value);

      if (cleaned.isNotEmpty) {
        return cleaned;
      }
    }

    return '';
  }
}

// ============================================================
// TRACKING STEP MODEL
// ============================================================

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

// ============================================================
// TRACKING STEP ITEM
// ============================================================

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

                const SizedBox(height: 6),

                Text(step.subtitle, style: BuyerStyle.small),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

// ============================================================
// TIMELINE INDICATOR
// ============================================================

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

        if (!isLast) Container(height: 75, width: 2, color: lineColor),
      ],
    );
  }
}
