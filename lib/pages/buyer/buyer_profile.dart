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
      if (!mounted) {
        return;
      }

      _refreshProfile();
    });
  }

  // ============================================================
  // INITIAL USER
  // ============================================================

  void _loadInitialUser() {
    final String initialName = widget.user['name']?.toString().trim() ?? '';

    buyerName = initialName.isNotEmpty ? initialName : 'Buyer';

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
  // REFRESH PROFILE
  // ============================================================

  Future<void> _refreshProfile() async {
    if (!mounted || isLoadingProfile) {
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

      final List<http.Response> responses = await Future.wait<http.Response>(
        <Future<http.Response>>[
          http.get(
            Uri.parse('$_baseUrl/auth/user'),
            headers: _authHeaders(token),
          ),
          http.get(
            Uri.parse('$_baseUrl/addresses'),
            headers: _authHeaders(token),
          ),
        ],
      );

      if (!mounted) {
        return;
      }

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
              .map((Map item) => Map<String, dynamic>.from(item))
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
    } catch (error) {
      debugPrint('Buyer profile refresh error: $error');

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
                _buildHeader(),
                const SizedBox(height: 20),
                _buildProfileCard(),
                const SizedBox(height: 25),
                const Text(
                  'Personal Information',
                  style: BuyerStyle.sectionTitle,
                ),
                const SizedBox(height: 12),
                _buildPersonalInformation(),
                const SizedBox(height: 25),
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
                const Text('My Account', style: BuyerStyle.sectionTitle),
                const SizedBox(height: 12),
                _buildAccountSection(),
                const SizedBox(height: 25),
                const Text('Preferences', style: BuyerStyle.sectionTitle),
                const SizedBox(height: 12),
                _buildPreferencesSection(),
                const SizedBox(height: 25),
                const Text('Support', style: BuyerStyle.sectionTitle),
                const SizedBox(height: 12),
                _buildSupportSection(),
                const SizedBox(height: 25),
                _buildLogoutButton(),
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

  // ============================================================
  // HEADER
  // ============================================================

  Widget _buildHeader() {
    return Row(
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
    );
  }

  // ============================================================
  // PROFILE CARD
  // ============================================================

  Widget _buildProfileCard() {
    return Container(
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
              border: Border.all(color: BuyerColors.softPurple, width: 2),
            ),
            child: const Icon(
              Icons.person_outline,
              size: 50,
              color: BuyerColors.primary,
            ),
          ),
          const SizedBox(height: 14),
          Text(buyerName, style: BuyerStyle.title, textAlign: TextAlign.center),
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
                minimumSize: const WidgetStatePropertyAll<Size>(Size(145, 44)),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PERSONAL INFORMATION
  // ============================================================

  Widget _buildPersonalInformation() {
    return Container(
      decoration: BuyerStyle.card,
      child: Column(
        children: [
          _profileInfo(Icons.person_outline, 'Full Name', buyerName),
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
            buyerPhone.isEmpty ? 'No phone number added' : buyerPhone,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // ACCOUNT
  // ============================================================

  Widget _buildAccountSection() {
    return Container(
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
    );
  }

  // ============================================================
  // PREFERENCES
  // ============================================================

  Widget _buildPreferencesSection() {
    return Container(
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
    );
  }

  // ============================================================
  // SUPPORT
  // ============================================================

  Widget _buildSupportSection() {
    return Container(
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
    );
  }

  // ============================================================
  // EDIT PROFILE
  // ============================================================

  Future<void> _showEditProfile() async {
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

    final bool? updated = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (BuildContext modalContext) {
        return StatefulBuilder(
          builder: (BuildContext modalContext, StateSetter setModalState) {
            Future<void> saveChanges() async {
              if (isSaving) {
                return;
              }

              final String name = nameController.text.trim();
              final String phone = phoneController.text.trim();

              if (name.isEmpty) {
                _showModalMessage(modalContext, 'Please enter your full name.');
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

                if (!modalContext.mounted) {
                  return;
                }

                if (token == null || token.trim().isEmpty) {
                  setModalState(() {
                    isSaving = false;
                  });

                  _showModalMessage(
                    modalContext,
                    'Your session has expired. Please login again.',
                  );

                  return;
                }

                final http.Response response = await http.put(
                  Uri.parse('$_baseUrl/auth/profile'),
                  headers: _authHeaders(token, json: true),
                  body: jsonEncode(<String, dynamic>{
                    'name': name,
                    'mobile_number': phone,
                  }),
                );

                if (!modalContext.mounted) {
                  return;
                }

                final dynamic decoded = _decodeResponse(response.body);

                if (response.statusCode != 200) {
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

                  return;
                }

                Map<String, dynamic> updatedUser = <String, dynamic>{};

                if (decoded is Map<String, dynamic> && decoded['user'] is Map) {
                  updatedUser = Map<String, dynamic>.from(decoded['user']);
                }

                if (updatedUser.isNotEmpty) {
                  await AuthStorage.updateUser(updatedUser);
                } else {
                  await AuthStorage.updateUser(<String, dynamic>{
                    'name': name,
                    'mobile_number': phone,
                    'phone': phone,
                  });
                }

                if (!modalContext.mounted) {
                  return;
                }

                Navigator.of(modalContext).pop(true);
              } catch (error) {
                debugPrint('Update buyer profile error: $error');

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
                  maxHeight: MediaQuery.of(modalContext).size.height * 0.85,
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
                      _sheetHandle(),
                      const SizedBox(height: 20),
                      Row(
                        children: [
                          const Expanded(
                            child: Text(
                              'Edit Profile',
                              style: BuyerStyle.title,
                            ),
                          ),
                          IconButton(
                            onPressed: isSaving
                                ? null
                                : () {
                                    Navigator.of(modalContext).pop(false);
                                  },
                            icon: const Icon(Icons.close_rounded),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
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
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );

    await WidgetsBinding.instance.endOfFrame;

    nameController.dispose();
    emailController.dispose();
    phoneController.dispose();

    if (!mounted || updated != true) {
      return;
    }

    await _refreshProfile();

    if (!mounted) {
      return;
    }

    _showSuccessMessage('Profile updated successfully.');
  }

  // ============================================================
  // ADDRESS MANAGER
  // ============================================================

  Future<void> _showAddressManager() async {
    await _refreshProfile();

    if (!mounted) {
      return;
    }

    bool keepManagerOpen = true;

    while (keepManagerOpen) {
      if (!mounted) {
        return;
      }

      final _AddressAction? action = await showModalBottomSheet<_AddressAction>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (BuildContext sheetContext) {
          return _buildAddressManagerSheet(sheetContext);
        },
      );

      if (!mounted) {
        return;
      }

      if (action == null || action.type == _AddressActionType.close) {
        keepManagerOpen = false;
        break;
      }

      await WidgetsBinding.instance.endOfFrame;

      if (!mounted) {
        return;
      }

      switch (action.type) {
        case _AddressActionType.add:
          {
            final bool saved = await _showAddressForm();

            if (!mounted) {
              return;
            }

            if (saved) {
              await _refreshProfile();

              if (!mounted) {
                return;
              }

              _showSuccessMessage('Address saved successfully.');
            }

            break;
          }

        case _AddressActionType.edit:
          {
            final Map<String, dynamic>? address = action.address;

            if (address == null) {
              break;
            }

            final bool saved = await _showAddressForm(address: address);

            if (!mounted) {
              return;
            }

            if (saved) {
              await _refreshProfile();

              if (!mounted) {
                return;
              }

              _showSuccessMessage('Address updated successfully.');
            }

            break;
          }

        case _AddressActionType.setDefault:
          {
            final Map<String, dynamic>? address = action.address;

            if (address == null) {
              break;
            }

            final bool success = await _setDefaultAddress(address);

            if (!mounted) {
              return;
            }

            if (success) {
              await _refreshProfile();

              if (!mounted) {
                return;
              }
            }

            break;
          }

        case _AddressActionType.close:
          keepManagerOpen = false;
          break;
      }

      if (!mounted) {
        return;
      }

      await WidgetsBinding.instance.endOfFrame;
    }

    if (!mounted) {
      return;
    }

    await _refreshProfile();
  }

  Widget _buildAddressManagerSheet(BuildContext sheetContext) {
    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(sheetContext).size.height * 0.82,
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
            _sheetHandle(),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 14),
              child: Row(
                children: [
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Delivery Addresses', style: BuyerStyle.title),
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
                      Navigator.of(sheetContext).pop(
                        const _AddressAction(type: _AddressActionType.close),
                      );
                    },
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            Expanded(
              child: buyerAddresses.isEmpty
                  ? _buildNoAddressState()
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(20, 0, 20, 15),
                      itemCount: buyerAddresses.length,
                      separatorBuilder: (BuildContext context, int index) {
                        return const SizedBox(height: 10);
                      },
                      itemBuilder: (BuildContext context, int index) {
                        final Map<String, dynamic> address =
                            buyerAddresses[index];

                        return _buildAddressCard(sheetContext, address);
                      },
                    ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 20),
              child: SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton.icon(
                  onPressed: () {
                    Navigator.of(
                      sheetContext,
                    ).pop(const _AddressAction(type: _AddressActionType.add));
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
  }

  Widget _buildAddressCard(
    BuildContext sheetContext,
    Map<String, dynamic> address,
  ) {
    final bool isDefault = _isTrue(address['is_default']);

    final String label = address['label']?.toString().trim() ?? '';

    final String fullAddress = address['full_address']?.toString().trim() ?? '';

    final String phone = address['phone']?.toString().trim() ?? '';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BuyerStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
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
                  label.isNotEmpty ? label : 'Delivery Address',
                  style: BuyerStyle.cardTitle,
                ),
              ),
              if (isDefault)
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: BuyerColors.lightPurple,
                    borderRadius: BorderRadius.circular(20),
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
            fullAddress.isNotEmpty ? fullAddress : _buildFullAddress(address),
            style: BuyerStyle.small,
          ),
          if (phone.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(phone, style: BuyerStyle.small),
          ],
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () {
                    Navigator.of(sheetContext).pop(
                      _AddressAction(
                        type: _AddressActionType.edit,
                        address: Map<String, dynamic>.from(address),
                      ),
                    );
                  },
                  icon: const Icon(Icons.edit_outlined, size: 17),
                  label: const Text('Edit'),
                ),
              ),
              if (!isDefault) ...[
                const SizedBox(width: 8),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.of(sheetContext).pop(
                        _AddressAction(
                          type: _AddressActionType.setDefault,
                          address: Map<String, dynamic>.from(address),
                        ),
                      );
                    },
                    style: BuyerStyle.primaryButton,
                    child: const Text('Set Default'),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildNoAddressState() {
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

  // ============================================================
  // ADD / EDIT ADDRESS
  // ============================================================

  Future<bool> _showAddressForm({Map<String, dynamic>? address}) async {
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

    final bool? saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (BuildContext modalContext) {
        return StatefulBuilder(
          builder: (BuildContext modalContext, StateSetter setModalState) {
            Future<void> saveAddress() async {
              if (isSaving) {
                return;
              }

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

              if (!modalContext.mounted) {
                return;
              }

              setModalState(() {
                isSaving = true;
              });

              try {
                final String? token = await AuthStorage.getToken();

                if (!modalContext.mounted) {
                  return;
                }

                if (token == null || token.trim().isEmpty) {
                  setModalState(() {
                    isSaving = false;
                  });

                  _showModalMessage(
                    modalContext,
                    'Your session has expired. Please login again.',
                  );

                  return;
                }

                final Map<String, dynamic> payload = <String, dynamic>{
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

                  if (addressId <= 0) {
                    setModalState(() {
                      isSaving = false;
                    });

                    _showModalMessage(
                      modalContext,
                      'Invalid delivery address.',
                    );

                    return;
                  }

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

                if (!modalContext.mounted) {
                  return;
                }

                final dynamic decoded = _decodeResponse(response.body);

                if (response.statusCode != 200 && response.statusCode != 201) {
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

                  return;
                }

                if (!modalContext.mounted) {
                  return;
                }

                Navigator.of(modalContext).pop(true);
              } catch (error) {
                debugPrint('Save buyer address error: $error');

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
                      _sheetHandle(),
                      const SizedBox(height: 20),
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              isEditing ? 'Edit Address' : 'Add Address',
                              style: BuyerStyle.title,
                            ),
                          ),
                          IconButton(
                            onPressed: isSaving
                                ? null
                                : () {
                                    Navigator.of(modalContext).pop(false);
                                  },
                            icon: const Icon(Icons.close_rounded),
                          ),
                        ],
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

    await WidgetsBinding.instance.endOfFrame;

    labelController.dispose();
    line1Controller.dispose();
    line2Controller.dispose();
    cityController.dispose();
    provinceController.dispose();
    postalController.dispose();
    phoneController.dispose();

    return saved == true;
  }

  // ============================================================
  // SET DEFAULT ADDRESS
  // ============================================================

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
    } catch (error) {
      debugPrint('Set default address error: $error');

      if (mounted) {
        _showErrorMessage('Unable to connect to the server. Please try again.');
      }

      return false;
    }
  }

  // ============================================================
  // PROFILE INFORMATION
  // ============================================================

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

  // ============================================================
  // PROFILE ITEM
  // ============================================================

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

  Widget _sheetHandle() {
    return Center(
      child: Container(
        height: 4,
        width: 45,
        decoration: BoxDecoration(
          color: BuyerColors.border,
          borderRadius: BorderRadius.circular(10),
        ),
      ),
    );
  }

  // ============================================================
  // ADDRESS STRING
  // ============================================================

  String _buildFullAddress(Map<String, dynamic> address) {
    return <String>[
      address['address_line1']?.toString().trim() ?? '',
      address['address_line2']?.toString().trim() ?? '',
      address['city']?.toString().trim() ?? '',
      address['province']?.toString().trim() ?? '',
      address['postal_code']?.toString().trim() ?? '',
      address['country']?.toString().trim() ?? '',
    ].where((String part) => part.isNotEmpty).join(', ');
  }

  // ============================================================
  // MESSAGES
  // ============================================================

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
    if (!modalContext.mounted) {
      return;
    }

    final ScaffoldMessengerState? messenger = ScaffoldMessenger.maybeOf(
      modalContext,
    );

    if (messenger == null) {
      return;
    }

    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
      );
  }

  // ============================================================
  // SETTINGS
  // ============================================================

  void _showSettingsMessage() {
    if (!mounted) {
      return;
    }

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
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text('$feature will be available soon.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  // ============================================================
  // LOGOUT
  // ============================================================

  Widget _buildLogoutButton() {
    return SizedBox(
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
    );
  }

  Future<void> _showLogoutDialog() async {
    final bool? confirmed = await showDialog<bool>(
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
                Navigator.of(dialogContext).pop(false);
              },
              child: const Text(
                'Cancel',
                style: TextStyle(color: BuyerColors.textGrey),
              ),
            ),
            TextButton(
              onPressed: () {
                Navigator.of(dialogContext).pop(true);
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

    if (!mounted || confirmed != true) {
      return;
    }

    await _logout();
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
    } catch (error) {
      debugPrint('Buyer logout server error: $error');
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

  // ============================================================
  // API HELPERS
  // ============================================================

  Map<String, String> _authHeaders(String token, {bool json = false}) {
    return <String, String>{
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

enum _AddressActionType { add, edit, setDefault, close }

class _AddressAction {
  final _AddressActionType type;
  final Map<String, dynamic>? address;

  const _AddressAction({required this.type, this.address});
}
