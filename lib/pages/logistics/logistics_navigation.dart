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
  int _currentIndex = 0;

  // ============================================================
  // BOTTOM NAV ITEMS
  // ============================================================

  final List<BottomNavItem> _items = const [
    BottomNavItem(icon: Icons.home_outlined, label: 'Home'),
    BottomNavItem(icon: Icons.qr_code_scanner_rounded, label: 'Scanner'),
    BottomNavItem(icon: Icons.location_on_outlined, label: 'Tracking'),
    BottomNavItem(icon: Icons.inventory_2_outlined, label: 'Parcels'),
    BottomNavItem(icon: Icons.person_outline_rounded, label: 'Profile'),
  ];

  // ============================================================
  // CHANGE TAB
  // ============================================================

  void _changeTab(int index) {
    if (index < 0 || index >= _items.length || index == _currentIndex) {
      return;
    }

    setState(() {
      _currentIndex = index;
    });
  }

  // ============================================================
  // BUILD PAGE
  // ============================================================

  Widget _buildCurrentPage() {
    switch (_currentIndex) {
      case 0:
        return LogisticsHome(
          onOpenScanner: () {
            _changeTab(1);
          },
        );

      case 1:
        return const ParcelScanner();

      case 2:
        return const TrackingMap();

      case 3:
        return const ParcelList();

      case 4:
        return const LogisticsProfile();

      default:
        return LogisticsHome(
          onOpenScanner: () {
            _changeTab(1);
          },
        );
    }
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,

      body: SafeArea(top: false, bottom: false, child: _buildCurrentPage()),

      bottomNavigationBar: BottomNav(
        currentIndex: _currentIndex,
        items: _items,
        onTap: _changeTab,
      ),
    );
  }
}
