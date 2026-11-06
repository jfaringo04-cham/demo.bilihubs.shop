import 'package:flutter/material.dart';

import '../../widgets/bottom_nav.dart';

import 'logistics_home.dart';
import 'logistics_profile.dart';
import 'logistics_wallet.dart';
import 'parcel_list.dart';
import 'parcel_scanner.dart';
import 'tracking_map.dart';

class LogisticsNavigation extends StatefulWidget {
  const LogisticsNavigation({super.key});

  @override
  State<LogisticsNavigation> createState() => _LogisticsNavigationState();
}

class _LogisticsNavigationState extends State<LogisticsNavigation> {
  // ============================================================
  // TAB INDEXES
  // ============================================================
  static const int _homeIndex = 0;
  static const int _scannerIndex = 1;
  static const int _trackingIndex = 2;
  static const int _profileIndex = 5;

  int _currentIndex = _homeIndex;

  // ============================================================
  // BOTTOM NAVIGATION ITEMS
  // ============================================================

  static const List<BottomNavItem> _items = <BottomNavItem>[
    BottomNavItem(icon: Icons.home_outlined, label: 'Home'),
    BottomNavItem(icon: Icons.qr_code_scanner_rounded, label: 'Scanner'),
    BottomNavItem(icon: Icons.location_on_outlined, label: 'Tracking'),
    BottomNavItem(icon: Icons.inventory_2_outlined, label: 'Parcels'),
    BottomNavItem(icon: Icons.account_balance_wallet_outlined, label: 'Wallet'),
    BottomNavItem(icon: Icons.person_outline_rounded, label: 'Profile'),
  ];

  // ============================================================
  // PERSISTENT PAGES
  // ============================================================

  late final Widget _homePage;
  late final Widget _parcelsPage;
  late final Widget _walletPage;
  late final Widget _profilePage;

  @override
  void initState() {
    super.initState();

    _homePage = LogisticsHome(onOpenScanner: _openScanner);

    _parcelsPage = const ParcelList();

    _walletPage = const LogisticsWallet();

    _profilePage = const LogisticsProfile();
  }

  // ============================================================
  // OPEN SCANNER FROM HOME
  // ============================================================

  void _openScanner() {
    _changeTab(_scannerIndex);
  }

  // ============================================================
  // CHANGE TAB
  // ============================================================

  void _changeTab(int index) {
    if (!mounted) return;

    if (index < _homeIndex || index > _profileIndex) {
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
  // HANDLE ANDROID BACK BUTTON
  // ============================================================

  void _handleBack(bool didPop, Object? result) {
    if (didPop || !mounted) {
      return;
    }

    if (_currentIndex != _homeIndex) {
      _changeTab(_homeIndex);
    }
  }

  // ============================================================
  // BUILD PAGES
  // ============================================================

  List<Widget> _buildPages() {
    return <Widget>[
      // Home remains mounted when switching tabs.
      _homePage,

      // Scanner is mounted ONLY while its tab is active.
      //
      // This prevents the camera from continuing to scan
      // QR codes while the user is on another page.
      if (_currentIndex == _scannerIndex)
        const ParcelScanner()
      else
        const SizedBox.shrink(),

      // Tracking Map is mounted ONLY while active.
      //
      // The map's timer and network polling stop when
      // the user leaves its tab because TrackingMap
      // is disposed.
      if (_currentIndex == _trackingIndex)
        const TrackingMap()
      else
        const SizedBox.shrink(),

      // These pages stay mounted to preserve UI state.
      _parcelsPage,
      _walletPage,
      _profilePage,
    ];
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return PopScope(
      // On Home, allow the enclosing route to pop.
      // On other tabs, Android Back returns to Home.
      canPop: _currentIndex == _homeIndex,
      onPopInvokedWithResult: _handleBack,
      child: Scaffold(
        backgroundColor: Colors.white,

        // Keep the non-camera pages alive while switching.
        // Scanner and Tracking Map are intentionally
        // recreated when their tabs become active.
        body: IndexedStack(index: _currentIndex, children: _buildPages()),

        bottomNavigationBar: BottomNav(
          currentIndex: _currentIndex,
          items: _items,
          onTap: _changeTab,
        ),
      ),
    );
  }
}
