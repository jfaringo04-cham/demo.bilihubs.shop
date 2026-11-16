import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../../services/api_services.dart';
import '../../services/auth_storage.dart';
import '../../styles/buyer_style.dart';
import '../../utils/date_formatter.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/status_chip.dart';

class OrderTracking extends StatefulWidget {
  final Map<String, dynamic> order;

  const OrderTracking({super.key, required this.order});

  @override
  State<OrderTracking> createState() => _OrderTrackingState();
}

class _OrderTrackingState extends State<OrderTracking> {
  static const Duration _timeout = Duration(seconds: 25);

  late Map<String, dynamic> _order;

  bool _refreshing = false;
  String? _refreshError;

  @override
  void initState() {
    super.initState();
    _order = Map<String, dynamic>.from(widget.order);
  }

  Future<void> _refresh() async {
    if (_refreshing) return;

    final int orderId = int.tryParse(_order['id']?.toString() ?? '') ?? 0;

    if (orderId <= 0) {
      setState(() {
        _refreshError = 'This order has no valid ID.';
      });
      return;
    }

    setState(() {
      _refreshing = true;
      _refreshError = null;
    });

    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        throw const _TrackingException('Session expired. Please log in again.');
      }

      Map<String, dynamic>? newest;

      for (int page = 1; page <= 20; page++) {
        final Uri uri = Uri.parse('${ApiService.baseUrl}/orders').replace(
          queryParameters: <String, String>{'page': '$page', 'per_page': '50'},
        );

        final http.Response response = await http
            .get(
              uri,
              headers: <String, String>{
                'Accept': 'application/json',
                'Authorization': 'Bearer ${token.trim()}',
              },
            )
            .timeout(_timeout);

        if (response.statusCode == 401) {
          throw const _TrackingException(
            'Session expired. Please log in again.',
          );
        }

        if (response.statusCode < 200 || response.statusCode >= 300) {
          throw const _TrackingException(
            'Unable to refresh tracking. Try again.',
          );
        }

        final dynamic decoded = jsonDecode(response.body);

        if (decoded is! Map) {
          throw const _TrackingException('Unexpected tracking response.');
        }

        final dynamic rawData = decoded['data'];

        final dynamic rawList = rawData is List
            ? rawData
            : rawData is Map
            ? (rawData['data'] ?? rawData['orders'])
            : decoded['orders'];

        if (rawList is! List) {
          throw const _TrackingException('Order data is unavailable.');
        }

        for (final dynamic item in rawList) {
          if (item is Map &&
              int.tryParse(item['id']?.toString() ?? '') == orderId) {
            newest = Map<String, dynamic>.from(item);
            break;
          }
        }

        if (newest != null || rawList.isEmpty) {
          break;
        }

        final dynamic rawMeta =
            decoded['meta'] ?? (rawData is Map ? rawData['meta'] : null);

        final int? lastPage = rawMeta is Map
            ? int.tryParse(rawMeta['last_page']?.toString() ?? '')
            : null;

        if (lastPage != null && page >= lastPage) {
          break;
        }

        if (rawList.length < 50 && lastPage == null) {
          break;
        }
      }

      if (!mounted) return;

      if (newest == null) {
        setState(() {
          _refreshError = 'Order was not found in the available history pages.';
        });
      } else {
        setState(() {
          _order = newest!;
        });
      }
    } on TimeoutException {
      if (mounted) {
        setState(() {
          _refreshError = 'Refresh timed out. Please try again.';
        });
      }
    } on _TrackingException catch (error) {
      if (mounted) {
        setState(() {
          _refreshError = error.message;
        });
      }
    } catch (error) {
      debugPrint('Order tracking refresh error: $error');

      if (mounted) {
        setState(() {
          _refreshError = 'Unable to refresh tracking. Check your connection.';
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _refreshing = false;
        });
      }
    }
  }

  List<Map<String, dynamic>> _asMaps(dynamic value) {
    if (value is! List) {
      return <Map<String, dynamic>>[];
    }

    return value
        .whereType<Map>()
        .map((Map item) => Map<String, dynamic>.from(item))
        .toList();
  }

  String _text(dynamic value) {
    final String result = value?.toString().trim() ?? '';

    return result.toLowerCase() == 'null' ? '' : result;
  }

  String _normalize(dynamic value) {
    return _text(value)
        .toLowerCase()
        .replaceAll('-', '_')
        .replaceAll(RegExp(r'\s+'), '_');
  }

  String _display(String value) {
    final String readable = _normalize(value).replaceAll('_', ' ');

    if (readable.isEmpty) {
      return 'Not available';
    }

    return readable
        .split(' ')
        .where((String word) => word.isNotEmpty)
        .map((String word) => '${word[0].toUpperCase()}${word.substring(1)}')
        .join(' ');
  }

  String _date(dynamic value) {
    return DateFormatter.dateTime(value, fallback: '');
  }

  String _firstDate(Map<String, dynamic> map, List<String> keys) {
    for (final String key in keys) {
      final String date = _date(map[key]);

      if (date.isNotEmpty) {
        return date;
      }
    }

    return '';
  }

  bool _hasValue(dynamic value) {
    return value != null &&
        _text(value).isNotEmpty &&
        _text(value) != '0' &&
        _normalize(value) != 'false';
  }

  bool _cancelled(String value) {
    return value == 'cancelled' || value == 'canceled';
  }

  bool _failed(String value) {
    return <String>{
      'failed',
      'delivery_failed',
      'failed_return_at_sorting_center',
      'failed_return_received',
    }.contains(value);
  }

  // Seven tracking steps based on the Web implementation.
  // Sorting and hub details are displayed separately.
  int _stage(String status) {
    switch (status) {
      case 'pending':
      case 'placed':
        return 0;

      case 'confirmed':
        return 1;

      case 'processing':
      case 'preparing':
      case 'assigned':
        return 2;

      case 'ready_for_pickup':
      case 'pickup_requested':
      case 'assigned_to_rider':
        return 3;

      case 'picked_up':
        return 4;

      case 'shipped':
      case 'in_transit':
      case 'at_sorting_center':
      case 'sorting':
      case 'sorted':
      case 'staged':
      case 'scanned':
      case 'received':
      case 'at_origin_hub':
      case 'at_destination_hub':
      case 'linehaul_assigned':
      case 'linehaul_in_transit':
      case 'in_hub_transfer':
      case 'out_for_delivery':
        return 5;

      case 'delivered':
      case 'completed':
        return 6;

      default:
        return -1;
    }
  }

  String _shipmentStatus(
    Map<String, dynamic>? shipment,
    Map<String, dynamic> sellerOrder,
  ) {
    final String fromShipment = _normalize(shipment?['status']);

    if (fromShipment.isNotEmpty) {
      return fromShipment;
    }

    return _normalize(sellerOrder['status']);
  }

  String _logisticsDetail(Map<String, dynamic> shipment, String status) {
    const Map<String, String> descriptions = <String, String>{
      'picked_up': 'The parcel has been picked up by a rider.',
      'at_sorting_center': 'The parcel has reached a sorting center.',
      'sorted': 'Sorting is complete.',
      'sorting': 'The parcel is being sorted.',
      'scanned': 'The parcel was scanned by logistics.',
      'staged': 'The parcel is staged for the next delivery step.',
      'at_origin_hub': 'The parcel is at the origin hub.',
      'at_destination_hub': 'The parcel is at the destination hub.',
      'in_hub_transfer': 'The parcel is moving between hubs.',
      'linehaul_assigned': 'The parcel is assigned for linehaul.',
      'linehaul_in_transit': 'The parcel is moving through linehaul.',
      'out_for_delivery': 'The final-mile rider is delivering the parcel.',
      'delivery_failed': 'A delivery attempt was unsuccessful.',
    };

    return descriptions[status] ??
        (status.isEmpty
            ? 'No shipment status recorded yet.'
            : _display(status));
  }

  List<OrderTrackingStep> _steps({
    required String status,
    required Map<String, dynamic> shipment,
    required bool cancelled,
    required bool failed,
  }) {
    final int stage = _stage(status);

    final String created = _date(_order['ordered_at'] ?? _order['created_at']);

    final String confirmed = _date(_order['confirmed_at']);

    final String ready = _firstDate(shipment, <String>[
      'ready_for_pickup_at',
      'seller_prepared_at',
    ]);

    final String picked = _firstDate(shipment, <String>[
      'picked_up_at',
      'seller_scan_confirmed_at',
      'scanned_at_seller',
    ]);

    final String transited = _firstDate(shipment, <String>[
      'at_sorting_center_at',
      'origin_hub_received_at',
      'linehaul_started_at',
      'destination_hub_received_at',
    ]);

    final String delivered = _firstDate(shipment, <String>['delivered_at']);

    final List<String> labels = <String>[
      'Order Placed',
      'Confirmed',
      'Preparing',
      'Ready for Pickup',
      'Picked Up',
      'In Transit',
      'Delivered',
    ];

    final List<String> subtitles = <String>[
      created.isEmpty ? 'Order was submitted' : 'Ordered on $created',
      confirmed.isEmpty
          ? 'Awaiting seller confirmation'
          : 'Confirmed on $confirmed',
      'Seller prepares the parcel',
      ready.isEmpty ? 'Waiting for the parcel to be ready' : 'Ready on $ready',
      picked.isEmpty
          ? 'Pickup rider receives the parcel'
          : 'Picked up on $picked',
      transited.isEmpty
          ? 'Moving through logistics and delivery hubs'
          : 'Logistics update on $transited',
      delivered.isEmpty
          ? 'Parcel handed to the buyer'
          : 'Delivered on $delivered',
    ];

    final List<bool> verified = <bool>[
      created.isNotEmpty,
      confirmed.isNotEmpty,
      false,
      ready.isNotEmpty || _hasValue(_order['ready_for_pickup']),
      picked.isNotEmpty,
      transited.isNotEmpty,
      delivered.isNotEmpty,
    ];

    return List<OrderTrackingStep>.generate(labels.length, (int index) {
      final bool terminal = cancelled || failed;

      final bool done = terminal
          ? verified[index]
          : (stage >= 0
                ? index < stage || (stage == 6 && index == 6)
                : verified[index]);

      final bool active = !terminal && stage == index && stage != 6;

      return OrderTrackingStep(
        title: labels[index],
        subtitle: subtitles[index],
        done: done,
        active: active,
      );
    });
  }

  Widget _shipmentCard({
    required Map<String, dynamic> sellerOrder,
    required int index,
    required bool parentCancelled,
  }) {
    final dynamic rawShipment = sellerOrder['shipment'];

    final Map<String, dynamic> shipment = rawShipment is Map
        ? Map<String, dynamic>.from(rawShipment)
        : <String, dynamic>{};

    final String shipmentStatus = _shipmentStatus(shipment, sellerOrder);

    final String fallbackStatus = _normalize(_order['status']);

    final String status = shipmentStatus.isNotEmpty
        ? shipmentStatus
        : fallbackStatus;

    final bool isCancelled = parentCancelled || _cancelled(status);

    final bool isFailed = _failed(status);

    final Map<String, dynamic> seller = sellerOrder['seller'] is Map
        ? Map<String, dynamic>.from(sellerOrder['seller'])
        : <String, dynamic>{};

    final String sellerName = _text(seller['name']);

    final String trackingNumber = _text(shipment['tracking_number']);

    final List<OrderTrackingStep> steps = _steps(
      status: status,
      shipment: shipment,
      cancelled: isCancelled,
      failed: isFailed,
    );

    return Container(
      margin: const EdgeInsets.only(bottom: 18),
      padding: const EdgeInsets.all(16),
      decoration: BuyerStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            sellerName.isNotEmpty ? sellerName : 'Parcel $index',
            style: BuyerStyle.cardTitle,
          ),

          const SizedBox(height: 10),

          if (trackingNumber.isNotEmpty)
            AppInfoRow(
              label: 'Tracking Number',
              value: trackingNumber,
              icon: Icons.qr_code_2_rounded,
              iconColor: BuyerColors.primary,
              selectable: true,
            ),

          const SizedBox(height: 10),

          StatusChip(
            status: isCancelled
                ? 'cancelled'
                : (status.isEmpty ? 'pending' : status),
          ),

          const SizedBox(height: 10),

          Text(_logisticsDetail(shipment, status), style: BuyerStyle.small),

          if (isCancelled || isFailed) ...<Widget>[
            const SizedBox(height: 10),

            Text(
              isCancelled
                  ? 'This order or parcel was cancelled.'
                  : 'The delivery attempt was unsuccessful.',
              style: const TextStyle(
                color: BuyerColors.cancelledText,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],

          const SizedBox(height: 18),

          for (int stepIndex = 0; stepIndex < steps.length; stepIndex++)
            _TrackingStepItem(
              step: steps[stepIndex],
              isLast: stepIndex == steps.length - 1,
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final String orderStatus = _normalize(_order['status']);

    final String deliveryStatus = _normalize(_order['delivery_status']);

    final bool parentCancelled = _cancelled(orderStatus);

    final List<Map<String, dynamic>> sellerOrders = _asMaps(
      _order['seller_orders'],
    );

    final String orderNumber = _text(_order['order_number']);

    final String overallStatus = parentCancelled
        ? 'cancelled'
        : (deliveryStatus == 'partially_delivered' ||
              orderStatus == 'partially_delivered')
        ? 'partially_delivered'
        : (deliveryStatus.isNotEmpty ? deliveryStatus : orderStatus);

    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        centerTitle: true,
        title: const Text('Order Tracking', style: BuyerStyle.appBarTitle),
        actions: <Widget>[
          IconButton(
            tooltip: 'Refresh tracking',
            onPressed: _refreshing ? null : _refresh,
            icon: _refreshing
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: RefreshIndicator(
          color: BuyerColors.primary,
          onRefresh: _refresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
            children: <Widget>[
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BuyerStyle.elevatedCard,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    const Text('Overall Order Status', style: BuyerStyle.small),

                    const SizedBox(height: 8),

                    Text(_display(overallStatus), style: BuyerStyle.cardTitle),

                    const SizedBox(height: 10),

                    StatusChip(
                      status: overallStatus.isEmpty ? 'pending' : overallStatus,
                    ),

                    if (orderNumber.isNotEmpty) ...<Widget>[
                      const SizedBox(height: 12),

                      AppInfoRow(
                        label: 'Order Number',
                        value: orderNumber,
                        icon: Icons.receipt_long_outlined,
                        iconColor: BuyerColors.primary,
                        selectable: true,
                      ),
                    ],

                    if (overallStatus == 'partially_delivered') ...<Widget>[
                      const SizedBox(height: 10),

                      const Text(
                        'Some parcels have been delivered; others are still in progress.',
                        style: BuyerStyle.small,
                      ),
                    ],
                  ],
                ),
              ),

              if (_refreshError != null) ...<Widget>[
                const SizedBox(height: 12),

                Text(
                  _refreshError!,
                  style: const TextStyle(color: BuyerColors.cancelledText),
                ),
              ],

              const SizedBox(height: 22),

              const Text('Parcel Tracking', style: BuyerStyle.sectionTitle),

              const SizedBox(height: 7),

              const Text(
                'Each seller shipment has its own progress. Pull down to refresh.',
                style: BuyerStyle.small,
              ),

              const SizedBox(height: 16),

              if (sellerOrders.isEmpty)
                _shipmentCard(
                  sellerOrder: <String, dynamic>{'status': orderStatus},
                  index: 1,
                  parentCancelled: parentCancelled,
                )
              else
                for (int index = 0; index < sellerOrders.length; index++)
                  _shipmentCard(
                    sellerOrder: sellerOrders[index],
                    index: index + 1,
                    parentCancelled: parentCancelled,
                  ),

              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: BuyerColors.lightPurple,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Text(
                  'Updates reflect the latest information available from BiliHub. '
                  'Some logistics events may not have a recorded timestamp.',
                  style: BuyerStyle.small,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TrackingException implements Exception {
  final String message;

  const _TrackingException(this.message);
}

class OrderTrackingStep {
  final String title;
  final String subtitle;
  final bool done;
  final bool active;

  const OrderTrackingStep({
    required this.title,
    required this.subtitle,
    required this.done,
    required this.active,
  });
}

class _TrackingStepItem extends StatelessWidget {
  final OrderTrackingStep step;
  final bool isLast;

  const _TrackingStepItem({required this.step, required this.isLast});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        _TimelineIndicator(
          done: step.done,
          active: step.active,
          isLast: isLast,
        ),

        const SizedBox(width: 14),

        Expanded(
          child: Container(
            margin: const EdgeInsets.only(bottom: 18),
            padding: const EdgeInsets.all(16),
            decoration: step.active
                ? BoxDecoration(
                    color: BuyerColors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: BuyerColors.primary, width: 1.3),
                  )
                : BuyerStyle.card,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(step.title, style: BuyerStyle.cardTitle),
                    ),

                    if (step.active)
                      const Text(
                        'Current',
                        style: TextStyle(
                          color: BuyerColors.primary,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                  ],
                ),

                const SizedBox(height: 6),

                Text(step.subtitle, style: BuyerStyle.small),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _TimelineIndicator extends StatelessWidget {
  final bool done;
  final bool active;
  final bool isLast;

  const _TimelineIndicator({
    required this.done,
    required this.active,
    required this.isLast,
  });

  @override
  Widget build(BuildContext context) {
    final Color circleColor = done
        ? BuyerColors.success
        : active
        ? BuyerColors.primary
        : BuyerColors.border;

    return Column(
      children: <Widget>[
        Container(
          height: 36,
          width: 36,
          decoration: BoxDecoration(shape: BoxShape.circle, color: circleColor),
          child: Icon(
            done
                ? Icons.check_rounded
                : active
                ? Icons.radio_button_checked_rounded
                : Icons.circle_outlined,
            color: done || active ? BuyerColors.white : BuyerColors.textGrey,
            size: 20,
          ),
        ),

        if (!isLast)
          Container(
            height: 75,
            width: 2,
            color: done ? BuyerColors.success : BuyerColors.border,
          ),
      ],
    );
  }
}
