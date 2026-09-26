import 'package:flutter/material.dart';

import '../../models/product_model.dart';
import '../../styles/buyer_style.dart';

class ProductDetails extends StatelessWidget {
  final ProductModel product;

  const ProductDetails({super.key, required this.product});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;
    final height = MediaQuery.of(context).size.height;

    return Scaffold(
      backgroundColor: BuyerColors.background,

      // ============================================================
      // APP BAR
      // ============================================================
      appBar: AppBar(
        backgroundColor: BuyerColors.white,

        elevation: 0,

        centerTitle: false,

        iconTheme: const IconThemeData(color: BuyerColors.textDark),

        title: const Text("Product Details", style: BuyerStyle.title),
      ),

      // ============================================================
      // BODY
      // ============================================================
      body: SafeArea(
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),

          padding: EdgeInsets.symmetric(
            horizontal: width * 0.045,
            vertical: 16,
          ),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              // ========================================================
              // PRODUCT IMAGE
              // ========================================================

              Container(
                height: height * 0.30,

                width: double.infinity,

                decoration: BoxDecoration(
                  color: BuyerColors.lightPurple,

                  borderRadius: BorderRadius.circular(20),

                  border: Border.all(color: BuyerColors.border),
                ),

                child: product.imageUrl.isEmpty
                    ? Column(
                        mainAxisAlignment: MainAxisAlignment.center,

                        children: [
                          Container(
                            height: 75,
                            width: 75,

                            decoration: BoxDecoration(
                              color: BuyerColors.softPurple,

                              borderRadius: BorderRadius.circular(20),
                            ),

                            child: const Icon(
                              Icons.image_outlined,

                              size: 42,

                              color: BuyerColors.primary,
                            ),
                          ),

                          const SizedBox(height: 10),

                          const Text("Product Image", style: BuyerStyle.small),
                        ],
                      )
                    : ClipRRect(
                        borderRadius: BorderRadius.circular(20),

                        child: Image.network(
                          product.imageUrl,

                          fit: BoxFit.cover,

                          width: double.infinity,

                          errorBuilder: (context, error, stackTrace) {
                            return Column(
                              mainAxisAlignment: MainAxisAlignment.center,

                              children: [
                                Container(
                                  height: 75,
                                  width: 75,

                                  decoration: BoxDecoration(
                                    color: BuyerColors.softPurple,

                                    borderRadius: BorderRadius.circular(20),
                                  ),

                                  child: const Icon(
                                    Icons.broken_image_outlined,

                                    size: 42,

                                    color: BuyerColors.primary,
                                  ),
                                ),

                                const SizedBox(height: 10),

                                const Text(
                                  "Unable to load image",
                                  style: BuyerStyle.small,
                                ),
                              ],
                            );
                          },
                        ),
                      ),
              ),

              const SizedBox(height: 22),

              // ========================================================
              // PRODUCT NAME
              // ========================================================
              Text(
                product.name,

                style: BuyerStyle.largeTitle,

                maxLines: 2,

                overflow: TextOverflow.ellipsis,
              ),

              const SizedBox(height: 10),

              // ========================================================
              // PRICE
              // ========================================================
              Text(
                "₱${product.price}",

                style: const TextStyle(
                  fontSize: 24,

                  fontWeight: FontWeight.w800,

                  color: BuyerColors.primary,
                ),
              ),

              const SizedBox(height: 14),

              // ========================================================
              // RATING / SOLD
              // ========================================================
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 6,
                    ),

                    decoration: BoxDecoration(
                      color: BuyerColors.lightPurple,

                      borderRadius: BorderRadius.circular(10),
                    ),

                    child: Row(
                      children: [
                        const Icon(
                          Icons.star_rounded,

                          size: 18,

                          color: BuyerColors.primary,
                        ),

                        const SizedBox(width: 4),

                        Text(
                          "${product.rating}",

                          style: const TextStyle(
                            fontSize: 12,

                            fontWeight: FontWeight.w700,

                            color: BuyerColors.textDark,
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(width: 10),

                  Container(height: 20, width: 1, color: BuyerColors.border),

                  const SizedBox(width: 10),

                  Text("${product.soldCount} sold", style: BuyerStyle.small),
                ],
              ),

              const SizedBox(height: 22),

              // ========================================================
              // STOCK
              // ========================================================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(16),

                decoration: BoxDecoration(
                  color: BuyerColors.lightPurple,

                  borderRadius: BorderRadius.circular(16),

                  border: Border.all(color: BuyerColors.border),
                ),

                child: Row(
                  children: [
                    Container(
                      height: 40,
                      width: 40,

                      decoration: BoxDecoration(
                        color: BuyerColors.white,

                        borderRadius: BorderRadius.circular(12),
                      ),

                      child: const Icon(
                        Icons.inventory_2_outlined,

                        color: BuyerColors.primary,

                        size: 21,
                      ),
                    ),

                    const SizedBox(width: 12),

                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,

                        children: [
                          const Text(
                            "Available Stock",

                            style: TextStyle(
                              fontSize: 11,

                              color: BuyerColors.textGrey,
                            ),
                          ),

                          const SizedBox(height: 3),

                          Text(
                            "${product.stock} items available",

                            style: const TextStyle(
                              fontSize: 13,

                              fontWeight: FontWeight.w700,

                              color: BuyerColors.textDark,
                            ),
                          ),
                        ],
                      ),
                    ),

                    const Icon(
                      Icons.check_circle_outline,

                      color: BuyerColors.success,

                      size: 21,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 22),

              // ========================================================
              // DESCRIPTION
              // ========================================================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(18),

                decoration: BuyerStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Row(
                      children: [
                        Container(
                          height: 38,
                          width: 38,

                          decoration: BoxDecoration(
                            color: BuyerColors.lightPurple,

                            borderRadius: BorderRadius.circular(11),
                          ),

                          child: const Icon(
                            Icons.description_outlined,

                            color: BuyerColors.primary,

                            size: 20,
                          ),
                        ),

                        const SizedBox(width: 10),

                        const Text(
                          "Description",

                          style: BuyerStyle.sectionTitle,
                        ),
                      ],
                    ),

                    const SizedBox(height: 14),

                    Text(
                      product.description,

                      style: const TextStyle(
                        fontSize: 13,

                        height: 1.5,

                        color: BuyerColors.textGrey,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 22),

              // ========================================================
              // DELIVERY INFORMATION
              // ========================================================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(18),

                decoration: BuyerStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text(
                      "Delivery Information",

                      style: BuyerStyle.sectionTitle,
                    ),

                    const SizedBox(height: 15),

                    _deliveryItem(
                      icon: Icons.local_shipping_outlined,

                      title: "Fast Delivery",

                      subtitle: "Get your order delivered to your address.",
                    ),

                    const SizedBox(height: 14),

                    _deliveryItem(
                      icon: Icons.verified_outlined,

                      title: "Verified Seller",

                      subtitle: "Products are listed by verified sellers.",
                    ),

                    const SizedBox(height: 14),

                    _deliveryItem(
                      icon: Icons.lock_outline,

                      title: "Secure Checkout",

                      subtitle: "Your checkout information is protected.",
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              // ========================================================
              // ADD TO CART
              // ========================================================
              SizedBox(
                width: double.infinity,

                height: 54,

                child: ElevatedButton.icon(
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        backgroundColor: BuyerColors.primaryDark,

                        behavior: SnackBarBehavior.floating,

                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),

                        content: const Row(
                          children: [
                            Icon(
                              Icons.check_circle_outline,

                              color: Colors.white,
                            ),

                            SizedBox(width: 10),

                            Text(
                              "Added to cart",
                              style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },

                  icon: const Icon(Icons.shopping_cart_outlined, size: 20),

                  label: const Text("Add to Cart"),

                  style: BuyerStyle.primaryButton,
                ),
              ),

              const SizedBox(height: 12),

              // ========================================================
              // BUY NOW
              // ========================================================
              SizedBox(
                width: double.infinity,

                height: 54,

                child: OutlinedButton.icon(
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text("Buy Now selected")),
                    );
                  },

                  icon: const Icon(Icons.flash_on_outlined, size: 20),

                  label: const Text("Buy Now"),

                  style: BuyerStyle.outlinedButton,
                ),
              ),

              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  // ================================================================
  // DELIVERY ITEM
  // ================================================================

  Widget _deliveryItem({
    required IconData icon,
    required String title,
    required String subtitle,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        Container(
          height: 40,
          width: 40,

          decoration: BoxDecoration(
            color: BuyerColors.lightPurple,

            borderRadius: BorderRadius.circular(12),
          ),

          child: Icon(icon, color: BuyerColors.primary, size: 20),
        ),

        const SizedBox(width: 12),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Text(title, style: BuyerStyle.cardTitle),

              const SizedBox(height: 3),

              Text(subtitle, style: BuyerStyle.small),
            ],
          ),
        ),
      ],
    );
  }
}
