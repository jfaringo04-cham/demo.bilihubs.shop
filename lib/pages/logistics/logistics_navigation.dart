import 'package:flutter/material.dart';

import '../../widgets/bottom_nav.dart';

import 'logistics_home.dart';
import 'logistics_profile.dart';
import 'parcel_list.dart';
import 'parcel_scanner.dart';
import 'tracking_map.dart';

class LogisticsNavigation extends StatefulWidget {
  const LogisticsNavigation({super.key});

  @override
  State<LogisticsNavigation> createState() => _LogisticsNavigationState();
}

class _LogisticsNavigationState extends State<LogisticsNavigation> {
  static const int _homeIndex = 0;
  static const int _scannerIndex = 1;
  static const int _profileIndex = 4;

  static const int _firstTabIndex = _homeIndex;
  static const int _lastTabIndex = _profileIndex;

  int _currentIndex = _homeIndex;

  final List<BottomNavItem> _items = const <BottomNavItem>[
    BottomNavItem(icon: Icons.home_outlined, label: 'Home'),
    BottomNavItem(icon: Icons.qr_code_scanner_rounded, label: 'Scanner'),
    BottomNavItem(icon: Icons.location_on_outlined, label: 'Tracking'),
    BottomNavItem(icon: Icons.inventory_2_outlined, label: 'Parcels'),
    BottomNavItem(icon: Icons.person_outline_rounded, label: 'Profile'),
  ];

  late final List<Widget> _pages;

  @override
  void initState() {
    super.initState();

    _pages = <Widget>[
      LogisticsHome(
        onOpenScanner: () {
          _changeTab(_scannerIndex);
        },
      ),
      const ParcelScanner(),
      const TrackingMap(),
      const ParcelList(),
      const LogisticsProfile(),
    ];
  }

  // ============================================================
  // CHANGE TAB
  // ============================================================

  void _changeTab(int index) {
    if (index < _firstTabIndex || index > _lastTabIndex) {
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

  void _handleBack(bool didPop, Object? result) {
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
