import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../../sevices/auth_storage.dart';
import '../../styles/buyer_style.dart';

import 'order_details.dart';

class OrderHistory extends StatefulWidget {
  const OrderHistory({super.key});

  @override
  State<OrderHistory> createState() => _OrderHistoryState();
}

class _OrderHistoryState extends State<OrderHistory> {
  static const String _apiBaseUrl =
      'https://demo-bilihubs-shop.onrender.com/api';

  static const String _supabaseProductBaseUrl =
      'https://furccwxvsinfibcdhxtn.supabase.co/storage/v1/object/public/bilihub-products';

  final List<Map<String, dynamic>> orders = <Map<String, dynamic>>[];

  bool isLoading = true;
  bool isRefreshing = false;

  String? errorMessage;

  @override
  void initState() {
    super.initState();
    _loadOrders();
  }

  // ================================================================
  // LOAD ORDERS
  // ================================================================

  Future<void> _loadOrders({bool refresh = false}) async {
    if (refresh) {
      if (isRefreshing) {
        return;
      }

      setState(() {
        isRefreshing = true;
        errorMessage = null;
      });
    } else {
      setState(() {
        isLoading = true;
        errorMessage = null;
      });
    }

    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        if (!mounted) {
          return;
        }

        setState(() {
          isLoading = false;
          isRefreshing = false;
          errorMessage = 'Your session has expired. Please login again.';
        });

        return;
      }

      final Uri uri = Uri.parse('$_apiBaseUrl/orders')
          .replace(queryParameters: <String, String>{'per_page': '50'});

      final http.Response response = await http.get(
        uri,
        headers: <String, String>{
          'Accept': 'application/json',
          'Authorization': 'Bearer ${token.trim()}',
        },
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (!mounted) {
        return;
      }

      if (response.statusCode == 200 && decoded is Map<String, dynamic>) {
        final List<dynamic> rawOrders = decoded['data'] is List
            ? List<dynamic>.from(decoded['data'])
            : <dynamic>[];

        final List<Map<String, dynamic>> parsedOrders = rawOrders
            .whereType<Map>()
            .map((Map order) => Map<String, dynamic>.from(order))
            .toList();

        setState(() {
          orders
            ..clear()
            ..addAll(parsedOrders);

          isLoading = false;
          isRefreshing = false;
          errorMessage = null;
        });

        return;
      }

      String message = 'Unable to load your orders.';

      if (decoded is Map<String, dynamic>) {
        final String serverMessage =
            decoded['message']?.toString().trim() ?? '';

        if (serverMessage.isNotEmpty) {
          message = serverMessage;
        }
      }

      if (response.statusCode == 401) {
        message = 'Your session has expired. Please login again.';
      }

      setState(() {
        isLoading = false;
        isRefreshing = false;
        errorMessage = message;
      });
    } catch (error) {
      debugPrint('Order history error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        isLoading = false;
        isRefreshing = false;
        errorMessage =
            'Unable to connect to the BiliHub server. '
            'Please check your internet connection and try again.';
      });
    }
  }

  // ================================================================
  // BUILD
  // ================================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        automaticallyImplyLeading: false,
        title: const Text('Order History', style: BuyerStyle.appBarTitle),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: isLoading || isRefreshing
                ? null
                : () {
                    _loadOrders(refresh: true);
                  },
            icon: isRefreshing
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: BuyerColors.primary,
                    ),
                  )
                : const Icon(
                    Icons.refresh_rounded,
                    color: BuyerColors.textDark,
                  ),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: SafeArea(top: false, child: _buildBody()),
    );
  }

  // ================================================================
  // BODY
  // ================================================================

  Widget _buildBody() {
    if (isLoading) {
      return const Center(
        child: CircularProgressIndicator(color: BuyerColors.primary),
      );
    }

    if (errorMessage != null) {
      return _buildErrorState();
    }

    if (orders.isEmpty) {
      return _buildEmptyState();
    }

    return RefreshIndicator(
      color: BuyerColors.primary,
      onRefresh: () {
        return _loadOrders(refresh: true);
      },
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(
          parent: BouncingScrollPhysics(),
        ),
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 28),
        itemCount: orders.length,
        separatorBuilder: (BuildContext context, int index) {
          return const SizedBox(height: 14);
        },
        itemBuilder: (BuildContext context, int index) {
          final Map<String, dynamic> order = orders[index];

          return _buildOrderCard(context, order);
        },
      ),
    );
  }

  // ================================================================
  // OPEN ORDER DETAILS
  // ================================================================

  Future<void> _openOrderDetails(
    BuildContext context,
    Map<String, dynamic> order,
  ) async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return OrderDetails(order: order);
        },
      ),
    );

    if (!mounted) {
      return;
    }

    await _loadOrders(refresh: true);
  }

  // ================================================================
  // ORDER CARD
  // ================================================================

  Widget _buildOrderCard(BuildContext context, Map<String, dynamic> order) {
    final String status = _displayStatus(
      order['status']?.toString() ?? 'Unknown',
    );

    final String orderNumber = order['order_number']?.toString().trim() ?? '';

    final int orderId = int.tryParse(order['id']?.toString() ?? '') ?? 0;

    final List<Map<String, dynamic>> items = _mapList(order['items']);

    final List<Map<String, dynamic>> sellerOrders = _mapList(
      order['seller_orders'],
    );

    final Map<String, dynamic>? firstItem = items.isNotEmpty
        ? items.first
        : null;

    final String productName =
        firstItem?['product_name']?.toString().trim() ?? 'Order Item';

    final String imageUrl = _extractProductImageUrl(firstItem);

    final int quantity = items.fold<int>(0, (
      int previous,
      Map<String, dynamic> item,
    ) {
      return previous + (int.tryParse(item['quantity']?.toString() ?? '') ?? 0);
    });

    final String shopName = _extractShopName(sellerOrders);

    final double total = _toDouble(order['total']);

    final String date = _formatDate(order['created_at']?.toString() ?? '');

    final int extraItems = items.length > 1 ? items.length - 1 : 0;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: () {
          _openOrderDetails(context, order);
        },
        child: Ink(
          padding: const EdgeInsets.all(16),
          decoration: BuyerStyle.elevatedCard,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Expanded(
                    child: Text(
                      orderNumber.isNotEmpty
                          ? orderNumber
                          : orderId > 0
                          ? 'Order #$orderId'
                          : 'Order',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: BuyerStyle.cardTitle,
                    ),
                  ),
                  const SizedBox(width: 10),
                  _buildStatusBadge(status),
                ],
              ),

              const SizedBox(height: 15),

              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(14),
                    child: Container(
                      height: 65,
                      width: 65,
                      color: BuyerColors.lightPurple,
                      child: _buildProductImage(imageUrl),
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

                        if (extraItems > 0) ...[
                          const SizedBox(height: 4),
                          Text(
                            '+$extraItems more '
                            'item${extraItems > 1 ? 's' : ''}',
                            style: const TextStyle(
                              fontSize: 12,
                              color: BuyerColors.primary,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],

                        const SizedBox(height: 6),

                        Text(
                          shopName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                            color: BuyerColors.textGrey,
                          ),
                        ),

                        const SizedBox(height: 5),

                        Text(
                          'Total quantity: $quantity',
                          style: BuyerStyle.small,
                        ),

                        if (date.isNotEmpty) ...[
                          const SizedBox(height: 4),
                          Row(
                            children: [
                              const Icon(
                                Icons.calendar_today_outlined,
                                size: 12,
                                color: BuyerColors.textGrey,
                              ),
                              const SizedBox(width: 5),
                              Expanded(
                                child: Text(date, style: BuyerStyle.small),
                              ),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),

                  const SizedBox(width: 10),

                  Text(
                    '₱${total.toStringAsFixed(2)}',
                    style: BuyerStyle.largePrice,
                  ),
                ],
              ),

              const SizedBox(height: 16),

              const Divider(height: 1, color: BuyerColors.border),

              const SizedBox(height: 6),

              InkWell(
                borderRadius: BorderRadius.circular(12),
                onTap: () {
                  _openOrderDetails(context, order);
                },
                child: const Padding(
                  padding: EdgeInsets.symmetric(vertical: 9, horizontal: 2),
                  child: Row(
                    children: [
                      Icon(
                        Icons.receipt_long_outlined,
                        size: 18,
                        color: BuyerColors.primary,
                      ),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'View order details',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: BuyerColors.primary,
                          ),
                        ),
                      ),
                      Icon(
                        Icons.arrow_forward_ios_rounded,
                        size: 14,
                        color: BuyerColors.primary,
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ================================================================
  // PRODUCT IMAGE
  // ================================================================

  Widget _buildProductImage(String imageUrl) {
    if (imageUrl.isEmpty) {
      return const Icon(
        Icons.shopping_bag_outlined,
        color: BuyerColors.primary,
        size: 29,
      );
    }

    return Image.network(
      imageUrl,
      fit: BoxFit.cover,
      width: double.infinity,
      height: double.infinity,
      errorBuilder:
          (BuildContext context, Object error, StackTrace? stackTrace) {
            debugPrint('Order image failed: $imageUrl');

            return const Icon(
              Icons.shopping_bag_outlined,
              color: BuyerColors.primary,
              size: 29,
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

  String _extractProductImageUrl(Map<String, dynamic>? item) {
    if (item == null) {
      return '';
    }

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
      final String raw = candidate?.toString().trim() ?? '';

      final String normalized = _normalizeImageUrl(raw);

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

  // ================================================================
  // STATUS BADGE
  // ================================================================

  Widget _buildStatusBadge(String status) {
    Color backgroundColor;
    Color textColor;
    IconData icon;

    switch (status.toLowerCase()) {
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
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: textColor),
          const SizedBox(width: 5),
          Text(
            status,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: textColor,
            ),
          ),
        ],
      ),
    );
  }

  // ================================================================
  // ERROR STATE
  // ================================================================

  Widget _buildErrorState() {
    return RefreshIndicator(
      color: BuyerColors.primary,
      onRefresh: () {
        return _loadOrders(refresh: true);
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          SizedBox(
            height: MediaQuery.sizeOf(context).height * 0.62,
            child: Center(
              child: Padding(
                padding: const EdgeInsets.all(30),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Container(
                      height: 88,
                      width: 88,
                      decoration: BuyerStyle.iconContainer(),
                      child: const Icon(
                        Icons.error_outline_rounded,
                        color: BuyerColors.danger,
                        size: 42,
                      ),
                    ),

                    const SizedBox(height: 20),

                    const Text(
                      'Unable to load orders',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: BuyerColors.textDark,
                      ),
                    ),

                    const SizedBox(height: 8),

                    Text(
                      errorMessage ?? 'Something went wrong.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 14,
                        height: 1.5,
                        color: BuyerColors.textGrey,
                      ),
                    ),

                    const SizedBox(height: 20),

                    ElevatedButton.icon(
                      style: BuyerStyle.primaryButton,
                      onPressed: () {
                        _loadOrders();
                      },
                      icon: const Icon(Icons.refresh_rounded),
                      label: const Text('Try Again'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ================================================================
  // EMPTY STATE
  // ================================================================

  Widget _buildEmptyState() {
    return RefreshIndicator(
      color: BuyerColors.primary,
      onRefresh: () {
        return _loadOrders(refresh: true);
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          SizedBox(
            height: MediaQuery.sizeOf(context).height * 0.62,
            child: Center(
              child: Padding(
                padding: const EdgeInsets.all(30),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Container(
                      height: 88,
                      width: 88,
                      decoration: BuyerStyle.iconContainer(),
                      child: const Icon(
                        Icons.receipt_long_outlined,
                        color: BuyerColors.primary,
                        size: 42,
                      ),
                    ),

                    const SizedBox(height: 20),

                    const Text(
                      'No orders yet',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: BuyerColors.textDark,
                      ),
                    ),

                    const SizedBox(height: 8),

                    const Text(
                      'Your current and completed orders will appear here.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 14,
                        height: 1.5,
                        color: BuyerColors.textGrey,
                      ),
                    ),

                    const SizedBox(height: 14),

                    const Text(
                      'Pull down to refresh.',
                      style: BuyerStyle.small,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
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

  String _extractShopName(List<Map<String, dynamic>> sellerOrders) {
    if (sellerOrders.isEmpty) {
      return 'BiliHub Seller';
    }

    final dynamic rawSeller = sellerOrders.first['seller'];

    if (rawSeller is Map) {
      final String name = rawSeller['name']?.toString().trim() ?? '';

      if (name.isNotEmpty) {
        if (sellerOrders.length > 1) {
          return '$name +${sellerOrders.length - 1} more';
        }

        return name;
      }
    }

    return 'BiliHub Seller';
  }

  double _toDouble(dynamic value) {
    if (value is num) {
      return value.toDouble();
    }

    return double.tryParse(value?.toString() ?? '') ?? 0;
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

      const List<String> months = <String>[
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

  dynamic _decodeResponse(String body) {
    if (body.trim().isEmpty) {
      return null;
    }

    try {
      return jsonDecode(body);
    } catch (_) {
      return body;
    }
  }
}
