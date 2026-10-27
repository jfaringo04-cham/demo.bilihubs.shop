import 'package:flutter/material.dart';

import '../../sevices/api_services.dart';
import '../../styles/rider_style.dart';

class AvailablePickup {
  final int id;
  final String orderNumber;
  final String trackingNumber;
  final String sellerName;
  final String customerName;
  final String deliveryAddress;
  final String status;
  final String source;

  const AvailablePickup({
    required this.id,
    required this.orderNumber,
    required this.trackingNumber,
    required this.sellerName,
    required this.customerName,
    required this.deliveryAddress,
    required this.status,
    required this.source,
  });

  factory AvailablePickup.fromJson(Map<String, dynamic> json) {
    return AvailablePickup(
      id:
          int.tryParse(
            json['id']?.toString() ?? json['shipment_id']?.toString() ?? '',
          ) ??
          0,

      orderNumber:
          json['order_number']?.toString() ??
          json['order_no']?.toString() ??
          '',

      trackingNumber:
          json['tracking_number']?.toString() ??
          json['tracking_code']?.toString() ??
          json['waybill_number']?.toString() ??
          '',

      sellerName:
          json['seller_name']?.toString() ??
          json['store_name']?.toString() ??
          '',

      customerName:
          json['customer_name']?.toString() ??
          json['recipient_name']?.toString() ??
          json['buyer_name']?.toString() ??
          '',

      deliveryAddress:
          json['delivery_address']?.toString() ??
          json['address']?.toString() ??
          '',

      status:
          json['status']?.toString() ??
          json['shipment_status']?.toString() ??
          'pending',

      source:
          json['source']?.toString() ?? json['pickup_source']?.toString() ?? '',
    );
  }
}

class AvailablePickups extends StatefulWidget {
  final List<AvailablePickup> pickups;

  const AvailablePickups({super.key, this.pickups = const <AvailablePickup>[]});

  @override
  State<AvailablePickups> createState() => _AvailablePickupsState();
}

class _AvailablePickupsState extends State<AvailablePickups> {
  bool _isLoading = true;
  bool _isRefreshing = false;

  String? _errorMessage;

  String _selectedFilter = 'all';

  List<AvailablePickup> _pickups = <AvailablePickup>[];

  final Map<int, TextEditingController> _noteControllers =
      <int, TextEditingController>{};

  @override
  void initState() {
    super.initState();

    if (widget.pickups.isNotEmpty) {
      _pickups = List<AvailablePickup>.from(widget.pickups);

      _isLoading = false;
    } else {
      _loadPickups();
    }
  }

  @override
  void dispose() {
    for (final TextEditingController controller in _noteControllers.values) {
      controller.dispose();
    }

    super.dispose();
  }

  // ============================================================
  // LOAD PICKUPS
  // ============================================================

  Future<void> _loadPickups({bool refresh = false}) async {
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
          _isLoading = false;
          _isRefreshing = false;

          _errorMessage =
              result['message']?.toString().trim().isNotEmpty == true
              ? result['message'].toString().trim()
              : 'Unable to load available pickups.';
        });

        return;
      }

      final List<Map<String, dynamic>> rawItems = _extractRawItems(result);

      final List<AvailablePickup> parsed = <AvailablePickup>[];

      for (final Map<String, dynamic> raw in rawItems) {
        try {
          final Map<String, dynamic> normalized = _normalizePickup(raw);

          final String status =
              normalized['status']?.toString().trim().toLowerCase().replaceAll(
                ' ',
                '_',
              ) ??
              '';

          final bool isPickupCandidate =
              status == 'pending' ||
              status == 'assigned' ||
              status == 'staged' ||
              status == 'ready_for_pickup' ||
              status == 'at_sorting_center';

          if (!isPickupCandidate) {
            continue;
          }

          parsed.add(AvailablePickup.fromJson(normalized));
        } catch (error) {
          debugPrint('Failed to parse available pickup: $error');
        }
      }

      setState(() {
        _pickups = parsed;

        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = null;
      });
    } catch (error) {
      debugPrint('Available pickups error: $error');

      if (!mounted) return;

      setState(() {
        _isLoading = false;
        _isRefreshing = false;

        _errorMessage = 'Unable to connect to the server. Please try again.';
      });
    }
  }

  // ============================================================
  // EXTRACT RAW ITEMS
  // ============================================================

  List<Map<String, dynamic>> _extractRawItems(Map<String, dynamic> result) {
    final List<Map<String, dynamic>> direct = _toMapList(result['data']);

    if (direct.isNotEmpty) {
      return direct;
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
  // NORMALIZE PICKUP
  // ============================================================

  Map<String, dynamic> _normalizePickup(Map<String, dynamic> raw) {
    final Map<String, dynamic> map = Map<String, dynamic>.from(raw);

    final dynamic shipment = raw['shipment'];

    if (shipment is Map) {
      map.addAll(Map<String, dynamic>.from(shipment));
    }

    map['id'] ??= map['shipment_id'] ?? map['order_id'] ?? 0;

    map['order_number'] ??= map['order_no'] ?? '';

    map['tracking_number'] ??=
        map['tracking_code'] ?? map['waybill_number'] ?? '';

    map['seller_name'] ??= map['store_name'] ?? '';

    map['customer_name'] ??= map['recipient_name'] ?? map['buyer_name'] ?? '';

    map['delivery_address'] ??= map['address'] ?? map['shipping_address'] ?? '';

    map['status'] ??=
        map['shipment_status'] ?? map['delivery_status'] ?? 'pending';

    map['source'] ??= map['pickup_source'] ?? '';

    return map;
  }

  // ============================================================
  // FILTERED PICKUPS
  // ============================================================

  List<AvailablePickup> get _filteredPickups {
    if (_selectedFilter == 'all') {
      return _pickups;
    }

    return _pickups.where((AvailablePickup pickup) {
      final String source = pickup.source.trim().toLowerCase().replaceAll(
        ' ',
        '_',
      );

      if (_selectedFilter == 'sorting_center') {
        return source == 'sorting_center' || source == 'from_sorting_center';
      }

      if (_selectedFilter == 'seller') {
        return source == 'seller' ||
            source == 'from_seller' ||
            source == 'from_sellers';
      }

      return true;
    }).toList();
  }

  TextEditingController _noteController(int id) {
    return _noteControllers.putIfAbsent(id, () => TextEditingController());
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.of(context).size.width;

    final double horizontalPadding = width < 380 ? 14 : 18;

    final List<AvailablePickup> pickups = _filteredPickups;

    return Scaffold(
      backgroundColor: RiderColors.background,

      appBar: AppBar(
        backgroundColor: RiderColors.white,

        elevation: 0,

        surfaceTintColor: Colors.transparent,

        iconTheme: const IconThemeData(color: RiderColors.textDark),

        title: const Text('Available Pickups', style: RiderStyle.title),

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
              tooltip: 'Refresh pickups',

              onPressed: _isLoading
                  ? null
                  : () {
                      _loadPickups(refresh: true);
                    },

              icon: const Icon(
                Icons.refresh_rounded,

                color: RiderColors.primary,
              ),
            ),
        ],
      ),

      body: SafeArea(
        top: false,

        child: Column(
          children: [
            // ==================================================
            // FILTERS
            // ==================================================

            Container(
              width: double.infinity,

              color: RiderColors.white,

              padding: EdgeInsets.fromLTRB(
                horizontalPadding,
                12,
                horizontalPadding,
                14,
              ),

              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,

                child: Row(
                  children: [
                    _filterChip(
                      label: 'All',
                      value: 'all',
                      icon: Icons.all_inbox_rounded,
                    ),

                    const SizedBox(width: 8),

                    _filterChip(
                      label: 'From Sorting Center',

                      value: 'sorting_center',

                      icon: Icons.account_tree_outlined,
                    ),

                    const SizedBox(width: 8),

                    _filterChip(
                      label: 'From Sellers',

                      value: 'seller',

                      icon: Icons.storefront_outlined,
                    ),
                  ],
                ),
              ),
            ),

            // ==================================================
            // BODY
            // ==================================================
            Expanded(
              child: RefreshIndicator(
                onRefresh: () {
                  return _loadPickups(refresh: true);
                },

                child: _buildBody(pickups, horizontalPadding),
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

  Widget _buildBody(List<AvailablePickup> pickups, double horizontalPadding) {
    if (_isLoading) {
      return _loadingState();
    }

    if (_errorMessage != null) {
      return _errorState();
    }

    if (_pickups.isEmpty) {
      return _emptyState();
    }

    if (pickups.isEmpty) {
      return _filterEmptyState();
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

      itemCount: pickups.length,

      itemBuilder: (BuildContext context, int index) {
        return _pickupCard(pickups[index]);
      },
    );
  }

  // ============================================================
  // FILTER CHIP
  // ============================================================

  Widget _filterChip({
    required String label,
    required String value,
    required IconData icon,
  }) {
    final bool selected = _selectedFilter == value;

    return Material(
      color: selected ? RiderColors.primary : RiderColors.background,

      borderRadius: BorderRadius.circular(20),

      child: InkWell(
        onTap: () {
          setState(() {
            _selectedFilter = value;
          });
        },

        borderRadius: BorderRadius.circular(20),

        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),

          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),

            border: Border.all(
              color: selected ? RiderColors.primary : RiderColors.border,
            ),
          ),

          child: Row(
            children: [
              Icon(
                icon,

                size: 16,

                color: selected ? Colors.white : RiderColors.primary,
              ),

              const SizedBox(width: 6),

              Text(
                label,

                style: TextStyle(
                  color: selected ? Colors.white : RiderColors.textDark,

                  fontSize: 11,

                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // PICKUP CARD
  // ============================================================

  Widget _pickupCard(AvailablePickup pickup) {
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
                  color: RiderColors.lightPurple,

                  borderRadius: BorderRadius.circular(13),
                ),

                child: const Icon(
                  Icons.inventory_2_outlined,

                  color: RiderColors.primary,

                  size: 22,
                ),
              ),

              const SizedBox(width: 12),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Text(
                      _displayValue(
                        pickup.trackingNumber,

                        fallback: 'Tracking unavailable',
                      ),

                      maxLines: 1,

                      overflow: TextOverflow.ellipsis,

                      style: RiderStyle.cardTitle,
                    ),

                    const SizedBox(height: 4),

                    Text(
                      'Order # ${_displayValue(pickup.orderNumber)}',

                      style: RiderStyle.small,
                    ),
                  ],
                ),
              ),

              _statusBadge(pickup.status),
            ],
          ),

          const SizedBox(height: 18),

          _infoRow(
            icon: Icons.storefront_outlined,

            title: 'Seller',

            value: _displayValue(pickup.sellerName),
          ),

          _infoRow(
            icon: Icons.person_outline_rounded,

            title: 'Customer',

            value: _displayValue(pickup.customerName),
          ),

          _infoRow(
            icon: Icons.location_on_outlined,

            title: 'Address',

            value: _displayValue(pickup.deliveryAddress),
          ),

          const SizedBox(height: 14),

          TextField(
            controller: _noteController(pickup.id),

            minLines: 2,
            maxLines: 3,

            decoration: InputDecoration(
              hintText: 'Optional notes',

              hintStyle: RiderStyle.small,

              filled: true,

              fillColor: RiderColors.background,

              contentPadding: const EdgeInsets.all(13),

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

                borderSide: const BorderSide(color: RiderColors.primary),
              ),
            ),
          ),

          const SizedBox(height: 12),

          SizedBox(
            width: double.infinity,

            height: 48,

            child: ElevatedButton.icon(
              onPressed: () {
                _showPickupActionInfo(pickup);
              },

              icon: const Icon(Icons.local_shipping_outlined, size: 19),

              label: const Text('View Pickup Status'),

              style: RiderStyle.primaryButton,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PICKUP ACTION
  // ============================================================

  void _showPickupActionInfo(AvailablePickup pickup) {
    final String status = _formatStatus(pickup.status);

    final String note = _noteController(pickup.id).text.trim();

    showModalBottomSheet<void>(
      context: context,

      backgroundColor: Colors.transparent,

      builder: (BuildContext sheetContext) {
        return SafeArea(
          child: Container(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),

            decoration: const BoxDecoration(
              color: RiderColors.white,

              borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
            ),

            child: Column(
              mainAxisSize: MainAxisSize.min,

              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Center(
                  child: Container(
                    width: 42,
                    height: 4,

                    decoration: BoxDecoration(
                      color: RiderColors.border,

                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),

                const SizedBox(height: 18),

                const Text(
                  'Pickup Information',

                  style: RiderStyle.sectionTitle,
                ),

                const SizedBox(height: 14),

                _sheetInfo(
                  'Tracking Number',
                  _displayValue(pickup.trackingNumber),
                ),

                _sheetInfo('Status', status),

                _sheetInfo('Source', _formatStatus(pickup.source)),

                if (note.isNotEmpty) _sheetInfo('Notes', note),

                const SizedBox(height: 12),

                const Text(
                  'Pickup completion will be connected once the Rider pickup action endpoint is confirmed in the Laravel backend.',

                  style: RiderStyle.small,
                ),

                const SizedBox(height: 16),

                SizedBox(
                  width: double.infinity,

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
        );
      },
    );
  }

  Widget _sheetInfo(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Text(label, style: RiderStyle.small),

          const SizedBox(height: 3),

          Text(_displayValue(value), style: RiderStyle.cardTitle),
        ],
      ),
    );
  }

  // ============================================================
  // INFORMATION ROW
  // ============================================================

  Widget _infoRow({
    required IconData icon,
    required String title,
    required String value,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 11),

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Icon(icon, color: RiderColors.textGrey, size: 18),

          const SizedBox(width: 9),

          SizedBox(width: 72, child: Text(title, style: RiderStyle.small)),

          Expanded(child: Text(value, style: RiderStyle.cardTitle)),
        ],
      ),
    );
  }

  // ============================================================
  // STATUS
  // ============================================================

  Widget _statusBadge(String status) {
    final String formatted = _formatStatus(status);

    final Color color = _statusColor(status);

    return Container(
      constraints: const BoxConstraints(maxWidth: 105),

      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),

      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),

        borderRadius: BorderRadius.circular(20),
      ),

      child: Text(
        formatted,

        maxLines: 1,

        overflow: TextOverflow.ellipsis,

        style: TextStyle(
          color: color,

          fontSize: 9,

          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }

  Color _statusColor(String status) {
    final String value = status.trim().toLowerCase().replaceAll(' ', '_');

    if (value.contains('sorting')) {
      return RiderColors.primary;
    }

    if (value == 'delivered' || value == 'completed') {
      return RiderColors.success;
    }

    if (value == 'failed' ||
        value == 'cancelled' ||
        value == 'delivery_failed') {
      return RiderColors.danger;
    }

    if (value == 'pending' ||
        value == 'assigned' ||
        value == 'staged' ||
        value == 'ready_for_pickup' ||
        value == 'ready') {
      return RiderColors.warning;
    }

    if (value == 'picked_up' ||
        value == 'in_transit' ||
        value == 'out_for_delivery') {
      return RiderColors.primary;
    }

    return RiderColors.textGrey;
  }

  String _formatStatus(String value) {
    final String cleaned = value
        .trim()
        .replaceAll('_', ' ')
        .replaceAll('-', ' ');

    if (cleaned.isEmpty) {
      return 'Unknown';
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

              SizedBox(height: 14),

              Text('Loading available pickups...', style: RiderStyle.small),
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

      padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 60),

      children: [
        const Icon(
          Icons.cloud_off_outlined,

          size: 58,

          color: RiderColors.primary,
        ),

        const SizedBox(height: 18),

        const Text(
          'Unable to Load Pickups',

          textAlign: TextAlign.center,

          style: TextStyle(
            color: RiderColors.textDark,

            fontSize: 18,

            fontWeight: FontWeight.w800,
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
              _loadPickups(refresh: true);
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
  // EMPTY STATE
  // ============================================================

  Widget _emptyState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),

      padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 60),

      children: [
        Center(
          child: Container(
            height: 90,
            width: 90,

            decoration: const BoxDecoration(
              color: RiderColors.lightPurple,

              shape: BoxShape.circle,
            ),

            child: const Icon(
              Icons.inventory_2_outlined,

              color: RiderColors.primary,

              size: 42,
            ),
          ),
        ),

        const SizedBox(height: 20),

        const Text(
          'No Available Pickups',

          textAlign: TextAlign.center,

          style: TextStyle(
            color: RiderColors.textDark,

            fontSize: 19,

            fontWeight: FontWeight.w800,
          ),
        ),

        const SizedBox(height: 8),

        const Text(
          'There are currently no pickup-ready shipments assigned to this rider.',

          textAlign: TextAlign.center,

          style: RiderStyle.small,
        ),
      ],
    );
  }

  Widget _filterEmptyState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),

      padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 60),

      children: [
        const Icon(
          Icons.filter_alt_off_outlined,

          size: 55,

          color: RiderColors.textGrey,
        ),

        const SizedBox(height: 16),

        const Text(
          'No Pickups Found',

          textAlign: TextAlign.center,

          style: TextStyle(
            color: RiderColors.textDark,

            fontSize: 18,

            fontWeight: FontWeight.w800,
          ),
        ),

        const SizedBox(height: 8),

        const Text(
          'No available pickups match the selected source filter.',

          textAlign: TextAlign.center,

          style: RiderStyle.small,
        ),

        const SizedBox(height: 14),

        Center(
          child: TextButton(
            onPressed: () {
              setState(() {
                _selectedFilter = 'all';
              });
            },

            child: const Text('Show All Pickups'),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // DISPLAY VALUE
  // ============================================================

  String _displayValue(String value, {String fallback = 'Not available'}) {
    final String cleaned = value.trim();

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return fallback;
    }

    return cleaned;
  }
}
