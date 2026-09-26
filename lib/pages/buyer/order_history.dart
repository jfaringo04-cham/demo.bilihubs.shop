import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';
import 'order_details.dart';

class OrderHistory extends StatelessWidget {
  const OrderHistory({super.key});

  final List<Map<String, dynamic>> orders = const [
    {
      "id": "ORD-001",
      "product": "Wireless Earbuds",
      "price": 899,
      "status": "Delivered",

      "date": "Sep 20, 2026",
      "dateOrdered": "September 20, 2026",
      "arrivalDate": "September 23, 2026",

      "quantity": 1,

      "shop": "TechZone PH",

      "rider": "Mark Santos",
    },

    {
      "id": "ORD-002",
      "product": "Smart Watch",
      "price": 1599,
      "status": "Processing",

      "date": "Sep 24, 2026",
      "dateOrdered": "September 24, 2026",
      "arrivalDate": "Estimated September 28, 2026",

      "quantity": 1,

      "shop": "Smart Gear Store",

      "rider": "Not yet assigned",
    },
  ];

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

      body: SafeArea(
        top: false,

        child: orders.isEmpty
            ? _buildEmptyState()
            : ListView.separated(
                padding: const EdgeInsets.fromLTRB(16, 18, 16, 28),

                itemCount: orders.length,

                separatorBuilder: (_, __) => const SizedBox(height: 14),

                itemBuilder: (context, index) {
                  final order = orders[index];

                  return _buildOrderCard(context, order);
                },
              ),
      ),
    );
  }

  // ------------------------------------------------------------
  // OPEN ORDER DETAILS
  // ------------------------------------------------------------

  void _openOrderDetails(BuildContext context, Map<String, dynamic> order) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => OrderDetails(order: order)),
    );
  }

  // ------------------------------------------------------------
  // ORDER CARD
  // ------------------------------------------------------------

  Widget _buildOrderCard(BuildContext context, Map<String, dynamic> order) {
    final status = order["status"]?.toString() ?? "Unknown";

    return Material(
      color: Colors.transparent,

      child: InkWell(
        borderRadius: BorderRadius.circular(20),

        // ------------------------------------------------------
        // WHOLE CARD IS CLICKABLE
        // ------------------------------------------------------
        onTap: () {
          _openOrderDetails(context, order);
        },

        child: Ink(
          padding: const EdgeInsets.all(16),

          decoration: BuyerStyle.elevatedCard,

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              // --------------------------------------------------
              // ORDER ID + STATUS
              // --------------------------------------------------

              Row(
                crossAxisAlignment: CrossAxisAlignment.center,

                children: [
                  Expanded(
                    child: Text(
                      order["id"]?.toString() ?? "Order",

                      style: BuyerStyle.cardTitle,
                    ),
                  ),

                  _buildStatusBadge(status),
                ],
              ),

              const SizedBox(height: 15),

              // --------------------------------------------------
              // PRODUCT
              // --------------------------------------------------
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,

                children: [
                  Container(
                    height: 60,
                    width: 60,

                    decoration: BuyerStyle.iconContainer(),

                    child: const Icon(
                      Icons.shopping_bag_outlined,
                      color: BuyerColors.primary,
                      size: 29,
                    ),
                  ),

                  const SizedBox(width: 14),

                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,

                      children: [
                        Text(
                          order["product"]?.toString() ?? "Product",

                          maxLines: 2,

                          overflow: TextOverflow.ellipsis,

                          style: BuyerStyle.productTitle,
                        ),

                        const SizedBox(height: 6),

                        Text(
                          order["shop"]?.toString() ?? "Shop",

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
                          "Qty: ${order["quantity"] ?? 1}",
                          style: BuyerStyle.small,
                        ),

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
                                order["date"]?.toString() ?? "",

                                style: BuyerStyle.small,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(width: 10),

                  Text("₱${order["price"] ?? 0}", style: BuyerStyle.largePrice),
                ],
              ),

              const SizedBox(height: 16),

              const Divider(height: 1, color: BuyerColors.border),

              const SizedBox(height: 6),

              // --------------------------------------------------
              // VIEW DETAILS BUTTON
              // --------------------------------------------------
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

  // ------------------------------------------------------------
  // STATUS BADGE
  // ------------------------------------------------------------

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
        backgroundColor = BuyerColors.processingBackground;

        textColor = BuyerColors.processingText;

        icon = Icons.schedule_rounded;

        break;

      case "shipped":
        backgroundColor = BuyerColors.shippedBackground;

        textColor = BuyerColors.shippedText;

        icon = Icons.local_shipping_outlined;

        break;

      case "cancelled":
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

  // ------------------------------------------------------------
  // EMPTY STATE
  // ------------------------------------------------------------

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
