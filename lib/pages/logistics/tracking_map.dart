import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../models/parcel_model.dart';
import '../../sevices/api_services.dart';
import '../../styles/logistics_style.dart';
import 'parcel_information.dart';

class TrackingMap extends StatefulWidget {
  const TrackingMap({super.key});

  @override
  State<TrackingMap> createState() => _TrackingMapState();
}

class _TrackingMapState extends State<TrackingMap> {
  final MapController _mapController = MapController();

  bool _isLoading = true;
  bool _isRefreshing = false;

  String? _errorMessage;

  List<ParcelModel> _parcels = <ParcelModel>[];

  ParcelModel? _selectedParcel;

  Timer? _refreshTimer;

  static const Duration _refreshInterval = Duration(seconds: 5);

  @override
  void initState() {
    super.initState();

    _loadTracking();

    _refreshTimer = Timer.periodic(_refreshInterval, (_) {
      _loadTracking(silent: true);
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();

    _mapController.dispose();

    super.dispose();
  }

  // ============================================================
  // LOAD TRACKING DATA
  // ============================================================

  Future<void> _loadTracking({bool silent = false}) async {
    if (!mounted) return;

    if (silent) {
      if (_isRefreshing) return;

      setState(() {
        _isRefreshing = true;
      });
    } else {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final Map<String, dynamic> result =
          await ApiService.getLogisticsShipments(perPage: 100);

      if (!mounted) return;

      if (result['success'] != true) {
        setState(() {
          _isLoading = false;
          _isRefreshing = false;

          _errorMessage =
              result['message']?.toString() ??
              'Unable to load parcel tracking.';
        });

        return;
      }

      final dynamic rawData = result['data'];

      if (rawData is! List) {
        setState(() {
          _isLoading = false;
          _isRefreshing = false;
          _errorMessage = 'Invalid tracking data received from server.';
        });

        return;
      }

      final List<ParcelModel> loaded = <ParcelModel>[];

      for (final dynamic item in rawData) {
        if (item is! Map) {
          continue;
        }

        final Map<String, dynamic> map = Map<String, dynamic>.from(item);

        map['order_date'] ??= map['created_at'] ?? '';

        map['tracking_number'] ??= map['tracking_code'] ?? '';

        map['customer_name'] ??= '';
        map['customer_phone'] ??= '';
        map['delivery_address'] ??= '';
        map['rider_name'] ??= '';
        map['status'] ??= 'pending';

        try {
          final ParcelModel parcel = ParcelModel.fromJson(map);

          if (parcel.isActive) {
            loaded.add(parcel);
          }
        } catch (e) {
          debugPrint('Unable to parse parcel for tracking: $e');
        }
      }

      ParcelModel? updatedSelectedParcel;

      final ParcelModel? currentSelected = _selectedParcel;

      if (currentSelected != null) {
        for (final ParcelModel parcel in loaded) {
          if (parcel.id == currentSelected.id) {
            updatedSelectedParcel = parcel;
            break;
          }
        }
      }

      setState(() {
        _parcels = loaded;

        _selectedParcel = updatedSelectedParcel ?? _selectedParcel;

        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = null;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isLoading = false;
        _isRefreshing = false;

        _errorMessage = 'Unable to load parcel tracking: $e';
      });
    }
  }

  // ============================================================
  // PARCELS WITH LOCATION
  // ============================================================

  List<ParcelModel> get _withLocation {
    return _parcels.where((ParcelModel parcel) => parcel.hasLocation).toList();
  }

  // ============================================================
  // MAP CENTER
  // ============================================================

  LatLng get _initialCenter {
    final List<ParcelModel> located = _withLocation;

    if (located.isNotEmpty) {
      return LatLng(located.first.latitude, located.first.longitude);
    }

    // Default map center if no coordinates exist.
    return const LatLng(14.2691, 121.4113);
  }

  // ============================================================
  // SELECT PARCEL
  // ============================================================

  void _selectParcel(ParcelModel parcel) {
    if (!parcel.hasLocation) {
      return;
    }

    setState(() {
      _selectedParcel = parcel;
    });

    _mapController.move(LatLng(parcel.latitude, parcel.longitude), 16);
  }

  // ============================================================
  // OPEN PARCEL DETAILS
  // ============================================================

  Future<void> _openParcel(ParcelModel parcel) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (BuildContext context) {
          return ParcelInformation(parcel: parcel);
        },
      ),
    );

    if (!mounted) return;

    await _loadTracking();
  }

  // ============================================================
  // FIT FIRST MARKER
  // ============================================================

  void _focusFirstParcel() {
    final List<ParcelModel> parcels = _withLocation;

    if (parcels.isEmpty) {
      return;
    }

    _selectParcel(parcels.first);
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final List<ParcelModel> locatedParcels = _withLocation;

    return Scaffold(
      backgroundColor: LogisticsColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        surfaceTintColor: Colors.transparent,

        title: const Text('Parcel Tracking', style: LogisticsStyle.title),

        actions: [
          if (_isRefreshing)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 14),
              child: Center(
                child: SizedBox(
                  height: 18,
                  width: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: LogisticsColors.primary,
                  ),
                ),
              ),
            )
          else
            IconButton(
              tooltip: 'Refresh',
              onPressed: () {
                _loadTracking();
              },
              icon: const Icon(Icons.refresh_rounded),
            ),
        ],
      ),

      body: RefreshIndicator(
        onRefresh: _loadTracking,

        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),

          padding: const EdgeInsets.all(16),

          children: [
            if (_isLoading)
              const LinearProgressIndicator(color: LogisticsColors.primary),

            if (_errorMessage != null) ...[
              const SizedBox(height: 12),
              _errorCard(),
            ],

            const SizedBox(height: 16),

            // ====================================================
            // SUMMARY
            // ====================================================
            Container(
              padding: const EdgeInsets.all(16),

              decoration: LogisticsStyle.card,

              child: Row(
                children: [
                  Container(
                    height: 52,
                    width: 52,

                    decoration: LogisticsStyle.iconContainer(),

                    child: const Icon(
                      Icons.location_on_outlined,

                      color: LogisticsColors.primary,

                      size: 27,
                    ),
                  ),

                  const SizedBox(width: 14),

                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,

                      children: [
                        const Text(
                          'Live Parcel Tracking',

                          style: LogisticsStyle.sectionTitle,
                        ),

                        const SizedBox(height: 4),

                        Text(
                          '${locatedParcels.length} of ${_parcels.length} active parcels currently have GPS coordinates.',

                          style: LogisticsStyle.small,
                        ),

                        const SizedBox(height: 3),

                        const Text(
                          'Automatically refreshes every 5 seconds.',

                          style: LogisticsStyle.small,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // ====================================================
            // MAP
            // ====================================================
            Container(
              height: 390,

              clipBehavior: Clip.antiAlias,

              decoration: BoxDecoration(
                color: LogisticsColors.white,

                borderRadius: BorderRadius.circular(20),

                border: Border.all(color: LogisticsColors.border),
              ),

              child: locatedParcels.isEmpty
                  ? _noLocationMap()
                  : Stack(
                      children: [
                        FlutterMap(
                          mapController: _mapController,

                          options: MapOptions(
                            initialCenter: _initialCenter,

                            initialZoom: 13,

                            minZoom: 4,

                            maxZoom: 19,
                          ),

                          children: [
                            TileLayer(
                              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',

                              userAgentPackageName: 'com.bilihub.mobile',
                            ),

                            MarkerLayer(
                              markers: locatedParcels.map((ParcelModel parcel) {
                                final bool selected =
                                    _selectedParcel?.id == parcel.id;

                                return Marker(
                                  point: LatLng(
                                    parcel.latitude,
                                    parcel.longitude,
                                  ),

                                  width: selected ? 58 : 48,

                                  height: selected ? 58 : 48,

                                  child: GestureDetector(
                                    onTap: () {
                                      _selectParcel(parcel);
                                    },

                                    child: Container(
                                      decoration: BoxDecoration(
                                        color: selected
                                            ? LogisticsColors.primary
                                            : Colors.white,

                                        shape: BoxShape.circle,

                                        border: Border.all(
                                          color: LogisticsColors.primary,

                                          width: 3,
                                        ),

                                        boxShadow: [
                                          BoxShadow(
                                            color: Colors.black.withValues(
                                              alpha: 0.18,
                                            ),

                                            blurRadius: 8,

                                            offset: const Offset(0, 4),
                                          ),
                                        ],
                                      ),

                                      child: Icon(
                                        Icons.local_shipping_rounded,

                                        color: selected
                                            ? Colors.white
                                            : LogisticsColors.primary,

                                        size: selected ? 28 : 23,
                                      ),
                                    ),
                                  ),
                                );
                              }).toList(),
                            ),
                          ],
                        ),

                        // ====================================================
                        // MAP CONTROLS
                        // ====================================================
                        Positioned(
                          right: 12,
                          top: 12,

                          child: Column(
                            children: [
                              _mapButton(
                                icon: Icons.add_rounded,

                                onTap: () {
                                  _mapController.move(
                                    _mapController.camera.center,

                                    _mapController.camera.zoom + 1,
                                  );
                                },
                              ),

                              const SizedBox(height: 8),

                              _mapButton(
                                icon: Icons.remove_rounded,

                                onTap: () {
                                  _mapController.move(
                                    _mapController.camera.center,

                                    _mapController.camera.zoom - 1,
                                  );
                                },
                              ),

                              const SizedBox(height: 8),

                              _mapButton(
                                icon: Icons.my_location_rounded,

                                onTap: _focusFirstParcel,
                              ),
                            ],
                          ),
                        ),

                        // ====================================================
                        // AUTO UPDATE BADGE
                        // ====================================================
                        Positioned(
                          left: 12,
                          top: 12,

                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 7,
                            ),

                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.94),

                              borderRadius: BorderRadius.circular(20),
                            ),

                            child: const Row(
                              mainAxisSize: MainAxisSize.min,

                              children: [
                                Icon(
                                  Icons.sync_rounded,

                                  size: 15,

                                  color: LogisticsColors.success,
                                ),

                                SizedBox(width: 5),

                                Text(
                                  'Auto refresh',

                                  style: TextStyle(
                                    fontSize: 10,

                                    fontWeight: FontWeight.w700,

                                    color: LogisticsColors.textDark,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
            ),

            const SizedBox(height: 16),

            // ====================================================
            // SELECTED PARCEL
            // ====================================================
            if (_selectedParcel != null && _selectedParcel!.hasLocation)
              _selectedParcelCard(_selectedParcel!),

            if (_selectedParcel != null) const SizedBox(height: 16),

            // ====================================================
            // PARCEL LIST
            // ====================================================
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Tracked Parcels',

                    style: LogisticsStyle.sectionTitle,
                  ),
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
                    '${locatedParcels.length}',

                    style: const TextStyle(
                      color: LogisticsColors.primary,

                      fontWeight: FontWeight.w800,

                      fontSize: 12,
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 12),

            if (!_isLoading && _parcels.isEmpty)
              _emptyCard(
                title: 'No Active Parcels',

                message: 'There are currently no active parcels available for tracking.',

                icon: Icons.inventory_2_outlined,
              )
            else if (!_isLoading && locatedParcels.isEmpty)
              _emptyCard(
                title: 'No GPS Coordinates Yet',

                message: 'Active parcels were found, but they do not currently have latitude and longitude values.',

                icon: Icons.location_off_outlined,
              )
            else
              ...locatedParcels.map((ParcelModel parcel) {
                return _parcelListCard(parcel);
              }),

            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // NO LOCATION MAP
  // ============================================================

  Widget _noLocationMap() {
    return Container(
      alignment: Alignment.center,

      padding: const EdgeInsets.all(26),

      color: LogisticsColors.background,

      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,

        children: [
          Container(
            height: 70,
            width: 70,

            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,

              shape: BoxShape.circle,
            ),

            child: const Icon(
              Icons.location_off_outlined,

              size: 34,

              color: LogisticsColors.primary,
            ),
          ),

          const SizedBox(height: 14),

          const Text(
            'Waiting for GPS Data',

            style: LogisticsStyle.sectionTitle,
          ),

          const SizedBox(height: 6),

          const Text(
            'The map will display parcel or rider markers once latitude and longitude are available from the server.',

            textAlign: TextAlign.center,

            style: LogisticsStyle.small,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // SELECTED PARCEL CARD
  // ============================================================

  Widget _selectedParcelCard(ParcelModel parcel) {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.all(16),

      decoration: LogisticsStyle.elevatedCard,

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Row(
            children: [
              Container(
                height: 44,
                width: 44,

                decoration: BoxDecoration(
                  color: LogisticsColors.lightPurple,

                  borderRadius: BorderRadius.circular(13),
                ),

                child: const Icon(
                  Icons.local_shipping_outlined,

                  color: LogisticsColors.primary,
                ),
              ),

              const SizedBox(width: 12),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Text(
                      parcel.trackingNumber,

                      style: LogisticsStyle.cardTitle,
                    ),

                    const SizedBox(height: 3),

                    Text(parcel.customerName, style: LogisticsStyle.small),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 14),

          _detailRow('Status', parcel.status),

          _detailRow(
            'Rider',
            parcel.riderName.trim().isEmpty ? 'Not assigned' : parcel.riderName,
          ),

          _detailRow(
            'Coordinates',
            '${parcel.latitude.toStringAsFixed(6)}, ${parcel.longitude.toStringAsFixed(6)}',
          ),

          _detailRow('Delivery', parcel.deliveryAddress),

          const SizedBox(height: 12),

          SizedBox(
            width: double.infinity,

            child: ElevatedButton.icon(
              onPressed: () {
                _openParcel(parcel);
              },

              icon: const Icon(Icons.inventory_2_outlined),

              label: const Text('View Parcel Information'),

              style: LogisticsStyle.primaryButton,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PARCEL LIST CARD
  // ============================================================

  Widget _parcelListCard(ParcelModel parcel) {
    final bool selected = _selectedParcel?.id == parcel.id;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),

      child: Material(
        color: Colors.transparent,

        child: InkWell(
          borderRadius: BorderRadius.circular(18),

          onTap: () {
            _selectParcel(parcel);
          },

          child: Container(
            padding: const EdgeInsets.all(15),

            decoration: BoxDecoration(
              color: LogisticsColors.white,

              borderRadius: BorderRadius.circular(18),

              border: Border.all(
                color: selected
                    ? LogisticsColors.primary
                    : LogisticsColors.border,

                width: selected ? 2 : 1,
              ),
            ),

            child: Row(
              children: [
                Container(
                  height: 44,
                  width: 44,

                  decoration: BoxDecoration(
                    color: LogisticsColors.lightPurple,

                    borderRadius: BorderRadius.circular(13),
                  ),

                  child: const Icon(
                    Icons.location_on_outlined,

                    color: LogisticsColors.primary,
                  ),
                ),

                const SizedBox(width: 12),

                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      Text(
                        parcel.trackingNumber,

                        maxLines: 1,

                        overflow: TextOverflow.ellipsis,

                        style: LogisticsStyle.cardTitle,
                      ),

                      const SizedBox(height: 4),

                      Text(
                        parcel.customerName,

                        maxLines: 1,

                        overflow: TextOverflow.ellipsis,

                        style: LogisticsStyle.small,
                      ),

                      const SizedBox(height: 4),

                      Text(parcel.status, style: LogisticsStyle.small),
                    ],
                  ),
                ),

                const Icon(
                  Icons.chevron_right_rounded,

                  color: LogisticsColors.textGrey,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // MAP BUTTON
  // ============================================================

  Widget _mapButton({required IconData icon, required VoidCallback onTap}) {
    return Material(
      color: Colors.white,

      borderRadius: BorderRadius.circular(12),

      elevation: 2,

      child: InkWell(
        onTap: onTap,

        borderRadius: BorderRadius.circular(12),

        child: SizedBox(
          height: 42,
          width: 42,

          child: Icon(icon, color: LogisticsColors.primary, size: 21),
        ),
      ),
    );
  }

  // ============================================================
  // DETAIL ROW
  // ============================================================

  Widget _detailRow(String title, String value) {
    final String display = value.trim().isEmpty
        ? 'Not available'
        : value.trim();

    return Padding(
      padding: const EdgeInsets.only(bottom: 7),

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          SizedBox(width: 92, child: Text(title, style: LogisticsStyle.small)),

          const SizedBox(width: 8),

          Expanded(child: Text(display, style: LogisticsStyle.cardTitle)),
        ],
      ),
    );
  }

  // ============================================================
  // EMPTY CARD
  // ============================================================

  Widget _emptyCard({
    required String title,
    required String message,
    required IconData icon,
  }) {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.all(22),

      decoration: LogisticsStyle.card,

      child: Column(
        children: [
          Icon(icon, color: LogisticsColors.textGrey, size: 38),

          const SizedBox(height: 10),

          Text(title, style: LogisticsStyle.cardTitle),

          const SizedBox(height: 5),

          Text(
            message,

            textAlign: TextAlign.center,

            style: LogisticsStyle.small,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // ERROR CARD
  // ============================================================

  Widget _errorCard() {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.all(14),

      decoration: BoxDecoration(
        color: LogisticsColors.danger.withValues(alpha: 0.07),

        borderRadius: BorderRadius.circular(14),

        border: Border.all(
          color: LogisticsColors.danger.withValues(alpha: 0.25),
        ),
      ),

      child: Row(
        children: [
          const Icon(
            Icons.error_outline_rounded,

            color: LogisticsColors.danger,
          ),

          const SizedBox(width: 10),

          Expanded(
            child: Text(
              _errorMessage ?? 'Unable to load tracking.',

              style: LogisticsStyle.small,
            ),
          ),

          IconButton(
            onPressed: () {
              _loadTracking();
            },

            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
    );
  }
}
