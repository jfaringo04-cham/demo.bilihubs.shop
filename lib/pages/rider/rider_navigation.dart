import 'package:flutter/material.dart';

import '../../widgets/bottom_nav.dart';

import 'rider_deliveries.dart';
import 'rider_earnings.dart';
import 'rider_home.dart';
import 'rider_profile.dart';
import 'rider_scanner.dart';

class RiderNavigation extends StatefulWidget {
  const RiderNavigation({super.key});

  @override
  State<RiderNavigation> createState() => _RiderNavigationState();
}

class _RiderNavigationState extends State<RiderNavigation> {
  static const int _homeIndex = 0;
  static const int _profileIndex = 4;
  static const int _lastTabIndex = _profileIndex;

  int _currentIndex = _homeIndex;

  final List<BottomNavItem> _items = const <BottomNavItem>[
    BottomNavItem(
      icon: Icons.home_outlined,
      label: 'Home',
    ),
    BottomNavItem(
      icon: Icons.local_shipping_outlined,
      label: 'Deliveries',
    ),
    BottomNavItem(
      icon: Icons.qr_code_scanner_rounded,
      label: 'Scanner',
    ),
    BottomNavItem(
      icon: Icons.account_balance_wallet_outlined,
      label: 'Earnings',
    ),
    BottomNavItem(
      icon: Icons.person_outline_rounded,
      label: 'Profile',
    ),
  ];

  late final List<Widget> _pages;

  @override
  void initState() {
    super.initState();

    _pages = <Widget>[
      RiderHome(
        onNavigateTab: _changeTab,
      ),
      const RiderDeliveries(),
      const RiderScanner(),
      const Earnings(),
      const RiderProfile(),
    ];
  }

  // ============================================================
  // CHANGE TAB
  // ============================================================

  void _changeTab(int index) {
    if (index < _homeIndex || index > _lastTabIndex) {
      return;
    }

    if (index == _currentIndex) {
      return;
    }

    setState(() {
      _currentIndex = index;
    });
  }

  // ============================================================
  // HANDLE BACK
  // ============================================================

  void _handleBack(
    bool didPop,
    dynamic result,
  ) {
    if (didPop) {
      return;
    }

    if (_currentIndex != _homeIndex) {
      setState(() {
        _currentIndex = _homeIndex;
      });
    }
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: _currentIndex == _homeIndex,
      onPopInvokedWithResult: _handleBack,
      child: Scaffold(
        backgroundColor: Colors.white,
        body: IndexedStack(
          index: _currentIndex,
          children: _pages,
        ),
        bottomNavigationBar: BottomNav(
          currentIndex: _currentIndex,
          items: _items,
          onTap: _changeTab,
        ),
      ),
    );
  }
}