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

  final Set<CartItem> _selectedItems = <CartItem>{};

  List<CartItem> get selectedItems {
    return cartItems
        .where((CartItem item) => _selectedItems.contains(item))
        .toList();
  }

  bool get _allSelected {
    return cartItems.isNotEmpty && _selectedItems.length == cartItems.length;
  }

  int get _selectedQuantity {
    return selectedItems.fold<int>(0, (int total, CartItem item) {
      return total + item.quantity;
    });
  }

  double get _selectedTotal {
    return selectedItems.fold<double>(0, (double total, CartItem item) {
      return total + item.subtotal;
    });
  }

  // ================================================================
  // SELECTION
  // ================================================================

  void _toggleItemSelection(CartItem item, bool? selected) {
    setState(() {
      if (selected == true) {
        _selectedItems.add(item);
      } else {
        _selectedItems.remove(item);
      }
    });
  }

  void _toggleSelectAll(bool? selected) {
    setState(() {
      if (selected == true) {
        _selectedItems
          ..clear()
          ..addAll(cartItems);
      } else {
        _selectedItems.clear();
      }
    });
  }

  // ================================================================
  // QUANTITY
  // ================================================================

  void _increaseQuantity(int index) {
    final CartItem item = cartItems[index];

    if (item.quantity >= item.product.stock) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text('Maximum available stock reached.'),
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

  // ================================================================
  // REMOVE ITEM
  // ================================================================

  void _removeItem(int index) {
    final CartItem item = cartItems[index];

    setState(() {
      _selectedItems.remove(item);
      CartService.removeAt(index);
    });

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          content: Text('${item.product.name} removed from cart.'),
        ),
      );
  }

  // ================================================================
  // CLEAR CART
  // ================================================================

  void _clearCart() {
    if (cartItems.isEmpty) {
      return;
    }

    showDialog<void>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          title: const Text('Clear Cart'),
          content: const Text('Remove all products from your cart?'),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);
              },
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);

                setState(() {
                  _selectedItems.clear();
                  CartService.clearCart();
                });
              },
              child: const Text(
                'Clear',
                style: TextStyle(color: BuyerColors.danger),
              ),
            ),
          ],
        );
      },
    );
  }

  // ================================================================
  // CHECKOUT
  // ================================================================

  void _checkoutSelectedItems() {
    final List<CartItem> items = List<CartItem>.from(selectedItems);

    if (items.isEmpty) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            behavior: SnackBarBehavior.floating,
            content: Text('Please select at least one product to checkout.'),
          ),
        );

      return;
    }

    Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return Checkout(items: items);
        },
      ),
    );
  }

  // ================================================================
  // BUILD
  // ================================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.of(context).size.width;

    return ValueListenableBuilder<int>(
      valueListenable: CartService.itemCountNotifier,
      builder: (BuildContext context, int itemCount, Widget? child) {
        _selectedItems.removeWhere(
          (CartItem item) => !cartItems.contains(item),
        );

        return Scaffold(
          backgroundColor: BuyerColors.background,

          // ============================================================
          // APP BAR
          // ============================================================
          appBar: AppBar(
            backgroundColor: Colors.white,
            elevation: 0,
            scrolledUnderElevation: 0,
            automaticallyImplyLeading: false,
            iconTheme: const IconThemeData(color: BuyerColors.textDark),
            title: const Text('My Cart', style: BuyerStyle.title),
            actions: [
              if (cartItems.isNotEmpty)
                IconButton(
                  tooltip: 'Clear Cart',
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
                    // ====================================================
                    // SELECT ALL
                    // ====================================================

                    Container(
                      width: double.infinity,
                      color: BuyerColors.white,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 4,
                      ),
                      child: Row(
                        children: [
                          Checkbox(
                            value: _allSelected,
                            activeColor: BuyerColors.primary,
                            onChanged: _toggleSelectAll,
                          ),
                          const Text(
                            'Select All',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              color: BuyerColors.textDark,
                            ),
                          ),
                          const Spacer(),
                          Text(
                            '${_selectedItems.length} selected',
                            style: BuyerStyle.small,
                          ),
                          const SizedBox(width: 8),
                        ],
                      ),
                    ),

                    const Divider(height: 1, color: BuyerColors.border),

                    // ====================================================
                    // CART ITEMS
                    // ====================================================
                    Expanded(
                      child: ListView.builder(
                        padding: EdgeInsets.all(width * 0.04),
                        itemCount: cartItems.length,
                        itemBuilder: (BuildContext context, int index) {
                          final CartItem item = cartItems[index];

                          final product = item.product;

                          final bool isSelected = _selectedItems.contains(item);

                          return Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            padding: const EdgeInsets.all(12),
                            decoration: BuyerStyle.card,
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.center,
                              children: [
                                // ========================================
                                // CHECKBOX
                                // ========================================

                                Checkbox(
                                  value: isSelected,
                                  activeColor: BuyerColors.primary,
                                  onChanged: (bool? value) {
                                    _toggleItemSelection(item, value);
                                  },
                                ),

                                const SizedBox(width: 2),

                                // ========================================
                                // PRODUCT IMAGE
                                // ========================================
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
                                                (
                                                  BuildContext context,
                                                  Object error,
                                                  StackTrace? stackTrace,
                                                ) {
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

                                // ========================================
                                // PRODUCT INFO
                                // ========================================
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        product.name.isEmpty
                                            ? 'Unnamed Product'
                                            : product.name,
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: BuyerStyle.cardTitle,
                                      ),

                                      const SizedBox(height: 6),

                                      Text(
                                        '₱${product.sellingPrice.toStringAsFixed(2)}',
                                        style: const TextStyle(
                                          color: BuyerColors.primary,
                                          fontSize: 16,
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),

                                      if (product.hasDiscount) ...[
                                        const SizedBox(height: 2),
                                        Text(
                                          '₱${product.price.toStringAsFixed(2)}',
                                          style: const TextStyle(
                                            color: BuyerColors.textGrey,
                                            fontSize: 12,
                                            decoration:
                                                TextDecoration.lineThrough,
                                          ),
                                        ),
                                      ],

                                      const SizedBox(height: 5),

                                      Text(
                                        product.stock > 0
                                            ? '${product.stock} available'
                                            : 'Out of stock',
                                        style: TextStyle(
                                          fontSize: 11,
                                          color: product.stock > 0
                                              ? BuyerColors.textGrey
                                              : BuyerColors.danger,
                                        ),
                                      ),

                                      const SizedBox(height: 10),

                                      // ==================================
                                      // QUANTITY
                                      // ==================================
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
                                        'Subtotal: ₱${item.subtotal.toStringAsFixed(2)}',
                                        style: const TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.w600,
                                          color: BuyerColors.textDark,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),

                                // ========================================
                                // DELETE
                                // ========================================
                                IconButton(
                                  tooltip: 'Remove',
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

                    // ====================================================
                    // CHECKOUT SUMMARY
                    // ====================================================
                    Container(
                      padding: EdgeInsets.all(width * 0.05),
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.vertical(
                          top: Radius.circular(25),
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: Color(0x12000000),
                            blurRadius: 12,
                            offset: Offset(0, -3),
                          ),
                        ],
                      ),
                      child: SafeArea(
                        top: false,
                        child: Column(
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text(
                                  'Selected Products',
                                  style: BuyerStyle.small,
                                ),
                                Text(
                                  '${_selectedItems.length}',
                                  style: BuyerStyle.cardTitle,
                                ),
                              ],
                            ),

                            const SizedBox(height: 8),

                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text(
                                  'Total Quantity',
                                  style: BuyerStyle.small,
                                ),
                                Text(
                                  '$_selectedQuantity',
                                  style: BuyerStyle.cardTitle,
                                ),
                              ],
                            ),

                            const SizedBox(height: 10),

                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text(
                                  'Total',
                                  style: BuyerStyle.sectionTitle,
                                ),
                                Text(
                                  '₱${_selectedTotal.toStringAsFixed(2)}',
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
                                onPressed: selectedItems.isEmpty
                                    ? null
                                    : _checkoutSelectedItems,
                                child: Text(
                                  selectedItems.isEmpty
                                      ? 'Select items to checkout'
                                      : 'Checkout (${_selectedItems.length})',
                                ),
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

  // ================================================================
  // QUANTITY BUTTON
  // ================================================================

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

  // ================================================================
  // EMPTY CART
  // ================================================================

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

            const Text('Your cart is empty', style: BuyerStyle.sectionTitle),

            const SizedBox(height: 8),

            const Text(
              'Products you add to your cart will appear here.',
              textAlign: TextAlign.center,
              style: BuyerStyle.small,
            ),
          ],
        ),
      ),
    );
  }
}
