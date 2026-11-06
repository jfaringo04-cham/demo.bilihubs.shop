import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../services/api_services.dart';
import '../../styles/rider_style.dart';
import '../../utils/address_formatter.dart';
import '../../utils/date_formatter.dart';
import '../../widgets/app_address_card.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_skeleton.dart';
import '../../widgets/status_chip.dart';

import 'delivery_confirmation.dart';
import 'rider_scanner.dart';

class RiderDeliveries extends StatefulWidget {
  const RiderDeliveries({super.key});

  @override
  State<RiderDeliveries> createState() => _RiderDeliveriesState();
}

class _RiderDeliveriesState extends State<RiderDeliveries> {
  static const Duration _requestTimeout = Duration(seconds: 30);

  static const List<Map<String, String>> _statusOptions = [
    {'value': 'all', 'label': 'All Statuses'},
    {'value': 'assigned', 'label': 'Assigned'},
    {'value': 'in_transit', 'label': 'In Transit'},
    {'value': 'at_sorting_center', 'label': 'At Sorting Center'},
    {'value': 'staged', 'label': 'Staged'},
    {'value': 'picked_up', 'label': 'Picked Up'},
    {'value': 'out_for_delivery', 'label': 'Out for Delivery'},
    {'value': 'delivered', 'label': 'Delivered'},
    {'value': 'delivery_failed', 'label': 'Delivery Failed'},
  ];

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isFetching = false;
  bool _hasLoaded = false;

  int _requestId = 0;

  String? _errorMessage;
  String _selectedStatus = 'all';

  List<ParcelModel> _deliveries = [];

  final Set<int> _processingIds = {};
  final Set<int> _openingScannerIds = {};

  @override
  void initState() {
    super.initState();
    _loadDeliveries();
  }

  @override
  void dispose() {
    _requestId++;
    super.dispose();
  }

  // ============================================================
  // API - LOAD ASSIGNED SHIPMENTS
  // ============================================================

  Future<void> _loadDeliveries() async {
    if (!mounted || _isFetching) return;

    final int requestId = ++_requestId;

    _isFetching = true;

    setState(() {
      _isLoading = !_hasLoaded;
      _isRefreshing = _hasLoaded;
      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> response =
          await ApiService.getRiderDeliveries().timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) return;

      if (response['success'] != true) {
        _setLoadError(
          requestId,
          _responseMessage(response, 'Unable to load assigned shipments.'),
        );
        return;
      }

      final List<Map<String, dynamic>> raw = _extractShipmentList(response);

      final List<ParcelModel> parsed = [];

      for (final Map<String, dynamic> item in raw) {
        try {
          final ParcelModel parcel = ParcelModel.fromJson(
            _normalizeShipment(item),
          );

          if (parcel.id > 0) {
            parsed.add(parcel);
          }
        } catch (error) {
          debugPrint('Shipment parsing error: $error');
        }
      }

      parsed.sort(_compareShipments);

      if (!mounted || requestId != _requestId) return;

      setState(() {
        _deliveries = parsed;
        _hasLoaded = true;
        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = null;
      });
    } on TimeoutException {
      _setLoadError(
        requestId,
        'The request timed out. Please refresh and try again.',
      );
    } catch (error) {
      debugPrint('Rider deliveries error: $error');

      _setLoadError(
        requestId,
        'Unable to connect to BiliHub. Check your internet connection.',
      );
    } finally {
      _isFetching = false;
    }
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
  // RESPONSE NORMALIZATION
  // ============================================================

  List<Map<String, dynamic>> _extractShipmentList(Map<String, dynamic> result) {
    for (final String key in ['data', 'shipments', 'deliveries', 'orders']) {
      if (result[key] is List) {
        return _toMapList(result[key]);
      }
    }

    final dynamic data = result['data'];

    if (data is Map) {
      for (final String key in ['data', 'shipments', 'deliveries', 'orders']) {
        if (data[key] is List) {
          return _toMapList(data[key]);
        }
      }
    }

    return [];
  }

  List<Map<String, dynamic>> _toMapList(dynamic value) {
    if (value is! List) return [];

    return value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Map<String, dynamic> _normalizeShipment(Map<String, dynamic> raw) {
    final Map<String, dynamic> map = Map<String, dynamic>.from(raw);

    if (raw['shipment'] is Map) {
      map.addAll(Map<String, dynamic>.from(raw['shipment']));
    }

    final Map<String, dynamic> order = map['order'] is Map
        ? Map<String, dynamic>.from(map['order'])
        : <String, dynamic>{};

    map['id'] ??= map['shipment_id'] ?? 0;
    map['shipment_id'] ??= map['id'];

    map['tracking_number'] ??=
        map['tracking_code'] ?? map['waybill_number'] ?? '';

    map['customer_name'] ??=
        order['customer_name'] ??
        map['recipient_name'] ??
        map['buyer_name'] ??
        '';

    map['customer_phone'] ??=
        order['customer_phone'] ?? map['recipient_phone'] ?? map['phone'] ?? '';

    map['delivery_address'] = _resolveDeliveryAddress(
      raw: raw,
      shipment: map,
      order: order,
    );

    map['pickup_address'] = AddressFormatter.clean(
      map['pickup_address'] ?? map['seller_address'],
    );

    map['status'] ??=
        map['shipment_status'] ?? map['delivery_status'] ?? 'pending';

    map['rider_name'] ??= map['driver_name'] ?? '';
    map['rider_id'] ??= map['driver_id'];

    map['order_date'] ??= order['created_at'] ?? map['created_at'] ?? '';

    map['delivery_earnings'] ??= map['earnings'] ?? map['delivery_fee'] ?? 0;

    return map;
  }

  int _compareShipments(ParcelModel a, ParcelModel b) {
    final DateTime? dateA = _activityDate(a);
    final DateTime? dateB = _activityDate(b);

    if (dateA != null && dateB != null) {
      final int result = dateB.compareTo(dateA);
      if (result != 0) return result;
    }

    if (dateA != null && dateB == null) return -1;
    if (dateB != null && dateA == null) return 1;

    return b.id.compareTo(a.id);
  }

  DateTime? _activityDate(ParcelModel parcel) {
    for (final String value in [
      parcel.updatedAt,
      parcel.pickedUpAt,
      parcel.createdAt,
      parcel.orderDate,
    ]) {
      final DateTime? parsed = DateFormatter.value(value);
      if (parsed != null) return parsed;
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

    return _deliveries.where((parcel) {
      final String status = parcel.normalizedStatus;

      if (_selectedStatus == 'delivery_failed') {
        return const <String>{
          'delivery_failed',
          'failed',
          'failed_return_at_sorting_center',
        }.contains(status);
      }

      return status == _selectedStatus;
    }).toList();
  }

  // ============================================================
  // ASSIGNMENT RULES
  // ============================================================

  bool _isFinalized(ParcelModel parcel) {
    return const <String>{
      'delivered',
      'completed',
      'delivery_failed',
      'failed',
      'cancelled',
      'canceled',
      'failed_return_at_sorting_center',
    }.contains(parcel.normalizedStatus);
  }

  bool _isSellerPickup(ParcelModel parcel) {
    return parcel.isSellerPickupAssignment;
  }

  bool _isFinalMile(ParcelModel parcel) {
    return parcel.isFinalMileAssignment;
  }

  bool _isLinehaul(ParcelModel parcel) {
    return parcel.isLinehaulAssignment;
  }

  bool _isBusy(ParcelModel parcel) {
    return _processingIds.contains(parcel.id) ||
        _openingScannerIds.contains(parcel.id);
  }

  // Seller pickup must be assigned and not yet
  // received by Logistics.
  //
  // The Laravel QR scan endpoint performs the
  // actual authorization and status update.
  bool _canScanSellerQr(ParcelModel parcel) {
    if (parcel.id <= 0 ||
        !_isSellerPickup(parcel) ||
        _isFinalized(parcel) ||
        parcel.receivedBySortingCenter) {
      return false;
    }

    return parcel.normalizedStatus == 'assigned';
  }

  // After successful seller QR scan, Laravel
  // sets status = in_transit.
  //
  // Only then can the rider declare that the
  // parcel has arrived at the sorting center.
  bool _canDeliverToSortingCenter(ParcelModel parcel) {
    if (parcel.id <= 0 ||
        !_isSellerPickup(parcel) ||
        _isFinalized(parcel) ||
        parcel.receivedBySortingCenter) {
      return false;
    }

    return parcel.normalizedStatus == 'in_transit';
  }

  // Final-mile hub scan must happen after Logistics
  // receiving, staging and rider assignment.
  bool _canScanHubQr(ParcelModel parcel) {
    if (parcel.id <= 0 || !_isFinalMile(parcel) || _isFinalized(parcel)) {
      return false;
    }

    if (!parcel.receivedBySortingCenter || !parcel.isStaged) {
      return false;
    }

    return const <String>{
      'assigned',
      'staged',
      'at_sorting_center',
    }.contains(parcel.normalizedStatus);
  }

  // The revised Laravel OrderController requires
  // a confirmed hub QR pickup before allowing
  // out_for_delivery.
  //
  // The DeliveryConfirmation page fetches the
  // latest shipment and rechecks permissions.
  bool _canManageDelivery(ParcelModel parcel) {
    if (parcel.id <= 0 || !_isFinalMile(parcel) || _isFinalized(parcel)) {
      return false;
    }

    return const <String>{
      'picked_up',
      'out_for_delivery',
    }.contains(parcel.normalizedStatus);
  }

  // ============================================================
  // OPEN QR SCANNER
  // ============================================================

  Future<void> _openScanner(ParcelModel parcel) async {
    if (!mounted || parcel.id <= 0 || _isBusy(parcel)) {
      return;
    }

    final bool seller = _canScanSellerQr(parcel);
    final bool hub = _canScanHubQr(parcel);

    if (!seller && !hub) return;

    setState(() {
      _openingScannerIds.add(parcel.id);
    });

    try {
      await Navigator.push<void>(
        context,
        MaterialPageRoute<void>(
          builder: (BuildContext scannerContext) {
            return RiderScanner(
              scannedParcel: parcel,
              onOpenDeliveries: () {
                Navigator.of(scannerContext).pop();
              },
            );
          },
        ),
      );

      if (!mounted) return;

      // A successful scan updates Laravel immediately.
      // Always reload assignments after returning.
      await _loadDeliveries();
    } catch (error) {
      debugPrint('Open rider scanner error: $error');

      if (!mounted) return;

      _showMessage('Unable to open the QR scanner.', isError: true);
    } finally {
      if (mounted) {
        setState(() {
          _openingScannerIds.remove(parcel.id);
        });
      }
    }
  }

  // ============================================================
  // SORTING CENTER HANDOFF
  // ============================================================

  Future<void> _deliverToSortingCenter(ParcelModel parcel) async {
    if (!mounted || !_canDeliverToSortingCenter(parcel) || _isBusy(parcel)) {
      return;
    }

    final bool confirmed = await _confirmAction(
      title: 'Confirm Sorting Center Arrival',
      message:
          'Tracking: ${_displayValue(parcel.trackingNumber)}\n\n'
          'Have you physically delivered this parcel '
          'to the assigned sorting center?\n\n'
          'This action records the rider handoff only. '
          'Logistics must separately confirm physical '
          'receipt and scan the parcel.',
      confirmLabel: 'CONFIRM ARRIVAL',
    );

    if (!mounted || !confirmed) return;

    if (_isBusy(parcel) || !_canDeliverToSortingCenter(parcel)) {
      return;
    }

    setState(() {
      _processingIds.add(parcel.id);
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.deliverRiderPickupToSortingCenter(
            shipmentId: parcel.id,
          ).timeout(_requestTimeout);

      if (!mounted) return;

      if (result['success'] == true) {
        _showMessage(
          _responseMessage(
            result,
            'Arrival recorded. Awaiting Logistics receipt.',
          ),
        );
      } else {
        _showMessage(
          _responseMessage(result, 'Unable to record sorting center arrival.'),
          isError: true,
        );
      }
    } on TimeoutException {
      if (!mounted) return;

      _showMessage(
        'The request timed out. The arrival may already '
        'have been recorded. Check the latest status.',
        isError: true,
      );
    } catch (error) {
      debugPrint('Sorting handoff error: $error');

      if (!mounted) return;

      _showMessage(
        'Unable to confirm arrival. Refresh before retrying.',
        isError: true,
      );
    } finally {
      if (mounted) {
        setState(() {
          _processingIds.remove(parcel.id);
        });

        await _loadDeliveries();
      }
    }
  }

  // ============================================================
  // FINAL-MILE DELIVERY
  // ============================================================

  Future<void> _openDeliveryManagement(ParcelModel parcel) async {
    if (!mounted || !_canManageDelivery(parcel)) return;

    final bool? changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute<bool>(
        builder: (_) => DeliveryConfirmation(parcel: parcel),
      ),
    );

    if (!mounted) return;

    // Reload even when changed == false, because
    // the server might have updated the shipment
    // after the screen was opened.
    if (changed == true || mounted) {
      await _loadDeliveries();
    }
  }

  // ============================================================
  // CONFIRMATION DIALOG
  // ============================================================

  Future<bool> _confirmAction({
    required String title,
    required String message,
    required String confirmLabel,
  }) async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          title: Text(title),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('CANCEL'),
            ),
            ElevatedButton(
              style: RiderStyle.primaryButton,
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(confirmLabel, style: RiderStyle.buttonText),
            ),
          ],
        );
      },
    );

    return confirmed == true;
  }

  // ============================================================
  // STATUS MESSAGES
  // ============================================================

  String _pickupProgress(ParcelModel parcel) {
    final String status = parcel.normalizedStatus;

    if (parcel.receivedBySortingCenter) {
      return 'Parcel received and confirmed by Logistics.';
    }

    if (status == 'assigned' || status == 'ready_for_pickup') {
      return 'Pickup assigned. Go to the seller, '
          'collect the parcel, and scan its original '
          'BiliHub waybill QR to confirm pickup.';
    }

    if (status == 'in_transit') {
      return 'Seller QR pickup confirmed. '
          'Deliver this parcel to the sorting center.';
    }

    if (status == 'at_sorting_center') {
      return 'Parcel arrived at the sorting center. '
          'Waiting for Logistics to receive it.';
    }

    if (status == 'staged') {
      return 'Parcel has been processed and staged.';
    }

    return '';
  }

  String _finalMileProgress(ParcelModel parcel) {
    final String status = parcel.normalizedStatus;

    if (_canScanHubQr(parcel)) {
      return 'Final-mile assignment is ready. '
          'Scan the original waybill QR when '
          'collecting the parcel from the hub.';
    }

    if (status == 'picked_up') {
      return 'Hub QR pickup confirmed. '
          'Open Manage Delivery to mark the parcel '
          'as Out for Delivery.';
    }

    if (status == 'out_for_delivery') {
      return 'Parcel is out for delivery. '
          'Complete the handoff, record any required '
          'COD payment, and upload delivery proof.';
    }

    if (status == 'delivered') {
      return 'Delivery completed successfully.';
    }

    if (parcel.receivedBySortingCenter && !parcel.isStaged) {
      return 'Waiting for Logistics sorting and staging.';
    }

    return '';
  }

  String _linehaulProgress(ParcelModel parcel) {
    if (!_isLinehaul(parcel)) return '';

    final String status = parcel.normalizedLinehaulStatus;
    final String routing = parcel.normalizedRoutingStatus;

    if (status == 'awaiting_assignment') {
      return 'Waiting for a line-haul assignment.';
    }

    if (status == 'assigned') {
      final String route = parcel.routeLabel.trim();

      return route.isEmpty
          ? 'Line-haul assigned. Waiting for dispatch.'
          : 'Line-haul route: $route. Waiting for dispatch.';
    }

    if (status == 'in_transit' || routing == 'in_hub_transfer') {
      return 'Parcel is in inter-hub transit.';
    }

    if (status == 'completed' || routing == 'at_destination_hub') {
      return 'Line-haul transfer completed.';
    }

    if (status == 'not_required') {
      return 'No line-haul transfer required.';
    }

    return 'Line-haul processing is managed by Logistics.';
  }

  // ============================================================
  // FEEDBACK
  // ============================================================

  String _responseMessage(Map<String, dynamic> response, String fallback) {
    final String message = response['message']?.toString().trim() ?? '';

    if (message.isNotEmpty) return message;

    final dynamic errors = response['errors'];

    if (errors is Map) {
      for (final dynamic entry in errors.values) {
        if (entry is List && entry.isNotEmpty) {
          return entry.first.toString();
        }
      }
    }

    return fallback;
  }

  void _showMessage(String message, {bool isError = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: isError ? RiderColors.danger : RiderColors.success,
          content: Text(message),
        ),
      );
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double screenWidth = MediaQuery.sizeOf(context).width;

    final double horizontalPadding = screenWidth < 380 ? 14 : 18;

    final List<ParcelModel> filtered = _filteredDeliveries;

    return Scaffold(
      backgroundColor: RiderColors.background,
      appBar: AppBar(
        backgroundColor: RiderColors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        automaticallyImplyLeading: false,
        titleSpacing: 18,
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('My Deliveries', style: RiderStyle.title),
            SizedBox(height: 3),
            Text(
              'Seller pickups and final-mile assignments',
              style: RiderStyle.small,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Refresh assignments',
            onPressed: _isFetching ? null : _loadDeliveries,
            icon: _isRefreshing
                ? const SizedBox(
                    width: 19,
                    height: 19,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: RiderColors.primary,
                    ),
                  )
                : const Icon(Icons.refresh_rounded, color: RiderColors.primary),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            _filterHeader(horizontalPadding, filtered),
            Expanded(
              child: RefreshIndicator(
                color: RiderColors.primary,
                onRefresh: _loadDeliveries,
                child: _buildBody(horizontalPadding, filtered),
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

  Widget _filterHeader(double horizontalPadding, List<ParcelModel> filtered) {
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
              if (_hasLoaded)
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: RiderColors.lightPurple,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    '${filtered.length} shipments',
                    style: const TextStyle(
                      color: RiderColors.primary,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            key: ValueKey<String>(_selectedStatus),
            initialValue: _selectedStatus,
            isExpanded: true,
            decoration: InputDecoration(
              filled: true,
              fillColor: RiderColors.background,
              prefixIcon: const Icon(
                Icons.filter_list_rounded,
                color: RiderColors.primary,
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(13),
                borderSide: BorderSide.none,
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(13),
                borderSide: const BorderSide(color: RiderColors.border),
              ),
            ),
            items: _statusOptions.map((option) {
              return DropdownMenuItem<String>(
                value: option['value'],
                child: Text(option['label'] ?? ''),
              );
            }).toList(),
            onChanged: (String? value) {
              if (value == null) return;

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

  Widget _buildBody(double horizontalPadding, List<ParcelModel> filtered) {
    if (_isLoading && !_hasLoaded) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(
          horizontalPadding,
          16,
          horizontalPadding,
          28,
        ),
        children: const [
          AppSkeleton(
            type: AppSkeletonType.parcel,
            itemCount: 4,
            color: RiderColors.primary,
          ),
        ],
      );
    }

    if (_errorMessage != null && !_hasLoaded) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          const SizedBox(height: 60),
          AppErrorState(
            title: 'Unable to Load Shipments',
            message: _errorMessage!,
            color: RiderColors.primary,
            onRetry: _loadDeliveries,
          ),
        ],
      );
    }

    if (_deliveries.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          if (_errorMessage != null) _refreshWarning(_errorMessage!),
          const SizedBox(height: 60),
          const AppEmptyState(
            title: 'No Shipments Assigned',
            message:
                'Accepted seller pickups and assigned '
                'final-mile deliveries will appear here.',
            icon: Icons.local_shipping_outlined,
            color: RiderColors.primary,
          ),
        ],
      );
    }

    if (filtered.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          const SizedBox(height: 60),
          AppEmptyState(
            title: 'No Shipments Found',
            message: 'No shipments match the selected status.',
            icon: Icons.filter_alt_off_outlined,
            color: RiderColors.primary,
            actionLabel: 'Show All Shipments',
            onAction: () {
              setState(() {
                _selectedStatus = 'all';
              });
            },
          ),
        ],
      );
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
      itemCount:
          filtered.length +
          (_isRefreshing ? 1 : 0) +
          (_errorMessage != null ? 1 : 0),
      itemBuilder: (context, index) {
        int adjusted = index;

        if (_isRefreshing) {
          if (adjusted == 0) {
            return const Padding(
              padding: EdgeInsets.only(bottom: 12),
              child: LinearProgressIndicator(color: RiderColors.primary),
            );
          }
          adjusted--;
        }

        if (_errorMessage != null) {
          if (adjusted == 0) {
            return _refreshWarning(_errorMessage!);
          }
          adjusted--;
        }

        return _deliveryCard(filtered[adjusted]);
      },
    );
  }

  Widget _refreshWarning(String message) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: RiderColors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: RiderColors.border),
      ),
      child: Row(
        children: [
          const Icon(Icons.wifi_off_rounded, color: RiderColors.warning),
          const SizedBox(width: 8),
          Expanded(child: Text(message, style: RiderStyle.small)),
          TextButton(
            onPressed: _isFetching ? null : _loadDeliveries,
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // SHIPMENT CARD
  // ============================================================

  Widget _deliveryCard(ParcelModel parcel) {
    final bool sellerPickup = _isSellerPickup(parcel);
    final bool finalMile = _isFinalMile(parcel);
    final bool linehaul = _isLinehaul(parcel);

    final bool scanSeller = _canScanSellerQr(parcel);
    final bool handoff = _canDeliverToSortingCenter(parcel);
    final bool scanHub = _canScanHubQr(parcel);
    final bool manage = _canManageDelivery(parcel);

    final bool busy = _isBusy(parcel);

    final String pickupProgress = _pickupProgress(parcel);

    final String finalMileProgress = _finalMileProgress(parcel);

    final String linehaulProgress = _linehaulProgress(parcel);

    final String orderDate = DateFormatter.dateTime(
      parcel.orderDate,
      fallback: '',
    );

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
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: RiderColors.lightPurple,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  sellerPickup
                      ? Icons.storefront_outlined
                      : linehaul
                      ? Icons.multiple_stop_rounded
                      : Icons.local_shipping_outlined,
                  color: RiderColors.primary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Tracking Number', style: RiderStyle.small),
                    const SizedBox(height: 4),
                    SelectableText(
                      _displayValue(parcel.trackingNumber),
                      maxLines: 2,
                      style: RiderStyle.cardTitle,
                    ),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          StatusChip(status: parcel.status),

          const SizedBox(height: 12),

          _assignmentBadge(parcel),

          if (sellerPickup && parcel.pickupAddress.trim().isNotEmpty) ...[
            const SizedBox(height: 14),
            AppInfoRow(
              label: 'Seller Pickup Address',
              value: parcel.pickupAddress,
              icon: Icons.storefront_outlined,
              iconColor: RiderColors.primary,
            ),
          ],

          if (linehaul) ...[
            const SizedBox(height: 12),
            if (parcel.originHubName.trim().isNotEmpty)
              AppInfoRow(
                label: 'Origin Hub',
                value: parcel.originHubName,
                icon: Icons.warehouse_outlined,
                iconColor: RiderColors.primary,
              ),
            if (parcel.destinationHubName.trim().isNotEmpty)
              AppInfoRow(
                label: 'Destination Hub',
                value: parcel.destinationHubName,
                icon: Icons.location_city_outlined,
                iconColor: RiderColors.primary,
              ),
            if (parcel.requiredVehicleType.trim().isNotEmpty)
              AppInfoRow(
                label: 'Required Vehicle',
                value: _titleCase(parcel.requiredVehicleType),
                icon: Icons.local_shipping_outlined,
                iconColor: RiderColors.primary,
              ),
            if (parcel.estimatedDistanceKm > 0)
              AppInfoRow(
                label: 'Estimated Distance',
                value: '${parcel.estimatedDistanceKm.toStringAsFixed(1)} km',
                icon: Icons.route_outlined,
                iconColor: RiderColors.primary,
              ),
          ],

          const SizedBox(height: 12),

          AppAddressCard(
            title: 'Delivery Address',
            recipientName: parcel.customerName,
            phoneNumber: parcel.customerPhone,
            address: parcel.deliveryAddress,
            accentColor: RiderColors.primary,
            compact: true,
          ),

          if (orderDate.isNotEmpty) ...[
            const SizedBox(height: 10),
            AppInfoRow(
              label: 'Order Date',
              value: orderDate,
              icon: Icons.calendar_today_outlined,
              iconColor: RiderColors.primary,
              compact: true,
            ),
          ],

          if (sellerPickup && pickupProgress.isNotEmpty)
            _progressBox(icon: Icons.route_outlined, text: pickupProgress),

          if (finalMile && finalMileProgress.isNotEmpty)
            _progressBox(
              icon: Icons.delivery_dining_outlined,
              text: finalMileProgress,
            ),

          if (linehaul && linehaulProgress.isNotEmpty)
            _progressBox(
              icon: Icons.multiple_stop_rounded,
              text: linehaulProgress,
            ),

          const SizedBox(height: 14),
          const Divider(color: RiderColors.border),
          const SizedBox(height: 10),

          if (scanSeller)
            _primaryAction(
              label: busy ? 'OPENING SCANNER...' : 'SCAN SELLER QR',
              icon: Icons.qr_code_scanner_rounded,
              enabled: !busy,
              onPressed: () => _openScanner(parcel),
            ),

          if (handoff)
            _primaryAction(
              label: busy ? 'UPDATING...' : 'ARRIVED AT SORTING CENTER',
              icon: Icons.warehouse_outlined,
              enabled: !busy,
              onPressed: () => _deliverToSortingCenter(parcel),
            ),

          if (scanHub)
            _primaryAction(
              label: busy ? 'OPENING SCANNER...' : 'SCAN HUB QR',
              icon: Icons.qr_code_scanner_rounded,
              enabled: !busy,
              onPressed: () => _openScanner(parcel),
            ),

          if (manage)
            _primaryAction(
              label: 'MANAGE DELIVERY',
              icon: Icons.delivery_dining_rounded,
              enabled: !busy,
              onPressed: () => _openDeliveryManagement(parcel),
            ),

          if (sellerPickup &&
              parcel.normalizedStatus == 'at_sorting_center' &&
              !parcel.receivedBySortingCenter)
            _progressBox(
              icon: Icons.hourglass_top_rounded,
              text: 'Awaiting Logistics receiving confirmation.',
            ),

          if (linehaul && !scanHub && !manage && !scanSeller && !handoff)
            _progressBox(
              icon: Icons.info_outline_rounded,
              text:
                  'Line-haul dispatch and hub receiving '
                  'are managed by Logistics.',
            ),

          const SizedBox(height: 10),

          SizedBox(
            width: double.infinity,
            height: 47,
            child: OutlinedButton.icon(
              onPressed: () => _showShipmentDetails(parcel),
              icon: const Icon(Icons.inventory_2_outlined, size: 18),
              label: const Text('VIEW SHIPMENT'),
              style: OutlinedButton.styleFrom(
                foregroundColor: RiderColors.primary,
                backgroundColor: RiderColors.white,
                side: const BorderSide(color: RiderColors.primary),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _primaryAction({
    required String label,
    required IconData icon,
    required bool enabled,
    required VoidCallback onPressed,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: SizedBox(
        width: double.infinity,
        height: 48,
        child: ElevatedButton.icon(
          style: RiderStyle.primaryButton,
          onPressed: enabled ? onPressed : null,
          icon: Icon(icon, color: Colors.white, size: 19),
          label: Text(label, style: RiderStyle.buttonText),
        ),
      ),
    );
  }

  // ============================================================
  // REUSABLE UI
  // ============================================================

  Widget _assignmentBadge(ParcelModel parcel) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: RiderColors.background,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: RiderColors.border),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            _isSellerPickup(parcel)
                ? Icons.storefront_outlined
                : _isLinehaul(parcel)
                ? Icons.multiple_stop_rounded
                : Icons.delivery_dining_outlined,
            color: RiderColors.primary,
            size: 15,
          ),
          const SizedBox(width: 7),
          Flexible(
            child: Text(
              parcel.assignmentLabel,
              style: const TextStyle(
                color: RiderColors.textDark,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _progressBox({required IconData icon, required String text}) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: RiderColors.background,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: RiderColors.border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: RiderColors.primary, size: 18),
          const SizedBox(width: 9),
          Expanded(child: Text(text, style: RiderStyle.small)),
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
        final bool sellerPickup = _isSellerPickup(parcel);

        final bool finalMile = _isFinalMile(parcel);

        final bool linehaul = _isLinehaul(parcel);

        return SafeArea(
          child: Container(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.sizeOf(sheetContext).height * 0.88,
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
                    'Shipment Details',
                    style: RiderStyle.sectionTitle,
                  ),
                  const SizedBox(height: 12),

                  StatusChip(status: parcel.status),
                  const SizedBox(height: 16),

                  _detailTile(
                    'Tracking Number',
                    _displayValue(parcel.trackingNumber),
                  ),

                  _detailTile('Assignment', parcel.assignmentLabel),

                  if (sellerPickup)
                    _detailTile('Pickup Progress', _pickupProgress(parcel)),

                  if (finalMile)
                    _detailTile(
                      'Final-Mile Progress',
                      _finalMileProgress(parcel),
                    ),

                  if (linehaul)
                    _detailTile(
                      'Line-Haul Progress',
                      _linehaulProgress(parcel),
                    ),

                  if (parcel.pickupAddress.trim().isNotEmpty)
                    _detailTile('Seller Pickup Address', parcel.pickupAddress),

                  if (parcel.originHubName.trim().isNotEmpty)
                    _detailTile('Origin Hub', parcel.originHubName),

                  if (parcel.currentHubName.trim().isNotEmpty)
                    _detailTile('Current Hub', parcel.currentHubName),

                  if (parcel.destinationHubName.trim().isNotEmpty)
                    _detailTile('Destination Hub', parcel.destinationHubName),

                  _detailTile('Customer', _displayValue(parcel.customerName)),

                  _detailTile(
                    'Customer Phone',
                    _displayValue(parcel.customerPhone),
                  ),

                  AppAddressCard(
                    title: 'Delivery Address',
                    recipientName: parcel.customerName,
                    phoneNumber: parcel.customerPhone,
                    address: parcel.deliveryAddress,
                    accentColor: RiderColors.primary,
                    compact: true,
                  ),

                  const SizedBox(height: 12),

                  if (_canScanSellerQr(parcel) || _canScanHubQr(parcel))
                    _primaryAction(
                      label: _canScanSellerQr(parcel)
                          ? 'SCAN SELLER QR'
                          : 'SCAN HUB QR',
                      icon: Icons.qr_code_scanner_rounded,
                      enabled: !_isBusy(parcel),
                      onPressed: () {
                        Navigator.pop(sheetContext);
                        _openScanner(parcel);
                      },
                    ),

                  if (_canDeliverToSortingCenter(parcel))
                    _primaryAction(
                      label: 'ARRIVED AT SORTING CENTER',
                      icon: Icons.warehouse_outlined,
                      enabled: !_isBusy(parcel),
                      onPressed: () {
                        Navigator.pop(sheetContext);
                        _deliverToSortingCenter(parcel);
                      },
                    ),

                  if (_canManageDelivery(parcel))
                    _primaryAction(
                      label: 'MANAGE DELIVERY',
                      icon: Icons.delivery_dining_rounded,
                      enabled: !_isBusy(parcel),
                      onPressed: () {
                        Navigator.pop(sheetContext);
                        _openDeliveryManagement(parcel);
                      },
                    ),

                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(sheetContext),
                      child: const Text('Close'),
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
          SelectableText(value, style: RiderStyle.cardTitle),
        ],
      ),
    );
  }

  // ============================================================
  // FORMATTING HELPERS
  // ============================================================

  String _titleCase(String value) {
    final String normalized = value
        .trim()
        .replaceAll('-', '_')
        .replaceAll(' ', '_');

    if (normalized.isEmpty) return 'Unknown';

    return normalized
        .split('_')
        .where((word) => word.isNotEmpty)
        .map(
          (word) =>
              '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  String _resolveDeliveryAddress({
    required Map<String, dynamic> raw,
    required Map<String, dynamic> shipment,
    required Map<String, dynamic> order,
  }) {
    final List<dynamic> candidates = [
      shipment['delivery_address'],
      shipment['full_delivery_address'],
      shipment['shipping_address'],
      shipment['buyer_address'],
      shipment['recipient_address'],
      shipment['address'],
      raw['delivery_address'],
      raw['shipping_address'],
      raw['address'],
      order['delivery_address'],
      order['shipping_address'],
      order['address'],
    ];

    for (final dynamic candidate in candidates) {
      final String address = AddressFormatter.clean(candidate);

      if (address.isNotEmpty) {
        return address;
      }
    }

    return '';
  }

  String _displayValue(String value, {String fallback = 'Not available'}) {
    final String cleaned = value.trim();

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return fallback;
    }

    return cleaned;
  }
}
