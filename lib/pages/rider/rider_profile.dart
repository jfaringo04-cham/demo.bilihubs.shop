import 'package:flutter/material.dart';

import '../../sevices/api_services.dart';
import '../../sevices/auth_storage.dart';
import '../../styles/rider_style.dart';
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
  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isLoggingOut = false;

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

    _loadProfile();
  }

  // ============================================================
  // LOAD PROFILE
  // ============================================================

  Future<void> _loadProfile({bool refresh = false}) async {
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
      await _loadSavedUser();

      final Map<String, dynamic> result = await ApiService.getRiderDashboard();

      if (!mounted) {
        return;
      }

      if (result['success'] == true) {
        final Map<String, dynamic> rider = _extractRiderData(result);

        _applyRiderData(rider);

        setState(() {
          _isLoading = false;
          _isRefreshing = false;
          _errorMessage = null;
        });

        return;
      }

      setState(() {
        _applyFallbackValues();

        _isLoading = false;
        _isRefreshing = false;

        final String message = result['message']?.toString().trim() ?? '';

        _errorMessage = message.isNotEmpty
            ? message
            : 'Rider information is not available yet.';
      });
    } catch (error) {
      debugPrint('Rider profile error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _applyFallbackValues();

        _isLoading = false;
        _isRefreshing = false;

        _errorMessage =
            'Unable to load rider information. '
            'Please try again.';
      });
    }
  }

  // ============================================================
  // SAVED USER
  // ============================================================

  Future<void> _loadSavedUser() async {
    final Map<String, dynamic>? saved = await AuthStorage.getUser();

    if (saved == null) {
      _savedUser = <String, dynamic>{};

      _applyWidgetFallback();

      return;
    }

    _savedUser = Map<String, dynamic>.from(saved);

    _name = _firstText(<dynamic>[
      saved['name'],
      saved['full_name'],
      widget.name,
    ]);

    _email = _firstText(<dynamic>[saved['email'], widget.email]);

    _phone = _firstText(<dynamic>[
      saved['mobile_number'],
      saved['phone'],
      saved['contact_number'],
      widget.phone,
    ]);

    _riderId = _firstText(<dynamic>[saved['rider_id'], widget.riderId]);

    _vehicle = _firstText(<dynamic>[
      saved['vehicle_type'],
      saved['vehicle'],
      widget.vehicle,
    ]);

    _availabilityStatus = _firstText(<dynamic>[
      saved['availability_status'],
      saved['availability'],
    ]);

    _accountStatus = _firstText(<dynamic>[
      saved['status'],
      saved['account_status'],
    ]);

    _licenseNumber = _firstText(<dynamic>[
      saved['license_number'],
      saved['driver_license_number'],
    ]);

    _vehiclePlateNumber = _firstText(<dynamic>[
      saved['plate_number'],
      saved['vehicle_plate_number'],
    ]);

    final dynamic savedRider = saved['rider'];

    if (savedRider is Map) {
      _applyRiderData(Map<String, dynamic>.from(savedRider));
    }
  }

  // ============================================================
  // EXTRACT RIDER DATA
  // ============================================================

  Map<String, dynamic> _extractRiderData(Map<String, dynamic> result) {
    final dynamic direct = result['rider'];

    if (direct is Map) {
      return Map<String, dynamic>.from(direct);
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
        return Map<String, dynamic>.from(profile);
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
        data.containsKey('driver_license_number');
  }

  // ============================================================
  // APPLY RIDER DATA
  // ============================================================

  void _applyRiderData(Map<String, dynamic> rider) {
    final String name = _firstText(<dynamic>[
      rider['name'],
      rider['full_name'],
      _name,
      widget.name,
    ]);

    final String email = _firstText(<dynamic>[
      rider['email'],
      _email,
      widget.email,
    ]);

    final String phone = _firstText(<dynamic>[
      rider['mobile_number'],
      rider['phone'],
      rider['contact_number'],
      _phone,
      widget.phone,
    ]);

    final String riderId = _firstText(<dynamic>[
      rider['rider_id'],
      rider['id'],
      _riderId,
      widget.riderId,
    ]);

    final String vehicle = _firstText(<dynamic>[
      rider['vehicle_type'],
      rider['vehicle'],
      rider['vehicle_name'],
      _vehicle,
      widget.vehicle,
    ]);

    final String availability = _availabilityFromMap(rider);

    final String accountStatus = _firstText(<dynamic>[
      rider['status'],
      rider['account_status'],
      _accountStatus,
    ]);

    final String licenseNumber = _firstText(<dynamic>[
      rider['license_number'],
      rider['driver_license_number'],
      _licenseNumber,
    ]);

    final String plateNumber = _firstText(<dynamic>[
      rider['plate_number'],
      rider['vehicle_plate_number'],
      _vehiclePlateNumber,
    ]);

    _name = name;
    _email = email;
    _phone = phone;
    _riderId = riderId;
    _vehicle = vehicle;
    _accountStatus = accountStatus;
    _licenseNumber = licenseNumber;
    _vehiclePlateNumber = plateNumber;

    if (availability.isNotEmpty) {
      _availabilityStatus = availability;
    }
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

      if (value == '1' || value == 'true' || value == 'yes') {
        return 'available';
      }

      if (value == '0' || value == 'false' || value == 'no') {
        return 'unavailable';
      }
    }

    return _firstText(<dynamic>[
      data['availability_status'],
      data['availability'],
      data['online_status'],
    ]);
  }

  void _applyWidgetFallback() {
    _name = widget.name?.trim() ?? '';

    _email = widget.email?.trim() ?? '';

    _phone = widget.phone?.trim() ?? '';

    _riderId = widget.riderId?.trim() ?? '';

    _vehicle = widget.vehicle?.trim() ?? '';
  }

  void _applyFallbackValues() {
    if (_name.isEmpty) {
      _name = _firstText(<dynamic>[_savedUser['name'], widget.name]);
    }

    if (_email.isEmpty) {
      _email = _firstText(<dynamic>[_savedUser['email'], widget.email]);
    }

    if (_phone.isEmpty) {
      _phone = _firstText(<dynamic>[
        _savedUser['mobile_number'],
        _savedUser['phone'],
        widget.phone,
      ]);
    }

    if (_riderId.isEmpty) {
      _riderId = _firstText(<dynamic>[_savedUser['rider_id'], widget.riderId]);
    }

    if (_vehicle.isEmpty) {
      _vehicle = _firstText(<dynamic>[
        _savedUser['vehicle_type'],
        widget.vehicle,
      ]);
    }

    if (_availabilityStatus.isEmpty) {
      _availabilityStatus = _firstText(<dynamic>[
        _savedUser['availability_status'],
        _savedUser['availability'],
      ]);
    }

    if (_accountStatus.isEmpty) {
      _accountStatus = _firstText(<dynamic>[
        _savedUser['status'],
        _savedUser['account_status'],
      ]);
    }

    if (_licenseNumber.isEmpty) {
      _licenseNumber = _firstText(<dynamic>[
        _savedUser['license_number'],
        _savedUser['driver_license_number'],
      ]);
    }

    if (_vehiclePlateNumber.isEmpty) {
      _vehiclePlateNumber = _firstText(<dynamic>[
        _savedUser['plate_number'],
        _savedUser['vehicle_plate_number'],
      ]);
    }
  }

  // ============================================================
  // HELPERS
  // ============================================================

  String _firstText(List<dynamic> values) {
    for (final dynamic value in values) {
      final String cleaned = value?.toString().trim() ?? '';

      if (cleaned.isNotEmpty && cleaned.toLowerCase() != 'null') {
        return cleaned;
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

  String _formatStatus(String value) {
    final String cleaned = value
        .trim()
        .replaceAll('_', ' ')
        .replaceAll('-', ' ');

    if (cleaned.isEmpty) {
      return 'Unavailable';
    }

    return cleaned
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

  String get _statusText {
    if (_availabilityStatus.trim().isEmpty) {
      return 'Unavailable';
    }

    return _formatStatus(_availabilityStatus);
  }

  String get _accountStatusText {
    if (_accountStatus.trim().isEmpty) {
      return 'Active';
    }

    return _formatStatus(_accountStatus);
  }

  bool get _isOnline {
    final String status = _availabilityStatus
        .trim()
        .toLowerCase()
        .replaceAll(' ', '_')
        .replaceAll('-', '_');

    return status == 'online' || status == 'available' || status == 'active';
  }

  bool get _isAccountApproved {
    final String status = _accountStatus
        .trim()
        .toLowerCase()
        .replaceAll(' ', '_')
        .replaceAll('-', '_');

    if (status.isEmpty) {
      return true;
    }

    return status == 'active' || status == 'approved' || status == 'verified';
  }

  void _showComingSoon(String feature) {
    if (!mounted) {
      return;
    }

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
    if (_isLoggingOut) {
      return;
    }

    final bool? confirmed = await _showLogoutConfirmation();

    if (!mounted || confirmed != true) {
      return;
    }

    setState(() {
      _isLoggingOut = true;
    });

    try {
      /*
       * Mobile auth currently relies on the stored Sanctum
       * token. Clearing the local session guarantees that the
       * protected Rider APIs can no longer be called from the
       * current device session.
       */
      await AuthStorage.clearSession();

      if (!mounted) {
        return;
      }

      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (BuildContext context) {
            return const LoginPage();
          },
        ),
        (Route<dynamic> route) => false,
      );
    } catch (error) {
      debugPrint('Logout error: $error');

      if (!mounted) {
        return;
      }

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
                elevation: 0,
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
              onPressed: _isLoading
                  ? null
                  : () {
                      _loadProfile(refresh: true);
                    },
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
          onRefresh: () {
            return _loadProfile(refresh: true);
          },
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: const EdgeInsets.fromLTRB(18, 12, 18, 30),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (_isLoading) _loadingCard(),

                if (_isLoading) const SizedBox(height: 15),

                if (!_isLoading && _errorMessage != null) _errorCard(),

                if (!_isLoading && _errorMessage != null)
                  const SizedBox(height: 15),

                _profileHeader(),

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
                  iconBackground: RiderColors.lightBlue,
                  iconColor: RiderColors.primary,
                ),

                _infoCard(
                  icon: Icons.phone_outlined,
                  title: 'Phone Number',
                  value: _displayValue(_phone),
                  iconBackground: RiderColors.lightGreen,
                  iconColor: RiderColors.success,
                ),

                _infoCard(
                  icon: Icons.badge_outlined,
                  title: 'Rider ID',
                  value: _displayValue(_riderId),
                  iconBackground: RiderColors.lightPurple,
                  iconColor: RiderColors.primary,
                ),

                const SizedBox(height: 10),

                const Text('Rider Information', style: RiderStyle.sectionTitle),

                const SizedBox(height: 12),

                _infoCard(
                  icon: Icons.two_wheeler_outlined,
                  title: 'Vehicle Type',
                  value: _displayValue(_formatStatus(_vehicle)),
                  iconBackground: RiderColors.lightOrange,
                  iconColor: RiderColors.warning,
                ),

                if (_vehiclePlateNumber.trim().isNotEmpty)
                  _infoCard(
                    icon: Icons.confirmation_number_outlined,
                    title: 'Plate Number',
                    value: _vehiclePlateNumber,
                    iconBackground: RiderColors.lightBlue,
                    iconColor: RiderColors.primary,
                  ),

                if (_licenseNumber.trim().isNotEmpty)
                  _infoCard(
                    icon: Icons.credit_card_outlined,
                    title: 'Driver License',
                    value: _licenseNumber,
                    iconBackground: RiderColors.lightPurple,
                    iconColor: RiderColors.primary,
                  ),

                _infoCard(
                  icon: _isOnline
                      ? Icons.check_circle_outline_rounded
                      : Icons.pause_circle_outline_rounded,
                  title: 'Availability',
                  value: _statusText,
                  iconBackground: _isOnline
                      ? RiderColors.lightGreen
                      : RiderColors.lightOrange,
                  iconColor: _isOnline
                      ? RiderColors.success
                      : RiderColors.warning,
                ),

                _infoCard(
                  icon: _isAccountApproved
                      ? Icons.verified_user_outlined
                      : Icons.pending_actions_outlined,
                  title: 'Account Status',
                  value: _accountStatusText,
                  iconBackground: _isAccountApproved
                      ? RiderColors.lightGreen
                      : RiderColors.lightOrange,
                  iconColor: _isAccountApproved
                      ? RiderColors.success
                      : RiderColors.warning,
                ),

                const SizedBox(height: 12),

                _accountSection(),

                const SizedBox(height: 22),

                _logoutButton(),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // LOADING
  // ============================================================

  Widget _loadingCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(15),
      decoration: RiderStyle.card,
      child: const Row(
        children: [
          SizedBox(
            height: 21,
            width: 21,
            child: CircularProgressIndicator(
              strokeWidth: 2.2,
              color: RiderColors.primary,
            ),
          ),
          SizedBox(width: 12),
          Expanded(child: Text('Loading profile...', style: RiderStyle.small)),
        ],
      ),
    );
  }

  // ============================================================
  // ERROR
  // ============================================================

  Widget _errorCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(15),
      decoration: RiderStyle.card,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.info_outline_rounded,
            color: RiderColors.warning,
            size: 21,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              _errorMessage ?? 'Unable to load rider information.',
              style: RiderStyle.small,
            ),
          ),
          IconButton(
            tooltip: 'Retry',
            onPressed: () {
              _loadProfile(refresh: true);
            },
            icon: const Icon(Icons.refresh_rounded, color: RiderColors.primary),
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
          colors: <Color>[RiderColors.primary, RiderColors.primaryDark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: RiderColors.primary.withValues(alpha: 0.18),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -45,
            top: -55,
            child: Container(
              height: 145,
              width: 145,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.07),
              ),
            ),
          ),

          Positioned(
            left: -55,
            bottom: -75,
            child: Container(
              height: 130,
              width: 130,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.05),
              ),
            ),
          ),

          Column(
            children: [
              Container(
                height: 88,
                width: 88,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: 0.16),
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
                maxLines: 1,
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
                maxLines: 1,
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
                    label: _statusText,
                    iconColor: _isOnline ? Colors.greenAccent : Colors.white54,
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
          Icon(icon, color: iconColor, size: 10),
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
  // INFORMATION CARD
  // ============================================================

  Widget _infoCard({
    required IconData icon,
    required String title,
    required String value,
    required Color iconBackground,
    required Color iconColor,
  }) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(15),
      decoration: RiderStyle.card,
      child: Row(
        children: [
          Container(
            height: 46,
            width: 46,
            decoration: BoxDecoration(
              color: iconBackground,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(icon, color: iconColor, size: 22),
          ),

          const SizedBox(width: 13),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: RiderStyle.small),

                const SizedBox(height: 4),

                Text(
                  value,
                  maxLines: 2,
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
              _showComingSoon('Account settings');
            },
          ),

          _divider(),

          _menuItem(
            icon: Icons.lock_outline_rounded,
            title: 'Security',
            subtitle: 'Password and account security',
            onTap: () {
              _showComingSoon('Security settings');
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

  // ============================================================
  // MENU ITEM
  // ============================================================

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
                    maxLines: 1,
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
