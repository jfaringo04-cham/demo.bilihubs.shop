import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../../services/auth_storage.dart';
import '../../styles/buyer_style.dart';
import '../../utils/date_formatter.dart';
import '../../utils/money_formatter.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_skeleton.dart';
import '../../widgets/status_chip.dart';

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

  static const Duration _requestTimeout = Duration(seconds: 25);

  final List<Map<String, dynamic>> orders = <Map<String, dynamic>>[];

  bool isLoading = true;
  bool isRefreshing = false;
  bool _hasLoadedOrders = false;

  String? errorMessage;

  int _requestId = 0;

  @override
  void initState() {
    super.initState();
    _loadOrders();
  }

  @override
  void dispose() {
    _requestId++;
    super.dispose();
  }

  // ============================================================
  // LOAD ORDERS
  // ============================================================

  Future<void> _loadOrders({bool refresh = false}) async {
    if (!mounted || isLoading && _requestId > 0 || isRefreshing) {
      return;
    }

    final int requestId = ++_requestId;

    setState(() {
      isLoading = !_hasLoadedOrders;
      isRefreshing = _hasLoadedOrders;
      errorMessage = null;
    });

    try {
      final String? token = await AuthStorage.getToken().timeout(
        _requestTimeout,
      );

      if (!mounted || requestId != _requestId) return;

      if (token == null || token.trim().isEmpty) {
        _setLoadError(
          requestId,
          'Your session has expired. Please log in again.',
        );
        return;
      }

      final Uri uri = Uri.parse('$_apiBaseUrl/orders')
          .replace(queryParameters: <String, String>{'per_page': '50'});

      final http.Response response = await http
          .get(
            uri,
            headers: <String, String>{
              'Accept': 'application/json',
              'Authorization': 'Bearer ${token.trim()}',
            },
          )
          .timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) return;

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode >= 200 &&
          response.statusCode < 300 &&
          decoded is Map) {
        final List<dynamic>? rawOrders = _extractOrders(decoded);

        if (rawOrders == null) {
          _setLoadError(
            requestId,
            'The server returned an unexpected order response.',
          );
          return;
        }

        final List<Map<String, dynamic>> parsedOrders =
            <Map<String, dynamic>>[];

        for (final dynamic item in rawOrders) {
          if (item is Map) {
            parsedOrders.add(Map<String, dynamic>.from(item));
          }
        }

        if (!mounted || requestId != _requestId) return;

        setState(() {
          orders
            ..clear()
            ..addAll(parsedOrders);

          _hasLoadedOrders = true;
          isLoading = false;
          isRefreshing = false;
          errorMessage = null;
        });

        return;
      }

      String message = 'Unable to load your orders.';

      if (decoded is Map) {
        final String serverMessage =
            decoded['message']?.toString().trim() ?? '';

        if (serverMessage.isNotEmpty) {
          message = serverMessage;
        }
      }

      if (response.statusCode == 401) {
        message = 'Your session has expired. Please log in again.';
      } else if (response.statusCode == 403) {
        message = 'You do not have permission to view these orders.';
      } else if (response.statusCode >= 500) {
        message =
            'The BiliHub server is temporarily unavailable. '
            'Please try again.';
      }

      _setLoadError(requestId, message);
    } on TimeoutException {
      _setLoadError(
        requestId,
        'The server took too long to respond. '
        'Please check your connection and retry.',
      );
    } catch (error) {
      debugPrint('Order history error: $error');

      _setLoadError(
        requestId,
        'Unable to connect to the BiliHub server. '
        'Please check your internet connection and retry.',
      );
    }
  }

  // Supports:
  // { "data": [ ... ] }
  // { "data": { "data": [ ... ] } }
  // { "data": { "orders": [ ... ] } }
  // { "orders": [ ... ] }

  List<dynamic>? _extractOrders(Map<dynamic, dynamic> response) {
    final dynamic data = response['data'];

    if (data is List) {
      return data;
    }

    if (data is Map) {
      if (data['data'] is List) {
        return data['data'] as List<dynamic>;
      }

      if (data['orders'] is List) {
        return data['orders'] as List<dynamic>;
      }
    }

    if (response['orders'] is List) {
      return response['orders'] as List<dynamic>;
    }

    return null;
  }

  void _setLoadError(int requestId, String message) {
    if (!mounted || requestId != _requestId) return;

    setState(() {
      isLoading = false;
      isRefreshing = false;
      errorMessage = message;
    });
  }

  Future<void> _refreshOrders() {
    return _loadOrders(refresh: true);
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        surfaceTintColor: Colors.transparent,
        centerTitle: true,
        automaticallyImplyLeading: false,
        title: const Text('Order History', style: BuyerStyle.appBarTitle),
        actions: [
          IconButton(
            tooltip: 'Refresh orders',
            onPressed: isLoading || isRefreshing
                ? null
                : () {
                    _refreshOrders();
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
      body: SafeArea(
        top: false,
        child: RefreshIndicator(
          color: BuyerColors.primary,
          onRefresh: _refreshOrders,
          child: _buildBody(),
        ),
      ),
    );
  }

  // ============================================================
  // BODY
  // ============================================================

  Widget _buildBody() {
    if (isLoading && !_hasLoadedOrders) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 28),
        children: const [
          AppSkeleton(
            type: AppSkeletonType.order,
            itemCount: 4,
            color: BuyerColors.primary,
          ),
        ],
      );
    }

    if (errorMessage != null && !_hasLoadedOrders) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 20),
        children: [
          const SizedBox(height: 110),
          AppErrorState(
            title: 'Unable to Load Orders',
            message: errorMessage!,
            color: BuyerColors.primary,
            onRetry: () {
              _loadOrders();
            },
          ),
        ],
      );
    }

    if (orders.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 20),
        children: [
          if (isRefreshing) ...[
            const SizedBox(height: 12),
            const LinearProgressIndicator(
              color: BuyerColors.primary,
              minHeight: 2,
            ),
          ],
          if (errorMessage != null) ...[
            const SizedBox(height: 15),
            _buildRefreshWarning(errorMessage!),
          ],
          const SizedBox(height: 100),
          const AppEmptyState(
            title: 'No Orders Yet',
            message:
                'Your current and completed orders will appear here. '
                'Pull down to refresh.',
            icon: Icons.receipt_long_outlined,
            color: BuyerColors.primary,
          ),
        ],
      );
    }

    final int extraCount =
        (isRefreshing ? 1 : 0) + (errorMessage != null ? 1 : 0);

    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(
        parent: BouncingScrollPhysics(),
      ),
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 28),
      itemCount: orders.length + extraCount,
      separatorBuilder: (BuildContext context, int index) {
        return const SizedBox(height: 14);
      },
      itemBuilder: (BuildContext context, int index) {
        int orderIndex = index;

        if (isRefreshing) {
          if (orderIndex == 0) {
            return const LinearProgressIndicator(
              color: BuyerColors.primary,
              minHeight: 2,
            );
          }

          orderIndex--;
        }

        if (errorMessage != null) {
          if (orderIndex == 0) {
            return _buildRefreshWarning(errorMessage!);
          }

          orderIndex--;
        }

        final Map<String, dynamic> order = orders[orderIndex];

        return _buildOrderCard(order);
      },
    );
  }

  // ============================================================
  // REFRESH WARNING
  // ============================================================

  Widget _buildRefreshWarning(String message) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: BuyerColors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: BuyerColors.border),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.wifi_off_rounded,
            color: BuyerColors.primary,
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(child: Text(message, style: BuyerStyle.small)),
          TextButton(
            onPressed: isLoading || isRefreshing
                ? null
                : () {
                    _refreshOrders();
                  },
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // ORDER DETAILS
  // ============================================================

  Future<void> _openOrderDetails(Map<String, dynamic> order) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return OrderDetails(order: order);
        },
      ),
    );

    if (!mounted) return;

    // Refresh order statuses after returning
    // from the details screen.
    await _refreshOrders();
  }

  // ============================================================
  // ORDER CARD
  // ============================================================

  Widget _buildOrderCard(Map<String, dynamic> order) {
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

    final double? total = MoneyFormatter.value(order['total']);

    final String date = DateFormatter.dateTime(
      order['created_at'],
      fallback: '',
    );

    final int extraItems = items.length > 1 ? items.length - 1 : 0;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: () => _openOrderDetails(order),
        child: Ink(
          padding: const EdgeInsets.all(16),
          decoration: BuyerStyle.elevatedCard,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
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
                  StatusChip(status: order['status']?.toString() ?? 'Unknown'),
                ],
              ),

              const SizedBox(height: 15),

              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
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
                            'item${extraItems == 1 ? '' : 's'}',
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
                                child: Text(
                                  date,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: BuyerStyle.small,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 14),

              Row(
                children: [
                  const Expanded(
                    child: Text('Order Total', style: BuyerStyle.small),
                  ),
                  Flexible(
                    child: Text(
                      total == null
                          ? 'Not available'
                          : MoneyFormatter.peso(total),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.end,
                      style: BuyerStyle.largePrice,
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 14),

              const Divider(height: 1, color: BuyerColors.border),

              const SizedBox(height: 12),

              const Row(
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
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // PRODUCT IMAGE
  // ============================================================

  Widget _buildProductImage(String imageUrl) {
    if (imageUrl.isEmpty) {
      return _imagePlaceholder();
    }

    return Image.network(
      imageUrl,
      fit: BoxFit.cover,
      width: double.infinity,
      height: double.infinity,
      errorBuilder:
          (BuildContext context, Object error, StackTrace? stackTrace) {
            debugPrint('Order image failed: $imageUrl');
            return _imagePlaceholder();
          },
      loadingBuilder:
          (BuildContext context, Widget child, ImageChunkEvent? progress) {
            if (progress == null) {
              return child;
            }

            return Center(
              child: SizedBox(
                height: 20,
                width: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: BuyerColors.primary,
                  value: progress.expectedTotalBytes == null
                      ? null
                      : progress.cumulativeBytesLoaded /
                            progress.expectedTotalBytes!,
                ),
              ),
            );
          },
    );
  }

  Widget _imagePlaceholder() {
    return const Center(
      child: Icon(
        Icons.shopping_bag_outlined,
        color: BuyerColors.primary,
        size: 29,
      ),
    );
  }

  // ============================================================
  // IMAGE URL EXTRACTION
  // ============================================================

  String _extractProductImageUrl(Map<String, dynamic>? item) {
    if (item == null) return '';

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

  // ============================================================
  // HELPERS
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
