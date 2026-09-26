import 'package:flutter/material.dart';

import '../../widgets/bottom_nav.dart';

import 'buyer_home.dart';
import 'product_list.dart';
import 'cart.dart';
import 'order_history.dart';
import 'buyer_profile.dart';

class BuyerNavigation extends StatefulWidget {
  const BuyerNavigation({super.key});

  @override
  State<BuyerNavigation> createState() => _BuyerNavigationState();
}

class _BuyerNavigationState extends State<BuyerNavigation> {
  int currentIndex = 0;

  final List<BottomNavItem> items = [
    BottomNavItem(icon: Icons.home_outlined, label: "Home"),

    BottomNavItem(icon: Icons.shopping_bag_outlined, label: "Products"),

    BottomNavItem(icon: Icons.shopping_cart_outlined, label: "Cart"),

    BottomNavItem(icon: Icons.receipt_long_outlined, label: "Orders"),

    BottomNavItem(icon: Icons.person_outline, label: "Profile"),
  ];

  final List<Widget> pages = [
    const BuyerHome(),

    const ProductList(),

    const CartPage(),

    const OrderHistory(),

    const BuyerProfile(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: currentIndex, children: pages),

      bottomNavigationBar: BottomNav(
        currentIndex: currentIndex,

        items: items,

        onTap: (index) {
          setState(() {
            currentIndex = index;
          });
        },
      ),
    );
  }
}
