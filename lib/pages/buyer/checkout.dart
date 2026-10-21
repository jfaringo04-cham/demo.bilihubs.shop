import 'package:flutter/material.dart';

import '../../sevices/auth_storage.dart';
import '../../sevices/cart_service.dart';
import '../../styles/buyer_style.dart';

class Checkout extends StatefulWidget {
  final List<CartItem> items;

  const Checkout({super.key, required this.items});

  @override
  State<Checkout> createState() => _CheckoutState();
}

class _CheckoutState extends State<Checkout> {
  String buyerName = '';
  String buyerPhone = '';
  String buyerAddress = '';
  String paymentMethod = 'Cash on Delivery';

  bool isLoadingBuyer = true;

  @override
  void initState() {
    super.initState();
    _loadBuyerInformation();
  }

  Future<void> _loadBuyerInformation() async {
    final user = await AuthStorage.getUser();

    if (!mounted) return;

    if (user != null) {
      setState(() {
        buyerName = user['name']?.toString() ?? '';

        buyerPhone =
            user['phone']?.toString() ??
            user['mobile_number']?.toString() ??
            '';

        buyerAddress = user['address']?.toString().trim() ?? '';

        paymentMethod =
            user['payment_method']?.toString().trim().isNotEmpty == true
            ? user['payment_method'].toString()
            : 'Cash on Delivery';

        isLoadingBuyer = false;
      });
    } else {
      setState(() {
        isLoadingBuyer = false;
      });
    }
  }

  double get total {
    double sum = 0;

    for (final item in widget.items) {
      sum += item.subtotal;
    }

    return sum;
  }

  int get totalItems {
    int count = 0;

    for (final item in widget.items) {
      count += item.quantity;
    }

    return count;
  }

  bool get hasAddress {
    return buyerAddress.trim().isNotEmpty;
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: BuyerColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        iconTheme: const IconThemeData(color: BuyerColors.textDark),
        title: const Text("Checkout", style: BuyerStyle.title),
      ),

      body: widget.items.isEmpty
          ? _buildEmptyCheckout(context)
          : SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              padding: EdgeInsets.symmetric(
                horizontal: width * 0.045,
                vertical: 16,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // ============================================================
                  // ORDER SUMMARY
                  // ============================================================

                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(18),
                    decoration: BuyerStyle.card,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          "Order Summary",
                          style: BuyerStyle.sectionTitle,
                        ),

                        const SizedBox(height: 5),

                        Text(
                          "$totalItems item${totalItems > 1 ? 's' : ''}",
                          style: BuyerStyle.small,
                        ),

                        const SizedBox(height: 18),

                        ListView.separated(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: widget.items.length,
                          separatorBuilder: (context, index) {
                            return const Padding(
                              padding: EdgeInsets.symmetric(vertical: 12),
                              child: Divider(
                                height: 1,
                                color: BuyerColors.border,
                              ),
                            );
                          },
                          itemBuilder: (context, index) {
                            final item = widget.items[index];

                            final product = item.product;

                            return Row(
                              crossAxisAlignment: CrossAxisAlignment.center,
                              children: [
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(12),
                                  child: Container(
                                    width: 65,
                                    height: 65,
                                    color: BuyerColors.lightPurple,
                                    child: product.imageUrl.isEmpty
                                        ? const Icon(
                                            Icons.shopping_bag_outlined,
                                            color: BuyerColors.primary,
                                            size: 28,
                                          )
                                        : Image.network(
                                            product.imageUrl,
                                            width: 65,
                                            height: 65,
                                            fit: BoxFit.cover,
                                            errorBuilder:
                                                (context, error, stackTrace) {
                                                  return const Icon(
                                                    Icons.broken_image_outlined,
                                                    color: BuyerColors.primary,
                                                    size: 28,
                                                  );
                                                },
                                          ),
                                  ),
                                ),

                                const SizedBox(width: 13),

                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        product.name.isEmpty
                                            ? "Unnamed Product"
                                            : product.name,
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: BuyerStyle.cardTitle,
                                      ),

                                      const SizedBox(height: 5),

                                      Text(
                                        "₱${product.price.toStringAsFixed(2)} each",
                                        style: BuyerStyle.small,
                                      ),

                                      const SizedBox(height: 4),

                                      Text(
                                        "Qty: ${item.quantity}",
                                        style: BuyerStyle.small,
                                      ),
                                    ],
                                  ),
                                ),

                                const SizedBox(width: 10),

                                Text(
                                  "₱${item.subtotal.toStringAsFixed(2)}",
                                  style: const TextStyle(
                                    color: BuyerColors.primary,
                                    fontSize: 14,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ],
                            );
                          },
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // ============================================================
                  // DELIVERY ADDRESS
                  // ============================================================
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(18),
                    decoration: BuyerStyle.card,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Row(
                          children: [
                            Icon(
                              Icons.location_on_outlined,
                              color: BuyerColors.primary,
                              size: 22,
                            ),

                            SizedBox(width: 9),

                            Text(
                              "Delivery Address",
                              style: BuyerStyle.sectionTitle,
                            ),
                          ],
                        ),

                        const SizedBox(height: 15),

                        if (isLoadingBuyer)
                          const Center(
                            child: CircularProgressIndicator(
                              color: BuyerColors.primary,
                              strokeWidth: 2,
                            ),
                          )
                        else if (!hasAddress)
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: BuyerColors.lightPurple,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: BuyerColors.border),
                            ),
                            child: const Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Icon(
                                  Icons.warning_amber_rounded,
                                  color: BuyerColors.danger,
                                  size: 21,
                                ),

                                SizedBox(width: 10),

                                Expanded(
                                  child: Text(
                                    "No delivery address saved. "
                                    "Please add your address in your profile first.",
                                    style: TextStyle(
                                      color: BuyerColors.textDark,
                                      fontSize: 13,
                                      height: 1.4,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          )
                        else
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: BuyerColors.lightPurple,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: BuyerColors.border),
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Icon(
                                  Icons.home_outlined,
                                  color: BuyerColors.primary,
                                  size: 21,
                                ),

                                const SizedBox(width: 10),

                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      if (buyerName.isNotEmpty)
                                        Text(
                                          buyerName,
                                          style: const TextStyle(
                                            color: BuyerColors.textDark,
                                            fontSize: 13,
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),

                                      if (buyerName.isNotEmpty)
                                        const SizedBox(height: 4),

                                      Text(
                                        buyerAddress,
                                        style: const TextStyle(
                                          color: BuyerColors.textDark,
                                          fontSize: 13,
                                          height: 1.4,
                                        ),
                                      ),

                                      if (buyerPhone.isNotEmpty) ...[
                                        const SizedBox(height: 5),

                                        Text(
                                          buyerPhone,
                                          style: BuyerStyle.small,
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // ============================================================
                  // PAYMENT METHOD
                  // ============================================================
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(18),
                    decoration: BuyerStyle.card,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Row(
                          children: [
                            Icon(
                              Icons.payments_outlined,
                              color: BuyerColors.primary,
                              size: 22,
                            ),

                            SizedBox(width: 9),

                            Text(
                              "Payment Method",
                              style: BuyerStyle.sectionTitle,
                            ),
                          ],
                        ),

                        const SizedBox(height: 15),

                        Row(
                          children: [
                            const Icon(
                              Icons.money_outlined,
                              color: BuyerColors.textGrey,
                              size: 22,
                            ),

                            const SizedBox(width: 10),

                            Expanded(
                              child: Text(
                                paymentMethod,
                                style: BuyerStyle.cardTitle,
                              ),
                            ),

                            const Icon(
                              Icons.check_circle,
                              color: BuyerColors.success,
                              size: 21,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // ============================================================
                  // PAYMENT SUMMARY
                  // ============================================================
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(18),
                    decoration: BuyerStyle.card,
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text("Total Items", style: BuyerStyle.small),

                            Text("$totalItems", style: BuyerStyle.cardTitle),
                          ],
                        ),

                        const SizedBox(height: 12),

                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text("Subtotal", style: BuyerStyle.small),

                            Text(
                              "₱${total.toStringAsFixed(2)}",
                              style: BuyerStyle.cardTitle,
                            ),
                          ],
                        ),

                        const SizedBox(height: 12),

                        const Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text("Delivery Fee", style: BuyerStyle.small),

                            Text("To be calculated", style: BuyerStyle.small),
                          ],
                        ),

                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 15),
                          child: Divider(height: 1, color: BuyerColors.border),
                        ),

                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text("Total", style: BuyerStyle.sectionTitle),

                            Text(
                              "₱${total.toStringAsFixed(2)}",
                              style: const TextStyle(
                                color: BuyerColors.primary,
                                fontSize: 21,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 25),

                  // ============================================================
                  // PLACE ORDER
                  // ============================================================
                  SizedBox(
                    width: double.infinity,
                    height: 55,
                    child: ElevatedButton.icon(
                      style: BuyerStyle.primaryButton,

                      onPressed: !hasAddress || isLoadingBuyer
                          ? null
                          : () {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  behavior: SnackBarBehavior.floating,
                                  backgroundColor: BuyerColors.primaryDark,
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  content: const Row(
                                    children: [
                                      Icon(
                                        Icons.info_outline,
                                        color: Colors.white,
                                      ),

                                      SizedBox(width: 10),

                                      Expanded(
                                        child: Text(
                                          "Order API is not connected yet.",
                                          style: TextStyle(
                                            color: Colors.white,
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              );
                            },

                      icon: const Icon(Icons.shopping_bag_outlined, size: 20),

                      label: const Text("Place Order"),
                    ),
                  ),

                  const SizedBox(height: 20),
                ],
              ),
            ),
    );
  }

  Widget _buildEmptyCheckout(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(30),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              height: 90,
              width: 90,
              decoration: BoxDecoration(
                color: BuyerColors.lightPurple,
                borderRadius: BorderRadius.circular(25),
              ),
              child: const Icon(
                Icons.shopping_bag_outlined,
                color: BuyerColors.primary,
                size: 45,
              ),
            ),

            const SizedBox(height: 20),

            const Text("Nothing to checkout", style: BuyerStyle.sectionTitle),

            const SizedBox(height: 8),

            const Text(
              "Add a product to your cart before checking out.",
              textAlign: TextAlign.center,
              style: BuyerStyle.small,
            ),

            const SizedBox(height: 20),

            ElevatedButton(
              style: BuyerStyle.primaryButton,
              onPressed: () {
                Navigator.pop(context);
              },
              child: const Text("Back to Cart"),
            ),
          ],
        ),
      ),
    );
  }
}
