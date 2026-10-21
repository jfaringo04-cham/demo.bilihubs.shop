import 'package:flutter/material.dart';

import '../../sevices/cart_service.dart';
import '../../widgets/bottom_nav.dart';

import 'buyer_home.dart';
import 'product_list.dart';
import 'cart.dart';
import 'order_history.dart';
import 'buyer_profile.dart';

class BuyerNavigation extends StatefulWidget {
  final Map<String, dynamic> user;

  const BuyerNavigation({super.key, required this.user});

  @override
  State<BuyerNavigation> createState() => _BuyerNavigationState();
}

class _BuyerNavigationState extends State<BuyerNavigation> {
  int currentIndex = 0;

  late final List<Widget> pages;

  @override
  void initState() {
    super.initState();

    pages = [
      const BuyerHome(),
      const ProductList(),
      const CartPage(),
      const OrderHistory(),
      BuyerProfile(user: widget.user),
    ];
  }

  List<BottomNavItem> _buildItems(int cartCount) {
    return [
      const BottomNavItem(icon: Icons.home_outlined, label: "Home"),

      const BottomNavItem(icon: Icons.shopping_bag_outlined, label: "Products"),

      BottomNavItem(
        icon: Icons.shopping_cart_outlined,
        label: "Cart",
        badgeCount: cartCount,
      ),

      const BottomNavItem(icon: Icons.receipt_long_outlined, label: "Orders"),

      const BottomNavItem(icon: Icons.person_outline, label: "Profile"),
    ];
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<int>(
      valueListenable: CartService.itemCountNotifier,
      builder: (context, cartCount, child) {
        return Scaffold(
          body: IndexedStack(index: currentIndex, children: pages),

          bottomNavigationBar: BottomNav(
            currentIndex: currentIndex,
            items: _buildItems(cartCount),
            onTap: (index) {
              setState(() {
                currentIndex = index;
              });
            },
          ),
        );
      },
    );
  }
}
