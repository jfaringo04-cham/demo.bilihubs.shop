import 'package:flutter/material.dart';

import '../../sevices/auth_storage.dart';
import '../../styles/buyer_style.dart';

class PaymentMethodsPage extends StatefulWidget {
  const PaymentMethodsPage({super.key});

  @override
  State<PaymentMethodsPage> createState() => _PaymentMethodsPageState();
}

class _PaymentMethodsPageState extends State<PaymentMethodsPage> {
  String selectedPaymentMethod = 'Cash on Delivery';

  bool isLoading = true;
  bool isSaving = false;

  final List<Map<String, dynamic>> paymentMethods = [
    {
      'name': 'Cash on Delivery',
      'subtitle': 'Pay when your order arrives',
      'icon': Icons.payments_outlined,
      'enabled': true,
    },
    {
      'name': 'GCash',
      'subtitle': 'GCash payment is not connected yet',
      'icon': Icons.account_balance_wallet_outlined,
      'enabled': false,
    },
    {
      'name': 'Maya',
      'subtitle': 'Maya payment is not connected yet',
      'icon': Icons.wallet_outlined,
      'enabled': false,
    },
    {
      'name': 'Credit / Debit Card',
      'subtitle': 'Card payment is not connected yet',
      'icon': Icons.credit_card_outlined,
      'enabled': false,
    },
  ];

  @override
  void initState() {
    super.initState();
    _loadSavedPaymentMethod();
  }

  // ============================================================
  // LOAD SAVED PAYMENT METHOD
  // ============================================================

  Future<void> _loadSavedPaymentMethod() async {
    final Map<String, dynamic>? user = await AuthStorage.getUser();

    if (!mounted) {
      return;
    }

    final String savedPaymentMethod =
        user?['payment_method']?.toString().trim() ?? '';

    bool paymentExists = false;

    if (savedPaymentMethod.isNotEmpty) {
      for (final Map<String, dynamic> method in paymentMethods) {
        if (method['name'] == savedPaymentMethod && method['enabled'] == true) {
          paymentExists = true;
          break;
        }
      }
    }

    setState(() {
      if (paymentExists) {
        selectedPaymentMethod = savedPaymentMethod;
      }

      isLoading = false;
    });
  }

  // ============================================================
  // SAVE PAYMENT METHOD
  // ============================================================

  Future<void> _savePaymentMethod() async {
    if (isSaving) {
      return;
    }

    setState(() {
      isSaving = true;
    });

    await AuthStorage.updateUser({'payment_method': selectedPaymentMethod});

    if (!mounted) {
      return;
    }

    setState(() {
      isSaving = false;
    });

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          backgroundColor: BuyerColors.primaryDark,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          content: Row(
            children: [
              const Icon(Icons.check_circle_outline, color: Colors.white),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  '$selectedPaymentMethod saved as your payment method.',
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

  // ============================================================
  // SELECT PAYMENT METHOD
  // ============================================================

  void _selectPaymentMethod(Map<String, dynamic> method) {
    final bool enabled = method['enabled'] == true;

    if (!enabled) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            behavior: SnackBarBehavior.floating,
            content: Text('${method['name']} is not connected yet.'),
          ),
        );

      return;
    }

    final String name = method['name']?.toString().trim() ?? '';

    if (name.isEmpty || name == selectedPaymentMethod) {
      return;
    }

    setState(() {
      selectedPaymentMethod = name;
    });
  }

  // ============================================================
  // RADIO GROUP CHANGE
  // ============================================================

  void _onPaymentMethodChanged(String? value) {
    if (value == null || value == selectedPaymentMethod) {
      return;
    }

    final Map<String, dynamic>? method = _findPaymentMethod(value);

    if (method == null || method['enabled'] != true) {
      return;
    }

    setState(() {
      selectedPaymentMethod = value;
    });
  }

  Map<String, dynamic>? _findPaymentMethod(String name) {
    for (final Map<String, dynamic> method in paymentMethods) {
      if (method['name']?.toString() == name) {
        return method;
      }
    }

    return null;
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
        centerTitle: true,
        leading: IconButton(
          tooltip: 'Back',
          onPressed: () {
            Navigator.pop(context);
          },
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: BuyerColors.textDark,
            size: 20,
          ),
        ),
        title: const Text('Payment Methods', style: BuyerStyle.appBarTitle),
      ),
      body: SafeArea(
        top: false,
        child: isLoading
            ? const Center(
                child: CircularProgressIndicator(color: BuyerColors.primary),
              )
            : RadioGroup<String>(
                groupValue: selectedPaymentMethod,
                onChanged: _onPaymentMethodChanged,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 20, 16, 30),
                  children: [
                    const Text(
                      'Choose Payment Method',
                      style: BuyerStyle.sectionTitle,
                    ),

                    const SizedBox(height: 6),

                    const Text(
                      'Select the payment method you want to use during checkout.',
                      style: BuyerStyle.subtitle,
                    ),

                    const SizedBox(height: 20),

                    ...paymentMethods.map((Map<String, dynamic> method) {
                      final String name = method['name']?.toString() ?? '';

                      final String subtitle =
                          method['subtitle']?.toString() ?? '';

                      final IconData icon = method['icon'] as IconData;

                      final bool enabled = method['enabled'] == true;

                      final bool selected = selectedPaymentMethod == name;

                      return Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Material(
                          color: Colors.transparent,
                          child: InkWell(
                            onTap: () {
                              _selectPaymentMethod(method);
                            },
                            borderRadius: BorderRadius.circular(18),
                            child: Ink(
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                color: enabled
                                    ? BuyerColors.white
                                    : Colors.grey.shade100,
                                borderRadius: BorderRadius.circular(18),
                                border: Border.all(
                                  color: selected
                                      ? BuyerColors.primary
                                      : BuyerColors.border,
                                  width: selected ? 1.6 : 1,
                                ),
                              ),
                              child: Row(
                                children: [
                                  Container(
                                    height: 48,
                                    width: 48,
                                    decoration: BoxDecoration(
                                      color: enabled
                                          ? BuyerColors.lightPurple
                                          : Colors.grey.shade200,
                                      borderRadius: BorderRadius.circular(14),
                                    ),
                                    child: Icon(
                                      icon,
                                      color: enabled
                                          ? BuyerColors.primary
                                          : Colors.grey,
                                    ),
                                  ),

                                  const SizedBox(width: 14),

                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            Expanded(
                                              child: Text(
                                                name,
                                                style: BuyerStyle.cardTitle,
                                              ),
                                            ),

                                            if (!enabled)
                                              Container(
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                      horizontal: 7,
                                                      vertical: 3,
                                                    ),
                                                decoration: BoxDecoration(
                                                  color:
                                                      BuyerColors.lightPurple,
                                                  borderRadius:
                                                      BorderRadius.circular(8),
                                                ),
                                                child: const Text(
                                                  'Soon',
                                                  style: TextStyle(
                                                    color: BuyerColors.primary,
                                                    fontSize: 10,
                                                    fontWeight: FontWeight.w700,
                                                  ),
                                                ),
                                              ),
                                          ],
                                        ),

                                        const SizedBox(height: 4),

                                        Text(subtitle, style: BuyerStyle.small),
                                      ],
                                    ),
                                  ),

                                  if (enabled)
                                    Radio<String>(
                                      value: name,
                                      activeColor: BuyerColors.primary,
                                    )
                                  else
                                    const Padding(
                                      padding: EdgeInsets.only(left: 12),
                                      child: Icon(
                                        Icons.lock_outline_rounded,
                                        color: BuyerColors.textGrey,
                                        size: 20,
                                      ),
                                    ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      );
                    }),

                    const SizedBox(height: 12),

                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: BuyerColors.lightPurple,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(
                            Icons.info_outline_rounded,
                            color: BuyerColors.primary,
                            size: 20,
                          ),

                          const SizedBox(width: 10),

                          Expanded(
                            child: Text(
                              'Selected: $selectedPaymentMethod',
                              style: const TextStyle(
                                fontSize: 12,
                                height: 1.4,
                                color: BuyerColors.textDark,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 12),

                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: BuyerColors.white,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: BuyerColors.border),
                      ),
                      child: const Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            Icons.security_outlined,
                            color: BuyerColors.primary,
                            size: 20,
                          ),

                          SizedBox(width: 10),

                          Expanded(
                            child: Text(
                              'Only Cash on Delivery is available for now. '
                              'GCash, Maya, and card payments will be enabled '
                              'after their payment integrations are connected.',
                              style: BuyerStyle.small,
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 24),

                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton(
                        style: BuyerStyle.primaryButton,
                        onPressed: isSaving ? null : _savePaymentMethod,
                        child: isSaving
                            ? const SizedBox(
                                height: 22,
                                width: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Text(
                                'Save Payment Method',
                                style: BuyerStyle.buttonText,
                              ),
                      ),
                    ),
                  ],
                ),
              ),
      ),
    );
  }
}
