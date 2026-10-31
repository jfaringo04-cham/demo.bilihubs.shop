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
  bool _isRefreshing = false;

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
    if (!mounted) {
      return;
    }

    setState(() {
      if (showLoading) {
        _isLoading = true;
      } else {
        _isRefreshing = true;
      }

      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.getLogisticsShipments(perPage: 100);

      if (!mounted) {
        return;
      }

      if (result['success'] != true) {
        final String message = result['message']?.toString().trim() ?? '';

        setState(() {
          _isLoading = false;
          _isRefreshing = false;

          _errorMessage = message.isNotEmpty
              ? message
              : 'Unable to load logistics parcels.';
        });

        return;
      }

      final List<Map<String, dynamic>> items = _extractShipmentMaps(result);

      final List<ParcelModel> loadedParcels = <ParcelModel>[];

      for (final Map<String, dynamic> item in items) {
        try {
          final Map<String, dynamic> normalized = _normalizeShipment(item);

          loadedParcels.add(ParcelModel.fromJson(normalized));
        } catch (error) {
          debugPrint('Unable to parse logistics parcel: $error');
        }
      }

      loadedParcels.sort((ParcelModel a, ParcelModel b) {
        final DateTime? aDate = _activityDate(a);
        final DateTime? bDate = _activityDate(b);

        if (aDate != null && bDate != null) {
          final int comparison = bDate.compareTo(aDate);

          if (comparison != 0) {
            return comparison;
          }
        } else if (aDate != null) {
          return -1;
        } else if (bDate != null) {
          return 1;
        }

        return b.id.compareTo(a.id);
      });

      setState(() {
        _parcels
          ..clear()
          ..addAll(loadedParcels);

        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = null;
      });
    } catch (error) {
      debugPrint('Logistics parcel list error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _isLoading = false;
        _isRefreshing = false;

        _errorMessage =
            'Unable to load logistics parcels. '
            'Please check your connection and try again.';
      });
    }
  }

  // ============================================================
  // EXTRACT SHIPMENT LIST
  // ============================================================

  List<Map<String, dynamic>> _extractShipmentMaps(Map<String, dynamic> result) {
    for (final String key in <String>[
      'data',
      'shipments',
      'parcels',
      'orders',
    ]) {
      final List<Map<String, dynamic>> list = _toMapList(result[key]);

      if (list.isNotEmpty) {
        return list;
      }
    }

    final dynamic rawData = result['data'];

    if (rawData is Map) {
      final Map<String, dynamic> data = Map<String, dynamic>.from(rawData);

      for (final String key in <String>[
        'data',
        'shipments',
        'parcels',
        'orders',
      ]) {
        final List<Map<String, dynamic>> list = _toMapList(data[key]);

        if (list.isNotEmpty) {
          return list;
        }
      }
    }

    return <Map<String, dynamic>>[];
  }

  List<Map<String, dynamic>> _toMapList(dynamic value) {
    if (value is! List) {
      return <Map<String, dynamic>>[];
    }

    final List<Map<String, dynamic>> list = <Map<String, dynamic>>[];

    for (final dynamic item in value) {
      if (item is Map<String, dynamic>) {
        list.add(Map<String, dynamic>.from(item));
      } else if (item is Map) {
        list.add(Map<String, dynamic>.from(item));
      }
    }

    return list;
  }

  // ============================================================
  // NORMALIZE SHIPMENT
  // ============================================================

  Map<String, dynamic> _normalizeShipment(Map<String, dynamic> raw) {
    final Map<String, dynamic> data = Map<String, dynamic>.from(raw);

    final dynamic nestedShipment = raw['shipment'];

    if (nestedShipment is Map) {
      data.addAll(Map<String, dynamic>.from(nestedShipment));
    }

    final dynamic order = raw['order'];

    if (order is Map) {
      final Map<String, dynamic> orderMap = Map<String, dynamic>.from(order);

      data['customer_name'] ??=
          orderMap['customer_name'] ??
          orderMap['buyer_name'] ??
          orderMap['name'];

      data['customer_phone'] ??=
          orderMap['customer_phone'] ?? orderMap['phone'];

      data['delivery_address'] ??=
          orderMap['delivery_address'] ?? orderMap['address'];

      data['order_date'] ??= orderMap['order_date'] ?? orderMap['created_at'];
    }

    final dynamic rider = data['rider'];

    if (rider is Map) {
      data['rider_id'] ??= rider['id'];
      data['rider_name'] ??= rider['name'];
    }

    final dynamic linehaulRider = data['linehaul_rider'];

    if (linehaulRider is Map) {
      data['linehaul_rider_id'] ??= linehaulRider['id'];

      data['linehaul_rider_name'] ??= linehaulRider['name'];
    }

    final dynamic originHub = data['origin_hub'];

    if (originHub is Map) {
      data['origin_hub_id'] ??= originHub['id'];

      data['origin_hub_name'] ??= originHub['name'];
    }

    final dynamic destinationHub = data['destination_hub'];

    if (destinationHub is Map) {
      data['destination_hub_id'] ??= destinationHub['id'];

      data['destination_hub_name'] ??= destinationHub['name'];
    }

    final dynamic currentHub = data['hub'];

    if (currentHub is Map) {
      data['current_hub_id'] ??= currentHub['id'];

      data['current_hub_name'] ??= currentHub['name'];
    }

    data['id'] ??= data['shipment_id'] ?? data['order_id'] ?? 0;

    data['shipment_id'] ??= data['id'];

    data['tracking_number'] ??=
        data['tracking_code'] ??
        data['tracking'] ??
        data['waybill_number'] ??
        '';

    data['customer_name'] ??=
        data['recipient_name'] ?? data['buyer_name'] ?? '';

    data['customer_phone'] ??= data['phone'] ?? data['recipient_phone'] ?? '';

    data['delivery_address'] ??=
        data['shipping_address'] ??
        data['recipient_address'] ??
        data['address'] ??
        '';

    data['pickup_address'] ??= data['seller_address'] ?? '';

    data['rider_name'] ??= '';

    data['status'] ??=
        data['shipment_status'] ?? data['delivery_status'] ?? 'pending';

    data['order_date'] ??= data['created_at'] ?? data['assigned_at'] ?? '';

    data['delivery_earnings'] ??= data['earnings'] ?? data['delivery_fee'] ?? 0;

    data['latitude'] ??= data['lat'];

    data['longitude'] ??= data['lng'] ?? data['lon'];

    return data;
  }

  // ============================================================
  // ACTIVITY DATE
  // ============================================================

  DateTime? _activityDate(ParcelModel parcel) {
    final List<String> candidates = <String>[
      parcel.updatedAt,
      parcel.destinationHubReceivedAt,
      parcel.linehaulCompletedAt,
      parcel.linehaulStartedAt,
      parcel.linehaulAssignedAt,
      parcel.transferDispatchedAt,
      parcel.originHubReceivedAt,
      parcel.pickedUpAt,
      parcel.createdAt,
      parcel.orderDate,
    ];

    for (final String value in candidates) {
      final String text = value.trim();

      if (text.isEmpty || text.toLowerCase() == 'null') {
        continue;
      }

      final DateTime? parsed = DateTime.tryParse(text);

      if (parsed != null) {
        return parsed;
      }
    }

    return null;
  }

  // ============================================================
  // FILTERED PARCELS
  // ============================================================

  List<ParcelModel> get _filteredParcels {
    return _parcels.where((ParcelModel parcel) {
      final String search = _searchQuery.trim().toLowerCase();

      final bool matchesSearch =
          search.isEmpty ||
          parcel.trackingNumber.toLowerCase().contains(search) ||
          parcel.customerName.toLowerCase().contains(search) ||
          parcel.customerPhone.toLowerCase().contains(search) ||
          parcel.deliveryAddress.toLowerCase().contains(search) ||
          parcel.pickupAddress.toLowerCase().contains(search) ||
          parcel.originHubName.toLowerCase().contains(search) ||
          parcel.destinationHubName.toLowerCase().contains(search) ||
          parcel.currentHubName.toLowerCase().contains(search) ||
          parcel.riderName.toLowerCase().contains(search) ||
          parcel.linehaulRiderName.toLowerCase().contains(search);

      if (!matchesSearch) {
        return false;
      }

      if (_selectedStatus == 'all') {
        return true;
      }

      final String status = parcel.normalizedStatus;

      final String routingStatus = parcel.normalizedRoutingStatus;

      final String linehaulStatus = parcel.normalizedLinehaulStatus;

      final String sortingStatus = parcel.normalizedSortingStatus;

      switch (_selectedStatus) {
        case 'pending':
          return status == 'pending' ||
              status == 'assigned' ||
              status == 'ready_for_pickup';

        case 'sorting_center':
          return status == 'at_sorting_center' ||
              status == 'sorted' ||
              status == 'staged' ||
              sortingStatus == 'received' ||
              sortingStatus == 'sorting' ||
              sortingStatus == 'sorted' ||
              sortingStatus == 'staged' ||
              routingStatus == 'at_origin_hub';

        case 'linehaul':
          return parcel.requiresHubTransfer ||
              parcel.isLinehaulAwaitingAssignment ||
              parcel.isLinehaulAssigned ||
              parcel.isLinehaulInTransit ||
              routingStatus == 'in_hub_transfer' ||
              linehaulStatus == 'assigned' ||
              linehaulStatus == 'in_transit';

        case 'destination_hub':
          return parcel.isAtDestinationHub ||
              linehaulStatus == 'completed' ||
              routingStatus == 'at_destination_hub';

        case 'out_for_delivery':
          return status == 'out_for_delivery';

        case 'delivered':
          return status == 'delivered' || status == 'completed';

        case 'cancelled':
          return status == 'cancelled' ||
              status == 'delivery_failed' ||
              status == 'failed';

        default:
          return true;
      }
    }).toList();
  }

  // ============================================================
  // COUNTERS
  // ============================================================

  int get _linehaulCount {
    return _parcels.where((ParcelModel parcel) {
      return parcel.requiresHubTransfer ||
          parcel.isLinehaulAssigned ||
          parcel.isLinehaulInTransit;
    }).length;
  }

  int get _sortingCenterCount {
    return _parcels.where((ParcelModel parcel) {
      return parcel.isAtSortingCenter ||
          parcel.isStaged ||
          parcel.normalizedStatus == 'at_sorting_center';
    }).length;
  }

  int get _deliveredCount {
    return _parcels.where((ParcelModel parcel) {
      return parcel.isDelivered;
    }).length;
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
    final double width = MediaQuery.of(context).size.width;

    final double horizontalPadding = width < 380 ? 14 : 18;

    final List<ParcelModel> filteredParcels = _filteredParcels;

    return Scaffold(
      backgroundColor: LogisticsColors.background,
      appBar: AppBar(
        backgroundColor: LogisticsColors.background,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        iconTheme: const IconThemeData(color: LogisticsColors.textDark),
        titleSpacing: 4,
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Parcel List',
              style: TextStyle(
                color: LogisticsColors.textDark,
                fontSize: 20,
                fontWeight: FontWeight.w800,
              ),
            ),
            SizedBox(height: 2),
            Text(
              'Hub routing and parcel operations',
              style: LogisticsStyle.small,
            ),
          ],
        ),
        actions: [
          if (_isRefreshing)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 16),
              child: Center(
                child: SizedBox(
                  height: 19,
                  width: 19,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: LogisticsColors.primary,
                  ),
                ),
              ),
            )
          else
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
        top: false,
        child: Column(
          children: [
            Padding(
              padding: EdgeInsets.fromLTRB(
                horizontalPadding,
                4,
                horizontalPadding,
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
                    'Monitor sorting, hub transfer, '
                    'line-haul, and final delivery.',
                    style: LogisticsStyle.small,
                  ),

                  const SizedBox(height: 16),

                  _searchAndFilter(),

                  const SizedBox(height: 14),

                  _summaryChips(),

                  const SizedBox(height: 18),

                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        _selectedStatus == 'all'
                            ? 'All Parcels'
                            : _filterTitle(_selectedStatus),
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
                          '${filteredParcels.length} '
                          'parcel'
                          '${filteredParcels.length == 1 ? '' : 's'}',
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
                child: _buildParcelBody(horizontalPadding, filteredParcels),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // SEARCH + FILTER
  // ============================================================

  Widget _searchAndFilter() {
    return Row(
      children: [
        Expanded(
          child: Container(
            decoration: BoxDecoration(
              color: LogisticsColors.white,
              borderRadius: BorderRadius.circular(15),
              border: Border.all(color: LogisticsColors.border),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.025),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: TextField(
              controller: _searchController,
              onChanged: (String value) {
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
                hintText: 'Tracking, customer, hub...',
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
                        icon: const Icon(Icons.close_rounded, size: 19),
                      )
                    : null,
                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(vertical: 14),
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
    );
  }

  // ============================================================
  // SUMMARY CHIPS
  // ============================================================

  Widget _summaryChips() {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      physics: const BouncingScrollPhysics(),
      child: Row(
        children: [
          _summaryChip(
            icon: Icons.inventory_2_outlined,
            label: '${_parcels.length} Total',
          ),
          const SizedBox(width: 8),
          _summaryChip(
            icon: Icons.warehouse_outlined,
            label: '$_sortingCenterCount at Hub',
          ),
          const SizedBox(width: 8),
          _summaryChip(
            icon: Icons.multiple_stop_rounded,
            label: '$_linehaulCount Line Haul',
          ),
          const SizedBox(width: 8),
          _summaryChip(
            icon: Icons.check_circle_outline_rounded,
            label: '$_deliveredCount Delivered',
          ),
        ],
      ),
    );
  }

  Widget _summaryChip({required IconData icon, required String label}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: LogisticsColors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: LogisticsColors.border),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: LogisticsColors.primary),
          const SizedBox(width: 5),
          Text(
            label,
            style: const TextStyle(
              color: LogisticsColors.textDark,
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // BODY
  // ============================================================

  Widget _buildParcelBody(
    double horizontalPadding,
    List<ParcelModel> filteredParcels,
  ) {
    if (_isLoading) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          SizedBox(height: 110),
          Center(
            child: CircularProgressIndicator(color: LogisticsColors.primary),
          ),
        ],
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

    return _parcelList(horizontalPadding, filteredParcels);
  }

  // ============================================================
  // PARCEL LIST
  // ============================================================

  Widget _parcelList(double horizontalPadding, List<ParcelModel> parcels) {
    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(
        parent: BouncingScrollPhysics(),
      ),
      padding: EdgeInsets.fromLTRB(horizontalPadding, 4, horizontalPadding, 24),
      itemCount: parcels.length,
      itemBuilder: (BuildContext context, int index) {
        final ParcelModel parcel = parcels[index];

        return _parcelItem(parcel);
      },
    );
  }

  Widget _parcelItem(ParcelModel parcel) {
    final bool showRouting =
        parcel.originHubName.trim().isNotEmpty ||
        parcel.destinationHubName.trim().isNotEmpty ||
        parcel.linehaulStatus.trim().isNotEmpty ||
        parcel.routingStatus.trim().isNotEmpty;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      child: Column(
        children: [
          ParcelCard(
            parcel: parcel,
            buttonText: 'View',
            onButtonPressed: () {
              _openParcel(parcel);
            },
          ),

          if (showRouting)
            Container(
              width: double.infinity,
              margin: const EdgeInsets.only(top: 2),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: LogisticsColors.white,
                borderRadius: const BorderRadius.vertical(
                  bottom: Radius.circular(14),
                ),
                border: Border.all(color: LogisticsColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (parcel.originHubName.trim().isNotEmpty ||
                      parcel.destinationHubName.trim().isNotEmpty)
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(
                          Icons.route_outlined,
                          color: LogisticsColors.primary,
                          size: 16,
                        ),
                        const SizedBox(width: 7),
                        Expanded(
                          child: Text(
                            _routeText(parcel),
                            style: LogisticsStyle.small,
                          ),
                        ),
                      ],
                    ),

                  if (parcel.routingStatus.trim().isNotEmpty) ...[
                    const SizedBox(height: 7),
                    _routeStatusRow(
                      label: 'Routing',
                      value: parcel.routingStatus,
                    ),
                  ],

                  if (parcel.linehaulStatus.trim().isNotEmpty) ...[
                    const SizedBox(height: 6),
                    _routeStatusRow(
                      label: 'Line Haul',
                      value: parcel.linehaulStatus,
                    ),
                  ],
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _routeStatusRow({required String label, required String value}) {
    return Row(
      children: [
        SizedBox(width: 72, child: Text(label, style: LogisticsStyle.small)),
        Expanded(
          child: Text(
            _formatStatus(value),
            style: const TextStyle(
              color: LogisticsColors.textDark,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }

  String _routeText(ParcelModel parcel) {
    final String origin = parcel.originHubName.trim();

    final String destination = parcel.destinationHubName.trim();

    if (origin.isNotEmpty && destination.isNotEmpty) {
      return '$origin → $destination';
    }

    if (origin.isNotEmpty) {
      return 'Origin: $origin';
    }

    if (destination.isNotEmpty) {
      return 'Destination: $destination';
    }

    return 'Hub route unavailable';
  }

  // ============================================================
  // ERROR STATE
  // ============================================================

  Widget _errorState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 30),
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
            icon: const Icon(Icons.refresh_rounded, size: 19),
            label: const Text('Try Again'),
            style: OutlinedButton.styleFrom(
              foregroundColor: LogisticsColors.primary,
              backgroundColor: LogisticsColors.white,
              side: const BorderSide(color: LogisticsColors.primary),
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
      padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 30),
      children: const [
        SizedBox(height: 50),

        Center(
          child: SizedBox(
            height: 100,
            width: 100,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: LogisticsColors.lightPurple,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.inventory_2_outlined,
                color: LogisticsColors.primary,
                size: 46,
              ),
            ),
          ),
        ),

        SizedBox(height: 22),

        Text(
          'No Parcels Yet',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: LogisticsColors.textDark,
            fontSize: 20,
            fontWeight: FontWeight.w800,
          ),
        ),

        SizedBox(height: 8),

        Text(
          'There are currently no shipments '
          'assigned to this logistics account.',
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
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (BuildContext sheetContext) {
        return SafeArea(
          child: Container(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),
            child: SingleChildScrollView(
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
                      Icon(Icons.tune_rounded, color: LogisticsColors.primary),
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
                    icon: Icons.warehouse_outlined,
                    title: 'Sorting Center',
                    value: 'sorting_center',
                    color: LogisticsColors.primary,
                  ),

                  _filterOption(
                    sheetContext,
                    icon: Icons.multiple_stop_rounded,
                    title: 'Line Haul',
                    value: 'linehaul',
                    color: LogisticsColors.primary,
                  ),

                  _filterOption(
                    sheetContext,
                    icon: Icons.location_city_outlined,
                    title: 'Destination Hub',
                    value: 'destination_hub',
                    color: LogisticsColors.primary,
                  ),

                  _filterOption(
                    sheetContext,
                    icon: Icons.delivery_dining_rounded,
                    title: 'Out for Delivery',
                    value: 'out_for_delivery',
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
                    title: 'Cancelled / Failed',
                    value: 'cancelled',
                    color: Colors.red,
                  ),
                ],
              ),
            ),
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
    final bool isSelected = _selectedStatus == value;

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
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
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
                  child: Icon(icon, color: color, size: 20),
                ),

                const SizedBox(width: 12),

                Expanded(child: Text(title, style: LogisticsStyle.cardTitle)),

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

  String _filterTitle(String value) {
    switch (value) {
      case 'pending':
        return 'Pending Parcels';

      case 'sorting_center':
        return 'Sorting Center';

      case 'linehaul':
        return 'Line Haul';

      case 'destination_hub':
        return 'Destination Hub';

      case 'out_for_delivery':
        return 'Out for Delivery';

      case 'delivered':
        return 'Delivered';

      case 'cancelled':
        return 'Cancelled / Failed';

      default:
        return 'All Parcels';
    }
  }

  String _formatStatus(String value) {
    final String cleaned = value
        .trim()
        .replaceAll('-', '_')
        .replaceAll(' ', '_');

    if (cleaned.isEmpty) {
      return 'Not available';
    }

    return cleaned
        .split('_')
        .where((String part) => part.isNotEmpty)
        .map(
          (String part) =>
              '${part[0].toUpperCase()}'
              '${part.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  // ============================================================
  // OPEN PARCEL INFORMATION
  // ============================================================

  Future<void> _openParcel(ParcelModel parcel) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return ParcelInformation(parcel: parcel);
        },
      ),
    );

    if (!mounted) {
      return;
    }

    await _loadParcels(showLoading: false);
  }
}
