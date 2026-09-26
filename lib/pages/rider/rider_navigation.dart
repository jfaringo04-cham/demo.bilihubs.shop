import 'package:flutter/material.dart';

import '../../widgets/bottom_nav.dart';

import 'rider_home.dart';
import 'rider_deliveries.dart';
import 'rider_scanner.dart';
import 'rider_earnings.dart';
import 'rider_profile.dart';

class RiderNavigation extends StatefulWidget {
  const RiderNavigation({super.key});

  @override
  State<RiderNavigation> createState() => _RiderNavigationState();
}

class _RiderNavigationState extends State<RiderNavigation> {
  int currentIndex = 0;

  final List<BottomNavItem> items = [
    BottomNavItem(icon: Icons.home_outlined, label: "Home"),

    BottomNavItem(icon: Icons.local_shipping_outlined, label: "Deliveries"),

    BottomNavItem(icon: Icons.qr_code_scanner, label: "Scanner"),

    BottomNavItem(
      icon: Icons.account_balance_wallet_outlined,

      label: "Earnings",
    ),

    BottomNavItem(icon: Icons.person_outline, label: "Profile"),
  ];

  late final List<Widget> pages;

  @override
  void initState() {
    super.initState();

    pages = [
      const RiderHome(),

      const RiderDeliveries(),

      const RiderScanner(),

      const Earnings(),

      const RiderProfile(
        name: "Rider Name",

        email: "rider@email.com",

        phone: "09123456789",

        riderId: "RID-001",

        vehicle: "Motorcycle",
      ),
    ];
  }

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
