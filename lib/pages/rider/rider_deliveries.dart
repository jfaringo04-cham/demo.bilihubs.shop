import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../sevices/api_services.dart';
import '../../styles/rider_style.dart';
import 'delivery_confirmation.dart';

class RiderDeliveries extends StatefulWidget {
  const RiderDeliveries({super.key});

  @override
  State<RiderDeliveries> createState() => _RiderDeliveriesState();
}

class _RiderDeliveriesState extends State<RiderDeliveries> {
  bool _isLoading = true;
  bool _isRefreshing = false;

  String? _errorMessage;

  List<ParcelModel> _deliveries = <ParcelModel>[];

  final Map<int, String> _assignmentTypes = <int, String>{};
  final Map<int, DateTime> _activityDates = <int, DateTime>{};
  final Map<int, bool> _receivedBySortingCenter = <int, bool>{};

  final Set<int> _processingPickupIds = <int>{};

  String _selectedStatus = 'all';

  final List<Map<String, String>> _statusOptions = const <Map<String, String>>[
    {'value': 'all', 'label': 'All Statuses'},
    {'value': 'pending', 'label': 'Pending'},
    {'value': 'assigned', 'label': 'Assigned'},
    {'value': 'in_transit', 'label': 'In Transit'},
    {'value': 'at_sorting_center', 'label': 'At Sorting Center'},
    {'value': 'staged', 'label': 'Staged'},
    {'value': 'out_for_delivery', 'label': 'Out for Delivery'},
    {'value': 'delivered', 'label': 'Delivered'},
    {'value': 'delivery_failed', 'label': 'Delivery Failed'},
  ];

  @override
  void initState() {
    super.initState();
    _loadDeliveries();
  }

  // ============================================================
  // LOAD DELIVERIES
  // ============================================================

  Future<void> _loadDeliveries({bool refresh = false}) async {
    if (!mounted) {
      return;
    }

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
              : 'Unable to load your assigned shipments.';
        });

        return;
      }

      final List<Map<String, dynamic>> rawDeliveries = _extractDeliveryMaps(
        result,
      );

      final List<ParcelModel> parsedDeliveries = <ParcelModel>[];

      final Map<int, String> assignmentTypes = <int, String>{};
      final Map<int, DateTime> activityDates = <int, DateTime>{};
      final Map<int, bool> receivedBySortingCenter = <int, bool>{};

      for (final Map<String, dynamic> raw in rawDeliveries) {
        try {
          final Map<String, dynamic> normalized = _normalizeShipment(raw);

          final ParcelModel parcel = ParcelModel.fromJson(normalized);

          parsedDeliveries.add(parcel);

          final String assignmentType =
              raw['assignment_type']?.toString().trim().toLowerCase() ?? '';

          if (parcel.id > 0 && assignmentType.isNotEmpty) {
            assignmentTypes[parcel.id] = assignmentType;
          }

          if (parcel.id > 0) {
            final DateTime? activityDate = _shipmentActivityDate(raw, parcel);

            if (activityDate != null) {
              activityDates[parcel.id] = activityDate;
            }

            receivedBySortingCenter[parcel.id] = _toBool(
              raw['received_by_sorting_center'],
            );
          }
        } catch (error) {
          debugPrint('Failed to parse rider shipment: $error');
        }
      }

      // Newest assignment / latest update always appears first.
      parsedDeliveries.sort((ParcelModel a, ParcelModel b) {
        final DateTime? aDate = activityDates[a.id];
        final DateTime? bDate = activityDates[b.id];

        if (aDate != null && bDate != null) {
          final int result = bDate.compareTo(aDate);

          if (result != 0) {
            return result;
          }
        } else if (aDate != null) {
          return -1;
        } else if (bDate != null) {
          return 1;
        }

        return b.id.compareTo(a.id);
      });

      setState(() {
        _deliveries = parsedDeliveries;

        _assignmentTypes
          ..clear()
          ..addAll(assignmentTypes);

        _activityDates
          ..clear()
          ..addAll(activityDates);

        _receivedBySortingCenter
          ..clear()
          ..addAll(receivedBySortingCenter);

        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = null;
      });
    } catch (error) {
      debugPrint('Rider deliveries error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _isLoading = false;
        _isRefreshing = false;

        _errorMessage =
            'Unable to connect to the server. '
            'Please check your internet connection and try again.';
      });
    }
  }

  // ============================================================
  // EXTRACT API DATA
  // ============================================================

  List<Map<String, dynamic>> _extractDeliveryMaps(Map<String, dynamic> result) {
    final List<Map<String, dynamic>> direct = _toMapList(result['data']);

    if (direct.isNotEmpty) {
      return direct;
    }

    final List<Map<String, dynamic>> deliveries = _toMapList(
      result['deliveries'],
    );

    if (deliveries.isNotEmpty) {
      return deliveries;
    }

    final List<Map<String, dynamic>> orders = _toMapList(result['orders']);

    if (orders.isNotEmpty) {
      return orders;
    }

    final List<Map<String, dynamic>> shipments = _toMapList(
      result['shipments'],
    );

    if (shipments.isNotEmpty) {
      return shipments;
    }

    final dynamic rawData = result['data'];

    if (rawData is Map) {
      final Map<String, dynamic> data = Map<String, dynamic>.from(rawData);

      for (final String key in <String>[
        'data',
        'deliveries',
        'orders',
        'shipments',
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

    map['status'] ??=
        map['shipment_status'] ?? map['delivery_status'] ?? 'pending';

    map['order_date'] ??= map['created_at'] ?? map['assigned_at'] ?? '';

    map['delivery_earnings'] ??= map['earnings'] ?? map['delivery_fee'] ?? 0;

    map['latitude'] ??= map['rider_latitude'];
    map['longitude'] ??= map['rider_longitude'];

    return map;
  }

  // ============================================================
  // ACTIVITY DATE
  // ============================================================

  DateTime? _shipmentActivityDate(
    Map<String, dynamic> raw,
    ParcelModel parcel,
  ) {
    final List<dynamic> candidates = <dynamic>[
      raw['updated_at'],
      raw['assigned_at'],
      raw['created_at'],
      raw['order_date'],
      parcel.orderDate,
    ];

    for (final dynamic value in candidates) {
      final String text = value?.toString().trim() ?? '';

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
  // FILTER
  // ============================================================

  List<ParcelModel> get _filteredDeliveries {
    if (_selectedStatus == 'all') {
      return _deliveries;
    }

    return _deliveries.where((ParcelModel parcel) {
      final String status = _normalizeStatus(parcel.status);

      if (_selectedStatus == 'pending') {
        return status == 'pending' || status == 'ready_for_pickup';
      }

      if (_selectedStatus == 'delivery_failed') {
        return status == 'delivery_failed' ||
            status == 'failed' ||
            status == 'cancelled' ||
            status == 'failed_return_at_sorting_center';
      }

      return status == _selectedStatus;
    }).toList();
  }

  // ============================================================
  // ASSIGNMENT
  // ============================================================

  String _assignmentType(ParcelModel parcel) {
    return _assignmentTypes[parcel.id]?.trim().toLowerCase() ?? '';
  }

  bool _isSellerPickupShipment(ParcelModel parcel) {
    return _assignmentType(parcel) == 'seller_pickup';
  }

  bool _isFinalMileShipment(ParcelModel parcel) {
    final String type = _assignmentType(parcel);

    return type == 'final_mile' || type == 'pickup_and_delivery';
  }

  bool _isFinalized(ParcelModel parcel) {
    final String status = _normalizeStatus(parcel.status);

    return status == 'delivered' ||
        status == 'completed' ||
        status == 'delivery_failed' ||
        status == 'failed' ||
        status == 'cancelled' ||
        status == 'failed_return_at_sorting_center';
  }

  bool _canManageDelivery(ParcelModel parcel) {
    if (parcel.id <= 0) {
      return false;
    }

    if (_isFinalized(parcel)) {
      return false;
    }

    if (_isSellerPickupShipment(parcel)) {
      return false;
    }

    return _isFinalMileShipment(parcel);
  }

  // ============================================================
  // SELLER PICKUP
  // ============================================================

  bool _isSellerPickupBusy(ParcelModel parcel) {
    return _processingPickupIds.contains(parcel.id);
  }

  bool _canUpdateSellerPickup(ParcelModel parcel) {
    if (!_isSellerPickupShipment(parcel) || parcel.id <= 0) {
      return false;
    }

    final String status = _normalizeStatus(parcel.status);

    return status == 'assigned' ||
        status == 'ready_for_pickup' ||
        status == 'picked_up' ||
        status == 'in_transit';
  }

  String _sellerPickupActionLabel(ParcelModel parcel) {
    final String status = _normalizeStatus(parcel.status);

    if (status == 'assigned' || status == 'ready_for_pickup') {
      return 'MARK PICKED UP';
    }

    if (status == 'picked_up' || status == 'in_transit') {
      return 'ARRIVED AT SORTING CENTER';
    }

    return '';
  }

  IconData _sellerPickupActionIcon(ParcelModel parcel) {
    final String status = _normalizeStatus(parcel.status);

    if (status == 'assigned' || status == 'ready_for_pickup') {
      return Icons.inventory_2_outlined;
    }

    return Icons.warehouse_outlined;
  }

  String _sellerPickupProgressText(ParcelModel parcel) {
    final String status = _normalizeStatus(parcel.status);

    if (status == 'assigned' || status == 'ready_for_pickup') {
      return 'Seller pickup assigned. '
          'Collect this parcel from the seller.';
    }

    if (status == 'picked_up' || status == 'in_transit') {
      return 'Parcel collected from the seller. '
          'Bring it to the assigned sorting center.';
    }

    if (status == 'at_sorting_center') {
      final bool received = _receivedBySortingCenter[parcel.id] == true;

      if (received) {
        return 'Sorting center has confirmed '
            'receipt of this parcel.';
      }

      return 'Parcel is at the sorting center. '
          'Waiting for Logistics confirmation.';
    }

    if (status == 'staged') {
      return 'Parcel has been received and staged '
          'at the sorting center.';
    }

    return '';
  }

  Future<void> _handleSellerPickup(ParcelModel parcel) async {
    if (_isSellerPickupBusy(parcel) || !_canUpdateSellerPickup(parcel)) {
      return;
    }

    final String status = _normalizeStatus(parcel.status);

    final bool markPickedUp =
        status == 'assigned' || status == 'ready_for_pickup';

    final bool confirmed = await _showPickupConfirmation(
      title: markPickedUp
          ? 'Confirm Seller Pickup'
          : 'Arrived at Sorting Center',
      message: markPickedUp
          ? 'Confirm that you picked up this parcel '
                'from the seller?'
          : 'Confirm that this parcel has arrived '
                'at the sorting center?',
    );

    if (!confirmed || !mounted) {
      return;
    }

    setState(() {
      _processingPickupIds.add(parcel.id);
    });

    try {
      final Map<String, dynamic> result;

      if (markPickedUp) {
        result = await ApiService.markRiderSellerPickupInTransit(
          shipmentId: parcel.id,
        );
      } else {
        result = await ApiService.deliverRiderPickupToSortingCenter(
          shipmentId: parcel.id,
        );
      }

      if (!mounted) {
        return;
      }

      final bool success = result['success'] == true;

      final String message = result['message']?.toString().trim() ?? '';

      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            behavior: SnackBarBehavior.floating,
            content: Text(
              message.isNotEmpty
                  ? message
                  : success
                  ? 'Seller pickup updated successfully.'
                  : 'Unable to update seller pickup.',
            ),
          ),
        );

      if (success) {
        await _loadDeliveries(refresh: true);
      }
    } catch (error) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            behavior: SnackBarBehavior.floating,
            content: Text(
              'Unable to update seller pickup: '
              '$error',
            ),
          ),
        );
    } finally {
      if (mounted) {
        setState(() {
          _processingPickupIds.remove(parcel.id);
        });
      }
    }
  }

  Future<bool> _showPickupConfirmation({
    required String title,
    required String message,
  }) async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          title: Text(title),
          content: Text(message),
          actions: <Widget>[
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext, false);
              },
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () {
                Navigator.pop(dialogContext, true);
              },
              style: RiderStyle.primaryButton,
              child: const Text('CONFIRM', style: RiderStyle.buttonText),
            ),
          ],
        );
      },
    );

    return confirmed == true;
  }

  // ============================================================
  // FINAL-MILE DELIVERY
  // ============================================================

  Future<void> _openDeliveryManagement(ParcelModel parcel) async {
    final bool? changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute<bool>(
        builder: (BuildContext context) {
          return DeliveryConfirmation(parcel: parcel);
        },
      ),
    );

    if (!mounted) {
      return;
    }

    if (changed == true) {
      await _loadDeliveries(refresh: true);
    }
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double screenWidth = MediaQuery.of(context).size.width;

    final double horizontalPadding = screenWidth < 380 ? 14 : 18;

    final List<ParcelModel> deliveries = _filteredDeliveries;

    return Scaffold(
      backgroundColor: RiderColors.background,
      appBar: AppBar(
        backgroundColor: RiderColors.white,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        automaticallyImplyLeading: false,
        titleSpacing: 18,
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('My Deliveries', style: RiderStyle.title),
            SizedBox(height: 2),
            Text('Manage pickups and deliveries', style: RiderStyle.small),
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
              tooltip: 'Refresh shipments',
              onPressed: _isLoading
                  ? null
                  : () {
                      _loadDeliveries(refresh: true);
                    },
              icon: const Icon(
                Icons.refresh_rounded,
                color: RiderColors.primary,
              ),
            ),
          const SizedBox(width: 6),
        ],
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            _filterHeader(horizontalPadding, deliveries),
            Expanded(
              child: RefreshIndicator(
                onRefresh: () {
                  return _loadDeliveries(refresh: true);
                },
                child: _buildBody(horizontalPadding, deliveries),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // FILTER HEADER
  // ============================================================

  Widget _filterHeader(double horizontalPadding, List<ParcelModel> deliveries) {
    return Container(
      width: double.infinity,
      color: RiderColors.white,
      padding: EdgeInsets.fromLTRB(
        horizontalPadding,
        14,
        horizontalPadding,
        16,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Assigned Shipments',
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
                    color: RiderColors.lightPurple,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    '${deliveries.length} '
                    '${deliveries.length == 1 ? 'shipment' : 'shipments'}',
                    style: const TextStyle(
                      color: RiderColors.primary,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _selectedStatus,
            isExpanded: true,
            decoration: InputDecoration(
              filled: true,
              fillColor: RiderColors.background,
              prefixIcon: const Icon(
                Icons.filter_list_rounded,
                color: RiderColors.primary,
                size: 20,
              ),
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 12,
                vertical: 12,
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
            items: _statusOptions.map((Map<String, String> option) {
              return DropdownMenuItem<String>(
                value: option['value'],
                child: Text(option['label'] ?? ''),
              );
            }).toList(),
            onChanged: (String? value) {
              if (value == null) {
                return;
              }

              setState(() {
                _selectedStatus = value;
              });
            },
          ),
        ],
      ),
    );
  }

  // ============================================================
  // BODY
  // ============================================================

  Widget _buildBody(double horizontalPadding, List<ParcelModel> deliveries) {
    if (_isLoading) {
      return _loadingState();
    }

    if (_errorMessage != null) {
      return _errorState();
    }

    if (_deliveries.isEmpty) {
      return _emptyState();
    }

    if (deliveries.isEmpty) {
      return _noFilteredDeliveries();
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
      itemCount: deliveries.length,
      itemBuilder: (BuildContext context, int index) {
        final ParcelModel parcel = deliveries[index];

        return _deliveryCard(parcel);
      },
    );
  }

  // ============================================================
  // DELIVERY CARD
  // ============================================================

  Widget _deliveryCard(ParcelModel parcel) {
    final String status = _formatStatus(parcel.status);

    final Color statusColor = _statusColor(parcel.status);

    final String assignmentLabel = _formatAssignmentType(
      _assignmentType(parcel),
    );

    final bool sellerPickup = _isSellerPickupShipment(parcel);

    final bool canManageDelivery = _canManageDelivery(parcel);

    final bool canUpdatePickup = _canUpdateSellerPickup(parcel);

    final bool pickupBusy = _isSellerPickupBusy(parcel);

    final String pickupProgress = _sellerPickupProgressText(parcel);

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
                  color: RiderColors.lightPurple,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  sellerPickup
                      ? Icons.inventory_2_outlined
                      : Icons.local_shipping_outlined,
                  color: RiderColors.primary,
                  size: 22,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Tracking Number', style: RiderStyle.small),
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
                constraints: const BoxConstraints(maxWidth: 125),
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  status,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: statusColor,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),

          if (assignmentLabel.isNotEmpty) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: RiderColors.background,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: RiderColors.border),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(
                    Icons.assignment_ind_outlined,
                    size: 15,
                    color: RiderColors.primary,
                  ),
                  const SizedBox(width: 6),
                  Text(
                    assignmentLabel,
                    style: const TextStyle(
                      color: RiderColors.textDark,
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ],

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
            label: 'Delivery Address',
            value: _displayValue(parcel.deliveryAddress),
          ),

          if (formattedDate.isNotEmpty)
            _infoRow(
              icon: Icons.calendar_today_outlined,
              label: 'Order Date',
              value: formattedDate,
            ),

          if (sellerPickup && pickupProgress.isNotEmpty) ...[
            const SizedBox(height: 4),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: RiderColors.background,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: RiderColors.border),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.route_outlined,
                    size: 18,
                    color: RiderColors.primary,
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: Text(pickupProgress, style: RiderStyle.small),
                  ),
                ],
              ),
            ),
          ],

          const SizedBox(height: 12),

          Divider(color: RiderColors.border.withValues(alpha: 0.8)),

          const SizedBox(height: 8),

          if (canUpdatePickup) ...[
            SizedBox(
              width: double.infinity,
              height: 47,
              child: ElevatedButton.icon(
                onPressed: pickupBusy
                    ? null
                    : () {
                        _handleSellerPickup(parcel);
                      },
                icon: pickupBusy
                    ? const SizedBox(
                        height: 17,
                        width: 17,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Icon(
                        _sellerPickupActionIcon(parcel),
                        size: 19,
                        color: Colors.white,
                      ),
                label: Text(
                  pickupBusy ? 'UPDATING...' : _sellerPickupActionLabel(parcel),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                style: RiderStyle.primaryButton,
              ),
            ),
            const SizedBox(height: 10),
          ],

          if (canManageDelivery) ...[
            SizedBox(
              width: double.infinity,
              height: 47,
              child: ElevatedButton.icon(
                onPressed: () {
                  _openDeliveryManagement(parcel);
                },
                icon: const Icon(
                  Icons.delivery_dining_rounded,
                  size: 19,
                  color: Colors.white,
                ),
                label: const Text(
                  'MANAGE DELIVERY',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                style: RiderStyle.primaryButton,
              ),
            ),
            const SizedBox(height: 10),
          ],

          SizedBox(
            width: double.infinity,
            height: 47,
            child: OutlinedButton.icon(
              onPressed: () {
                _showShipmentDetails(parcel);
              },
              icon: const Icon(Icons.inventory_2_outlined, size: 18),
              label: const Text('VIEW SHIPMENT'),
              style: OutlinedButton.styleFrom(
                foregroundColor: RiderColors.primary,
                backgroundColor: RiderColors.white,
                side: const BorderSide(color: RiderColors.primary),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
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
                  maxLines: label == 'Delivery Address' ? 3 : 2,
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
  // SHIPMENT DETAILS
  // ============================================================

  void _showShipmentDetails(ParcelModel parcel) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (BuildContext sheetContext) {
        final Color statusColor = _statusColor(parcel.status);

        final String assignment = _formatAssignmentType(
          _assignmentType(parcel),
        );

        final String pickupProgress = _sellerPickupProgressText(parcel);

        final String formattedDate = _formatDate(parcel.orderDate);

        return SafeArea(
          child: Container(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(sheetContext).size.height * 0.88,
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
                          color: RiderColors.lightPurple,
                          borderRadius: BorderRadius.circular(13),
                        ),
                        child: Icon(
                          _isSellerPickupShipment(parcel)
                              ? Icons.inventory_2_outlined
                              : Icons.local_shipping_outlined,
                          color: RiderColors.primary,
                        ),
                      ),

                      const SizedBox(width: 12),

                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Shipment Details',
                              style: RiderStyle.sectionTitle,
                            ),
                            const SizedBox(height: 3),
                            Text(
                              _displayValue(
                                parcel.trackingNumber,
                                fallback: 'Tracking unavailable',
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: RiderStyle.small,
                            ),
                          ],
                        ),
                      ),

                      const SizedBox(width: 8),

                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 9,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: statusColor.withValues(alpha: 0.10),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          _formatStatus(parcel.status),
                          style: TextStyle(
                            color: statusColor,
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 22),

                  _detailTile(
                    'Tracking Number',
                    _displayValue(parcel.trackingNumber),
                  ),

                  if (assignment.isNotEmpty)
                    _detailTile('Assignment', assignment),

                  _detailTile('Customer', _displayValue(parcel.customerName)),

                  _detailTile('Contact', _displayValue(parcel.customerPhone)),

                  _detailTile(
                    'Delivery Address',
                    _displayValue(parcel.deliveryAddress),
                  ),

                  if (parcel.riderName.trim().isNotEmpty)
                    _detailTile('Assigned Rider', parcel.riderName),

                  if (formattedDate.isNotEmpty)
                    _detailTile('Order Date', formattedDate),

                  _detailTile('Status', _formatStatus(parcel.status)),

                  if (_isSellerPickupShipment(parcel) &&
                      pickupProgress.isNotEmpty)
                    _detailTile('Pickup Progress', pickupProgress),

                  const SizedBox(height: 10),

                  if (_canUpdateSellerPickup(parcel)) ...[
                    SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: ElevatedButton.icon(
                        onPressed: _isSellerPickupBusy(parcel)
                            ? null
                            : () {
                                Navigator.pop(sheetContext);

                                _handleSellerPickup(parcel);
                              },
                        icon: Icon(
                          _sellerPickupActionIcon(parcel),
                          color: Colors.white,
                        ),
                        label: Text(
                          _sellerPickupActionLabel(parcel),
                          style: RiderStyle.buttonText,
                        ),
                        style: RiderStyle.primaryButton,
                      ),
                    ),
                    const SizedBox(height: 10),
                  ],

                  if (_canManageDelivery(parcel)) ...[
                    SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: ElevatedButton.icon(
                        onPressed: () {
                          Navigator.pop(sheetContext);

                          _openDeliveryManagement(parcel);
                        },
                        icon: const Icon(
                          Icons.delivery_dining_rounded,
                          color: Colors.white,
                        ),
                        label: const Text(
                          'MANAGE DELIVERY',
                          style: RiderStyle.buttonText,
                        ),
                        style: RiderStyle.primaryButton,
                      ),
                    ),
                    const SizedBox(height: 10),
                  ],

                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: OutlinedButton(
                      onPressed: () {
                        Navigator.pop(sheetContext);
                      },
                      style: OutlinedButton.styleFrom(
                        foregroundColor: RiderColors.primary,
                        side: const BorderSide(color: RiderColors.primary),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text(
                        'Close',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
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

  Widget _detailTile(String label, String value) {
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
          Text(label, style: RiderStyle.small),
          const SizedBox(height: 4),
          Text(value, style: RiderStyle.cardTitle),
        ],
      ),
    );
  }

  // ============================================================
  // STATUS
  // ============================================================

  String _normalizeStatus(String status) {
    return status
        .trim()
        .toLowerCase()
        .replaceAll(' ', '_')
        .replaceAll('-', '_');
  }

  String _formatStatus(String status) {
    final String normalized = _normalizeStatus(status);

    if (normalized.isEmpty) {
      return 'Unknown';
    }

    switch (normalized) {
      case 'ready_for_pickup':
        return 'Ready for Pickup';

      case 'assigned':
        return 'Assigned';

      case 'picked_up':
        return 'Picked Up';

      case 'in_transit':
        return 'In Transit';

      case 'at_sorting_center':
        return 'At Sorting Center';

      case 'staged':
        return 'Staged';

      case 'out_for_delivery':
        return 'Out for Delivery';

      case 'delivery_failed':
      case 'failed':
        return 'Delivery Failed';

      case 'failed_return_at_sorting_center':
        return 'Returned to Sorting Center';

      case 'delivered':
        return 'Delivered';

      default:
        return normalized
            .replaceAll('_', ' ')
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

  String _formatAssignmentType(String type) {
    switch (type.trim().toLowerCase()) {
      case 'final_mile':
        return 'Final-Mile Delivery';

      case 'seller_pickup':
        return 'Seller Pickup';

      case 'pickup_and_delivery':
        return 'Pickup & Final-Mile';

      default:
        return '';
    }
  }

  Color _statusColor(String status) {
    final String normalized = _normalizeStatus(status);

    switch (normalized) {
      case 'delivered':
      case 'completed':
        return RiderColors.success;

      case 'failed':
      case 'delivery_failed':
      case 'cancelled':
      case 'failed_return_at_sorting_center':
        return RiderColors.danger;

      case 'pending':
      case 'assigned':
      case 'ready_for_pickup':
        return RiderColors.warning;

      case 'picked_up':
      case 'in_transit':
      case 'out_for_delivery':
      case 'at_sorting_center':
      case 'staged':
      case 'sorted':
        return RiderColors.primary;

      default:
        return RiderColors.textGrey;
    }
  }

  // ============================================================
  // DATE
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
  // STATES
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
              Text('Loading assigned shipments...', style: RiderStyle.small),
            ],
          ),
        ),
      ],
    );
  }

  Widget _errorState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(30),
      children: [
        const SizedBox(height: 100),
        const Icon(
          Icons.cloud_off_outlined,
          size: 55,
          color: RiderColors.primary,
        ),
        const SizedBox(height: 20),
        const Text(
          'Unable to Load Shipments',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 18,
            color: RiderColors.textDark,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          _errorMessage ?? 'Something went wrong.',
          textAlign: TextAlign.center,
          style: RiderStyle.small,
        ),
        const SizedBox(height: 20),
        Center(
          child: ElevatedButton.icon(
            onPressed: () {
              _loadDeliveries(refresh: true);
            },
            icon: const Icon(Icons.refresh_rounded, color: Colors.white),
            label: const Text('Retry', style: TextStyle(color: Colors.white)),
            style: RiderStyle.primaryButton,
          ),
        ),
      ],
    );
  }

  Widget _emptyState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(30),
      children: const [
        SizedBox(height: 100),
        Icon(
          Icons.local_shipping_outlined,
          size: 55,
          color: RiderColors.primary,
        ),
        SizedBox(height: 20),
        Text(
          'No Shipments Assigned',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 18,
            color: RiderColors.textDark,
          ),
        ),
        SizedBox(height: 8),
        Text(
          'Seller pickups and final-mile deliveries '
          'assigned to your rider account will appear here.',
          textAlign: TextAlign.center,
          style: RiderStyle.small,
        ),
      ],
    );
  }

  Widget _noFilteredDeliveries() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(30),
      children: [
        const SizedBox(height: 100),
        const Icon(
          Icons.filter_alt_off_outlined,
          size: 55,
          color: RiderColors.textGrey,
        ),
        const SizedBox(height: 16),
        const Text(
          'No Shipments Found',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w800,
            color: RiderColors.textDark,
          ),
        ),
        const SizedBox(height: 7),
        const Text(
          'No assigned shipments match the selected status.',
          textAlign: TextAlign.center,
          style: RiderStyle.small,
        ),
        const SizedBox(height: 14),
        Center(
          child: TextButton(
            onPressed: () {
              setState(() {
                _selectedStatus = 'all';
              });
            },
            child: const Text('Show All Shipments'),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // HELPERS
  // ============================================================

  bool _toBool(dynamic value) {
    if (value == true) {
      return true;
    }

    final String text = value?.toString().trim().toLowerCase() ?? '';

    return text == '1' || text == 'true' || text == 'yes';
  }

  String _displayValue(String value, {String fallback = 'Not available'}) {
    final String cleaned = value.trim();

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return fallback;
    }

    return cleaned;
  }
}