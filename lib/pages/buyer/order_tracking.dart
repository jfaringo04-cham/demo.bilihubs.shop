import 'package:flutter/material.dart';

import '../../sevices/api_services.dart';
import '../../styles/buyer_style.dart';

class OrderTracking extends StatefulWidget {
  final int orderId;
  final String orderNumber;

  const OrderTracking({
    super.key,
    required this.orderId,
    required this.orderNumber,
  });

  @override
  State<OrderTracking> createState() => _OrderTrackingState();
}

class _OrderTrackingState extends State<OrderTracking> {
  bool isLoading = true;
  String? errorMessage;
  List<Map<String, dynamic>> shipments = [];

  @override
  void initState() {
    super.initState();
    _loadTracking();
  }

  Future<void> _loadTracking() async {
    setState(() {
      isLoading = true;
      errorMessage = null;
    });

    final result = await ApiService.getOrderShipments(widget.orderId);
    if (!mounted) return;

    if (result['success'] == true && result['data'] is Map) {
      final data = Map<String, dynamic>.from(result['data'] as Map);
      final raw = data['shipments'];
      final parsed = <Map<String, dynamic>>[];
      if (raw is List) {
        for (final item in raw) {
          if (item is Map) parsed.add(Map<String, dynamic>.from(item));
        }
      }
      setState(() {
        shipments = parsed;
        isLoading = false;
      });
      return;
    }

    setState(() {
      isLoading = false;
      errorMessage = result['message']?.toString() ??
          'Unable to load shipment tracking.';
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        title: Text(widget.orderNumber, style: BuyerStyle.title),
      ),
      body: RefreshIndicator(
        color: BuyerColors.primary,
        onRefresh: _loadTracking,
        child: _body(),
      ),
    );
  }

  Widget _body() {
    if (isLoading) {
      return const Center(
        child: CircularProgressIndicator(color: BuyerColors.primary),
      );
    }

    if (errorMessage != null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        children: [
          const SizedBox(height: 100),
          const Icon(
            Icons.local_shipping_outlined,
            size: 58,
            color: BuyerColors.textGrey,
          ),
          const SizedBox(height: 14),
          Text(
            errorMessage!,
            textAlign: TextAlign.center,
            style: BuyerStyle.body,
          ),
        ],
      );
    }

    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 30),
      itemCount: shipments.length,
      separatorBuilder: (_, __) => const SizedBox(height: 18),
      itemBuilder: (_, index) => _shipmentCard(shipments[index]),
    );
  }

  Widget _shipmentCard(Map<String, dynamic> shipment) {
    final status = shipment['status']?.toString() ?? 'pending';
    final tracking = shipment['tracking_number']?.toString() ?? 'Pending';
    final seller = shipment['seller_name']?.toString() ?? 'BiliHub Seller';

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BuyerStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(seller, style: BuyerStyle.sectionTitle),
          const SizedBox(height: 5),
          Text('Tracking: $tracking', style: BuyerStyle.small),
          const SizedBox(height: 18),
          ..._stepsForStatus(status).asMap().entries.map((entry) {
            final index = entry.key;
            final step = entry.value;
            return _TrackingStepItem(
              step: step,
              isLast: index == _stepsForStatus(status).length - 1,
            );
          }),
        ],
      ),
    );
  }

  List<OrderTrackingStep> _stepsForStatus(String rawStatus) {
    final status = rawStatus.toLowerCase().replaceAll(' ', '_');
    const ordered = [
      'pending',
      'preparing',
      'in_transit',
      'out_for_delivery',
      'delivered',
    ];

    int current = ordered.indexOf(status);
    if (status == 'picked_up' || status == 'at_sorting_center') current = 2;
    if (status == 'confirmed') current = 1;
    if (current < 0) current = 0;

    return [
      OrderTrackingStep(
        title: 'Order Placed',
        subtitle: 'Your order was received by BiliHub.',
        done: current >= 0,
      ),
      OrderTrackingStep(
        title: 'Preparing',
        subtitle: 'The seller is preparing your parcel.',
        done: current >= 1,
      ),
      OrderTrackingStep(
        title: 'In Transit',
        subtitle: 'Your parcel is moving through the delivery network.',
        done: current >= 2,
      ),
      OrderTrackingStep(
        title: 'Out for Delivery',
        subtitle: 'A rider is delivering your parcel.',
        done: current >= 3,
      ),
      OrderTrackingStep(
        title: 'Delivered',
        subtitle: status == 'cancelled'
            ? 'This shipment was cancelled.'
            : 'Package received.',
        done: current >= 4,
      ),
    ];
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
        Column(
          children: [
            Container(
              height: 34,
              width: 34,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: step.done ? BuyerColors.success : BuyerColors.border,
              ),
              child: Icon(
                step.done ? Icons.check_rounded : Icons.circle_outlined,
                color: step.done ? BuyerColors.white : BuyerColors.textGrey,
                size: 19,
              ),
            ),
            if (!isLast)
              Container(height: 48, width: 2, color: BuyerColors.border),
          ],
        ),
        const SizedBox(width: 13),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(top: 2, bottom: 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(step.title, style: BuyerStyle.cardTitle),
                const SizedBox(height: 4),
                Text(step.subtitle, style: BuyerStyle.small),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
