import 'package:flutter/material.dart';

import '../../sevices/api_services.dart';
import '../../sevices/auth_storage.dart';
import '../../styles/login_style.dart';

import '../buyer/buyer_navigation.dart';
import '../logistics/logistics_navigation.dart';
import '../rider/rider_navigation.dart';

import 'forgot_password_page.dart';
import 'registration_page.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  // ============================================================
  // FORM
  // ============================================================

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  // ============================================================
  // CONTROLLERS
  // ============================================================

  final TextEditingController emailController = TextEditingController();

  final TextEditingController passwordController = TextEditingController();

  // ============================================================
  // FOCUS NODES
  // ============================================================

  final FocusNode emailFocusNode = FocusNode();

  final FocusNode passwordFocusNode = FocusNode();

  // ============================================================
  // STATE
  // ============================================================

  bool hidePassword = true;

  bool rememberMe = false;

  bool isLoading = false;

  bool isCheckingSession = true;

  // ============================================================
  // INITIALIZE
  // ============================================================

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  // ============================================================
  // RESTORE SAVED SESSION
  // ============================================================

  Future<void> _restoreSession() async {
    try {
      final bool shouldRemember = await AuthStorage.getRememberMe();

      if (!mounted) {
        return;
      }

      setState(() {
        rememberMe = shouldRemember;
      });

      if (!shouldRemember) {
        setState(() {
          isCheckingSession = false;
        });

        return;
      }

      final String? token = await AuthStorage.getToken();

      final Map<String, dynamic>? user = await AuthStorage.getUser();

      if (!mounted) {
        return;
      }

      if (token == null || user == null || user.isEmpty) {
        await AuthStorage.clearSession();

        if (!mounted) {
          return;
        }

        setState(() {
          rememberMe = false;
          isCheckingSession = false;
        });

        return;
      }

      final Widget? destination = _destinationForUser(user);

      if (destination == null) {
        await AuthStorage.clearSession();

        if (!mounted) {
          return;
        }

        setState(() {
          rememberMe = false;
          isCheckingSession = false;
        });

        return;
      }

      if (!mounted) {
        return;
      }

      _openAuthenticatedPage(destination);
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        isCheckingSession = false;
      });
    }
  }

  // ============================================================
  // LOGIN
  // ============================================================

  Future<void> login() async {
    FocusScope.of(context).unfocus();

    if (isLoading) {
      return;
    }

    final bool isValid = _formKey.currentState?.validate() ?? false;

    if (!isValid) {
      return;
    }

    final String email = emailController.text.trim().toLowerCase();

    final String password = passwordController.text;

    setState(() {
      isLoading = true;
    });

    final Map<String, dynamic> result = await ApiService.login(
      email: email,
      password: password,
    );

    if (!mounted) {
      return;
    }

    setState(() {
      isLoading = false;
    });

    if (result['success'] != true) {
      showMessage(result['message']?.toString() ?? 'Unable to login.');

      return;
    }

    final dynamic rawUser = result['user'];

    final dynamic rawToken = result['token'];

    if (rawUser is! Map ||
        rawToken == null ||
        rawToken.toString().trim().isEmpty) {
      showMessage('Invalid login data received from server.');

      return;
    }

    final Map<String, dynamic> authenticatedUser = Map<String, dynamic>.from(
      rawUser,
    );

    final String token = rawToken.toString().trim();

    final Widget? destination = _destinationForUser(authenticatedUser);

    if (destination == null) {
      showMessage('This account role is not available in the mobile app.');

      return;
    }

    await AuthStorage.saveSession(
      token: token,
      user: authenticatedUser,
      rememberMe: rememberMe,
    );

    if (!mounted) {
      return;
    }

    _openAuthenticatedPage(destination);
  }

  // ============================================================
  // DESTINATION BY ROLE
  // ============================================================

  Widget? _destinationForUser(Map<String, dynamic> user) {
    final dynamic rawRoles = user['roles'];

    if (rawRoles is! List || rawRoles.isEmpty) {
      return null;
    }

    for (final dynamic rawRole in rawRoles) {
      String role = '';

      if (rawRole is Map) {
        role = rawRole['name']?.toString().trim().toLowerCase() ?? '';
      } else {
        role = rawRole.toString().trim().toLowerCase();
      }

      switch (role) {
        case 'buyer':
          return BuyerNavigation(user: user);

        case 'rider':
          return const RiderNavigation();

        case 'logistics':
        case 'logistic':
        case 'logistic_owner':
          return const LogisticsNavigation();
      }
    }

    return null;
  }

  // ============================================================
  // EMAIL VALIDATOR
  // ============================================================

  String? validateEmail(String? value) {
    final String input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter your email';
    }

    final RegExp emailRegex = RegExp(
      r'^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$',
    );

    if (!emailRegex.hasMatch(input)) {
      return 'Please enter a valid email address';
    }

    return null;
  }

  // ============================================================
  // PASSWORD VALIDATOR
  // ============================================================

  String? validatePassword(String? value) {
    if (value == null || value.isEmpty) {
      return 'Please enter your password';
    }

    if (value.length < 6) {
      return 'Password must contain at least 6 characters';
    }

    return null;
  }

  // ============================================================
  // OPEN AUTHENTICATED PAGE
  // ============================================================

  void _openAuthenticatedPage(Widget page) {
    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return page;
        },
      ),
      (Route<dynamic> route) {
        return false;
      },
    );
  }

  // ============================================================
  // OPEN REGISTRATION
  // ============================================================

  void openRegister() {
    FocusScope.of(context).unfocus();

    Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return const RegistrationPage();
        },
      ),
    );
  }

  // ============================================================
  // FORGOT PASSWORD
  // ============================================================

  void forgotPassword() {
    FocusScope.of(context).unfocus();

    Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return const ForgotPasswordPage();
        },
      ),
    );
  }

  // ============================================================
  // MESSAGE
  // ============================================================

  void showMessage(String message) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          behavior: SnackBarBehavior.floating,
          backgroundColor: LoginColors.textDark,
          margin: const EdgeInsets.all(16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      );
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    if (isCheckingSession) {
      return const Scaffold(
        backgroundColor: LoginColors.background,
        body: Center(
          child: CircularProgressIndicator(color: LoginColors.primary),
        ),
      );
    }

    final Size screenSize = MediaQuery.sizeOf(context);

    final double horizontalPadding = screenSize.width < 360 ? 16 : 22;

    return Scaffold(
      backgroundColor: LoginColors.background,
      body: SafeArea(
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () {
            FocusScope.of(context).unfocus();
          },
          child: LayoutBuilder(
            builder: (BuildContext context, BoxConstraints constraints) {
              return SingleChildScrollView(
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                padding: EdgeInsets.symmetric(
                  horizontal: horizontalPadding,
                  vertical: 24,
                ),
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: constraints.maxHeight > 48
                        ? constraints.maxHeight - 48
                        : 0,
                  ),
                  child: Center(
                    child: Container(
                      width: double.infinity,
                      constraints: const BoxConstraints(maxWidth: 430),
                      padding: EdgeInsets.symmetric(
                        horizontal: screenSize.width < 360 ? 18 : 24,
                        vertical: 28,
                      ),
                      decoration: LoginStyle.card,
                      child: AutofillGroup(
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              // ==================================================
                              // LOGO
                              // ==================================================

                              Center(
                                child: Image.asset(
                                  'assets/bilihub_logo.png',
                                  width: 260,
                                  fit: BoxFit.contain,
                                ),
                              ),

                              const SizedBox(height: 32),

                              // ==================================================
                              // WELCOME
                              // ==================================================
                              const Text(
                                'Welcome Back',
                                style: LoginStyle.title,
                              ),

                              const SizedBox(height: 7),

                              const Text(
                                'Login to continue to BiliHub',
                                style: LoginStyle.subtitle,
                              ),

                              const SizedBox(height: 28),

                              // ==================================================
                              // EMAIL
                              // ==================================================
                              const Text(
                                'Email Address',
                                style: LoginStyle.sectionTitle,
                              ),

                              const SizedBox(height: 8),

                              TextFormField(
                                controller: emailController,
                                focusNode: emailFocusNode,
                                enabled: !isLoading,
                                keyboardType: TextInputType.emailAddress,
                                textInputAction: TextInputAction.next,
                                autofillHints: const [
                                  AutofillHints.email,
                                  AutofillHints.username,
                                ],
                                autocorrect: false,
                                style: LoginStyle.inputText,
                                validator: validateEmail,
                                onFieldSubmitted: (String value) {
                                  passwordFocusNode.requestFocus();
                                },
                                decoration: LoginStyle.inputDecoration(
                                  hint: 'Enter your email address',
                                  icon: Icons.mail_outline_rounded,
                                ),
                              ),

                              const SizedBox(height: 20),

                              // ==================================================
                              // PASSWORD
                              // ==================================================
                              const Text(
                                'Password',
                                style: LoginStyle.sectionTitle,
                              ),

                              const SizedBox(height: 8),

                              TextFormField(
                                controller: passwordController,
                                focusNode: passwordFocusNode,
                                enabled: !isLoading,
                                obscureText: hidePassword,
                                keyboardType: TextInputType.visiblePassword,
                                textInputAction: TextInputAction.done,
                                autofillHints: const [AutofillHints.password],
                                autocorrect: false,
                                enableSuggestions: false,
                                style: LoginStyle.inputText,
                                validator: validatePassword,
                                onFieldSubmitted: (String value) {
                                  login();
                                },
                                decoration:
                                    LoginStyle.inputDecoration(
                                      hint: 'Enter your password',
                                      icon: Icons.lock_outline_rounded,
                                    ).copyWith(
                                      suffixIcon: IconButton(
                                        tooltip: hidePassword
                                            ? 'Show password'
                                            : 'Hide password',
                                        splashRadius: 20,
                                        icon: Icon(
                                          hidePassword
                                              ? Icons.visibility_off_outlined
                                              : Icons.visibility_outlined,
                                          color: LoginColors.textGrey,
                                          size: 21,
                                        ),
                                        onPressed: isLoading
                                            ? null
                                            : () {
                                                setState(() {
                                                  hidePassword = !hidePassword;
                                                });
                                              },
                                      ),
                                    ),
                              ),

                              const SizedBox(height: 10),

                              // ==================================================
                              // REMEMBER + FORGOT PASSWORD
                              // ==================================================
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.center,
                                children: [
                                  Transform.scale(
                                    scale: 0.9,
                                    child: Checkbox(
                                      value: rememberMe,
                                      activeColor: LoginColors.primary,
                                      side: const BorderSide(
                                        color: LoginColors.border,
                                        width: 1.4,
                                      ),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(5),
                                      ),
                                      onChanged: isLoading
                                          ? null
                                          : (bool? value) {
                                              setState(() {
                                                rememberMe = value ?? false;
                                              });
                                            },
                                    ),
                                  ),

                                  Expanded(
                                    child: GestureDetector(
                                      onTap: isLoading
                                          ? null
                                          : () {
                                              setState(() {
                                                rememberMe = !rememberMe;
                                              });
                                            },
                                      child: const Text(
                                        'Remember me',
                                        style: LoginStyle.rememberText,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                  ),

                                  Flexible(
                                    child: TextButton(
                                      onPressed: isLoading
                                          ? null
                                          : forgotPassword,
                                      style: TextButton.styleFrom(
                                        foregroundColor: LoginColors.primary,
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 4,
                                          vertical: 8,
                                        ),
                                        tapTargetSize:
                                            MaterialTapTargetSize.shrinkWrap,
                                      ),
                                      child: const Text(
                                        'Forgot Password?',
                                        style: LoginStyle.forgotPasswordText,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                  ),
                                ],
                              ),

                              const SizedBox(height: 20),

                              // ==================================================
                              // LOGIN BUTTON
                              // ==================================================
                              SizedBox(
                                width: double.infinity,
                                height: 52,
                                child: ElevatedButton(
                                  onPressed: isLoading ? null : login,
                                  style: LoginStyle.primaryButton,
                                  child: AnimatedSwitcher(
                                    duration: const Duration(milliseconds: 200),
                                    child: isLoading
                                        ? const SizedBox(
                                            key: ValueKey<String>('loading'),
                                            width: 22,
                                            height: 22,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2.2,
                                              color: Colors.white,
                                            ),
                                          )
                                        : const Text(
                                            'Login',
                                            key: ValueKey<String>('login-text'),
                                            style: LoginStyle.buttonText,
                                          ),
                                  ),
                                ),
                              ),

                              const SizedBox(height: 22),

                              // ==================================================
                              // DIVIDER
                              // ==================================================
                              const Row(
                                children: [
                                  Expanded(
                                    child: Divider(color: LoginColors.border),
                                  ),
                                  Padding(
                                    padding: EdgeInsets.symmetric(
                                      horizontal: 12,
                                    ),
                                    child: Text(
                                      'OR',
                                      style: LoginStyle.dividerText,
                                    ),
                                  ),
                                  Expanded(
                                    child: Divider(color: LoginColors.border),
                                  ),
                                ],
                              ),

                              const SizedBox(height: 22),

                              // ==================================================
                              // REGISTER
                              // ==================================================
                              SizedBox(
                                width: double.infinity,
                                height: 52,
                                child: OutlinedButton(
                                  onPressed: isLoading ? null : openRegister,
                                  style: LoginStyle.secondaryButton,
                                  child: const Text(
                                    'Create an Account',
                                    style: LoginStyle.registerText,
                                  ),
                                ),
                              ),

                              const SizedBox(height: 24),

                              // ==================================================
                              // FOOTER
                              // ==================================================
                              const Center(
                                child: Text(
                                  'Shop smart. Shop with BiliHub.',
                                  textAlign: TextAlign.center,
                                  style: LoginStyle.footerText,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  // ============================================================
  // DISPOSE
  // ============================================================

  @override
  void dispose() {
    emailController.dispose();
    passwordController.dispose();

    emailFocusNode.dispose();
    passwordFocusNode.dispose();

    super.dispose();
  }
}
