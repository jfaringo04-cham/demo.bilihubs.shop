import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

class LogisticsProfile extends StatelessWidget {
  final String name;
  final String email;
  final String employeeId;
  final String department;

  const LogisticsProfile({
    super.key,
    required this.name,
    required this.email,
    required this.employeeId,
    required this.department,
  });

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: LogisticsColors.background,

      // ==========================================================
      // APP BAR
      // ==========================================================
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
      ),

      // ==========================================================
      // BODY
      // ==========================================================
      body: SafeArea(
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),

          padding: EdgeInsets.fromLTRB(width * 0.045, 4, width * 0.045, 30),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ====================================================
              // PROFILE HEADER
              // ====================================================

              _profileHeader(),

              const SizedBox(height: 24),

              // ====================================================
              // PERSONAL INFORMATION
              // ====================================================
              const Text(
                'Personal Information',
                style: LogisticsStyle.sectionTitle,
              ),

              const SizedBox(height: 12),

              _infoCard(
                icon: Icons.badge_outlined,
                title: 'Employee ID',
                value: employeeId,
              ),

              _infoCard(
                icon: Icons.business_outlined,
                title: 'Department',
                value: department,
              ),

              _infoCard(
                icon: Icons.email_outlined,
                title: 'Email Address',
                value: email,
              ),

              const SizedBox(height: 12),

              // ====================================================
              // ACCOUNT
              // ====================================================
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
                      subtitle: 'Manage your account information',
                      onTap: () {},
                    ),

                    _divider(),

                    _menuItem(
                      icon: Icons.lock_outline_rounded,
                      title: 'Security',
                      subtitle: 'Password and security settings',
                      onTap: () {},
                    ),

                    _divider(),

                    _menuItem(
                      icon: Icons.help_outline_rounded,
                      title: 'Help Center',
                      subtitle: 'Get help and support',
                      onTap: () {},
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
                height: 52,

                child: OutlinedButton.icon(
                  onPressed: () {
                    _showLogoutDialog(context);
                  },

                  icon: const Icon(Icons.logout_rounded, size: 20),

                  label: const Text('Logout'),

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

              // ====================================================
              // APP VERSION
              // ====================================================
              const Center(
                child: Text(
                  'BiliHub Logistics • Version 1.0.0',
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
    );
  }

  // ==============================================================
  // PROFILE HEADER
  // ==============================================================

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
          // Decorative circle
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
                // Avatar
                Stack(
                  clipBehavior: Clip.none,

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

                    // Online indicator
                    Positioned(
                      right: 1,
                      bottom: 3,

                      child: Container(
                        height: 23,
                        width: 23,

                        decoration: BoxDecoration(
                          color: LogisticsColors.success,
                          shape: BoxShape.circle,

                          border: Border.all(
                            color: LogisticsColors.primaryDark,
                            width: 3,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 16),

                Text(
                  name,
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
                  email,
                  textAlign: TextAlign.center,

                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,

                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.78),
                    fontSize: 12,
                  ),
                ),

                const SizedBox(height: 14),

                // Active badge
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

                  child: const Row(
                    mainAxisSize: MainAxisSize.min,

                    children: [
                      Icon(Icons.circle, color: Colors.greenAccent, size: 8),

                      SizedBox(width: 6),

                      Text(
                        'ACTIVE STAFF',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ==============================================================
  // INFORMATION CARD
  // ==============================================================

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
          // Icon
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

          // Information
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

  // ==============================================================
  // MENU ITEM
  // ==============================================================

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

  // ==============================================================
  // DIVIDER
  // ==============================================================

  Widget _divider() {
    return const Padding(
      padding: EdgeInsets.only(left: 71),

      child: Divider(height: 1, thickness: 0.7, color: LogisticsColors.border),
    );
  }

  // ==============================================================
  // LOGOUT DIALOG
  // ==============================================================

  void _showLogoutDialog(BuildContext context) {
    showDialog(
      context: context,

      builder: (dialogContext) {
        return AlertDialog(
          backgroundColor: Colors.white,

          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(22),
          ),

          titlePadding: const EdgeInsets.fromLTRB(22, 22, 22, 0),

          contentPadding: const EdgeInsets.fromLTRB(22, 10, 22, 8),

          actionsPadding: const EdgeInsets.fromLTRB(16, 4, 16, 16),

          title: Row(
            children: [
              Container(
                height: 42,
                width: 42,

                decoration: BoxDecoration(
                  color: LogisticsColors.danger.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(12),
                ),

                child: const Icon(
                  Icons.logout_rounded,
                  color: LogisticsColors.danger,
                  size: 21,
                ),
              ),

              const SizedBox(width: 12),

              const Expanded(
                child: Text(
                  'Logout',
                  style: TextStyle(
                    color: LogisticsColors.textDark,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
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
                Navigator.pop(dialogContext);
              },

              child: const Text(
                'Cancel',
                style: TextStyle(
                  color: LogisticsColors.textGrey,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),

            ElevatedButton(
              onPressed: () {
                Navigator.pop(dialogContext);

                // ==================================================
                // LOGOUT API WILL BE CONNECTED LATER
                // ==================================================
              },

              style: ElevatedButton.styleFrom(
                backgroundColor: LogisticsColors.danger,
                foregroundColor: Colors.white,
                elevation: 0,

                padding: const EdgeInsets.symmetric(
                  horizontal: 18,
                  vertical: 11,
                ),

                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),

              child: const Text(
                'Logout',
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
              ),
            ),
          ],
        );
      },
    );
  }
}
