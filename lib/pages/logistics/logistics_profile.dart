import 'package:flutter/material.dart';

import '../../sevices/auth_storage.dart';
import '../../styles/logistics_style.dart';

class LogisticsProfile extends StatefulWidget {
  const LogisticsProfile({super.key});

  @override
  State<LogisticsProfile> createState() => _LogisticsProfileState();
}

class _LogisticsProfileState extends State<LogisticsProfile> {
  bool _isLoading = true;
  bool _isLoggingOut = false;

  String? _errorMessage;

  Map<String, dynamic> _user = <String, dynamic>{};

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

    try {
      final Map<String, dynamic>? user = await AuthStorage.getUser();

      if (!mounted) return;

      setState(() {
        _user = user ?? <String, dynamic>{};
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isLoading = false;
        _errorMessage = 'Unable to load profile information: $e';
      });
    }
  }

  // ============================================================
  // LOGISTICS DATA
  // ============================================================

  Map<String, dynamic> get _logistic {
    final dynamic value = _user['logistic'];

    if (value is Map) {
      return Map<String, dynamic>.from(value);
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

  String _capitalizeWords(String value) {
    final String cleaned = value.replaceAll('_', ' ').trim();

    if (cleaned.isEmpty) {
      return cleaned;
    }

    return cleaned
        .split(RegExp(r'\s+'))
        .map((String word) {
          if (word.isEmpty) {
            return word;
          }

          return '${word[0].toUpperCase()}${word.substring(1).toLowerCase()}';
        })
        .join(' ');
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
        final dynamic name = firstRole['name'];

        if (name != null && name.toString().trim().isNotEmpty) {
          return _capitalizeWords(name.toString());
        }
      } else if (firstRole != null) {
        return _capitalizeWords(firstRole.toString());
      }
    }

    return 'Logistics';
  }

  // ============================================================
  // LOGISTICS STATUS
  // ============================================================

  String get _logisticsStatus {
    final dynamic topLevel = _user['logistic_status'];

    if (topLevel != null && topLevel.toString().trim().isNotEmpty) {
      return _capitalizeWords(topLevel.toString());
    }

    final dynamic nestedStatus = _logistic['status'];

    if (nestedStatus != null && nestedStatus.toString().trim().isNotEmpty) {
      return _capitalizeWords(nestedStatus.toString());
    }

    return 'Approved';
  }

  // ============================================================
  // ACCOUNT STATUS
  // ============================================================

  String get _accountStatus {
    return _capitalizeWords(_value(_user['status'], fallback: 'active'));
  }

  // ============================================================
  // COMPANY NAME
  // ============================================================

  String get _companyName {
    final String nested = _value(_logistic['company_name'], fallback: '');

    if (nested.isNotEmpty) {
      return nested;
    }

    return _value(_user['company_name'], fallback: 'Logistics Company');
  }

  // ============================================================
  // AVATAR LETTER
  // ============================================================

  String get _avatarLetter {
    final String name = _value(_user['name'], fallback: 'L');

    if (name.isEmpty) {
      return 'L';
    }

    return name.substring(0, 1).toUpperCase();
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

        title: const Text('Profile', style: LogisticsStyle.title),

        actions: [
          IconButton(
            tooltip: 'Refresh profile',
            onPressed: _isLoading ? null : _loadProfile,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),

      body: RefreshIndicator(
        onRefresh: _loadProfile,

        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),

          padding: const EdgeInsets.all(18),

          children: [
            if (_isLoading)
              const LinearProgressIndicator(color: LogisticsColors.primary),

            if (_errorMessage != null) ...[
              const SizedBox(height: 12),

              _errorCard(),
            ],

            const SizedBox(height: 12),

            // ====================================================
            // PROFILE HEADER
            // ====================================================
            Container(
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
                    _value(_user['name'], fallback: 'Logistics Staff'),

                    textAlign: TextAlign.center,

                    style: LogisticsStyle.sectionTitle,
                  ),

                  const SizedBox(height: 4),

                  Text(
                    _value(_user['email'], fallback: 'Email not available'),

                    textAlign: TextAlign.center,

                    style: LogisticsStyle.small,
                  ),

                  const SizedBox(height: 10),

                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 6,
                    ),

                    decoration: LogisticsStyle.statusBadge(
                      color: LogisticsColors.success,
                    ),

                    child: Text(
                      _role.toUpperCase(),

                      style: const TextStyle(
                        color: LogisticsColors.success,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 22),

            // ====================================================
            // ACCOUNT INFORMATION
            // ====================================================
            const Text(
              'Account Information',

              style: LogisticsStyle.sectionTitle,
            ),

            const SizedBox(height: 12),

            Container(
              decoration: LogisticsStyle.card,

              child: Column(
                children: [
                  _info(Icons.badge_outlined, 'User ID', _value(_user['id'])),

                  const Divider(height: 1),

                  _info(Icons.email_outlined, 'Email', _value(_user['email'])),

                  const Divider(height: 1),

                  _info(
                    Icons.phone_outlined,
                    'Phone',
                    _value(
                      _user['phone'] ?? _user['mobile_number'],
                      fallback: 'Not provided',
                    ),
                  ),

                  const Divider(height: 1),

                  _info(
                    Icons.verified_user_outlined,
                    'Account Status',
                    _accountStatus,
                  ),

                  const Divider(height: 1),

                  _info(
                    Icons.approval_outlined,
                    'Logistics Status',
                    _logisticsStatus,
                  ),
                ],
              ),
            ),

            const SizedBox(height: 22),

            // ====================================================
            // COMPANY
            // ====================================================
            const Text(
              'Company Information',

              style: LogisticsStyle.sectionTitle,
            ),

            const SizedBox(height: 12),

            Container(
              decoration: LogisticsStyle.card,

              child: Column(
                children: [
                  _info(Icons.business_outlined, 'Company', _companyName),

                  const Divider(height: 1),

                  _info(
                    Icons.person_outline_rounded,
                    'Contact Person',
                    _value(
                      _logistic['contact_person'],
                      fallback: 'Not provided',
                    ),
                  ),

                  const Divider(height: 1),

                  _info(
                    Icons.email_outlined,
                    'Company Email',
                    _value(
                      _logistic['email'],
                      fallback: _value(
                        _user['email'],
                        fallback: 'Not provided',
                      ),
                    ),
                  ),

                  const Divider(height: 1),

                  _info(
                    Icons.phone_in_talk_outlined,
                    'Company Phone',
                    _value(_logistic['phone'], fallback: 'Not provided'),
                  ),

                  const Divider(height: 1),

                  _info(
                    Icons.location_on_outlined,
                    'Address',
                    _value(_logistic['address'], fallback: 'Not provided'),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 22),

            // ====================================================
            // SESSION
            // ====================================================
            const Text('Session', style: LogisticsStyle.sectionTitle),

            const SizedBox(height: 12),

            Container(
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
                      'Your logistics account is authenticated using the secure session saved on this device.',

                      style: LogisticsStyle.small,
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // ====================================================
            // LOGOUT
            // ====================================================
            SizedBox(
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

                  side: const BorderSide(color: LogisticsColors.danger),

                  padding: const EdgeInsets.symmetric(vertical: 14),

                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
              ),
            ),

            const SizedBox(height: 20),
          ],
        ),
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

        child: Text(value, style: LogisticsStyle.cardTitle),
      ),
    );
  }

  // ============================================================
  // LOGOUT DIALOG
  // ============================================================

  Future<void> _showLogoutDialog() async {
    final bool? shouldLogout = await showDialog<bool>(
      context: context,

      builder: (BuildContext dialogContext) {
        return AlertDialog(
          title: const Text('Logout'),

          content: const Text(
            'Are you sure you want to logout from your logistics account?',
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
            ),
          ],
        );
      },
    );

    if (shouldLogout == true && mounted) {
      await _logout();
    }
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

      if (!mounted) return;

      Navigator.of(context).popUntil((Route<dynamic> route) => route.isFirst);
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isLoggingOut = false;
      });

      ScaffoldMessenger.of(context).hideCurrentSnackBar();

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,

          backgroundColor: LogisticsColors.danger,

          content: Text('Unable to logout: $e'),
        ),
      );
    }
  }
}
