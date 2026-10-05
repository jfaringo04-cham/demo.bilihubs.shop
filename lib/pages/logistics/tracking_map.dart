import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../styles/logistics_style.dart';

class TrackingMap extends StatefulWidget {
  const TrackingMap({super.key});

  @override
  State<TrackingMap> createState() => _TrackingMapState();
}

class _TrackingMapState extends State<TrackingMap> {
  final MapController _mapController = MapController();

  // ------------------------------------------------------------
  // DEMO LOCATIONS
  // Around Santa Cruz, Laguna
  // ------------------------------------------------------------

  static const LatLng santaCruzCenter = LatLng(14.2854, 121.4134);

  // Demo rider location
  static const LatLng riderLocation = LatLng(14.2818, 121.4112);

  // Demo pickup location
  static const LatLng pickupLocation = LatLng(14.2885, 121.4075);

  // Demo customer destination
  static const LatLng destinationLocation = LatLng(14.2932, 121.4182);

  // Demo route connecting rider -> destination
  final List<LatLng> demoRoute = const [
    LatLng(14.2818, 121.4112),
    LatLng(14.2830, 121.4105),
    LatLng(14.2844, 121.4097),
    LatLng(14.2858, 121.4104),
    LatLng(14.2870, 121.4120),
    LatLng(14.2885, 121.4132),
    LatLng(14.2900, 121.4145),
    LatLng(14.2915, 121.4160),
    LatLng(14.2932, 121.4182),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: LogisticsColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        surfaceTintColor: Colors.transparent,

        iconTheme: const IconThemeData(color: LogisticsColors.textDark),

        title: const Text('Realtime Tracking', style: LogisticsStyle.title),

        actions: [
          IconButton(
            onPressed: _recenterMap,
            tooltip: 'Recenter',
            icon: const Icon(
              Icons.my_location_rounded,
              color: LogisticsColors.primary,
            ),
          ),

          const SizedBox(width: 6),
        ],
      ),

      body: Column(
        children: [
          // ------------------------------------------------------
          // MAP
          // ------------------------------------------------------
          Expanded(
            child: Stack(
              children: [
                FlutterMap(
                  mapController: _mapController,

                  options: const MapOptions(
                    initialCenter: santaCruzCenter,
                    initialZoom: 14.2,

                    minZoom: 11,
                    maxZoom: 19,
                  ),

                  children: [
                    // ------------------------------------------------
                    // OPENSTREETMAP
                    // ------------------------------------------------
                    TileLayer(
                      urlTemplate:
                          'https://tile.openstreetmap.org/{z}/{x}/{y}.png',

                      userAgentPackageName: 'com.bilihub.mobileapp',

                      maxZoom: 19,
                    ),

                    // ------------------------------------------------
                    // DEMO DELIVERY ROUTE
                    // ------------------------------------------------
                    PolylineLayer(
                      polylines: [
                        Polyline(
                          points: demoRoute,
                          strokeWidth: 5,
                          color: LogisticsColors.primary,
                          borderStrokeWidth: 2,
                          borderColor: Colors.white,
                        ),
                      ],
                    ),

                    // ------------------------------------------------
                    // MAP MARKERS
                    // ------------------------------------------------
                    MarkerLayer(
                      markers: [
                        // Rider
                        Marker(
                          point: riderLocation,
                          width: 58,
                          height: 70,
                          child: _mapMarker(
                            icon: Icons.delivery_dining_rounded,
                            color: LogisticsColors.primary,
                            label: 'Rider',
                          ),
                        ),

                        // Pickup
                        Marker(
                          point: pickupLocation,
                          width: 58,
                          height: 70,
                          child: _mapMarker(
                            icon: Icons.inventory_2_rounded,
                            color: LogisticsColors.warning,
                            label: 'Pickup',
                          ),
                        ),

                        // Destination
                        Marker(
                          point: destinationLocation,
                          width: 58,
                          height: 70,
                          child: _mapMarker(
                            icon: Icons.home_rounded,
                            color: LogisticsColors.success,
                            label: 'Customer',
                          ),
                        ),
                      ],
                    ),
                  ],
                ),

                // ----------------------------------------------------
                // MAP LABEL
                // ----------------------------------------------------
                Positioned(
                  top: 16,
                  left: 16,
                  right: 16,
                  child: _mapStatusCard(),
                ),

                // ----------------------------------------------------
                // ZOOM BUTTONS
                // ----------------------------------------------------
                Positioned(right: 16, bottom: 20, child: _zoomControls()),

                // ----------------------------------------------------
                // OSM ATTRIBUTION
                // ----------------------------------------------------
                Positioned(
                  left: 8,
                  bottom: 6,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 7,
                      vertical: 3,
                    ),
                    color: Colors.white.withValues(alpha: 0.85),
                    child: const Text(
                      '© OpenStreetMap contributors',
                      style: TextStyle(fontSize: 9, color: Colors.black87),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // --------------------------------------------------------
          // BOTTOM TRACKING PANEL
          // --------------------------------------------------------
          _trackingPanel(),
        ],
      ),
    );
  }

  // ==============================================================
  // MAP MARKER
  // ==============================================================

  Widget _mapMarker({
    required IconData icon,
    required Color color,
    required String label,
  }) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          height: 42,
          width: 42,
          decoration: BoxDecoration(
            color: color,
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white, width: 3),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.20),
                blurRadius: 8,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Icon(icon, color: Colors.white, size: 22),
        ),

        const SizedBox(height: 3),

        Container(
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(6),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.10),
                blurRadius: 4,
              ),
            ],
          ),
          child: Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 8,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }

  // ==============================================================
  // MAP STATUS CARD
  // ==============================================================

  Widget _mapStatusCard() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),

      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),

        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.12),
            blurRadius: 14,
            offset: const Offset(0, 5),
          ),
        ],
      ),

      child: Row(
        children: [
          Container(
            height: 40,
            width: 40,
            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.location_on_rounded,
              color: LogisticsColors.primary,
              size: 22,
            ),
          ),

          const SizedBox(width: 10),

          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Santa Cruz, Laguna', style: LogisticsStyle.cardTitle),

                SizedBox(height: 3),

                Text(
                  'Rider is currently delivering',
                  style: LogisticsStyle.small,
                ),
              ],
            ),
          ),

          Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
            decoration: BoxDecoration(
              color: LogisticsColors.lightGreen,
              borderRadius: BorderRadius.circular(20),
            ),
            child: const Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.circle, color: LogisticsColors.success, size: 7),

                SizedBox(width: 5),

                Text(
                  'LIVE',
                  style: TextStyle(
                    color: LogisticsColors.success,
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ==============================================================
  // ZOOM CONTROLS
  // ==============================================================

  Widget _zoomControls() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.15),
            blurRadius: 10,
          ),
        ],
      ),

      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            onPressed: () {
              _mapController.move(
                _mapController.camera.center,
                _mapController.camera.zoom + 1,
              );
            },
            icon: const Icon(
              Icons.add_rounded,
              color: LogisticsColors.textDark,
            ),
          ),

          Container(height: 1, width: 35, color: LogisticsColors.border),

          IconButton(
            onPressed: () {
              _mapController.move(
                _mapController.camera.center,
                _mapController.camera.zoom - 1,
              );
            },
            icon: const Icon(
              Icons.remove_rounded,
              color: LogisticsColors.textDark,
            ),
          ),
        ],
      ),
    );
  }

  // ==============================================================
  // RECENTER MAP
  // ==============================================================

  void _recenterMap() {
    _mapController.move(santaCruzCenter, 14.2);
  }

  // ==============================================================
  // BOTTOM TRACKING PANEL
  // ==============================================================

  Widget _trackingPanel() {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.fromLTRB(18, 16, 18, 20),

      decoration: const BoxDecoration(
        color: Colors.white,

        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Drag indicator
          Center(
            child: Container(
              height: 4,
              width: 42,
              decoration: BoxDecoration(
                color: LogisticsColors.border,
                borderRadius: BorderRadius.circular(10),
              ),
            ),
          ),

          const SizedBox(height: 14),

          // --------------------------------------------------------
          // ACTIVE RIDER
          // --------------------------------------------------------
          const Text('Active Rider', style: LogisticsStyle.sectionTitle),

          const SizedBox(height: 12),

          _riderInfo(),

          const SizedBox(height: 18),

          // --------------------------------------------------------
          // DELIVERY PROGRESS
          // --------------------------------------------------------
          const Text('Delivery Progress', style: LogisticsStyle.sectionTitle),

          const SizedBox(height: 12),

          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Parcel BH-2026-001245', style: LogisticsStyle.small),

              Text(
                '65%',
                style: TextStyle(
                  color: LogisticsColors.primary,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),

          const SizedBox(height: 8),

          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: const LinearProgressIndicator(
              value: 0.65,
              minHeight: 10,
              backgroundColor: LogisticsColors.lightPurple,
              valueColor: AlwaysStoppedAnimation<Color>(
                LogisticsColors.primary,
              ),
            ),
          ),

          const SizedBox(height: 8),

          const Text(
            'Estimated arrival: 15–20 minutes',
            style: LogisticsStyle.small,
          ),
        ],
      ),
    );
  }

  // ==============================================================
  // RIDER INFORMATION
  // ==============================================================

  Widget _riderInfo() {
    return Container(
      padding: const EdgeInsets.all(14),

      decoration: LogisticsStyle.card,

      child: Row(
        children: [
          Container(
            height: 52,
            width: 52,

            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,
              shape: BoxShape.circle,
            ),

            child: const Icon(
              Icons.delivery_dining_rounded,
              color: LogisticsColors.primary,
              size: 29,
            ),
          ),

          const SizedBox(width: 12),

          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Rider Name', style: LogisticsStyle.cardTitle),

                SizedBox(height: 4),

                Text('Motorcycle • BH-RDR-001', style: LogisticsStyle.small),

                SizedBox(height: 4),

                Row(
                  children: [
                    Icon(Icons.circle, color: LogisticsColors.success, size: 8),

                    SizedBox(width: 5),

                    Text(
                      'Online • Delivering Parcel',
                      style: TextStyle(
                        color: LogisticsColors.success,
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          const SizedBox(width: 8),

          Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),

            decoration: BoxDecoration(
              color: LogisticsColors.lightGreen,
              borderRadius: BorderRadius.circular(20),
            ),

            child: const Text(
              'ONLINE',
              style: TextStyle(
                color: LogisticsColors.success,
                fontSize: 10,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
