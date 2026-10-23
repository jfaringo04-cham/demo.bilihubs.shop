import 'package:flutter/material.dart';

import '../../styles/registration_style.dart';

import 'buyer_registration.dart' as buyer;
import 'logistics_registration.dart';

class RegistrationPage extends StatefulWidget {
  const RegistrationPage({super.key});

  @override
  State<RegistrationPage> createState() => _RegistrationPageState();
}

class _RegistrationPageState extends State<RegistrationPage> {
  late final PageController _pageController;

  // 0 = Logistics
  // 1 = Buyer
  // 2 = Rider
  //
  // Buyer is shown first/default.
  int _currentPage = 1;

  final List<_RegistrationRole> _roles = const [
    _RegistrationRole(
      title: 'Logistics',
      subtitle: 'Manage parcel operations',
      description: 'Register your logistics company to manage hubs, riders, parcels, and delivery operations.',
      icon: Icons.local_shipping_outlined,
      benefits: [
        'Manage logistics operations',
        'Track parcel movement',
        'Manage riders',
        'Handle shipment processing',
      ],
    ),
    _RegistrationRole(
      title: 'Buyer',
      subtitle: 'Shop with BiliHub',
      description: 'Create a Buyer account to browse products, place orders, and track your purchases.',
      icon: Icons.shopping_bag_outlined,
      benefits: [
        'Browse available products',
        'Add products to cart',
        'Place and track orders',
        'Manage your Buyer profile',
      ],
    ),
    _RegistrationRole(
      title: 'Rider',
      subtitle: 'Deliver with BiliHub',
      description: 'Rider accounts are created and managed by an approved BiliHub Logistics account.',
      icon: Icons.delivery_dining_outlined,
      benefits: [
        'Manage assigned deliveries',
        'Scan parcel waybills',
        'Track delivery earnings',
        'Update parcel status',
      ],
    ),
  ];

  @override
  void initState() {
    super.initState();

    _pageController = PageController(initialPage: 1, viewportFraction: 0.84);
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  // ============================================================
  // OPEN SELECTED REGISTRATION
  // ============================================================

  void _openSelectedRegistration() {
    switch (_currentPage) {
      // --------------------------------------------------------
      // LOGISTICS
      // --------------------------------------------------------

      case 0:
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const LogisticsRegistrationPage()),
        );
        break;

      // --------------------------------------------------------
      // BUYER
      // --------------------------------------------------------

      case 1:
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const buyer.RegistrationPage()),
        );
        break;

      // --------------------------------------------------------
      // RIDER
      //
      // Rider accounts should not be created publicly.
      // An approved Logistics account creates Rider accounts.
      // --------------------------------------------------------

      case 2:
        _showRiderInformation();
        break;
    }
  }

  // ============================================================
  // RIDER INFORMATION
  // ============================================================

  Future<void> _showRiderInformation() async {
    await showDialog<void>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Row(
            children: [
              Icon(
                Icons.delivery_dining_outlined,
                color: RegistrationColors.primary,
              ),
              SizedBox(width: 10),
              Expanded(child: Text('Rider Registration')),
            ],
          ),
          content: const Text(
            'Rider accounts cannot be registered directly from this page.\n\n'
            'An approved BiliHub Logistics account must create the Rider account. '
            'After the Rider account is created, the rider will receive an activation link '
            'to set up their account and password.',
            style: TextStyle(height: 1.5, color: RegistrationColors.textDark),
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);
              },
              child: const Text(
                'Got it',
                style: TextStyle(
                  color: RegistrationColors.primary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        );
      },
    );
  }

  // ============================================================
  // BUTTON TEXT
  // ============================================================

  String _buttonText() {
    switch (_currentPage) {
      case 0:
        return 'Register as Logistics';

      case 1:
        return 'Register as Buyer';

      case 2:
        return 'Rider Account Information';

      default:
        return 'Continue';
    }
  }

  // ============================================================
  // BUTTON ICON
  // ============================================================

  IconData _buttonIcon() {
    switch (_currentPage) {
      case 0:
        return Icons.local_shipping_outlined;

      case 1:
        return Icons.shopping_bag_outlined;

      case 2:
        return Icons.info_outline_rounded;

      default:
        return Icons.arrow_forward_rounded;
    }
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RegistrationColors.background,

      appBar: AppBar(
        backgroundColor: RegistrationColors.background,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        leading: IconButton(
          tooltip: 'Back',
          onPressed: () {
            Navigator.pop(context);
          },
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: RegistrationColors.textDark,
            size: 20,
          ),
        ),
        title: const Text(
          'Create Account',
          style: RegistrationStyle.appBarTitle,
        ),
      ),

      body: SafeArea(
        top: false,
        child: Column(
          children: [
            const SizedBox(height: 16),

            // ==================================================
            // HEADER
            // ==================================================
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                children: [
                  Text(
                    'Join BiliHub',
                    textAlign: TextAlign.center,
                    style: RegistrationStyle.title,
                  ),
                  SizedBox(height: 8),
                  Text(
                    'Choose the type of account you want to create.',
                    textAlign: TextAlign.center,
                    style: RegistrationStyle.subtitle,
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // ==================================================
            // CAROUSEL
            // ==================================================
            Expanded(
              child: PageView.builder(
                controller: _pageController,
                itemCount: _roles.length,
                onPageChanged: (int index) {
                  setState(() {
                    _currentPage = index;
                  });
                },
                itemBuilder: (BuildContext context, int index) {
                  final _RegistrationRole role = _roles[index];

                  final bool selected = index == _currentPage;

                  return AnimatedPadding(
                    duration: const Duration(milliseconds: 220),
                    padding: EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: selected ? 8 : 28,
                    ),
                    child: AnimatedScale(
                      duration: const Duration(milliseconds: 220),
                      scale: selected ? 1 : 0.94,
                      child: AnimatedOpacity(
                        duration: const Duration(milliseconds: 220),
                        opacity: selected ? 1 : 0.68,
                        child: _buildRoleCard(
                          role,
                          isRider: role.title == 'Rider',
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),

            const SizedBox(height: 10),

            // ==================================================
            // PAGE INDICATOR
            // ==================================================
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(_roles.length, (int index) {
                final bool selected = index == _currentPage;

                return AnimatedContainer(
                  duration: const Duration(milliseconds: 220),
                  margin: const EdgeInsets.symmetric(horizontal: 4),
                  width: selected ? 24 : 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: selected
                        ? RegistrationColors.primary
                        : RegistrationColors.border,
                    borderRadius: BorderRadius.circular(20),
                  ),
                );
              }),
            ),

            const SizedBox(height: 22),

            // ==================================================
            // CONTINUE BUTTON
            // ==================================================
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
              child: SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton.icon(
                  onPressed: _openSelectedRegistration,
                  style: RegistrationStyle.primaryButton,
                  icon: Icon(_buttonIcon(), size: 20),
                  label: Text(
                    _buttonText(),
                    style: RegistrationStyle.buttonText,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // ROLE CARD
  // ============================================================

  Widget _buildRoleCard(_RegistrationRole role, {required bool isRider}) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: RegistrationStyle.card,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // ----------------------------------------------------
          // ICON
          // ----------------------------------------------------

          Container(
            height: 82,
            width: 82,
            decoration: BoxDecoration(
              color: RegistrationColors.lightPurple,
              borderRadius: BorderRadius.circular(24),
            ),
            child: Icon(role.icon, size: 42, color: RegistrationColors.primary),
          ),

          const SizedBox(height: 22),

          // ----------------------------------------------------
          // TITLE
          // ----------------------------------------------------
          Text(
            role.title,
            textAlign: TextAlign.center,
            style: RegistrationStyle.title,
          ),

          const SizedBox(height: 6),

          Text(
            role.subtitle,
            textAlign: TextAlign.center,
            style: RegistrationStyle.sectionTitle,
          ),

          const SizedBox(height: 14),

          Text(
            role.description,
            textAlign: TextAlign.center,
            style: RegistrationStyle.subtitle,
          ),

          const SizedBox(height: 24),

          // ----------------------------------------------------
          // BENEFITS
          // ----------------------------------------------------
          ...role.benefits.map((String benefit) {
            return Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.check_circle_outline_rounded,
                    size: 21,
                    color: RegistrationColors.success,
                  ),

                  const SizedBox(width: 10),

                  Expanded(
                    child: Text(benefit, style: RegistrationStyle.termsText),
                  ),
                ],
              ),
            );
          }),

          // ----------------------------------------------------
          // RIDER INFORMATION
          // ----------------------------------------------------
          if (isRider) ...[
            const SizedBox(height: 8),

            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: RegistrationColors.lightPurple,
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.info_outline_rounded,
                    color: RegistrationColors.primary,
                    size: 20,
                  ),

                  SizedBox(width: 8),

                  Expanded(
                    child: Text(
                      'Rider accounts are created by an approved Logistics account. '
                      'Direct public Rider registration is not available.',
                      style: RegistrationStyle.termsText,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

// ============================================================
// ROLE MODEL
// ============================================================

class _RegistrationRole {
  final String title;
  final String subtitle;
  final String description;
  final IconData icon;
  final List<String> benefits;

  const _RegistrationRole({
    required this.title,
    required this.subtitle,
    required this.description,
    required this.icon,
    required this.benefits,
  });
}
