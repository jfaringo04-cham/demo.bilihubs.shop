import 'package:flutter/material.dart';

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
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      orderNumber: json['order_number']?.toString() ?? '',
      trackingNumber: json['tracking_number']?.toString() ?? '',
      sellerName: json['seller_name']?.toString() ?? '',
      customerName: json['customer_name']?.toString() ?? '',
      deliveryAddress: json['delivery_address']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
      source: json['source']?.toString() ?? '',
    );
  }
}

class AvailablePickups extends StatefulWidget {
  final List<AvailablePickup> pickups;

  const AvailablePickups({super.key, this.pickups = const []});

  @override
  State<AvailablePickups> createState() => _AvailablePickupsState();
}

class _AvailablePickupsState extends State<AvailablePickups> {
  String _selectedFilter = 'all';

  final Map<int, TextEditingController> _noteControllers = {};

  @override
  void dispose() {
    for (final controller in _noteControllers.values) {
      controller.dispose();
    }

    super.dispose();
  }

  // ============================================================
  // FILTERED PICKUPS
  // ============================================================

  List<AvailablePickup> get _filteredPickups {
    if (_selectedFilter == 'all') {
      return widget.pickups;
    }

    return widget.pickups.where((pickup) {
      final source = pickup.source.trim().toLowerCase().replaceAll(' ', '_');

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
    final width = MediaQuery.of(context).size.width;

    final horizontalPadding = width < 380 ? 14.0 : 18.0;

    final pickups = _filteredPickups;

    return Scaffold(
      backgroundColor: RiderColors.background,

      appBar: AppBar(
        backgroundColor: RiderColors.white,

        elevation: 0,

        surfaceTintColor: Colors.transparent,

        iconTheme: const IconThemeData(color: RiderColors.textDark),

        title: const Text('Available Pickups', style: RiderStyle.title),
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
                onRefresh: _refreshPickups,

                child: pickups.isEmpty
                    ? _emptyState()
                    : ListView.builder(
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

                        itemBuilder: (context, index) {
                          return _pickupCard(pickups[index]);
                        },
                      ),
              ),
            ),
          ],
        ),
      ),
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
    final selected = _selectedFilter == value;

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
          // HEADER

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

          // NOTES
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

                borderSide: BorderSide(color: RiderColors.border),
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
                _showBackendUnavailable();
              },

              icon: const Icon(Icons.local_shipping_outlined, size: 19),

              label: const Text('Deliver to Sorting Center'),

              style: RiderStyle.primaryButton,
            ),
          ),
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
    final formatted = _formatStatus(status);

    final color = _statusColor(status);

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
    final value = status.trim().toLowerCase();

    if (value.contains('sorting')) {
      return RiderColors.primary;
    }

    if (value == 'delivered' || value == 'completed') {
      return RiderColors.success;
    }

    if (value == 'pending' || value == 'ready_for_pickup' || value == 'ready') {
      return RiderColors.warning;
    }

    return RiderColors.textGrey;
  }

  String _formatStatus(String value) {
    final cleaned = value.trim().replaceAll('_', ' ');

    if (cleaned.isEmpty) {
      return 'Unknown';
    }

    return cleaned
        .split(RegExp(r'\s+'))
        .map(
          (word) => word.isEmpty
              ? word
              : '${word[0].toUpperCase()}${word.substring(1).toLowerCase()}',
        )
        .join(' ');
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
          'Available pickup shipments will appear here once the mobile app is connected to the pickup API.',

          textAlign: TextAlign.center,

          style: RiderStyle.small,
        ),
      ],
    );
  }

  // ============================================================
  // REFRESH
  // ============================================================

  Future<void> _refreshPickups() async {
    if (!mounted) return;

    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        behavior: SnackBarBehavior.floating,

        content: Text('Available Pickups API is not connected yet.'),
      ),
    );
  }

  // ============================================================
  // BACKEND ACTION
  // ============================================================

  void _showBackendUnavailable() {
    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        behavior: SnackBarBehavior.floating,

        content: Text(
          'Pickup processing will be enabled once the Rider pickup API is connected.',
        ),
      ),
    );
  }

  String _displayValue(String value, {String fallback = 'Not available'}) {
    final cleaned = value.trim();

    return cleaned.isEmpty ? fallback : cleaned;
  }
}
