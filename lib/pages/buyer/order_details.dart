import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';
import 'order_tracking.dart';

class OrderDetails extends StatelessWidget {
  final Map<String, dynamic> order;

  const OrderDetails({super.key, required this.order});

  @override
  Widget build(BuildContext context) {
    final items = _mapList(order['items']);
    final firstItem = items.isNotEmpty ? items.first : <String, dynamic>{};
    final sellerOrders = _mapList(order['seller_orders']);
    final firstSellerOrder =
        sellerOrders.isNotEmpty ? sellerOrders.first : <String, dynamic>{};
    final seller = _map(firstSellerOrder['seller']);
    final shipment = _map(firstSellerOrder['shipment']);
    final rider = _map(shipment['rider']);
    final address = _map(order['address']);

    final status = order['delivery_status']?.toString().trim().isNotEmpty == true
        ? order['delivery_status'].toString()
        : order['status']?.toString() ?? 'Unknown';
    final orderNumber =
        order['order_number']?.toString() ?? 'Order #${order['id'] ?? ''}';
    final productName =
        firstItem['product_name']?.toString() ?? 'Order item';
    final imageUrl = firstItem['product_image']?.toString() ?? '';
    final quantity =
        int.tryParse(firstItem['quantity']?.toString() ?? '0') ?? 0;
    final itemPrice =
        double.tryParse(firstItem['price']?.toString() ?? '0') ?? 0;
    final subtotal = double.tryParse(order['subtotal']?.toString() ?? '0') ?? 0;
    final shipping =
        double.tryParse(order['shipping_fee']?.toString() ?? '0') ?? 0;
    final serviceFee =
        double.tryParse(order['service_fee']?.toString() ?? '0') ?? 0;
    final total = double.tryParse(order['total']?.toString() ?? '0') ?? 0;
    final sellerName = seller['name']?.toString() ?? 'BiliHub Seller';
    final riderName = rider['name']?.toString() ?? 'Rider not assigned';
    final trackingNumber = shipment['tracking_number']?.toString() ?? '';
    final fullAddress = address['full_address']?.toString() ?? '';
    final orderId = int.tryParse(order['id']?.toString() ?? '');

    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        title: const Text('Order Details', style: BuyerStyle.appBarTitle),
      ),
      body: SafeArea(
        top: false,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(18),
                decoration: BuyerStyle.elevatedCard,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(orderNumber, style: BuyerStyle.title),
                    const SizedBox(height: 8),
                    Text(
                      _titleCase(status.replaceAll('_', ' ')),
                      style: const TextStyle(
                        color: BuyerColors.primary,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    if (trackingNumber.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text('Tracking: $trackingNumber', style: BuyerStyle.small),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 24),
              const Text('Product Information', style: BuyerStyle.sectionTitle),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BuyerStyle.card,
                child: Row(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(14),
                      child: Container(
                        height: 68,
                        width: 68,
                        color: BuyerColors.lightPurple,
                        child: imageUrl.isEmpty
                            ? const Icon(
                                Icons.shopping_bag_outlined,
                                color: BuyerColors.primary,
                                size: 31,
                              )
                            : Image.network(
                                imageUrl,
                                fit: BoxFit.cover,
                                errorBuilder: (_, __, ___) => const Icon(
                                  Icons.broken_image_outlined,
                                  color: BuyerColors.primary,
                                  size: 31,
                                ),
                              ),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(productName, style: BuyerStyle.productTitle),
                          const SizedBox(height: 5),
                          Text(sellerName, style: BuyerStyle.small),
                          const SizedBox(height: 5),
                          Text('Quantity: $quantity', style: BuyerStyle.small),
                        ],
                      ),
                    ),
                    Text(
                      '₱${itemPrice.toStringAsFixed(2)}',
                      style: BuyerStyle.largePrice,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              const Text('Delivery Information', style: BuyerStyle.sectionTitle),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BuyerStyle.card,
                child: Column(
                  children: [
                    _detailRow(Icons.storefront_outlined, 'Seller', sellerName),
                    const Divider(height: 30, color: BuyerColors.border),
                    _detailRow(Icons.delivery_dining_rounded, 'Rider', riderName),
                    if (fullAddress.isNotEmpty) ...[
                      const Divider(height: 30, color: BuyerColors.border),
                      _detailRow(
                        Icons.location_on_outlined,
                        'Delivery Address',
                        fullAddress,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 24),
              const Text('Payment Summary', style: BuyerStyle.sectionTitle),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BuyerStyle.card,
                child: Column(
                  children: [
                    _summaryRow('Subtotal', subtotal),
                    const SizedBox(height: 12),
                    _summaryRow('Delivery Fee', shipping),
                    const SizedBox(height: 12),
                    _summaryRow('Service Fee', serviceFee),
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 14),
                      child: Divider(height: 1, color: BuyerColors.border),
                    ),
                    _summaryRow('Total', total, isTotal: true),
                  ],
                ),
              ),
              if (orderId != null && sellerOrders.isNotEmpty) ...[
                const SizedBox(height: 24),
                SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton.icon(
                    style: BuyerStyle.primaryButton,
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => OrderTracking(
                            orderId: orderId,
                            orderNumber: orderNumber,
                          ),
                        ),
                      );
                    },
                    icon: const Icon(Icons.local_shipping_outlined),
                    label: const Text('Track Order'),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _detailRow(IconData icon, String title, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: BuyerColors.primary, size: 21),
        const SizedBox(width: 11),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: BuyerStyle.small),
              const SizedBox(height: 4),
              Text(value, style: BuyerStyle.cardTitle),
            ],
          ),
        ),
      ],
    );
  }

  Widget _summaryRow(String label, double value, {bool isTotal = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: isTotal ? BuyerStyle.sectionTitle : BuyerStyle.small),
        Text(
          '₱${value.toStringAsFixed(2)}',
          style: isTotal ? BuyerStyle.largePrice : BuyerStyle.cardTitle,
        ),
      ],
    );
  }

  List<Map<String, dynamic>> _mapList(dynamic value) {
    if (value is! List) return [];
    return value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Map<String, dynamic> _map(dynamic value) {
    return value is Map ? Map<String, dynamic>.from(value) : {};
  }

  String _titleCase(String value) {
    if (value.trim().isEmpty) return 'Unknown';
    return value
        .split(RegExp(r'\s+'))
        .where((word) => word.isNotEmpty)
        .map((word) => '${word[0].toUpperCase()}${word.substring(1)}')
        .join(' ');
  }
}
