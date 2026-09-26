import 'package:flutter/material.dart';

import '../../models/product_model.dart';
import '../../styles/buyer_style.dart';
import '../../widgets/product_card.dart';

import 'product_details.dart';

class BuyerHome extends StatelessWidget {
  const BuyerHome({super.key});

  @override
  Widget build(BuildContext context) {
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
    ];

    return Scaffold(
      backgroundColor: BuyerColors.background,

      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,

              padding: const EdgeInsets.fromLTRB(18, 14, 18, 25),

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  // ==========================================================
                  // HEADER
                  // ==========================================================

                  Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Container(
                        height: 45,
                        width: 45,
                        decoration: BoxDecoration(
                          color: BuyerColors.lightPurple,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Icon(
                          Icons.shopping_bag_outlined,
                          color: BuyerColors.primary,
                          size: 24,
                        ),
                      ),

                      const SizedBox(width: 11),

                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              "BiliHub",
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                                color: BuyerColors.textDark,
                              ),
                            ),
                            Text(
                              "Shop smart. Shop easy.",
                              style: BuyerStyle.small,
                            ),
                          ],
                        ),
                      ),

                      _headerButton(
                        icon: Icons.notifications_none,
                        onTap: () {},
                      ),

                      const SizedBox(width: 4),

                      _headerButton(
                        icon: Icons.shopping_cart_outlined,
                        onTap: () {},
                      ),
                    ],
                  ),

                  const SizedBox(height: 24),

                  // ==========================================================
                  // HERO TEXT
                  // ==========================================================
                  const Text("BILIHUB", style: BuyerStyle.welcome),

                  const SizedBox(height: 5),

                  const Text(
                    "Find something\nworth bringing home.",
                    style: BuyerStyle.largeTitle,
                  ),

                  const SizedBox(height: 9),

                  const Text(
                    "Browse products from different categories\n"
                    "and discover something that fits your needs.",
                    style: BuyerStyle.subtitle,
                  ),

                  const SizedBox(height: 18),

                  // ==========================================================
                  // SEARCH
                  // ==========================================================
                  Container(
                    decoration: BuyerStyle.search,

                    child: TextField(
                      textInputAction: TextInputAction.search,

                      decoration: InputDecoration(
                        hintText: "Search products...",

                        hintStyle: const TextStyle(
                          color: BuyerColors.textGrey,
                          fontSize: 13,
                        ),

                        prefixIcon: const Icon(
                          Icons.search,
                          color: BuyerColors.primary,
                        ),

                        suffixIcon: Container(
                          margin: const EdgeInsets.all(5),

                          decoration: BoxDecoration(
                            color: BuyerColors.primary,
                            borderRadius: BorderRadius.circular(10),
                          ),

                          child: const Icon(
                            Icons.search,
                            color: Colors.white,
                            size: 20,
                          ),
                        ),

                        border: InputBorder.none,

                        contentPadding: const EdgeInsets.symmetric(
                          vertical: 15,
                        ),
                      ),
                    ),
                  ),

                  const SizedBox(height: 25),

                  // ==========================================================
                  // QUICK FEATURES
                  // ==========================================================
                  Row(
                    children: [
                      Expanded(
                        child: _featureItem(
                          Icons.local_shipping_outlined,
                          "Fast Delivery",
                        ),
                      ),

                      Expanded(
                        child: _featureItem(
                          Icons.verified_user_outlined,
                          "Verified Sellers",
                        ),
                      ),

                      Expanded(
                        child: _featureItem(
                          Icons.lock_outline,
                          "Secure Payments",
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 28),

                  // ==========================================================
                  // CATEGORIES
                  // ==========================================================
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,

                    children: [
                      const Text(
                        "Shop by Category",
                        style: BuyerStyle.sectionTitle,
                      ),

                      TextButton(
                        onPressed: () {},
                        child: const Text(
                          "View All →",
                          style: TextStyle(
                            color: BuyerColors.primary,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 8),

                  SizedBox(
                    height: 105,

                    child: ListView(
                      scrollDirection: Axis.horizontal,

                      children: [
                        _categoryCard("Fashion", Icons.checkroom_outlined),

                        _categoryCard("Electronics", Icons.headphones_outlined),

                        _categoryCard("Home", Icons.home_outlined),

                        _categoryCard("Beauty", Icons.face_retouching_natural),

                        _categoryCard("Food", Icons.fastfood_outlined),

                        _categoryCard(
                          "Accessories",
                          Icons.shopping_bag_outlined,
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 24),

                  // ==========================================================
                  // FEATURED
                  // ==========================================================
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,

                    children: [
                      const Text(
                        "Featured Picks",
                        style: BuyerStyle.sectionTitle,
                      ),

                      TextButton(
                        onPressed: () {},
                        child: const Text(
                          "View All →",
                          style: TextStyle(
                            color: BuyerColors.primary,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 8),

                  // ==========================================================
                  // PRODUCTS
                  // ==========================================================
                  SizedBox(
                    height: 275,

                    child: ListView.builder(
                      scrollDirection: Axis.horizontal,

                      itemCount: products.length,

                      itemBuilder: (context, index) {
                        return Padding(
                          padding: const EdgeInsets.only(right: 12),

                          child: SizedBox(
                            width: constraints.maxWidth * 0.47,

                            child: ProductCard(
                              product: products[index],

                              onTap: () {
                                Navigator.push(
                                  context,

                                  MaterialPageRoute(
                                    builder: (context) => ProductDetails(
                                      product: products[index],
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
                        );
                      },
                    ),
                  ),

                  const SizedBox(height: 25),

                  // ==========================================================
                  // BILIHUB PROMO
                  // ==========================================================
                  Container(
                    width: double.infinity,

                    padding: const EdgeInsets.all(20),

                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [BuyerColors.primary, BuyerColors.primaryDark],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),

                      borderRadius: BorderRadius.circular(20),
                    ),

                    child: Row(
                      children: [
                        Container(
                          height: 55,
                          width: 55,

                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.15),
                            borderRadius: BorderRadius.circular(16),
                          ),

                          child: const Icon(
                            Icons.shopping_bag_outlined,
                            color: Colors.white,
                            size: 28,
                          ),
                        ),

                        const SizedBox(width: 14),

                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,

                            children: [
                              Text(
                                "Made for everyday finds.",
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 17,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),

                              SizedBox(height: 5),

                              Text(
                                "Discover products from different sellers "
                                "in one place.",
                                style: TextStyle(
                                  color: Colors.white70,
                                  fontSize: 11,
                                  height: 1.4,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 25),

                  // ==========================================================
                  // RECOMMENDED
                  // ==========================================================
                  const Text(
                    "Recommended For You",
                    style: BuyerStyle.sectionTitle,
                  ),

                  const SizedBox(height: 12),

                  Container(
                    width: double.infinity,

                    padding: const EdgeInsets.all(20),

                    decoration: BuyerStyle.card,

                    child: Column(
                      children: [
                        Container(
                          height: 55,
                          width: 55,

                          decoration: BoxDecoration(
                            color: BuyerColors.lightPurple,
                            borderRadius: BorderRadius.circular(16),
                          ),

                          child: const Icon(
                            Icons.auto_awesome_outlined,
                            color: BuyerColors.primary,
                            size: 27,
                          ),
                        ),

                        const SizedBox(height: 12),

                        const Text(
                          "More products coming soon",
                          style: BuyerStyle.productTitle,
                        ),

                        const SizedBox(height: 5),

                        const Text(
                          "We're preparing more finds for you.",
                          textAlign: TextAlign.center,
                          style: BuyerStyle.small,
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 25),

                  // ==========================================================
                  // TRUST FEATURES
                  // ==========================================================
                  Container(
                    width: double.infinity,

                    padding: const EdgeInsets.symmetric(vertical: 18),

                    decoration: BuyerStyle.card,

                    child: Row(
                      children: [
                        _trustItem(
                          Icons.local_shipping_outlined,
                          "Fast\nDelivery",
                        ),

                        _trustItem(
                          Icons.verified_outlined,
                          "Verified\nSellers",
                        ),

                        _trustItem(Icons.lock_outline, "Secure\nPayments"),
                      ],
                    ),
                  ),

                  const SizedBox(height: 15),

                  const Center(
                    child: Text(
                      "© 2026 BILIHUB. All rights reserved.",
                      style: TextStyle(
                        fontSize: 10,
                        color: BuyerColors.textGrey,
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  // ================================================================
  // HEADER BUTTON
  // ================================================================

  Widget _headerButton({required IconData icon, required VoidCallback onTap}) {
    return Material(
      color: Colors.transparent,

      child: InkWell(
        onTap: onTap,

        borderRadius: BorderRadius.circular(12),

        child: Container(
          height: 42,
          width: 42,

          decoration: BoxDecoration(
            color: BuyerColors.white,

            borderRadius: BorderRadius.circular(12),

            border: Border.all(color: BuyerColors.border),
          ),

          child: Icon(icon, size: 21, color: BuyerColors.textDark),
        ),
      ),
    );
  }

  // ================================================================
  // FEATURE
  // ================================================================

  Widget _featureItem(IconData icon, String title) {
    return Column(
      children: [
        Icon(icon, size: 21, color: BuyerColors.primary),

        const SizedBox(height: 5),

        Text(
          title,
          textAlign: TextAlign.center,

          style: const TextStyle(
            fontSize: 9.5,
            fontWeight: FontWeight.w600,
            color: BuyerColors.textGrey,
          ),
        ),
      ],
    );
  }

  // ================================================================
  // CATEGORY
  // ================================================================

  Widget _categoryCard(String title, IconData icon) {
    return Container(
      width: 92,

      margin: const EdgeInsets.only(right: 10),

      decoration: BoxDecoration(
        color: BuyerColors.white,

        borderRadius: BorderRadius.circular(15),

        border: Border.all(color: BuyerColors.border),
      ),

      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,

        children: [
          Container(
            height: 43,
            width: 43,

            decoration: BoxDecoration(
              color: BuyerColors.lightPurple,

              borderRadius: BorderRadius.circular(13),
            ),

            child: Icon(icon, color: BuyerColors.primary, size: 23),
          ),

          const SizedBox(height: 7),

          Text(title, style: BuyerStyle.category),
        ],
      ),
    );
  }

  // ================================================================
  // TRUST ITEM
  // ================================================================

  Widget _trustItem(IconData icon, String title) {
    return Expanded(
      child: Column(
        children: [
          Icon(icon, color: BuyerColors.primary, size: 22),

          const SizedBox(height: 6),

          Text(
            title,
            textAlign: TextAlign.center,

            style: const TextStyle(
              fontSize: 10,
              height: 1.25,
              fontWeight: FontWeight.w600,
              color: BuyerColors.textGrey,
            ),
          ),
        ],
      ),
    );
  }
}
