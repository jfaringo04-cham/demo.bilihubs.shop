import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';

import 'order_details.dart';

class OrderHistory extends StatefulWidget {
  const OrderHistory({super.key});

  @override
  State<OrderHistory> createState() => _OrderHistoryState();
}

class _OrderHistoryState extends State<OrderHistory> {
  final List<Map<String, dynamic>> orders = [];

  bool isLoading = false;
  String? errorMessage;

  @override
  void initState() {
    super.initState();

    // Kapag meron na tayong Laravel orders API,
    // dito natin tatawagin ang load orders.
    //
    // Example:
    // _loadOrders();
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

        leading: IconButton(
          tooltip: "Back",
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: BuyerColors.textDark,
            size: 20,
          ),
          onPressed: () {
            Navigator.pop(context);
          },
        ),

        title: const Text("Order History", style: BuyerStyle.appBarTitle),
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
      onRefresh: () async {
        // Kapag connected na ang orders API,
        // dito natin ire-refresh.
      },
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 28),
        itemCount: orders.length,
        separatorBuilder: (_, __) {
          return const SizedBox(height: 14);
        },
        itemBuilder: (context, index) {
          final order = orders[index];

          return _buildOrderCard(context, order);
        },
      ),
    );
  }

  // ================================================================
  // OPEN ORDER DETAILS
  // ================================================================

  void _openOrderDetails(BuildContext context, Map<String, dynamic> order) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => OrderDetails(order: order)),
    );
  }

  // ================================================================
  // ORDER CARD
  // ================================================================

  Widget _buildOrderCard(BuildContext context, Map<String, dynamic> order) {
    final status = order["status"]?.toString() ?? "Unknown";

    final productName =
        order["product"]?.toString() ??
        order["product_name"]?.toString() ??
        "Product";

    final shopName =
        order["shop"]?.toString() ??
        order["seller_name"]?.toString() ??
        "Seller";

    final quantity = int.tryParse((order["quantity"] ?? 1).toString()) ?? 1;

    final price = double.tryParse((order["price"] ?? 0).toString()) ?? 0;

    final date =
        order["date"]?.toString() ?? order["created_at"]?.toString() ?? "";

    final imageUrl =
        order["image"]?.toString() ?? order["image_url"]?.toString() ?? "";

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
              // ========================================================
              // ORDER ID + STATUS
              // ========================================================

              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Expanded(
                    child: Text(
                      order["id"]?.toString() ??
                          order["order_number"]?.toString() ??
                          "Order",
                      style: BuyerStyle.cardTitle,
                    ),
                  ),

                  _buildStatusBadge(status),
                ],
              ),

              const SizedBox(height: 15),

              // ========================================================
              // PRODUCT
              // ========================================================
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
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
                              errorBuilder: (context, error, stackTrace) {
                                return const Icon(
                                  Icons.broken_image_outlined,
                                  color: BuyerColors.primary,
                                  size: 29,
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

                        Text("Qty: $quantity", style: BuyerStyle.small),

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
                    "₱${price.toStringAsFixed(2)}",
                    style: BuyerStyle.largePrice,
                  ),
                ],
              ),

              const SizedBox(height: 16),

              const Divider(height: 1, color: BuyerColors.border),

              const SizedBox(height: 6),

              // ========================================================
              // VIEW DETAILS
              // ========================================================
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
                          "View order details",
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
  // STATUS BADGE
  // ================================================================

  Widget _buildStatusBadge(String status) {
    Color backgroundColor;
    Color textColor;
    IconData icon;

    switch (status.toLowerCase()) {
      case "delivered":
        backgroundColor = BuyerColors.deliveredBackground;

        textColor = BuyerColors.deliveredText;

        icon = Icons.check_circle_outline_rounded;

        break;

      case "processing":
      case "pending":
        backgroundColor = BuyerColors.processingBackground;

        textColor = BuyerColors.processingText;

        icon = Icons.schedule_rounded;

        break;

      case "shipped":
      case "out for delivery":
        backgroundColor = BuyerColors.shippedBackground;

        textColor = BuyerColors.shippedText;

        icon = Icons.local_shipping_outlined;

        break;

      case "cancelled":
      case "canceled":
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
    return Center(
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
              "Unable to load orders",
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: BuyerColors.textDark,
              ),
            ),

            const SizedBox(height: 8),

            Text(
              errorMessage ?? "Something went wrong.",
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 14,
                height: 1.5,
                color: BuyerColors.textGrey,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ================================================================
  // EMPTY STATE
  // ================================================================

  Widget _buildEmptyState() {
    return Center(
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
              "No orders yet",
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: BuyerColors.textDark,
              ),
            ),

            const SizedBox(height: 8),

            const Text(
              "Your current and completed orders will appear here.",
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14,
                height: 1.5,
                color: BuyerColors.textGrey,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
