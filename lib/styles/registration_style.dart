import 'package:flutter/material.dart';

class RegistrationColors {
  // ------------------------------------------------------------
  // BRAND COLORS
  // ------------------------------------------------------------

  static const Color primary = Color(0xFF6C4CF1);
  static const Color primaryDark = Color(0xFF5438D6);
  static const Color primaryLight = Color(0xFFEEE9FF);

  static const Color accent = Color(0xFF8B74F5);

  // ------------------------------------------------------------
  // BACKGROUND
  // ------------------------------------------------------------

  static const Color background = Color(0xFFF8F7FC);
  static const Color white = Colors.white;
  static const Color lightPurple = Color(0xFFF1EEFF);
  static const Color inputBackground = Color(0xFFFBFAFF);

  // ------------------------------------------------------------
  // TEXT
  // ------------------------------------------------------------

  static const Color textDark = Color(0xFF1D2340);
  static const Color textGrey = Color(0xFF74798B);
  static const Color textLight = Color(0xFFA0A4B3);

  // ------------------------------------------------------------
  // BORDER / STATES
  // ------------------------------------------------------------

  static const Color border = Color(0xFFE7E4F2);
  static const Color error = Color(0xFFE5484D);
  static const Color success = Color(0xFF2E9B62);
}

class RegistrationStyle {
  // ------------------------------------------------------------
  // LOGO
  // ------------------------------------------------------------

  static const TextStyle logo = TextStyle(
    fontSize: 30,
    fontWeight: FontWeight.w800,
    color: RegistrationColors.primary,
    letterSpacing: 0.3,
  );

  static BoxDecoration logoContainer = BoxDecoration(
    color: RegistrationColors.lightPurple,
    borderRadius: BorderRadius.circular(18),
  );

  // ------------------------------------------------------------
  // TITLES
  // ------------------------------------------------------------

  static const TextStyle appBarTitle = TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w700,
    color: RegistrationColors.textDark,
  );

  static const TextStyle title = TextStyle(
    fontSize: 27,
    fontWeight: FontWeight.w800,
    color: RegistrationColors.textDark,
    height: 1.2,
  );

  static const TextStyle subtitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w400,
    color: RegistrationColors.textGrey,
    height: 1.5,
  );

  static const TextStyle sectionTitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w600,
    color: RegistrationColors.textDark,
  );

  // ------------------------------------------------------------
  // INPUT TEXT
  // ------------------------------------------------------------

  static const TextStyle inputText = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w500,
    color: RegistrationColors.textDark,
  );

  static const TextStyle hintText = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w400,
    color: RegistrationColors.textLight,
  );

  static const TextStyle errorText = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w500,
    color: RegistrationColors.error,
  );

  // ------------------------------------------------------------
  // SMALL TEXT
  // ------------------------------------------------------------

  static const TextStyle termsText = TextStyle(
    fontSize: 12,
    height: 1.45,
    fontWeight: FontWeight.w400,
    color: RegistrationColors.textGrey,
  );

  static const TextStyle termsLinkText = TextStyle(
    fontSize: 12,
    height: 1.45,
    fontWeight: FontWeight.w600,
    color: RegistrationColors.primary,
  );

  static const TextStyle loginLinkText = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w600,
    color: RegistrationColors.primary,
  );

  // ------------------------------------------------------------
  // BUTTON TEXT
  // ------------------------------------------------------------

  static const TextStyle buttonText = TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w700,
    color: Colors.white,
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

      prefixIcon: Icon(icon, color: RegistrationColors.primary, size: 21),

      filled: true,

      fillColor: RegistrationColors.inputBackground,

      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 17),

      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: RegistrationColors.border),
      ),

      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(
          color: RegistrationColors.border,
          width: 1.1,
        ),
      ),

      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(
          color: RegistrationColors.primary,
          width: 1.7,
        ),
      ),

      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(
          color: RegistrationColors.error,
          width: 1.2,
        ),
      ),

      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(
          color: RegistrationColors.error,
          width: 1.6,
        ),
      ),

      disabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(
          color: RegistrationColors.border.withValues(alpha: 0.7),
        ),
      ),

      errorStyle: errorText,
    );
  }

  // ------------------------------------------------------------
  // CARD
  // ------------------------------------------------------------

  static BoxDecoration card = BoxDecoration(
    color: RegistrationColors.white,

    borderRadius: BorderRadius.circular(24),

    border: Border.all(
      color: RegistrationColors.border.withValues(alpha: 0.75),
    ),

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
    backgroundColor: RegistrationColors.primary,

    foregroundColor: Colors.white,

    disabledBackgroundColor: RegistrationColors.primary.withValues(alpha: 0.45),

    disabledForegroundColor: Colors.white.withValues(alpha: 0.75),

    elevation: 0,

    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),

    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  // ------------------------------------------------------------
  // TEXT BUTTON
  // ------------------------------------------------------------

  static ButtonStyle textButton = TextButton.styleFrom(
    foregroundColor: RegistrationColors.primary,

    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),

    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
  );
}
