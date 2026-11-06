import 'dart:async';

import 'package:flutter/material.dart';

import '../../services/api_services.dart';
import '../../services/auth_storage.dart';
import '../../styles/rider_style.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_loading_state.dart';

import '../auth/login_page.dart';

class RiderProfile extends StatefulWidget {
  final String? name;
  final String? email;
  final String? phone;
  final String? riderId;
  final String? vehicle;

  const RiderProfile({
    super.key,
    this.name,
    this.email,
    this.phone,
    this.riderId,
    this.vehicle,
  });

  @override
  State<RiderProfile> createState() => _RiderProfileState();
}

class _RiderProfileState extends State<RiderProfile> {
  static const Duration _requestTimeout = Duration(seconds: 25);

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isLoggingOut = false;
  bool _hasProfile = false;
  bool _isServerVerified = false;

  int _requestId = 0;

  String? _errorMessage;

  String _name = '';
  String _email = '';
  String _phone = '';
  String _riderId = '';
  String _vehicle = '';
  String _availabilityStatus = '';
  String _accountStatus = '';
  String _licenseNumber = '';
  String _vehiclePlateNumber = '';

  Map<String, dynamic> _savedUser = <String, dynamic>{};

  @override
  void initState() {
    super.initState();
    _applyWidgetFallback();
    _loadProfile();
  }

  @override
  void dispose() {
    _requestId++;
    super.dispose();
  }

  // ============================================================
  // LOAD PROFILE
  // ============================================================

  Future<void> _loadProfile({bool refresh = false}) async {
    if (!mounted || _isLoggingOut || _isRefreshing) {
      return;
    }

    final int requestId = ++_requestId;

    setState(() {
      _isLoading = !_hasProfile;
      _isRefreshing = refresh || _hasProfile;
      _errorMessage = null;
    });

    try {
      // Load locally stored user information first.
      try {
        final Map<String, dynamic>? saved = await AuthStorage.getUser();

        if (!mounted || requestId != _requestId) {
          return;
        }

        if (saved != null) {
          _savedUser = Map<String, dynamic>.from(saved);

          _applySavedUser(_savedUser);
        }
      } catch (error) {
        debugPrint('Rider saved profile error: $error');
      }

      // Get the current rider information from Laravel.
      final Map<String, dynamic> result = await ApiService.getRiderDashboard()
          .timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) {
        return;
      }

      if (result['success'] != true) {
        _setLoadError(
          requestId,
          _resultMessage(result, fallback: 'Unable to load rider information.'),
        );

        return;
      }

      final Map<String, dynamic> rider = _extractRiderData(result);

      if (rider.isNotEmpty) {
        _applyRiderData(rider);
      }

      setState(() {
        _hasProfile = _hasMeaningfulProfile();
        _isServerVerified = rider.isNotEmpty;

        _isLoading = false;
        _isRefreshing = false;

        _errorMessage = rider.isEmpty
            ? 'The server responded, but current rider '
                  'profile details were not included. '
                  'Showing available saved information.'
            : null;
      });
    } on TimeoutException {
      _setLoadError(
        requestId,
        'The server is taking too long to respond. '
        'Please check your connection and try again.',
      );
    } catch (error) {
      debugPrint('Rider profile load error: $error');

      _setLoadError(
        requestId,
        'Unable to connect to the BiliHub server. '
        'Please check your internet connection '
        'and try again.',
      );
    }
  }

  void _setLoadError(int requestId, String message) {
    if (!mounted || requestId != _requestId) {
      return;
    }

    setState(() {
      _hasProfile = _hasMeaningfulProfile();

      _isLoading = false;
      _isRefreshing = false;

      _errorMessage = message;
    });
  }

  bool _hasMeaningfulProfile() {
    return _name.isNotEmpty ||
        _email.isNotEmpty ||
        _phone.isNotEmpty ||
        _riderId.isNotEmpty ||
        _vehicle.isNotEmpty ||
        _availabilityStatus.isNotEmpty ||
        _accountStatus.isNotEmpty;
  }

  // ============================================================
  // PROFILE FALLBACK
  // ============================================================

  void _applyWidgetFallback() {
    _name = _cleanText(widget.name);
    _email = _cleanText(widget.email);
    _phone = _cleanText(widget.phone);
    _riderId = _cleanText(widget.riderId);
    _vehicle = _cleanText(widget.vehicle);

    _hasProfile = _hasMeaningfulProfile();
  }

  void _applySavedUser(Map<String, dynamic> saved) {
    _name = _firstText([saved['name'], saved['full_name'], _name, widget.name]);

    _email = _firstText([saved['email'], _email, widget.email]);

    _phone = _firstText([
      saved['mobile_number'],
      saved['phone'],
      saved['contact_number'],
      _phone,
      widget.phone,
    ]);

    _riderId = _firstText([saved['rider_id'], _riderId, widget.riderId]);

    _vehicle = _firstText([
      saved['vehicle_type'],
      saved['vehicle'],
      _vehicle,
      widget.vehicle,
    ]);

    _availabilityStatus = _firstText([
      saved['availability_status'],
      saved['availability'],
      _availabilityStatus,
    ]);

    _accountStatus = _firstText([
      saved['account_status'],
      saved['status'],
      _accountStatus,
    ]);

    _licenseNumber = _firstText([
      saved['license_number'],
      saved['driver_license_number'],
      _licenseNumber,
    ]);

    _vehiclePlateNumber = _firstText([
      saved['plate_number'],
      saved['vehicle_plate_number'],
      _vehiclePlateNumber,
    ]);

    final dynamic savedRider = saved['rider'];

    if (savedRider is Map) {
      _applyRiderData(Map<String, dynamic>.from(savedRider));
    }

    _hasProfile = _hasMeaningfulProfile();
  }

  // ============================================================
  // EXTRACT RIDER DATA
  // ============================================================

  Map<String, dynamic> _extractRiderData(Map<String, dynamic> result) {
    final dynamic directRider = result['rider'];

    if (directRider is Map) {
      return Map<String, dynamic>.from(directRider);
    }

    final dynamic rawData = result['data'];

    if (rawData is Map) {
      final Map<String, dynamic> data = Map<String, dynamic>.from(rawData);

      final dynamic nestedRider = data['rider'];

      if (nestedRider is Map) {
        return Map<String, dynamic>.from(nestedRider);
      }

      final dynamic profile = data['profile'];

      if (profile is Map) {
        final Map<String, dynamic> profileMap = Map<String, dynamic>.from(
          profile,
        );

        if (profileMap['rider'] is Map) {
          return Map<String, dynamic>.from(profileMap['rider']);
        }

        if (_looksLikeRider(profileMap)) {
          return profileMap;
        }
      }

      if (_looksLikeRider(data)) {
        return data;
      }
    }

    return <String, dynamic>{};
  }

  bool _looksLikeRider(Map<String, dynamic> data) {
    return data.containsKey('vehicle_type') ||
        data.containsKey('availability_status') ||
        data.containsKey('rider_id') ||
        data.containsKey('license_number') ||
        data.containsKey('driver_license_number') ||
        data.containsKey('plate_number');
  }

  // ============================================================
  // APPLY RIDER INFORMATION
  // ============================================================

  void _applyRiderData(Map<String, dynamic> rider) {
    final dynamic user = rider['user'];

    final Map<String, dynamic> userMap = user is Map
        ? Map<String, dynamic>.from(user)
        : <String, dynamic>{};

    _name = _firstText([
      rider['name'],
      rider['full_name'],
      userMap['name'],
      _name,
      widget.name,
    ]);

    _email = _firstText([
      rider['email'],
      userMap['email'],
      _email,
      widget.email,
    ]);

    _phone = _firstText([
      rider['mobile_number'],
      rider['phone'],
      rider['contact_number'],
      userMap['mobile_number'],
      userMap['phone'],
      _phone,
      widget.phone,
    ]);

    _riderId = _firstText([
      rider['rider_id'],
      rider['id'],
      _riderId,
      widget.riderId,
    ]);

    _vehicle = _firstText([
      rider['vehicle_type'],
      rider['vehicle'],
      rider['vehicle_name'],
      _vehicle,
      widget.vehicle,
    ]);

    final String availability = _availabilityFromMap(rider);

    if (availability.isNotEmpty) {
      _availabilityStatus = availability;
    }

    _accountStatus = _firstText([
      rider['account_status'],
      rider['status'],
      _accountStatus,
    ]);

    _licenseNumber = _firstText([
      rider['license_number'],
      rider['driver_license_number'],
      _licenseNumber,
    ]);

    _vehiclePlateNumber = _firstText([
      rider['plate_number'],
      rider['vehicle_plate_number'],
      _vehiclePlateNumber,
    ]);
  }

  // ============================================================
  // AVAILABILITY
  // ============================================================

  String _availabilityFromMap(Map<String, dynamic> data) {
    final dynamic boolValue = data['is_available'] ?? data['available'];

    if (boolValue is bool) {
      return boolValue ? 'available' : 'unavailable';
    }

    if (boolValue != null) {
      final String value = boolValue.toString().trim().toLowerCase();

      if (value == 'true' || value == '1' || value == 'yes') {
        return 'available';
      }

      if (value == 'false' || value == '0' || value == 'no') {
        return 'unavailable';
      }
    }

    return _firstText([
      data['availability_status'],
      data['availability'],
      data['online_status'],
    ]);
  }

  bool get _isOnline {
    final String status = _normalizeStatus(_availabilityStatus);

    return status == 'available' || status == 'online' || status == 'active';
  }

  bool get _isAccountApproved {
    final String status = _normalizeStatus(_accountStatus);

    return status == 'active' || status == 'approved' || status == 'verified';
  }

  String get _availabilityText {
    if (_availabilityStatus.isEmpty) {
      return 'Not available';
    }

    return _formatStatus(_availabilityStatus);
  }

  String get _accountStatusText {
    if (_accountStatus.isEmpty) {
      return 'Not available';
    }

    return _formatStatus(_accountStatus);
  }

  // ============================================================
  // TEXT HELPERS
  // ============================================================

  String _cleanText(dynamic value) {
    final String cleaned = value?.toString().trim() ?? '';

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return '';
    }

    return cleaned;
  }

  String _firstText(List<dynamic> values) {
    for (final dynamic value in values) {
      final String text = _cleanText(value);

      if (text.isNotEmpty) {
        return text;
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

  String _normalizeStatus(String value) {
    return value.trim().toLowerCase().replaceAll(' ', '_').replaceAll('-', '_');
  }

  String _formatStatus(String value) {
    final String cleaned = value
        .trim()
        .replaceAll('_', ' ')
        .replaceAll('-', ' ');

    if (cleaned.isEmpty) {
      return 'Not available';
    }

    return cleaned
        .split(RegExp(r'\s+'))
        .where((String word) => word.isNotEmpty)
        .map(
          (String word) =>
              '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  String _resultMessage(
    Map<String, dynamic> result, {
    required String fallback,
  }) {
    final String message = _cleanText(result['message']);

    return message.isNotEmpty ? message : fallback;
  }

  // ============================================================
  // NOT AVAILABLE FEATURES
  // ============================================================

  void _showComingSoon(String feature) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          content: Text('$feature is not available yet.'),
        ),
      );
  }

  // ============================================================
  // LOGOUT
  // ============================================================

  Future<void> _logout() async {
    if (_isLoggingOut) return;

    final bool? confirmed = await _showLogoutConfirmation();

    if (!mounted || confirmed != true) {
      return;
    }

    setState(() {
      _isLoggingOut = true;
    });

    try {
      // Clears the local session and stored token.
      // This does not claim to revoke the token on Laravel.
      await AuthStorage.clearSession();

      if (!mounted) return;

      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (BuildContext context) {
            return const LoginPage();
          },
        ),
        (Route<dynamic> route) => false,
      );
    } catch (error) {
      debugPrint('Rider logout error: $error');

      if (!mounted) return;

      setState(() {
        _isLoggingOut = false;
      });

      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            behavior: SnackBarBehavior.floating,
            content: Text(
              'Unable to complete logout. '
              'Please try again.',
            ),
          ),
        );
    }
  }

  Future<bool?> _showLogoutConfirmation() {
    return showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Text('Logout', style: RiderStyle.sectionTitle),
          content: const Text(
            'Are you sure you want to log out '
            'of your Rider account?',
            style: RiderStyle.small,
          ),
          actions: [
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
              style: ElevatedButton.styleFrom(
                backgroundColor: RiderColors.danger,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: const Text('Logout'),
            ),
          ],
        );
      },
    );
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderColors.background,
      appBar: AppBar(
        backgroundColor: RiderColors.white,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        centerTitle: false,
        automaticallyImplyLeading: false,
        title: const Text('My Profile', style: RiderStyle.appBarTitle),
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
              tooltip: 'Refresh profile',
              onPressed: _isLoading || _isLoggingOut
                  ? null
                  : () => _loadProfile(refresh: true),
              icon: const Icon(
                Icons.refresh_rounded,
                color: RiderColors.primary,
              ),
            ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: RefreshIndicator(
          color: RiderColors.primary,
          onRefresh: () => _loadProfile(refresh: true),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: const EdgeInsets.fromLTRB(18, 12, 18, 30),
            children: [
              if (_isLoading && !_hasProfile) ...[
                const SizedBox(height: 125),
                const AppLoadingState(
                  message: 'Loading rider profile...',
                  color: RiderColors.primary,
                ),
              ] else if (!_hasProfile) ...[
                const SizedBox(height: 80),
                AppErrorState(
                  title: 'Unable to Load Profile',
                  message:
                      _errorMessage ??
                      'Rider information is unavailable. '
                          'Please try again.',
                  color: RiderColors.primary,
                  onRetry: () => _loadProfile(refresh: true),
                ),
                const SizedBox(height: 30),
                _logoutButton(),
              ] else ...[
                if (_isRefreshing) ...[
                  const LinearProgressIndicator(
                    color: RiderColors.primary,
                    minHeight: 2,
                  ),
                  const SizedBox(height: 15),
                ],

                if (_errorMessage != null) ...[
                  _errorBanner(),
                  const SizedBox(height: 15),
                ],

                _profileHeader(),

                const SizedBox(height: 14),

                if (!_isServerVerified) _cachedDataNotice(),

                const SizedBox(height: 22),

                const Text(
                  'Personal Information',
                  style: RiderStyle.sectionTitle,
                ),

                const SizedBox(height: 12),

                _infoCard(
                  icon: Icons.email_outlined,
                  title: 'Email Address',
                  value: _displayValue(_email),
                ),

                _infoCard(
                  icon: Icons.phone_outlined,
                  title: 'Phone Number',
                  value: _displayValue(_phone),
                ),

                _infoCard(
                  icon: Icons.badge_outlined,
                  title: 'Rider ID',
                  value: _displayValue(_riderId),
                  selectable: true,
                ),

                const SizedBox(height: 12),

                const Text('Rider Information', style: RiderStyle.sectionTitle),

                const SizedBox(height: 12),

                _infoCard(
                  icon: Icons.two_wheeler_outlined,
                  title: 'Vehicle Type',
                  value: _displayValue(
                    _vehicle.isEmpty ? '' : _formatStatus(_vehicle),
                  ),
                ),

                if (_vehiclePlateNumber.isNotEmpty)
                  _infoCard(
                    icon: Icons.confirmation_number_outlined,
                    title: 'Plate Number',
                    value: _vehiclePlateNumber,
                  ),

                if (_licenseNumber.isNotEmpty)
                  _infoCard(
                    icon: Icons.credit_card_outlined,
                    title: 'Driver License',
                    value: _licenseNumber,
                  ),

                _infoCard(
                  icon: _isOnline
                      ? Icons.check_circle_outline_rounded
                      : Icons.pause_circle_outline_rounded,
                  title: 'Availability',
                  value: _availabilityText,
                ),

                _infoCard(
                  icon: _isAccountApproved
                      ? Icons.verified_user_outlined
                      : Icons.pending_actions_outlined,
                  title: 'Account Status',
                  value: _accountStatusText,
                ),

                const SizedBox(height: 14),

                _accountSection(),

                const SizedBox(height: 22),

                _logoutButton(),
              ],
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // ERROR BANNER
  // ============================================================

  Widget _errorBanner() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: RiderColors.white,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: RiderColors.border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.info_outline_rounded,
            color: RiderColors.warning,
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(child: Text(_errorMessage ?? '', style: RiderStyle.small)),
          TextButton(
            onPressed: _isRefreshing ? null : () => _loadProfile(refresh: true),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _cachedDataNotice() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: RiderColors.lightOrange,
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.info_outline_rounded,
            color: RiderColors.warning,
            size: 19,
          ),
          SizedBox(width: 9),
          Expanded(
            child: Text(
              'Some information is from your saved session '
              'or previously loaded data and has not yet '
              'been verified with the server.',
              style: RiderStyle.small,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PROFILE HEADER
  // ============================================================

  Widget _profileHeader() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 22),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [RiderColors.primary, RiderColors.primaryDark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: RiderColors.primary.withValues(alpha: 0.18),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        children: [
          Container(
            height: 88,
            width: 88,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.16),
              shape: BoxShape.circle,
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.30),
                width: 2,
              ),
            ),
            child: const Icon(
              Icons.delivery_dining_rounded,
              color: Colors.white,
              size: 48,
            ),
          ),

          const SizedBox(height: 14),

          Text(
            _displayValue(_name, fallback: 'Rider'),
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 21,
              fontWeight: FontWeight.w800,
            ),
          ),

          const SizedBox(height: 5),

          Text(
            _displayValue(_email),
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white70, fontSize: 12),
          ),

          const SizedBox(height: 13),

          Wrap(
            alignment: WrapAlignment.center,
            spacing: 8,
            runSpacing: 8,
            children: [
              _headerBadge(
                icon: Icons.circle,
                label: _availabilityText,
                iconColor: _isOnline ? Colors.greenAccent : Colors.white70,
              ),
              _headerBadge(
                icon: _isAccountApproved
                    ? Icons.verified_rounded
                    : Icons.schedule_rounded,
                label: _accountStatusText,
                iconColor: _isAccountApproved
                    ? Colors.greenAccent
                    : Colors.amberAccent,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _headerBadge({
    required IconData icon,
    required String label,
    required Color iconColor,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white.withValues(alpha: 0.10)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: iconColor, size: 11),
          const SizedBox(width: 6),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // SHARED INFORMATION CARD
  // ============================================================

  Widget _infoCard({
    required IconData icon,
    required String title,
    required String value,
    bool selectable = false,
  }) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(15),
      decoration: RiderStyle.card,
      child: AppInfoRow(
        label: title,
        value: value,
        icon: icon,
        iconColor: RiderColors.primary,
        selectable: selectable,
      ),
    );
  }

  // ============================================================
  // ACCOUNT SECTION
  // ============================================================

  Widget _accountSection() {
    return Container(
      width: double.infinity,
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
            child: Text('Account', style: RiderStyle.sectionTitle),
          ),

          _menuItem(
            icon: Icons.settings_outlined,
            title: 'Account Settings',
            subtitle: 'Manage your profile information',
            onTap: () {
              _showComingSoon('Account Settings');
            },
          ),

          _divider(),

          _menuItem(
            icon: Icons.lock_outline_rounded,
            title: 'Security',
            subtitle: 'Password and account security',
            onTap: () {
              _showComingSoon('Security Settings');
            },
          ),

          _divider(),

          _menuItem(
            icon: Icons.help_outline_rounded,
            title: 'Help Center',
            subtitle: 'Get help with your rider account',
            onTap: () {
              _showComingSoon('Help Center');
            },
          ),
        ],
      ),
    );
  }

  Widget _menuItem({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
        child: Row(
          children: [
            Container(
              height: 40,
              width: 40,
              decoration: BoxDecoration(
                color: RiderColors.lightPurple,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: RiderColors.primary, size: 20),
            ),

            const SizedBox(width: 12),

            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: RiderStyle.cardTitle),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: RiderStyle.small,
                  ),
                ],
              ),
            ),

            const SizedBox(width: 8),

            const Icon(
              Icons.arrow_forward_ios_rounded,
              color: RiderColors.textLight,
              size: 15,
            ),
          ],
        ),
      ),
    );
  }

  Widget _divider() {
    return const Padding(
      padding: EdgeInsets.only(left: 68),
      child: Divider(height: 1, thickness: 1, color: RiderColors.border),
    );
  }

  // ============================================================
  // LOGOUT BUTTON
  // ============================================================

  Widget _logoutButton() {
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: OutlinedButton.icon(
        onPressed: _isLoggingOut ? null : _logout,
        icon: _isLoggingOut
            ? const SizedBox(
                height: 18,
                width: 18,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: RiderColors.danger,
                ),
              )
            : const Icon(Icons.logout_rounded, size: 19),
        label: Text(
          _isLoggingOut ? 'Logging out...' : 'Logout',
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
        ),
        style: OutlinedButton.styleFrom(
          foregroundColor: RiderColors.danger,
          backgroundColor: RiderColors.white,
          disabledForegroundColor: RiderColors.danger.withValues(alpha: 0.60),
          side: BorderSide(color: RiderColors.danger.withValues(alpha: 0.35)),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
    );
  }
}
