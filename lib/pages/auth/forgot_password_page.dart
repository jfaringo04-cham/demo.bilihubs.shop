import 'package:flutter/material.dart';

import '../../sevices/api_services.dart';
import '../../styles/login_style.dart';

class ForgotPasswordPage extends StatefulWidget {
  const ForgotPasswordPage({super.key});

  @override
  State<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<ForgotPasswordPage> {
  final TextEditingController emailController = TextEditingController();
  bool isSending = false;
  bool linkSent = false;

  bool _isValidEmail(String value) {
    return RegExp(
      r'^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$',
    ).hasMatch(value);
  }

  Future<void> _sendResetLink() async {
    FocusScope.of(context).unfocus();

    final email = emailController.text.trim().toLowerCase();
    if (email.isEmpty) {
      _showMessage('Please enter your email address.');
      return;
    }
    if (!_isValidEmail(email)) {
      _showMessage('Please enter a valid email address.');
      return;
    }
    if (isSending) return;

    setState(() => isSending = true);

    final result = await ApiService.forgotPassword(email: email);

    if (!mounted) return;
    setState(() {
      isSending = false;
      linkSent = result['success'] == true;
    });

    _showMessage(
      result['message']?.toString() ??
          (linkSent
              ? 'Password reset link sent to your email.'
              : 'Unable to send password reset link.'),
      success: linkSent,
    );
  }

  void _showMessage(String message, {bool success = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: success ? Colors.green.shade700 : LoginColors.textDark,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          content: Text(message),
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: LoginColors.background,
      appBar: AppBar(
        backgroundColor: LoginColors.background,
        elevation: 0,
        iconTheme: const IconThemeData(color: LoginColors.textDark),
      ),
      body: SafeArea(
        top: false,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(22, 20, 22, 30),
          child: Center(
            child: Container(
              constraints: const BoxConstraints(maxWidth: 430),
              padding: const EdgeInsets.all(24),
              decoration: LoginStyle.card,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      height: 72,
                      width: 72,
                      decoration: BoxDecoration(
                        color: LoginColors.lightPurple,
                        borderRadius: BorderRadius.circular(22),
                      ),
                      child: const Icon(
                        Icons.lock_reset_rounded,
                        color: LoginColors.primary,
                        size: 36,
                      ),
                    ),
                  ),
                  const SizedBox(height: 22),
                  const Center(
                    child: Text(
                      'Forgot Password?',
                      style: LoginStyle.title,
                      textAlign: TextAlign.center,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Center(
                    child: Text(
                      'Enter the email connected to your BiliHub account. '
                      'We will send the official password reset link to your inbox.',
                      style: LoginStyle.subtitle,
                      textAlign: TextAlign.center,
                    ),
                  ),
                  const SizedBox(height: 26),
                  const Text('Email Address', style: LoginStyle.sectionTitle),
                  const SizedBox(height: 8),
                  TextField(
                    controller: emailController,
                    enabled: !isSending,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.done,
                    autofillHints: const [AutofillHints.email],
                    onSubmitted: (_) => _sendResetLink(),
                    decoration: LoginStyle.inputDecoration(
                      hint: 'Enter your email address',
                      icon: Icons.email_outlined,
                    ),
                  ),
                  if (linkSent) ...[
                    const SizedBox(height: 18),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: LoginColors.lightPurple,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            Icons.mark_email_read_outlined,
                            color: LoginColors.primary,
                          ),
                          SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              'Check your email and open the reset link. '
                              'The reset token is handled securely by Laravel.',
                              style: LoginStyle.subtitle,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 24),
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: ElevatedButton(
                      onPressed: isSending ? null : _sendResetLink,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: LoginColors.primary,
                        disabledBackgroundColor:
                            LoginColors.primary.withValues(alpha: 0.55),
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      child: isSending
                          ? const SizedBox(
                              height: 22,
                              width: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.4,
                                color: Colors.white,
                              ),
                            )
                          : Text(
                              linkSent ? 'Resend Reset Link' : 'Send Reset Link',
                              style: LoginStyle.buttonText,
                            ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: TextButton(
                      onPressed: isSending ? null : () => Navigator.pop(context),
                      child: const Text(
                        'Back to Login',
                        style: TextStyle(
                          color: LoginColors.primary,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  @override
  void dispose() {
    emailController.dispose();
    super.dispose();
  }
}
