import 'dart:async';

import 'package:flutter/material.dart';

import '../../services/api_services.dart';
import '../../styles/rider_style.dart';
import '../../widgets/app_skeleton.dart';

class AvailablePickup {
  final int id;
  final int shipmentId;
  final String trackingNumber;
  final String sellerName;
  final String pickupAddress;
  final String status;
  final DateTime? expiresAt;
  final int secondsRemaining;

  const AvailablePickup({
    required this.id,
    required this.shipmentId,
    required this.trackingNumber,
    required this.sellerName,
    required this.pickupAddress,
    required this.status,
    required this.expiresAt,
    required this.secondsRemaining,
  });

  factory AvailablePickup.fromJson(Map<String, dynamic> json) {
    int parseInt(dynamic value) {
      if (value is num) return value.toInt();

      return int.tryParse(value?.toString().trim() ?? '') ?? 0;
    }

    String parseString(dynamic value) {
      final String text = value?.toString().trim() ?? '';

      return text.toLowerCase() == 'null' ? '' : text;
    }

    return AvailablePickup(
      id: parseInt(json['offer_id'] ?? json['id']),
      shipmentId: parseInt(json['shipment_id']),
      trackingNumber: parseString(json['tracking_number']),
      sellerName: parseString(json['seller_name']),
      pickupAddress: parseString(json['pickup_address']),
      status: parseString(json['status']).isNotEmpty
          ? parseString(json['status'])
          : 'offered',
      expiresAt: DateTime.tryParse(parseString(json['expires_at'])),
      secondsRemaining: parseInt(json['seconds_remaining']),
    );
  }
}

class AvailablePickups extends StatefulWidget {
  final VoidCallback? onPickupAccepted;

  const AvailablePickups({super.key, this.onPickupAccepted});

  @override
  State<AvailablePickups> createState() => _AvailablePickupsState();
}

class _AvailablePickupsState extends State<AvailablePickups> {
  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _hasLoaded = false;
  bool _requestInProgress = false;
  bool _actionInProgress = false;

  String? _errorMessage;

  List<AvailablePickup> _offers = <AvailablePickup>[];

  final Set<int> _processingIds = <int>{};

  Timer? _countdownTimer;

  DateTime _serverSyncTime = DateTime.now();
  DateTime _localSyncTime = DateTime.now();

  int _requestId = 0;

  @override
  void initState() {
    super.initState();

    _loadOffers();

    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (Timer timer) {
      if (!mounted || _offers.isEmpty) return;

      setState(() {
        // Rebuild the countdown labels.
      });
    });
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    _requestId++;
    super.dispose();
  }

  // ============================================================
  // FETCH PICKUP OFFERS
  // ============================================================

  Future<void> _loadOffers({bool refresh = false}) async {
    if (!mounted || _requestInProgress) return;

    _requestInProgress = true;

    final int requestId = ++_requestId;
    final DateTime requestStartedAt = DateTime.now();

    setState(() {
      _isLoading = !_hasLoaded;
      _isRefreshing = _hasLoaded;
      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> response =
          await ApiService.getRiderPickupOffers();

      if (!mounted || requestId != _requestId) return;

      if (response['success'] != true) {
        setState(() {
          _errorMessage =
              response['message']?.toString() ??
              'Unable to load pickup offers.';

          _isLoading = false;
          _isRefreshing = false;
        });

        return;
      }

      final dynamic rawData = response['data'];

      if (rawData is! List) {
        setState(() {
          _errorMessage = 'Invalid pickup offers response.';
          _isLoading = false;
          _isRefreshing = false;
        });

        return;
      }

      final List<AvailablePickup> parsed = <AvailablePickup>[];

      for (final dynamic item in rawData) {
        if (item is! Map) continue;

        try {
          final AvailablePickup offer = AvailablePickup.fromJson(
            Map<String, dynamic>.from(item),
          );

          if (offer.id > 0 && offer.shipmentId > 0) {
            parsed.add(offer);
          }
        } catch (error) {
          debugPrint('Pickup offer parsing error: $error');
        }
      }

      final Map<String, dynamic> meta = response['meta'] is Map
          ? Map<String, dynamic>.from(response['meta'])
          : <String, dynamic>{};

      final DateTime? serverTime = DateTime.tryParse(
        meta['server_time']?.toString() ?? '',
      );

      parsed.sort((AvailablePickup a, AvailablePickup b) {
        if (a.expiresAt != null && b.expiresAt != null) {
          return a.expiresAt!.compareTo(b.expiresAt!);
        }

        if (a.expiresAt != null) return -1;
        if (b.expiresAt != null) return 1;

        return a.id.compareTo(b.id);
      });

      setState(() {
        _offers = parsed;
        _hasLoaded = true;

        // For countdown fallback, start at the time
        // the API request began, avoiding an inflated timer
        // when the request takes several seconds.
        _serverSyncTime = serverTime ?? requestStartedAt;
        _localSyncTime = serverTime != null ? DateTime.now() : requestStartedAt;

        _isLoading = false;
        _isRefreshing = false;
        _errorMessage = null;
      });
    } catch (error) {
      debugPrint('Pickup offers request error: $error');

      if (!mounted || requestId != _requestId) return;

      setState(() {
        _errorMessage = 'Unable to retrieve pickup offers. Please retry.';
        _isLoading = false;
        _isRefreshing = false;
      });
    } finally {
      _requestInProgress = false;
    }
  }

  // ============================================================
  // COUNTDOWN
  // ============================================================

  DateTime get _estimatedServerNow {
    final Duration elapsed = DateTime.now().difference(_localSyncTime);

    return _serverSyncTime.add(elapsed);
  }

  Duration _remainingTime(AvailablePickup offer) {
    final Duration elapsed = DateTime.now().difference(_localSyncTime);

    if (offer.expiresAt != null) {
      return offer.expiresAt!.toUtc().difference(_estimatedServerNow.toUtc());
    }

    return Duration(seconds: offer.secondsRemaining) - elapsed;
  }

  bool _isExpired(AvailablePickup offer) {
    return _remainingTime(offer).inMilliseconds <= 0;
  }

  String _countdownText(AvailablePickup offer) {
    final Duration remaining = _remainingTime(offer);

    if (remaining.inMilliseconds <= 0) {
      return 'Expired';
    }

    final int totalSeconds = (remaining.inMilliseconds / 1000).ceil();

    final int minutes = totalSeconds ~/ 60;
    final int seconds = totalSeconds % 60;

    return '$minutes:${seconds.toString().padLeft(2, '0')}';
  }

  int get _activeOfferCount {
    return _offers.where((AvailablePickup offer) => !_isExpired(offer)).length;
  }

  // ============================================================
  // ACCEPT / DECLINE
  // ============================================================

  Future<void> _handleOffer(
    AvailablePickup offer, {
    required bool accept,
  }) async {
    if (!mounted || _actionInProgress || _processingIds.contains(offer.id)) {
      return;
    }

    if (_isExpired(offer)) {
      _showMessage(
        'This pickup offer has expired. Refreshing offers.',
        isError: true,
      );

      await _loadOffers(refresh: true);
      return;
    }

    final bool confirmed = await _showConfirmation(offer, accept: accept);

    if (!confirmed || !mounted) return;

    // Recheck after the confirmation dialog.
    if (_isExpired(offer)) {
      _showMessage('This pickup offer has expired.', isError: true);

      await _loadOffers(refresh: true);
      return;
    }

    if (_actionInProgress) return;

    setState(() {
      _actionInProgress = true;
      _processingIds.add(offer.id);
    });

    try {
      final Map<String, dynamic> response = accept
          ? await ApiService.acceptRiderPickupOffer(offerId: offer.id)
          : await ApiService.declineRiderPickupOffer(offerId: offer.id);

      if (!mounted) return;

      if (response['success'] != true) {
        final String message =
            response['message']?.toString() ??
            'Unable to process this pickup offer.';

        _showMessage(message, isError: true);

        // A request may have succeeded on the server
        // before a timeout or connection interruption.
        // Refresh rather than assuming it failed.
        await _loadOffers(refresh: true);
        return;
      }

      setState(() {
        _offers.removeWhere((AvailablePickup item) => item.id == offer.id);
      });

      if (accept) {
        // Notify Rider Home to refresh when this
        // page is closed.
        widget.onPickupAccepted?.call();

        await _showSuccessDialog(offer);
      } else {
        _showMessage('Pickup offer declined successfully.');
      }

      if (!mounted) return;

      await _loadOffers(refresh: true);
    } catch (error) {
      debugPrint('Pickup offer action error: $error');

      if (!mounted) return;

      _showMessage(
        'Unable to confirm the pickup request. '
        'Refreshing the latest offer status.',
        isError: true,
      );

      await _loadOffers(refresh: true);
    } finally {
      if (mounted) {
        setState(() {
          _processingIds.remove(offer.id);
          _actionInProgress = false;
        });
      }
    }
  }

  // ============================================================
  // CONFIRMATION DIALOG
  // ============================================================

  Future<bool> _showConfirmation(
    AvailablePickup offer, {
    required bool accept,
  }) async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      barrierDismissible: true,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          title: Row(
            children: [
              Icon(
                accept ? Icons.local_shipping_outlined : Icons.cancel_outlined,
                color: accept ? RiderColors.primary : RiderColors.warning,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  accept ? 'Accept Pickup Request?' : 'Decline Pickup Request?',
                ),
              ),
            ],
          ),
          content: Text(
            accept
                ? 'Are you sure you want to accept pickup '
                      '${_display(offer.trackingNumber)}?\n\n'
                      'Seller: ${_display(offer.sellerName)}\n'
                      'Pickup Address: ${_display(offer.pickupAddress)}\n\n'
                      'Once confirmed by BiliHub, this parcel '
                      'will be assigned to your rider account.'
                : 'Are you sure you want to decline pickup '
                      '${_display(offer.trackingNumber)}?\n\n'
                      'The system may offer this parcel '
                      'to another eligible rider.',
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(dialogContext).pop(false);
              },
              child: const Text('Cancel'),
            ),
            FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: accept
                    ? RiderColors.primary
                    : RiderColors.warning,
              ),
              onPressed: () {
                Navigator.of(dialogContext).pop(true);
              },
              child: Text(accept ? 'Confirm Accept' : 'Confirm Decline'),
            ),
          ],
        );
      },
    );

    return confirmed ?? false;
  }

  // ============================================================
  // SUCCESS DIALOG
  // ============================================================

  Future<void> _showSuccessDialog(AvailablePickup offer) async {
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          title: const Row(
            children: [
              Icon(
                Icons.check_circle_rounded,
                color: RiderColors.success,
                size: 28,
              ),
              SizedBox(width: 10),
              Expanded(child: Text('Pickup Assigned!')),
            ],
          ),
          content: Text(
            'You successfully accepted parcel '
            '${_display(offer.trackingNumber)}.\n\n'
            'You are now assigned to collect this '
            'parcel from the seller.\n\n'
            'Open My Deliveries to manage the pickup.',
          ),
          actions: [
            FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: RiderColors.primary,
              ),
              onPressed: () {
                Navigator.of(dialogContext).pop();
              },
              child: const Text('Got It'),
            ),
          ],
        );
      },
    );
  }

  // ============================================================
  // NOTIFICATIONS
  // ============================================================

  void _showMessage(String message, {bool isError = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: isError ? Colors.red.shade700 : RiderColors.primary,
          content: Text(message),
        ),
      );
  }

  String _display(String value) {
    final String text = value.trim();

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return 'Not available';
    }

    return text;
  }

  // ============================================================
  // MAIN UI
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderColors.background,
      appBar: AppBar(
        backgroundColor: RiderColors.background,
        surfaceTintColor: RiderColors.background,
        elevation: 0,
        title: const Text('Pickup Offers', style: RiderStyle.title),
        actions: [
          IconButton(
            tooltip: 'Refresh pickup offers',
            onPressed: _requestInProgress || _actionInProgress
                ? null
                : () => _loadOffers(refresh: true),
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
      body: RefreshIndicator(
        color: RiderColors.primary,
        onRefresh: () => _loadOffers(refresh: true),
        child: _buildContent(),
      ),
    );
  }

  Widget _buildContent() {
    if (_isLoading && !_hasLoaded) {
      // FIX: ListView is not a const constructor.
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          const AppSkeleton(
            type: AppSkeletonType.parcel,
            itemCount: 3,
            color: RiderColors.primary,
          ),
        ],
      );
    }

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(
        parent: BouncingScrollPhysics(),
      ),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 30),
      children: [
        _headerCard(),

        const SizedBox(height: 16),

        if (_isRefreshing) ...[
          const LinearProgressIndicator(
            color: RiderColors.primary,
            minHeight: 2,
          ),
          const SizedBox(height: 16),
        ],

        if (_errorMessage != null) ...[
          _errorCard(),
          const SizedBox(height: 16),
        ],

        if (_offers.isEmpty) _emptyCard() else ..._offers.map(_offerCard),
      ],
    );
  }

  // ============================================================
  // HEADER CARD
  // ============================================================

  Widget _headerCard() {
    final int activeCount = _activeOfferCount;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(17),
      decoration: RiderStyle.card,
      child: Row(
        children: [
          Container(
            height: 48,
            width: 48,
            decoration: BoxDecoration(
              color: RiderColors.lightPurple,
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(
              Icons.notifications_active_outlined,
              color: RiderColors.primary,
              size: 25,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'New Pickup Requests',
                  style: RiderStyle.sectionTitle,
                ),
                const SizedBox(height: 5),
                Text(
                  '$activeCount active '
                  '${activeCount == 1 ? 'offer' : 'offers'}',
                  style: RiderStyle.small,
                ),
                const SizedBox(height: 3),
                const Text(
                  'Accept before the offer expires.',
                  style: RiderStyle.small,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // OFFER CARD
  // ============================================================

  Widget _offerCard(AvailablePickup offer) {
    final bool expired = _isExpired(offer);
    final bool busy = _processingIds.contains(offer.id);
    final bool disableActions = expired || busy || _actionInProgress;

    return Container(
      width: double.infinity,
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
                height: 44,
                width: 44,
                decoration: BoxDecoration(
                  color: RiderColors.lightPurple,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.local_shipping_outlined,
                  color: RiderColors.primary,
                  size: 24,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _display(offer.trackingNumber),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: RiderStyle.cardTitle,
                    ),
                    const SizedBox(height: 4),
                    const Text(
                      'Seller Pickup Request',
                      style: RiderStyle.small,
                    ),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 16),

          _detail(
            icon: Icons.storefront_outlined,
            label: 'Seller',
            value: _display(offer.sellerName),
          ),

          const SizedBox(height: 12),

          _detail(
            icon: Icons.location_on_outlined,
            label: 'Pickup Address',
            value: _display(offer.pickupAddress),
          ),

          const SizedBox(height: 14),

          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: expired
                  ? RiderColors.lightOrange
                  : RiderColors.lightPurple,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              children: [
                Icon(
                  expired ? Icons.timer_off_outlined : Icons.timer_outlined,
                  size: 19,
                  color: expired ? RiderColors.warning : RiderColors.primary,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    expired
                        ? 'Offer expired — refresh to update'
                        : 'Time remaining: ${_countdownText(offer)}',
                    style: RiderStyle.small,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),

          if (busy)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 6),
              child: Center(
                child: CircularProgressIndicator(color: RiderColors.primary),
              ),
            )
          else
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: disableActions
                        ? null
                        : () => _handleOffer(offer, accept: false),
                    child: const Text('Decline'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton(
                    style: FilledButton.styleFrom(
                      backgroundColor: RiderColors.primary,
                    ),
                    onPressed: disableActions
                        ? null
                        : () => _handleOffer(offer, accept: true),
                    child: const Text('Accept'),
                  ),
                ),
              ],
            ),
        ],
      ),
    );
  }

  Widget _detail({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: RiderColors.primary, size: 19),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: RiderStyle.small),
              const SizedBox(height: 3),
              Text(value, style: RiderStyle.body),
            ],
          ),
        ),
      ],
    );
  }

  // ============================================================
  // ERROR CARD
  // ============================================================

  Widget _errorCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: RiderStyle.card,
      child: Column(
        children: [
          const Icon(
            Icons.info_outline_rounded,
            color: RiderColors.warning,
            size: 25,
          ),
          const SizedBox(height: 8),
          Text(
            _errorMessage ?? 'Unable to load pickup offers.',
            textAlign: TextAlign.center,
            style: RiderStyle.small,
          ),
          const SizedBox(height: 10),
          TextButton.icon(
            onPressed: _requestInProgress || _actionInProgress
                ? null
                : () => _loadOffers(refresh: true),
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // EMPTY STATE
  // ============================================================

  Widget _emptyCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(24),
      decoration: RiderStyle.card,
      child: Column(
        children: [
          const Icon(
            Icons.inbox_outlined,
            size: 48,
            color: RiderColors.primary,
          ),
          const SizedBox(height: 12),
          const Text(
            'No Active Pickup Offers',
            style: RiderStyle.sectionTitle,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 8),
          const Text(
            'When BiliHub offers you a seller pickup, '
            'the request will appear here.\n\n'
            'Pull down or tap Refresh Offers to check '
            'for new requests.',
            style: RiderStyle.small,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 12),
          TextButton.icon(
            onPressed: _requestInProgress || _actionInProgress
                ? null
                : () => _loadOffers(refresh: true),
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Refresh Offers'),
          ),
        ],
      ),
    );
  }
}
