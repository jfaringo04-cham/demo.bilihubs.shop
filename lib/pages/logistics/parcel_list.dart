import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../sevices/api_services.dart';
import '../../styles/logistics_style.dart';
import '../../widgets/parcel_card.dart';

import 'parcel_information.dart';

class ParcelList extends StatefulWidget {
  const ParcelList({super.key});

  @override
  State<ParcelList> createState() => _ParcelListState();
}

class _ParcelListState extends State<ParcelList> {
  final TextEditingController _searchController = TextEditingController();

  final List<ParcelModel> _parcels = <ParcelModel>[];

  String _searchQuery = '';
  String _selectedStatus = 'all';

  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadParcels();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  // ============================================================
  // LOAD PARCELS
  // ============================================================

  Future<void> _loadParcels({bool showLoading = true}) async {
    if (showLoading && mounted) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final result = await ApiService.getLogisticsShipments();

      if (!mounted) return;

      if (result['success'] != true) {
        setState(() {
          _isLoading = false;
          _errorMessage =
              result['message']?.toString() ??
              'Unable to load logistics parcels.';
        });

        return;
      }

      final dynamic rawData = result['data'];

      final List<dynamic> items;

      if (rawData is List) {
        items = rawData;
      } else {
        items = <dynamic>[];
      }

      final List<ParcelModel> loadedParcels = [];

      for (final item in items) {
        if (item is! Map) continue;

        try {
          final map = Map<String, dynamic>.from(item);

          // ParcelModel expects order_date.
          // Logistics API currently returns created_at,
          // so use it as fallback.
          map['order_date'] ??= map['created_at'] ?? '';

          map['tracking_number'] ??= map['tracking_code'] ?? '';

          map['customer_name'] ??= '';
          map['customer_phone'] ??= '';
          map['delivery_address'] ??= '';
          map['rider_name'] ??= '';
          map['status'] ??= 'pending';

          loadedParcels.add(ParcelModel.fromJson(map));
        } catch (e) {
          debugPrint('Unable to parse logistics parcel: $e');
        }
      }

      setState(() {
        _parcels
          ..clear()
          ..addAll(loadedParcels);

        _isLoading = false;
        _errorMessage = null;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isLoading = false;
        _errorMessage = 'Unable to load logistics parcels: $e';
      });
    }
  }

  // ============================================================
  // FILTERED PARCELS
  // ============================================================

  List<ParcelModel> get _filteredParcels {
    return _parcels.where((parcel) {
      final search = _searchQuery.trim().toLowerCase();

      final matchesSearch =
          search.isEmpty ||
          parcel.trackingNumber.toLowerCase().contains(search) ||
          parcel.customerName.toLowerCase().contains(search) ||
          parcel.deliveryAddress.toLowerCase().contains(search);

      if (!matchesSearch) {
        return false;
      }

      if (_selectedStatus == 'all') {
        return true;
      }

      final status = parcel.status
          .trim()
          .toLowerCase()
          .replaceAll(' ', '_');

      switch (_selectedStatus) {
        case 'pending':
          return status == 'pending' ||
              status == 'assigned' ||
              status == 'staged' ||
              status == 'ready_for_pickup';

        case 'in_transit':
          return status == 'in_transit' ||
              status == 'picked_up' ||
              status == 'at_sorting_center' ||
              status == 'sorted' ||
              status == 'out_for_delivery';

        case 'delivered':
          return status == 'delivered' || status == 'completed';

        case 'cancelled':
          return status == 'cancelled';

        default:
          return true;
      }
    }).toList();
  }

  // ============================================================
  // REFRESH
  // ============================================================

  Future<void> _refreshParcels() async {
    await _loadParcels(showLoading: false);
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;
    final filteredParcels = _filteredParcels;

    return Scaffold(
      backgroundColor: LogisticsColors.background,
      appBar: AppBar(
        backgroundColor: LogisticsColors.background,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        iconTheme: const IconThemeData(
          color: LogisticsColors.textDark,
        ),
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
            onPressed: _isLoading ? null : _refreshParcels,
            tooltip: 'Refresh',
            icon: const Icon(
              Icons.refresh_rounded,
              color: LogisticsColors.primary,
            ),
          ),
          const SizedBox(width: 6),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: EdgeInsets.fromLTRB(
                width * 0.045,
                4,
                width * 0.045,
                0,
              ),
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
                      Expanded(
                        child: Container(
                          decoration: BoxDecoration(
                            color: LogisticsColors.white,
                            borderRadius: BorderRadius.circular(15),
                            border: Border.all(
                              color: LogisticsColors.border,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(
                                  alpha: 0.025,
                                ),
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
                                        size: 19,
                                      ),
                                    )
                                  : null,
                              border: InputBorder.none,
                              contentPadding:
                                  const EdgeInsets.symmetric(
                                    vertical: 14,
                                  ),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Material(
                        color: Colors.transparent,
                        child: InkWell(
                          onTap: _showFilterOptions,
                          borderRadius: BorderRadius.circular(15),
                          child: Container(
                            height: 50,
                            width: 50,
                            decoration: BoxDecoration(
                              color: LogisticsColors.white,
                              borderRadius: BorderRadius.circular(15),
                              border: Border.all(
                                color: _selectedStatus == 'all'
                                    ? LogisticsColors.border
                                    : LogisticsColors.primary,
                              ),
                            ),
                            child: Stack(
                              alignment: Alignment.center,
                              children: [
                                const Icon(
                                  Icons.tune_rounded,
                                  color: LogisticsColors.primary,
                                ),
                                if (_selectedStatus != 'all')
                                  Positioned(
                                    top: 8,
                                    right: 8,
                                    child: Container(
                                      height: 7,
                                      width: 7,
                                      decoration: const BoxDecoration(
                                        color: LogisticsColors.warning,
                                        shape: BoxShape.circle,
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  Row(
                    mainAxisAlignment:
                        MainAxisAlignment.spaceBetween,
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
                          '${filteredParcels.length} parcel${filteredParcels.length == 1 ? '' : 's'}',
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

            Expanded(
              child: RefreshIndicator(
                onRefresh: _refreshParcels,
                child: _buildParcelBody(
                  width,
                  filteredParcels,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // BODY
  // ============================================================

  Widget _buildParcelBody(
    double width,
    List<ParcelModel> filteredParcels,
  ) {
    if (_isLoading) {
      return const Center(
        child: CircularProgressIndicator(
          color: LogisticsColors.primary,
        ),
      );
    }

    if (_errorMessage != null) {
      return _errorState();
    }

    if (_parcels.isEmpty) {
      return _emptyState();
    }

    if (filteredParcels.isEmpty) {
      return _noSearchResults();
    }

    return _parcelList(width, filteredParcels);
  }

  // ============================================================
  // PARCEL LIST
  // ============================================================

  Widget _parcelList(
    double width,
    List<ParcelModel> parcels,
  ) {
    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(
        parent: BouncingScrollPhysics(),
      ),
      padding: EdgeInsets.fromLTRB(
        width * 0.045,
        4,
        width * 0.045,
        24,
      ),
      itemCount: parcels.length,
      itemBuilder: (context, index) {
        final parcel = parcels[index];

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
  // ERROR STATE
  // ============================================================

  Widget _errorState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(
        horizontal: 30,
        vertical: 30,
      ),
      children: [
        const SizedBox(height: 50),
        Center(
          child: Container(
            height: 100,
            width: 100,
            decoration: const BoxDecoration(
              color: LogisticsColors.lightPurple,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.cloud_off_rounded,
              color: LogisticsColors.primary,
              size: 46,
            ),
          ),
        ),
        const SizedBox(height: 22),
        const Text(
          'Unable to Load Parcels',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: LogisticsColors.textDark,
            fontSize: 20,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          _errorMessage ?? 'Unable to load parcels.',
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: LogisticsColors.textGrey,
            fontSize: 13,
            height: 1.5,
          ),
        ),
        const SizedBox(height: 22),
        Center(
          child: OutlinedButton.icon(
            onPressed: () {
              _loadParcels();
            },
            icon: const Icon(
              Icons.refresh_rounded,
              size: 19,
            ),
            label: const Text('Try Again'),
            style: OutlinedButton.styleFrom(
              foregroundColor: LogisticsColors.primary,
              backgroundColor: LogisticsColors.white,
              side: const BorderSide(
                color: LogisticsColors.primary,
              ),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(13),
              ),
            ),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // EMPTY STATE
  // ============================================================

  Widget _emptyState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(
        horizontal: 30,
        vertical: 30,
      ),
      children: [
        const SizedBox(height: 50),
        Center(
          child: Container(
            height: 100,
            width: 100,
            decoration: const BoxDecoration(
              color: LogisticsColors.lightPurple,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.inventory_2_outlined,
              color: LogisticsColors.primary,
              size: 46,
            ),
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
          'There are currently no shipments assigned to this logistics account.',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: LogisticsColors.textGrey,
            fontSize: 13,
            height: 1.5,
          ),
        ),
      ],
    );
  }

  // ============================================================
  // NO SEARCH RESULTS
  // ============================================================

  Widget _noSearchResults() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(30),
      children: [
        const SizedBox(height: 70),
        Center(
          child: Container(
            height: 80,
            width: 80,
            decoration: const BoxDecoration(
              color: LogisticsColors.lightPurple,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.search_off_rounded,
              color: LogisticsColors.primary,
              size: 36,
            ),
          ),
        ),
        const SizedBox(height: 18),
        const Text(
          'No Parcels Found',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: LogisticsColors.textDark,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 7),
        Text(
          _searchQuery.isNotEmpty
              ? 'No parcel matches "$_searchQuery".'
              : 'No parcels match the selected filter.',
          textAlign: TextAlign.center,
          style: LogisticsStyle.small,
        ),
        const SizedBox(height: 18),
        Center(
          child: TextButton(
            onPressed: () {
              _searchController.clear();

              setState(() {
                _searchQuery = '';
                _selectedStatus = 'all';
              });
            },
            style: LogisticsStyle.textButton,
            child: const Text('Clear Filters'),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // FILTER OPTIONS
  // ============================================================

  void _showFilterOptions() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return Container(
          padding: const EdgeInsets.fromLTRB(
            20,
            12,
            20,
            28,
          ),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(
              top: Radius.circular(28),
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                height: 4,
                width: 42,
                decoration: BoxDecoration(
                  color: LogisticsColors.border,
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              const SizedBox(height: 18),
              const Row(
                children: [
                  Icon(
                    Icons.tune_rounded,
                    color: LogisticsColors.primary,
                  ),
                  SizedBox(width: 10),
                  Text(
                    'Filter Parcels',
                    style: LogisticsStyle.sectionTitle,
                  ),
                ],
              ),
              const SizedBox(height: 18),

              _filterOption(
                sheetContext,
                icon: Icons.inventory_2_outlined,
                title: 'All Parcels',
                value: 'all',
                color: LogisticsColors.primary,
              ),

              _filterOption(
                sheetContext,
                icon: Icons.schedule_rounded,
                title: 'Pending',
                value: 'pending',
                color: LogisticsColors.warning,
              ),

              _filterOption(
                sheetContext,
                icon: Icons.local_shipping_outlined,
                title: 'In Transit',
                value: 'in_transit',
                color: LogisticsColors.primary,
              ),

              _filterOption(
                sheetContext,
                icon: Icons.check_circle_outline_rounded,
                title: 'Delivered',
                value: 'delivered',
                color: Colors.green,
              ),

              _filterOption(
                sheetContext,
                icon: Icons.cancel_outlined,
                title: 'Cancelled',
                value: 'cancelled',
                color: Colors.red,
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _filterOption(
    BuildContext sheetContext, {
    required IconData icon,
    required String title,
    required String value,
    required Color color,
  }) {
    final isSelected = _selectedStatus == value;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () {
            Navigator.pop(sheetContext);

            setState(() {
              _selectedStatus = value;
            });
          },
          borderRadius: BorderRadius.circular(14),
          child: Container(
            padding: const EdgeInsets.symmetric(
              horizontal: 14,
              vertical: 12,
            ),
            decoration: BoxDecoration(
              color: isSelected
                  ? LogisticsColors.lightPurple
                  : LogisticsColors.background,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: isSelected
                    ? LogisticsColors.primary
                    : LogisticsColors.border,
              ),
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
                  child: Icon(
                    icon,
                    color: color,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    title,
                    style: LogisticsStyle.cardTitle,
                  ),
                ),
                if (isSelected)
                  const Icon(
                    Icons.check_rounded,
                    color: LogisticsColors.primary,
                  )
                else
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
  // OPEN PARCEL INFORMATION
  // ============================================================

  Future<void> _openParcel(ParcelModel parcel) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) {
          return ParcelInformation(
            parcel: parcel,
          );
        },
      ),
    );

    if (!mounted) return;

    await _loadParcels(
      showLoading: false,
    );
  }
}