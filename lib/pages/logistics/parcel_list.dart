import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../styles/logistics_style.dart';
import '../../widgets/parcel_card.dart';

class ParcelList extends StatefulWidget {
  const ParcelList({super.key});

  @override
  State<ParcelList> createState() => _ParcelListState();
}

class _ParcelListState extends State<ParcelList> {
  final TextEditingController _searchController = TextEditingController();

  String _searchQuery = '';

  // ============================================================
  // DEMO PARCEL LIST
  // ============================================================
  //
  // Keep this empty for now.
  // Your Laravel/API data can be connected here later.
  //
  final List<ParcelModel> parcels = [];

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: LogisticsColors.background,

      // ========================================================
      // APP BAR
      // ========================================================
      appBar: AppBar(
        backgroundColor: LogisticsColors.background,
        elevation: 0,
        surfaceTintColor: Colors.transparent,

        iconTheme: const IconThemeData(color: LogisticsColors.textDark),

        titleSpacing: 4,

        title: const Text(
          'Parcel List',
          style: TextStyle(
            color: LogisticsColors.textDark,
            fontSize: 20,
            fontWeight: FontWeight.w800,
          ),
        ),

        actions: [
          IconButton(
            onPressed: _refreshParcels,
            tooltip: 'Refresh',
            icon: const Icon(
              Icons.refresh_rounded,
              color: LogisticsColors.primary,
            ),
          ),

          const SizedBox(width: 6),
        ],
      ),

      // ========================================================
      // BODY
      // ========================================================
      body: SafeArea(
        child: Column(
          children: [
            // ====================================================
            // HEADER / SEARCH AREA
            // ====================================================

            Padding(
              padding: EdgeInsets.fromLTRB(width * 0.045, 4, width * 0.045, 0),

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  const Text(
                    'Manage Parcels',
                    style: LogisticsStyle.sectionTitle,
                  ),

                  const SizedBox(height: 4),

                  const Text(
                    'Search and view parcel delivery information.',
                    style: LogisticsStyle.small,
                  ),

                  const SizedBox(height: 16),

                  Row(
                    children: [
                      // Search
                      Expanded(
                        child: Container(
                          decoration: BoxDecoration(
                            color: LogisticsColors.white,
                            borderRadius: BorderRadius.circular(15),
                            border: Border.all(color: LogisticsColors.border),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.025),
                                blurRadius: 10,
                                offset: const Offset(0, 4),
                              ),
                            ],
                          ),

                          child: TextField(
                            controller: _searchController,

                            onChanged: (value) {
                              setState(() {
                                _searchQuery = value.trim();
                              });
                            },

                            textInputAction: TextInputAction.search,

                            style: const TextStyle(
                              color: LogisticsColors.textDark,
                              fontSize: 13,
                              fontWeight: FontWeight.w500,
                            ),

                            decoration: InputDecoration(
                              hintText: 'Search tracking number...',
                              hintStyle: const TextStyle(
                                color: LogisticsColors.textLight,
                                fontSize: 13,
                              ),

                              prefixIcon: const Icon(
                                Icons.search_rounded,
                                color: LogisticsColors.primary,
                                size: 22,
                              ),

                              suffixIcon: _searchQuery.isNotEmpty
                                  ? IconButton(
                                      onPressed: () {
                                        _searchController.clear();

                                        setState(() {
                                          _searchQuery = '';
                                        });
                                      },
                                      icon: const Icon(
                                        Icons.close_rounded,
                                        color: LogisticsColors.textGrey,
                                        size: 19,
                                      ),
                                    )
                                  : null,

                              filled: true,
                              fillColor: LogisticsColors.white,

                              contentPadding: const EdgeInsets.symmetric(
                                horizontal: 14,
                                vertical: 14,
                              ),

                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(15),
                                borderSide: BorderSide.none,
                              ),

                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(15),
                                borderSide: BorderSide.none,
                              ),

                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(15),
                                borderSide: const BorderSide(
                                  color: LogisticsColors.primary,
                                  width: 1.3,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),

                      const SizedBox(width: 10),

                      // Filter button
                      Material(
                        color: LogisticsColors.lightPurple,
                        borderRadius: BorderRadius.circular(15),

                        child: InkWell(
                          onTap: _showFilterOptions,
                          borderRadius: BorderRadius.circular(15),

                          child: Container(
                            height: 50,
                            width: 50,

                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(15),
                              border: Border.all(
                                color: LogisticsColors.softPurple,
                              ),
                            ),

                            child: const Icon(
                              Icons.tune_rounded,
                              color: LogisticsColors.primary,
                              size: 22,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // ==================================================
                  // LIST HEADER
                  // ==================================================
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.center,

                    children: [
                      const Text(
                        'All Parcels',
                        style: LogisticsStyle.sectionTitle,
                      ),

                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),

                        decoration: BoxDecoration(
                          color: LogisticsColors.lightPurple,
                          borderRadius: BorderRadius.circular(20),
                        ),

                        child: Text(
                          '${parcels.length} parcel${parcels.length == 1 ? '' : 's'}',
                          style: const TextStyle(
                            color: LogisticsColors.primary,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 10),
                ],
              ),
            ),

            // ====================================================
            // PARCEL LIST / EMPTY STATE
            // ====================================================
            Expanded(
              child: parcels.isEmpty ? _emptyState() : _parcelList(width),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // PARCEL LIST
  // ============================================================

  Widget _parcelList(double width) {
    final filteredParcels = parcels.where((parcel) {
      if (_searchQuery.isEmpty) {
        return true;
      }

      return parcel.trackingNumber.toLowerCase().contains(
        _searchQuery.toLowerCase(),
      );
    }).toList();

    if (filteredParcels.isEmpty) {
      return _noSearchResults();
    }

    return ListView.builder(
      physics: const BouncingScrollPhysics(),

      padding: EdgeInsets.fromLTRB(width * 0.045, 4, width * 0.045, 24),

      itemCount: filteredParcels.length,

      itemBuilder: (context, index) {
        final parcel = filteredParcels[index];

        return Padding(
          padding: const EdgeInsets.only(bottom: 12),

          child: ParcelCard(
            parcel: parcel,

            buttonText: 'View',

            onButtonPressed: () {
              _openParcel(parcel);
            },
          ),
        );
      },
    );
  }

  // ============================================================
  // EMPTY STATE
  // ============================================================

  Widget _emptyState() {
    return Center(
      child: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),

        padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 30),

        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,

          children: [
            // Icon container
            Container(
              height: 100,
              width: 100,

              decoration: BoxDecoration(
                color: LogisticsColors.lightPurple,
                shape: BoxShape.circle,
              ),

              child: const Icon(
                Icons.inventory_2_rounded,
                color: LogisticsColors.primary,
                size: 46,
              ),
            ),

            const SizedBox(height: 22),

            const Text(
              'No Parcels Yet',
              textAlign: TextAlign.center,

              style: TextStyle(
                color: LogisticsColors.textDark,
                fontSize: 20,
                fontWeight: FontWeight.w800,
              ),
            ),

            const SizedBox(height: 8),

            const Text(
              'There are currently no parcels available.\n'
              'New parcels will appear here once they are added.',
              textAlign: TextAlign.center,

              style: TextStyle(
                color: LogisticsColors.textGrey,
                fontSize: 13,
                height: 1.5,
              ),
            ),

            const SizedBox(height: 22),

            // Refresh button
            SizedBox(
              height: 46,

              child: OutlinedButton.icon(
                onPressed: _refreshParcels,

                icon: const Icon(Icons.refresh_rounded, size: 19),

                label: const Text('Refresh Parcels'),

                style: OutlinedButton.styleFrom(
                  foregroundColor: LogisticsColors.primary,
                  backgroundColor: LogisticsColors.white,
                  elevation: 0,

                  padding: const EdgeInsets.symmetric(horizontal: 18),

                  side: const BorderSide(color: LogisticsColors.primary),

                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(13),
                  ),

                  textStyle: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // NO SEARCH RESULTS
  // ============================================================

  Widget _noSearchResults() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(30),

        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,

          children: [
            Container(
              height: 80,
              width: 80,

              decoration: BoxDecoration(
                color: LogisticsColors.lightPurple,
                shape: BoxShape.circle,
              ),

              child: const Icon(
                Icons.search_off_rounded,
                color: LogisticsColors.primary,
                size: 36,
              ),
            ),

            const SizedBox(height: 18),

            const Text(
              'No Parcels Found',
              style: TextStyle(
                color: LogisticsColors.textDark,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),

            const SizedBox(height: 7),

            Text(
              'No parcel matches "$_searchQuery".',
              textAlign: TextAlign.center,

              style: LogisticsStyle.small,
            ),

            const SizedBox(height: 18),

            TextButton(
              onPressed: () {
                _searchController.clear();

                setState(() {
                  _searchQuery = '';
                });
              },

              style: LogisticsStyle.textButton,

              child: const Text('Clear Search'),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // FILTER OPTIONS
  // ============================================================

  void _showFilterOptions() {
    showModalBottomSheet(
      context: context,

      backgroundColor: Colors.transparent,

      builder: (context) {
        return Container(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),

          decoration: const BoxDecoration(
            color: Colors.white,

            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),

          child: Column(
            mainAxisSize: MainAxisSize.min,

            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
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

              const SizedBox(height: 18),

              const Text('Filter Parcels', style: LogisticsStyle.sectionTitle),

              const SizedBox(height: 5),

              const Text(
                'Choose a parcel status to filter the list.',
                style: LogisticsStyle.small,
              ),

              const SizedBox(height: 18),

              _filterOption(
                icon: Icons.all_inbox_rounded,
                title: 'All Parcels',
                color: LogisticsColors.primary,
              ),

              _filterOption(
                icon: Icons.pending_actions_rounded,
                title: 'Pending',
                color: LogisticsColors.warning,
              ),

              _filterOption(
                icon: Icons.local_shipping_rounded,
                title: 'In Transit',
                color: LogisticsColors.primary,
              ),

              _filterOption(
                icon: Icons.check_circle_rounded,
                title: 'Delivered',
                color: LogisticsColors.success,
              ),
            ],
          ),
        );
      },
    );
  }

  // ============================================================
  // FILTER OPTION
  // ============================================================

  Widget _filterOption({
    required IconData icon,
    required String title,
    required Color color,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),

      child: Material(
        color: Colors.transparent,

        child: InkWell(
          onTap: () {
            Navigator.pop(context);

            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                behavior: SnackBarBehavior.floating,

                backgroundColor: LogisticsColors.primary,

                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),

                content: Text(
                  '$title filter selected',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            );
          },

          borderRadius: BorderRadius.circular(14),

          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),

            decoration: BoxDecoration(
              color: LogisticsColors.background,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: LogisticsColors.border),
            ),

            child: Row(
              children: [
                Container(
                  height: 38,
                  width: 38,

                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(11),
                  ),

                  child: Icon(icon, color: color, size: 20),
                ),

                const SizedBox(width: 12),

                Expanded(child: Text(title, style: LogisticsStyle.cardTitle)),

                const Icon(
                  Icons.chevron_right_rounded,
                  color: LogisticsColors.textLight,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // REFRESH
  // ============================================================

  void _refreshParcels() {
    // API connection will be added later.
    //
    // For now, this simply gives visual feedback.

    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,

        backgroundColor: LogisticsColors.primary,

        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),

        content: const Row(
          children: [
            Icon(Icons.refresh_rounded, color: Colors.white, size: 20),

            SizedBox(width: 10),

            Text(
              'Parcel list refreshed',
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );

    setState(() {});
  }

  // ============================================================
  // OPEN PARCEL
  // ============================================================

  void _openParcel(ParcelModel parcel) {
    // Connect this to ParcelInformation later.

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,

        backgroundColor: LogisticsColors.primary,

        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),

        content: Text(
          'Opening ${parcel.trackingNumber}',
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }
}
