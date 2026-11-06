import 'dart:async';

import 'package:flutter/material.dart';

import '../../services/api_services.dart';
import '../../services/auth_storage.dart';
import '../../styles/logistics_style.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_loading_state.dart';

import '../auth/login_page.dart';

class LogisticsProfile extends StatefulWidget {
  const LogisticsProfile({super.key});

  @override
  State<LogisticsProfile> createState() => _LogisticsProfileState();
}

class _LogisticsProfileState extends State<LogisticsProfile> {
  static const Duration _requestTimeout = Duration(seconds: 25);

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isFetching = false;
  bool _isLoggingOut = false;

  bool _hasProfile = false;
  bool _isServerVerified = false;

  String? _errorMessage;

  Map<String, dynamic> _user = <String, dynamic>{};
  Map<String, dynamic> _logisticData = <String, dynamic>{};

  int _requestId = 0;

  @override
  void initState() {
    super.initState();
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
    if (!mounted || _isFetching || _isLoggingOut) {
      return;
    }

    final int requestId = ++_requestId;

    setState(() {
      _isFetching = true;
      _isLoading = !_hasProfile;
      _isRefreshing = _hasProfile || refresh;
      _errorMessage = null;
    });

    // Read saved information and Laravel independently.
    // A failure in one must not discard the other.
    Map<String, dynamic>? savedUser;
    Map<String, dynamic>? dashboardData;

    String? storageError;
    String? dashboardError;

    try {
      savedUser = await AuthStorage.getUser().timeout(_requestTimeout);
    } on TimeoutException {
      storageError = 'Reading saved account data timed out.';
    } catch (error) {
      debugPrint('Logistics saved user error: $error');
      storageError = 'Saved account information is unavailable.';
    }

    if (!mounted || requestId != _requestId) return;

    if (savedUser != null) {
      final Map<String, dynamic> saved = Map<String, dynamic>.from(savedUser);

      setState(() {
        _user = _mergeNonEmpty(_user, saved);

        _logisticData = _mergeNonEmpty(
          _logisticData,
          _extractSavedLogistic(saved),
        );

        _hasProfile = _hasProfileInformation();
      });
    }

    try {
      final Map<String, dynamic> response =
          await ApiService.getLogisticsDashboard().timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) return;

      if (response['success'] == true) {
        dashboardData = _extractDashboardData(response);
      } else {
        dashboardError = _responseMessage(
          response,
          'Unable to load current logistics information.',
        );
      }
    } on TimeoutException {
      dashboardError = 'The logistics server took too long to respond.';
    } catch (error) {
      debugPrint('Logistics dashboard profile error: $error');

      dashboardError = 'Unable to connect to the BiliHub logistics server.';
    }

    if (!mounted || requestId != _requestId) return;

    if (dashboardData != null) {
      final Map<String, dynamic> liveLogistic = _asMap(
        dashboardData['logistic'],
      );

      final Map<String, dynamic> liveUser = _asMap(dashboardData['user']);

      setState(() {
        _logisticData = _mergeNonEmpty(_logisticData, liveLogistic);

        if (liveUser.isNotEmpty) {
          _user = _mergeNonEmpty(_user, liveUser);
        }

        _isServerVerified = liveLogistic.isNotEmpty;
        _hasProfile = _hasProfileInformation();

        if (liveLogistic.isEmpty) {
          dashboardError =
              'The dashboard did not include current '
              'logistics company information.';
        }
      });
    } else {
      _isServerVerified = false;
    }

    if (!mounted || requestId != _requestId) return;

    setState(() {
      _isFetching = false;
      _isLoading = false;
      _isRefreshing = false;
      _hasProfile = _hasProfileInformation();

      _errorMessage = dashboardError ?? (_hasProfile ? null : storageError);
    });
  }

  // ============================================================
  // RESPONSE HELPERS
  // ============================================================

  Map<String, dynamic> _extractDashboardData(Map<String, dynamic> response) {
    final dynamic data = response['data'];

    if (data is Map) {
      return Map<String, dynamic>.from(data);
    }

    return Map<String, dynamic>.from(response);
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

    final dynamic profile = user['profile'];

    if (profile is Map && profile['logistic'] is Map) {
      return Map<String, dynamic>.from(profile['logistic']);
    }

    return <String, dynamic>{};
  }

  // Preserve meaningful old fields when a newer response
  // contains null or empty values.
  Map<String, dynamic> _mergeNonEmpty(
    Map<String, dynamic> previous,
    Map<String, dynamic> incoming,
  ) {
    final Map<String, dynamic> merged = Map<String, dynamic>.from(previous);

    for (final MapEntry<String, dynamic> entry in incoming.entries) {
      final dynamic value = entry.value;

      if (value == null) continue;

      if (value is String &&
          (value.trim().isEmpty || value.trim().toLowerCase() == 'null')) {
        continue;
      }

      merged[entry.key] = value;
    }

    return merged;
  }

  String _responseMessage(Map<String, dynamic> result, String fallback) {
    final String message = result['message']?.toString().trim() ?? '';

    return message.isNotEmpty ? message : fallback;
  }

  bool _hasProfileInformation() {
    return _user.isNotEmpty || _logisticData.isNotEmpty;
  }

  // ============================================================
  // VALUE HELPERS
  // ============================================================

  String _clean(dynamic value) {
    final String text = value?.toString().trim() ?? '';

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return '';
    }

    return text;
  }

  String _firstValue(
    List<dynamic> values, {
    String fallback = 'Not available',
  }) {
    for (final dynamic value in values) {
      final String text = _clean(value);

      if (text.isNotEmpty) return text;
    }

    return fallback;
  }

  String _capitalizeWords(String value) {
    final String cleaned = value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .trim();

    if (cleaned.isEmpty) return 'Not available';

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

  String _normalizedStatus(String status) {
    return status
        .trim()
        .toLowerCase()
        .replaceAll(' ', '_')
        .replaceAll('-', '_');
  }

  // ============================================================
  // ACCOUNT INFORMATION
  // ============================================================

  String get _name {
    return _firstValue([
      _user['name'],
      _user['full_name'],
      _logisticData['contact_person'],
    ], fallback: 'Logistics Staff');
  }

  String get _email {
    return _firstValue([_user['email'], _logisticData['email']]);
  }

  String get _phone {
    return _firstValue([
      _user['phone'],
      _user['mobile_number'],
      _user['contact_number'],
      _logisticData['phone'],
    ]);
  }

  String get _userId {
    return _firstValue([_user['id'], _user['user_id']]);
  }

  String get _role {
    final dynamic directRole = _user['role'];

    if (directRole is String && _clean(directRole).isNotEmpty) {
      return _capitalizeWords(directRole);
    }

    if (directRole is Map) {
      final String name = _clean(directRole['name']);

      if (name.isNotEmpty) {
        return _capitalizeWords(name);
      }
    }

    final dynamic roles = _user['roles'];

    if (roles is List) {
      for (final dynamic role in roles) {
        if (role is Map) {
          final String name = _clean(role['name']);

          if (name.isNotEmpty) {
            return _capitalizeWords(name);
          }
        } else {
          final String name = _clean(role);

          if (name.isNotEmpty) {
            return _capitalizeWords(name);
          }
        }
      }
    }

    return 'Not available';
  }

  String get _rawAccountStatus {
    return _firstValue([
      _user['account_status'],
      _user['status'],
    ], fallback: '');
  }

  String get _accountStatus {
    return _capitalizeWords(_rawAccountStatus);
  }

  bool get _isAccountApproved {
    final String status = _normalizedStatus(_rawAccountStatus);

    return status == 'active' || status == 'approved' || status == 'verified';
  }

  String get _rawLogisticsStatus {
    return _firstValue([
      _logisticData['status'],
      _user['logistic_status'],
    ], fallback: '');
  }

  String get _logisticsStatus {
    return _capitalizeWords(_rawLogisticsStatus);
  }

  bool get _isLogisticsApproved {
    final String status = _normalizedStatus(_rawLogisticsStatus);

    return status == 'approved' || status == 'active' || status == 'verified';
  }

  String get _accessStatus {
    if (_rawAccountStatus.isEmpty || _rawLogisticsStatus.isEmpty) {
      return 'Not available';
    }

    return _isAccountApproved && _isLogisticsApproved
        ? 'Approved'
        : 'Requires Review';
  }

  // ============================================================
  // COMPANY INFORMATION
  // ============================================================

  String get _companyName {
    return _firstValue([
      _logisticData['company_name'],
      _user['company_name'],
    ], fallback: 'Logistics Company');
  }

  String get _contactPerson {
    return _firstValue([_logisticData['contact_person'], _user['name']]);
  }

  String get _companyEmail {
    return _firstValue([_logisticData['email'], _user['email']]);
  }

  String get _companyPhone {
    return _firstValue([
      _logisticData['phone'],
      _user['phone'],
      _user['mobile_number'],
    ]);
  }

  String get _companyAddress {
    return _firstValue([
      _logisticData['address'],
      _logisticData['business_address'],
    ]);
  }

  String get _logisticId {
    return _firstValue([_logisticData['id'], _user['logistic_id']]);
  }

  String get _avatarLetter {
    final String name = _name.trim();

    if (name.isEmpty) return 'L';

    return name.substring(0, 1).toUpperCase();
  }

  // ============================================================
  // LOGOUT
  // ============================================================

  Future<void> _showLogoutDialog() async {
    if (_isLoggingOut) return;

    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Text('Logout', style: LogisticsStyle.sectionTitle),
          content: const Text(
            'Are you sure you want to log out '
            'of your Logistics account?',
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
              icon: const Icon(Icons.logout_rounded),
              label: const Text('Logout'),
              style: ElevatedButton.styleFrom(
                backgroundColor: LogisticsColors.danger,
                foregroundColor: Colors.white,
              ),
            ),
          ],
        );
      },
    );

    if (!mounted || confirmed != true) return;

    await _logout();
  }

  Future<void> _logout() async {
    if (!mounted || _isLoggingOut) return;

    setState(() {
      _isLoggingOut = true;
    });

    try {
      // Invalidate any pending profile response.
      _requestId++;

      // Clear the locally stored authentication session.
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
      debugPrint('Logistics logout error: $error');

      if (!mounted) return;

      setState(() {
        _isLoggingOut = false;
      });

      _showError('Unable to complete logout. Please try again.');
    }
  }

  void _showError(String message) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: LogisticsColors.danger,
          content: Text(message),
        ),
      );
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
          IconButton(
            tooltip: 'Refresh profile',
            onPressed: _isFetching || _isLoggingOut
                ? null
                : () => _loadProfile(refresh: true),
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
          onRefresh: () => _loadProfile(refresh: true),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: const EdgeInsets.fromLTRB(18, 12, 18, 30),
            children: [
              if (_isLoading && !_hasProfile) ...[
                const SizedBox(height: 110),
                const AppLoadingState(
                  message: 'Loading logistics profile...',
                  color: LogisticsColors.primary,
                ),
              ] else if (!_hasProfile) ...[
                const SizedBox(height: 75),
                AppErrorState(
                  title: 'Unable to Load Profile',
                  message:
                      _errorMessage ??
                      'Logistics account information '
                          'is currently unavailable.',
                  color: LogisticsColors.primary,
                  onRetry: () => _loadProfile(refresh: true),
                ),
                const SizedBox(height: 24),
                _logoutButton(),
              ] else ...[
                if (_isRefreshing) ...[
                  const LinearProgressIndicator(
                    color: LogisticsColors.primary,
                    minHeight: 2,
                  ),
                  const SizedBox(height: 14),
                ],

                if (_errorMessage != null) ...[
                  _errorBanner(),
                  const SizedBox(height: 14),
                ],

                if (!_isServerVerified) ...[
                  _savedDataNotice(),
                  const SizedBox(height: 14),
                ],

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
              ],
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // ERROR / SAVED DATA NOTICES
  // ============================================================

  Widget _errorBanner() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: LogisticsStyle.card,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.info_outline_rounded,
            color: LogisticsColors.warning,
            size: 21,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              _errorMessage ?? 'Unable to refresh logistics information.',
              style: LogisticsStyle.small,
            ),
          ),
          TextButton(
            onPressed: _isFetching ? null : () => _loadProfile(refresh: true),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _savedDataNotice() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: LogisticsColors.lightPurple,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: LogisticsColors.border),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.cloud_off_outlined,
            color: LogisticsColors.primary,
            size: 21,
          ),
          SizedBox(width: 10),
          Expanded(
            child: Text(
              'Showing available saved or previously '
              'loaded account information. '
              'Current company details have not been '
              'verified with Laravel in this refresh.',
              style: LogisticsStyle.small,
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
      padding: const EdgeInsets.all(20),
      decoration: LogisticsStyle.elevatedCard,
      child: Column(
        children: [
          CircleAvatar(
            radius: 42,
            backgroundColor: LogisticsColors.lightPurple,
            child: Text(
              _avatarLetter,
              style: const TextStyle(
                fontSize: 30,
                fontWeight: FontWeight.w800,
                color: LogisticsColors.primary,
              ),
            ),
          ),

          const SizedBox(height: 14),

          Text(
            _name,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: LogisticsStyle.sectionTitle,
          ),

          const SizedBox(height: 5),

          Text(
            _email,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: LogisticsStyle.small,
          ),

          const SizedBox(height: 14),

          Wrap(
            alignment: WrapAlignment.center,
            spacing: 8,
            runSpacing: 8,
            children: [
              _statusBadge(
                label: _role,
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
                    : Icons.info_outline_rounded,
              ),
            ],
          ),

          const SizedBox(height: 14),

          Text(
            _companyName,
            textAlign: TextAlign.center,
            style: LogisticsStyle.cardTitle,
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
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
      decoration: LogisticsStyle.statusBadge(color: color),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 6),
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
    return _infoSection(
      children: [
        _info(Icons.badge_outlined, 'User ID', _userId, selectable: true),
        _divider(),

        _info(Icons.person_outline_rounded, 'Full Name', _name),
        _divider(),

        _info(Icons.email_outlined, 'Email', _email),
        _divider(),

        _info(Icons.phone_outlined, 'Phone', _phone),
        _divider(),

        _info(Icons.work_outline_rounded, 'Role', _role),
        _divider(),

        _info(Icons.verified_user_outlined, 'Account Status', _accountStatus),
        _divider(),

        _info(Icons.approval_outlined, 'Logistics Status', _logisticsStatus),
        _divider(),

        _info(
          _accessStatus == 'Approved'
              ? Icons.check_circle_outline_rounded
              : Icons.info_outline_rounded,
          'Account Approval',
          _accessStatus,
        ),
      ],
    );
  }

  // ============================================================
  // COMPANY INFORMATION
  // ============================================================

  Widget _companyInformationCard() {
    return _infoSection(
      children: [
        _info(Icons.business_outlined, 'Company Name', _companyName),
        _divider(),

        _info(
          Icons.fingerprint_rounded,
          'Logistics ID',
          _logisticId,
          selectable: true,
        ),
        _divider(),

        _info(Icons.person_outline_rounded, 'Contact Person', _contactPerson),
        _divider(),

        _info(Icons.email_outlined, 'Company Email', _companyEmail),
        _divider(),

        _info(Icons.phone_in_talk_outlined, 'Company Phone', _companyPhone),
        _divider(),

        _info(Icons.location_on_outlined, 'Business Address', _companyAddress),
      ],
    );
  }

  // ============================================================
  // SESSION INFORMATION
  // ============================================================

  Widget _sessionCard() {
    return Container(
      width: double.infinity,
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
          SizedBox(width: 12),
          Expanded(
            child: Text(
              'This device uses your authenticated '
              'BiliHub session for protected logistics '
              'operations, including parcel processing, '
              'sorting, hub routing, and shipment tracking.',
              style: LogisticsStyle.small,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // SHARED INFORMATION WIDGETS
  // ============================================================

  Widget _infoSection({required List<Widget> children}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: LogisticsStyle.card,
      child: Column(children: children),
    );
  }

  Widget _info(
    IconData icon,
    String title,
    String value, {
    bool selectable = false,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 9),
      child: AppInfoRow(
        label: title,
        value: value,
        icon: icon,
        iconColor: LogisticsColors.primary,
        selectable: selectable,
      ),
    );
  }

  Widget _divider() {
    return const Divider(
      height: 1,
      thickness: 1,
      color: LogisticsColors.border,
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
            : const Icon(Icons.logout_rounded, size: 20),
        label: Text(
          _isLoggingOut ? 'Logging out...' : 'Logout',
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
        ),
        style: OutlinedButton.styleFrom(
          foregroundColor: LogisticsColors.danger,
          backgroundColor: LogisticsColors.white,
          side: BorderSide(
            color: LogisticsColors.danger.withValues(alpha: 0.40),
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
    );
  }
}
