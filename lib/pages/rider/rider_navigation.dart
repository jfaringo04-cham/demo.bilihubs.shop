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
  // ============================================================
  // TAB INDEXES
  // ============================================================

  static const int _homeIndex = 0;
  static const int _deliveriesIndex = 1;
  static const int _profileIndex = 4;

  static const int _firstTabIndex = _homeIndex;
  static const int _lastTabIndex = _profileIndex;

  int _currentIndex = _homeIndex;

  // Incrementing this value creates a fresh RiderDeliveries
  // instance and triggers a new API request.
  int _deliveriesRefreshVersion = 0;

  // ============================================================
  // BOTTOM NAVIGATION ITEMS
  // ============================================================

  static const List<BottomNavItem> _items = <BottomNavItem>[
    BottomNavItem(icon: Icons.home_outlined, label: 'Home'),
    BottomNavItem(icon: Icons.local_shipping_outlined, label: 'Deliveries'),
    BottomNavItem(icon: Icons.qr_code_scanner_rounded, label: 'Scanner'),
    BottomNavItem(icon: Icons.account_balance_wallet_outlined, label: 'Wallet'),
    BottomNavItem(icon: Icons.person_outline_rounded, label: 'Profile'),
  ];

  late final List<Widget> _pages;

  // ============================================================
  // INITIALIZE PAGES
  // ============================================================

  @override
  void initState() {
    super.initState();

    _pages = <Widget>[
      RiderHome(onNavigateTab: _changeTab),
      RiderDeliveries(key: ValueKey<int>(_deliveriesRefreshVersion)),
      RiderScanner(onOpenDeliveries: _openDeliveriesAfterScan),
      const Earnings(),
      const RiderProfile(),
    ];
  }

  // ============================================================
  // REFRESH DELIVERIES
  // ============================================================

  void _refreshDeliveries() {
    _deliveriesRefreshVersion++;

    _pages[_deliveriesIndex] = RiderDeliveries(
      key: ValueKey<int>(_deliveriesRefreshVersion),
    );
  }

  // ============================================================
  // CHANGE TAB
  // ============================================================

  void _changeTab(int index) {
    if (!mounted) return;

    if (index < _firstTabIndex || index > _lastTabIndex) {
      return;
    }

    // Refresh the data whenever the user opens
    // the Deliveries tab.
    //
    // This includes:
    // - Returning after accepting a pickup offer
    // - Opening My Deliveries from Rider Home
    // - Returning from another tab
    // - Tapping Deliveries again to reload
    if (index == _deliveriesIndex) {
      setState(() {
        _refreshDeliveries();
        _currentIndex = _deliveriesIndex;
      });

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
  // OPEN DELIVERIES AFTER QR SCAN
  // ============================================================

  void _openDeliveriesAfterScan() {
    if (!mounted) return;

    _changeTab(_deliveriesIndex);
  }

  // ============================================================
  // HANDLE ANDROID BACK BUTTON
  // ============================================================

  void _handleBack(bool didPop, Object? result) {
    if (didPop || !mounted) {
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
        body: IndexedStack(index: _currentIndex, children: _pages),
        bottomNavigationBar: BottomNav(
          currentIndex: _currentIndex,
          items: _items,
          onTap: _changeTab,
        ),
      ),
    );
  }
}
