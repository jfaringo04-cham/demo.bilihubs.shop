import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

class TrackingMap extends StatelessWidget {
  const TrackingMap({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: LogisticsColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        surfaceTintColor: Colors.transparent,

        iconTheme: const IconThemeData(color: LogisticsColors.textDark),

        title: const Text('Parcel Tracking Map', style: LogisticsStyle.title),
      ),

      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refreshTracking,

          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),

            padding: const EdgeInsets.all(24),

            children: [
              const SizedBox(height: 55),

              // ============================================================
              // ICON
              // ============================================================
              Center(
                child: Container(
                  height: 105,
                  width: 105,

                  decoration: const BoxDecoration(
                    color: LogisticsColors.lightPurple,

                    shape: BoxShape.circle,
                  ),

                  child: const Icon(
                    Icons.map_outlined,

                    color: LogisticsColors.primary,

                    size: 50,
                  ),
                ),
              ),

              const SizedBox(height: 24),

              // ============================================================
              // TITLE
              // ============================================================
              const Text(
                'Tracking Map Unavailable',

                textAlign: TextAlign.center,

                style: TextStyle(
                  color: LogisticsColors.textDark,

                  fontSize: 21,

                  fontWeight: FontWeight.w800,
                ),
              ),

              const SizedBox(height: 10),

              const Text(
                'Real parcel and rider locations are not available yet because the Logistics tracking backend has not been connected.',

                textAlign: TextAlign.center,

                style: TextStyle(
                  color: LogisticsColors.textGrey,

                  fontSize: 13,

                  height: 1.5,
                ),
              ),

              const SizedBox(height: 28),

              // ============================================================
              // TRACKING REQUIREMENTS
              // ============================================================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(18),

                decoration: LogisticsStyle.card,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text(
                      'Tracking Requirements',

                      style: LogisticsStyle.sectionTitle,
                    ),

                    const SizedBox(height: 16),

                    _requirementRow(
                      icon: Icons.inventory_2_outlined,

                      title: 'Parcel Location',

                      description: 'Latitude and longitude from the backend.',
                    ),

                    const SizedBox(height: 14),

                    _requirementRow(
                      icon: Icons.delivery_dining_outlined,

                      title: 'Rider Location',

                      description:
                          'Current rider coordinates for real-time tracking.',
                    ),

                    const SizedBox(height: 14),

                    _requirementRow(
                      icon: Icons.route_outlined,

                      title: 'Delivery Route',

                      description:
                          'A real route or tracking service from the backend.',
                    ),

                    const SizedBox(height: 14),

                    _requirementRow(
                      icon: Icons.schedule_outlined,

                      title: 'Updated Time',

                      description: 'Timestamp showing when the rider location was last updated.',
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              // ============================================================
              // INFO
              // ============================================================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(16),

                decoration: BoxDecoration(
                  color: LogisticsColors.lightPurple,

                  borderRadius: BorderRadius.circular(16),

                  border: Border.all(color: LogisticsColors.softPurple),
                ),

                child: const Row(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Icon(
                      Icons.info_outline_rounded,

                      color: LogisticsColors.primary,

                      size: 21,
                    ),

                    SizedBox(width: 10),

                    Expanded(
                      child: Text(
                        'No demo coordinates, fake rider locations, fake routes, or fake ETA are being displayed.',

                        style: LogisticsStyle.small,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // ============================================================
              // REFRESH
              // ============================================================
              Center(
                child: OutlinedButton.icon(
                  onPressed: () {
                    _showUnavailableMessage(context);
                  },

                  icon: const Icon(Icons.refresh_rounded, size: 19),

                  label: const Text('Check Tracking'),

                  style: OutlinedButton.styleFrom(
                    foregroundColor: LogisticsColors.primary,

                    backgroundColor: Colors.white,

                    side: const BorderSide(color: LogisticsColors.primary),

                    padding: const EdgeInsets.symmetric(
                      horizontal: 18,

                      vertical: 12,
                    ),

                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(13),
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 30),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // REQUIREMENT ROW
  // ============================================================

  Widget _requirementRow({
    required IconData icon,
    required String title,
    required String description,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        Container(
          height: 42,
          width: 42,

          decoration: BoxDecoration(
            color: LogisticsColors.lightPurple,

            borderRadius: BorderRadius.circular(12),
          ),

          child: Icon(icon, color: LogisticsColors.primary, size: 21),
        ),

        const SizedBox(width: 12),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Text(title, style: LogisticsStyle.cardTitle),

              const SizedBox(height: 3),

              Text(description, style: LogisticsStyle.small),
            ],
          ),
        ),
      ],
    );
  }

  // ============================================================
  // REFRESH
  // ============================================================

  Future<void> _refreshTracking() async {
    return;
  }

  // ============================================================
  // MESSAGE
  // ============================================================

  void _showUnavailableMessage(BuildContext context) {
    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        behavior: SnackBarBehavior.floating,

        content: Text(
          'Live tracking is not available until the Logistics backend is connected.',
        ),
      ),
    );
  }
}
