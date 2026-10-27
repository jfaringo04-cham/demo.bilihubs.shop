import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../sevices/api_services.dart';
import '../../styles/rider_style.dart';

class DeliveredParcels extends StatefulWidget {
  final List<ParcelModel>? parcels;

  const DeliveredParcels({super.key, this.parcels});

  @override
  State<DeliveredParcels> createState() => _DeliveredParcelsState();
}

class _DeliveredParcelsState extends State<DeliveredParcels> {
  final TextEditingController _searchController = TextEditingController();

  bool _isLoading = true;
  bool _isRefreshing = false;

  String? _errorMessage;
  String _searchQuery = '';

  List<ParcelModel> _deliveredParcels = <ParcelModel>[];

  @override
  void initState() {
    super.initState();

    if (widget.parcels != null && widget.parcels!.isNotEmpty) {
      _deliveredParcels = widget.parcels!.where(_isDeliveredParcel).toList();

      _sortParcels();

      _isLoading = false;
    } else {
      _loadDeliveredParcels();
    }
  }

  @override
  void dispose() {
    _searchController.dispose();

    super.dispose();
  }

  // ============================================================
  // LOAD DELIVERY HISTORY
  // ============================================================

  Future<void> _loadDeliveredParcels({bool refresh = false}) async {
    if (!mounted) return;

    setState(() {
      if (refresh) {
        _isRefreshing = true;
      } else {
        _isLoading = true;
      }

      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> result = await ApiService.getRiderDeliveries();

      if (!mounted) return;

      if (result['success'] != true) {
        setState(() {
          _deliveredParcels = <ParcelModel>[];

          _isLoading = false;
          _isRefreshing = false;

          final String message = result['message']?.toString().trim() ?? '';

          _errorMessage = message.isNotEmpty
              ? message
              : 'Unable to load delivery history.';
        });

        return;
      }

      final List<Map<String, dynamic>> rawItems = _extractDeliveryMaps(result);

      final List<ParcelModel> parsedParcels = <ParcelModel>[];

      for (final Map<String, dynamic> raw in rawItems) {
        try {
          final Map<String, dynamic> normalized = _normalizeShipment(raw);

          final ParcelModel parcel = ParcelModel.fromJson(normalized);

          if (_isDeliveredParcel(parcel)) {
            parsedParcels.add(parcel);
          }
        } catch (error) {
          debugPrint('Failed to parse delivered parcel: $error');
        }
      }

      parsedParcels.sort((ParcelModel a, ParcelModel b) {
        final DateTime? aDate = DateTime.tryParse(a.orderDate);

        final DateTime? bDate = DateTime.tryParse(b.orderDate);

        if (aDate == null && bDate == null) {
          return 0;
        }

        if (aDate == null) {
          return 1;
        }

        if (bDate == null) {
          return -1;
        }

        return bDate.compareTo(aDate);
      });

      setState(() {
        _deliveredParcels = parsedParcels;

        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = null;
      });
    } catch (error) {
      debugPrint('Delivery history error: $error');

      if (!mounted) return;

      setState(() {
        _deliveredParcels = <ParcelModel>[];

        _isLoading = false;
        _isRefreshing = false;

        _errorMessage = 'Unable to connect to the server. Please try again.';
      });
    }
  }

  // ============================================================
  // EXTRACT DELIVERY MAPS
  // ============================================================

  List<Map<String, dynamic>> _extractDeliveryMaps(Map<String, dynamic> result) {
    final List<Map<String, dynamic>> direct = _toMapList(result['data']);

    if (direct.isNotEmpty) {
      return direct;
    }

    final List<Map<String, dynamic>> directDeliveries = _toMapList(
      result['deliveries'],
    );

    if (directDeliveries.isNotEmpty) {
      return directDeliveries;
    }

    final List<Map<String, dynamic>> directOrders = _toMapList(
      result['orders'],
    );

    if (directOrders.isNotEmpty) {
      return directOrders;
    }

    final List<Map<String, dynamic>> directShipments = _toMapList(
      result['shipments'],
    );

    if (directShipments.isNotEmpty) {
      return directShipments;
    }

    final dynamic rawData = result['data'];

    if (rawData is Map) {
      final Map<String, dynamic> data = Map<String, dynamic>.from(rawData);

      final List<Map<String, dynamic>> nestedData = _toMapList(data['data']);

      if (nestedData.isNotEmpty) {
        return nestedData;
      }

      final List<Map<String, dynamic>> deliveries = _toMapList(
        data['deliveries'],
      );

      if (deliveries.isNotEmpty) {
        return deliveries;
      }

      final List<Map<String, dynamic>> orders = _toMapList(data['orders']);

      if (orders.isNotEmpty) {
        return orders;
      }

      final List<Map<String, dynamic>> shipments = _toMapList(
        data['shipments'],
      );

      if (shipments.isNotEmpty) {
        return shipments;
      }
    }

    return <Map<String, dynamic>>[];
  }

  // ============================================================
  // CONVERT TO MAP LIST
  // ============================================================

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
    final Map<String, dynamic> map = Map<String, dynamic>.from(raw);

    final dynamic nestedShipment = raw['shipment'];

    if (nestedShipment is Map) {
      map.addAll(Map<String, dynamic>.from(nestedShipment));
    }

    final dynamic nestedOrder = raw['order'];

    if (nestedOrder is Map) {
      final Map<String, dynamic> order = Map<String, dynamic>.from(nestedOrder);

      map['order_number'] ??= order['order_number'] ?? order['number'];

      map['created_at'] ??= order['created_at'];

      map['customer_name'] ??= order['customer_name'];

      map['customer_phone'] ??= order['customer_phone'];

      map['delivery_address'] ??= order['delivery_address'];
    }

    map['id'] ??= map['shipment_id'] ?? map['order_id'] ?? 0;

    map['shipment_id'] ??= map['id'];

    map['tracking_number'] ??=
        map['tracking_code'] ?? map['waybill_number'] ?? '';

    map['customer_name'] ??= map['recipient_name'] ?? map['buyer_name'] ?? '';

    map['customer_phone'] ??= map['phone'] ?? map['recipient_phone'] ?? '';

    map['delivery_address'] ??= map['address'] ?? map['shipping_address'] ?? '';

    map['rider_name'] ??= map['driver_name'] ?? '';

    map['rider_id'] ??= map['driver_id'];

    map['status'] ??= map['shipment_status'] ?? map['delivery_status'] ?? '';

    map['order_date'] ??= map['delivered_at'] ?? map['created_at'] ?? '';

    map['delivery_earnings'] ??= map['earnings'] ?? map['delivery_fee'] ?? 0;

    map['latitude'] ??= map['rider_latitude'];

    map['longitude'] ??= map['rider_longitude'];

    return map;
  }

  // ============================================================
  // DELIVERY STATUS
  // ============================================================

  bool _isDeliveredParcel(ParcelModel parcel) {
    final String status = parcel.status
        .trim()
        .toLowerCase()
        .replaceAll(' ', '_')
        .replaceAll('-', '_');

    return status == 'delivered' || status == 'completed';
  }

  // ============================================================
  // SORT
  // ============================================================

  void _sortParcels() {
    _deliveredParcels.sort((ParcelModel a, ParcelModel b) {
      final DateTime? aDate = DateTime.tryParse(a.orderDate);

      final DateTime? bDate = DateTime.tryParse(b.orderDate);

      if (aDate == null && bDate == null) {
        return 0;
      }

      if (aDate == null) {
        return 1;
      }

      if (bDate == null) {
        return -1;
      }

      return bDate.compareTo(aDate);
    });
  }

  // ============================================================
  // FILTERED HISTORY
  // ============================================================

  List<ParcelModel> get _filteredParcels {
    final String query = _searchQuery.trim().toLowerCase();

    if (query.isEmpty) {
      return _deliveredParcels;
    }

    return _deliveredParcels.where((ParcelModel parcel) {
      return parcel.trackingNumber.toLowerCase().contains(query) ||
          parcel.customerName.toLowerCase().contains(query) ||
          parcel.customerPhone.toLowerCase().contains(query) ||
          parcel.deliveryAddress.toLowerCase().contains(query);
    }).toList();
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.of(context).size.width;

    final double horizontalPadding = width < 380 ? 14 : 18;

    final List<ParcelModel> parcels = _filteredParcels;

    return Scaffold(
      backgroundColor: RiderColors.background,

      appBar: AppBar(
        backgroundColor: RiderColors.white,

        elevation: 0,

        surfaceTintColor: Colors.transparent,

        iconTheme: const IconThemeData(color: RiderColors.textDark),

        titleSpacing: 18,

        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            Text('Delivery History', style: RiderStyle.title),

            SizedBox(height: 2),

            Text('Your completed deliveries', style: RiderStyle.small),
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

                    color: RiderColors.primary,
                  ),
                ),
              ),
            )
          else
            IconButton(
              tooltip: 'Refresh',

              onPressed: _isLoading
                  ? null
                  : () {
                      _loadDeliveredParcels(refresh: true);
                    },

              icon: const Icon(
                Icons.refresh_rounded,

                color: RiderColors.primary,
              ),
            ),

          const SizedBox(width: 5),
        ],
      ),

      body: SafeArea(
        top: false,

        child: Column(
          children: [
            // ==================================================
            // SEARCH / HEADER
            // ==================================================

            Container(
              width: double.infinity,

              color: RiderColors.white,

              padding: EdgeInsets.fromLTRB(
                horizontalPadding,
                14,
                horizontalPadding,
                15,
              ),

              child: Column(
                children: [
                  Row(
                    children: [
                      const Expanded(
                        child: Text(
                          'Completed Deliveries',

                          style: RiderStyle.sectionTitle,
                        ),
                      ),

                      if (!_isLoading)
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 5,
                          ),

                          decoration: BoxDecoration(
                            color: RiderColors.lightGreen,

                            borderRadius: BorderRadius.circular(20),
                          ),

                          child: Text(
                            '${parcels.length}',

                            style: const TextStyle(
                              color: RiderColors.success,

                              fontSize: 10,

                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                    ],
                  ),

                  const SizedBox(height: 12),

                  TextField(
                    controller: _searchController,

                    onChanged: (String value) {
                      setState(() {
                        _searchQuery = value;
                      });
                    },

                    textInputAction: TextInputAction.search,

                    decoration: InputDecoration(
                      hintText: 'Search tracking, customer, address...',

                      hintStyle: RiderStyle.small,

                      prefixIcon: const Icon(
                        Icons.search_rounded,

                        color: RiderColors.primary,

                        size: 21,
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

                                color: RiderColors.textGrey,
                              ),
                            )
                          : null,

                      filled: true,

                      fillColor: RiderColors.background,

                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 13,
                        vertical: 13,
                      ),

                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(13),

                        borderSide: BorderSide.none,
                      ),

                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(13),

                        borderSide: const BorderSide(color: RiderColors.border),
                      ),

                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(13),

                        borderSide: const BorderSide(
                          color: RiderColors.primary,

                          width: 1.3,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // ==================================================
            // BODY
            // ==================================================
            Expanded(
              child: RefreshIndicator(
                onRefresh: () {
                  return _loadDeliveredParcels(refresh: true);
                },

                child: _buildBody(horizontalPadding, parcels),
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

  Widget _buildBody(double horizontalPadding, List<ParcelModel> parcels) {
    if (_isLoading) {
      return _loadingState();
    }

    if (_errorMessage != null) {
      return _errorState();
    }

    if (_deliveredParcels.isEmpty) {
      return _emptyState();
    }

    if (parcels.isEmpty) {
      return _noSearchResults();
    }

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(
        parent: BouncingScrollPhysics(),
      ),

      padding: EdgeInsets.fromLTRB(
        horizontalPadding,
        16,
        horizontalPadding,
        28,
      ),

      itemCount: parcels.length,

      itemBuilder: (BuildContext context, int index) {
        return _historyCard(parcels[index]);
      },
    );
  }

  // ============================================================
  // HISTORY CARD
  // ============================================================

  Widget _historyCard(ParcelModel parcel) {
    final String formattedDate = _formatDate(parcel.orderDate);

    return Container(
      margin: const EdgeInsets.only(bottom: 14),

      padding: const EdgeInsets.all(16),

      decoration: RiderStyle.card,

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Container(
                height: 45,
                width: 45,

                decoration: BoxDecoration(
                  color: RiderColors.lightGreen,

                  borderRadius: BorderRadius.circular(13),
                ),

                child: const Icon(
                  Icons.check_circle_outline_rounded,

                  color: RiderColors.success,

                  size: 23,
                ),
              ),

              const SizedBox(width: 12),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text('Tracking #', style: RiderStyle.small),

                    const SizedBox(height: 3),

                    Text(
                      _displayValue(
                        parcel.trackingNumber,

                        fallback: 'Tracking unavailable',
                      ),

                      maxLines: 1,

                      overflow: TextOverflow.ellipsis,

                      style: RiderStyle.cardTitle,
                    ),
                  ],
                ),
              ),

              const SizedBox(width: 8),

              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),

                decoration: BoxDecoration(
                  color: RiderColors.success.withValues(alpha: 0.10),

                  borderRadius: BorderRadius.circular(20),
                ),

                child: Text(
                  _formatStatus(parcel.status),

                  style: const TextStyle(
                    color: RiderColors.success,

                    fontSize: 9,

                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 17),

          _infoRow(
            icon: Icons.person_outline_rounded,

            label: 'Customer',

            value: _displayValue(parcel.customerName),
          ),

          _infoRow(
            icon: Icons.phone_outlined,

            label: 'Contact',

            value: _displayValue(parcel.customerPhone),
          ),

          _infoRow(
            icon: Icons.location_on_outlined,

            label: 'Address',

            value: _displayValue(parcel.deliveryAddress),
          ),

          if (formattedDate.isNotEmpty)
            _infoRow(
              icon: Icons.calendar_today_outlined,

              label: 'Delivery Date',

              value: formattedDate,
            ),

          const SizedBox(height: 4),

          Divider(color: RiderColors.border.withValues(alpha: 0.8)),

          const SizedBox(height: 7),

          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text('Delivery Earnings', style: RiderStyle.small),

                    const SizedBox(height: 3),

                    Text(
                      parcel.deliveryEarnings > 0
                          ? '₱${parcel.deliveryEarnings.toStringAsFixed(2)}'
                          : '—',

                      style: const TextStyle(
                        color: RiderColors.earnings,

                        fontSize: 17,

                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),

              OutlinedButton.icon(
                onPressed: () {
                  _showDeliveryDetails(parcel);
                },

                icon: const Icon(Icons.visibility_outlined, size: 16),

                label: const Text('View'),

                style: OutlinedButton.styleFrom(
                  foregroundColor: RiderColors.primary,

                  side: const BorderSide(color: RiderColors.primary),

                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(11),
                  ),

                  textStyle: const TextStyle(
                    fontSize: 11,

                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ============================================================
  // INFO ROW
  // ============================================================

  Widget _infoRow({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 11),

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Container(
            height: 34,
            width: 34,

            decoration: BoxDecoration(
              color: RiderColors.lightPurple,

              borderRadius: BorderRadius.circular(10),
            ),

            child: Icon(icon, color: RiderColors.primary, size: 17),
          ),

          const SizedBox(width: 10),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(label, style: RiderStyle.small),

                const SizedBox(height: 2),

                Text(
                  value,

                  maxLines: label == 'Address' ? 3 : 2,

                  overflow: TextOverflow.ellipsis,

                  style: RiderStyle.cardTitle,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // DETAILS
  // ============================================================

  void _showDeliveryDetails(ParcelModel parcel) {
    showModalBottomSheet<void>(
      context: context,

      isScrollControlled: true,

      backgroundColor: Colors.transparent,

      builder: (BuildContext sheetContext) {
        final String formattedDate = _formatDate(parcel.orderDate);

        return SafeArea(
          child: Container(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(sheetContext).size.height * 0.82,
            ),

            padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),

            decoration: const BoxDecoration(
              color: RiderColors.white,

              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),

            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  Center(
                    child: Container(
                      height: 4,
                      width: 42,

                      decoration: BoxDecoration(
                        color: RiderColors.border,

                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),

                  const SizedBox(height: 18),

                  Row(
                    children: [
                      Container(
                        height: 46,
                        width: 46,

                        decoration: BoxDecoration(
                          color: RiderColors.lightGreen,

                          borderRadius: BorderRadius.circular(13),
                        ),

                        child: const Icon(
                          Icons.check_circle_outline_rounded,

                          color: RiderColors.success,

                          size: 23,
                        ),
                      ),

                      const SizedBox(width: 12),

                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,

                          children: [
                            const Text(
                              'Delivery Details',

                              style: RiderStyle.sectionTitle,
                            ),

                            const SizedBox(height: 3),

                            Text(
                              _displayValue(parcel.trackingNumber),

                              style: RiderStyle.small,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 22),

                  _detailTile(
                    'Tracking Number',
                    _displayValue(parcel.trackingNumber),
                  ),

                  _detailTile('Customer', _displayValue(parcel.customerName)),

                  _detailTile('Contact', _displayValue(parcel.customerPhone)),

                  _detailTile(
                    'Delivery Address',
                    _displayValue(parcel.deliveryAddress),
                  ),

                  _detailTile('Rider', _displayValue(parcel.riderName)),

                  if (formattedDate.isNotEmpty)
                    _detailTile('Delivery Date', formattedDate),

                  _detailTile('Status', _formatStatus(parcel.status)),

                  _detailTile(
                    'Delivery Earnings',
                    parcel.deliveryEarnings > 0
                        ? '₱${parcel.deliveryEarnings.toStringAsFixed(2)}'
                        : 'Not available',
                  ),

                  const SizedBox(height: 10),

                  SizedBox(
                    width: double.infinity,

                    height: 48,

                    child: ElevatedButton(
                      onPressed: () {
                        Navigator.pop(sheetContext);
                      },

                      style: RiderStyle.primaryButton,

                      child: const Text('Close', style: RiderStyle.buttonText),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _detailTile(String title, String value) {
    return Container(
      width: double.infinity,

      margin: const EdgeInsets.only(bottom: 10),

      padding: const EdgeInsets.all(13),

      decoration: BoxDecoration(
        color: RiderColors.background,

        borderRadius: BorderRadius.circular(12),

        border: Border.all(color: RiderColors.border),
      ),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Text(title, style: RiderStyle.small),

          const SizedBox(height: 4),

          Text(value, style: RiderStyle.cardTitle),
        ],
      ),
    );
  }

  // ============================================================
  // LOADING
  // ============================================================

  Widget _loadingState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),

      children: const [
        SizedBox(height: 120),

        Center(
          child: Column(
            children: [
              CircularProgressIndicator(color: RiderColors.primary),

              SizedBox(height: 15),

              Text('Loading delivery history...', style: RiderStyle.small),
            ],
          ),
        ),
      ],
    );
  }

  // ============================================================
  // ERROR
  // ============================================================

  Widget _errorState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),

      padding: const EdgeInsets.all(30),

      children: [
        const SizedBox(height: 100),

        const Icon(
          Icons.cloud_off_outlined,

          size: 65,

          color: RiderColors.textGrey,
        ),

        const SizedBox(height: 15),

        const Text(
          'Unable to Load Delivery History',

          textAlign: TextAlign.center,

          style: TextStyle(
            fontWeight: FontWeight.w800,

            fontSize: 17,

            color: RiderColors.textDark,
          ),
        ),

        const SizedBox(height: 8),

        Text(
          _errorMessage ?? 'Something went wrong.',

          textAlign: TextAlign.center,

          style: RiderStyle.small,
        ),

        const SizedBox(height: 18),

        Center(
          child: ElevatedButton.icon(
            onPressed: () {
              _loadDeliveredParcels(refresh: true);
            },

            icon: const Icon(Icons.refresh_rounded, color: Colors.white),

            label: const Text('Retry', style: TextStyle(color: Colors.white)),

            style: RiderStyle.primaryButton,
          ),
        ),
      ],
    );
  }

  // ============================================================
  // EMPTY
  // ============================================================

  Widget _emptyState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),

      padding: const EdgeInsets.all(30),

      children: [
        const SizedBox(height: 100),

        Center(
          child: Container(
            height: 90,
            width: 90,

            decoration: const BoxDecoration(
              color: RiderColors.lightGreen,

              shape: BoxShape.circle,
            ),

            child: const Icon(
              Icons.history_rounded,

              size: 44,

              color: RiderColors.success,
            ),
          ),
        ),

        const SizedBox(height: 20),

        const Text(
          'No Delivery History Yet',

          textAlign: TextAlign.center,

          style: TextStyle(
            fontWeight: FontWeight.w800,

            fontSize: 18,

            color: RiderColors.textDark,
          ),
        ),

        const SizedBox(height: 7),

        const Text(
          'Completed deliveries will appear here.',

          textAlign: TextAlign.center,

          style: RiderStyle.small,
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
        const SizedBox(height: 100),

        const Icon(
          Icons.search_off_rounded,

          size: 55,

          color: RiderColors.textGrey,
        ),

        const SizedBox(height: 16),

        const Text(
          'No Delivery Found',

          textAlign: TextAlign.center,

          style: TextStyle(
            color: RiderColors.textDark,

            fontSize: 17,

            fontWeight: FontWeight.w800,
          ),
        ),

        const SizedBox(height: 7),

        Text(
          'No delivery matches "$_searchQuery".',

          textAlign: TextAlign.center,

          style: RiderStyle.small,
        ),

        const SizedBox(height: 14),

        Center(
          child: TextButton(
            onPressed: () {
              _searchController.clear();

              setState(() {
                _searchQuery = '';
              });
            },

            child: const Text('Clear Search'),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // FORMAT DATE
  // ============================================================

  String _formatDate(String value) {
    final String cleaned = value.trim();

    if (cleaned.isEmpty) {
      return '';
    }

    final DateTime? parsed = DateTime.tryParse(cleaned);

    if (parsed == null) {
      return cleaned;
    }

    final DateTime local = parsed.toLocal();

    const List<String> months = <String>[
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];

    final String month = months[local.month - 1];

    final int hour = local.hour == 0
        ? 12
        : local.hour > 12
        ? local.hour - 12
        : local.hour;

    final String minute = local.minute.toString().padLeft(2, '0');

    final String meridiem = local.hour >= 12 ? 'PM' : 'AM';

    return '$month ${local.day}, ${local.year} • '
        '$hour:$minute $meridiem';
  }

  // ============================================================
  // HELPERS
  // ============================================================

  String _displayValue(String value, {String fallback = 'Not available'}) {
    final String cleaned = value.trim();

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return fallback;
    }

    return cleaned;
  }

  String _formatStatus(String value) {
    final String cleaned = value
        .trim()
        .replaceAll('_', ' ')
        .replaceAll('-', ' ');

    if (cleaned.isEmpty) {
      return 'Delivered';
    }

    return cleaned
        .split(RegExp(r'\s+'))
        .map((String word) {
          if (word.isEmpty) {
            return word;
          }

          return '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}';
        })
        .join(' ');
  }
}
