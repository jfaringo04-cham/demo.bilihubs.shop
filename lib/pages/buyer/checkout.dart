import 'package:flutter/material.dart';

import '../../sevices/api_services.dart';
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
  final TextEditingController notesController = TextEditingController();

  String buyerName = '';
  String buyerPhone = '';

  List<Map<String, dynamic>> buyerAddresses = <Map<String, dynamic>>[];

  Map<String, dynamic>? selectedAddress;

  String paymentMethod = 'cod';

  bool isLoadingCheckout = true;
  bool isPlacingOrder = false;

  static const double shippingFee = 50.00;

  static const Map<String, String> paymentMethodLabels = {
    'cod': 'Cash on Delivery',
    'gcash': 'GCash',
    'maya': 'Maya',
    'card': 'Card',
    'bank_transfer': 'Bank Transfer',
  };

  @override
  void initState() {
    super.initState();
    _loadCheckoutInformation();
  }

  @override
  void dispose() {
    notesController.dispose();
    super.dispose();
  }

  // ============================================================
  // LOAD BUYER + SAVED ADDRESSES
  // ============================================================

  Future<void> _loadCheckoutInformation() async {
    final Map<String, dynamic>? user = await AuthStorage.getUser();

    if (user != null) {
      buyerName = user['name']?.toString().trim() ?? '';

      buyerPhone =
          user['phone']?.toString().trim() ??
          user['mobile_number']?.toString().trim() ??
          '';
    }

    final Map<String, dynamic> addressResult =
        await ApiService.getBuyerAddresses();

    if (!mounted) {
      return;
    }

    if (addressResult['success'] == true) {
      final List<dynamic> rawAddresses = addressResult['data'] is List
          ? List<dynamic>.from(addressResult['data'])
          : <dynamic>[];

      final List<Map<String, dynamic>> parsedAddresses = rawAddresses
          .whereType<Map>()
          .map((Map item) => Map<String, dynamic>.from(item))
          .toList();

      Map<String, dynamic>? defaultAddress;

      for (final Map<String, dynamic> address in parsedAddresses) {
        if (_isTrue(address['is_default'])) {
          defaultAddress = address;
          break;
        }
      }

      if (parsedAddresses.isNotEmpty) {
        defaultAddress ??= parsedAddresses.first;
      }

      setState(() {
        buyerAddresses = parsedAddresses;
        selectedAddress = defaultAddress;

        final String selectedPhone =
            selectedAddress?['phone']?.toString().trim() ?? '';

        if (selectedPhone.isNotEmpty) {
          buyerPhone = selectedPhone;
        }

        isLoadingCheckout = false;
      });

      return;
    }

    setState(() {
      isLoadingCheckout = false;
    });

    final String message =
        addressResult['message']?.toString().trim() ??
        'Unable to load your saved delivery addresses.';

    _showMessage(message);
  }

  // ============================================================
  // TOTALS
  // ============================================================

  double get subtotal {
    double sum = 0;

    for (final CartItem item in widget.items) {
      sum += item.subtotal;
    }

    return sum;
  }

  int get totalItems {
    int count = 0;

    for (final CartItem item in widget.items) {
      count += item.quantity;
    }

    return count;
  }

  double get serviceFee {
    final int subtotalMinor = (subtotal * 100).round();

    final int serviceFeeMinor = ((subtotalMinor * 3) + 50) ~/ 100;

    return serviceFeeMinor / 100;
  }

  double get grandTotal {
    return subtotal + shippingFee + serviceFee;
  }

  // ============================================================
  // ADDRESS HELPERS
  // ============================================================

  bool get hasAddress {
    final int addressId =
        int.tryParse(selectedAddress?['id']?.toString() ?? '') ?? 0;

    return addressId > 0;
  }

  String get selectedAddressText {
    final Map<String, dynamic>? address = selectedAddress;

    if (address == null) {
      return '';
    }

    final String fullAddress = address['full_address']?.toString().trim() ?? '';

    if (fullAddress.isNotEmpty) {
      return fullAddress;
    }

    final List<String> parts = [
      address['address_line1']?.toString().trim() ?? '',
      address['address_line2']?.toString().trim() ?? '',
      address['city']?.toString().trim() ?? '',
      address['province']?.toString().trim() ?? '',
      address['postal_code']?.toString().trim() ?? '',
      address['country']?.toString().trim() ?? '',
    ].where((String value) => value.isNotEmpty).toList();

    return parts.join(', ');
  }

  String get selectedAddressLabel {
    final String label = selectedAddress?['label']?.toString().trim() ?? '';

    return label.isNotEmpty ? label : 'Delivery Address';
  }

  // ============================================================
  // ADDRESS PICKER
  // ============================================================

  Future<void> _showAddressPicker() async {
    if (buyerAddresses.isEmpty || isPlacingOrder) {
      return;
    }

    final Map<String, dynamic>? picked =
        await showModalBottomSheet<Map<String, dynamic>>(
          context: context,
          isScrollControlled: true,
          showDragHandle: true,
          builder: (BuildContext sheetContext) {
            return SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(18, 4, 18, 24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Select Delivery Address',
                      style: BuyerStyle.sectionTitle,
                    ),
                    const SizedBox(height: 14),
                    Flexible(
                      child: ListView.separated(
                        shrinkWrap: true,
                        itemCount: buyerAddresses.length,
                        separatorBuilder: (BuildContext context, int index) {
                          return const Divider(
                            height: 1,
                            color: BuyerColors.border,
                          );
                        },
                        itemBuilder: (BuildContext context, int index) {
                          final Map<String, dynamic> address =
                              buyerAddresses[index];

                          final int addressId =
                              int.tryParse(address['id']?.toString() ?? '') ??
                              0;

                          final int selectedId =
                              int.tryParse(
                                selectedAddress?['id']?.toString() ?? '',
                              ) ??
                              0;

                          final bool selected = addressId == selectedId;

                          final String rawLabel =
                              address['label']?.toString().trim() ?? '';

                          final String label = rawLabel.isNotEmpty
                              ? rawLabel
                              : 'Address ${index + 1}';

                          final String fullAddress =
                              address['full_address']?.toString().trim() ?? '';

                          final String phone =
                              address['phone']?.toString().trim() ?? '';

                          return ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: Icon(
                              selected
                                  ? Icons.check_circle_rounded
                                  : Icons.location_on_outlined,
                              color: selected
                                  ? BuyerColors.primary
                                  : BuyerColors.textGrey,
                            ),
                            title: Text(
                              label,
                              style: const TextStyle(
                                color: BuyerColors.textDark,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            subtitle: Padding(
                              padding: const EdgeInsets.only(top: 5),
                              child: Text(
                                [fullAddress, if (phone.isNotEmpty) phone]
                                    .where((String value) => value.isNotEmpty)
                                    .join('\n'),
                                style: BuyerStyle.small,
                              ),
                            ),
                            onTap: () {
                              Navigator.pop(sheetContext, address);
                            },
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );

    if (picked == null || !mounted) {
      return;
    }

    setState(() {
      selectedAddress = picked;

      final String addressPhone = picked['phone']?.toString().trim() ?? '';

      if (addressPhone.isNotEmpty) {
        buyerPhone = addressPhone;
      }
    });
  }

  // ============================================================
  // PLACE ORDER
  // ============================================================

  Future<void> _placeOrder() async {
    if (isPlacingOrder || widget.items.isEmpty) {
      return;
    }

    if (!hasAddress) {
      _showMessage(
        'Please select a saved delivery address before placing your order.',
      );

      return;
    }

    final int addressId =
        int.tryParse(selectedAddress?['id']?.toString() ?? '') ?? 0;

    final List<Map<String, dynamic>> orderItems = widget.items
        .map(
          (CartItem item) => <String, dynamic>{
            'product_id': item.product.id,
            'variant_id': null,
            'size_id': null,
            'quantity': item.quantity,
          },
        )
        .toList();

    setState(() {
      isPlacingOrder = true;
    });

    final Map<String, dynamic> result = await ApiService.createOrder(
      addressId: addressId,
      paymentMethod: paymentMethod,
      notes: notesController.text,
      items: orderItems,
    );

    if (!mounted) {
      return;
    }

    setState(() {
      isPlacingOrder = false;
    });

    if (result['success'] != true) {
      _showMessage(_extractApiErrorMessage(result));

      return;
    }

    final Map<String, dynamic> order = result['data'] is Map
        ? Map<String, dynamic>.from(result['data'])
        : <String, dynamic>{};

    _clearLocalCartIfCheckoutMatchesCart();

    final String orderNumber = order['order_number']?.toString().trim() ?? '';

    final String serverMessage = result['message']?.toString().trim() ?? '';

    final String message = serverMessage.isNotEmpty
        ? serverMessage
        : 'Order placed successfully.';

    await _showSuccessDialog(message: message, orderNumber: orderNumber);

    if (!mounted) {
      return;
    }

    Navigator.pop(context, true);
  }

  // ============================================================
  // LOCAL CART CLEANUP
  // ============================================================

  void _clearLocalCartIfCheckoutMatchesCart() {
    final List<CartItem> localCart = CartService.items;

    if (localCart.length != widget.items.length) {
      return;
    }

    final Map<int, int> cartQuantities = <int, int>{};

    final Map<int, int> checkoutQuantities = <int, int>{};

    for (final CartItem item in localCart) {
      cartQuantities[item.product.id] =
          (cartQuantities[item.product.id] ?? 0) + item.quantity;
    }

    for (final CartItem item in widget.items) {
      checkoutQuantities[item.product.id] =
          (checkoutQuantities[item.product.id] ?? 0) + item.quantity;
    }

    if (cartQuantities.length != checkoutQuantities.length) {
      return;
    }

    for (final MapEntry<int, int> entry in cartQuantities.entries) {
      if (checkoutQuantities[entry.key] != entry.value) {
        return;
      }
    }

    CartService.clearCart();
  }

  // ============================================================
  // API ERROR
  // ============================================================

  String _extractApiErrorMessage(Map<String, dynamic> result) {
    final dynamic errors = result['errors'];

    if (errors is Map) {
      for (final dynamic value in errors.values) {
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }

        if (value != null && value.toString().trim().isNotEmpty) {
          return value.toString();
        }
      }
    }

    final String message = result['message']?.toString().trim() ?? '';

    if (message.isNotEmpty) {
      return message;
    }

    return 'Unable to place your order. Please try again.';
  }

  // ============================================================
  // SUCCESS DIALOG
  // ============================================================

  Future<void> _showSuccessDialog({
    required String message,
    required String orderNumber,
  }) async {
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Row(
            children: [
              Icon(
                Icons.check_circle_outline_rounded,
                color: BuyerColors.success,
              ),
              SizedBox(width: 10),
              Expanded(child: Text('Order Placed')),
            ],
          ),
          content: Text(
            orderNumber.isEmpty
                ? message
                : '$message\n\n'
                      'Order Number: '
                      '$orderNumber',
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);
              },
              child: const Text(
                'Done',
                style: TextStyle(
                  color: BuyerColors.primary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        );
      },
    );
  }

  // ============================================================
  // MESSAGE
  // ============================================================

  void _showMessage(String message) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: BuyerColors.primaryDark,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          content: Row(
            children: [
              const Icon(Icons.info_outline_rounded, color: Colors.white),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  message,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
  }

  bool _isTrue(dynamic value) {
    if (value == true || value == 1) {
      return true;
    }

    final String normalized = value?.toString().trim().toLowerCase() ?? '';

    return normalized == 'true' || normalized == '1';
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.sizeOf(context).width;

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
                  _buildNotes(),
                  const SizedBox(height: 20),
                  _buildPaymentSummary(),
                  const SizedBox(height: 25),
                  _buildPlaceOrderButton(),
                  const SizedBox(height: 20),
                ],
              ),
            ),
    );
  }

  // ============================================================
  // ORDER SUMMARY
  // ============================================================

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
            '$totalItems item${totalItems > 1 ? 's' : ''}',
            style: BuyerStyle.small,
          ),
          const SizedBox(height: 18),
          ListView.separated(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: widget.items.length,
            separatorBuilder: (BuildContext context, int index) {
              return const Padding(
                padding: EdgeInsets.symmetric(vertical: 12),
                child: Divider(height: 1, color: BuyerColors.border),
              );
            },
            itemBuilder: (BuildContext context, int index) {
              final CartItem item = widget.items[index];

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
                                  (
                                    BuildContext context,
                                    Object error,
                                    StackTrace? stackTrace,
                                  ) {
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
                          '₱${product.sellingPrice.toStringAsFixed(2)} each',
                          style: BuyerStyle.small,
                        ),
                        if (product.hasDiscount) ...[
                          const SizedBox(height: 2),
                          Text(
                            '₱${product.price.toStringAsFixed(2)} original',
                            style: const TextStyle(
                              color: BuyerColors.textGrey,
                              fontSize: 11,
                              decoration: TextDecoration.lineThrough,
                            ),
                          ),
                        ],
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

  // ============================================================
  // DELIVERY ADDRESS
  // ============================================================

  Widget _buildDeliveryAddress() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BuyerStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.location_on_outlined,
                color: BuyerColors.primary,
                size: 22,
              ),
              const SizedBox(width: 9),
              const Expanded(
                child: Text('Delivery Address', style: BuyerStyle.sectionTitle),
              ),
              if (!isLoadingCheckout && buyerAddresses.isNotEmpty)
                TextButton(
                  onPressed: isPlacingOrder ? null : _showAddressPicker,
                  child: const Text('Change'),
                ),
            ],
          ),

          const SizedBox(height: 15),

          if (isLoadingCheckout)
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
                      'No saved delivery address was found. '
                      'Please add a saved address to your '
                      'BiliHub account first.',
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
            InkWell(
              onTap: buyerAddresses.length > 1 && !isPlacingOrder
                  ? _showAddressPicker
                  : null,
              borderRadius: BorderRadius.circular(14),
              child: Container(
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
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            selectedAddressLabel,
                            style: const TextStyle(
                              color: BuyerColors.textDark,
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          if (buyerName.isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text(
                              buyerName,
                              style: const TextStyle(
                                color: BuyerColors.textDark,
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                          const SizedBox(height: 4),
                          Text(
                            selectedAddressText,
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

                    if (buyerAddresses.length > 1)
                      const Icon(
                        Icons.keyboard_arrow_down_rounded,
                        color: BuyerColors.textGrey,
                      ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  // ============================================================
  // PAYMENT METHOD
  // ============================================================

  Widget _buildPaymentMethod() {
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
                Icons.payments_outlined,
                color: BuyerColors.primary,
                size: 22,
              ),
              SizedBox(width: 9),
              Text('Payment Method', style: BuyerStyle.sectionTitle),
            ],
          ),

          const SizedBox(height: 15),

          DropdownButtonFormField<String>(
            initialValue: paymentMethod,
            isExpanded: true,
            decoration: InputDecoration(
              filled: true,
              fillColor: BuyerColors.lightPurple,
              prefixIcon: const Icon(
                Icons.account_balance_wallet_outlined,
                color: BuyerColors.primary,
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: BuyerColors.border),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: BuyerColors.border),
              ),
            ),
            items: paymentMethodLabels.entries
                .map(
                  (MapEntry<String, String> entry) => DropdownMenuItem<String>(
                    value: entry.key,
                    child: Text(entry.value),
                  ),
                )
                .toList(),
            onChanged: isPlacingOrder
                ? null
                : (String? value) {
                    if (value == null) {
                      return;
                    }

                    setState(() {
                      paymentMethod = value;
                    });
                  },
          ),

          if (paymentMethod != 'cod') ...[
            const SizedBox(height: 10),
            const Text(
              'Non-COD payments are created as pending '
              'by the current backend. Payment gateway '
              'confirmation can be connected separately.',
              style: BuyerStyle.small,
            ),
          ],
        ],
      ),
    );
  }

  // ============================================================
  // NOTES
  // ============================================================

  Widget _buildNotes() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BuyerStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.notes_outlined, color: BuyerColors.primary, size: 22),
              SizedBox(width: 9),
              Text('Order Notes', style: BuyerStyle.sectionTitle),
            ],
          ),

          const SizedBox(height: 15),

          TextField(
            controller: notesController,
            enabled: !isPlacingOrder,
            maxLines: 3,
            maxLength: 1000,
            decoration: InputDecoration(
              hintText: 'Optional delivery or order note',
              filled: true,
              fillColor: BuyerColors.lightPurple,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: BuyerColors.border),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: BuyerColors.border),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PAYMENT SUMMARY
  // ============================================================

  Widget _buildPaymentSummary() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BuyerStyle.card,
      child: Column(
        children: [
          _summaryRow('Total Items', '$totalItems'),

          const SizedBox(height: 12),

          _summaryRow('Subtotal', '₱${subtotal.toStringAsFixed(2)}'),

          const SizedBox(height: 12),

          _summaryRow('Shipping Fee', '₱${shippingFee.toStringAsFixed(2)}'),

          const SizedBox(height: 12),

          _summaryRow('Service Fee (3%)', '₱${serviceFee.toStringAsFixed(2)}'),

          const Padding(
            padding: EdgeInsets.symmetric(vertical: 15),
            child: Divider(height: 1, color: BuyerColors.border),
          ),

          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Total', style: BuyerStyle.sectionTitle),
              Text(
                '₱${grandTotal.toStringAsFixed(2)}',
                style: const TextStyle(
                  color: BuyerColors.primary,
                  fontSize: 21,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),

          const SizedBox(height: 8),

          const Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'Final pricing is verified again by the '
              'BiliHub server when the order is placed.',
              style: BuyerStyle.small,
            ),
          ),
        ],
      ),
    );
  }

  Widget _summaryRow(String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: BuyerStyle.small),
        Text(value, style: BuyerStyle.cardTitle),
      ],
    );
  }

  // ============================================================
  // PLACE ORDER BUTTON
  // ============================================================

  Widget _buildPlaceOrderButton() {
    return SizedBox(
      width: double.infinity,
      height: 55,
      child: ElevatedButton.icon(
        style: BuyerStyle.primaryButton,
        onPressed: !hasAddress || isLoadingCheckout || isPlacingOrder
            ? null
            : _placeOrder,
        icon: isPlacingOrder
            ? const SizedBox(
                height: 20,
                width: 20,
                child: CircularProgressIndicator(
                  color: Colors.white,
                  strokeWidth: 2.2,
                ),
              )
            : const Icon(Icons.shopping_bag_outlined, size: 20),
        label: Text(isPlacingOrder ? 'Placing Order...' : 'Place Order'),
      ),
    );
  }

  // ============================================================
  // EMPTY CHECKOUT
  // ============================================================

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
              onPressed: () {
                Navigator.pop(context);
              },
              child: const Text('Back to Cart'),
            ),
          ],
        ),
      ),
    );
  }
}
