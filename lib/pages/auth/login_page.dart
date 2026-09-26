import 'package:flutter/material.dart';

import '../buyer/buyer_navigation.dart';
import '../rider/rider_navigation.dart';
import '../logistics/logistics_navigation.dart';

import '../../styles/login_style.dart';

import 'registration_page.dart';
import 'forgot_password_page.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  // ------------------------------------------------------------
  // FORM
  // ------------------------------------------------------------

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  // ------------------------------------------------------------
  // CONTROLLERS
  // ------------------------------------------------------------

  final TextEditingController emailController = TextEditingController();

  final TextEditingController passwordController = TextEditingController();

  // ------------------------------------------------------------
  // FOCUS NODES
  // ------------------------------------------------------------

  final FocusNode emailFocusNode = FocusNode();

  final FocusNode passwordFocusNode = FocusNode();

  // ------------------------------------------------------------
  // STATE
  // ------------------------------------------------------------

  bool hidePassword = true;

  bool rememberMe = false;

  bool isLoading = false;

  // ------------------------------------------------------------
  // LOGIN
  // ------------------------------------------------------------

  Future<void> login() async {
    FocusScope.of(context).unfocus();

    if (isLoading) return;

    final isValid = _formKey.currentState?.validate() ?? false;

    if (!isValid) return;

    final email = emailController.text.trim().toLowerCase();

    // Do not trim passwords automatically.
    final password = passwordController.text;

    setState(() {
      isLoading = true;
    });

    // ----------------------------------------------------------
    // TEMPORARY DEMO LOGIN
    //
    // Replace this section with your API authentication later.
    // ----------------------------------------------------------

    await Future.delayed(const Duration(milliseconds: 800));

    if (!mounted) return;

    if (password != "123456") {
      setState(() {
        isLoading = false;
      });

      showMessage("Incorrect password");

      return;
    }

    Widget? destination;

    switch (email) {
      case "buyer@test.com":
        destination = const BuyerNavigation();
        break;

      case "rider@test.com":
        destination = const RiderNavigation();
        break;

      case "logistics@test.com":
        destination = const LogisticsNavigation();
        break;

      default:
        destination = null;
    }

    if (!mounted) return;

    setState(() {
      isLoading = false;
    });

    if (destination == null) {
      showMessage("Account not found");

      return;
    }

    openPage(destination);
  }

  // ------------------------------------------------------------
  // EMAIL VALIDATOR
  // ------------------------------------------------------------

  String? validateEmail(String? value) {
    final input = value?.trim() ?? "";

    if (input.isEmpty) {
      return "Please enter your email";
    }

    final emailRegex = RegExp(
      r'^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$',
    );

    if (!emailRegex.hasMatch(input)) {
      return "Please enter a valid email address";
    }

    return null;
  }

  // ------------------------------------------------------------
  // PASSWORD VALIDATOR
  // ------------------------------------------------------------

  String? validatePassword(String? value) {
    if (value == null || value.isEmpty) {
      return "Please enter your password";
    }

    if (value.length < 6) {
      return "Password must contain at least 6 characters";
    }

    return null;
  }

  // ------------------------------------------------------------
  // OPEN PAGE
  // ------------------------------------------------------------

  void openPage(Widget page) {
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (context) => page),
    );
  }

  // ------------------------------------------------------------
  // OPEN REGISTRATION
  // ------------------------------------------------------------

  void openRegister() {
    FocusScope.of(context).unfocus();

    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const RegistrationPage()),
    );
  }

  // ------------------------------------------------------------
  // FORGOT PASSWORD
  // ------------------------------------------------------------

  void forgotPassword() {
    FocusScope.of(context).unfocus();

    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const ForgotPasswordPage()),
    );
  }

  // ------------------------------------------------------------
  // MESSAGE
  // ------------------------------------------------------------

  void showMessage(String message) {
    if (!mounted) return;

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

  // ------------------------------------------------------------
  // BUILD
  // ------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final screenSize = MediaQuery.of(context).size;

    final horizontalPadding = screenSize.width < 360 ? 16.0 : 22.0;

    return Scaffold(
      backgroundColor: LoginColors.background,

      body: SafeArea(
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,

          onTap: () {
            FocusScope.of(context).unfocus();
          },

          child: LayoutBuilder(
            builder: (context, constraints) {
              return SingleChildScrollView(
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,

                padding: EdgeInsets.symmetric(
                  horizontal: horizontalPadding,
                  vertical: 24,
                ),

                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: constraints.maxHeight - 48,
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
                              // --------------------------------
                              // LOGO
                              // --------------------------------

                              Center(
                                child: Column(
                                  children: [
                                    Container(
                                      height: 62,
                                      width: 62,

                                      decoration: LoginStyle.logoContainer,

                                      child: const Icon(
                                        Icons.shopping_bag_outlined,

                                        color: LoginColors.primary,

                                        size: 32,
                                      ),
                                    ),

                                    const SizedBox(height: 12),

                                    const Text(
                                      "BiliHub",
                                      style: LoginStyle.logo,
                                    ),
                                  ],
                                ),
                              ),

                              const SizedBox(height: 32),

                              // --------------------------------
                              // WELCOME
                              // --------------------------------
                              const Text(
                                "Welcome Back",
                                style: LoginStyle.title,
                              ),

                              const SizedBox(height: 7),

                              const Text(
                                "Login to continue to BiliHub",
                                style: LoginStyle.subtitle,
                              ),

                              const SizedBox(height: 28),

                              // --------------------------------
                              // EMAIL
                              // --------------------------------
                              const Text(
                                "Email Address",
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

                                onFieldSubmitted: (_) {
                                  passwordFocusNode.requestFocus();
                                },

                                decoration: LoginStyle.inputDecoration(
                                  hint: "Enter your email address",

                                  icon: Icons.mail_outline_rounded,
                                ),
                              ),

                              const SizedBox(height: 20),

                              // --------------------------------
                              // PASSWORD
                              // --------------------------------
                              const Text(
                                "Password",
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

                                onFieldSubmitted: (_) {
                                  login();
                                },

                                decoration:
                                    LoginStyle.inputDecoration(
                                      hint: "Enter your password",

                                      icon: Icons.lock_outline_rounded,
                                    ).copyWith(
                                      suffixIcon: IconButton(
                                        tooltip: hidePassword
                                            ? "Show password"
                                            : "Hide password",

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

                              // --------------------------------
                              // REMEMBER + FORGOT PASSWORD
                              // --------------------------------
                              Row(
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
                                          : (value) {
                                              setState(() {
                                                rememberMe = value ?? false;
                                              });
                                            },
                                    ),
                                  ),

                                  GestureDetector(
                                    onTap: isLoading
                                        ? null
                                        : () {
                                            setState(() {
                                              rememberMe = !rememberMe;
                                            });
                                          },

                                    child: const Text(
                                      "Remember me",
                                      style: LoginStyle.rememberText,
                                    ),
                                  ),

                                  const Spacer(),

                                  TextButton(
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
                                      "Forgot Password?",

                                      style: LoginStyle.forgotPasswordText,
                                    ),
                                  ),
                                ],
                              ),

                              const SizedBox(height: 20),

                              // --------------------------------
                              // LOGIN BUTTON
                              // --------------------------------
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
                                            key: ValueKey("loading"),

                                            width: 22,

                                            height: 22,

                                            child: CircularProgressIndicator(
                                              strokeWidth: 2.2,

                                              color: Colors.white,
                                            ),
                                          )
                                        : const Text(
                                            "Login",

                                            key: ValueKey("login-text"),

                                            style: LoginStyle.buttonText,
                                          ),
                                  ),
                                ),
                              ),

                              const SizedBox(height: 22),

                              // --------------------------------
                              // DIVIDER
                              // --------------------------------
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
                                      "OR",
                                      style: LoginStyle.dividerText,
                                    ),
                                  ),

                                  Expanded(
                                    child: Divider(color: LoginColors.border),
                                  ),
                                ],
                              ),

                              const SizedBox(height: 22),

                              // --------------------------------
                              // REGISTER BUTTON
                              // --------------------------------
                              SizedBox(
                                width: double.infinity,

                                height: 52,

                                child: OutlinedButton(
                                  onPressed: isLoading ? null : openRegister,

                                  style: LoginStyle.secondaryButton,

                                  child: const Text(
                                    "Create an Account",

                                    style: LoginStyle.registerText,
                                  ),
                                ),
                              ),

                              const SizedBox(height: 24),

                              // --------------------------------
                              // FOOTER
                              // --------------------------------
                              const Center(
                                child: Text(
                                  "Shop smart. Shop with BiliHub.",

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

  // ------------------------------------------------------------
  // DISPOSE
  // ------------------------------------------------------------

  @override
  void dispose() {
    emailController.dispose();

    passwordController.dispose();

    emailFocusNode.dispose();

    passwordFocusNode.dispose();

    super.dispose();
  }
}
