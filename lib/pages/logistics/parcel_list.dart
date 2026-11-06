import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../services/api_services.dart';
import '../../styles/logistics_style.dart';
import '../../utils/address_formatter.dart';
import '../../utils/date_formatter.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_skeleton.dart';
import '../../widgets/parcel_card.dart';

import 'parcel_information.dart';

class ParcelList extends StatefulWidget {
  const ParcelList({super.key});

  @override
  State<ParcelList> createState() => _ParcelListState();
}

class _ParcelListState extends State<ParcelList> {
  static const Duration _requestTimeout = Duration(seconds: 25);

  final TextEditingController _searchController = TextEditingController();

  List<ParcelModel> _parcels = <ParcelModel>[];

  String _searchQuery = '';
  String _selectedStatus = 'all';

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _hasLoaded = false;

  String? _errorMessage;
  int _requestId = 0;

  static const List<_ParcelFilterOption> _filterOptions = [
    _ParcelFilterOption(
      value: 'all',
      title: 'All Parcels',
      icon: Icons.inventory_2_outlined,
    ),
    _ParcelFilterOption(
      value: 'pending',
      title: 'Pending',
      icon: Icons.schedule_rounded,
    ),
    _ParcelFilterOption(
      value: 'sorting_center',
      title: 'Sorting Center',
      icon: Icons.warehouse_outlined,
    ),
    _ParcelFilterOption(
      value: 'linehaul',
      title: 'Line Haul',
      icon: Icons.multiple_stop_rounded,
    ),
    _ParcelFilterOption(
      value: 'destination_hub',
      title: 'Destination Hub',
      icon: Icons.location_city_outlined,
    ),
    _ParcelFilterOption(
      value: 'out_for_delivery',
      title: 'Out for Delivery',
      icon: Icons.delivery_dining_rounded,
    ),
    _ParcelFilterOption(
      value: 'delivered',
      title: 'Delivered',
      icon: Icons.check_circle_outline_rounded,
    ),
    _ParcelFilterOption(
      value: 'cancelled',
      title: 'Cancelled / Failed',
      icon: Icons.cancel_outlined,
    ),
  ];

  @override
  void initState() {
    super.initState();
    _loadParcels();
  }

  @override
  void dispose() {
    _requestId++;
    _searchController.dispose();
    super.dispose();
  }

  // ============================================================
  // LOAD PARCELS
  // ============================================================

  Future<void> _loadParcels({bool showLoading = true}) async {
    if (!mounted || _isRefreshing) return;

    // Avoid overlapping initial requests.
    if (_isLoading && _requestId > 0) return;

    final int requestId = ++_requestId;

    setState(() {
      _isLoading = !_hasLoaded && showLoading;
      _isRefreshing = _hasLoaded || !showLoading;
      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.getLogisticsShipments(perPage: 100)
              .timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) return;

      if (result['success'] != true) {
        final String message = result['message']?.toString().trim() ?? '';

        _setLoadError(
          requestId,
          message.isNotEmpty ? message : 'Unable to load logistics parcels.',
        );
        return;
      }

      final List<Map<String, dynamic>> rawItems = _extractShipmentMaps(result);

      final List<ParcelModel> loadedParcels = <ParcelModel>[];

      for (final Map<String, dynamic> item in rawItems) {
        try {
          loadedParcels.add(ParcelModel.fromJson(_normalizeShipment(item)));
        } catch (error) {
          debugPrint('Unable to parse logistics parcel: $error');
        }
      }

      loadedParcels.sort(_compareParcels);

      if (!mounted || requestId != _requestId) return;

      setState(() {
        _parcels = loadedParcels;
        _hasLoaded = true;
        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = null;
      });
    } on TimeoutException {
      _setLoadError(
        requestId,
        'The server took too long to respond. '
        'Please check your connection and retry.',
      );
    } catch (error) {
      debugPrint('Logistics parcel list error: $error');

      _setLoadError(
        requestId,
        'Unable to connect to the BiliHub server. '
        'Please check your internet connection.',
      );
    }
  }

  Future<void> _refreshParcels() {
    return _loadParcels(showLoading: false);
  }

  void _setLoadError(int requestId, String message) {
    if (!mounted || requestId != _requestId) return;

    setState(() {
      _isLoading = false;
      _isRefreshing = false;
      _errorMessage = message;
    });
  }

  // ============================================================
  // EXTRACT SHIPMENT API DATA
  // ============================================================

  List<Map<String, dynamic>> _extractShipmentMaps(Map<String, dynamic> result) {
    for (final String key in ['data', 'shipments', 'parcels', 'orders']) {
      if (result[key] is List) {
        return _toMapList(result[key]);
      }
    }

    final dynamic rawData = result['data'];

    if (rawData is Map) {
      for (final String key in ['data', 'shipments', 'parcels', 'orders']) {
        if (rawData[key] is List) {
          return _toMapList(rawData[key]);
        }
      }
    }

    return <Map<String, dynamic>>[];
  }

  List<Map<String, dynamic>> _toMapList(dynamic value) {
    if (value is! List) {
      return <Map<String, dynamic>>[];
    }

    return value
        .whereType<Map>()
        .map((Map item) => Map<String, dynamic>.from(item))
        .toList();
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

    final Map<String, dynamic> order = _asMap(raw['order']);

    data['customer_name'] ??=
        order['customer_name'] ?? order['buyer_name'] ?? order['name'];

    data['customer_phone'] ??= order['customer_phone'] ?? order['phone'];

    data['order_date'] ??= order['order_date'] ?? order['created_at'];

    final Map<String, dynamic> rider = _asMap(data['rider']);

    data['rider_id'] ??= rider['id'];
    data['rider_name'] ??= rider['name'];

    final Map<String, dynamic> linehaulRider = _asMap(data['linehaul_rider']);

    data['linehaul_rider_id'] ??= linehaulRider['id'];
    data['linehaul_rider_name'] ??= linehaulRider['name'];

    final Map<String, dynamic> originHub = _asMap(data['origin_hub']);

    data['origin_hub_id'] ??= originHub['id'];
    data['origin_hub_name'] ??= originHub['name'];

    final Map<String, dynamic> destinationHub = _asMap(data['destination_hub']);

    data['destination_hub_id'] ??= destinationHub['id'];
    data['destination_hub_name'] ??= destinationHub['name'];

    final Map<String, dynamic> currentHub = _asMap(data['hub']);

    data['current_hub_id'] ??= currentHub['id'];
    data['current_hub_name'] ??= currentHub['name'];

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

    data['delivery_address'] = _resolveDeliveryAddress(data, raw, order);

    data['pickup_address'] = AddressFormatter.clean(
      data['pickup_address'] ?? data['seller_address'],
    );

    data['rider_name'] ??= '';

    data['status'] ??=
        data['shipment_status'] ?? data['delivery_status'] ?? 'pending';

    data['order_date'] ??= data['created_at'] ?? data['assigned_at'] ?? '';

    data['delivery_earnings'] ??= data['earnings'] ?? data['delivery_fee'] ?? 0;

    data['latitude'] ??= data['lat'];
    data['longitude'] ??= data['lng'] ?? data['lon'];

    return data;
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }

    return <String, dynamic>{};
  }

  String _resolveDeliveryAddress(
    Map<String, dynamic> shipment,
    Map<String, dynamic> raw,
    Map<String, dynamic> order,
  ) {
    for (final Map<String, dynamic> source in [shipment, raw, order]) {
      for (final String key in [
        'delivery_address',
        'full_delivery_address',
        'shipping_address',
        'recipient_address',
        'buyer_address',
        'address',
        'delivery_address_details',
        'shipping_address_details',
        'address_details',
      ]) {
        final String address = AddressFormatter.clean(source[key]);

        if (address.isNotEmpty) {
          return address;
        }
      }
    }

    return '';
  }

  // ============================================================
  // SORT PARCELS
  // ============================================================

  DateTime? _activityDate(ParcelModel parcel) {
    final List<String> dates = [
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

    for (final String raw in dates) {
      final String value = raw.trim();

      if (value.isEmpty || value.toLowerCase() == 'null') {
        continue;
      }

      final DateTime? parsed = DateFormatter.value(value);

      if (parsed != null) return parsed;
    }

    return null;
  }

  int _compareParcels(ParcelModel a, ParcelModel b) {
    final DateTime? aDate = _activityDate(a);
    final DateTime? bDate = _activityDate(b);

    if (aDate != null && bDate != null) {
      final int result = bDate.compareTo(aDate);
      if (result != 0) return result;
    } else if (aDate != null) {
      return -1;
    } else if (bDate != null) {
      return 1;
    }

    return b.id.compareTo(a.id);
  }

  // ============================================================
  // SEARCH AND FILTER
  // ============================================================

  List<ParcelModel> get _filteredParcels {
    final String search = _searchQuery.trim().toLowerCase();

    return _parcels.where((ParcelModel parcel) {
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

      if (!matchesSearch) return false;

      final String status = parcel.normalizedStatus;
      final String routing = parcel.normalizedRoutingStatus;
      final String linehaul = parcel.normalizedLinehaulStatus;
      final String sorting = parcel.normalizedSortingStatus;

      switch (_selectedStatus) {
        case 'all':
          return true;

        case 'pending':
          return status == 'pending' ||
              status == 'assigned' ||
              status == 'ready_for_pickup';

        case 'sorting_center':
          return status == 'at_sorting_center' ||
              status == 'sorted' ||
              status == 'staged' ||
              sorting == 'received' ||
              sorting == 'sorting' ||
              sorting == 'sorted' ||
              sorting == 'staged' ||
              routing == 'at_origin_hub';

        case 'linehaul':
          return parcel.requiresHubTransfer ||
              parcel.isLinehaulAwaitingAssignment ||
              parcel.isLinehaulAssigned ||
              parcel.isLinehaulInTransit ||
              routing == 'in_hub_transfer' ||
              linehaul == 'assigned' ||
              linehaul == 'in_transit';

        case 'destination_hub':
          return parcel.isAtDestinationHub ||
              linehaul == 'completed' ||
              routing == 'at_destination_hub';

        case 'out_for_delivery':
          return status == 'out_for_delivery';

        case 'delivered':
          return status == 'delivered' || status == 'completed';

        case 'cancelled':
          return status == 'cancelled' ||
              status == 'canceled' ||
              status == 'delivery_failed' ||
              status == 'failed' ||
              status == 'failed_return_at_sorting_center';

        default:
          return true;
      }
    }).toList();
  }

  int get _linehaulCount => _parcels.where((ParcelModel parcel) {
    return parcel.requiresHubTransfer ||
        parcel.isLinehaulAssigned ||
        parcel.isLinehaulInTransit;
  }).length;

  int get _sortingCenterCount => _parcels.where((ParcelModel parcel) {
    return parcel.isAtSortingCenter ||
        parcel.isStaged ||
        parcel.normalizedStatus == 'at_sorting_center';
  }).length;

  int get _deliveredCount => _parcels.where((ParcelModel parcel) {
    return parcel.isDelivered;
  }).length;

  bool get _hasFilters =>
      _searchQuery.trim().isNotEmpty || _selectedStatus != 'all';

  void _clearFilters() {
    _searchController.clear();

    setState(() {
      _searchQuery = '';
      _selectedStatus = 'all';
    });
  }

  void _clearSearch() {
    _searchController.clear();

    setState(() {
      _searchQuery = '';
    });
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.sizeOf(context).width;
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
          IconButton(
            tooltip: 'Refresh parcels',
            onPressed: _isLoading || _isRefreshing ? null : _refreshParcels,
            icon: _isRefreshing
                ? const SizedBox(
                    width: 19,
                    height: 19,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: LogisticsColors.primary,
                    ),
                  )
                : const Icon(
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

                  if (_hasLoaded)
                    _summaryChips()
                  else if (_isLoading)
                    _summarySkeleton(),

                  const SizedBox(height: 14),

                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          _filterTitle(_selectedStatus),
                          style: LogisticsStyle.sectionTitle,
                        ),
                      ),
                      if (_hasLoaded)
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
                            '${filteredParcels.length == 1 ? 'parcel' : 'parcels'}',
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
                color: LogisticsColors.primary,
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
  // SEARCH BAR
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
                        tooltip: 'Clear search',
                        onPressed: _clearSearch,
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
              width: 50,
              height: 50,
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
                    const Positioned(
                      top: 8,
                      right: 8,
                      child: CircleAvatar(
                        radius: 3.5,
                        backgroundColor: LogisticsColors.warning,
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
  // SUMMARY
  // ============================================================

  Widget _summarySkeleton() {
    final Color placeholder = Theme.of(context)
        .colorScheme
        .surfaceContainerHighest;

    return SizedBox(
      height: 33,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: 4,
        separatorBuilder: (BuildContext context, int index) {
          return const SizedBox(width: 8);
        },
        itemBuilder: (BuildContext context, int index) {
          return Container(
            width: 100,
            decoration: BoxDecoration(
              color: placeholder,
              borderRadius: BorderRadius.circular(20),
            ),
          );
        },
      ),
    );
  }

  Widget _summaryChips() {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      physics: const BouncingScrollPhysics(),
      child: Row(
        children: [
          _summaryChip(
            icon: Icons.inventory_2_outlined,
            label: '${_parcels.length} Loaded',
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
  // PARCEL BODY AND SKELETON LOADING
  // ============================================================

  Widget _buildParcelBody(
    double horizontalPadding,
    List<ParcelModel> filteredParcels,
  ) {
    if (_isLoading && !_hasLoaded) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(
          horizontalPadding,
          12,
          horizontalPadding,
          24,
        ),
        children: const [
          AppSkeleton(
            type: AppSkeletonType.parcel,
            itemCount: 4,
            color: LogisticsColors.primary,
          ),
        ],
      );
    }

    if (!_hasLoaded) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          const SizedBox(height: 70),
          AppErrorState(
            title: 'Unable to Load Parcels',
            message: _errorMessage ?? 'The shipment list is unavailable.',
            color: LogisticsColors.primary,
            onRetry: _loadParcels,
          ),
        ],
      );
    }

    if (_parcels.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          if (_isRefreshing) _refreshIndicator(),
          if (_errorMessage != null) _refreshWarning(),
          const SizedBox(height: 75),
          const AppEmptyState(
            title: 'No Parcels Yet',
            message:
                'There are currently no shipments '
                'assigned to this logistics account.',
            icon: Icons.inventory_2_outlined,
            color: LogisticsColors.primary,
          ),
        ],
      );
    }

    if (filteredParcels.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          if (_isRefreshing) _refreshIndicator(),
          if (_errorMessage != null) _refreshWarning(),
          const SizedBox(height: 65),
          AppEmptyState(
            title: 'No Parcels Found',
            message: _searchQuery.isNotEmpty
                ? 'No loaded parcels match "$_searchQuery".'
                : 'No loaded parcels match this filter.',
            icon: Icons.search_off_rounded,
            color: LogisticsColors.primary,
            actionLabel: _hasFilters ? 'Clear Filters' : null,
            onAction: _hasFilters ? _clearFilters : null,
          ),
        ],
      );
    }

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(
        parent: BouncingScrollPhysics(),
      ),
      padding: EdgeInsets.fromLTRB(horizontalPadding, 4, horizontalPadding, 24),
      itemCount:
          filteredParcels.length +
          (_isRefreshing ? 1 : 0) +
          (_errorMessage != null ? 1 : 0),
      itemBuilder: (BuildContext context, int index) {
        int adjustedIndex = index;

        if (_isRefreshing) {
          if (adjustedIndex == 0) {
            return _refreshIndicator();
          }
          adjustedIndex--;
        }

        if (_errorMessage != null) {
          if (adjustedIndex == 0) {
            return _refreshWarning();
          }
          adjustedIndex--;
        }

        return _parcelItem(filteredParcels[adjustedIndex]);
      },
    );
  }

  Widget _refreshIndicator() {
    return const Padding(
      padding: EdgeInsets.only(bottom: 12),
      child: LinearProgressIndicator(
        color: LogisticsColors.primary,
        minHeight: 2,
      ),
    );
  }

  Widget _refreshWarning() {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(13),
      decoration: LogisticsStyle.card,
      child: Row(
        children: [
          const Icon(
            Icons.wifi_off_rounded,
            color: LogisticsColors.warning,
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Showing previously loaded parcels. '
              '${_errorMessage ?? 'Refresh failed.'}',
              style: LogisticsStyle.small,
            ),
          ),
          TextButton(
            onPressed: _isLoading || _isRefreshing ? null : _refreshParcels,
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PARCEL ITEM
  // ============================================================

  Widget _parcelItem(ParcelModel parcel) {
    final bool hasRoute =
        parcel.originHubName.trim().isNotEmpty ||
        parcel.destinationHubName.trim().isNotEmpty;

    final bool showRouting =
        hasRoute ||
        parcel.routingStatus.trim().isNotEmpty ||
        parcel.linehaulStatus.trim().isNotEmpty;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      child: Column(
        children: [
          ParcelCard(
            parcel: parcel,
            buttonText: 'View',
            onButtonPressed: () => _openParcel(parcel),
          ),
          if (showRouting)
            Container(
              width: double.infinity,
              margin: const EdgeInsets.only(top: 3),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: LogisticsColors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: LogisticsColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (hasRoute)
                    AppInfoRow(
                      label: 'Hub Route',
                      value: _routeText(parcel),
                      icon: Icons.route_outlined,
                      iconColor: LogisticsColors.primary,
                      compact: true,
                    ),
                  if (parcel.routingStatus.trim().isNotEmpty) ...[
                    const SizedBox(height: 7),
                    AppInfoRow(
                      label: 'Routing Status',
                      value: _formatStatus(parcel.routingStatus),
                      icon: Icons.hub_outlined,
                      iconColor: LogisticsColors.primary,
                      compact: true,
                    ),
                  ],
                  if (parcel.linehaulStatus.trim().isNotEmpty) ...[
                    const SizedBox(height: 7),
                    AppInfoRow(
                      label: 'Line-Haul Status',
                      value: _formatStatus(parcel.linehaulStatus),
                      icon: Icons.multiple_stop_rounded,
                      iconColor: LogisticsColors.primary,
                      compact: true,
                    ),
                  ],
                ],
              ),
            ),
        ],
      ),
    );
  }

  String _routeText(ParcelModel parcel) {
    final String origin = parcel.originHubName.trim();
    final String destination = parcel.destinationHubName.trim();

    if (origin.isNotEmpty && destination.isNotEmpty) {
      return '$origin → $destination';
    }

    if (origin.isNotEmpty) return 'Origin: $origin';
    if (destination.isNotEmpty) {
      return 'Destination: $destination';
    }

    return 'Hub route unavailable';
  }

  // ============================================================
  // FILTER BOTTOM SHEET
  // ============================================================

  void _showFilterOptions() {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (BuildContext sheetContext) {
        return SafeArea(
          child: Container(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.sizeOf(sheetContext).height * 0.8,
            ),
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
            decoration: const BoxDecoration(
              color: LogisticsColors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
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
                    Icon(Icons.tune_rounded, color: LogisticsColors.primary),
                    SizedBox(width: 10),
                    Text('Filter Parcels', style: LogisticsStyle.sectionTitle),
                  ],
                ),
                const SizedBox(height: 18),
                Flexible(
                  child: ListView.builder(
                    shrinkWrap: true,
                    itemCount: _filterOptions.length,
                    itemBuilder: (BuildContext context, int index) {
                      final _ParcelFilterOption option = _filterOptions[index];

                      return _filterOption(sheetContext, option);
                    },
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _filterOption(BuildContext sheetContext, _ParcelFilterOption option) {
    final bool isSelected = _selectedStatus == option.value;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () {
            Navigator.pop(sheetContext);

            setState(() {
              _selectedStatus = option.value;
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
                    color: LogisticsColors.lightPurple,
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: Icon(
                    option.icon,
                    color: LogisticsColors.primary,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(option.title, style: LogisticsStyle.cardTitle),
                ),
                Icon(
                  isSelected
                      ? Icons.check_rounded
                      : Icons.chevron_right_rounded,
                  color: isSelected
                      ? LogisticsColors.primary
                      : LogisticsColors.textLight,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _filterTitle(String value) {
    for (final _ParcelFilterOption option in _filterOptions) {
      if (option.value == value) {
        return option.title;
      }
    }

    return 'All Parcels';
  }

  String _formatStatus(String value) {
    final String normalized = value
        .trim()
        .replaceAll('-', '_')
        .replaceAll(' ', '_');

    if (normalized.isEmpty) {
      return 'Not available';
    }

    return normalized
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
  // OPEN PARCEL DETAILS
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

    if (!mounted) return;

    await _refreshParcels();
  }
}

class _ParcelFilterOption {
  final String value;
  final String title;
  final IconData icon;

  const _ParcelFilterOption({
    required this.value,
    required this.title,
    required this.icon,
  });
}
