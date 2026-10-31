import 'package:flutter/material.dart';

import '../../sevices/api_services.dart';
import '../../sevices/auth_storage.dart';
import '../../styles/logistics_style.dart';
import '../auth/login_page.dart';

class LogisticsProfile extends StatefulWidget {
  const LogisticsProfile({super.key});

  @override
  State<LogisticsProfile> createState() => _LogisticsProfileState();
}

class _LogisticsProfileState extends State<LogisticsProfile> {
  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isLoggingOut = false;

  String? _errorMessage;

  Map<String, dynamic> _user = <String, dynamic>{};
  Map<String, dynamic> _logisticData = <String, dynamic>{};

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
      final Map<String, dynamic>? savedUser = await AuthStorage.getUser();

      final Map<String, dynamic> dashboardResult =
          await ApiService.getLogisticsDashboard();

      if (!mounted) {
        return;
      }

      final Map<String, dynamic> saved = savedUser == null
          ? <String, dynamic>{}
          : Map<String, dynamic>.from(savedUser);

      final Map<String, dynamic> dashboardData = _extractDashboardData(
        dashboardResult,
      );

      final Map<String, dynamic> dashboardLogistic = _asMap(
        dashboardData['logistic'],
      );

      final Map<String, dynamic> savedLogistic = _extractSavedLogistic(saved);

      final Map<String, dynamic> mergedLogistic = <String, dynamic>{
        ...savedLogistic,
        ...dashboardLogistic,
      };

      setState(() {
        _user = saved;
        _logisticData = mergedLogistic;

        _isLoading = false;
        _isRefreshing = false;

        if (dashboardResult['success'] == true) {
          _errorMessage = null;
        } else {
          final String message =
              dashboardResult['message']?.toString().trim() ?? '';

          _errorMessage = message.isNotEmpty
              ? message
              : 'Live logistics information is unavailable. '
                    'Showing saved account information.';
        }
      });
    } catch (error) {
      debugPrint('Logistics profile error: $error');

      try {
        final Map<String, dynamic>? savedUser = await AuthStorage.getUser();

        if (!mounted) {
          return;
        }

        final Map<String, dynamic> saved = savedUser == null
            ? <String, dynamic>{}
            : Map<String, dynamic>.from(savedUser);

        setState(() {
          _user = saved;
          _logisticData = _extractSavedLogistic(saved);

          _isLoading = false;
          _isRefreshing = false;

          _errorMessage =
              'Unable to refresh live logistics information. '
              'Showing saved account information.';
        });
      } catch (storageError) {
        debugPrint('Saved logistics profile error: $storageError');

        if (!mounted) {
          return;
        }

        setState(() {
          _isLoading = false;
          _isRefreshing = false;

          _errorMessage = 'Unable to load profile information.';
        });
      }
    }
  }

  // ============================================================
  // RESPONSE PARSING
  // ============================================================

  Map<String, dynamic> _extractDashboardData(Map<String, dynamic> result) {
    final dynamic rawData = result['data'];

    if (rawData is Map) {
      return Map<String, dynamic>.from(rawData);
    }

    return Map<String, dynamic>.from(result);
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }

    return <String, dynamic>{};
  }

  Map<String, dynamic> _extractSavedLogistic(Map<String, dynamic> user) {
    final dynamic logistic = user['logistic'];

    if (logistic is Map) {
      return Map<String, dynamic>.from(logistic);
    }

    return <String, dynamic>{};
  }

  // ============================================================
  // VALUE HELPERS
  // ============================================================

  String _value(dynamic value, {String fallback = '—'}) {
    final String text = value?.toString().trim() ?? '';

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return fallback;
    }

    return text;
  }

  String _firstValue(List<dynamic> values, {String fallback = '—'}) {
    for (final dynamic value in values) {
      final String text = value?.toString().trim() ?? '';

      if (text.isNotEmpty && text.toLowerCase() != 'null') {
        return text;
      }
    }

    return fallback;
  }

  String _capitalizeWords(String value) {
    final String cleaned = value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .trim();

    if (cleaned.isEmpty) {
      return cleaned;
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

  // ============================================================
  // USER DATA
  // ============================================================

  String get _name {
    return _firstValue(<dynamic>[
      _user['name'],
      _user['full_name'],
      _logisticData['contact_person'],
    ], fallback: 'Logistics Staff');
  }

  String get _email {
    return _firstValue(<dynamic>[
      _user['email'],
      _logisticData['email'],
    ], fallback: 'Email not available');
  }

  String get _phone {
    return _firstValue(<dynamic>[
      _user['phone'],
      _user['mobile_number'],
      _user['contact_number'],
      _logisticData['phone'],
    ], fallback: 'Not provided');
  }

  String get _userId {
    return _firstValue(<dynamic>[_user['id'], _user['user_id']]);
  }

  // ============================================================
  // ROLE
  // ============================================================

  String get _role {
    final dynamic directRole = _user['role'];

    if (directRole != null && directRole.toString().trim().isNotEmpty) {
      return _capitalizeWords(directRole.toString());
    }

    final dynamic roles = _user['roles'];

    if (roles is List && roles.isNotEmpty) {
      final dynamic firstRole = roles.first;

      if (firstRole is Map) {
        final dynamic roleName = firstRole['name'];

        if (roleName != null && roleName.toString().trim().isNotEmpty) {
          return _capitalizeWords(roleName.toString());
        }
      } else if (firstRole != null) {
        return _capitalizeWords(firstRole.toString());
      }
    }

    return 'Logistics';
  }

  // ============================================================
  // ACCOUNT STATUS
  // ============================================================

  String get _accountStatus {
    final String value = _firstValue(<dynamic>[
      _user['status'],
      _user['account_status'],
    ], fallback: 'active');

    return _capitalizeWords(value);
  }

  bool get _isAccountActive {
    final String status = _accountStatus.trim().toLowerCase().replaceAll(
      ' ',
      '_',
    );

    return status == 'active' || status == 'approved' || status == 'verified';
  }

  // ============================================================
  // LOGISTICS STATUS
  // ============================================================

  String get _logisticsStatus {
    final String value = _firstValue(<dynamic>[
      _logisticData['status'],
      _user['logistic_status'],
    ], fallback: 'approved');

    return _capitalizeWords(value);
  }

  bool get _isLogisticsApproved {
    final String status = _logisticsStatus.trim().toLowerCase().replaceAll(
      ' ',
      '_',
    );

    return status == 'approved' || status == 'active' || status == 'verified';
  }

  // ============================================================
  // COMPANY DATA
  // ============================================================

  String get _companyName {
    return _firstValue(<dynamic>[
      _logisticData['company_name'],
      _user['company_name'],
    ], fallback: 'Logistics Company');
  }

  String get _contactPerson {
    return _firstValue(<dynamic>[
      _logisticData['contact_person'],
      _user['name'],
    ], fallback: 'Not provided');
  }

  String get _companyEmail {
    return _firstValue(<dynamic>[
      _logisticData['email'],
      _user['email'],
    ], fallback: 'Not provided');
  }

  String get _companyPhone {
    return _firstValue(<dynamic>[
      _logisticData['phone'],
      _user['phone'],
      _user['mobile_number'],
    ], fallback: 'Not provided');
  }

  String get _companyAddress {
    return _firstValue(<dynamic>[
      _logisticData['address'],
      _logisticData['business_address'],
    ], fallback: 'Not provided');
  }

  String get _logisticId {
    return _firstValue(<dynamic>[
      _logisticData['id'],
      _user['logistic_id'],
    ], fallback: 'Not available');
  }

  // ============================================================
  // AVATAR
  // ============================================================

  String get _avatarLetter {
    final String name = _name.trim();

    if (name.isEmpty) {
      return 'L';
    }

    return name.substring(0, 1).toUpperCase();
  }

  // ============================================================
  // LOGOUT DIALOG
  // ============================================================

  Future<void> _showLogoutDialog() async {
    if (_isLoggingOut) {
      return;
    }

    final bool? shouldLogout = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Text('Logout', style: LogisticsStyle.sectionTitle),
          content: const Text(
            'Are you sure you want to log out '
            'of your logistics account?',
            style: LogisticsStyle.small,
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext, false);
              },
              child: const Text('Cancel'),
            ),
            ElevatedButton.icon(
              onPressed: () {
                Navigator.pop(dialogContext, true);
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: LogisticsColors.danger,
                foregroundColor: Colors.white,
              ),
              icon: const Icon(Icons.logout_rounded),
              label: const Text('Logout'),
            ),
          ],
        );
      },
    );

    if (!mounted || shouldLogout != true) {
      return;
    }

    await _logout();
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
      debugPrint('Logistics logout error: $error');

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
            backgroundColor: LogisticsColors.danger,
            content: Text('Unable to logout. Please try again.'),
          ),
        );
    }
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: LogisticsColors.background,
      appBar: AppBar(
        backgroundColor: LogisticsColors.background,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        automaticallyImplyLeading: false,
        title: const Text('Profile', style: LogisticsStyle.title),
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
                    color: LogisticsColors.primary,
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
                color: LogisticsColors.primary,
              ),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () {
          return _loadProfile(refresh: true);
        },
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 30),
          children: [
            if (_isLoading)
              const LinearProgressIndicator(color: LogisticsColors.primary),

            if (_errorMessage != null) ...[
              const SizedBox(height: 12),
              _errorCard(),
            ],

            const SizedBox(height: 12),

            _profileHeader(),

            const SizedBox(height: 22),

            const Text(
              'Account Information',
              style: LogisticsStyle.sectionTitle,
            ),

            const SizedBox(height: 12),

            _accountInformationCard(),

            const SizedBox(height: 22),

            const Text(
              'Company Information',
              style: LogisticsStyle.sectionTitle,
            ),

            const SizedBox(height: 12),

            _companyInformationCard(),

            const SizedBox(height: 22),

            const Text('Session', style: LogisticsStyle.sectionTitle),

            const SizedBox(height: 12),

            _sessionCard(),

            const SizedBox(height: 24),

            _logoutButton(),

            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // PROFILE HEADER
  // ============================================================

  Widget _profileHeader() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: LogisticsStyle.elevatedCard,
      child: Column(
        children: [
          CircleAvatar(
            radius: 40,
            backgroundColor: LogisticsColors.lightPurple,
            child: Text(
              _avatarLetter,
              style: const TextStyle(
                fontSize: 29,
                fontWeight: FontWeight.w800,
                color: LogisticsColors.primary,
              ),
            ),
          ),

          const SizedBox(height: 13),

          Text(
            _name,
            textAlign: TextAlign.center,
            style: LogisticsStyle.sectionTitle,
          ),

          const SizedBox(height: 4),

          Text(
            _email,
            textAlign: TextAlign.center,
            style: LogisticsStyle.small,
          ),

          const SizedBox(height: 10),

          Wrap(
            alignment: WrapAlignment.center,
            spacing: 8,
            runSpacing: 8,
            children: [
              _statusBadge(
                label: _role.toUpperCase(),
                color: LogisticsColors.primary,
                icon: Icons.badge_outlined,
              ),

              _statusBadge(
                label: _logisticsStatus,
                color: _isLogisticsApproved
                    ? LogisticsColors.success
                    : LogisticsColors.warning,
                icon: _isLogisticsApproved
                    ? Icons.verified_rounded
                    : Icons.schedule_rounded,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _statusBadge({
    required String label,
    required Color color,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
      decoration: LogisticsStyle.statusBadge(color: color),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 10,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // ACCOUNT INFORMATION
  // ============================================================

  Widget _accountInformationCard() {
    return Container(
      decoration: LogisticsStyle.card,
      child: Column(
        children: [
          _info(Icons.badge_outlined, 'User ID', _userId),

          const Divider(height: 1),

          _info(Icons.email_outlined, 'Email', _email),

          const Divider(height: 1),

          _info(Icons.phone_outlined, 'Phone', _phone),

          const Divider(height: 1),

          _info(Icons.verified_user_outlined, 'Account Status', _accountStatus),

          const Divider(height: 1),

          _info(Icons.approval_outlined, 'Logistics Status', _logisticsStatus),

          const Divider(height: 1),

          _info(
            _isAccountActive
                ? Icons.check_circle_outline_rounded
                : Icons.warning_amber_rounded,
            'Access',
            _isAccountActive ? 'Active' : 'Restricted',
          ),
        ],
      ),
    );
  }

  // ============================================================
  // COMPANY INFORMATION
  // ============================================================

  Widget _companyInformationCard() {
    return Container(
      decoration: LogisticsStyle.card,
      child: Column(
        children: [
          _info(Icons.business_outlined, 'Company', _companyName),

          const Divider(height: 1),

          _info(Icons.fingerprint_rounded, 'Logistics ID', _logisticId),

          const Divider(height: 1),

          _info(Icons.person_outline_rounded, 'Contact Person', _contactPerson),

          const Divider(height: 1),

          _info(Icons.email_outlined, 'Company Email', _companyEmail),

          const Divider(height: 1),

          _info(Icons.phone_in_talk_outlined, 'Company Phone', _companyPhone),

          const Divider(height: 1),

          _info(Icons.location_on_outlined, 'Address', _companyAddress),
        ],
      ),
    );
  }

  // ============================================================
  // SESSION
  // ============================================================

  Widget _sessionCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: LogisticsStyle.card,
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.security_outlined,
            color: LogisticsColors.primary,
            size: 22,
          ),

          SizedBox(width: 11),

          Expanded(
            child: Text(
              'This device uses the authenticated '
              'BiliHub logistics session for protected '
              'parcel, hub, sorting, and routing operations.',
              style: LogisticsStyle.small,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // INFO TILE
  // ============================================================

  Widget _info(IconData icon, String title, String value) {
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 3),
      leading: Container(
        height: 40,
        width: 40,
        decoration: BoxDecoration(
          color: LogisticsColors.lightPurple,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Icon(icon, color: LogisticsColors.primary, size: 21),
      ),
      title: Text(title, style: LogisticsStyle.small),
      subtitle: Padding(
        padding: const EdgeInsets.only(top: 3),
        child: Text(_value(value), style: LogisticsStyle.cardTitle),
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
              _errorMessage ?? 'Unable to load profile information.',
              style: LogisticsStyle.small,
            ),
          ),

          IconButton(
            tooltip: 'Retry',
            onPressed: () {
              _loadProfile(refresh: true);
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

  // ============================================================
  // LOGOUT BUTTON
  // ============================================================

  Widget _logoutButton() {
    return SizedBox(
      width: double.infinity,
      height: 50,
      child: OutlinedButton.icon(
        onPressed: _isLoggingOut ? null : _showLogoutDialog,
        icon: _isLoggingOut
            ? const SizedBox(
                height: 18,
                width: 18,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: LogisticsColors.danger,
                ),
              )
            : const Icon(Icons.logout_rounded),
        label: Text(_isLoggingOut ? 'Logging out...' : 'Logout'),
        style: OutlinedButton.styleFrom(
          foregroundColor: LogisticsColors.danger,
          backgroundColor: LogisticsColors.white,
          side: BorderSide(
            color: LogisticsColors.danger.withValues(alpha: 0.60),
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
    );
  }
}
