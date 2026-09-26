import 'package:flutter/material.dart';

import '../models/product_model.dart';

import '../styles/buyer_style.dart';

class ProductCard extends StatelessWidget {
  final ProductModel product;

  final VoidCallback onTap;

  const ProductCard({super.key, required this.product, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return GestureDetector(
      onTap: onTap,

      child: Container(
        width: width * 0.42,

        decoration: BuyerStyle.card,

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            SizedBox(
              height: 130,

              width: double.infinity,

              child: Container(
                decoration: const BoxDecoration(
                  color: Color(0xffEEEEEE),

                  borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
                ),

                child: product.imageUrl.isEmpty
                    ? const Center(
                        child: Icon(
                          Icons.image_outlined,

                          size: 55,

                          color: Colors.grey,
                        ),
                      )
                    : ClipRRect(
                        borderRadius: const BorderRadius.vertical(
                          top: Radius.circular(18),
                        ),

                        child: Image.network(
                          product.imageUrl,

                          fit: BoxFit.cover,
                        ),
                      ),
              ),
            ),

            Padding(
              padding: const EdgeInsets.all(12),

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  Text(
                    product.name,

                    maxLines: 1,

                    overflow: TextOverflow.ellipsis,

                    style: BuyerStyle.cardTitle,
                  ),

                  const SizedBox(height: 6),

                  Text(
                    "₱${product.price}",

                    style: const TextStyle(
                      fontWeight: FontWeight.bold,

                      fontSize: 16,

                      color: Colors.blue,
                    ),
                  ),

                  const SizedBox(height: 6),

                  Row(
                    children: [
                      const Icon(Icons.star, size: 16, color: Colors.orange),

                      const SizedBox(width: 4),

                      Text("${product.rating}", style: BuyerStyle.small),

                      const SizedBox(width: 8),

                      Expanded(
                        child: Text(
                          "${product.soldCount} sold",

                          overflow: TextOverflow.ellipsis,

                          style: BuyerStyle.small,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
