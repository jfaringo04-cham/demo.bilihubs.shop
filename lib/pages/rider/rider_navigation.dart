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
  static const int _homeIndex = 0;
  static const int _lastTabIndex = 4;

  int currentIndex = _homeIndex;

  final List<BottomNavItem> items = const [
    BottomNavItem(icon: Icons.home_outlined, label: 'Home'),
    BottomNavItem(icon: Icons.local_shipping_outlined, label: 'Deliveries'),
    BottomNavItem(icon: Icons.qr_code_scanner, label: 'Scanner'),
    BottomNavItem(
      icon: Icons.account_balance_wallet_outlined,
      label: 'Earnings',
    ),
    BottomNavItem(icon: Icons.person_outline, label: 'Profile'),
  ];

  void _changeTab(int index) {
    if (index < _homeIndex || index > _lastTabIndex) {
      return;
    }

    if (index == currentIndex) {
      return;
    }

    setState(() {
      currentIndex = index;
    });
  }

  Widget _buildCurrentPage() {
    switch (currentIndex) {
      case 0:
        return RiderHome(onNavigateTab: _changeTab);

      case 1:
        return const RiderDeliveries();

      case 2:
        return const RiderScanner();

      case 3:
        return const Earnings();

      case 4:
        return const RiderProfile();

      default:
        return RiderHome(onNavigateTab: _changeTab);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: currentIndex == _homeIndex,
      onPopInvokedWithResult: (bool didPop, dynamic result) {
        if (didPop) {
          return;
        }

        if (currentIndex != _homeIndex) {
          setState(() {
            currentIndex = _homeIndex;
          });
        }
      },
      child: Scaffold(
        body: _buildCurrentPage(),

        bottomNavigationBar: BottomNav(
          currentIndex: currentIndex,
          items: items,
          onTap: _changeTab,
        ),
      ),
    );
  }
}
