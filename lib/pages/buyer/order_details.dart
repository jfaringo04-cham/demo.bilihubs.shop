import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';
import '../../utils/date_formatter.dart';
import '../../utils/money_formatter.dart';
import '../../widgets/app_address_card.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/status_chip.dart';

class OrderDetails extends StatelessWidget {
  final Map<String, dynamic> order;

  const OrderDetails({super.key, required this.order});

  static const String _supabaseProductBaseUrl =
      'https://furccwxvsinfibcdhxtn.supabase.co/storage/v1/object/public/bilihub-products';

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final String rawStatus = order['status']?.toString().trim() ?? 'Unknown';

    final String status = _displayStatus(rawStatus);

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

    final String riderName = _cleanText(rider?['name']).isNotEmpty
        ? _cleanText(rider?['name'])
        : 'Rider not assigned';

    final String riderPhone = _cleanText(rider?['phone']);

    final String vehicleType = _cleanText(rider?['vehicle_type']);

    final String shopName = _extractShopName(sellerOrders);

    final String trackingNumber = _extractTrackingNumber(sellerOrders);

    final String shipmentStatus = _extractShipmentStatus(sellerOrders);

    final double subtotal = MoneyFormatter.value(order['subtotal']) ?? 0;

    final double shippingFee = MoneyFormatter.value(order['shipping_fee']) ?? 0;

    final double serviceFee = MoneyFormatter.value(order['service_fee']) ?? 0;

    final double discount = MoneyFormatter.value(order['discount']) ?? 0;

    final double total = MoneyFormatter.value(order['total']) ?? 0;

    final String paymentMethod = _formatPaymentMethod(
      order['payment_method']?.toString() ?? '',
    );

    final String paymentStatus = _displayStatus(
      order['payment_status']?.toString() ?? 'Unknown',
    );

    final String dateOrdered = DateFormatter.dateTime(
      order['created_at'],
      fallback: '',
    );

    final String confirmedAt = DateFormatter.dateTime(
      order['confirmed_at'],
      fallback: '',
    );

    final String deliveredAt = DateFormatter.dateTime(
      order['delivered_at'],
      fallback: '',
    );

    final String address = _extractAddress(order);

    final String notes = _cleanText(order['notes']);

    final Map<String, dynamic> shippingAddress = _addressMap(order);

    final String recipientName = _firstNonEmpty(<dynamic>[
      shippingAddress['recipient_name'],
      shippingAddress['name'],
      order['recipient_name'],
      order['customer_name'],
    ]);

    final String recipientPhone = _firstNonEmpty(<dynamic>[
      shippingAddress['phone'],
      shippingAddress['phone_number'],
      order['recipient_phone'],
      order['customer_phone'],
    ]);

    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        title: const Text('Order Details', style: BuyerStyle.appBarTitle),
        leading: IconButton(
          tooltip: 'Back',
          onPressed: () => Navigator.pop(context),
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: BuyerColors.textDark,
            size: 20,
          ),
        ),
      ),
      body: SafeArea(
        top: false,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ==================================================
              // ORDER HEADER
              // ==================================================

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
                          width: 54,
                          height: 54,
                          decoration: BuyerStyle.iconContainer(),
                          child: const Icon(
                            Icons.receipt_long_outlined,
                            color: BuyerColors.primary,
                            size: 28,
                          ),
                        ),

                        const SizedBox(width: 12),

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
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: BuyerStyle.cardTitle,
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 14),

                    StatusChip(status: rawStatus),

                    const SizedBox(height: 14),

                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(13),
                      decoration: BoxDecoration(
                        color: BuyerColors.lightPurple,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
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
                                height: 1.45,
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

              // ==================================================
              // PRODUCTS
              // ==================================================
              const Text('Products', style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              if (items.isEmpty)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BuyerStyle.card,
                  child: const AppEmptyState(
                    title: 'No Product Information',
                    message:
                        'Product details are not available for this order.',
                    icon: Icons.inventory_2_outlined,
                    color: BuyerColors.primary,
                    compact: true,
                  ),
                )
              else
                ListView.separated(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: items.length,
                  separatorBuilder: (BuildContext context, int index) {
                    return const SizedBox(height: 10);
                  },
                  itemBuilder: (BuildContext context, int index) {
                    return _buildProductCard(items[index]);
                  },
                ),

              const SizedBox(height: 24),

              // ==================================================
              // DELIVERY INFORMATION
              // ==================================================
              const Text(
                'Delivery Information',
                style: BuyerStyle.sectionTitle,
              ),

              const SizedBox(height: 12),

              _buildInfoCard(
                children: [
                  AppInfoRow(
                    label: 'Date Ordered',
                    value: dateOrdered,
                    icon: Icons.shopping_cart_checkout_rounded,
                    iconColor: BuyerColors.primary,
                  ),

                  if (confirmedAt.isNotEmpty) ...[
                    const Divider(height: 1, color: BuyerColors.border),
                    AppInfoRow(
                      label: 'Confirmed',
                      value: confirmedAt,
                      icon: Icons.check_circle_outline_rounded,
                      iconColor: BuyerColors.primary,
                    ),
                  ],

                  if (deliveredAt.isNotEmpty) ...[
                    const Divider(height: 1, color: BuyerColors.border),
                    AppInfoRow(
                      label: 'Date Delivered',
                      value: deliveredAt,
                      icon: Icons.event_available_outlined,
                      iconColor: BuyerColors.primary,
                    ),
                  ],

                  const Divider(height: 1, color: BuyerColors.border),

                  AppInfoRow(
                    label: 'Shop',
                    value: shopName,
                    icon: Icons.storefront_outlined,
                    iconColor: BuyerColors.primary,
                  ),

                  const Divider(height: 1, color: BuyerColors.border),

                  AppInfoRow(
                    label: 'Shipment Status',
                    value: shipmentStatus.isNotEmpty
                        ? _displayStatus(shipmentStatus)
                        : 'Not available',
                    icon: Icons.local_shipping_outlined,
                    iconColor: BuyerColors.primary,
                  ),

                  const Divider(height: 1, color: BuyerColors.border),

                  AppInfoRow(
                    label: 'Tracking Number',
                    value: trackingNumber,
                    icon: Icons.qr_code_2_rounded,
                    iconColor: BuyerColors.primary,
                    selectable: true,
                    fallback: 'Not available',
                  ),

                  const Divider(height: 1, color: BuyerColors.border),

                  AppInfoRow(
                    label: 'Rider',
                    value: _riderDisplay(riderName, riderPhone, vehicleType),
                    icon: Icons.delivery_dining_rounded,
                    iconColor: BuyerColors.primary,
                  ),
                ],
              ),

              const SizedBox(height: 24),

              // ==================================================
              // ADDRESS CARD
              // ==================================================
              AppAddressCard(
                title: 'Delivery Address',
                recipientName: recipientName,
                phoneNumber: recipientPhone,
                address: address,
                accentColor: BuyerColors.primary,
                fallback: 'Delivery address not available',
              ),

              const SizedBox(height: 24),

              // ==================================================
              // PAYMENT INFORMATION
              // ==================================================
              const Text('Payment Information', style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              _buildInfoCard(
                children: [
                  AppInfoRow(
                    label: 'Payment Method',
                    value: paymentMethod,
                    icon: Icons.payments_outlined,
                    iconColor: BuyerColors.primary,
                  ),

                  const Divider(height: 1, color: BuyerColors.border),

                  AppInfoRow(
                    label: 'Payment Status',
                    value: paymentStatus,
                    icon: Icons.account_balance_wallet_outlined,
                    iconColor: BuyerColors.primary,
                  ),
                ],
              ),

              const SizedBox(height: 24),

              // ==================================================
              // ORDER SUMMARY
              // ==================================================
              const Text('Order Summary', style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BuyerStyle.card,
                child: Column(
                  children: [
                    _summaryRow('Subtotal', MoneyFormatter.peso(subtotal)),

                    const SizedBox(height: 12),

                    _summaryRow(
                      'Shipping Fee',
                      MoneyFormatter.peso(shippingFee),
                    ),

                    const SizedBox(height: 12),

                    _summaryRow('Service Fee', MoneyFormatter.peso(serviceFee)),

                    if (discount > 0) ...[
                      const SizedBox(height: 12),

                      _summaryRow(
                        'Discount',
                        MoneyFormatter.signedPeso(-discount),
                      ),
                    ],

                    const SizedBox(height: 14),

                    const Divider(height: 1, color: BuyerColors.border),

                    const SizedBox(height: 14),

                    _summaryRow(
                      'Total',
                      MoneyFormatter.peso(total),
                      isTotal: true,
                    ),
                  ],
                ),
              ),

              // ==================================================
              // ORDER NOTES
              // ==================================================
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

              // ==================================================
              // SUPPORT INFORMATION
              // ==================================================
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

  // ============================================================
  // REUSABLE INFO CARD
  // ============================================================

  Widget _buildInfoCard({required List<Widget> children}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      decoration: BuyerStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: children,
      ),
    );
  }

  // ============================================================
  // PRODUCT CARD
  // ============================================================

  Widget _buildProductCard(Map<String, dynamic> item) {
    final String productName = _cleanText(item['product_name']).isNotEmpty
        ? _cleanText(item['product_name'])
        : 'Product';

    final String imageUrl = _extractProductImageUrl(item);

    final String variation = _cleanText(item['variation_name']);

    final String size = _cleanText(item['size_name']);

    final int quantity = int.tryParse(item['quantity']?.toString() ?? '') ?? 0;

    final double price = MoneyFormatter.value(item['price']) ?? 0;

    final double subtotal = MoneyFormatter.value(item['subtotal']) ?? 0;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BuyerStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(14),
                child: Container(
                  width: 68,
                  height: 68,
                  color: BuyerColors.lightPurple,
                  child: _buildProductImage(imageUrl),
                ),
              ),

              const SizedBox(width: 12),

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
                      '${MoneyFormatter.peso(price)} each',
                      style: BuyerStyle.small,
                    ),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          const Divider(height: 1, color: BuyerColors.border),

          const SizedBox(height: 10),

          Row(
            children: [
              const Expanded(
                child: Text('Item Subtotal', style: BuyerStyle.small),
              ),

              Flexible(
                child: Text(
                  MoneyFormatter.peso(subtotal),
                  textAlign: TextAlign.end,
                  style: BuyerStyle.largePrice,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PRODUCT IMAGE
  // ============================================================

  Widget _buildProductImage(String imageUrl) {
    if (imageUrl.isEmpty) {
      return const Icon(
        Icons.shopping_bag_outlined,
        color: BuyerColors.primary,
        size: 31,
      );
    }

    return Image.network(
      imageUrl,
      fit: BoxFit.cover,
      width: double.infinity,
      height: double.infinity,
      errorBuilder:
          (BuildContext context, Object error, StackTrace? stackTrace) {
            debugPrint('Order details image failed: $imageUrl');

            return const Icon(
              Icons.shopping_bag_outlined,
              color: BuyerColors.primary,
              size: 31,
            );
          },
      loadingBuilder:
          (BuildContext context, Widget child, ImageChunkEvent? progress) {
            if (progress == null) {
              return child;
            }

            return const Center(
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: BuyerColors.primary,
              ),
            );
          },
    );
  }

  // ============================================================
  // PRODUCT IMAGE URL
  // ============================================================

  String _extractProductImageUrl(Map<String, dynamic> item) {
    final List<dynamic> candidates = <dynamic>[
      item['product_image'],
      item['image_url'],
      item['product_image_url'],
      item['image'],
      item['thumbnail'],
      item['thumbnail_url'],
    ];

    final dynamic product = item['product'];

    if (product is Map) {
      candidates.addAll(<dynamic>[
        product['image_url'],
        product['product_image'],
        product['primary_image'],
        product['image'],
        product['thumbnail'],
      ]);

      final dynamic images = product['images'];

      if (images is List && images.isNotEmpty) {
        final dynamic firstImage = images.first;

        if (firstImage is Map) {
          candidates.addAll(<dynamic>[
            firstImage['url'],
            firstImage['image_url'],
            firstImage['path'],
          ]);
        } else {
          candidates.add(firstImage);
        }
      }
    }

    for (final dynamic candidate in candidates) {
      final String normalized = _normalizeImageUrl(_cleanText(candidate));

      if (normalized.isNotEmpty) {
        return normalized;
      }
    }

    return '';
  }

  String _normalizeImageUrl(String rawUrl) {
    String value = rawUrl.trim();

    if (value.isEmpty || value.toLowerCase() == 'null') {
      return '';
    }

    value = value.replaceAll('\\', '/');

    if (value.startsWith('//')) {
      return 'https:$value';
    }

    final Uri? parsed = Uri.tryParse(value);

    if (parsed != null &&
        (parsed.scheme == 'http' || parsed.scheme == 'https')) {
      return value;
    }

    while (value.startsWith('/')) {
      value = value.substring(1);
    }

    if (value.startsWith('storage/')) {
      value = value.substring('storage/'.length);
    }

    if (value.startsWith('bilihub-products/')) {
      value = value.substring('bilihub-products/'.length);
    }

    if (value.startsWith('products/')) {
      return '$_supabaseProductBaseUrl/$value';
    }

    return '$_supabaseProductBaseUrl/products/$value';
  }

  // ============================================================
  // ORDER SUMMARY ROW
  // ============================================================

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

        const SizedBox(width: 12),

        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: TextStyle(
              fontSize: isTotal ? 18 : 14,
              fontWeight: isTotal ? FontWeight.w800 : FontWeight.w600,
              color: isTotal ? BuyerColors.primary : BuyerColors.textDark,
            ),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // ORDER STATUS MESSAGE
  // ============================================================

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
      case 'delivery failed':
        return 'The delivery attempt for this order was unsuccessful.';

      default:
        return 'Check this page for the latest information about your order.';
    }
  }

  // ============================================================
  // DATA HELPERS
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
        final String name = _cleanText(seller['name']);

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
        final String tracking = _cleanText(shipment['tracking_number']);

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
        final String status = _cleanText(shipment['status']);

        if (status.isNotEmpty) {
          return status;
        }
      }
    }

    return _cleanText(order['delivery_status']);
  }

  // ============================================================
  // ADDRESS EXTRACTION
  // ============================================================

  Map<String, dynamic> _addressMap(Map<String, dynamic> order) {
    final dynamic shippingAddress = order['shipping_address'];

    if (shippingAddress is Map) {
      return Map<String, dynamic>.from(shippingAddress);
    }

    final dynamic directAddress = order['address'];

    if (directAddress is Map) {
      return Map<String, dynamic>.from(directAddress);
    }

    return <String, dynamic>{};
  }

  String _extractAddress(Map<String, dynamic> order) {
    final dynamic directAddress = order['address'];

    if (directAddress is String && directAddress.trim().isNotEmpty) {
      return directAddress.trim();
    }

    if (directAddress is Map) {
      final String fullAddress = _cleanText(directAddress['full_address']);

      if (fullAddress.isNotEmpty) {
        return fullAddress;
      }
    }

    final dynamic shippingAddress = order['shipping_address'];

    if (shippingAddress is String && shippingAddress.trim().isNotEmpty) {
      return shippingAddress.trim();
    }

    if (shippingAddress is Map) {
      final String fullAddress = _cleanText(shippingAddress['full_address']);

      if (fullAddress.isNotEmpty) {
        return fullAddress;
      }

      final List<String> parts = <String>[
        _cleanText(shippingAddress['address_line1']),
        _cleanText(shippingAddress['address_line2']),
        _cleanText(shippingAddress['city']),
        _cleanText(shippingAddress['province']),
        _cleanText(shippingAddress['postal_code']),
        _cleanText(shippingAddress['country']),
      ].where((String part) => part.isNotEmpty).toList();

      return parts.join(', ');
    }

    return '';
  }

  String _riderDisplay(String name, String phone, String vehicle) {
    final List<String> parts = <String>[
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
        return value.trim().isEmpty ? 'Not available' : _displayStatus(value);
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
