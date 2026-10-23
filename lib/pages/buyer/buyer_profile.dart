import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../../sevices/auth_storage.dart';
import '../../styles/buyer_style.dart';

import '../auth/login_page.dart';
import 'order_history.dart';
import 'payment_methods.dart';

class BuyerProfile extends StatefulWidget {
  final Map<String, dynamic> user;

  const BuyerProfile({super.key, required this.user});

  @override
  State<BuyerProfile> createState() => _BuyerProfileState();
}

class _BuyerProfileState extends State<BuyerProfile> {
  static const String _baseUrl = 'https://demo-bilihubs-shop.onrender.com/api';

  String buyerName = 'Buyer';
  String buyerEmail = '';
  String buyerPhone = '';
  String buyerAddress = 'No delivery address added yet';

  List<Map<String, dynamic>> buyerAddresses = <Map<String, dynamic>>[];

  Map<String, dynamic>? defaultAddress;

  bool isLoadingProfile = false;
  bool isLoggingOut = false;

  @override
  void initState() {
    super.initState();

    _loadInitialUser();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _refreshProfile();
    });
  }

  // ============================================================
  // INITIAL LOCAL USER
  // ============================================================

  void _loadInitialUser() {
    buyerName = widget.user['name']?.toString().trim().isNotEmpty == true
        ? widget.user['name'].toString().trim()
        : 'Buyer';

    buyerEmail = widget.user['email']?.toString().trim() ?? '';

    buyerPhone =
        widget.user['mobile_number']?.toString().trim() ??
        widget.user['phone']?.toString().trim() ??
        '';

    final String oldAddress = widget.user['address']?.toString().trim() ?? '';

    if (oldAddress.isNotEmpty) {
      buyerAddress = oldAddress;
    }
  }

  // ============================================================
  // REFRESH PROFILE FROM SERVER
  // ============================================================

  Future<void> _refreshProfile() async {
    if (isLoadingProfile) {
      return;
    }

    setState(() {
      isLoadingProfile = true;
    });

    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        if (!mounted) {
          return;
        }

        setState(() {
          isLoadingProfile = false;
        });

        return;
      }

      final List<Future<http.Response>> requests = [
        http.get(
          Uri.parse('$_baseUrl/auth/user'),
          headers: _authHeaders(token),
        ),
        http.get(
          Uri.parse('$_baseUrl/addresses'),
          headers: _authHeaders(token),
        ),
      ];

      final List<http.Response> responses = await Future.wait(requests);

      final http.Response userResponse = responses[0];

      final http.Response addressResponse = responses[1];

      Map<String, dynamic>? serverUser;

      if (userResponse.statusCode == 200) {
        final dynamic decoded = _decodeResponse(userResponse.body);

        if (decoded is Map<String, dynamic>) {
          final dynamic rawUser = decoded['user'];

          if (rawUser is Map) {
            serverUser = Map<String, dynamic>.from(rawUser);
          }
        }
      }

      List<Map<String, dynamic>> loadedAddresses = <Map<String, dynamic>>[];

      if (addressResponse.statusCode == 200) {
        final dynamic decoded = _decodeResponse(addressResponse.body);

        if (decoded is Map<String, dynamic> && decoded['data'] is List) {
          loadedAddresses = List<dynamic>.from(decoded['data'])
              .whereType<Map>()
              .map((Map address) => Map<String, dynamic>.from(address))
              .toList();
        }
      }

      Map<String, dynamic>? selectedDefault;

      for (final Map<String, dynamic> address in loadedAddresses) {
        if (_isTrue(address['is_default'])) {
          selectedDefault = address;
          break;
        }
      }

      if (selectedDefault == null && loadedAddresses.isNotEmpty) {
        selectedDefault = loadedAddresses.first;
      }

      if (serverUser != null) {
        await AuthStorage.updateUser(serverUser);
      }

      if (!mounted) {
        return;
      }

      setState(() {
        if (serverUser != null) {
          final String serverName = serverUser['name']?.toString().trim() ?? '';

          final String serverEmail =
              serverUser['email']?.toString().trim() ?? '';

          final String serverPhone =
              serverUser['mobile_number']?.toString().trim() ??
              serverUser['phone']?.toString().trim() ??
              '';

          if (serverName.isNotEmpty) {
            buyerName = serverName;
          }

          buyerEmail = serverEmail;
          buyerPhone = serverPhone;
        }

        buyerAddresses = loadedAddresses;

        defaultAddress = selectedDefault;

        if (selectedDefault != null) {
          final String fullAddress =
              selectedDefault['full_address']?.toString().trim() ?? '';

          buyerAddress = fullAddress.isNotEmpty
              ? fullAddress
              : _buildFullAddress(selectedDefault);
        } else {
          buyerAddress = 'No delivery address added yet';
        }

        isLoadingProfile = false;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        isLoadingProfile = false;
      });
    }
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,
      body: SafeArea(
        child: RefreshIndicator(
          color: BuyerColors.primary,
          onRefresh: _refreshProfile,
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
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
                    const Text('My Profile', style: BuyerStyle.title),
                    Row(
                      children: [
                        if (isLoadingProfile)
                          const Padding(
                            padding: EdgeInsets.only(right: 10),
                            child: SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: BuyerColors.primary,
                              ),
                            ),
                          ),
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
                            onPressed: _showSettingsMessage,
                            icon: const Icon(
                              Icons.settings_outlined,
                              size: 21,
                              color: BuyerColors.textDark,
                            ),
                          ),
                        ),
                      ],
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
                          onPressed: isLoadingProfile ? null : _showEditProfile,
                          icon: const Icon(Icons.edit_outlined, size: 17),
                          label: const Text('Edit Profile'),
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
                  'Personal Information',
                  style: BuyerStyle.sectionTitle,
                ),

                const SizedBox(height: 12),

                Container(
                  decoration: BuyerStyle.card,
                  child: Column(
                    children: [
                      _profileInfo(
                        Icons.person_outline,
                        'Full Name',
                        buyerName,
                      ),

                      _divider(),

                      _profileInfo(
                        Icons.email_outlined,
                        'Email Address',
                        buyerEmail.isEmpty ? 'No email address' : buyerEmail,
                      ),

                      _divider(),

                      _profileInfo(
                        Icons.phone_outlined,
                        'Phone Number',
                        buyerPhone.isEmpty
                            ? 'No phone number added'
                            : buyerPhone,
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 25),

                // ==========================================================
                // DELIVERY INFORMATION
                // ==========================================================
                const Text(
                  'Delivery Information',
                  style: BuyerStyle.sectionTitle,
                ),

                const SizedBox(height: 12),

                Container(
                  decoration: BuyerStyle.card,
                  child: _profileInfo(
                    Icons.location_on_outlined,
                    'Delivery Address',
                    buyerAddress,
                  ),
                ),

                const SizedBox(height: 25),

                // ==========================================================
                // MY ACCOUNT
                // ==========================================================
                const Text('My Account', style: BuyerStyle.sectionTitle),

                const SizedBox(height: 12),

                Container(
                  decoration: BuyerStyle.card,
                  child: Column(
                    children: [
                      _profileItem(
                        Icons.receipt_long_outlined,
                        'My Orders',
                        'View your order history',
                        () {
                          Navigator.push(
                            context,
                            MaterialPageRoute<void>(
                              builder: (BuildContext context) {
                                return const OrderHistory();
                              },
                            ),
                          );
                        },
                      ),

                      _divider(),

                      _profileItem(
                        Icons.location_on_outlined,
                        'Delivery Address',
                        'Manage your saved addresses',
                        _showAddressManager,
                      ),

                      _divider(),

                      _profileItem(
                        Icons.payment_outlined,
                        'Payment Methods',
                        'Manage your payment options',
                        () {
                          Navigator.push(
                            context,
                            MaterialPageRoute<void>(
                              builder: (BuildContext context) {
                                return const PaymentMethodsPage();
                              },
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
                const Text('Preferences', style: BuyerStyle.sectionTitle),

                const SizedBox(height: 12),

                Container(
                  decoration: BuyerStyle.card,
                  child: Column(
                    children: [
                      _profileItem(
                        Icons.notifications_none,
                        'Notifications',
                        'Manage your notifications',
                        () {
                          _showComingSoon('Notifications');
                        },
                      ),

                      _divider(),

                      _profileItem(
                        Icons.settings_outlined,
                        'Settings',
                        'Manage your account settings',
                        _showSettingsMessage,
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 25),

                // ==========================================================
                // SUPPORT
                // ==========================================================
                const Text('Support', style: BuyerStyle.sectionTitle),

                const SizedBox(height: 12),

                Container(
                  decoration: BuyerStyle.card,
                  child: Column(
                    children: [
                      _profileItem(
                        Icons.help_outline,
                        'Help Center',
                        'Get help with BiliHub',
                        () {
                          _showComingSoon('Help Center');
                        },
                      ),

                      _divider(),

                      _profileItem(
                        Icons.info_outline,
                        'About BiliHub',
                        'Learn more about BiliHub',
                        () {
                          _showComingSoon('About BiliHub');
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
                    onPressed: isLoggingOut ? null : _showLogoutDialog,
                    icon: isLoggingOut
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: BuyerColors.danger,
                            ),
                          )
                        : const Icon(Icons.logout, size: 19),
                    label: Text(
                      isLoggingOut ? 'Logging out...' : 'Logout',
                      style: const TextStyle(fontWeight: FontWeight.w700),
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

                const Center(
                  child: Text(
                    'BiliHub • Shop smart. Shop easy.',
                    style: TextStyle(fontSize: 10, color: BuyerColors.textGrey),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ================================================================
  // EDIT PROFILE
  // ================================================================

  void _showEditProfile() {
    final TextEditingController nameController = TextEditingController(
      text: buyerName,
    );

    final TextEditingController emailController = TextEditingController(
      text: buyerEmail,
    );

    final TextEditingController phoneController = TextEditingController(
      text: buyerPhone,
    );

    bool isSaving = false;

    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (BuildContext modalContext) {
        return StatefulBuilder(
          builder: (BuildContext modalContext, StateSetter setModalState) {
            Future<void> saveChanges() async {
              final String name = nameController.text.trim();

              final String phone = phoneController.text.trim();

              if (name.isEmpty) {
                _showModalMessage(modalContext, 'Please enter your full name.');

                return;
              }

              if (phone.isEmpty) {
                _showModalMessage(
                  modalContext,
                  'Please enter your phone number.',
                );

                return;
              }

              if (!RegExp(r'^09\d{9}$').hasMatch(phone)) {
                _showModalMessage(
                  modalContext,
                  'Please enter a valid 11-digit Philippine mobile number.',
                );

                return;
              }

              setModalState(() {
                isSaving = true;
              });

              try {
                final String? token = await AuthStorage.getToken();

                if (token == null || token.trim().isEmpty) {
                  if (modalContext.mounted) {
                    setModalState(() {
                      isSaving = false;
                    });

                    _showModalMessage(
                      modalContext,
                      'Your session has expired. Please login again.',
                    );
                  }

                  return;
                }

                final http.Response response = await http.put(
                  Uri.parse('$_baseUrl/auth/profile'),
                  headers: _authHeaders(token, json: true),
                  body: jsonEncode({'name': name, 'mobile_number': phone}),
                );

                final dynamic decoded = _decodeResponse(response.body);

                if (response.statusCode != 200) {
                  if (modalContext.mounted) {
                    setModalState(() {
                      isSaving = false;
                    });

                    _showModalMessage(
                      modalContext,
                      _extractErrorMessage(
                        decoded,
                        fallback: 'Unable to update your profile.',
                      ),
                    );
                  }

                  return;
                }

                Map<String, dynamic> updatedUser = <String, dynamic>{};

                if (decoded is Map<String, dynamic> && decoded['user'] is Map) {
                  updatedUser = Map<String, dynamic>.from(decoded['user']);
                }

                if (updatedUser.isNotEmpty) {
                  await AuthStorage.updateUser(updatedUser);
                } else {
                  await AuthStorage.updateUser({
                    'name': name,
                    'mobile_number': phone,
                    'phone': phone,
                  });
                }

                if (!mounted) {
                  return;
                }

                setState(() {
                  buyerName =
                      updatedUser['name']?.toString().trim().isNotEmpty == true
                      ? updatedUser['name'].toString().trim()
                      : name;

                  buyerPhone =
                      updatedUser['mobile_number']
                              ?.toString()
                              .trim()
                              .isNotEmpty ==
                          true
                      ? updatedUser['mobile_number'].toString().trim()
                      : phone;

                  final String serverEmail =
                      updatedUser['email']?.toString().trim() ?? '';

                  if (serverEmail.isNotEmpty) {
                    buyerEmail = serverEmail;
                  }
                });

                if (modalContext.mounted) {
                  Navigator.pop(modalContext);
                }

                if (!mounted) {
                  return;
                }

                _showSuccessMessage(
                  decoded is Map<String, dynamic>
                      ? decoded['message']?.toString().trim().isNotEmpty == true
                            ? decoded['message'].toString().trim()
                            : 'Profile updated successfully.'
                      : 'Profile updated successfully.',
                );
              } catch (_) {
                if (!modalContext.mounted) {
                  return;
                }

                setModalState(() {
                  isSaving = false;
                });

                _showModalMessage(
                  modalContext,
                  'Unable to connect to the server. Please try again.',
                );
              }
            }

            return Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(modalContext).viewInsets.bottom,
              ),
              child: Container(
                constraints: const BoxConstraints(maxHeight: 620),
                decoration: const BoxDecoration(
                  color: BuyerColors.background,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 25),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
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

                      const Text('Edit Profile', style: BuyerStyle.title),

                      const SizedBox(height: 5),

                      const Text(
                        'Update your personal information.',
                        style: BuyerStyle.subtitle,
                      ),

                      const SizedBox(height: 22),

                      const Text('Full Name', style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: nameController,
                        enabled: !isSaving,
                        textCapitalization: TextCapitalization.words,
                        decoration: BuyerStyle.inputDecoration(
                          hint: 'Enter your full name',
                          icon: Icons.person_outline,
                        ),
                      ),

                      const SizedBox(height: 15),

                      const Text('Email Address', style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: emailController,
                        enabled: false,
                        keyboardType: TextInputType.emailAddress,
                        decoration:
                            BuyerStyle.inputDecoration(
                              hint: 'Email address',
                              icon: Icons.email_outlined,
                            ).copyWith(
                              helperText: 'Email cannot be changed from the mobile profile.',
                            ),
                      ),

                      const SizedBox(height: 15),

                      const Text('Phone Number', style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: phoneController,
                        enabled: !isSaving,
                        keyboardType: TextInputType.phone,
                        maxLength: 11,
                        decoration: BuyerStyle.inputDecoration(
                          hint: '09XXXXXXXXX',
                          icon: Icons.phone_outlined,
                        ).copyWith(counterText: ''),
                      ),

                      const SizedBox(height: 25),

                      SizedBox(
                        width: double.infinity,
                        height: 52,
                        child: ElevatedButton(
                          onPressed: isSaving ? null : saveChanges,
                          style: BuyerStyle.primaryButton,
                          child: isSaving
                              ? const SizedBox(
                                  height: 22,
                                  width: 22,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text(
                                  'Save Changes',
                                  style: BuyerStyle.buttonText,
                                ),
                        ),
                      ),

                      const SizedBox(height: 10),

                      SizedBox(
                        width: double.infinity,
                        height: 52,
                        child: OutlinedButton(
                          onPressed: isSaving
                              ? null
                              : () {
                                  Navigator.pop(modalContext);
                                },
                          style: BuyerStyle.outlinedButton,
                          child: const Text(
                            'Cancel',
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
    });
  }

  // ================================================================
  // ADDRESS MANAGER
  // ================================================================

  Future<void> _showAddressManager() async {
    await _refreshProfile();

    if (!mounted) {
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (BuildContext modalContext) {
        return StatefulBuilder(
          builder: (BuildContext modalContext, StateSetter setModalState) {
            Future<void> refreshAddresses() async {
              await _refreshProfile();

              if (modalContext.mounted) {
                setModalState(() {});
              }
            }

            return Container(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.of(modalContext).size.height * 0.82,
              ),
              decoration: const BoxDecoration(
                color: BuyerColors.background,
                borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
              ),
              child: SafeArea(
                top: false,
                child: Column(
                  children: [
                    const SizedBox(height: 12),

                    Container(
                      height: 4,
                      width: 45,
                      decoration: BoxDecoration(
                        color: BuyerColors.border,
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),

                    Padding(
                      padding: const EdgeInsets.fromLTRB(20, 18, 20, 14),
                      child: Row(
                        children: [
                          const Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Delivery Addresses',
                                  style: BuyerStyle.title,
                                ),
                                SizedBox(height: 4),
                                Text(
                                  'Choose or manage the address used for checkout.',
                                  style: BuyerStyle.subtitle,
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            onPressed: () {
                              Navigator.pop(modalContext);
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                        ],
                      ),
                    ),

                    Expanded(
                      child: buyerAddresses.isEmpty
                          ? _buildNoAddressState(modalContext, refreshAddresses)
                          : ListView.separated(
                              padding: const EdgeInsets.fromLTRB(20, 0, 20, 15),
                              itemCount: buyerAddresses.length,
                              separatorBuilder:
                                  (BuildContext context, int index) {
                                    return const SizedBox(height: 10);
                                  },
                              itemBuilder: (BuildContext context, int index) {
                                final Map<String, dynamic> address =
                                    buyerAddresses[index];

                                final bool isDefault = _isTrue(
                                  address['is_default'],
                                );

                                return Container(
                                  padding: const EdgeInsets.all(14),
                                  decoration: BuyerStyle.card,
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        children: [
                                          const Icon(
                                            Icons.location_on_outlined,
                                            color: BuyerColors.primary,
                                          ),
                                          const SizedBox(width: 9),
                                          Expanded(
                                            child: Text(
                                              address['label']
                                                          ?.toString()
                                                          .trim()
                                                          .isNotEmpty ==
                                                      true
                                                  ? address['label']
                                                        .toString()
                                                        .trim()
                                                  : 'Delivery Address',
                                              style: BuyerStyle.cardTitle,
                                            ),
                                          ),
                                          if (isDefault)
                                            Container(
                                              padding:
                                                  const EdgeInsets.symmetric(
                                                    horizontal: 9,
                                                    vertical: 4,
                                                  ),
                                              decoration: BoxDecoration(
                                                color: BuyerColors.lightPurple,
                                                borderRadius:
                                                    BorderRadius.circular(20),
                                              ),
                                              child: const Text(
                                                'Default',
                                                style: TextStyle(
                                                  fontSize: 11,
                                                  color: BuyerColors.primary,
                                                  fontWeight: FontWeight.w700,
                                                ),
                                              ),
                                            ),
                                        ],
                                      ),

                                      const SizedBox(height: 10),

                                      Text(
                                        address['full_address']
                                                    ?.toString()
                                                    .trim()
                                                    .isNotEmpty ==
                                                true
                                            ? address['full_address']
                                                  .toString()
                                                  .trim()
                                            : _buildFullAddress(address),
                                        style: BuyerStyle.small,
                                      ),

                                      finalPhoneWidget(address),

                                      const SizedBox(height: 12),

                                      Row(
                                        children: [
                                          Expanded(
                                            child: OutlinedButton.icon(
                                              onPressed: () async {
                                                await _showAddressForm(
                                                  address: address,
                                                );

                                                await refreshAddresses();
                                              },
                                              icon: const Icon(
                                                Icons.edit_outlined,
                                                size: 17,
                                              ),
                                              label: const Text('Edit'),
                                            ),
                                          ),

                                          if (!isDefault) ...[
                                            const SizedBox(width: 8),
                                            Expanded(
                                              child: ElevatedButton(
                                                onPressed: () async {
                                                  final bool success =
                                                      await _setDefaultAddress(
                                                        address,
                                                      );

                                                  if (success) {
                                                    await refreshAddresses();
                                                  }
                                                },
                                                style: BuyerStyle.primaryButton,
                                                child: const Text(
                                                  'Set Default',
                                                ),
                                              ),
                                            ),
                                          ],
                                        ],
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
                    ),

                    Padding(
                      padding: const EdgeInsets.fromLTRB(20, 10, 20, 20),
                      child: SizedBox(
                        width: double.infinity,
                        height: 52,
                        child: ElevatedButton.icon(
                          onPressed: () async {
                            await _showAddressForm();

                            await refreshAddresses();
                          },
                          style: BuyerStyle.primaryButton,
                          icon: const Icon(Icons.add_location_alt_outlined),
                          label: const Text('Add New Address'),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );

    await _refreshProfile();
  }

  Widget finalPhoneWidget(Map<String, dynamic> address) {
    final String phone = address['phone']?.toString().trim() ?? '';

    if (phone.isEmpty) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Text(phone, style: BuyerStyle.small),
    );
  }

  Widget _buildNoAddressState(
    BuildContext modalContext,
    Future<void> Function() refreshAddresses,
  ) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(25),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              height: 75,
              width: 75,
              decoration: BoxDecoration(
                color: BuyerColors.lightPurple,
                borderRadius: BorderRadius.circular(22),
              ),
              child: const Icon(
                Icons.location_off_outlined,
                size: 36,
                color: BuyerColors.primary,
              ),
            ),

            const SizedBox(height: 15),

            const Text('No Saved Address', style: BuyerStyle.sectionTitle),

            const SizedBox(height: 6),

            const Text(
              'Add a delivery address so it can be used during checkout.',
              textAlign: TextAlign.center,
              style: BuyerStyle.small,
            ),
          ],
        ),
      ),
    );
  }

  // ================================================================
  // ADD / EDIT ADDRESS
  // ================================================================

  Future<void> _showAddressForm({Map<String, dynamic>? address}) async {
    final bool isEditing = address != null;

    final TextEditingController labelController = TextEditingController(
      text: address?['label']?.toString() ?? '',
    );

    final TextEditingController line1Controller = TextEditingController(
      text: address?['address_line1']?.toString() ?? '',
    );

    final TextEditingController line2Controller = TextEditingController(
      text: address?['address_line2']?.toString() ?? '',
    );

    final TextEditingController cityController = TextEditingController(
      text: address?['city']?.toString() ?? '',
    );

    final TextEditingController provinceController = TextEditingController(
      text: address?['province']?.toString() ?? '',
    );

    final TextEditingController postalController = TextEditingController(
      text: address?['postal_code']?.toString() ?? '',
    );

    final TextEditingController phoneController = TextEditingController(
      text: address?['phone']?.toString() ?? buyerPhone,
    );

    bool isDefault = _isTrue(address?['is_default']);

    bool isSaving = false;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (BuildContext modalContext) {
        return StatefulBuilder(
          builder: (BuildContext modalContext, StateSetter setModalState) {
            Future<void> saveAddress() async {
              final String line1 = line1Controller.text.trim();

              final String city = cityController.text.trim();

              final String province = provinceController.text.trim();

              final String phone = phoneController.text.trim();

              if (line1.isEmpty ||
                  city.isEmpty ||
                  province.isEmpty ||
                  phone.isEmpty) {
                _showModalMessage(
                  modalContext,
                  'Please complete the required address fields.',
                );

                return;
              }

              if (!RegExp(r'^09\d{9}$').hasMatch(phone)) {
                _showModalMessage(
                  modalContext,
                  'Please enter a valid 11-digit Philippine mobile number.',
                );

                return;
              }

              setModalState(() {
                isSaving = true;
              });

              try {
                final String? token = await AuthStorage.getToken();

                if (token == null || token.trim().isEmpty) {
                  if (modalContext.mounted) {
                    setModalState(() {
                      isSaving = false;
                    });

                    _showModalMessage(
                      modalContext,
                      'Your session has expired. Please login again.',
                    );
                  }

                  return;
                }

                final Map<String, dynamic> payload = {
                  'label': labelController.text.trim().isEmpty
                      ? null
                      : labelController.text.trim(),
                  'address_line1': line1,
                  'address_line2': line2Controller.text.trim().isEmpty
                      ? null
                      : line2Controller.text.trim(),
                  'city': city,
                  'province': province,
                  'postal_code': postalController.text.trim().isEmpty
                      ? null
                      : postalController.text.trim(),
                  'country': 'Philippines',
                  'phone': phone,
                  'is_default': isDefault,
                };

                late final http.Response response;

                if (isEditing) {
                  final int addressId =
                      int.tryParse(address['id']?.toString() ?? '') ?? 0;

                  response = await http.put(
                    Uri.parse('$_baseUrl/addresses/$addressId'),
                    headers: _authHeaders(token, json: true),
                    body: jsonEncode(payload),
                  );
                } else {
                  response = await http.post(
                    Uri.parse('$_baseUrl/addresses'),
                    headers: _authHeaders(token, json: true),
                    body: jsonEncode(payload),
                  );
                }

                final dynamic decoded = _decodeResponse(response.body);

                if (response.statusCode != 200 && response.statusCode != 201) {
                  if (modalContext.mounted) {
                    setModalState(() {
                      isSaving = false;
                    });

                    _showModalMessage(
                      modalContext,
                      _extractErrorMessage(
                        decoded,
                        fallback: 'Unable to save this address.',
                      ),
                    );
                  }

                  return;
                }

                if (modalContext.mounted) {
                  Navigator.pop(modalContext);
                }

                if (!mounted) {
                  return;
                }

                _showSuccessMessage(
                  decoded is Map<String, dynamic> &&
                          decoded['message']?.toString().trim().isNotEmpty ==
                              true
                      ? decoded['message'].toString().trim()
                      : isEditing
                      ? 'Address updated successfully.'
                      : 'Address saved successfully.',
                );
              } catch (_) {
                if (!modalContext.mounted) {
                  return;
                }

                setModalState(() {
                  isSaving = false;
                });

                _showModalMessage(
                  modalContext,
                  'Unable to connect to the server. Please try again.',
                );
              }
            }

            return Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(modalContext).viewInsets.bottom,
              ),
              child: Container(
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.of(modalContext).size.height * 0.88,
                ),
                decoration: const BoxDecoration(
                  color: BuyerColors.background,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 25),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
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

                      Text(
                        isEditing ? 'Edit Address' : 'Add Address',
                        style: BuyerStyle.title,
                      ),

                      const SizedBox(height: 20),

                      const Text('Address Label', style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: labelController,
                        enabled: !isSaving,
                        textCapitalization: TextCapitalization.words,
                        decoration: BuyerStyle.inputDecoration(
                          hint: 'Home, Work, etc.',
                          icon: Icons.label_outline,
                        ),
                      ),

                      const SizedBox(height: 15),

                      const Text('Address Line 1 *', style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: line1Controller,
                        enabled: !isSaving,
                        textCapitalization: TextCapitalization.words,
                        decoration: BuyerStyle.inputDecoration(
                          hint: 'House no., street, barangay',
                          icon: Icons.home_outlined,
                        ),
                      ),

                      const SizedBox(height: 15),

                      const Text('Address Line 2', style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: line2Controller,
                        enabled: !isSaving,
                        decoration: BuyerStyle.inputDecoration(
                          hint: 'Subdivision, building, landmark',
                          icon: Icons.location_city_outlined,
                        ),
                      ),

                      const SizedBox(height: 15),

                      const Text(
                        'City / Municipality *',
                        style: BuyerStyle.label,
                      ),

                      const SizedBox(height: 7),

                      TextField(
                        controller: cityController,
                        enabled: !isSaving,
                        textCapitalization: TextCapitalization.words,
                        decoration: BuyerStyle.inputDecoration(
                          hint: 'Enter city or municipality',
                          icon: Icons.location_city_outlined,
                        ),
                      ),

                      const SizedBox(height: 15),

                      const Text('Province *', style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: provinceController,
                        enabled: !isSaving,
                        textCapitalization: TextCapitalization.words,
                        decoration: BuyerStyle.inputDecoration(
                          hint: 'Enter province',
                          icon: Icons.map_outlined,
                        ),
                      ),

                      const SizedBox(height: 15),

                      const Text('Postal Code', style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: postalController,
                        enabled: !isSaving,
                        keyboardType: TextInputType.number,
                        decoration: BuyerStyle.inputDecoration(
                          hint: 'Postal code',
                          icon: Icons.markunread_mailbox_outlined,
                        ),
                      ),

                      const SizedBox(height: 15),

                      const Text('Contact Number *', style: BuyerStyle.label),

                      const SizedBox(height: 7),

                      TextField(
                        controller: phoneController,
                        enabled: !isSaving,
                        maxLength: 11,
                        keyboardType: TextInputType.phone,
                        decoration: BuyerStyle.inputDecoration(
                          hint: '09XXXXXXXXX',
                          icon: Icons.phone_outlined,
                        ).copyWith(counterText: ''),
                      ),

                      const SizedBox(height: 12),

                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        title: const Text(
                          'Set as default address',
                          style: BuyerStyle.cardTitle,
                        ),
                        subtitle: const Text(
                          'This address will be selected automatically during checkout.',
                          style: BuyerStyle.small,
                        ),
                        value: isDefault,
                        onChanged: isSaving
                            ? null
                            : (bool value) {
                                setModalState(() {
                                  isDefault = value;
                                });
                              },
                      ),

                      const SizedBox(height: 18),

                      SizedBox(
                        width: double.infinity,
                        height: 52,
                        child: ElevatedButton(
                          style: BuyerStyle.primaryButton,
                          onPressed: isSaving ? null : saveAddress,
                          child: isSaving
                              ? const SizedBox(
                                  height: 22,
                                  width: 22,
                                  child: CircularProgressIndicator(
                                    color: Colors.white,
                                    strokeWidth: 2,
                                  ),
                                )
                              : Text(
                                  isEditing ? 'Save Address' : 'Add Address',
                                  style: BuyerStyle.buttonText,
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
    );

    labelController.dispose();
    line1Controller.dispose();
    line2Controller.dispose();
    cityController.dispose();
    provinceController.dispose();
    postalController.dispose();
    phoneController.dispose();
  }

  // ================================================================
  // SET DEFAULT ADDRESS
  // ================================================================

  Future<bool> _setDefaultAddress(Map<String, dynamic> address) async {
    try {
      final int addressId = int.tryParse(address['id']?.toString() ?? '') ?? 0;

      if (addressId <= 0) {
        _showErrorMessage('Invalid delivery address.');

        return false;
      }

      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        _showErrorMessage('Your session has expired. Please login again.');

        return false;
      }

      final http.Response response = await http.post(
        Uri.parse('$_baseUrl/addresses/$addressId/set-default'),
        headers: _authHeaders(token, json: true),
        body: jsonEncode(<String, dynamic>{}),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode != 200) {
        _showErrorMessage(
          _extractErrorMessage(
            decoded,
            fallback: 'Unable to update the default address.',
          ),
        );

        return false;
      }

      if (!mounted) {
        return false;
      }

      _showSuccessMessage(
        decoded is Map<String, dynamic> &&
                decoded['message']?.toString().trim().isNotEmpty == true
            ? decoded['message'].toString().trim()
            : 'Default address updated.',
      );

      return true;
    } catch (_) {
      _showErrorMessage('Unable to connect to the server. Please try again.');

      return false;
    }
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

  Widget _divider() {
    return const Divider(
      height: 1,
      indent: 70,
      endIndent: 15,
      color: BuyerColors.border,
    );
  }

  // ================================================================
  // ADDRESS STRING
  // ================================================================

  String _buildFullAddress(Map<String, dynamic> address) {
    return [
      address['address_line1']?.toString().trim() ?? '',
      address['address_line2']?.toString().trim() ?? '',
      address['city']?.toString().trim() ?? '',
      address['province']?.toString().trim() ?? '',
      address['postal_code']?.toString().trim() ?? '',
      address['country']?.toString().trim() ?? '',
    ].where((String part) => part.isNotEmpty).join(', ');
  }

  // ================================================================
  // SNACKBARS
  // ================================================================

  void _showSuccessMessage(String message) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          backgroundColor: BuyerColors.primaryDark,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          content: Row(
            children: [
              const Icon(Icons.check_circle_outline, color: Colors.white),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  message,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
  }

  void _showErrorMessage(String message) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          behavior: SnackBarBehavior.floating,
          backgroundColor: BuyerColors.danger,
        ),
      );
  }

  void _showModalMessage(BuildContext modalContext, String message) {
    ScaffoldMessenger.of(modalContext)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
      );
  }

  // ================================================================
  // SETTINGS
  // ================================================================

  void _showSettingsMessage() {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(
          content: Text('Account settings will be available soon.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  void _showComingSoon(String feature) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text('$feature will be available soon.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  // ================================================================
  // LOGOUT
  // ================================================================

  void _showLogoutDialog() {
    showDialog<void>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          backgroundColor: BuyerColors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Text(
            'Logout',
            style: TextStyle(
              fontWeight: FontWeight.w800,
              color: BuyerColors.textDark,
            ),
          ),
          content: const Text(
            'Are you sure you want to logout?',
            style: TextStyle(color: BuyerColors.textGrey),
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);
              },
              child: const Text(
                'Cancel',
                style: TextStyle(color: BuyerColors.textGrey),
              ),
            ),
            TextButton(
              onPressed: () async {
                Navigator.pop(dialogContext);

                await _logout();
              },
              child: const Text(
                'Logout',
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

  Future<void> _logout() async {
    if (isLoggingOut) {
      return;
    }

    setState(() {
      isLoggingOut = true;
    });

    try {
      final String? token = await AuthStorage.getToken();

      if (token != null && token.trim().isNotEmpty) {
        await http.post(
          Uri.parse('$_baseUrl/auth/logout'),
          headers: _authHeaders(token),
        );
      }
    } catch (_) {
      // Local logout will still continue.
    }

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
      (Route<dynamic> route) {
        return false;
      },
    );
  }

  // ================================================================
  // API HELPERS
  // ================================================================

  Map<String, String> _authHeaders(String token, {bool json = false}) {
    return {
      'Accept': 'application/json',
      if (json) 'Content-Type': 'application/json',
      'Authorization': 'Bearer $token',
    };
  }

  dynamic _decodeResponse(String body) {
    if (body.trim().isEmpty) {
      return null;
    }

    try {
      return jsonDecode(body);
    } catch (_) {
      return body;
    }
  }

  String _extractErrorMessage(dynamic decoded, {required String fallback}) {
    if (decoded is Map<String, dynamic>) {
      final dynamic errors = decoded['errors'];

      if (errors is Map) {
        for (final dynamic value in errors.values) {
          if (value is List && value.isNotEmpty) {
            return value.first.toString();
          }

          if (value != null && value.toString().trim().isNotEmpty) {
            return value.toString();
          }
        }
      }

      final String message = decoded['message']?.toString().trim() ?? '';

      if (message.isNotEmpty) {
        return message;
      }
    }

    return fallback;
  }

  bool _isTrue(dynamic value) {
    if (value == true || value == 1) {
      return true;
    }

    final String normalized = value?.toString().trim().toLowerCase() ?? '';

    return normalized == 'true' || normalized == '1';
  }
}
