import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

import '../../widgets/custom_button.dart';

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

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        iconTheme: const IconThemeData(color: Colors.black),

        title: const Text("Profile", style: LogisticsStyle.title),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: EdgeInsets.all(width * 0.045),

          child: Column(
            children: [
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(20),

                decoration: LogisticsStyle.card,

                child: Column(
                  children: [
                    Container(
                      height: 90,

                      width: 90,

                      decoration: BoxDecoration(
                        shape: BoxShape.circle,

                        color: Colors.blue.withValues(alpha: .1),
                      ),

                      child: const Icon(
                        Icons.badge_outlined,

                        size: 55,

                        color: Colors.blue,
                      ),
                    ),

                    const SizedBox(height: 15),

                    Text(
                      name,

                      style: LogisticsStyle.title,

                      textAlign: TextAlign.center,
                    ),

                    const SizedBox(height: 5),

                    Text(email, style: LogisticsStyle.small),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              _infoCard(Icons.badge_outlined, "Employee ID", employeeId),

              _infoCard(Icons.business_outlined, "Department", department),

              _infoCard(Icons.email_outlined, "Email Address", email),

              const SizedBox(height: 20),

              Container(
                decoration: LogisticsStyle.card,

                child: Column(
                  children: [
                    _menuItem(
                      Icons.settings_outlined,

                      "Account Settings",

                      () {},
                    ),

                    _menuItem(Icons.lock_outline, "Security", () {}),

                    _menuItem(Icons.help_outline, "Help Center", () {}),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              CustomButton(
                text: "Logout",

                icon: Icons.logout,

                backgroundColor: Colors.red,

                onPressed: () {
                  // Logout API later
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _infoCard(IconData icon, String title, String value) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),

      padding: const EdgeInsets.all(16),

      decoration: LogisticsStyle.card,

      child: Row(
        children: [
          Icon(icon, color: LogisticsColors.primary),

          const SizedBox(width: 12),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(title, style: LogisticsStyle.small),

                const SizedBox(height: 5),

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

  Widget _menuItem(IconData icon, String title, VoidCallback onTap) {
    return ListTile(
      dense: true,

      leading: Icon(icon, color: LogisticsColors.primary),

      title: Text(title),

      trailing: const Icon(Icons.arrow_forward_ios, size: 16),

      onTap: onTap,
    );
  }
}
