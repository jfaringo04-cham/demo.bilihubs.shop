import 'package:flutter/material.dart';

import '../../models/product_model.dart';

import '../../styles/buyer_style.dart';

import 'checkout.dart';

class CartPage extends StatefulWidget {
  const CartPage({super.key});

  @override
  State<CartPage> createState() => _CartPageState();
}

class _CartPageState extends State<CartPage> {
  final List<ProductModel> cartItems = [
    ProductModel(
      id: 1,

      name: "Wireless Earbuds",

      description: "High quality Bluetooth earbuds",

      price: 899,

      imageUrl: "",

      rating: 4.8,

      soldCount: 250,

      stock: 20,
    ),

    ProductModel(
      id: 2,

      name: "Smart Watch",

      description: "Fitness and lifestyle smartwatch",

      price: 1599,

      imageUrl: "",

      rating: 4.6,

      soldCount: 180,

      stock: 15,
    ),
  ];

  double get total {
    double sum = 0;

    for (var item in cartItems) {
      sum += item.price;
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

        title: const Text("My Cart", style: BuyerStyle.title),
      ),

      body: Column(
        children: [
          Expanded(
            child: ListView.builder(
              padding: EdgeInsets.all(width * 0.04),

              itemCount: cartItems.length,

              itemBuilder: (context, index) {
                final item = cartItems[index];

                return Container(
                  margin: const EdgeInsets.only(bottom: 12),

                  padding: const EdgeInsets.all(16),

                  decoration: BuyerStyle.card,

                  child: Row(
                    children: [
                      Container(
                        height: 60,

                        width: 60,

                        decoration: BoxDecoration(
                          color: Colors.grey.shade200,

                          borderRadius: BorderRadius.circular(12),
                        ),

                        child: const Icon(Icons.shopping_bag_outlined),
                      ),

                      const SizedBox(width: 15),

                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,

                          children: [
                            Text(
                              item.name,

                              maxLines: 1,

                              overflow: TextOverflow.ellipsis,

                              style: BuyerStyle.cardTitle,
                            ),

                            const SizedBox(height: 5),

                            Text("₱${item.price}", style: BuyerStyle.small),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),

          Container(
            padding: EdgeInsets.all(width * 0.05),

            decoration: const BoxDecoration(
              color: Colors.white,

              borderRadius: BorderRadius.vertical(top: Radius.circular(25)),
            ),

            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,

                  children: [
                    const Text("Total", style: BuyerStyle.sectionTitle),

                    Text("₱$total", style: BuyerStyle.title),
                  ],
                ),

                const SizedBox(height: 15),

                SizedBox(
                  width: double.infinity,

                  height: 55,

                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.push(
                        context,

                        MaterialPageRoute(
                          builder: (context) => Checkout(products: cartItems),
                        ),
                      );
                    },

                    child: const Text("Checkout"),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
