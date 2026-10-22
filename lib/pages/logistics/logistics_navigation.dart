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
  late final List<Widget> pages;

  final List<BottomNavItem> items = const [
    BottomNavItem(icon: Icons.home_outlined, label: 'Home'),
    BottomNavItem(icon: Icons.qr_code_scanner, label: 'Scanner'),
    BottomNavItem(icon: Icons.location_on_outlined, label: 'Tracking'),
    BottomNavItem(icon: Icons.inventory_2_outlined, label: 'Parcels'),
    BottomNavItem(icon: Icons.person_outline, label: 'Profile'),
  ];

  @override
  void initState() {
    super.initState();
    pages = [
      LogisticsHome(onOpenScanner: () => _changeTab(1)),
      const ParcelScanner(),
      const TrackingMap(),
      const ParcelList(),
      const LogisticsProfile(),
    ];
  }

  void _changeTab(int index) {
    if (index < 0 || index >= items.length || index == currentIndex) return;
    setState(() => currentIndex = index);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: currentIndex, children: pages),
      bottomNavigationBar: BottomNav(
        currentIndex: currentIndex,
        items: items,
        onTap: _changeTab,
      ),
    );
  }
}
