import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';

import 'order_history.dart';
import 'payment_methods.dart';

class BuyerProfile extends StatefulWidget {
  const BuyerProfile({super.key});

  @override
  State<BuyerProfile> createState() => _BuyerProfileState();
}

class _BuyerProfileState extends State<BuyerProfile> {
  // ============================================================
  // BUYER INFORMATION
  // ============================================================

  String buyerName = "John Carlo";
  String buyerEmail = "buyer@test.com";
  String buyerPhone = "09123456789";
  String buyerAddress = "No delivery address added yet";

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(18, 16, 18, 25),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              // ==========================================================
              // HEADER
              // ==========================================================

              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,

                children: [
                  const Text("My Profile", style: BuyerStyle.title),

                  Container(
                    height: 42,
                    width: 42,

                    decoration: BoxDecoration(
                      color: BuyerColors.white,

                      borderRadius: BorderRadius.circular(12),

                      border: Border.all(color: BuyerColors.border),
                    ),

                    child: IconButton(
                      padding: EdgeInsets.zero,

                      onPressed: () {
                        _showSettingsMessage();
                      },

                      icon: const Icon(
                        Icons.settings_outlined,
                        size: 21,
                        color: BuyerColors.textDark,
                      ),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 20),

              // ==========================================================
              // PROFILE CARD
              // ==========================================================
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BuyerStyle.card,

                child: Column(
                  children: [
                    Container(
                      height: 90,
                      width: 90,

                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: BuyerColors.lightPurple,

                        border: Border.all(
                          color: BuyerColors.softPurple,
                          width: 2,
                        ),
                      ),

                      child: const Icon(
                        Icons.person_outline,
                        size: 50,
                        color: BuyerColors.primary,
                      ),
                    ),

                    const SizedBox(height: 14),

                    Text(
                      buyerName,
                      style: BuyerStyle.title,
                      textAlign: TextAlign.center,
                    ),

                    const SizedBox(height: 5),

                    Text(
                      buyerEmail,
                      style: BuyerStyle.small,
                      textAlign: TextAlign.center,
                    ),

                    const SizedBox(height: 16),

                    SizedBox(
                      height: 44,

                      child: OutlinedButton.icon(
                        onPressed: () {
                          _showEditProfile();
                        },

                        icon: const Icon(Icons.edit_outlined, size: 17),

                        label: const Text("Edit Profile"),

                        style: BuyerStyle.outlinedButton.copyWith(
                          minimumSize: const WidgetStatePropertyAll(
                            Size(145, 44),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              // ==========================================================
              // PERSONAL INFORMATION
              // ==========================================================
              const Text(
                "Personal Information",
                style: BuyerStyle.sectionTitle,
              ),

              const SizedBox(height: 12),

              Container(
                decoration: BuyerStyle.card,

                child: Column(
                  children: [
                    _profileInfo(Icons.person_outline, "Full Name", buyerName),

                    _divider(),

                    _profileInfo(
                      Icons.email_outlined,
                      "Email Address",
                      buyerEmail,
                    ),

                    _divider(),

                    _profileInfo(
                      Icons.phone_outlined,
                      "Phone Number",
                      buyerPhone,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              // ==========================================================
              // DELIVERY INFORMATION
              // ==========================================================
              const Text(
                "Delivery Information",
                style: BuyerStyle.sectionTitle,
              ),

              const SizedBox(height: 12),

              Container(
                decoration: BuyerStyle.card,

                child: _profileInfo(
                  Icons.location_on_outlined,
                  "Delivery Address",
                  buyerAddress,
                ),
              ),

              const SizedBox(height: 25),

              // ==========================================================
              // MY ACCOUNT
              // ==========================================================
              const Text("My Account", style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              Container(
                decoration: BuyerStyle.card,

                child: Column(
                  children: [
                    // ----------------------------------------------------
                    // MY ORDERS
                    // ----------------------------------------------------

                    _profileItem(
                      Icons.receipt_long_outlined,
                      "My Orders",
                      "View your order history",
                      () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const OrderHistory(),
                          ),
                        );
                      },
                    ),

                    _divider(),

                    // ----------------------------------------------------
                    // DELIVERY ADDRESS
                    // ----------------------------------------------------
                    _profileItem(
                      Icons.location_on_outlined,
                      "Delivery Address",
                      "Manage your delivery addresses",
                      () {
                        _showEditProfile();
                      },
                    ),

                    _divider(),

                    // ----------------------------------------------------
                    // PAYMENT METHODS
                    // ----------------------------------------------------
                    _profileItem(
                      Icons.payment_outlined,
                      "Payment Methods",
                      "Manage your payment options",
                      () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const PaymentMethodsPage(),
                          ),
                        );
                      },
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              // ==========================================================
              // PREFERENCES
              // ==========================================================
              const Text("Preferences", style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              Container(
                decoration: BuyerStyle.card,

                child: Column(
                  children: [
                    _profileItem(
                      Icons.notifications_none,
                      "Notifications",
                      "Manage your notifications",
                      () {
                        _showComingSoon("Notifications");
                      },
                    ),

                    _divider(),

                    _profileItem(
                      Icons.settings_outlined,
                      "Settings",
                      "Manage your account settings",
                      () {
                        _showSettingsMessage();
                      },
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              // ==========================================================
              // SUPPORT
              // ==========================================================
              const Text("Support", style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              Container(
                decoration: BuyerStyle.card,

                child: Column(
                  children: [
                    _profileItem(
                      Icons.help_outline,
                      "Help Center",
                      "Get help with BiliHub",
                      () {
                        _showComingSoon("Help Center");
                      },
                    ),

                    _divider(),

                    _profileItem(
                      Icons.info_outline,
                      "About BiliHub",
                      "Learn more about BiliHub",
                      () {
                        _showComingSoon("About BiliHub");
                      },
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              // ==========================================================
              // LOGOUT
              // ==========================================================
              SizedBox(
                width: double.infinity,
                height: 52,

                child: OutlinedButton.icon(
                  onPressed: () {
                    _showLogoutDialog();
                  },

                  icon: const Icon(Icons.logout, size: 19),

                  label: const Text(
                    "Logout",
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),

                  style: OutlinedButton.styleFrom(
                    foregroundColor: BuyerColors.danger,

                    side: const BorderSide(color: BuyerColors.danger),

                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 20),

              // ==========================================================
              // FOOTER
              // ==========================================================
              const Center(
                child: Text(
                  "BiliHub • Shop smart. Shop easy.",
                  style: TextStyle(fontSize: 10, color: BuyerColors.textGrey),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ================================================================
  // EDIT PROFILE
  // ================================================================

  void _showEditProfile() {
    final nameController = TextEditingController(text: buyerName);

    final emailController = TextEditingController(text: buyerEmail);

    final phoneController = TextEditingController(text: buyerPhone);

    final addressController = TextEditingController(
      text: buyerAddress == "No delivery address added yet" ? "" : buyerAddress,
    );

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,

      builder: (context) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(context).viewInsets.bottom,
              ),

              child: Container(
                constraints: const BoxConstraints(maxHeight: 700),

                decoration: const BoxDecoration(
                  color: BuyerColors.background,

                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),

                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 25),

                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      // TOP HANDLE

                      Center(
                        child: Container(
                          height: 4,
                          width: 45,

                          decoration: BoxDecoration(
                            color: BuyerColors.border,

                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                      ),

                      const SizedBox(height: 20),

                      const Text("Edit Profile", style: BuyerStyle.title),

                      const SizedBox(height: 5),

                      const Text(
                        "Update your personal information.",
                        style: BuyerStyle.subtitle,
                      ),

                      const SizedBox(height: 22),

                      // ==================================================
                      // FULL NAME
                      // ==================================================
                      const Text("Full Name", style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: nameController,

                        textCapitalization: TextCapitalization.words,

                        decoration: BuyerStyle.inputDecoration(
                          hint: "Enter your full name",
                          icon: Icons.person_outline,
                        ),
                      ),

                      const SizedBox(height: 15),

                      // ==================================================
                      // EMAIL
                      // ==================================================
                      const Text("Email Address", style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: emailController,

                        keyboardType: TextInputType.emailAddress,

                        decoration: BuyerStyle.inputDecoration(
                          hint: "Enter your email",
                          icon: Icons.email_outlined,
                        ),
                      ),

                      const SizedBox(height: 15),

                      // ==================================================
                      // PHONE
                      // ==================================================
                      const Text("Phone Number", style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: phoneController,

                        keyboardType: TextInputType.phone,

                        decoration: BuyerStyle.inputDecoration(
                          hint: "Enter your phone number",
                          icon: Icons.phone_outlined,
                        ),
                      ),

                      const SizedBox(height: 15),

                      // ==================================================
                      // ADDRESS
                      // ==================================================
                      const Text("Delivery Address", style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: addressController,

                        maxLines: 3,

                        textCapitalization: TextCapitalization.sentences,

                        decoration: BuyerStyle.inputDecoration(
                          hint: "Enter your delivery address",
                          icon: Icons.location_on_outlined,
                        ),
                      ),

                      const SizedBox(height: 25),

                      // ==================================================
                      // SAVE
                      // ==================================================
                      SizedBox(
                        width: double.infinity,
                        height: 52,

                        child: ElevatedButton(
                          onPressed: () {
                            final name = nameController.text.trim();

                            final email = emailController.text.trim();

                            final phone = phoneController.text.trim();

                            final address = addressController.text.trim();

                            if (name.isEmpty ||
                                email.isEmpty ||
                                phone.isEmpty ||
                                address.isEmpty) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                  content: Text("Please complete all fields."),
                                ),
                              );

                              return;
                            }

                            setState(() {
                              buyerName = name;
                              buyerEmail = email;
                              buyerPhone = phone;
                              buyerAddress = address;
                            });

                            Navigator.pop(context);

                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                backgroundColor: BuyerColors.primaryDark,

                                behavior: SnackBarBehavior.floating,

                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),

                                content: const Row(
                                  children: [
                                    Icon(
                                      Icons.check_circle_outline,
                                      color: Colors.white,
                                    ),

                                    SizedBox(width: 10),

                                    Text(
                                      "Profile updated successfully",
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },

                          style: BuyerStyle.primaryButton,

                          child: const Text(
                            "Save Changes",
                            style: BuyerStyle.buttonText,
                          ),
                        ),
                      ),

                      const SizedBox(height: 10),

                      // ==================================================
                      // CANCEL
                      // ==================================================
                      SizedBox(
                        width: double.infinity,
                        height: 52,

                        child: OutlinedButton(
                          onPressed: () {
                            Navigator.pop(context);
                          },

                          style: BuyerStyle.outlinedButton,

                          child: const Text(
                            "Cancel",

                            style: TextStyle(
                              color: BuyerColors.primary,

                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    ).whenComplete(() {
      nameController.dispose();
      emailController.dispose();
      phoneController.dispose();
      addressController.dispose();
    });
  }

  // ================================================================
  // PROFILE INFORMATION
  // ================================================================

  Widget _profileInfo(IconData icon, String title, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 15),

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Container(
            height: 42,
            width: 42,

            decoration: BoxDecoration(
              color: BuyerColors.lightPurple,

              borderRadius: BorderRadius.circular(12),
            ),

            child: Icon(icon, color: BuyerColors.primary, size: 21),
          ),

          const SizedBox(width: 13),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(title, style: BuyerStyle.small),

                const SizedBox(height: 4),

                Text(value, style: BuyerStyle.cardTitle),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ================================================================
  // PROFILE ITEM
  // ================================================================

  Widget _profileItem(
    IconData icon,
    String title,
    String subtitle,
    VoidCallback onTap,
  ) {
    return Material(
      color: Colors.transparent,

      child: InkWell(
        onTap: onTap,

        borderRadius: BorderRadius.circular(16),

        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 13),

          child: Row(
            children: [
              Container(
                height: 42,
                width: 42,

                decoration: BoxDecoration(
                  color: BuyerColors.lightPurple,

                  borderRadius: BorderRadius.circular(12),
                ),

                child: Icon(icon, color: BuyerColors.primary, size: 21),
              ),

              const SizedBox(width: 13),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Text(title, style: BuyerStyle.cardTitle),

                    const SizedBox(height: 3),

                    Text(
                      subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: BuyerStyle.small,
                    ),
                  ],
                ),
              ),

              const Icon(
                Icons.chevron_right,
                color: BuyerColors.textGrey,
                size: 21,
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ================================================================
  // DIVIDER
  // ================================================================

  Widget _divider() {
    return const Divider(
      height: 1,
      indent: 70,
      endIndent: 15,
      color: BuyerColors.border,
    );
  }

  // ================================================================
  // SETTINGS MESSAGE
  // ================================================================

  void _showSettingsMessage() {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(
          content: Text("Account settings will be available soon."),
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  // ================================================================
  // COMING SOON MESSAGE
  // ================================================================

  void _showComingSoon(String feature) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text("$feature will be available soon."),
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  // ================================================================
  // LOGOUT DIALOG
  // ================================================================

  void _showLogoutDialog() {
    showDialog(
      context: context,

      builder: (context) {
        return AlertDialog(
          backgroundColor: BuyerColors.white,

          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),

          title: const Text(
            "Logout",

            style: TextStyle(
              fontWeight: FontWeight.w800,

              color: BuyerColors.textDark,
            ),
          ),

          content: const Text(
            "Are you sure you want to logout?",

            style: TextStyle(color: BuyerColors.textGrey),
          ),

          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context);
              },

              child: const Text(
                "Cancel",

                style: TextStyle(color: BuyerColors.textGrey),
              ),
            ),

            TextButton(
              onPressed: () {
                Navigator.pop(context);

                // TODO:
                // Clear login/session data.
                // Navigate to LoginPage.
              },

              child: const Text(
                "Logout",

                style: TextStyle(
                  color: BuyerColors.danger,

                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}
