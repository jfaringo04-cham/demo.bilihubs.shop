import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

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
  bool _isLoggingOut = false;

  String? _errorMessage;

  String _name = '';
  String _email = '';
  String _phone = '';
  String _riderId = '';
  String _vehicle = '';
  String _availabilityStatus = '';

  @override
  void initState() {
    super.initState();

    _loadProfile();
  }

  // ============================================================
  // LOAD PROFILE
  // ============================================================

  Future<void> _loadProfile() async {
    if (mounted) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    // ----------------------------------------------------------
    // SAVED USER DATA
    // ----------------------------------------------------------

    final Map<String, dynamic>? savedUser = await AuthStorage.getUser();

    if (!mounted) {
      return;
    }

    if (savedUser != null) {
      final String savedName = savedUser['name']?.toString().trim() ?? '';

      final String savedEmail = savedUser['email']?.toString().trim() ?? '';

      final String savedPhone =
          savedUser['mobile_number']?.toString().trim() ??
          savedUser['phone']?.toString().trim() ??
          '';

      _name = savedName.isNotEmpty ? savedName : widget.name ?? '';

      _email = savedEmail.isNotEmpty ? savedEmail : widget.email ?? '';

      _phone = savedPhone.isNotEmpty ? savedPhone : widget.phone ?? '';
    } else {
      _name = widget.name ?? '';
      _email = widget.email ?? '';
      _phone = widget.phone ?? '';
    }

    // ----------------------------------------------------------
    // RIDER DATA FROM DASHBOARD API
    // ----------------------------------------------------------

    final Map<String, dynamic> result = await ApiService.getRiderDashboard();

    if (!mounted) {
      return;
    }

    if (result['success'] == true) {
      final dynamic riderData = result['rider'];

      if (riderData is Map) {
        final Map<String, dynamic> rider = Map<String, dynamic>.from(riderData);

        final String? apiName = rider['name']?.toString().trim();

        final String? apiEmail = rider['email']?.toString().trim();

        final String? apiPhone =
            rider['mobile_number']?.toString().trim() ??
            rider['phone']?.toString().trim();

        if (apiName != null && apiName.isNotEmpty) {
          _name = apiName;
        }

        if (apiEmail != null && apiEmail.isNotEmpty) {
          _email = apiEmail;
        }

        if (apiPhone != null && apiPhone.isNotEmpty) {
          _phone = apiPhone;
        }

        _riderId = rider['id']?.toString() ?? widget.riderId ?? '';

        _vehicle =
            rider['vehicle_type']?.toString().trim() ?? widget.vehicle ?? '';

        _availabilityStatus =
            rider['availability_status']?.toString().trim() ?? '';
      }

      setState(() {
        _isLoading = false;
        _errorMessage = null;
      });

      return;
    }

    // Saved user information remains visible even if
    // the Rider dashboard API is unavailable.
    setState(() {
      _riderId = widget.riderId ?? '';
      _vehicle = widget.vehicle ?? '';

      _isLoading = false;

      _errorMessage =
          result['message']?.toString() ??
          'Rider information is not available yet.';
    });
  }

  // ============================================================
  // HELPERS
  // ============================================================

  String _displayValue(String value, {String fallback = 'Not available'}) {
    final String cleaned = value.trim();

    return cleaned.isEmpty ? fallback : cleaned;
  }

  String get _statusText {
    if (_availabilityStatus.trim().isEmpty) {
      return 'Status unavailable';
    }

    final String clean = _availabilityStatus.replaceAll('_', ' ').trim();

    return clean
        .split(RegExp(r'\s+'))
        .map(
          (String word) => word.isEmpty
              ? word
              : '${word[0].toUpperCase()}'
                    '${word.substring(1).toLowerCase()}',
        )
        .join(' ');
  }

  bool get _isOnline {
    final String status = _availabilityStatus.trim().toLowerCase();

    return status == 'online' || status == 'available' || status == 'active';
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

    setState(() {
      _isLoggingOut = true;
    });

    try {
      final String? token = await AuthStorage.getToken();

      if (token != null && token.trim().isNotEmpty) {
        await http.post(
          Uri.parse('${ApiService.baseUrl}/auth/logout'),
          headers: <String, String>{
            'Accept': 'application/json',
            'Authorization': 'Bearer ${token.trim()}',
          },
        );
      }
    } catch (_) {
      // Local logout must still continue even if
      // the server cannot be reached.
    }

    await AuthStorage.clearSession();

    if (!mounted) {
      return;
    }

    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const LoginPage()),
      (Route<dynamic> route) => false,
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

        title: const Text('My Profile', style: RiderStyle.appBarTitle),

        leading: IconButton(
          onPressed: () {
            Navigator.pop(context);
          },
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: RiderColors.textDark,
            size: 20,
          ),
        ),
      ),

      body: SafeArea(
        top: false,

        child: RefreshIndicator(
          onRefresh: _loadProfile,

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

                _infoCard(
                  icon: Icons.two_wheeler_outlined,
                  title: 'Vehicle Information',
                  value: _displayValue(_vehicle),
                  iconBackground: RiderColors.lightOrange,
                  iconColor: RiderColors.warning,
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
            onPressed: _loadProfile,
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

              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 7,
                ),

                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.14),

                  borderRadius: BorderRadius.circular(20),

                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.10),
                  ),
                ),

                child: Row(
                  mainAxisSize: MainAxisSize.min,

                  children: [
                    Icon(
                      Icons.circle,
                      color: _isOnline ? Colors.greenAccent : Colors.white54,
                      size: 9,
                    ),

                    const SizedBox(width: 6),

                    Text(
                      _statusText,

                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
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
