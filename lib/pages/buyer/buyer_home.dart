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
              physics: const BouncingScrollPhysics(),
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: const EdgeInsets.fromLTRB(18, 14, 18, 30),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // ==========================================================
                  // HEADER
                  // ==========================================================

                  Row(
                    children: [
                      // Logo
                      Container(
                        height: 46,
                        width: 46,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [
                              BuyerColors.primary,
                              BuyerColors.primaryDark,
                            ],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(15),
                          boxShadow: [
                            BoxShadow(
                              color: BuyerColors.primary.withValues(
                                alpha: 0.20,
                              ),
                              blurRadius: 12,
                              offset: const Offset(0, 5),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.shopping_bag_outlined,
                          color: Colors.white,
                          size: 24,
                        ),
                      ),

                      const SizedBox(width: 12),

                      // Brand
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              "BiliHub",
                              style: TextStyle(
                                fontSize: 19,
                                fontWeight: FontWeight.w800,
                                color: BuyerColors.textDark,
                                letterSpacing: -0.3,
                              ),
                            ),
                            SizedBox(height: 2),
                            Text(
                              "Shop smart. Shop easy.",
                              style: BuyerStyle.small,
                            ),
                          ],
                        ),
                      ),

                      // Notification
                      _headerButton(
                        icon: Icons.notifications_none_rounded,
                        badge: true,
                        onTap: () {},
                      ),

                      const SizedBox(width: 7),

                      // Cart
                      _headerButton(
                        icon: Icons.shopping_cart_outlined,
                        onTap: () {},
                      ),
                    ],
                  ),

                  const SizedBox(height: 25),

                  // ==========================================================
                  // GREETING / HERO
                  // ==========================================================
                  const Text("WELCOME TO BILIHUB", style: BuyerStyle.welcome),

                  const SizedBox(height: 6),

                  const Text(
                    "Find something\nworth bringing home.",
                    style: BuyerStyle.largeTitle,
                  ),

                  const SizedBox(height: 9),

                  const Text(
                    "Discover products from different sellers "
                    "and find something made for you.",
                    style: BuyerStyle.subtitle,
                  ),

                  const SizedBox(height: 19),

                  // ==========================================================
                  // SEARCH BAR
                  // ==========================================================
                  Container(
                    height: 56,
                    decoration: BoxDecoration(
                      color: BuyerColors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: BuyerColors.border),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.025),
                          blurRadius: 12,
                          offset: const Offset(0, 5),
                        ),
                      ],
                    ),
                    child: Row(
                      children: [
                        const SizedBox(width: 15),

                        const Icon(
                          Icons.search_rounded,
                          color: BuyerColors.primary,
                          size: 23,
                        ),

                        const SizedBox(width: 10),

                        const Expanded(
                          child: TextField(
                            textInputAction: TextInputAction.search,
                            decoration: InputDecoration(
                              hintText: "Search products...",
                              hintStyle: TextStyle(
                                color: BuyerColors.textGrey,
                                fontSize: 13,
                              ),
                              border: InputBorder.none,
                              isDense: true,
                            ),
                          ),
                        ),

                        Container(
                          height: 40,
                          width: 40,
                          margin: const EdgeInsets.only(right: 7),
                          decoration: BoxDecoration(
                            color: BuyerColors.lightPurple,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Icon(
                            Icons.tune_rounded,
                            color: BuyerColors.primary,
                            size: 20,
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // ==========================================================
                  // QUICK BENEFITS
                  // ==========================================================
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 13,
                    ),
                    decoration: BoxDecoration(
                      color: BuyerColors.white,
                      borderRadius: BorderRadius.circular(17),
                      border: Border.all(color: BuyerColors.border),
                    ),
                    child: Row(
                      children: [
                        _miniBenefit(
                          icon: Icons.local_shipping_outlined,
                          title: "Fast Delivery",
                        ),
                        _benefitDivider(),
                        _miniBenefit(
                          icon: Icons.verified_user_outlined,
                          title: "Verified Sellers",
                        ),
                        _benefitDivider(),
                        _miniBenefit(
                          icon: Icons.lock_outline_rounded,
                          title: "Secure Payment",
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 27),

                  // ==========================================================
                  // PROMO BANNER
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
                      borderRadius: BorderRadius.circular(22),
                      boxShadow: [
                        BoxShadow(
                          color: BuyerColors.primary.withValues(alpha: 0.22),
                          blurRadius: 18,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    child: Stack(
                      children: [
                        // Decorative circle
                        Positioned(
                          right: -35,
                          top: -45,
                          child: Container(
                            height: 125,
                            width: 125,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: Colors.white.withValues(alpha: 0.07),
                            ),
                          ),
                        ),

                        // Decorative circle
                        Positioned(
                          right: 35,
                          bottom: -60,
                          child: Container(
                            height: 100,
                            width: 100,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: Colors.white.withValues(alpha: 0.05),
                            ),
                          ),
                        ),

                        Row(
                          children: [
                            Container(
                              height: 58,
                              width: 58,
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.14),
                                borderRadius: BorderRadius.circular(17),
                              ),
                              child: const Icon(
                                Icons.local_mall_outlined,
                                color: Colors.white,
                                size: 29,
                              ),
                            ),

                            const SizedBox(width: 15),

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
                                    "Shop products from different "
                                    "sellers in one place.",
                                    style: TextStyle(
                                      color: Colors.white70,
                                      fontSize: 11.5,
                                      height: 1.4,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 28),

                  // ==========================================================
                  // CATEGORIES
                  // ==========================================================
                  _sectionHeader(
                    title: "Shop by Category",
                    action: "View All",
                    onTap: () {},
                  ),

                  const SizedBox(height: 12),

                  SizedBox(
                    height: 112,
                    child: ListView(
                      scrollDirection: Axis.horizontal,
                      physics: const BouncingScrollPhysics(),
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

                  const SizedBox(height: 28),

                  // ==========================================================
                  // FEATURED PRODUCTS
                  // ==========================================================
                  _sectionHeader(
                    title: "Featured Picks",
                    action: "View All",
                    onTap: () {},
                  ),

                  const SizedBox(height: 12),

                  SizedBox(
                    height: 290,
                    child: ListView.builder(
                      scrollDirection: Axis.horizontal,
                      physics: const BouncingScrollPhysics(),
                      itemCount: products.length,
                      itemBuilder: (context, index) {
                        return Padding(
                          padding: EdgeInsets.only(
                            right: index == products.length - 1 ? 0 : 13,
                          ),
                          child: SizedBox(
                            width: constraints.maxWidth * 0.48,
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

                  const SizedBox(height: 28),

                  // ==========================================================
                  // SHOPPING HIGHLIGHT
                  // ==========================================================
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: BuyerColors.lightPurple,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: BuyerColors.softPurple),
                    ),
                    child: Row(
                      children: [
                        Container(
                          height: 52,
                          width: 52,
                          decoration: BoxDecoration(
                            color: BuyerColors.white,
                            borderRadius: BorderRadius.circular(15),
                          ),
                          child: const Icon(
                            Icons.auto_awesome_outlined,
                            color: BuyerColors.primary,
                            size: 26,
                          ),
                        ),

                        const SizedBox(width: 14),

                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                "Discover something new",
                                style: BuyerStyle.productTitle,
                              ),
                              SizedBox(height: 4),
                              Text(
                                "Explore products selected for "
                                "your everyday needs.",
                                style: BuyerStyle.small,
                              ),
                            ],
                          ),
                        ),

                        Container(
                          height: 34,
                          width: 34,
                          decoration: BoxDecoration(
                            color: BuyerColors.white,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: const Icon(
                            Icons.arrow_forward_rounded,
                            color: BuyerColors.primary,
                            size: 18,
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 28),

                  // ==========================================================
                  // RECOMMENDED
                  // ==========================================================
                  _sectionHeader(
                    title: "Recommended For You",
                    action: "See More",
                    onTap: () {},
                  ),

                  const SizedBox(height: 12),

                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: BuyerColors.white,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: BuyerColors.border),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.025),
                          blurRadius: 14,
                          offset: const Offset(0, 5),
                        ),
                      ],
                    ),
                    child: Column(
                      children: [
                        Container(
                          height: 62,
                          width: 62,
                          decoration: BoxDecoration(
                            color: BuyerColors.lightPurple,
                            borderRadius: BorderRadius.circular(18),
                          ),
                          child: const Icon(
                            Icons.auto_awesome_outlined,
                            color: BuyerColors.primary,
                            size: 28,
                          ),
                        ),

                        const SizedBox(height: 13),

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

                        const SizedBox(height: 14),

                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 7,
                          ),
                          decoration: BoxDecoration(
                            color: BuyerColors.lightPurple,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: const Text(
                            "Stay tuned",
                            style: TextStyle(
                              color: BuyerColors.primary,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 28),

                  // ==========================================================
                  // WHY BILIHUB
                  // ==========================================================
                  const Text(
                    "Why Shop With BiliHub?",
                    style: BuyerStyle.sectionTitle,
                  ),

                  const SizedBox(height: 12),

                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(
                      vertical: 20,
                      horizontal: 10,
                    ),
                    decoration: BoxDecoration(
                      color: BuyerColors.white,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: BuyerColors.border),
                    ),
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
                        _trustItem(
                          Icons.lock_outline_rounded,
                          "Secure\nPayments",
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 25),

                  // ==========================================================
                  // FOOTER
                  // ==========================================================
                  Center(
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Container(
                              height: 24,
                              width: 24,
                              decoration: BoxDecoration(
                                color: BuyerColors.lightPurple,
                                borderRadius: BorderRadius.circular(7),
                              ),
                              child: const Icon(
                                Icons.shopping_bag_outlined,
                                color: BuyerColors.primary,
                                size: 14,
                              ),
                            ),
                            const SizedBox(width: 7),
                            const Text(
                              "BiliHub",
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                                color: BuyerColors.textDark,
                              ),
                            ),
                          ],
                        ),

                        const SizedBox(height: 7),

                        const Text(
                          "Shop smart. Shop easy.",
                          style: TextStyle(
                            fontSize: 10,
                            color: BuyerColors.textGrey,
                          ),
                        ),

                        const SizedBox(height: 4),

                        const Text(
                          "© 2026 BILIHUB. All rights reserved.",
                          style: TextStyle(
                            fontSize: 9,
                            color: BuyerColors.textGrey,
                          ),
                        ),
                      ],
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
  // SECTION HEADER
  // ================================================================

  Widget _sectionHeader({
    required String title,
    required String action,
    required VoidCallback onTap,
  }) {
    return Row(
      children: [
        Expanded(child: Text(title, style: BuyerStyle.sectionTitle)),

        InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(10),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 5),
            child: Row(
              children: [
                Text(
                  action,
                  style: const TextStyle(
                    color: BuyerColors.primary,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(width: 3),
                const Icon(
                  Icons.arrow_forward_ios_rounded,
                  color: BuyerColors.primary,
                  size: 11,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  // ================================================================
  // HEADER BUTTON
  // ================================================================

  Widget _headerButton({
    required IconData icon,
    required VoidCallback onTap,
    bool badge = false,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(13),
        child: Stack(
          children: [
            Container(
              height: 43,
              width: 43,
              decoration: BoxDecoration(
                color: BuyerColors.white,
                borderRadius: BorderRadius.circular(13),
                border: Border.all(color: BuyerColors.border),
              ),
              child: Icon(icon, size: 21, color: BuyerColors.textDark),
            ),

            if (badge)
              Positioned(
                top: 7,
                right: 7,
                child: Container(
                  height: 7,
                  width: 7,
                  decoration: const BoxDecoration(
                    color: BuyerColors.danger,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  // ================================================================
  // MINI BENEFIT
  // ================================================================

  Widget _miniBenefit({required IconData icon, required String title}) {
    return Expanded(
      child: Column(
        children: [
          Container(
            height: 31,
            width: 31,
            decoration: BoxDecoration(
              color: BuyerColors.lightPurple,
              borderRadius: BorderRadius.circular(9),
            ),
            child: Icon(icon, color: BuyerColors.primary, size: 17),
          ),

          const SizedBox(height: 5),

          Text(
            title,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 8.5,
              height: 1.2,
              fontWeight: FontWeight.w600,
              color: BuyerColors.textGrey,
            ),
          ),
        ],
      ),
    );
  }

  // ================================================================
  // BENEFIT DIVIDER
  // ================================================================

  Widget _benefitDivider() {
    return Container(height: 35, width: 1, color: BuyerColors.border);
  }

  // ================================================================
  // CATEGORY CARD
  // ================================================================

  Widget _categoryCard(String title, IconData icon) {
    return Container(
      width: 96,
      margin: const EdgeInsets.only(right: 10),
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        color: BuyerColors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: BuyerColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.025),
            blurRadius: 9,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            height: 46,
            width: 46,
            decoration: BoxDecoration(
              color: BuyerColors.lightPurple,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(icon, color: BuyerColors.primary, size: 23),
          ),

          const SizedBox(height: 8),

          Text(
            title,
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: BuyerStyle.category,
          ),
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
          Container(
            height: 39,
            width: 39,
            decoration: BoxDecoration(
              color: BuyerColors.lightPurple,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: BuyerColors.primary, size: 20),
          ),

          const SizedBox(height: 7),

          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 9.5,
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
