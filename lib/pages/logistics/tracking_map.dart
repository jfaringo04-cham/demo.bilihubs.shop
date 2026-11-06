import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../models/parcel_model.dart';
import '../../services/api_services.dart';
import '../../styles/logistics_style.dart';
import '../../utils/date_formatter.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_loading_state.dart';
import '../../widgets/status_chip.dart';

import 'parcel_information.dart';

class TrackingMap extends StatefulWidget {
  const TrackingMap({super.key});

  @override
  State<TrackingMap> createState() => _TrackingMapState();
}

class _TrackingMapState extends State<TrackingMap> with WidgetsBindingObserver {
  static const Duration _requestTimeout = Duration(seconds: 25);
  static const Duration _refreshInterval = Duration(seconds: 5);

  final MapController _mapController = MapController();

  Timer? _refreshTimer;

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isFetching = false;
  bool _hasLoaded = false;
  bool _mapReady = false;
  bool _isForeground = true;

  String? _errorMessage;

  List<ParcelModel> _parcels = <ParcelModel>[];
  ParcelModel? _selectedParcel;

  DateTime? _lastUpdated;

  int _requestId = 0;

  @override
  void initState() {
    super.initState();

    WidgetsBinding.instance.addObserver(this);

    _loadTracking();
    _startAutoRefresh();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);

    _refreshTimer?.cancel();
    _requestId++;

    _mapController.dispose();

    super.dispose();
  }

  // ============================================================
  // APP LIFECYCLE
  // ============================================================

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    switch (state) {
      case AppLifecycleState.resumed:
        _isForeground = true;

        _startAutoRefresh();

        if (_hasLoaded) {
          _loadTracking(silent: true);
        }
        break;

      case AppLifecycleState.inactive:
      case AppLifecycleState.paused:
      case AppLifecycleState.hidden:
      case AppLifecycleState.detached:
        _isForeground = false;
        _refreshTimer?.cancel();
        _refreshTimer = null;
        break;
    }
  }

  void _startAutoRefresh() {
    _refreshTimer?.cancel();

    if (!_isForeground) return;

    _refreshTimer = Timer.periodic(_refreshInterval, (_) {
      if (!mounted || !_isForeground || _isFetching) {
        return;
      }

      _loadTracking(silent: true);
    });
  }

  // ============================================================
  // LOAD TRACKING DATA
  // ============================================================

  Future<void> _loadTracking({bool silent = false}) async {
    if (!mounted || _isFetching) return;

    final int requestId = ++_requestId;

    setState(() {
      _isFetching = true;
      _isLoading = !_hasLoaded;
      _isRefreshing = _hasLoaded && !silent;

      if (!silent) {
        _errorMessage = null;
      }
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.getLogisticsShipments(perPage: 100)
              .timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) return;

      if (result['success'] != true) {
        _setLoadError(
          requestId,
          _resultMessage(
            result,
            'Unable to retrieve parcel tracking information.',
          ),
        );
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

          if (parcel.id > 0 && parcel.isActive) {
            loaded.add(parcel);
          }
        } catch (error) {
          debugPrint('Tracking parcel parsing error: $error');
        }
      }

      loaded.sort(_compareParcels);

      ParcelModel? updatedSelection;

      if (_selectedParcel != null) {
        for (final ParcelModel parcel in loaded) {
          if (parcel.id == _selectedParcel!.id &&
              _hasTrackingLocation(parcel)) {
            updatedSelection = parcel;
            break;
          }
        }
      }

      if (!mounted || requestId != _requestId) return;

      setState(() {
        _parcels = loaded;
        _selectedParcel = updatedSelection;

        _hasLoaded = true;
        _isLoading = false;
        _isRefreshing = false;
        _isFetching = false;

        _lastUpdated = DateTime.now();
        _errorMessage = null;
      });
    } on TimeoutException {
      _setLoadError(
        requestId,
        'Tracking request timed out. '
        'The previous coordinates are still displayed '
        'when available.',
      );
    } catch (error) {
      debugPrint('Logistics tracking error: $error');

      _setLoadError(
        requestId,
        'Unable to connect to the BiliHub server. '
        'Please check your connection and retry.',
      );
    }
  }

  void _setLoadError(int requestId, String message) {
    if (!mounted || requestId != _requestId) return;

    setState(() {
      _isLoading = false;
      _isRefreshing = false;
      _isFetching = false;
      _errorMessage = message;
    });
  }

  String _resultMessage(Map<String, dynamic> result, String fallback) {
    final String message = result['message']?.toString().trim() ?? '';

    return message.isNotEmpty ? message : fallback;
  }

  // ============================================================
  // SHIPMENT RESPONSE
  // ============================================================

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }

    return <String, dynamic>{};
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

  List<Map<String, dynamic>> _extractShipmentMaps(Map<String, dynamic> result) {
    for (final String key in ['data', 'shipments', 'parcels']) {
      if (result[key] is List) {
        return _toMapList(result[key]);
      }
    }

    final Map<String, dynamic> data = _asMap(result['data']);

    for (final String key in ['data', 'shipments', 'parcels']) {
      if (data[key] is List) {
        return _toMapList(data[key]);
      }
    }

    return <Map<String, dynamic>>[];
  }

  // ============================================================
  // NORMALIZE SHIPMENT
  // ============================================================

  Map<String, dynamic> _normalizeShipment(Map<String, dynamic> raw) {
    final Map<String, dynamic> data = Map<String, dynamic>.from(raw);

    final Map<String, dynamic> nested = _asMap(data['shipment']);

    if (nested.isNotEmpty) {
      data.addAll(nested);
    }

    final Map<String, dynamic> order = _asMap(data['order']);

    data['customer_name'] ??=
        order['customer_name'] ?? order['buyer_name'] ?? order['name'];

    data['customer_phone'] ??= order['customer_phone'] ?? order['phone'];

    data['delivery_address'] ??= order['delivery_address'] ?? order['address'];

    data['order_date'] ??= order['order_date'] ?? order['created_at'];

    final Map<String, dynamic> rider = _asMap(data['rider']);

    data['rider_id'] ??= rider['id'];
    data['rider_name'] ??= rider['name'];

    data['rider_latitude'] ??= rider['latitude'] ?? rider['lat'];

    data['rider_longitude'] ??=
        rider['longitude'] ?? rider['lng'] ?? rider['lon'];

    final Map<String, dynamic> linehaulRider = _asMap(data['linehaul_rider']);

    data['linehaul_rider_id'] ??= linehaulRider['id'];

    data['linehaul_rider_name'] ??= linehaulRider['name'];

    data['rider_latitude'] ??=
        linehaulRider['latitude'] ?? linehaulRider['lat'];

    data['rider_longitude'] ??=
        linehaulRider['longitude'] ??
        linehaulRider['lng'] ??
        linehaulRider['lon'];

    final Map<String, dynamic> originHub = _asMap(data['origin_hub']);

    data['origin_hub_id'] ??= originHub['id'];
    data['origin_hub_name'] ??= originHub['name'];

    final Map<String, dynamic> destinationHub = _asMap(data['destination_hub']);

    data['destination_hub_id'] ??= destinationHub['id'];

    data['destination_hub_name'] ??= destinationHub['name'];

    final Map<String, dynamic> hub = _asMap(data['hub']);

    data['current_hub_id'] ??= hub['id'];
    data['current_hub_name'] ??= hub['name'];

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
  // SORT BY ACTIVITY DATE
  // ============================================================

  DateTime? _activityDate(ParcelModel parcel) {
    for (final String raw in [
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
    ]) {
      final String text = raw.trim();

      if (text.isEmpty || text.toLowerCase() == 'null') {
        continue;
      }

      final DateTime? date = DateFormatter.value(text);

      if (date != null) return date;
    }

    return null;
  }

  int _compareParcels(ParcelModel a, ParcelModel b) {
    final DateTime? dateA = _activityDate(a);
    final DateTime? dateB = _activityDate(b);

    if (dateA != null && dateB != null) {
      final int comparison = dateB.compareTo(dateA);

      if (comparison != 0) return comparison;
    } else if (dateA != null) {
      return -1;
    } else if (dateB != null) {
      return 1;
    }

    return b.id.compareTo(a.id);
  }

  // ============================================================
  // GPS VALIDATION
  // ============================================================

  bool _validCoordinates(double latitude, double longitude) {
    if (!latitude.isFinite || !longitude.isFinite) {
      return false;
    }

    if (latitude < -90 ||
        latitude > 90 ||
        longitude < -180 ||
        longitude > 180) {
      return false;
    }

    // Treat default (0,0) coordinates as unavailable.
    if (latitude == 0 && longitude == 0) {
      return false;
    }

    return true;
  }

  bool _hasRiderGps(ParcelModel parcel) {
    return parcel.hasRiderLocation &&
        _validCoordinates(parcel.riderLatitude, parcel.riderLongitude);
  }

  bool _hasShipmentGps(ParcelModel parcel) {
    return parcel.hasLocation &&
        _validCoordinates(parcel.latitude, parcel.longitude);
  }

  bool _hasTrackingLocation(ParcelModel parcel) {
    return _hasRiderGps(parcel) || _hasShipmentGps(parcel);
  }

  LatLng _trackingPoint(ParcelModel parcel) {
    if (_hasRiderGps(parcel)) {
      return LatLng(parcel.riderLatitude, parcel.riderLongitude);
    }

    return LatLng(parcel.latitude, parcel.longitude);
  }

  String _coordinateSource(ParcelModel parcel) {
    if (_hasRiderGps(parcel)) {
      if (parcel.isLinehaulAssignment || parcel.isLinehaulInTransit) {
        return 'Line-Haul Rider GPS';
      }

      return 'Rider GPS';
    }

    if (_hasShipmentGps(parcel)) {
      return 'Shipment Coordinates';
    }

    return 'GPS unavailable';
  }

  List<ParcelModel> get _withLocation {
    return _parcels.where(_hasTrackingLocation).toList();
  }

  LatLng get _initialCenter {
    final List<ParcelModel> located = _withLocation;

    if (located.isNotEmpty) {
      return _trackingPoint(located.first);
    }

    return const LatLng(14.2691, 121.4113);
  }

  // ============================================================
  // SELECT / FOCUS PARCEL
  // ============================================================

  void _selectParcel(ParcelModel parcel) {
    if (!_hasTrackingLocation(parcel)) return;

    setState(() {
      _selectedParcel = parcel;
    });

    if (_mapReady) {
      _mapController.move(_trackingPoint(parcel), 16);
    }
  }

  void _focusFirstParcel() {
    final List<ParcelModel> located = _withLocation;

    if (located.isEmpty) return;

    _selectParcel(located.first);
  }

  void _changeZoom(double difference) {
    if (!_mapReady) return;

    final double target = (_mapController.camera.zoom + difference)
        .clamp(4.0, 19.0)
        .toDouble();

    _mapController.move(_mapController.camera.center, target);
  }

  // ============================================================
  // OPEN PARCEL INFORMATION
  // ============================================================

  Future<void> _openParcel(ParcelModel parcel) async {
    _refreshTimer?.cancel();
    _refreshTimer = null;

    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return ParcelInformation(parcel: parcel);
        },
      ),
    );

    if (!mounted) return;

    await _loadTracking();

    if (!mounted) return;

    _startAutoRefresh();
  }

  // ============================================================
  // DISPLAY HELPERS
  // ============================================================

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

  String _display(String value, {String fallback = 'Not available'}) {
    final String text = value.trim();

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return fallback;
    }

    return text;
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

  String get _lastUpdatedText {
    final DateTime? date = _lastUpdated;

    if (date == null) {
      return 'Not updated yet';
    }

    final String hour = date.hour.toString().padLeft(2, '0');

    final String minute = date.minute.toString().padLeft(2, '0');

    final String second = date.second.toString().padLeft(2, '0');

    return 'Last refreshed $hour:$minute:$second';
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final List<ParcelModel> located = _withLocation;

    final bool hasData = _hasLoaded;

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
              'Latest reported parcel locations',
              style: LogisticsStyle.small,
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Refresh tracking',
            onPressed: _isFetching ? null : () => _loadTracking(),
            icon: _isRefreshing
                ? const SizedBox(
                    height: 19,
                    width: 19,
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
        ],
      ),
      body: SafeArea(
        top: false,
        child: RefreshIndicator(
          color: LogisticsColors.primary,
          onRefresh: () => _loadTracking(),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 30),
            children: [
              if (_isLoading && !hasData) ...[
                const SizedBox(height: 115),
                const AppLoadingState(
                  message: 'Loading parcel tracking...',
                  color: LogisticsColors.primary,
                ),
              ] else if (!hasData) ...[
                const SizedBox(height: 75),
                AppErrorState(
                  title: 'Tracking Unavailable',
                  message: _errorMessage ?? 'Unable to load active shipments.',
                  color: LogisticsColors.primary,
                  onRetry: () => _loadTracking(),
                ),
              ] else ...[
                if (_isRefreshing) ...[
                  const LinearProgressIndicator(
                    minHeight: 2,
                    color: LogisticsColors.primary,
                  ),
                  const SizedBox(height: 12),
                ],

                if (_errorMessage != null) ...[
                  _warningCard(),
                  const SizedBox(height: 12),
                ],

                _summaryCard(located),

                const SizedBox(height: 16),

                _mapCard(located),

                if (_selectedParcel != null &&
                    _hasTrackingLocation(_selectedParcel!)) ...[
                  const SizedBox(height: 16),
                  _selectedParcelCard(_selectedParcel!),
                ],

                const SizedBox(height: 22),

                _parcelListHeader(located.length),

                const SizedBox(height: 12),

                if (_parcels.isEmpty)
                  const AppEmptyState(
                    title: 'No Active Parcels',
                    message:
                        'There are currently no active '
                        'shipments available for tracking.',
                    icon: Icons.inventory_2_outlined,
                    color: LogisticsColors.primary,
                  )
                else if (located.isEmpty)
                  const AppEmptyState(
                    title: 'No GPS Coordinates Yet',
                    message:
                        'Active shipments were loaded, but '
                        'no usable rider or shipment '
                        'coordinates were provided.',
                    icon: Icons.location_off_outlined,
                    color: LogisticsColors.primary,
                  )
                else
                  ...located.map(_parcelListCard),
              ],
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // REFRESH WARNING
  // ============================================================

  Widget _warningCard() {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: LogisticsStyle.card,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.wifi_off_rounded,
            color: LogisticsColors.warning,
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Showing previously loaded data. '
              '${_errorMessage ?? 'Refresh failed.'}',
              style: LogisticsStyle.small,
            ),
          ),
          TextButton(
            onPressed: _isFetching ? null : () => _loadTracking(),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // SUMMARY
  // ============================================================

  Widget _summaryCard(List<ParcelModel> located) {
    final int riderGps = located.where(_hasRiderGps).length;

    final int shipmentGps = located
        .where((ParcelModel p) => !_hasRiderGps(p) && _hasShipmentGps(p))
        .length;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: LogisticsStyle.card,
      child: Row(
        children: [
          Container(
            height: 48,
            width: 48,
            decoration: LogisticsStyle.iconContainer(),
            child: const Icon(
              Icons.location_on_outlined,
              color: LogisticsColors.primary,
              size: 25,
            ),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Parcel Location Overview',
                  style: LogisticsStyle.sectionTitle,
                ),
                const SizedBox(height: 5),
                Text(
                  '${located.length} of ${_parcels.length} '
                  'loaded active parcels have coordinates.',
                  style: LogisticsStyle.small,
                ),
                const SizedBox(height: 5),
                Text(
                  '$riderGps rider GPS • '
                  '$shipmentGps shipment coordinates',
                  style: LogisticsStyle.small,
                ),
                const SizedBox(height: 5),
                Text(_lastUpdatedText, style: LogisticsStyle.small),
                const SizedBox(height: 4),
                const Text(
                  'Checks for updates every 5 seconds '
                  'while this screen is active. '
                  'GPS accuracy and freshness depend '
                  'on the server data.',
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

  Widget _mapCard(List<ParcelModel> located) {
    return Container(
      height: 385,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: LogisticsColors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: LogisticsColors.border),
      ),
      child: located.isEmpty
          ? const Center(
              child: AppEmptyState(
                title: 'Waiting for GPS Data',
                message:
                    'Rider coordinates will be used first. '
                    'Shipment coordinates are the fallback.',
                icon: Icons.location_off_outlined,
                color: LogisticsColors.primary,
                compact: true,
              ),
            )
          : Stack(
              children: [
                FlutterMap(
                  mapController: _mapController,
                  options: MapOptions(
                    initialCenter: _initialCenter,
                    initialZoom: 13,
                    minZoom: 4,
                    maxZoom: 19,
                    onMapReady: () {
                      _mapReady = true;
                    },
                  ),
                  children: [
                    TileLayer(
                      urlTemplate:
                          'https://tile.openstreetmap.org/'
                          '{z}/{x}/{y}.png',
                      userAgentPackageName: 'com.bilihub.mobile',
                    ),
                    MarkerLayer(markers: located.map(_parcelMarker).toList()),
                  ],
                ),

                Positioned(
                  top: 12,
                  right: 12,
                  child: Column(
                    children: [
                      _mapButton(
                        icon: Icons.add_rounded,
                        tooltip: 'Zoom in',
                        onTap: () => _changeZoom(1),
                      ),
                      const SizedBox(height: 8),
                      _mapButton(
                        icon: Icons.remove_rounded,
                        tooltip: 'Zoom out',
                        onTap: () => _changeZoom(-1),
                      ),
                      const SizedBox(height: 8),
                      _mapButton(
                        icon: Icons.center_focus_strong,
                        tooltip: 'Focus first parcel',
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
                    child: const Text(
                      'Latest server coordinates',
                      style: TextStyle(
                        color: LogisticsColors.textDark,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  Marker _parcelMarker(ParcelModel parcel) {
    final bool selected = _selectedParcel?.id == parcel.id;

    final bool riderGps = _hasRiderGps(parcel);

    return Marker(
      point: _trackingPoint(parcel),
      width: selected ? 60 : 50,
      height: selected ? 60 : 50,
      child: GestureDetector(
        onTap: () => _selectParcel(parcel),
        child: Container(
          decoration: BoxDecoration(
            color: selected ? LogisticsColors.primary : Colors.white,
            shape: BoxShape.circle,
            border: Border.all(color: LogisticsColors.primary, width: 3),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.16),
                blurRadius: 8,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Icon(
            riderGps
                ? Icons.delivery_dining_rounded
                : Icons.inventory_2_rounded,
            color: selected ? Colors.white : LogisticsColors.primary,
            size: selected ? 27 : 23,
          ),
        ),
      ),
    );
  }

  Widget _mapButton({
    required IconData icon,
    required String tooltip,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.white,
      elevation: 2,
      borderRadius: BorderRadius.circular(12),
      child: IconButton(
        tooltip: tooltip,
        onPressed: onTap,
        icon: Icon(icon, color: LogisticsColors.primary, size: 21),
      ),
    );
  }

  // ============================================================
  // SELECTED PARCEL
  // ============================================================

  Widget _selectedParcelCard(ParcelModel parcel) {
    final LatLng location = _trackingPoint(parcel);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: LogisticsStyle.elevatedCard,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.location_on_rounded,
                color: LogisticsColors.primary,
                size: 27,
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  _display(
                    parcel.trackingNumber,
                    fallback: 'Tracking unavailable',
                  ),
                  style: LogisticsStyle.sectionTitle,
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          StatusChip(status: parcel.status),

          const SizedBox(height: 12),

          _detail(
            'Customer',
            _display(parcel.customerName),
            Icons.person_outline_rounded,
          ),

          _detail(
            'GPS Source',
            _coordinateSource(parcel),
            Icons.gps_fixed_rounded,
          ),

          _detail(
            'Rider',
            parcel.riderName.trim().isNotEmpty
                ? parcel.riderName
                : _display(parcel.linehaulRiderName, fallback: 'Not assigned'),
            Icons.delivery_dining_outlined,
          ),

          _detail(
            'Coordinates',
            '${location.latitude.toStringAsFixed(6)}, '
                '${location.longitude.toStringAsFixed(6)}',
            Icons.my_location_rounded,
          ),

          if (parcel.currentHubName.trim().isNotEmpty)
            _detail(
              'Current Hub',
              parcel.currentHubName,
              Icons.warehouse_outlined,
            ),

          if (parcel.originHubName.trim().isNotEmpty ||
              parcel.destinationHubName.trim().isNotEmpty)
            _detail('Hub Route', _routeText(parcel), Icons.route_outlined),

          if (parcel.routingStatus.trim().isNotEmpty)
            _detail(
              'Routing Status',
              _formatStatus(parcel.routingStatus),
              Icons.hub_outlined,
            ),

          if (parcel.linehaulStatus.trim().isNotEmpty)
            _detail(
              'Line-Haul Status',
              _formatStatus(parcel.linehaulStatus),
              Icons.multiple_stop_rounded,
            ),

          _detail(
            'Delivery Address',
            _display(parcel.deliveryAddress),
            Icons.location_on_outlined,
          ),

          const SizedBox(height: 12),

          SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton.icon(
              onPressed: () => _openParcel(parcel),
              icon: const Icon(Icons.inventory_2_outlined),
              label: const Text('View Parcel Information'),
              style: LogisticsStyle.primaryButton,
            ),
          ),
        ],
      ),
    );
  }

  Widget _detail(String label, String value, IconData icon) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 9),
      child: AppInfoRow(
        label: label,
        value: value,
        icon: icon,
        iconColor: LogisticsColors.primary,
        compact: true,
      ),
    );
  }

  // ============================================================
  // TRACKED PARCELS
  // ============================================================

  Widget _parcelListHeader(int count) {
    return Row(
      children: [
        const Expanded(
          child: Text('Tracked Parcels', style: LogisticsStyle.sectionTitle),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
          decoration: BoxDecoration(
            color: LogisticsColors.lightPurple,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            '$count',
            style: const TextStyle(
              color: LogisticsColors.primary,
              fontWeight: FontWeight.w800,
              fontSize: 12,
            ),
          ),
        ),
      ],
    );
  }

  Widget _parcelListCard(ParcelModel parcel) {
    final bool selected = _selectedParcel?.id == parcel.id;

    final bool riderGps = _hasRiderGps(parcel);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => _selectParcel(parcel),
          borderRadius: BorderRadius.circular(16),
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: LogisticsColors.white,
              borderRadius: BorderRadius.circular(16),
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
                  height: 43,
                  width: 43,
                  decoration: BoxDecoration(
                    color: LogisticsColors.lightPurple,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    riderGps
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
                        _display(
                          parcel.trackingNumber,
                          fallback: 'Tracking unavailable',
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: LogisticsStyle.cardTitle,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _display(parcel.customerName),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: LogisticsStyle.small,
                      ),
                      const SizedBox(height: 5),
                      StatusChip(status: parcel.status),
                      const SizedBox(height: 5),
                      Text(
                        _coordinateSource(parcel),
                        style: LogisticsStyle.small,
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
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
}
