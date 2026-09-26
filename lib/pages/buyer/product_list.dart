import 'package:flutter/material.dart';

import '../../models/product_model.dart';

import '../../styles/buyer_style.dart';

import '../../widgets/product_card.dart';

import 'product_details.dart';

class ProductList extends StatefulWidget {
  const ProductList({super.key});

  @override
  State<ProductList> createState() => _ProductListState();
}

class _ProductListState extends State<ProductList> {
  final List<ProductModel> products = [
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

    ProductModel(
      id: 3,

      name: "Phone Stand",

      description: "Adjustable phone holder",

      price: 299,

      imageUrl: "",

      rating: 4.5,

      soldCount: 90,

      stock: 30,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        title: const Text("Products", style: BuyerStyle.title),
      ),

      body: GridView.builder(
        padding: const EdgeInsets.all(16),

        itemCount: products.length,

        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,

          crossAxisSpacing: 12,

          mainAxisSpacing: 12,

          childAspectRatio: .65,
        ),

        itemBuilder: (context, index) {
          final product = products[index];

          return ProductCard(
            product: product,

            onTap: () {
              Navigator.push(
                context,

                MaterialPageRoute(
                  builder: (context) => ProductDetails(product: product),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
