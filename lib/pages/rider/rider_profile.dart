import 'package:flutter/material.dart';

import '../../styles/rider_style.dart';

import '../../widgets/custom_button.dart';

class RiderProfile extends StatelessWidget {
  final String name;

  final String email;

  final String phone;

  final String riderId;

  final String vehicle;

  const RiderProfile({
    super.key,

    required this.name,

    required this.email,

    required this.phone,

    required this.riderId,

    required this.vehicle,
  });

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return Scaffold(
      backgroundColor: RiderColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        iconTheme: const IconThemeData(color: Colors.black),

        title: const Text("Rider Profile", style: RiderStyle.title),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: EdgeInsets.all(width * 0.045),

          child: Column(
            children: [
              Container(
                width: double.infinity,

                padding: EdgeInsets.all(width * 0.05),

                decoration: RiderStyle.card,

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
                        Icons.delivery_dining,

                        size: 55,

                        color: Colors.blue,
                      ),
                    ),

                    const SizedBox(height: 15),

                    Text(
                      name,

                      style: RiderStyle.title,

                      textAlign: TextAlign.center,
                    ),

                    const SizedBox(height: 5),

                    Text(email, style: RiderStyle.small),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              _infoCard(Icons.phone_outlined, "Phone Number", phone),

              _infoCard(Icons.badge_outlined, "Rider ID", riderId),

              _infoCard(
                Icons.motorcycle_outlined,

                "Vehicle Information",

                vehicle,
              ),

              const SizedBox(height: 20),

              Container(
                decoration: RiderStyle.card,

                child: Column(
                  children: [
                    _menuItem(
                      Icons.settings_outlined,

                      "Account Settings",

                      () {},
                    ),

                    _menuItem(Icons.security_outlined, "Security", () {}),

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

      decoration: RiderStyle.card,

      child: Row(
        children: [
          Icon(icon, color: Colors.blue),

          const SizedBox(width: 12),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(title, style: RiderStyle.small),

                const SizedBox(height: 5),

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

  Widget _menuItem(IconData icon, String title, VoidCallback onTap) {
    return ListTile(
      dense: true,

      leading: Icon(icon, color: Colors.blue),

      title: Text(title),

      trailing: const Icon(Icons.arrow_forward_ios, size: 16),

      onTap: onTap,
    );
  }
}
