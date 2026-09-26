import 'package:flutter/material.dart';

class LoginColors {
  // ------------------------------------------------------------
  // BRAND COLORS
  // ------------------------------------------------------------

  static const Color primary = Color(0xFF6C4CF1);
  static const Color primaryDark = Color(0xFF5438D6);
  static const Color primaryLight = Color(0xFFEEE9FF);

  static const Color accent = Color(0xFF8B74F5);

  // ------------------------------------------------------------
  // BACKGROUND COLORS
  // ------------------------------------------------------------

  static const Color background = Color(0xFFF8F7FC);
  static const Color white = Colors.white;

  static const Color lightPurple = Color(0xFFF1EEFF);

  // ------------------------------------------------------------
  // TEXT COLORS
  // ------------------------------------------------------------

  static const Color textDark = Color(0xFF1D2340);
  static const Color textGrey = Color(0xFF74798B);
  static const Color textLight = Color(0xFFA0A4B3);

  // ------------------------------------------------------------
  // BORDER COLORS
  // ------------------------------------------------------------

  static const Color border = Color(0xFFE7E4F2);
  static const Color error = Color(0xFFE5484D);

  // ------------------------------------------------------------
  // INPUT
  // ------------------------------------------------------------

  static const Color inputBackground = Color(0xFFFBFAFF);
}

class LoginStyle {
  // ------------------------------------------------------------
  // LOGO
  // ------------------------------------------------------------

  static const TextStyle logo = TextStyle(
    fontSize: 30,
    fontWeight: FontWeight.w800,
    color: LoginColors.primary,
    letterSpacing: 0.3,
  );

  // ------------------------------------------------------------
  // TITLES
  // ------------------------------------------------------------

  static const TextStyle title = TextStyle(
    fontSize: 27,
    fontWeight: FontWeight.w800,
    color: LoginColors.textDark,
    height: 1.2,
  );

  static const TextStyle subtitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w400,
    color: LoginColors.textGrey,
    height: 1.5,
  );

  static const TextStyle sectionTitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w600,
    color: LoginColors.textDark,
  );

  // ------------------------------------------------------------
  // SMALL TEXT
  // ------------------------------------------------------------

  static const TextStyle rememberText = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w500,
    color: LoginColors.textGrey,
  );

  static const TextStyle forgotPasswordText = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w600,
    color: LoginColors.primary,
  );

  static const TextStyle footerText = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w400,
    color: LoginColors.textGrey,
  );

  static const TextStyle dividerText = TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w600,
    color: LoginColors.textGrey,
  );

  // ------------------------------------------------------------
  // BUTTON TEXT
  // ------------------------------------------------------------

  static const TextStyle buttonText = TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w700,
    color: Colors.white,
  );

  static const TextStyle registerText = TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w700,
    color: LoginColors.primary,
  );

  // ------------------------------------------------------------
  // INPUT TEXT
  // ------------------------------------------------------------

  static const TextStyle inputText = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w500,
    color: LoginColors.textDark,
  );

  static const TextStyle hintText = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w400,
    color: LoginColors.textLight,
  );

  static const TextStyle errorText = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w500,
    color: LoginColors.error,
  );

  // ------------------------------------------------------------
  // INPUT DECORATION
  // ------------------------------------------------------------

  static InputDecoration inputDecoration({
    required String hint,
    required IconData icon,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: hintText,

      prefixIcon: Icon(icon, color: LoginColors.primary, size: 21),

      filled: true,
      fillColor: LoginColors.inputBackground,

      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 17),

      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: LoginColors.border),
      ),

      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: LoginColors.border, width: 1.1),
      ),

      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: LoginColors.primary, width: 1.7),
      ),

      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: LoginColors.error, width: 1.2),
      ),

      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: LoginColors.error, width: 1.6),
      ),

      errorStyle: errorText,
    );
  }

  // ------------------------------------------------------------
  // CARD
  // ------------------------------------------------------------

  static BoxDecoration card = BoxDecoration(
    color: LoginColors.white,
    borderRadius: BorderRadius.circular(24),
    border: Border.all(color: LoginColors.border.withValues(alpha: 0.75)),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.045),
        blurRadius: 24,
        offset: const Offset(0, 10),
      ),
    ],
  );

  // ------------------------------------------------------------
  // PRIMARY BUTTON
  // ------------------------------------------------------------

  static ButtonStyle primaryButton = ElevatedButton.styleFrom(
    backgroundColor: LoginColors.primary,
    foregroundColor: Colors.white,

    disabledBackgroundColor: LoginColors.primary.withValues(alpha: 0.45),

    disabledForegroundColor: Colors.white.withValues(alpha: 0.75),

    elevation: 0,

    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),

    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  // ------------------------------------------------------------
  // REGISTER BUTTON
  // ------------------------------------------------------------

  static ButtonStyle secondaryButton = OutlinedButton.styleFrom(
    foregroundColor: LoginColors.primary,

    backgroundColor: LoginColors.white,

    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),

    side: const BorderSide(color: LoginColors.primary, width: 1.3),

    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  // ------------------------------------------------------------
  // LOGO CONTAINER
  // ------------------------------------------------------------

  static BoxDecoration logoContainer = BoxDecoration(
    color: LoginColors.lightPurple,
    borderRadius: BorderRadius.circular(18),
  );
}
