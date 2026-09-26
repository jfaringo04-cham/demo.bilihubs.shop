import 'package:flutter/material.dart';

import '../../models/product_model.dart';

import '../../styles/buyer_style.dart';

class Checkout extends StatelessWidget {
  final List<ProductModel> products;

  const Checkout({super.key, required this.products});

  double get total {
    double sum = 0;

    for (var product in products) {
      sum += product.price;
    }

    return sum;
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: BuyerColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        title: const Text("Checkout", style: BuyerStyle.title),
      ),

      body: SingleChildScrollView(
        padding: EdgeInsets.symmetric(horizontal: width * 0.045, vertical: 16),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            // ORDER SUMMARY

            Container(
              padding: const EdgeInsets.all(18),

              decoration: BuyerStyle.card,

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  const Text("Order Summary", style: BuyerStyle.sectionTitle),

                  const SizedBox(height: 15),

                  ListView.builder(
                    shrinkWrap: true,

                    physics: const NeverScrollableScrollPhysics(),

                    itemCount: products.length,

                    itemBuilder: (context, index) {
                      final product = products[index];

                      return Padding(
                        padding: const EdgeInsets.only(bottom: 12),

                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                product.name,

                                maxLines: 1,

                                overflow: TextOverflow.ellipsis,

                                style: BuyerStyle.cardTitle,
                              ),
                            ),

                            const SizedBox(width: 10),

                            Text("₱${product.price}", style: BuyerStyle.small),
                          ],
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // ADDRESS
            Container(
              padding: const EdgeInsets.all(18),

              decoration: BuyerStyle.card,

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  const Text(
                    "Delivery Address",

                    style: BuyerStyle.sectionTitle,
                  ),

                  const SizedBox(height: 10),

                  const Text("123 Sample Street, Manila", softWrap: true),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // TOTAL
            Container(
              padding: const EdgeInsets.all(18),

              decoration: BuyerStyle.card,

              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,

                children: [
                  const Text("Total", style: BuyerStyle.sectionTitle),

                  Text("₱$total", style: BuyerStyle.title),
                ],
              ),
            ),

            const SizedBox(height: 25),

            SizedBox(
              width: double.infinity,

              height: 55,

              child: ElevatedButton(
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text("Order placed successfully")),
                  );
                },

                child: const Text("Place Order"),
              ),
            ),

            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }
}
