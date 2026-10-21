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
    setState(() {
      _isLoading = true;
    });

    final savedUser = await AuthStorage.getUser();

    if (!mounted) return;

    setState(() {
      _user = savedUser ?? <String, dynamic>{};

      _isLoading = false;
    });
  }

  // ============================================================
  // PROFILE VALUES
  // ============================================================

  String get _name {
    final value = _user['name']?.toString().trim();

    if (value == null || value.isEmpty) {
      return 'Logistics Staff';
    }

    return value;
  }

  String get _email {
    final value = _user['email']?.toString().trim();

    if (value == null || value.isEmpty) {
      return 'Not available';
    }

    return value;
  }

  String get _userId {
    final value = _user['id'];

    if (value == null || value.toString().trim().isEmpty) {
      return 'Not available';
    }

    return value.toString();
  }

  String get _role {
    final directRole = _user['role']?.toString().trim();

    if (directRole != null && directRole.isNotEmpty) {
      return _formatRole(directRole);
    }

    final roles = _user['roles'];

    if (roles is List && roles.isNotEmpty) {
      final firstRole = roles.first;

      if (firstRole is Map) {
        final roleName = firstRole['name']?.toString().trim();

        if (roleName != null && roleName.isNotEmpty) {
          return _formatRole(roleName);
        }
      }
    }

    return 'Logistics';
  }

  String _formatRole(String value) {
    return value
        .replaceAll('_', ' ')
        .split(' ')
        .map(
          (word) => word.isEmpty
              ? word
              : '${word[0].toUpperCase()}${word.substring(1)}',
        )
        .join(' ');
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: LogisticsColors.background,

      appBar: AppBar(
        backgroundColor: LogisticsColors.background,

        elevation: 0,

        surfaceTintColor: Colors.transparent,

        iconTheme: const IconThemeData(color: LogisticsColors.textDark),

        titleSpacing: 4,

        title: const Text(
          'Profile',

          style: TextStyle(
            color: LogisticsColors.textDark,

            fontSize: 20,

            fontWeight: FontWeight.w800,
          ),
        ),

        actions: [
          IconButton(
            tooltip: 'Refresh',

            onPressed: _isLoading ? null : _loadProfile,

            icon: const Icon(
              Icons.refresh_rounded,

              color: LogisticsColors.primary,
            ),
          ),

          const SizedBox(width: 6),
        ],
      ),

      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _loadProfile,

          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),

            padding: EdgeInsets.fromLTRB(width * 0.045, 4, width * 0.045, 30),

            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                if (_isLoading) ...[_loadingCard(), const SizedBox(height: 16)],

                _profileHeader(),

                const SizedBox(height: 24),

                // ============================================================
                // PERSONAL INFORMATION
                // ============================================================
                const Text(
                  'Personal Information',

                  style: LogisticsStyle.sectionTitle,
                ),

                const SizedBox(height: 12),

                _infoCard(
                  icon: Icons.person_outline_rounded,

                  title: 'Name',

                  value: _name,
                ),

                _infoCard(
                  icon: Icons.email_outlined,

                  title: 'Email Address',

                  value: _email,
                ),

                _infoCard(
                  icon: Icons.badge_outlined,

                  title: 'User ID',

                  value: _userId,
                ),

                _infoCard(
                  icon: Icons.account_circle_outlined,

                  title: 'Account Role',

                  value: _role,
                ),

                const SizedBox(height: 12),

                // ============================================================
                // LOGISTICS INFORMATION
                // ============================================================
                const Text(
                  'Logistics Information',

                  style: LogisticsStyle.sectionTitle,
                ),

                const SizedBox(height: 12),

                Container(
                  width: double.infinity,

                  padding: const EdgeInsets.all(16),

                  decoration: LogisticsStyle.card,

                  child: const Row(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      Icon(
                        Icons.info_outline_rounded,

                        color: LogisticsColors.primary,

                        size: 21,
                      ),

                      SizedBox(width: 10),

                      Expanded(
                        child: Text(
                          'Employee ID, department, hub, and other Logistics-specific information will appear once the Logistics backend API is available.',

                          style: LogisticsStyle.small,
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 24),

                // ============================================================
                // ACCOUNT
                // ============================================================
                const Text('Account', style: LogisticsStyle.sectionTitle),

                const SizedBox(height: 12),

                Container(
                  width: double.infinity,

                  decoration: LogisticsStyle.card,

                  child: Column(
                    children: [
                      _menuItem(
                        icon: Icons.settings_outlined,

                        title: 'Account Settings',

                        subtitle: 'Account settings are not available yet.',

                        onTap: () {
                          _showUnavailable(
                            'Account settings are not available yet.',
                          );
                        },
                      ),

                      _divider(),

                      _menuItem(
                        icon: Icons.lock_outline_rounded,

                        title: 'Security',

                        subtitle: 'Security settings are not available yet.',

                        onTap: () {
                          _showUnavailable(
                            'Security settings are not available yet.',
                          );
                        },
                      ),

                      _divider(),

                      _menuItem(
                        icon: Icons.help_outline_rounded,

                        title: 'Help Center',

                        subtitle: 'Help center is not available yet.',

                        onTap: () {
                          _showUnavailable('Help center is not available yet.');
                        },
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 24),

                // ============================================================
                // LOGOUT
                // ============================================================
                SizedBox(
                  width: double.infinity,

                  height: 52,

                  child: OutlinedButton.icon(
                    onPressed: _isLoggingOut ? null : _showLogoutDialog,

                    icon: _isLoggingOut
                        ? const SizedBox(
                            height: 19,

                            width: 19,

                            child: CircularProgressIndicator(
                              strokeWidth: 2,

                              color: LogisticsColors.danger,
                            ),
                          )
                        : const Icon(Icons.logout_rounded, size: 20),

                    label: Text(_isLoggingOut ? 'Logging out...' : 'Logout'),

                    style: OutlinedButton.styleFrom(
                      foregroundColor: LogisticsColors.danger,

                      backgroundColor: Colors.white,

                      elevation: 0,

                      side: const BorderSide(color: LogisticsColors.danger),

                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),

                      textStyle: const TextStyle(
                        fontSize: 14,

                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),

                const SizedBox(height: 14),

                const Center(
                  child: Text(
                    'BiliHub Logistics',

                    style: TextStyle(
                      color: LogisticsColors.textLight,

                      fontSize: 10,

                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ],
            ),
          ),
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

      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,

          end: Alignment.bottomRight,

          colors: [LogisticsColors.primary, LogisticsColors.primaryDark],
        ),

        borderRadius: BorderRadius.circular(24),

        boxShadow: [
          BoxShadow(
            color: LogisticsColors.primary.withValues(alpha: 0.22),

            blurRadius: 20,

            offset: const Offset(0, 8),
          ),
        ],
      ),

      child: Stack(
        children: [
          Positioned(
            top: -35,

            right: -25,

            child: Container(
              height: 120,

              width: 120,

              decoration: BoxDecoration(
                shape: BoxShape.circle,

                color: Colors.white.withValues(alpha: 0.08),
              ),
            ),
          ),

          Positioned(
            bottom: -45,

            left: -30,

            child: Container(
              height: 110,

              width: 110,

              decoration: BoxDecoration(
                shape: BoxShape.circle,

                color: Colors.white.withValues(alpha: 0.06),
              ),
            ),
          ),

          Padding(
            padding: const EdgeInsets.all(22),

            child: Column(
              children: [
                Container(
                  height: 92,

                  width: 92,

                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.16),

                    shape: BoxShape.circle,

                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.75),

                      width: 2,
                    ),
                  ),

                  child: const Icon(
                    Icons.badge_rounded,

                    color: Colors.white,

                    size: 50,
                  ),
                ),

                const SizedBox(height: 16),

                Text(
                  _name,

                  textAlign: TextAlign.center,

                  maxLines: 2,

                  overflow: TextOverflow.ellipsis,

                  style: const TextStyle(
                    color: Colors.white,

                    fontSize: 22,

                    fontWeight: FontWeight.w800,
                  ),
                ),

                const SizedBox(height: 6),

                Text(
                  _email,

                  textAlign: TextAlign.center,

                  maxLines: 1,

                  overflow: TextOverflow.ellipsis,

                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.78),

                    fontSize: 12,
                  ),
                ),

                const SizedBox(height: 14),

                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,

                    vertical: 6,
                  ),

                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.14),

                    borderRadius: BorderRadius.circular(20),

                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.18),
                    ),
                  ),

                  child: Text(
                    _role.toUpperCase(),

                    style: const TextStyle(
                      color: Colors.white,

                      fontSize: 10,

                      fontWeight: FontWeight.w800,

                      letterSpacing: 0.5,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // LOADING
  // ============================================================

  Widget _loadingCard() {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.all(16),

      decoration: LogisticsStyle.card,

      child: const Row(
        children: [
          SizedBox(
            height: 22,

            width: 22,

            child: CircularProgressIndicator(
              strokeWidth: 2.2,

              color: LogisticsColors.primary,
            ),
          ),

          SizedBox(width: 12),

          Expanded(
            child: Text('Loading profile...', style: LogisticsStyle.small),
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
  }) {
    return Container(
      width: double.infinity,

      margin: const EdgeInsets.only(bottom: 10),

      padding: const EdgeInsets.all(15),

      decoration: LogisticsStyle.card,

      child: Row(
        children: [
          Container(
            height: 44,

            width: 44,

            decoration: BoxDecoration(
              color: LogisticsColors.lightPurple,

              borderRadius: BorderRadius.circular(13),
            ),

            child: Icon(icon, color: LogisticsColors.primary, size: 22),
          ),

          const SizedBox(width: 13),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(
                  title,

                  style: const TextStyle(
                    color: LogisticsColors.textGrey,

                    fontSize: 11,

                    fontWeight: FontWeight.w500,
                  ),
                ),

                const SizedBox(height: 4),

                Text(
                  value,

                  maxLines: 2,

                  overflow: TextOverflow.ellipsis,

                  style: LogisticsStyle.cardTitle,
                ),
              ],
            ),
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
    return Material(
      color: Colors.transparent,

      child: InkWell(
        onTap: onTap,

        borderRadius: BorderRadius.circular(18),

        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 13),

          child: Row(
            children: [
              Container(
                height: 43,

                width: 43,

                decoration: BoxDecoration(
                  color: LogisticsColors.lightPurple,

                  borderRadius: BorderRadius.circular(12),
                ),

                child: Icon(icon, color: LogisticsColors.primary, size: 21),
              ),

              const SizedBox(width: 13),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Text(title, style: LogisticsStyle.cardTitle),

                    const SizedBox(height: 3),

                    Text(
                      subtitle,

                      maxLines: 1,

                      overflow: TextOverflow.ellipsis,

                      style: const TextStyle(
                        color: LogisticsColors.textGrey,

                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(width: 8),

              const Icon(
                Icons.chevron_right_rounded,

                color: LogisticsColors.textLight,

                size: 22,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _divider() {
    return const Padding(
      padding: EdgeInsets.only(left: 71),

      child: Divider(height: 1, thickness: 0.7, color: LogisticsColors.border),
    );
  }

  // ============================================================
  // UNAVAILABLE
  // ============================================================

  void _showUnavailable(String message) {
    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(behavior: SnackBarBehavior.floating, content: Text(message)),
    );
  }

  // ============================================================
  // LOGOUT DIALOG
  // ============================================================

  Future<void> _showLogoutDialog() async {
    final shouldLogout = await showDialog<bool>(
      context: context,

      builder: (dialogContext) {
        return AlertDialog(
          backgroundColor: Colors.white,

          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(22),
          ),

          title: const Text(
            'Logout',

            style: TextStyle(
              color: LogisticsColors.textDark,

              fontSize: 18,

              fontWeight: FontWeight.w800,
            ),
          ),

          content: const Text(
            'Are you sure you want to logout from your logistics account?',

            style: TextStyle(
              color: LogisticsColors.textGrey,

              fontSize: 13,

              height: 1.45,
            ),
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
                backgroundColor: LogisticsColors.danger,

                foregroundColor: Colors.white,
              ),

              child: const Text('Logout'),
            ),
          ],
        );
      },
    );

    if (shouldLogout != true || !mounted) {
      return;
    }

    await _logout();
  }

  // ============================================================
  // LOGOUT
  // ============================================================

  Future<void> _logout() async {
    setState(() {
      _isLoggingOut = true;
    });

    try {
      await AuthStorage.clearSession();

      if (!mounted) return;

      Navigator.of(context).popUntil((route) => route.isFirst);
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isLoggingOut = false;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,

          content: Text('Unable to logout: $e'),
        ),
      );
    }
  }
}
