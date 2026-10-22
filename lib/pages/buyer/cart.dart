import 'package:flutter/material.dart';

import '../../sevices/cart_service.dart';
import '../../styles/buyer_style.dart';

import 'checkout.dart';

class CartPage extends StatefulWidget {
  const CartPage({super.key});

  @override
  State<CartPage> createState() => _CartPageState();
}

class _CartPageState extends State<CartPage> {
  List<CartItem> get cartItems => CartService.items;

  double get total => CartService.total;

  void _increaseQuantity(int index) {
    final item = cartItems[index];

    if (item.quantity >= item.product.stock) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text("Maximum available stock reached."),
            behavior: SnackBarBehavior.floating,
          ),
        );

      return;
    }

    setState(() {
      CartService.increaseQuantity(index);
    });
  }

  void _decreaseQuantity(int index) {
    setState(() {
      CartService.decreaseQuantity(index);
    });
  }

  void _removeItem(int index) {
    final item = cartItems[index];

    setState(() {
      CartService.removeAt(index);
    });

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          content: Text("${item.product.name} removed from cart."),
        ),
      );
  }

  void _clearCart() {
    if (cartItems.isEmpty) {
      return;
    }

    showDialog(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: const Text("Clear Cart"),
          content: const Text("Remove all products from your cart?"),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);
              },
              child: const Text("Cancel"),
            ),
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);

                setState(() {
                  CartService.clearCart();
                });
              },
              child: const Text(
                "Clear",
                style: TextStyle(color: BuyerColors.danger),
              ),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return ValueListenableBuilder<int>(
      valueListenable: CartService.itemCountNotifier,
      builder: (context, itemCount, child) {
        return Scaffold(
          backgroundColor: BuyerColors.background,

          appBar: AppBar(
            backgroundColor: Colors.white,
            elevation: 0,
            iconTheme: const IconThemeData(color: BuyerColors.textDark),
            title: const Text("My Cart", style: BuyerStyle.title),
            actions: [
              if (cartItems.isNotEmpty)
                IconButton(
                  tooltip: "Clear Cart",
                  onPressed: _clearCart,
                  icon: const Icon(
                    Icons.delete_sweep_outlined,
                    color: BuyerColors.danger,
                  ),
                ),
            ],
          ),

          body: cartItems.isEmpty
              ? _buildEmptyCart()
              : Column(
                  children: [
                    Expanded(
                      child: ListView.builder(
                        padding: EdgeInsets.all(width * 0.04),
                        itemCount: cartItems.length,
                        itemBuilder: (context, index) {
                          final item = cartItems[index];
                          final product = item.product;

                          return Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            padding: const EdgeInsets.all(14),
                            decoration: BuyerStyle.card,
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.center,
                              children: [
                                // PRODUCT IMAGE
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(12),
                                  child: Container(
                                    height: 75,
                                    width: 75,
                                    color: Colors.grey.shade200,
                                    child: product.imageUrl.isEmpty
                                        ? const Icon(
                                            Icons.shopping_bag_outlined,
                                            color: Colors.grey,
                                            size: 30,
                                          )
                                        : Image.network(
                                            product.imageUrl,
                                            fit: BoxFit.cover,
                                            errorBuilder:
                                                (context, error, stackTrace) {
                                                  return const Icon(
                                                    Icons.broken_image_outlined,
                                                    color: Colors.grey,
                                                    size: 30,
                                                  );
                                                },
                                          ),
                                  ),
                                ),

                                const SizedBox(width: 14),

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

                                      const SizedBox(height: 6),

                                      Text(
                                        "₱${product.effectivePrice.toStringAsFixed(2)}",
                                        style: const TextStyle(
                                          color: BuyerColors.primary,
                                          fontSize: 16,
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),

                                      const SizedBox(height: 5),

                                      Text(
                                        product.stock > 0
                                            ? "${product.stock} available"
                                            : "Out of stock",
                                        style: TextStyle(
                                          fontSize: 11,
                                          color: product.stock > 0
                                              ? BuyerColors.textGrey
                                              : BuyerColors.danger,
                                        ),
                                      ),

                                      const SizedBox(height: 10),

                                      // QUANTITY
                                      Row(
                                        children: [
                                          _quantityButton(
                                            icon: Icons.remove,
                                            onTap: () {
                                              _decreaseQuantity(index);
                                            },
                                          ),

                                          Container(
                                            constraints: const BoxConstraints(
                                              minWidth: 42,
                                            ),
                                            alignment: Alignment.center,
                                            child: Text(
                                              item.quantity.toString(),
                                              style: const TextStyle(
                                                fontSize: 14,
                                                fontWeight: FontWeight.w700,
                                                color: BuyerColors.textDark,
                                              ),
                                            ),
                                          ),

                                          _quantityButton(
                                            icon: Icons.add,
                                            onTap: () {
                                              _increaseQuantity(index);
                                            },
                                          ),
                                        ],
                                      ),

                                      const SizedBox(height: 8),

                                      Text(
                                        "Subtotal: ₱${item.subtotal.toStringAsFixed(2)}",
                                        style: const TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.w600,
                                          color: BuyerColors.textDark,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),

                                IconButton(
                                  tooltip: "Remove",
                                  onPressed: () {
                                    _removeItem(index);
                                  },
                                  icon: const Icon(
                                    Icons.delete_outline_rounded,
                                    color: BuyerColors.danger,
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
                    ),

                    // CHECKOUT SUMMARY
                    Container(
                      padding: EdgeInsets.all(width * 0.05),
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.vertical(
                          top: Radius.circular(25),
                        ),
                      ),
                      child: SafeArea(
                        top: false,
                        child: Column(
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text(
                                  "Total Items",
                                  style: BuyerStyle.small,
                                ),
                                Text("$itemCount", style: BuyerStyle.cardTitle),
                              ],
                            ),

                            const SizedBox(height: 10),

                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text(
                                  "Total",
                                  style: BuyerStyle.sectionTitle,
                                ),
                                Text(
                                  "₱${total.toStringAsFixed(2)}",
                                  style: const TextStyle(
                                    color: BuyerColors.primary,
                                    fontSize: 20,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ],
                            ),

                            const SizedBox(height: 15),

                            SizedBox(
                              width: double.infinity,
                              height: 55,
                              child: ElevatedButton(
                                style: BuyerStyle.primaryButton,
                                onPressed: cartItems.isEmpty
                                    ? null
                                    : () {
                                        Navigator.push(
                                          context,
                                          MaterialPageRoute(
                                            builder: (context) => Checkout(
                                              items: CartService.items,
                                              clearLocalCartOnSuccess: true,
                                            ),
                                          ),
                                        );
                                      },
                                child: const Text("Checkout"),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
        );
      },
    );
  }

  Widget _quantityButton({
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          height: 32,
          width: 32,
          decoration: BoxDecoration(
            color: BuyerColors.lightPurple,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: BuyerColors.border),
          ),
          alignment: Alignment.center,
          child: Icon(icon, size: 17, color: BuyerColors.primary),
        ),
      ),
    );
  }

  Widget _buildEmptyCart() {
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
                Icons.shopping_cart_outlined,
                color: BuyerColors.primary,
                size: 45,
              ),
            ),

            const SizedBox(height: 20),

            const Text("Your cart is empty", style: BuyerStyle.sectionTitle),

            const SizedBox(height: 8),

            const Text(
              "Products you add to your cart will appear here.",
              textAlign: TextAlign.center,
              style: BuyerStyle.small,
            ),
          ],
        ),
      ),
    );
  }
}
