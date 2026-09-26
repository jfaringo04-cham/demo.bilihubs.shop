import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';

class PaymentMethodsPage extends StatefulWidget {
  const PaymentMethodsPage({super.key});

  @override
  State<PaymentMethodsPage> createState() => _PaymentMethodsPageState();
}

class _PaymentMethodsPageState extends State<PaymentMethodsPage> {
  String selectedPaymentMethod = "Cash on Delivery";

  final List<Map<String, dynamic>> paymentMethods = [
    {
      "name": "Cash on Delivery",
      "subtitle": "Pay when your order arrives",
      "icon": Icons.payments_outlined,
    },
    {
      "name": "GCash",
      "subtitle": "Pay using your GCash account",
      "icon": Icons.account_balance_wallet_outlined,
    },
    {
      "name": "Maya",
      "subtitle": "Pay using your Maya wallet",
      "icon": Icons.wallet_outlined,
    },
    {
      "name": "Credit / Debit Card",
      "subtitle": "Visa, Mastercard and supported cards",
      "icon": Icons.credit_card_outlined,
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
          onPressed: () {
            Navigator.pop(context);
          },

          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: BuyerColors.textDark,
            size: 20,
          ),
        ),

        title: const Text("Payment Methods", style: BuyerStyle.appBarTitle),
      ),

      body: SafeArea(
        top: false,

        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 30),

          children: [
            const Text("Choose Payment Method", style: BuyerStyle.sectionTitle),

            const SizedBox(height: 6),

            const Text(
              "Select the payment method you want to use during checkout.",
              style: BuyerStyle.subtitle,
            ),

            const SizedBox(height: 20),

            ...paymentMethods.map((method) {
              final name = method["name"] as String;

              final selected = selectedPaymentMethod == name;

              return Padding(
                padding: const EdgeInsets.only(bottom: 12),

                child: Material(
                  color: Colors.transparent,

                  child: InkWell(
                    onTap: () {
                      setState(() {
                        selectedPaymentMethod = name;
                      });
                    },

                    borderRadius: BorderRadius.circular(18),

                    child: Ink(
                      padding: const EdgeInsets.all(16),

                      decoration: BoxDecoration(
                        color: BuyerColors.white,

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
                              color: BuyerColors.lightPurple,

                              borderRadius: BorderRadius.circular(14),
                            ),

                            child: Icon(
                              method["icon"] as IconData,

                              color: BuyerColors.primary,
                            ),
                          ),

                          const SizedBox(width: 14),

                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,

                              children: [
                                Text(name, style: BuyerStyle.cardTitle),

                                const SizedBox(height: 4),

                                Text(
                                  method["subtitle"] as String,

                                  style: BuyerStyle.small,
                                ),
                              ],
                            ),
                          ),

                          Radio<String>(
                            value: name,

                            groupValue: selectedPaymentMethod,

                            activeColor: BuyerColors.primary,

                            onChanged: (value) {
                              if (value == null) {
                                return;
                              }

                              setState(() {
                                selectedPaymentMethod = value;
                              });
                            },
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
                      "Selected: $selectedPaymentMethod",
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

            const SizedBox(height: 24),

            SizedBox(
              width: double.infinity,

              child: ElevatedButton(
                style: BuyerStyle.primaryButton,

                onPressed: () {
                  ScaffoldMessenger.of(context)
                    ..hideCurrentSnackBar()
                    ..showSnackBar(
                      SnackBar(
                        content: Text(
                          "$selectedPaymentMethod selected as your payment method.",
                        ),

                        behavior: SnackBarBehavior.floating,
                      ),
                    );
                },

                child: const Text(
                  "Save Payment Method",
                  style: BuyerStyle.buttonText,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
