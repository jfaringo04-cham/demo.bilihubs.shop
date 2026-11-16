import 'package:flutter/material.dart';

import '../../widgets/bottom_nav.dart';

import 'cod_remittance.dart';
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
  static const int _walletIndex = 3;
  static const int _profileIndex = 4;

  static const int _firstTabIndex = _homeIndex;
  static const int _lastTabIndex = _profileIndex;

  int _currentIndex = _homeIndex;

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
  // OPEN COD REMITTANCE SCREEN
  // ============================================================

  Future<void> _openCodRemittance() async {
    if (!mounted) return;

    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return const CodRemittance();
        },
      ),
    );
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

        // ======================================================
        // COD REMITTANCE SHORTCUT ON RIDER WALLET TAB
        // ======================================================
        floatingActionButton: _currentIndex == _walletIndex
            ? FloatingActionButton.extended(
                heroTag: 'rider_cod_remittance_button',
                onPressed: _openCodRemittance,
                backgroundColor: const Color(0xFF4F46E5),
                foregroundColor: Colors.white,
                elevation: 3,
                icon: const Icon(Icons.payments_outlined, size: 21),
                label: const Text(
                  'COD Remittance',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
              )
            : null,

        floatingActionButtonLocation: FloatingActionButtonLocation.endFloat,

        // ======================================================
        // EXISTING BOTTOM NAVIGATION
        // ======================================================
        bottomNavigationBar: BottomNav(
          currentIndex: _currentIndex,
          items: _items,
          onTap: _changeTab,
        ),
      ),
    );
  }
}
