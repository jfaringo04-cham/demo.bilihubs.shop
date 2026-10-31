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
    if (!mounted) {
      return;
    }

    if (silent) {
      if (_isRefreshing || _isLoading) {
        return;
      }

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
              : 'Unable to load parcel tracking.';
        });

        return;
      }

      final List<Map<String, dynamic>> shipmentMaps = _extractShipmentMaps(
        result,
      );

      final List<ParcelModel> loaded = <ParcelModel>[];

      for (final Map<String, dynamic> item in shipmentMaps) {
        try {
          final ParcelModel parcel = ParcelModel.fromJson(
            _normalizeShipment(item),
          );

          if (parcel.isActive) {
            loaded.add(parcel);
          }
        } catch (error) {
          debugPrint('Unable to parse parcel for tracking: $error');
        }
      }

      loaded.sort((ParcelModel a, ParcelModel b) {
        final DateTime? aDate = _activityDate(a);
        final DateTime? bDate = _activityDate(b);

        if (aDate != null && bDate != null) {
          return bDate.compareTo(aDate);
        }

        if (aDate != null) {
          return -1;
        }

        if (bDate != null) {
          return 1;
        }

        return b.id.compareTo(a.id);
      });

      ParcelModel? updatedSelectedParcel;

      final ParcelModel? selected = _selectedParcel;

      if (selected != null) {
        for (final ParcelModel parcel in loaded) {
          if (parcel.id == selected.id) {
            updatedSelectedParcel = parcel;

            break;
          }
        }
      }

      setState(() {
        _parcels = loaded;

        if (updatedSelectedParcel != null) {
          _selectedParcel = updatedSelectedParcel;
        } else if (selected != null &&
            !loaded.any((ParcelModel parcel) => parcel.id == selected.id)) {
          _selectedParcel = null;
        }

        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = null;
      });
    } catch (error) {
      debugPrint('Logistics tracking error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _isLoading = false;
        _isRefreshing = false;

        _errorMessage =
            'Unable to load parcel tracking. '
            'Please try again.';
      });
    }
  }

  // ============================================================
  // EXTRACT SHIPMENTS
  // ============================================================

  List<Map<String, dynamic>> _extractShipmentMaps(Map<String, dynamic> result) {
    for (final String key in <String>['data', 'shipments', 'parcels']) {
      final List<Map<String, dynamic>> list = _toMapList(result[key]);

      if (list.isNotEmpty) {
        return list;
      }
    }

    final dynamic rawData = result['data'];

    if (rawData is Map) {
      final Map<String, dynamic> data = Map<String, dynamic>.from(rawData);

      for (final String key in <String>['data', 'shipments', 'parcels']) {
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

    final dynamic shipment = data['shipment'];

    if (shipment is Map) {
      data.addAll(Map<String, dynamic>.from(shipment));
    }

    final dynamic order = data['order'];

    if (order is Map) {
      data['customer_name'] ??=
          order['customer_name'] ?? order['buyer_name'] ?? order['name'];

      data['customer_phone'] ??= order['customer_phone'] ?? order['phone'];

      data['delivery_address'] ??=
          order['delivery_address'] ?? order['address'];

      data['order_date'] ??= order['order_date'] ?? order['created_at'];
    }

    final dynamic rider = data['rider'];

    if (rider is Map) {
      data['rider_id'] ??= rider['id'];

      data['rider_name'] ??= rider['name'];

      data['rider_latitude'] ??= rider['latitude'] ?? rider['lat'];

      data['rider_longitude'] ??=
          rider['longitude'] ?? rider['lng'] ?? rider['lon'];
    }

    final dynamic linehaulRider = data['linehaul_rider'];

    if (linehaulRider is Map) {
      data['linehaul_rider_id'] ??= linehaulRider['id'];

      data['linehaul_rider_name'] ??= linehaulRider['name'];

      data['rider_latitude'] ??=
          linehaulRider['latitude'] ?? linehaulRider['lat'];

      data['rider_longitude'] ??=
          linehaulRider['longitude'] ??
          linehaulRider['lng'] ??
          linehaulRider['lon'];
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

    data['tracking_number'] ??= data['tracking_code'] ?? data['tracking'] ?? '';

    data['customer_name'] ??= '';

    data['customer_phone'] ??= '';

    data['delivery_address'] ??= '';

    data['pickup_address'] ??= data['seller_address'] ?? '';

    data['rider_name'] ??= '';

    data['status'] ??=
        data['shipment_status'] ?? data['delivery_status'] ?? 'pending';

    data['order_date'] ??= data['created_at'] ?? '';

    data['delivery_earnings'] ??= data['earnings'] ?? 0;

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
      parcel.transferDispatchedAt,
      parcel.linehaulAssignedAt,
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
  // TRACKABLE PARCELS
  // ============================================================

  bool _hasTrackingLocation(ParcelModel parcel) {
    return parcel.hasRiderLocation || parcel.hasLocation;
  }

  List<ParcelModel> get _withLocation {
    return _parcels.where((ParcelModel parcel) {
      return _hasTrackingLocation(parcel);
    }).toList();
  }

  // ============================================================
  // TRACKING POINT
  // ============================================================

  LatLng _trackingPoint(ParcelModel parcel) {
    if (parcel.hasRiderLocation) {
      return LatLng(parcel.riderLatitude, parcel.riderLongitude);
    }

    return LatLng(parcel.latitude, parcel.longitude);
  }

  String _coordinateSource(ParcelModel parcel) {
    if (parcel.hasRiderLocation) {
      if (parcel.isLinehaulAssignment || parcel.isLinehaulInTransit) {
        return 'Line-Haul Rider GPS';
      }

      return 'Rider GPS';
    }

    if (parcel.hasLocation) {
      return 'Shipment GPS';
    }

    return 'No GPS';
  }

  // ============================================================
  // MAP CENTER
  // ============================================================

  LatLng get _initialCenter {
    final List<ParcelModel> located = _withLocation;

    if (located.isNotEmpty) {
      return _trackingPoint(located.first);
    }

    return const LatLng(14.2691, 121.4113);
  }

  // ============================================================
  // SELECT PARCEL
  // ============================================================

  void _selectParcel(ParcelModel parcel) {
    if (!_hasTrackingLocation(parcel)) {
      return;
    }

    setState(() {
      _selectedParcel = parcel;
    });

    _mapController.move(_trackingPoint(parcel), 16);
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

    if (!mounted) {
      return;
    }

    await _loadTracking();
  }

  // ============================================================
  // FOCUS FIRST MARKER
  // ============================================================

  void _focusFirstParcel() {
    final List<ParcelModel> parcels = _withLocation;

    if (parcels.isEmpty) {
      return;
    }

    _selectParcel(parcels.first);
  }

  // ============================================================
  // ROUTING HELPERS
  // ============================================================

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

    return 'Hub route not available';
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
        .where((String word) => word.isNotEmpty)
        .map(
          (String word) =>
              '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}',
        )
        .join(' ');
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
        backgroundColor: LogisticsColors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: LogisticsColors.textDark),
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Parcel Tracking', style: LogisticsStyle.title),
            SizedBox(height: 2),
            Text(
              'Live rider and shipment locations',
              style: LogisticsStyle.small,
            ),
          ],
        ),
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
              onPressed: _isLoading
                  ? null
                  : () {
                      _loadTracking();
                    },
              icon: const Icon(
                Icons.refresh_rounded,
                color: LogisticsColors.primary,
              ),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () {
          return _loadTracking();
        },
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 30),
          children: [
            if (_isLoading)
              const LinearProgressIndicator(color: LogisticsColors.primary),

            if (_errorMessage != null) ...[
              const SizedBox(height: 12),
              _errorCard(),
            ],

            const SizedBox(height: 16),

            _summaryCard(locatedParcels),

            const SizedBox(height: 16),

            _mapCard(locatedParcels),

            const SizedBox(height: 16),

            if (_selectedParcel != null &&
                _hasTrackingLocation(_selectedParcel!))
              _selectedParcelCard(_selectedParcel!),

            if (_selectedParcel != null) const SizedBox(height: 16),

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
                message:
                    'There are currently no active '
                    'parcels available for tracking.',
                icon: Icons.inventory_2_outlined,
              )
            else if (!_isLoading && locatedParcels.isEmpty)
              _emptyCard(
                title: 'No GPS Coordinates Yet',
                message:
                    'Active parcels were found, but '
                    'neither rider nor shipment GPS '
                    'coordinates are currently available.',
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
  // SUMMARY
  // ============================================================

  Widget _summaryCard(List<ParcelModel> locatedParcels) {
    final int riderGpsCount = locatedParcels
        .where((ParcelModel parcel) => parcel.hasRiderLocation)
        .length;

    final int shipmentGpsCount = locatedParcels
        .where(
          (ParcelModel parcel) =>
              !parcel.hasRiderLocation && parcel.hasLocation,
        )
        .length;

    return Container(
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
                  '${locatedParcels.length} of '
                  '${_parcels.length} active parcels '
                  'currently have tracking coordinates.',
                  style: LogisticsStyle.small,
                ),

                const SizedBox(height: 4),

                Text(
                  '$riderGpsCount rider GPS • '
                  '$shipmentGpsCount shipment GPS',
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
    );
  }

  // ============================================================
  // MAP
  // ============================================================

  Widget _mapCard(List<ParcelModel> locatedParcels) {
    return Container(
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
                      urlTemplate:
                          'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                      userAgentPackageName: 'com.bilihub.mobile',
                    ),

                    MarkerLayer(
                      markers: locatedParcels.map((ParcelModel parcel) {
                        return _parcelMarker(parcel);
                      }).toList(),
                    ),
                  ],
                ),

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
    );
  }

  Marker _parcelMarker(ParcelModel parcel) {
    final bool selected = _selectedParcel?.id == parcel.id;

    final bool riderLocation = parcel.hasRiderLocation;

    return Marker(
      point: _trackingPoint(parcel),
      width: selected ? 60 : 50,
      height: selected ? 60 : 50,
      child: GestureDetector(
        onTap: () {
          _selectParcel(parcel);
        },
        child: Container(
          decoration: BoxDecoration(
            color: selected ? LogisticsColors.primary : Colors.white,
            shape: BoxShape.circle,
            border: Border.all(color: LogisticsColors.primary, width: 3),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.18),
                blurRadius: 8,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Icon(
            riderLocation
                ? Icons.delivery_dining_rounded
                : Icons.inventory_2_rounded,
            color: selected ? Colors.white : LogisticsColors.primary,
            size: selected ? 28 : 23,
          ),
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
            decoration: const BoxDecoration(
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
            'The map will display rider GPS first. '
            'Shipment coordinates are used as fallback '
            'when rider location is unavailable.',
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
    final LatLng point = _trackingPoint(parcel);

    final bool hasRoute =
        parcel.originHubName.trim().isNotEmpty ||
        parcel.destinationHubName.trim().isNotEmpty;

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
                child: Icon(
                  parcel.hasRiderLocation
                      ? Icons.delivery_dining_rounded
                      : Icons.local_shipping_outlined,
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

          _detailRow('Status', _formatStatus(parcel.status)),

          _detailRow('GPS Source', _coordinateSource(parcel)),

          _detailRow(
            'Rider',
            parcel.riderName.trim().isEmpty
                ? parcel.linehaulRiderName.trim().isEmpty
                      ? 'Not assigned'
                      : parcel.linehaulRiderName
                : parcel.riderName,
          ),

          _detailRow(
            'Coordinates',
            '${point.latitude.toStringAsFixed(6)}, '
                '${point.longitude.toStringAsFixed(6)}',
          ),

          if (parcel.currentHubName.trim().isNotEmpty)
            _detailRow('Current Hub', parcel.currentHubName),

          if (hasRoute) _detailRow('Hub Route', _routeText(parcel)),

          if (parcel.routingStatus.trim().isNotEmpty)
            _detailRow('Routing', _formatStatus(parcel.routingStatus)),

          if (parcel.linehaulStatus.trim().isNotEmpty)
            _detailRow('Line Haul', _formatStatus(parcel.linehaulStatus)),

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

    final bool riderLocation = parcel.hasRiderLocation;

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
                  child: Icon(
                    riderLocation
                        ? Icons.delivery_dining_rounded
                        : Icons.location_on_outlined,
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

                      Text(
                        _formatStatus(parcel.status),
                        style: LogisticsStyle.small,
                      ),

                      const SizedBox(height: 3),

                      Text(
                        _coordinateSource(parcel),
                        style: LogisticsStyle.small,
                      ),

                      if (parcel.currentHubName.trim().isNotEmpty) ...[
                        const SizedBox(height: 3),
                        Text(
                          parcel.currentHubName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: LogisticsStyle.small,
                        ),
                      ],
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
          SizedBox(width: 100, child: Text(title, style: LogisticsStyle.small)),

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
        crossAxisAlignment: CrossAxisAlignment.start,
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
            tooltip: 'Retry',
            onPressed: () {
              _loadTracking();
            },
            icon: const Icon(
              Icons.refresh_rounded,
              color: LogisticsColors.primary,
            ),
          ),
        ],
      ),
    );
  }
}
