import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';

class OrderTracking extends StatelessWidget {
  const OrderTracking({super.key});

  static const List<OrderTrackingStep> steps = [
    OrderTrackingStep(
      title: 'Order Placed',
      subtitle: 'Your order has been confirmed',
      done: true,
    ),
    OrderTrackingStep(
      title: 'Preparing',
      subtitle: 'Seller is preparing your item',
      done: true,
    ),
    OrderTrackingStep(
      title: 'Shipped',
      subtitle: 'Package is on the way',
      done: true,
    ),
    OrderTrackingStep(
      title: 'Out for Delivery',
      subtitle: 'Rider is delivering your package',
      done: false,
    ),
    OrderTrackingStep(
      title: 'Delivered',
      subtitle: 'Package received',
      done: false,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        centerTitle: false,
        title: const Text('Order Tracking', style: BuyerStyle.title),
      ),
      body: ListView.builder(
        padding: const EdgeInsets.fromLTRB(18, 20, 18, 30),
        itemCount: steps.length,
        itemBuilder: (context, index) {
          return _TrackingStepItem(
            step: steps[index],
            isLast: index == steps.length - 1,
          );
        },
      ),
    );
  }
}

class OrderTrackingStep {
  final String title;
  final String subtitle;
  final bool done;

  const OrderTrackingStep({
    required this.title,
    required this.subtitle,
    required this.done,
  });
}

class _TrackingStepItem extends StatelessWidget {
  final OrderTrackingStep step;
  final bool isLast;

  const _TrackingStepItem({required this.step, required this.isLast});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _TimelineIndicator(done: step.done, isLast: isLast),
        const SizedBox(width: 14),
        Expanded(
          child: Container(
            margin: const EdgeInsets.only(bottom: 18),
            padding: const EdgeInsets.all(16),
            decoration: BuyerStyle.card,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(step.title, style: BuyerStyle.cardTitle),
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

class _TimelineIndicator extends StatelessWidget {
  final bool done;
  final bool isLast;

  const _TimelineIndicator({required this.done, required this.isLast});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          height: 36,
          width: 36,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: done ? BuyerColors.success : BuyerColors.border,
          ),
          child: Icon(
            done ? Icons.check_rounded : Icons.circle_outlined,
            color: done ? BuyerColors.white : BuyerColors.textGrey,
            size: 20,
          ),
        ),
        if (!isLast) Container(height: 60, width: 2, color: BuyerColors.border),
      ],
    );
  }
}
