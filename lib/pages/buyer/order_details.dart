import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';

class OrderDetails extends StatelessWidget {
  final Map<String, dynamic> order;

  const OrderDetails({super.key, required this.order});

  @override
  Widget build(BuildContext context) {
    final String status = _displayStatus(
      order['status']?.toString() ?? 'Unknown',
    );

    final String orderNumber = order['order_number']?.toString().trim() ?? '';

    final int orderId = int.tryParse(order['id']?.toString() ?? '') ?? 0;

    final String displayOrderNumber = orderNumber.isNotEmpty
        ? orderNumber
        : orderId > 0
        ? 'Order #$orderId'
        : 'Order';

    final List<Map<String, dynamic>> items = _mapList(order['items']);

    final List<Map<String, dynamic>> sellerOrders = _mapList(
      order['seller_orders'],
    );

    final Map<String, dynamic>? rider = _extractRider(order, sellerOrders);

    final String riderName =
        rider?['name']?.toString().trim().isNotEmpty == true
        ? rider!['name'].toString().trim()
        : 'Rider not assigned';

    final String riderPhone = rider?['phone']?.toString().trim() ?? '';

    final String vehicleType = rider?['vehicle_type']?.toString().trim() ?? '';

    final String shopName = _extractShopName(sellerOrders);

    final String trackingNumber = _extractTrackingNumber(sellerOrders);

    final String shipmentStatus = _extractShipmentStatus(sellerOrders);

    final double subtotal = _toDouble(order['subtotal']);

    final double shippingFee = _toDouble(order['shipping_fee']);

    final double serviceFee = _toDouble(order['service_fee']);

    final double discount = _toDouble(order['discount']);

    final double total = _toDouble(order['total']);

    final String paymentMethod = _formatPaymentMethod(
      order['payment_method']?.toString() ?? '',
    );

    final String paymentStatus = _displayStatus(
      order['payment_status']?.toString() ?? 'Unknown',
    );

    final String dateOrdered = _formatDate(
      order['created_at']?.toString() ?? '',
    );

    final String confirmedAt = _formatDate(
      order['confirmed_at']?.toString() ?? '',
    );

    final String deliveredAt = _formatDate(
      order['delivered_at']?.toString() ?? '',
    );

    final String address = _extractAddress(order);

    final String notes = order['notes']?.toString().trim() ?? '';

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
        title: const Text('Order Details', style: BuyerStyle.appBarTitle),
      ),
      body: SafeArea(
        top: false,
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ============================================================
              // ORDER HEADER
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
                          height: 54,
                          width: 54,
                          decoration: BuyerStyle.iconContainer(),
                          child: const Icon(
                            Icons.receipt_long_outlined,
                            color: BuyerColors.primary,
                            size: 28,
                          ),
                        ),

                        const SizedBox(width: 14),

                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Order Number',
                                style: BuyerStyle.small,
                              ),
                              const SizedBox(height: 4),
                              Text(
                                displayOrderNumber,
                                style: BuyerStyle.cardTitle,
                              ),
                            ],
                          ),
                        ),

                        const SizedBox(width: 10),

                        _buildStatusBadge(status),
                      ],
                    ),

                    const SizedBox(height: 16),

                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 12,
                      ),
                      decoration: BoxDecoration(
                        color: BuyerColors.lightPurple,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.info_outline_rounded,
                            color: BuyerColors.primary,
                            size: 19,
                          ),
                          const SizedBox(width: 9),
                          Expanded(
                            child: Text(
                              _statusMessage(status),
                              style: const TextStyle(
                                fontSize: 12,
                                height: 1.4,
                                color: BuyerColors.textDark,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // ============================================================
              // PRODUCT INFORMATION
              // ============================================================
              const Text('Products', style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              if (items.isEmpty)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(18),
                  decoration: BuyerStyle.card,
                  child: const Text(
                    'No product information available.',
                    style: BuyerStyle.small,
                  ),
                )
              else
                ListView.separated(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: items.length,
                  separatorBuilder: (context, index) {
                    return const SizedBox(height: 10);
                  },
                  itemBuilder: (BuildContext context, int index) {
                    return _buildProductCard(items[index]);
                  },
                ),

              const SizedBox(height: 24),

              // ============================================================
              // DELIVERY INFORMATION
              // ============================================================
              const Text(
                'Delivery Information',
                style: BuyerStyle.sectionTitle,
              ),

              const SizedBox(height: 12),

              Container(
                padding: const EdgeInsets.all(16),
                decoration: BuyerStyle.card,
                child: Column(
                  children: [
                    _detailRow(
                      icon: Icons.shopping_cart_checkout_rounded,
                      title: 'Date Ordered',
                      value: dateOrdered.isNotEmpty
                          ? dateOrdered
                          : 'Not available',
                    ),

                    if (confirmedAt.isNotEmpty) ...[
                      const Divider(height: 30, color: BuyerColors.border),
                      _detailRow(
                        icon: Icons.check_circle_outline_rounded,
                        title: 'Confirmed',
                        value: confirmedAt,
                      ),
                    ],

                    if (deliveredAt.isNotEmpty) ...[
                      const Divider(height: 30, color: BuyerColors.border),
                      _detailRow(
                        icon: Icons.event_available_outlined,
                        title: 'Date Delivered',
                        value: deliveredAt,
                      ),
                    ],

                    const Divider(height: 30, color: BuyerColors.border),

                    _detailRow(
                      icon: Icons.location_on_outlined,
                      title: 'Delivery Address',
                      value: address.isNotEmpty
                          ? address
                          : 'Address not available',
                    ),

                    const Divider(height: 30, color: BuyerColors.border),

                    _detailRow(
                      icon: Icons.storefront_outlined,
                      title: 'Shop',
                      value: shopName,
                    ),

                    const Divider(height: 30, color: BuyerColors.border),

                    _detailRow(
                      icon: Icons.local_shipping_outlined,
                      title: 'Shipment Status',
                      value: shipmentStatus.isNotEmpty
                          ? _displayStatus(shipmentStatus)
                          : 'Not available',
                    ),

                    const Divider(height: 30, color: BuyerColors.border),

                    _detailRow(
                      icon: Icons.qr_code_2_rounded,
                      title: 'Tracking Number',
                      value: trackingNumber.isNotEmpty
                          ? trackingNumber
                          : 'Not available',
                    ),

                    const Divider(height: 30, color: BuyerColors.border),

                    _detailRow(
                      icon: Icons.delivery_dining_rounded,
                      title: 'Rider',
                      value: _riderDisplay(riderName, riderPhone, vehicleType),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // ============================================================
              // PAYMENT INFORMATION
              // ============================================================
              const Text('Payment Information', style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              Container(
                padding: const EdgeInsets.all(16),
                decoration: BuyerStyle.card,
                child: Column(
                  children: [
                    _detailRow(
                      icon: Icons.payments_outlined,
                      title: 'Payment Method',
                      value: paymentMethod,
                    ),

                    const Divider(height: 30, color: BuyerColors.border),

                    _detailRow(
                      icon: Icons.account_balance_wallet_outlined,
                      title: 'Payment Status',
                      value: paymentStatus,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // ============================================================
              // ORDER SUMMARY
              // ============================================================
              const Text('Order Summary', style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              Container(
                padding: const EdgeInsets.all(16),
                decoration: BuyerStyle.card,
                child: Column(
                  children: [
                    _summaryRow('Subtotal', '₱${subtotal.toStringAsFixed(2)}'),

                    const SizedBox(height: 12),

                    _summaryRow(
                      'Shipping Fee',
                      '₱${shippingFee.toStringAsFixed(2)}',
                    ),

                    const SizedBox(height: 12),

                    _summaryRow(
                      'Service Fee',
                      '₱${serviceFee.toStringAsFixed(2)}',
                    ),

                    if (discount > 0) ...[
                      const SizedBox(height: 12),
                      _summaryRow(
                        'Discount',
                        '-₱${discount.toStringAsFixed(2)}',
                      ),
                    ],

                    const SizedBox(height: 14),

                    const Divider(height: 1, color: BuyerColors.border),

                    const SizedBox(height: 14),

                    _summaryRow(
                      'Total',
                      '₱${total.toStringAsFixed(2)}',
                      isTotal: true,
                    ),
                  ],
                ),
              ),

              if (notes.isNotEmpty) ...[
                const SizedBox(height: 24),

                const Text('Order Notes', style: BuyerStyle.sectionTitle),

                const SizedBox(height: 12),

                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BuyerStyle.card,
                  child: Text(notes, style: BuyerStyle.body),
                ),
              ],

              const SizedBox(height: 24),

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
                      Icons.support_agent_rounded,
                      color: BuyerColors.primary,
                      size: 21,
                    ),
                    SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'If there is an issue with this order, '
                        'please contact BiliHub support.',
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
      ),
    );
  }

  // ================================================================
  // PRODUCT CARD
  // ================================================================

  Widget _buildProductCard(Map<String, dynamic> item) {
    final String productName =
        item['product_name']?.toString().trim() ?? 'Product';

    final String imageUrl = item['product_image']?.toString().trim() ?? '';

    final String variation = item['variation_name']?.toString().trim() ?? '';

    final String size = item['size_name']?.toString().trim() ?? '';

    final int quantity = int.tryParse(item['quantity']?.toString() ?? '') ?? 0;

    final double price = _toDouble(item['price']);

    final double subtotal = _toDouble(item['subtotal']);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BuyerStyle.card,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
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
                      errorBuilder: (context, error, stackTrace) {
                        return const Icon(
                          Icons.broken_image_outlined,
                          color: BuyerColors.primary,
                          size: 31,
                        );
                      },
                    ),
            ),
          ),

          const SizedBox(width: 14),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  productName,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: BuyerStyle.productTitle,
                ),

                if (variation.isNotEmpty) ...[
                  const SizedBox(height: 5),
                  Text('Variant: $variation', style: BuyerStyle.small),
                ],

                if (size.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text('Size: $size', style: BuyerStyle.small),
                ],

                const SizedBox(height: 5),

                Text('Qty: $quantity', style: BuyerStyle.small),

                const SizedBox(height: 4),

                Text(
                  '₱${price.toStringAsFixed(2)} each',
                  style: BuyerStyle.small,
                ),
              ],
            ),
          ),

          const SizedBox(width: 10),

          Text('₱${subtotal.toStringAsFixed(2)}', style: BuyerStyle.largePrice),
        ],
      ),
    );
  }

  // ================================================================
  // DETAIL ROW
  // ================================================================

  Widget _detailRow({
    required IconData icon,
    required String title,
    required String value,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          height: 42,
          width: 42,
          decoration: BoxDecoration(
            color: BuyerColors.lightPurple,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, color: BuyerColors.primary, size: 20),
        ),

        const SizedBox(width: 12),

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

  // ================================================================
  // SUMMARY ROW
  // ================================================================

  Widget _summaryRow(String title, String value, {bool isTotal = false}) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: TextStyle(
              fontSize: isTotal ? 15 : 13,
              fontWeight: isTotal ? FontWeight.w700 : FontWeight.w500,
              color: isTotal ? BuyerColors.textDark : BuyerColors.textGrey,
            ),
          ),
        ),

        Text(
          value,
          style: TextStyle(
            fontSize: isTotal ? 18 : 14,
            fontWeight: isTotal ? FontWeight.w800 : FontWeight.w600,
            color: isTotal ? BuyerColors.primary : BuyerColors.textDark,
          ),
        ),
      ],
    );
  }

  // ================================================================
  // STATUS BADGE
  // ================================================================

  Widget _buildStatusBadge(String status) {
    final String normalizedStatus = status.toLowerCase().trim();

    Color backgroundColor;
    Color textColor;
    IconData icon;

    switch (normalizedStatus) {
      case 'delivered':
        backgroundColor = BuyerColors.deliveredBackground;
        textColor = BuyerColors.deliveredText;
        icon = Icons.check_circle_outline_rounded;
        break;

      case 'pending':
      case 'confirmed':
      case 'processing':
      case 'preparing':
        backgroundColor = BuyerColors.processingBackground;
        textColor = BuyerColors.processingText;
        icon = Icons.schedule_rounded;
        break;

      case 'shipped':
      case 'in transit':
      case 'out for delivery':
        backgroundColor = BuyerColors.shippedBackground;
        textColor = BuyerColors.shippedText;
        icon = Icons.local_shipping_outlined;
        break;

      case 'cancelled':
      case 'canceled':
      case 'failed':
        backgroundColor = BuyerColors.cancelledBackground;
        textColor = BuyerColors.cancelledText;
        icon = Icons.cancel_outlined;
        break;

      default:
        backgroundColor = BuyerColors.lightPurple;
        textColor = BuyerColors.primary;
        icon = Icons.info_outline_rounded;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: textColor, size: 14),

          const SizedBox(width: 5),

          Text(
            status,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: textColor,
            ),
          ),
        ],
      ),
    );
  }

  // ================================================================
  // STATUS MESSAGE
  // ================================================================

  String _statusMessage(String status) {
    switch (status.toLowerCase().trim()) {
      case 'delivered':
        return 'This order has been delivered successfully.';

      case 'shipped':
      case 'in transit':
      case 'out for delivery':
        return 'Your order is on the way to your delivery address.';

      case 'pending':
        return 'Your order has been received and is waiting to be processed.';

      case 'confirmed':
        return 'Your order has been confirmed.';

      case 'processing':
      case 'preparing':
        return 'The shop is currently preparing your order.';

      case 'cancelled':
      case 'canceled':
        return 'This order has been cancelled.';

      case 'failed':
        return 'The delivery attempt for this order was unsuccessful.';

      default:
        return 'Check this page for the latest information about your order.';
    }
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

  Map<String, dynamic>? _extractRider(
    Map<String, dynamic> order,
    List<Map<String, dynamic>> sellerOrders,
  ) {
    final dynamic directRider = order['rider'];

    if (directRider is Map) {
      return Map<String, dynamic>.from(directRider);
    }

    for (final Map<String, dynamic> sellerOrder in sellerOrders) {
      final dynamic shipment = sellerOrder['shipment'];

      if (shipment is Map && shipment['rider'] is Map) {
        return Map<String, dynamic>.from(shipment['rider']);
      }
    }

    return null;
  }

  String _extractShopName(List<Map<String, dynamic>> sellerOrders) {
    if (sellerOrders.isEmpty) {
      return 'Shop not available';
    }

    final List<String> names = <String>[];

    for (final Map<String, dynamic> sellerOrder in sellerOrders) {
      final dynamic seller = sellerOrder['seller'];

      if (seller is Map) {
        final String name = seller['name']?.toString().trim() ?? '';

        if (name.isNotEmpty) {
          names.add(name);
        }
      }
    }

    if (names.isEmpty) {
      return 'Shop not available';
    }

    return names.toSet().join(', ');
  }

  String _extractTrackingNumber(List<Map<String, dynamic>> sellerOrders) {
    for (final Map<String, dynamic> sellerOrder in sellerOrders) {
      final dynamic shipment = sellerOrder['shipment'];

      if (shipment is Map) {
        final String tracking =
            shipment['tracking_number']?.toString().trim() ?? '';

        if (tracking.isNotEmpty) {
          return tracking;
        }
      }
    }

    return '';
  }

  String _extractShipmentStatus(List<Map<String, dynamic>> sellerOrders) {
    for (final Map<String, dynamic> sellerOrder in sellerOrders) {
      final dynamic shipment = sellerOrder['shipment'];

      if (shipment is Map) {
        final String status = shipment['status']?.toString().trim() ?? '';

        if (status.isNotEmpty) {
          return status;
        }
      }
    }

    return order['delivery_status']?.toString().trim() ?? '';
  }

  String _extractAddress(Map<String, dynamic> order) {
    final dynamic rawAddress = order['address'];

    if (rawAddress is Map) {
      final String fullAddress =
          rawAddress['full_address']?.toString().trim() ?? '';

      if (fullAddress.isNotEmpty) {
        return fullAddress;
      }
    }

    final dynamic shippingAddress = order['shipping_address'];

    if (shippingAddress is Map) {
      final List<String> parts = [
        shippingAddress['address_line1']?.toString().trim() ?? '',
        shippingAddress['address_line2']?.toString().trim() ?? '',
        shippingAddress['city']?.toString().trim() ?? '',
        shippingAddress['province']?.toString().trim() ?? '',
        shippingAddress['postal_code']?.toString().trim() ?? '',
        shippingAddress['country']?.toString().trim() ?? '',
      ].where((String part) => part.isNotEmpty).toList();

      return parts.join(', ');
    }

    return '';
  }

  String _riderDisplay(String name, String phone, String vehicle) {
    final List<String> parts = [
      name,
      if (phone.isNotEmpty) phone,
      if (vehicle.isNotEmpty) _displayStatus(vehicle),
    ];

    return parts.join('\n');
  }

  String _formatPaymentMethod(String value) {
    switch (value.toLowerCase().trim()) {
      case 'cod':
        return 'Cash on Delivery';

      case 'gcash':
        return 'GCash';

      case 'maya':
        return 'Maya';

      case 'card':
        return 'Card';

      case 'bank_transfer':
        return 'Bank Transfer';

      default:
        return value.isEmpty ? 'Not available' : _displayStatus(value);
    }
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
              '${word[0].toUpperCase()}${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  double _toDouble(dynamic value) {
    if (value is num) {
      return value.toDouble();
    }

    return double.tryParse(value?.toString() ?? '') ?? 0;
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
