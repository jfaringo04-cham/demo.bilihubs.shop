import 'package:flutter/material.dart';

class ForgotPasswordColors {
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
  static const Color inputBackground = Color(0xFFFBFAFF);

  // ------------------------------------------------------------
  // TEXT COLORS
  // ------------------------------------------------------------

  static const Color textDark = Color(0xFF1D2340);
  static const Color textGrey = Color(0xFF74798B);
  static const Color textLight = Color(0xFFA0A4B3);

  // ------------------------------------------------------------
  // BORDER / STATE COLORS
  // ------------------------------------------------------------

  static const Color border = Color(0xFFE7E4F2);

  static const Color error = Color(0xFFE5484D);

  static const Color success = Color(0xFF2E9B62);
}

class ForgotPasswordStyle {
  // ------------------------------------------------------------
  // APP BAR
  // ------------------------------------------------------------

  static const TextStyle appBarTitle = TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w700,
    color: ForgotPasswordColors.textDark,
  );

  // ------------------------------------------------------------
  // TITLE
  // ------------------------------------------------------------

  static const TextStyle title = TextStyle(
    fontSize: 26,
    fontWeight: FontWeight.w800,
    color: ForgotPasswordColors.textDark,
    height: 1.2,
  );

  // ------------------------------------------------------------
  // SUBTITLE
  // ------------------------------------------------------------

  static const TextStyle subtitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w400,
    color: ForgotPasswordColors.textGrey,
    height: 1.5,
  );

  // ------------------------------------------------------------
  // SECTION TITLE
  // ------------------------------------------------------------

  static const TextStyle sectionTitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w600,
    color: ForgotPasswordColors.textDark,
  );

  // ------------------------------------------------------------
  // INPUT TEXT
  // ------------------------------------------------------------

  static const TextStyle inputText = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w500,
    color: ForgotPasswordColors.textDark,
  );

  // ------------------------------------------------------------
  // HINT TEXT
  // ------------------------------------------------------------

  static const TextStyle hintText = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w400,
    color: ForgotPasswordColors.textLight,
  );

  // ------------------------------------------------------------
  // ERROR TEXT
  // ------------------------------------------------------------

  static const TextStyle errorText = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w500,
    color: ForgotPasswordColors.error,
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
  // LOGIN LINK
  // ------------------------------------------------------------

  static const TextStyle loginLinkText = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w600,
    color: ForgotPasswordColors.primary,
  );

  // ------------------------------------------------------------
  // ICON CONTAINER
  // ------------------------------------------------------------

  static BoxDecoration iconContainer = BoxDecoration(
    color: ForgotPasswordColors.lightPurple,

    borderRadius: BorderRadius.circular(20),

    border: Border.all(
      color: ForgotPasswordColors.primary.withValues(alpha: 0.08),
    ),
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

      prefixIcon: Icon(icon, color: ForgotPasswordColors.primary, size: 21),

      filled: true,

      fillColor: ForgotPasswordColors.inputBackground,

      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 17),

      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),

        borderSide: const BorderSide(color: ForgotPasswordColors.border),
      ),

      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),

        borderSide: const BorderSide(
          color: ForgotPasswordColors.border,
          width: 1.1,
        ),
      ),

      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),

        borderSide: const BorderSide(
          color: ForgotPasswordColors.primary,
          width: 1.7,
        ),
      ),

      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),

        borderSide: const BorderSide(
          color: ForgotPasswordColors.error,
          width: 1.2,
        ),
      ),

      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),

        borderSide: const BorderSide(
          color: ForgotPasswordColors.error,
          width: 1.6,
        ),
      ),

      disabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),

        borderSide: BorderSide(
          color: ForgotPasswordColors.border.withValues(alpha: 0.7),
        ),
      ),

      errorStyle: errorText,
    );
  }

  // ------------------------------------------------------------
  // MAIN CARD
  // ------------------------------------------------------------

  static BoxDecoration card = BoxDecoration(
    color: ForgotPasswordColors.white,

    borderRadius: BorderRadius.circular(24),

    border: Border.all(
      color: ForgotPasswordColors.border.withValues(alpha: 0.75),
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
    backgroundColor: ForgotPasswordColors.primary,

    foregroundColor: Colors.white,

    disabledBackgroundColor: ForgotPasswordColors.primary.withValues(
      alpha: 0.45,
    ),

    disabledForegroundColor: Colors.white.withValues(alpha: 0.75),

    elevation: 0,

    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),

    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  // ------------------------------------------------------------
  // TEXT BUTTON
  // ------------------------------------------------------------

  static ButtonStyle textButton = TextButton.styleFrom(
    foregroundColor: ForgotPasswordColors.primary,

    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),

    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
  );
}
