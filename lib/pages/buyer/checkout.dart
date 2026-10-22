import 'package:flutter/material.dart';

import '../../sevices/api_services.dart';
import '../../sevices/auth_storage.dart';
import '../../sevices/cart_service.dart';
import '../../styles/buyer_style.dart';

class Checkout extends StatefulWidget {
  final List<CartItem> items;
  final bool clearLocalCartOnSuccess;

  const Checkout({
    super.key,
    required this.items,
    this.clearLocalCartOnSuccess = false,
  });

  @override
  State<Checkout> createState() => _CheckoutState();
}

class _CheckoutState extends State<Checkout> {
  String buyerName = '';
  String buyerPhone = '';
  String buyerAddress = '';
  int? addressId;
  String paymentMethod = 'cod';

  bool isLoadingBuyer = true;
  bool isPlacingOrder = false;
  String? addressError;

  @override
  void initState() {
    super.initState();
    _loadBuyerInformation();
  }

  Future<void> _loadBuyerInformation() async {
    final user = await AuthStorage.getUser();
    final addressResult = await ApiService.getAddresses();

    if (!mounted) return;

    buyerName = user?['name']?.toString() ?? '';
    buyerPhone =
        user?['mobile_number']?.toString() ??
        user?['phone']?.toString() ??
        '';

    if (addressResult['success'] == true) {
      final data = addressResult['data'];
      if (data is List && data.isNotEmpty) {
        Map<String, dynamic>? selected;

        for (final item in data) {
          if (item is Map) {
            final address = Map<String, dynamic>.from(item);
            if (selected == null) selected = address;
            if (address['is_default'] == true ||
                address['is_default']?.toString() == '1') {
              selected = address;
              break;
            }
          }
        }

        if (selected != null) {
          addressId = int.tryParse(selected['id']?.toString() ?? '');
          buyerAddress =
              selected['full_address']?.toString().trim() ??
              _buildAddress(selected);
          final savedPhone = selected['phone']?.toString().trim() ?? '';
          if (savedPhone.isNotEmpty) buyerPhone = savedPhone;
        }
      }
    } else {
      addressError = addressResult['message']?.toString();
    }

    setState(() {
      isLoadingBuyer = false;
    });
  }

  String _buildAddress(Map<String, dynamic> address) {
    return [
      address['address_line1'],
      address['address_line2'],
      address['city'],
      address['province'],
      address['postal_code'],
      address['country'],
    ]
        .map((value) => value?.toString().trim() ?? '')
        .where((value) => value.isNotEmpty)
        .join(', ');
  }

  double get subtotal {
    double sum = 0;
    for (final item in widget.items) {
      sum += item.subtotal;
    }
    return sum;
  }

  double get shippingFee => widget.items.isEmpty ? 0 : 50.0;

  double get serviceFee => subtotal * 0.03;

  double get total => subtotal + shippingFee + serviceFee;

  int get totalItems {
    int count = 0;
    for (final item in widget.items) {
      count += item.quantity;
    }
    return count;
  }

  bool get hasAddress => addressId != null && buyerAddress.trim().isNotEmpty;

  Future<void> _placeOrder() async {
    if (isPlacingOrder || !hasAddress || widget.items.isEmpty) return;

    setState(() => isPlacingOrder = true);

    final items = widget.items
        .map(
          (item) => <String, dynamic>{
            'product_id': item.product.id,
            'quantity': item.quantity,
          },
        )
        .toList();

    final result = await ApiService.placeOrder(
      addressId: addressId!,
      paymentMethod: paymentMethod,
      items: items,
    );

    if (!mounted) return;
    setState(() => isPlacingOrder = false);

    if (result['success'] != true) {
      _showMessage(
        result['message']?.toString() ?? 'Unable to place order.',
        success: false,
      );
      return;
    }

    if (widget.clearLocalCartOnSuccess) {
      CartService.clearCart();
    }

    final data = result['data'];
    String orderNumber = '';
    if (data is Map) {
      orderNumber = data['order_number']?.toString() ?? '';
    }

    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return AlertDialog(
          icon: const Icon(
            Icons.check_circle_outline_rounded,
            color: BuyerColors.success,
            size: 42,
          ),
          title: const Text('Order Placed'),
          content: Text(
            orderNumber.isEmpty
                ? 'Your order was submitted successfully.'
                : 'Your order $orderNumber was submitted successfully.',
            textAlign: TextAlign.center,
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('OK'),
            ),
          ],
        );
      },
    );

    if (!mounted) return;
    Navigator.pop(context, true);
  }

  void _showMessage(String message, {required bool success}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor:
              success ? BuyerColors.success : BuyerColors.primaryDark,
          content: Text(message),
        ),
      );
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
        title: const Text('Checkout', style: BuyerStyle.title),
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
                  _buildOrderSummary(),
                  const SizedBox(height: 20),
                  _buildDeliveryAddress(),
                  const SizedBox(height: 20),
                  _buildPaymentMethod(),
                  const SizedBox(height: 20),
                  _buildPaymentSummary(),
                  const SizedBox(height: 25),
                  SizedBox(
                    width: double.infinity,
                    height: 55,
                    child: ElevatedButton.icon(
                      style: BuyerStyle.primaryButton,
                      onPressed:
                          !hasAddress || isLoadingBuyer || isPlacingOrder
                          ? null
                          : _placeOrder,
                      icon: isPlacingOrder
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(
                              Icons.shopping_bag_outlined,
                              size: 20,
                            ),
                      label: Text(
                        isPlacingOrder ? 'Placing Order...' : 'Place Order',
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                ],
              ),
            ),
    );
  }

  Widget _buildOrderSummary() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BuyerStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Order Summary', style: BuyerStyle.sectionTitle),
          const SizedBox(height: 5),
          Text(
            '$totalItems item${totalItems == 1 ? '' : 's'}',
            style: BuyerStyle.small,
          ),
          const SizedBox(height: 18),
          ListView.separated(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: widget.items.length,
            separatorBuilder: (_, __) => const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Divider(height: 1, color: BuyerColors.border),
            ),
            itemBuilder: (context, index) {
              final item = widget.items[index];
              final product = item.product;
              return Row(
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
                            )
                          : Image.network(
                              product.imageUrl,
                              fit: BoxFit.cover,
                              errorBuilder: (_, __, ___) => const Icon(
                                Icons.broken_image_outlined,
                                color: BuyerColors.primary,
                              ),
                            ),
                    ),
                  ),
                  const SizedBox(width: 13),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          product.name.isEmpty
                              ? 'Unnamed Product'
                              : product.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: BuyerStyle.cardTitle,
                        ),
                        const SizedBox(height: 5),
                        Text(
                          '₱${product.effectivePrice.toStringAsFixed(2)} each',
                          style: BuyerStyle.small,
                        ),
                        const SizedBox(height: 4),
                        Text('Qty: ${item.quantity}', style: BuyerStyle.small),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  Text(
                    '₱${item.subtotal.toStringAsFixed(2)}',
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
    );
  }

  Widget _buildDeliveryAddress() {
    return Container(
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
              Text('Delivery Address', style: BuyerStyle.sectionTitle),
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
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.warning_amber_rounded,
                    color: BuyerColors.danger,
                    size: 21,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      addressError ??
                          'No saved checkout address was found. Add a delivery address to your BiliHub account before placing an order.',
                      style: const TextStyle(
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
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (buyerName.isNotEmpty)
                          Text(buyerName, style: BuyerStyle.cardTitle),
                        if (buyerName.isNotEmpty) const SizedBox(height: 4),
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
                          Text(buyerPhone, style: BuyerStyle.small),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildPaymentMethod() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BuyerStyle.card,
      child: const Row(
        children: [
          Icon(Icons.payments_outlined, color: BuyerColors.primary, size: 22),
          SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Payment Method', style: BuyerStyle.sectionTitle),
                SizedBox(height: 5),
                Text('Cash on Delivery', style: BuyerStyle.small),
              ],
            ),
          ),
          Icon(Icons.check_circle, color: BuyerColors.success, size: 21),
        ],
      ),
    );
  }

  Widget _buildPaymentSummary() {
    Widget row(String label, double value, {bool totalRow = false}) {
      return Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: totalRow ? BuyerStyle.sectionTitle : BuyerStyle.small,
          ),
          Text(
            '₱${value.toStringAsFixed(2)}',
            style: totalRow
                ? const TextStyle(
                    color: BuyerColors.primary,
                    fontSize: 21,
                    fontWeight: FontWeight.w800,
                  )
                : BuyerStyle.cardTitle,
          ),
        ],
      );
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BuyerStyle.card,
      child: Column(
        children: [
          row('Subtotal', subtotal),
          const SizedBox(height: 12),
          row('Delivery Fee', shippingFee),
          const SizedBox(height: 12),
          row('Service Fee (3%)', serviceFee),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 15),
            child: Divider(height: 1, color: BuyerColors.border),
          ),
          row('Total', total, totalRow: true),
        ],
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
            const Text('Nothing to checkout', style: BuyerStyle.sectionTitle),
            const SizedBox(height: 8),
            const Text(
              'Add a product to your cart before checking out.',
              textAlign: TextAlign.center,
              style: BuyerStyle.small,
            ),
            const SizedBox(height: 20),
            ElevatedButton(
              style: BuyerStyle.primaryButton,
              onPressed: () => Navigator.pop(context),
              child: const Text('Back to Cart'),
            ),
          ],
        ),
      ),
    );
  }
}
