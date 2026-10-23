import 'dart:async';
import 'dart:math';

import 'package:flutter/material.dart';

import '../../styles/login_style.dart';

class ForgotPasswordPage extends StatefulWidget {
  const ForgotPasswordPage({super.key});

  @override
  State<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<ForgotPasswordPage> {
  // ------------------------------------------------------------
  // CONTROLLERS
  // ------------------------------------------------------------

  final TextEditingController emailController = TextEditingController();

  final TextEditingController codeController = TextEditingController();

  final TextEditingController newPasswordController = TextEditingController();

  final TextEditingController confirmPasswordController =
      TextEditingController();

  // ------------------------------------------------------------
  // STATE
  // ------------------------------------------------------------

  bool isSendingCode = false;
  bool isVerifyingCode = false;
  bool isResettingPassword = false;

  bool hideNewPassword = true;
  bool hideConfirmPassword = true;

  String generatedCode = "";

  Timer? resendTimer;
  int resendSeconds = 0;

  // ------------------------------------------------------------
  // SEND VERIFICATION CODE
  // ------------------------------------------------------------

  Future<void> sendCode() async {
    FocusScope.of(context).unfocus();

    final email = emailController.text.trim();

    if (email.isEmpty) {
      showMessage("Please enter your email address.");
      return;
    }

    if (!_isValidEmail(email)) {
      showMessage("Please enter a valid email address.");
      return;
    }

    if (isSendingCode) return;

    setState(() {
      isSendingCode = true;
    });

    // ----------------------------------------------------------
    // TEMPORARY DEMO VERIFICATION CODE
    //
    // This will be replaced with your Laravel API later.
    // The backend will generate the code and send it through email.
    // ----------------------------------------------------------

    final random = Random();

    generatedCode = (100000 + random.nextInt(900000)).toString();

    // Temporary delay to simulate sending email.
    await Future.delayed(const Duration(seconds: 1));

    if (!mounted) return;

    setState(() {
      isSendingCode = false;
    });

    // Start 30-second resend countdown.
    startResendTimer();

    // Show verification popup.
    showVerificationDialog();
  }

  // ------------------------------------------------------------
  // VERIFICATION CODE POPUP
  // ------------------------------------------------------------

  void showVerificationDialog() {
    codeController.clear();

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return Dialog(
              backgroundColor: LoginColors.white,
              insetPadding: const EdgeInsets.symmetric(horizontal: 22),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(24),
              ),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(22, 24, 22, 22),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // ------------------------------------------------
                    // ICON
                    // ------------------------------------------------

                    Container(
                      height: 64,
                      width: 64,
                      decoration: BoxDecoration(
                        color: LoginColors.lightPurple,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Icon(
                        Icons.mark_email_read_outlined,
                        color: LoginColors.primary,
                        size: 31,
                      ),
                    ),

                    const SizedBox(height: 17),

                    // ------------------------------------------------
                    // TITLE
                    // ------------------------------------------------
                    const Text(
                      "Verify Your Email",
                      style: LoginStyle.title,
                      textAlign: TextAlign.center,
                    ),

                    const SizedBox(height: 7),

                    // ------------------------------------------------
                    // DESCRIPTION
                    // ------------------------------------------------
                    Text(
                      "We've sent a verification code to\n"
                      "${maskEmail(emailController.text.trim())}",
                      textAlign: TextAlign.center,
                      style: LoginStyle.subtitle,
                    ),

                    const SizedBox(height: 22),

                    // ------------------------------------------------
                    // CODE INPUT
                    // ------------------------------------------------
                    TextField(
                      controller: codeController,
                      keyboardType: TextInputType.number,
                      textAlign: TextAlign.center,
                      maxLength: 6,
                      autofocus: true,
                      style: const TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 7,
                        color: LoginColors.textDark,
                      ),
                      decoration: InputDecoration(
                        hintText: "000000",
                        counterText: "",
                        hintStyle: TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 7,
                          color: LoginColors.textGrey.withValues(alpha: 0.45),
                        ),
                        filled: true,
                        fillColor: LoginColors.background,
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 16,
                        ),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: const BorderSide(
                            color: LoginColors.border,
                          ),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: const BorderSide(
                            color: LoginColors.border,
                          ),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: const BorderSide(
                            color: LoginColors.primary,
                            width: 1.8,
                          ),
                        ),
                      ),
                      onChanged: (_) {
                        setDialogState(() {});
                      },
                    ),

                    const SizedBox(height: 18),

                    // ------------------------------------------------
                    // VERIFY BUTTON
                    // ------------------------------------------------
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton(
                        onPressed: isVerifyingCode
                            ? null
                            : () {
                                verifyCode(dialogContext, setDialogState);
                              },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: LoginColors.primary,
                          disabledBackgroundColor: LoginColors.primary
                              .withValues(alpha: 0.55),
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                        ),
                        child: isVerifyingCode
                            ? const SizedBox(
                                height: 22,
                                width: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.5,
                                  color: Colors.white,
                                ),
                              )
                            : const Text(
                                "Verify Code",
                                style: LoginStyle.buttonText,
                              ),
                      ),
                    ),

                    const SizedBox(height: 12),

                    // ------------------------------------------------
                    // RESEND CODE
                    // ------------------------------------------------
                    TextButton(
                      onPressed: resendSeconds > 0
                          ? null
                          : () async {
                              Navigator.pop(dialogContext);

                              await sendCode();
                            },
                      child: Text(
                        resendSeconds > 0
                            ? "Resend code in ${resendSeconds}s"
                            : "Resend Code",
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: resendSeconds > 0
                              ? LoginColors.textGrey
                              : LoginColors.primary,
                        ),
                      ),
                    ),

                    const SizedBox(height: 2),

                    // ------------------------------------------------
                    // CANCEL
                    // ------------------------------------------------
                    TextButton(
                      onPressed: () {
                        Navigator.pop(dialogContext);
                      },
                      child: const Text(
                        "Cancel",
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: LoginColors.textGrey,
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
  }

  // ------------------------------------------------------------
  // VERIFY CODE
  // ------------------------------------------------------------

  Future<void> verifyCode(
    BuildContext dialogContext,
    StateSetter setDialogState,
  ) async {
    final code = codeController.text.trim();

    if (code.isEmpty) {
      showDialogMessage(dialogContext, "Please enter the verification code.");
      return;
    }

    if (code.length != 6) {
      showDialogMessage(
        dialogContext,
        "Please enter the complete 6-digit code.",
      );
      return;
    }

    setDialogState(() {
      isVerifyingCode = true;
    });

    // Temporary delay.
    await Future.delayed(const Duration(milliseconds: 700));

    if (!mounted) return;

    // ----------------------------------------------------------
    // CHECK CODE
    // ----------------------------------------------------------

    if (code != generatedCode) {
      setDialogState(() {
        isVerifyingCode = false;
      });

      showDialogMessage(dialogContext, "Incorrect verification code.");

      return;
    }

    setDialogState(() {
      isVerifyingCode = false;
    });

    // Close verification popup.
    Navigator.pop(dialogContext);

    // Open reset password popup.
    showResetPasswordDialog();
  }

  // ------------------------------------------------------------
  // RESET PASSWORD POPUP
  // ------------------------------------------------------------

  void showResetPasswordDialog() {
    newPasswordController.clear();
    confirmPasswordController.clear();

    hideNewPassword = true;
    hideConfirmPassword = true;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return Dialog(
              backgroundColor: LoginColors.white,
              insetPadding: const EdgeInsets.symmetric(horizontal: 22),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(24),
              ),
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(22),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // ------------------------------------------------
                    // ICON
                    // ------------------------------------------------

                    Center(
                      child: Container(
                        height: 64,
                        width: 64,
                        decoration: BoxDecoration(
                          color: LoginColors.lightPurple,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Icon(
                          Icons.lock_reset_outlined,
                          color: LoginColors.primary,
                          size: 32,
                        ),
                      ),
                    ),

                    const SizedBox(height: 17),

                    // ------------------------------------------------
                    // TITLE
                    // ------------------------------------------------
                    const Center(
                      child: Text(
                        "Create New Password",
                        style: LoginStyle.title,
                        textAlign: TextAlign.center,
                      ),
                    ),

                    const SizedBox(height: 7),

                    const Center(
                      child: Text(
                        "Create a new password for your account.",
                        style: LoginStyle.subtitle,
                        textAlign: TextAlign.center,
                      ),
                    ),

                    const SizedBox(height: 22),

                    // ------------------------------------------------
                    // NEW PASSWORD
                    // ------------------------------------------------
                    const Text("New Password", style: LoginStyle.sectionTitle),

                    const SizedBox(height: 8),

                    TextField(
                      controller: newPasswordController,
                      obscureText: hideNewPassword,
                      textInputAction: TextInputAction.next,
                      decoration:
                          LoginStyle.inputDecoration(
                            hint: "Enter your new password",
                            icon: Icons.lock_outline,
                          ).copyWith(
                            suffixIcon: IconButton(
                              onPressed: () {
                                setDialogState(() {
                                  hideNewPassword = !hideNewPassword;
                                });
                              },
                              icon: Icon(
                                hideNewPassword
                                    ? Icons.visibility_off_outlined
                                    : Icons.visibility_outlined,
                                color: LoginColors.textGrey,
                              ),
                            ),
                          ),
                    ),

                    const SizedBox(height: 16),

                    // ------------------------------------------------
                    // CONFIRM PASSWORD
                    // ------------------------------------------------
                    const Text(
                      "Confirm Password",
                      style: LoginStyle.sectionTitle,
                    ),

                    const SizedBox(height: 8),

                    TextField(
                      controller: confirmPasswordController,
                      obscureText: hideConfirmPassword,
                      textInputAction: TextInputAction.done,
                      decoration:
                          LoginStyle.inputDecoration(
                            hint: "Confirm your new password",
                            icon: Icons.lock_outline,
                          ).copyWith(
                            suffixIcon: IconButton(
                              onPressed: () {
                                setDialogState(() {
                                  hideConfirmPassword = !hideConfirmPassword;
                                });
                              },
                              icon: Icon(
                                hideConfirmPassword
                                    ? Icons.visibility_off_outlined
                                    : Icons.visibility_outlined,
                                color: LoginColors.textGrey,
                              ),
                            ),
                          ),
                    ),

                    const SizedBox(height: 22),

                    // ------------------------------------------------
                    // RESET BUTTON
                    // ------------------------------------------------
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton(
                        onPressed: isResettingPassword
                            ? null
                            : () {
                                resetPassword(dialogContext, setDialogState);
                              },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: LoginColors.primary,
                          disabledBackgroundColor: LoginColors.primary
                              .withValues(alpha: 0.55),
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                        ),
                        child: isResettingPassword
                            ? const SizedBox(
                                height: 22,
                                width: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.5,
                                  color: Colors.white,
                                ),
                              )
                            : const Text(
                                "Reset Password",
                                style: LoginStyle.buttonText,
                              ),
                      ),
                    ),

                    const SizedBox(height: 10),

                    // ------------------------------------------------
                    // CANCEL
                    // ------------------------------------------------
                    SizedBox(
                      width: double.infinity,
                      child: TextButton(
                        onPressed: () {
                          Navigator.pop(dialogContext);
                        },
                        child: const Text(
                          "Cancel",
                          style: TextStyle(
                            color: LoginColors.textGrey,
                            fontWeight: FontWeight.w600,
                          ),
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
  }

  // ------------------------------------------------------------
  // RESET PASSWORD
  // ------------------------------------------------------------

  Future<void> resetPassword(
    BuildContext dialogContext,
    StateSetter setDialogState,
  ) async {
    final password = newPasswordController.text;

    final confirmPassword = confirmPasswordController.text;

    // ----------------------------------------------------------
    // VALIDATION
    // ----------------------------------------------------------

    if (password.isEmpty || confirmPassword.isEmpty) {
      showDialogMessage(dialogContext, "Please complete both password fields.");
      return;
    }

    if (password.length < 6) {
      showDialogMessage(
        dialogContext,
        "Password must contain at least 6 characters.",
      );
      return;
    }

    if (password != confirmPassword) {
      showDialogMessage(dialogContext, "Passwords do not match.");
      return;
    }

    setDialogState(() {
      isResettingPassword = true;
    });

    // Temporary delay.
    await Future.delayed(const Duration(seconds: 1));

    if (!mounted) return;

    setDialogState(() {
      isResettingPassword = false;
    });

    // Close reset password popup.
    Navigator.pop(dialogContext);

    // Show success popup.
    showSuccessDialog();
  }

  // ------------------------------------------------------------
  // RESEND TIMER
  // ------------------------------------------------------------

  void startResendTimer() {
    resendTimer?.cancel();

    setState(() {
      resendSeconds = 30;
    });

    resendTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }

      if (resendSeconds <= 1) {
        timer.cancel();

        setState(() {
          resendSeconds = 0;
        });

        return;
      }

      setState(() {
        resendSeconds--;
      });
    });
  }

  // ------------------------------------------------------------
  // EMAIL VALIDATION
  // ------------------------------------------------------------

  bool _isValidEmail(String email) {
    final regex = RegExp(r'^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$');

    return regex.hasMatch(email);
  }

  // ------------------------------------------------------------
  // MASK EMAIL
  // ------------------------------------------------------------

  String maskEmail(String email) {
    final parts = email.split("@");

    if (parts.length != 2) {
      return email;
    }

    final username = parts[0];
    final domain = parts[1];

    if (username.length <= 2) {
      return "***@$domain";
    }

    return "${username.substring(0, 2)}***@$domain";
  }

  // ------------------------------------------------------------
  // DIALOG MESSAGE
  // ------------------------------------------------------------

  void showDialogMessage(BuildContext context, String message) {
    showDialog(
      context: context,
      builder: (messageContext) {
        return AlertDialog(
          backgroundColor: LoginColors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          title: const Text(
            "Notice",
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: LoginColors.textDark,
            ),
          ),
          content: Text(message, style: LoginStyle.subtitle),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(messageContext);
              },
              child: const Text(
                "OK",
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  color: LoginColors.primary,
                ),
              ),
            ),
          ],
        );
      },
    );
  }

  // ------------------------------------------------------------
  // SUCCESS DIALOG
  // ------------------------------------------------------------

  void showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (successContext) {
        return AlertDialog(
          backgroundColor: LoginColors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(22),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // --------------------------------------------------
              // SUCCESS ICON
              // --------------------------------------------------

              Container(
                height: 64,
                width: 64,
                decoration: BoxDecoration(
                  color: const Color(0xFFE8F7EE),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: const Icon(
                  Icons.check_circle_outline,
                  color: Color(0xFF22A06B),
                  size: 34,
                ),
              ),

              const SizedBox(height: 18),

              // --------------------------------------------------
              // TITLE
              // --------------------------------------------------
              const Text(
                "Password Reset Successful",
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 19,
                  fontWeight: FontWeight.w800,
                  color: LoginColors.textDark,
                ),
              ),

              const SizedBox(height: 8),

              // --------------------------------------------------
              // DESCRIPTION
              // --------------------------------------------------
              const Text(
                "Your password has been updated successfully. "
                "You can now login with your new password.",
                textAlign: TextAlign.center,
                style: LoginStyle.subtitle,
              ),

              const SizedBox(height: 20),

              // --------------------------------------------------
              // BACK TO LOGIN
              // --------------------------------------------------
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton(
                  onPressed: () {
                    // Close success dialog.
                    Navigator.pop(successContext);

                    // Close Forgot Password page.
                    Navigator.pop(context);
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: LoginColors.primary,
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: const Text(
                    "Back to Login",
                    style: LoginStyle.buttonText,
                  ),
                ),
              ),
            ],
          ),
        );
      },
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
  // DISPOSE
  // ------------------------------------------------------------

  @override
  void dispose() {
    resendTimer?.cancel();

    emailController.dispose();
    codeController.dispose();
    newPasswordController.dispose();
    confirmPasswordController.dispose();

    super.dispose();
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
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // ------------------------------------------------
                          // BACK BUTTON
                          // ------------------------------------------------

                          IconButton(
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(
                              minWidth: 42,
                              minHeight: 42,
                            ),
                            onPressed: () {
                              Navigator.pop(context);
                            },
                            icon: const Icon(
                              Icons.arrow_back_ios_new_rounded,
                              color: LoginColors.textDark,
                              size: 20,
                            ),
                          ),

                          const SizedBox(height: 12),

                          // ------------------------------------------------
                          // ICON
                          // ------------------------------------------------
                          Center(
                            child: Container(
                              height: 70,
                              width: 70,
                              decoration: BoxDecoration(
                                color: LoginColors.lightPurple,
                                borderRadius: BorderRadius.circular(22),
                              ),
                              child: const Icon(
                                Icons.lock_reset_outlined,
                                color: LoginColors.primary,
                                size: 37,
                              ),
                            ),
                          ),

                          const SizedBox(height: 22),

                          // ------------------------------------------------
                          // TITLE
                          // ------------------------------------------------
                          const Center(
                            child: Text(
                              "Forgot Password?",
                              style: LoginStyle.title,
                              textAlign: TextAlign.center,
                            ),
                          ),

                          const SizedBox(height: 8),

                          // ------------------------------------------------
                          // SUBTITLE
                          // ------------------------------------------------
                          const Center(
                            child: Text(
                              "Enter your email address and we'll "
                              "send you a verification code to reset "
                              "your password.",
                              style: LoginStyle.subtitle,
                              textAlign: TextAlign.center,
                            ),
                          ),

                          const SizedBox(height: 28),

                          // ------------------------------------------------
                          // EMAIL LABEL
                          // ------------------------------------------------
                          const Text(
                            "Email Address",
                            style: LoginStyle.sectionTitle,
                          ),

                          const SizedBox(height: 8),

                          // ------------------------------------------------
                          // EMAIL FIELD
                          // ------------------------------------------------
                          TextField(
                            controller: emailController,
                            enabled: !isSendingCode,
                            keyboardType: TextInputType.emailAddress,
                            textInputAction: TextInputAction.done,
                            autocorrect: false,
                            onSubmitted: (_) {
                              if (!isSendingCode) {
                                sendCode();
                              }
                            },
                            decoration: LoginStyle.inputDecoration(
                              hint: "Enter your email address",
                              icon: Icons.mail_outline_rounded,
                            ),
                          ),

                          const SizedBox(height: 22),

                          // ------------------------------------------------
                          // SEND CODE BUTTON
                          // ------------------------------------------------
                          SizedBox(
                            width: double.infinity,
                            height: 52,
                            child: ElevatedButton(
                              onPressed: isSendingCode ? null : sendCode,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: LoginColors.primary,
                                disabledBackgroundColor: LoginColors.primary
                                    .withValues(alpha: 0.55),
                                elevation: 0,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(14),
                                ),
                              ),
                              child: isSendingCode
                                  ? const SizedBox(
                                      height: 22,
                                      width: 22,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2.5,
                                        color: Colors.white,
                                      ),
                                    )
                                  : const Text(
                                      "Send Verification Code",
                                      style: LoginStyle.buttonText,
                                    ),
                            ),
                          ),

                          const SizedBox(height: 18),

                          // ------------------------------------------------
                          // BACK TO LOGIN
                          // ------------------------------------------------
                          Center(
                            child: TextButton(
                              onPressed: isSendingCode
                                  ? null
                                  : () {
                                      Navigator.pop(context);
                                    },
                              child: const Text(
                                "Back to Login",
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                  color: LoginColors.primary,
                                ),
                              ),
                            ),
                          ),
                        ],
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
}
