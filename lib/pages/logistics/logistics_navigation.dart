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
    BottomNavItem(icon: Icons.home_outlined, label: "Home"),

    BottomNavItem(icon: Icons.qr_code_scanner, label: "Scanner"),

    BottomNavItem(icon: Icons.location_on_outlined, label: "Tracking"),

    BottomNavItem(icon: Icons.inventory_2_outlined, label: "Parcels"),

    BottomNavItem(icon: Icons.person_outline, label: "Profile"),
  ];

  late final List<Widget> pages;

  @override
  void initState() {
    super.initState();

    pages = [
      const LogisticsHome(),

      const ParcelScanner(),

      const TrackingMap(),

      const ParcelList(),

      const LogisticsProfile(
        name: "Logistics Staff",

        email: "staff@email.com",

        employeeId: "LOG-001",

        department: "Operations",
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
