import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';

class OrderDetails extends StatelessWidget {
  final Map<String, dynamic> order;

  const OrderDetails({super.key, required this.order});

  @override
  Widget build(BuildContext context) {
    final status = order["status"]?.toString() ?? "Unknown";

    return Scaffold(
      backgroundColor: BuyerColors.background,

      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,

        leading: IconButton(
          tooltip: "Back",
          onPressed: () {
            Navigator.pop(context);
          },
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: BuyerColors.textDark,
            size: 20,
          ),
        ),

        title: const Text("Order Details", style: BuyerStyle.appBarTitle),
      ),

      body: SafeArea(
        top: false,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              // ------------------------------------------------------------
              // ORDER HEADER
              // ------------------------------------------------------------

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
                                "Order Number",
                                style: BuyerStyle.small,
                              ),

                              const SizedBox(height: 4),

                              Text(
                                order["id"]?.toString() ?? "Order",
                                style: BuyerStyle.cardTitle,
                              ),
                            ],
                          ),
                        ),

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

              // ------------------------------------------------------------
              // PRODUCT INFORMATION
              // ------------------------------------------------------------
              const Text("Product Information", style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              Container(
                padding: const EdgeInsets.all(16),
                decoration: BuyerStyle.card,

                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.center,

                  children: [
                    Container(
                      height: 68,
                      width: 68,

                      decoration: BuyerStyle.iconContainer(),

                      child: const Icon(
                        Icons.shopping_bag_outlined,
                        color: BuyerColors.primary,
                        size: 31,
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

                          Row(
                            children: [
                              const Icon(
                                Icons.storefront_outlined,
                                size: 14,
                                color: BuyerColors.textGrey,
                              ),

                              const SizedBox(width: 5),

                              Expanded(
                                child: Text(
                                  order["shop"]?.toString() ??
                                      "Shop not available",
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: BuyerStyle.body,
                                ),
                              ),
                            ],
                          ),

                          const SizedBox(height: 5),

                          Text(
                            "Quantity: ${order["quantity"] ?? 1}",
                            style: BuyerStyle.small,
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(width: 10),

                    Text(
                      "₱${order["price"] ?? 0}",
                      style: BuyerStyle.largePrice,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // ------------------------------------------------------------
              // DELIVERY INFORMATION
              // ------------------------------------------------------------
              const Text(
                "Delivery Information",
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
                      title: "Date Ordered",
                      value:
                          order["dateOrdered"]?.toString() ??
                          order["date"]?.toString() ??
                          "Not available",
                    ),

                    const Divider(height: 30, color: BuyerColors.border),

                    _detailRow(
                      icon: Icons.event_available_outlined,
                      title: _arrivalTitle(status),
                      value:
                          order["arrivalDate"]?.toString() ?? "Not available",
                    ),

                    const Divider(height: 30, color: BuyerColors.border),

                    _detailRow(
                      icon: Icons.delivery_dining_rounded,
                      title: "Rider",
                      value: order["rider"]?.toString() ?? "Rider not assigned",
                    ),

                    const Divider(height: 30, color: BuyerColors.border),

                    _detailRow(
                      icon: Icons.storefront_outlined,
                      title: "Shop",
                      value: order["shop"]?.toString() ?? "Not available",
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // ------------------------------------------------------------
              // ORDER SUMMARY
              // ------------------------------------------------------------
              const Text("Order Summary", style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              Container(
                padding: const EdgeInsets.all(16),
                decoration: BuyerStyle.card,

                child: Column(
                  children: [
                    _summaryRow("Item subtotal", "₱${order["price"] ?? 0}"),

                    const SizedBox(height: 12),

                    _summaryRow("Quantity", "${order["quantity"] ?? 1}"),

                    const SizedBox(height: 14),

                    const Divider(height: 1, color: BuyerColors.border),

                    const SizedBox(height: 14),

                    _summaryRow(
                      "Total",
                      "₱${order["price"] ?? 0}",
                      isTotal: true,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // ------------------------------------------------------------
              // SUPPORT NOTE
              // ------------------------------------------------------------
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
                        "If there is an issue with this order, you can contact the shop or customer support once this feature is connected.",
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

  // ------------------------------------------------------------
  // DETAIL ROW
  // ------------------------------------------------------------

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

  // ------------------------------------------------------------
  // SUMMARY ROW
  // ------------------------------------------------------------

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

  // ------------------------------------------------------------
  // STATUS BADGE
  // ------------------------------------------------------------

  Widget _buildStatusBadge(String status) {
    final normalizedStatus = status.toLowerCase();

    Color backgroundColor;
    Color textColor;
    IconData icon;

    switch (normalizedStatus) {
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

  // ------------------------------------------------------------
  // STATUS MESSAGE
  // ------------------------------------------------------------

  String _statusMessage(String status) {
    switch (status.toLowerCase()) {
      case "delivered":
        return "This order has been delivered successfully.";

      case "shipped":
        return "Your order is on the way to your delivery address.";

      case "processing":
        return "The shop is currently preparing your order.";

      case "cancelled":
        return "This order has been cancelled.";

      default:
        return "Check this page for the latest information about your order.";
    }
  }

  // ------------------------------------------------------------
  // ARRIVAL LABEL
  // ------------------------------------------------------------

  String _arrivalTitle(String status) {
    if (status.toLowerCase() == "delivered") {
      return "Date Arrived";
    }

    return "Expected Arrival";
  }
}
