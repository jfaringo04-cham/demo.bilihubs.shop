import 'package:flutter/material.dart';

import '../../widgets/bottom_nav.dart';

import 'logistics_home.dart';
import 'parcel_scanner.dart';
import 'tracking_map.dart';
import 'parcel_list.dart';
import 'logistics_profile.dart';

class LogisticsNavigation extends StatefulWidget {
  const LogisticsNavigation({super.key});

  @override
  State<LogisticsNavigation> createState() => _LogisticsNavigationState();
}

class _LogisticsNavigationState extends State<LogisticsNavigation> {
  int currentIndex = 0;

  final List<BottomNavItem> items = [
    BottomNavItem(icon: Icons.home_outlined, label: 'Home'),
    BottomNavItem(icon: Icons.qr_code_scanner, label: 'Scanner'),
    BottomNavItem(icon: Icons.location_on_outlined, label: 'Tracking'),
    BottomNavItem(icon: Icons.inventory_2_outlined, label: 'Parcels'),
    BottomNavItem(icon: Icons.person_outline, label: 'Profile'),
  ];

  // ============================================================
  // CHANGE TAB
  // ============================================================

  void _changeTab(int index) {
    if (index < 0 || index >= items.length || index == currentIndex) {
      return;
    }

    setState(() {
      currentIndex = index;
    });
  }

  // ============================================================
  // BUILD CURRENT PAGE
  // ============================================================

  Widget _buildCurrentPage() {
    switch (currentIndex) {
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
      body: _buildCurrentPage(),

      bottomNavigationBar: BottomNav(
        currentIndex: currentIndex,
        items: items,
        onTap: _changeTab,
      ),
    );
  }
}
