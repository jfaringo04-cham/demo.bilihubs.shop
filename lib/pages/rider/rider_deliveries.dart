import 'package:flutter/material.dart';

import '../../models/parcel_model.dart';
import '../../sevices/api_services.dart';
import '../../styles/rider_style.dart';

class RiderDeliveries extends StatefulWidget {
  const RiderDeliveries({super.key});

  @override
  State<RiderDeliveries> createState() => _RiderDeliveriesState();
}

class _RiderDeliveriesState extends State<RiderDeliveries> {
  bool _isLoading = true;

  String? _errorMessage;

  List<ParcelModel> _deliveries = [];

  String _selectedStatus = 'all';

  // ============================================================
  // STATUS OPTIONS
  // ============================================================

  final List<Map<String, String>> _statusOptions = const [
    {'value': 'all', 'label': 'All Statuses'},
    {'value': 'pending', 'label': 'Pending'},
    {'value': 'assigned', 'label': 'Assigned'},
    {'value': 'staged', 'label': 'Staged'},
    {'value': 'at_sorting_center', 'label': 'At Sorting Center'},
    {'value': 'picked_up', 'label': 'Picked Up'},
    {'value': 'in_transit', 'label': 'In Transit'},
    {'value': 'out_for_delivery', 'label': 'Out for Delivery'},
    {'value': 'delivered', 'label': 'Delivered'},
    {'value': 'failed', 'label': 'Failed'},
  ];

  @override
  void initState() {
    super.initState();

    _loadDeliveries();
  }

  // ============================================================
  // LOAD RIDER DELIVERIES
  // ============================================================

  Future<void> _loadDeliveries() async {
    if (mounted) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final result = await ApiService.getRiderDeliveries();

      if (!mounted) {
        return;
      }

      if (result['success'] == true) {
        final dynamic rawData = result['data'];

        final List<ParcelModel> parsedDeliveries = [];

        if (rawData is List) {
          for (final item in rawData) {
            try {
              if (item is Map<String, dynamic>) {
                parsedDeliveries.add(ParcelModel.fromJson(item));
              } else if (item is Map) {
                parsedDeliveries.add(
                  ParcelModel.fromJson(Map<String, dynamic>.from(item)),
                );
              }
            } catch (error) {
              debugPrint('Failed to parse rider shipment: $error');
            }
          }
        }

        setState(() {
          _deliveries = parsedDeliveries;
          _isLoading = false;
          _errorMessage = null;
        });

        return;
      }

      setState(() {
        _deliveries = [];
        _isLoading = false;

        _errorMessage =
            result['message']?.toString() ?? 'Unable to load your deliveries.';
      });
    } catch (error) {
      debugPrint('Rider deliveries error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _deliveries = [];
        _isLoading = false;

        _errorMessage = 'Unable to connect to the server. Please try again.';
      });
    }
  }

  // ============================================================
  // FILTERED DELIVERIES
  // ============================================================

  List<ParcelModel> get _filteredDeliveries {
    if (_selectedStatus == 'all') {
      return _deliveries;
    }

    return _deliveries.where((parcel) {
      final status = _normalizeStatus(parcel.status);

      if (_selectedStatus == 'pending') {
        return status == 'pending' || status == 'ready_for_pickup';
      }

      if (_selectedStatus == 'failed') {
        return status == 'failed' ||
            status == 'delivery_failed' ||
            status == 'cancelled' ||
            status == 'failed_return_at_sorting_center';
      }

      return status == _selectedStatus;
    }).toList();
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final screenWidth = MediaQuery.of(context).size.width;

    final horizontalPadding = screenWidth < 380 ? 14.0 : 18.0;

    final deliveries = _filteredDeliveries;

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
            Text('Manage your assigned shipments', style: RiderStyle.small),
          ],
        ),

        actions: [
          IconButton(
            tooltip: 'Refresh deliveries',
            onPressed: _isLoading ? null : _loadDeliveries,
            icon: const Icon(Icons.refresh_rounded, color: RiderColors.primary),
          ),

          const SizedBox(width: 6),
        ],
      ),

      body: SafeArea(
        top: false,
        child: Column(
          children: [
            // ==================================================
            // FILTER HEADER
            // ==================================================

            Container(
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

                    items: _statusOptions.map((option) {
                      return DropdownMenuItem<String>(
                        value: option['value'],
                        child: Text(option['label'] ?? ''),
                      );
                    }).toList(),

                    onChanged: (value) {
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
            ),

            // ==================================================
            // BODY
            // ==================================================
            Expanded(
              child: RefreshIndicator(
                onRefresh: _loadDeliveries,
                child: _buildBody(horizontalPadding, deliveries),
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

      itemBuilder: (context, index) {
        final parcel = deliveries[index];

        return _deliveryCard(parcel);
      },
    );
  }

  // ============================================================
  // DELIVERY CARD
  // ============================================================

  Widget _deliveryCard(ParcelModel parcel) {
    final status = _formatStatus(parcel.status);

    final statusColor = _statusColor(parcel.status);

    final formattedDate = _formatDate(parcel.orderDate);

    return Container(
      margin: const EdgeInsets.only(bottom: 14),

      padding: const EdgeInsets.all(16),

      decoration: RiderStyle.card,

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          // ====================================================
          // HEADER
          // ====================================================

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
                  Icons.local_shipping_outlined,
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
                constraints: const BoxConstraints(maxWidth: 120),
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

          const SizedBox(height: 17),

          // ====================================================
          // CUSTOMER
          // ====================================================
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

          const SizedBox(height: 8),

          Divider(color: RiderColors.border.withValues(alpha: 0.8)),

          const SizedBox(height: 8),

          // ====================================================
          // VIEW SHIPMENT
          // ====================================================
          SizedBox(
            width: double.infinity,
            height: 47,

            child: OutlinedButton.icon(
              onPressed: () {
                _showShipmentDetails(parcel);
              },

              icon: const Icon(Icons.inventory_2_outlined, size: 18),

              label: const Text('View Shipment'),

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
        final statusColor = _statusColor(parcel.status);

        final formattedDate = _formatDate(parcel.orderDate);

        return SafeArea(
          child: Container(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(sheetContext).size.height * 0.85,
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

                        child: const Icon(
                          Icons.local_shipping_outlined,
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

                  _detailTile('Customer', _displayValue(parcel.customerName)),

                  _detailTile('Contact', _displayValue(parcel.customerPhone)),

                  _detailTile(
                    'Delivery Address',
                    _displayValue(parcel.deliveryAddress),
                  ),

                  _detailTile(
                    'Assigned Rider',
                    _displayValue(parcel.riderName, fallback: 'Not available'),
                  ),

                  if (formattedDate.isNotEmpty)
                    _detailTile('Order Date', formattedDate),

                  _detailTile('Status', _formatStatus(parcel.status)),

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

  // ============================================================
  // DETAIL TILE
  // ============================================================

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
  // NORMALIZE STATUS
  // ============================================================

  String _normalizeStatus(String status) {
    return status.trim().toLowerCase().replaceAll(' ', '_');
  }

  // ============================================================
  // FORMAT STATUS
  // ============================================================

  String _formatStatus(String status) {
    final normalized = _normalizeStatus(status);

    if (normalized.isEmpty) {
      return 'Unknown';
    }

    switch (normalized) {
      case 'ready_for_pickup':
        return 'Ready for Pickup';

      case 'at_sorting_center':
        return 'At Sorting Center';

      case 'picked_up':
        return 'Picked Up';

      case 'in_transit':
        return 'In Transit';

      case 'out_for_delivery':
        return 'Out for Delivery';

      case 'delivery_failed':
        return 'Delivery Failed';

      case 'failed_return_at_sorting_center':
        return 'Returned to Sorting Center';

      default:
        final cleaned = normalized.replaceAll('_', ' ');

        return cleaned
            .split(RegExp(r'\s+'))
            .map((word) {
              if (word.isEmpty) {
                return word;
              }

              return '${word[0].toUpperCase()}'
                  '${word.substring(1).toLowerCase()}';
            })
            .join(' ');
    }
  }

  // ============================================================
  // STATUS COLOR
  // ============================================================

  Color _statusColor(String status) {
    final normalized = _normalizeStatus(status);

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
      case 'staged':
        return RiderColors.warning;

      case 'picked_up':
      case 'in_transit':
      case 'out_for_delivery':
      case 'at_sorting_center':
      case 'sorted':
        return RiderColors.primary;

      default:
        return RiderColors.textGrey;
    }
  }

  // ============================================================
  // FORMAT DATE
  // ============================================================

  String _formatDate(String value) {
    final cleaned = value.trim();

    if (cleaned.isEmpty) {
      return '';
    }

    final parsed = DateTime.tryParse(cleaned);

    if (parsed == null) {
      return cleaned;
    }

    final local = parsed.toLocal();

    const months = [
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

    final month = months[local.month - 1];

    final hour = local.hour == 0
        ? 12
        : local.hour > 12
        ? local.hour - 12
        : local.hour;

    final minute = local.minute.toString().padLeft(2, '0');

    final meridiem = local.hour >= 12 ? 'PM' : 'AM';

    return '$month ${local.day}, ${local.year} • '
        '$hour:$minute $meridiem';
  }

  // ============================================================
  // LOADING STATE
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

              Text('Loading deliveries...', style: RiderStyle.small),
            ],
          ),
        ),
      ],
    );
  }

  // ============================================================
  // ERROR STATE
  // ============================================================

  Widget _errorState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),

      padding: const EdgeInsets.all(30),

      children: [
        const SizedBox(height: 100),

        Center(
          child: Container(
            height: 85,
            width: 85,

            decoration: const BoxDecoration(
              color: RiderColors.lightPurple,
              shape: BoxShape.circle,
            ),

            child: const Icon(
              Icons.cloud_off_outlined,
              size: 40,
              color: RiderColors.primary,
            ),
          ),
        ),

        const SizedBox(height: 20),

        const Text(
          'Unable to Load Deliveries',
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
            onPressed: _loadDeliveries,
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

      padding: const EdgeInsets.all(30),

      children: [
        const SizedBox(height: 100),

        Center(
          child: Container(
            height: 90,
            width: 90,

            decoration: const BoxDecoration(
              color: RiderColors.lightPurple,
              shape: BoxShape.circle,
            ),

            child: const Icon(
              Icons.local_shipping_outlined,
              size: 43,
              color: RiderColors.primary,
            ),
          ),
        ),

        const SizedBox(height: 20),

        const Text(
          'No Deliveries Assigned',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 18,
            color: RiderColors.textDark,
          ),
        ),

        const SizedBox(height: 8),

        const Text(
          'Shipments assigned to your rider account will appear here.',
          textAlign: TextAlign.center,
          style: RiderStyle.small,
        ),
      ],
    );
  }

  // ============================================================
  // NO FILTER RESULTS
  // ============================================================

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
          'No Deliveries Found',
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
            child: const Text('Show All Deliveries'),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // DISPLAY VALUE
  // ============================================================

  String _displayValue(String value, {String fallback = 'Not available'}) {
    final cleaned = value.trim();

    return cleaned.isEmpty ? fallback : cleaned;
  }
}
