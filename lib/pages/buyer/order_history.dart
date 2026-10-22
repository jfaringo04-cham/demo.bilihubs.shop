import 'package:flutter/material.dart';

import '../../sevices/api_services.dart';
import '../../styles/buyer_style.dart';
import 'order_details.dart';

class OrderHistory extends StatefulWidget {
  const OrderHistory({super.key});

  @override
  State<OrderHistory> createState() => _OrderHistoryState();
}

class _OrderHistoryState extends State<OrderHistory> {
  List<Map<String, dynamic>> orders = [];
  bool isLoading = true;
  String? errorMessage;

  @override
  void initState() {
    super.initState();
    _loadOrders();
  }

  Future<void> _loadOrders() async {
    if (mounted) {
      setState(() {
        isLoading = true;
        errorMessage = null;
      });
    }

    final result = await ApiService.getOrders();
    if (!mounted) return;

    if (result['success'] == true) {
      final data = result['data'];
      final parsed = <Map<String, dynamic>>[];
      if (data is List) {
        for (final item in data) {
          if (item is Map) parsed.add(Map<String, dynamic>.from(item));
        }
      }

      setState(() {
        orders = parsed;
        isLoading = false;
      });
      return;
    }

    setState(() {
      isLoading = false;
      errorMessage = result['message']?.toString() ?? 'Unable to load orders.';
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        title: const Text('Order History', style: BuyerStyle.appBarTitle),
      ),
      body: SafeArea(top: false, child: _buildBody()),
    );
  }

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
      onRefresh: _loadOrders,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 28),
        itemCount: orders.length,
        separatorBuilder: (_, __) => const SizedBox(height: 14),
        itemBuilder: (context, index) => _buildOrderCard(orders[index]),
      ),
    );
  }

  Widget _buildOrderCard(Map<String, dynamic> order) {
    final status = order['delivery_status']?.toString().trim().isNotEmpty == true
        ? order['delivery_status'].toString()
        : order['status']?.toString() ?? 'Unknown';

    final items = _mapList(order['items']);
    final firstItem = items.isNotEmpty ? items.first : <String, dynamic>{};
    final sellerOrders = _mapList(order['seller_orders']);
    final firstSellerOrder =
        sellerOrders.isNotEmpty ? sellerOrders.first : <String, dynamic>{};
    final seller = _map(firstSellerOrder['seller']);

    final productName =
        firstItem['product_name']?.toString() ?? 'Order items';
    final shopName = seller['name']?.toString() ?? 'BiliHub Seller';
    final quantity =
        int.tryParse(firstItem['quantity']?.toString() ?? '0') ?? 0;
    final total = double.tryParse(order['total']?.toString() ?? '0') ?? 0;
    final imageUrl = firstItem['product_image']?.toString() ?? '';
    final createdAt = order['created_at']?.toString() ?? '';
    final orderNumber =
        order['order_number']?.toString() ?? 'Order #${order['id'] ?? ''}';

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: () async {
          final id = int.tryParse(order['id']?.toString() ?? '');
          Map<String, dynamic> detail = order;

          if (id != null) {
            final result = await ApiService.getOrder(id);
            if (!mounted) return;
            if (result['success'] == true && result['data'] is Map) {
              detail = Map<String, dynamic>.from(result['data'] as Map);
            }
          }

          if (!mounted) return;
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => OrderDetails(order: detail)),
          );
        },
        child: Ink(
          padding: const EdgeInsets.all(16),
          decoration: BuyerStyle.elevatedCard,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(orderNumber, style: BuyerStyle.cardTitle),
                  ),
                  _buildStatusBadge(status),
                ],
              ),
              const SizedBox(height: 15),
              Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(14),
                    child: Container(
                      height: 60,
                      width: 60,
                      color: BuyerColors.lightPurple,
                      child: imageUrl.isEmpty
                          ? const Icon(
                              Icons.shopping_bag_outlined,
                              color: BuyerColors.primary,
                              size: 29,
                            )
                          : Image.network(
                              imageUrl,
                              fit: BoxFit.cover,
                              errorBuilder: (_, __, ___) => const Icon(
                                Icons.broken_image_outlined,
                                color: BuyerColors.primary,
                                size: 29,
                              ),
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
                        const SizedBox(height: 5),
                        Text(shopName, style: BuyerStyle.small),
                        if (quantity > 0) ...[
                          const SizedBox(height: 4),
                          Text('Qty: $quantity', style: BuyerStyle.small),
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
              if (createdAt.isNotEmpty) ...[
                const SizedBox(height: 12),
                Text(_friendlyDate(createdAt), style: BuyerStyle.small),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatusBadge(String status) {
    final clean = status.replaceAll('_', ' ').trim();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: BuyerColors.lightPurple,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        clean.isEmpty ? 'Unknown' : _titleCase(clean),
        style: const TextStyle(
          color: BuyerColors.primary,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _buildErrorState() {
    return RefreshIndicator(
      color: BuyerColors.primary,
      onRefresh: _loadOrders,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          SizedBox(height: MediaQuery.of(context).size.height * 0.18),
          const Icon(
            Icons.cloud_off_outlined,
            size: 56,
            color: BuyerColors.textGrey,
          ),
          const SizedBox(height: 14),
          Text(
            errorMessage ?? 'Unable to load orders.',
            textAlign: TextAlign.center,
            style: BuyerStyle.body,
          ),
          const SizedBox(height: 16),
          Center(
            child: ElevatedButton(
              style: BuyerStyle.primaryButton,
              onPressed: _loadOrders,
              child: const Text('Try Again'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState() {
    return RefreshIndicator(
      color: BuyerColors.primary,
      onRefresh: _loadOrders,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          SizedBox(height: MediaQuery.of(context).size.height * 0.18),
          const Icon(
            Icons.receipt_long_outlined,
            size: 62,
            color: BuyerColors.textGrey,
          ),
          const SizedBox(height: 14),
          const Text(
            'No orders yet',
            textAlign: TextAlign.center,
            style: BuyerStyle.sectionTitle,
          ),
          const SizedBox(height: 7),
          const Text(
            'Your BiliHub orders will appear here.',
            textAlign: TextAlign.center,
            style: BuyerStyle.small,
          ),
        ],
      ),
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
    return value
        .split(RegExp(r'\s+'))
        .where((word) => word.isNotEmpty)
        .map((word) => '${word[0].toUpperCase()}${word.substring(1)}')
        .join(' ');
  }

  String _friendlyDate(String value) {
    final date = DateTime.tryParse(value)?.toLocal();
    if (date == null) return value;
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }
}
