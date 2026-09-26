import 'package:flutter/material.dart';

import '../../styles/forgot_password_style.dart';

class ForgotPasswordPage extends StatefulWidget {
  const ForgotPasswordPage({super.key});

  @override
  State<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<ForgotPasswordPage> {
  // ------------------------------------------------------------
  // FORM
  // ------------------------------------------------------------

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  // ------------------------------------------------------------
  // CONTROLLER
  // ------------------------------------------------------------

  final TextEditingController emailController = TextEditingController();

  // ------------------------------------------------------------
  // FOCUS NODE
  // ------------------------------------------------------------

  final FocusNode emailFocusNode = FocusNode();

  // ------------------------------------------------------------
  // STATE
  // ------------------------------------------------------------

  bool isLoading = false;

  // ------------------------------------------------------------
  // SEND RESET CODE
  // ------------------------------------------------------------

  Future<void> sendResetCode() async {
    FocusScope.of(context).unfocus();

    if (isLoading) return;

    final isValid = _formKey.currentState?.validate() ?? false;

    if (!isValid) return;

    final email = emailController.text.trim().toLowerCase();

    setState(() {
      isLoading = true;
    });

    // ----------------------------------------------------------
    // TEMPORARY RESET PASSWORD LOGIC
    //
    // Later replace this section with your API:
    //
    // await authService.sendPasswordResetCode(
    //   email: email,
    // );
    // ----------------------------------------------------------

    await Future.delayed(const Duration(milliseconds: 900));

    if (!mounted) return;

    setState(() {
      isLoading = false;
    });

    debugPrint("Password reset requested for: $email");

    showMessage("Reset code sent to your email.", isSuccess: true);

    // ----------------------------------------------------------
    // NEXT STEP
    //
    // Later you can navigate here:
    //
    // Navigator.push(
    //   context,
    //   MaterialPageRoute(
    //     builder: (context) =>
    //         VerifyResetCodePage(email: email),
    //   ),
    // );
    // ----------------------------------------------------------
  }

  // ------------------------------------------------------------
  // EMAIL VALIDATION
  // ------------------------------------------------------------

  String? validateEmail(String? value) {
    final input = value?.trim() ?? "";

    if (input.isEmpty) {
      return "Please enter your email address";
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
  // MESSAGE
  // ------------------------------------------------------------

  void showMessage(String message, {bool isSuccess = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Row(
            children: [
              Icon(
                isSuccess
                    ? Icons.check_circle_outline_rounded
                    : Icons.info_outline_rounded,
                color: Colors.white,
                size: 21,
              ),
              const SizedBox(width: 10),
              Expanded(child: Text(message)),
            ],
          ),
          behavior: SnackBarBehavior.floating,
          backgroundColor: isSuccess
              ? ForgotPasswordColors.success
              : ForgotPasswordColors.textDark,
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
      backgroundColor: ForgotPasswordColors.background,

      appBar: AppBar(
        backgroundColor: ForgotPasswordColors.background,

        elevation: 0,

        scrolledUnderElevation: 0,

        centerTitle: true,

        leading: IconButton(
          tooltip: "Back",

          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: ForgotPasswordColors.textDark,
            size: 20,
          ),

          onPressed: isLoading
              ? null
              : () {
                  Navigator.pop(context);
                },
        ),

        title: const Text(
          "Forgot Password",
          style: ForgotPasswordStyle.appBarTitle,
        ),
      ),

      body: SafeArea(
        top: false,

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

                padding: EdgeInsets.fromLTRB(
                  horizontalPadding,
                  20,
                  horizontalPadding,
                  24,
                ),

                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: constraints.maxHeight - 44,
                  ),

                  child: Center(
                    child: Container(
                      width: double.infinity,

                      constraints: const BoxConstraints(maxWidth: 430),

                      padding: EdgeInsets.symmetric(
                        horizontal: screenSize.width < 360 ? 18 : 24,
                        vertical: 30,
                      ),

                      decoration: ForgotPasswordStyle.card,

                      child: AutofillGroup(
                        child: Form(
                          key: _formKey,

                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,

                            children: [
                              // --------------------------------
                              // ICON
                              // --------------------------------

                              Center(
                                child: Container(
                                  height: 68,
                                  width: 68,

                                  decoration: ForgotPasswordStyle.iconContainer,

                                  child: const Icon(
                                    Icons.lock_reset_rounded,
                                    color: ForgotPasswordColors.primary,
                                    size: 34,
                                  ),
                                ),
                              ),

                              const SizedBox(height: 26),

                              // --------------------------------
                              // TITLE
                              // --------------------------------
                              const Center(
                                child: Text(
                                  "Reset your password",
                                  textAlign: TextAlign.center,
                                  style: ForgotPasswordStyle.title,
                                ),
                              ),

                              const SizedBox(height: 9),

                              const Center(
                                child: Text(
                                  "Enter the email address connected to your BiliHub account. We'll send you a reset code.",
                                  textAlign: TextAlign.center,
                                  style: ForgotPasswordStyle.subtitle,
                                ),
                              ),

                              const SizedBox(height: 30),

                              // --------------------------------
                              // EMAIL LABEL
                              // --------------------------------
                              const Text(
                                "Email Address",
                                style: ForgotPasswordStyle.sectionTitle,
                              ),

                              const SizedBox(height: 8),

                              // --------------------------------
                              // EMAIL FIELD
                              // --------------------------------
                              TextFormField(
                                controller: emailController,

                                focusNode: emailFocusNode,

                                enabled: !isLoading,

                                keyboardType: TextInputType.emailAddress,

                                textInputAction: TextInputAction.done,

                                autofillHints: const [AutofillHints.email],

                                autocorrect: false,

                                style: ForgotPasswordStyle.inputText,

                                validator: validateEmail,

                                onFieldSubmitted: (_) {
                                  sendResetCode();
                                },

                                decoration: ForgotPasswordStyle.inputDecoration(
                                  hint: "Enter your email address",
                                  icon: Icons.mail_outline_rounded,
                                ),
                              ),

                              const SizedBox(height: 24),

                              // --------------------------------
                              // SEND BUTTON
                              // --------------------------------
                              SizedBox(
                                width: double.infinity,
                                height: 52,

                                child: ElevatedButton(
                                  onPressed: isLoading ? null : sendResetCode,

                                  style: ForgotPasswordStyle.primaryButton,

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
                                            "Send Reset Code",
                                            key: ValueKey("send-code"),
                                            style:
                                                ForgotPasswordStyle.buttonText,
                                          ),
                                  ),
                                ),
                              ),

                              const SizedBox(height: 22),

                              // --------------------------------
                              // BACK TO LOGIN
                              // --------------------------------
                              Center(
                                child: TextButton(
                                  onPressed: isLoading
                                      ? null
                                      : () {
                                          Navigator.pop(context);
                                        },

                                  style: ForgotPasswordStyle.textButton,

                                  child: const Text(
                                    "Back to Login",
                                    style: ForgotPasswordStyle.loginLinkText,
                                  ),
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
    emailFocusNode.dispose();

    super.dispose();
  }
}
