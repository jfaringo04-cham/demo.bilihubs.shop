import 'package:flutter/material.dart';

class RegistrationColors {
  // ============================================================
  // MAIN COLORS
  // ============================================================

  static const Color primary = Color(0xFF5B3FE8);
  static const Color primaryDark = Color(0xFF4930C7);

  static const Color background = Color(0xFFF8F7FF);

  static const Color lightPurple = Color(0xFFF0EEFF);
  static const Color softPurple = Color(0xFFE8E4FF);

  static const Color white = Colors.white;

  // ============================================================
  // TEXT COLORS
  // ============================================================

  static const Color textDark = Color(0xFF13264F);
  static const Color textGrey = Color(0xFF68738A);
  static const Color textLight = Color(0xFF8A94A6);

  // ============================================================
  // BORDER / INPUT
  // ============================================================

  static const Color border = Color(0xFFE6E3F2);
  static const Color inputBackground = Color(0xFFF8F9FC);

  // ============================================================
  // STATUS COLORS
  // ============================================================

  static const Color success = Color(0xFF22A06B);
  static const Color warning = Color(0xFFF59E0B);
  static const Color error = Color(0xFFDC3545);
  static const Color info = Color(0xFF2864E8);
}

class RegistrationStyle {
  // ============================================================
  // LOGO TEXT
  //
  // Used by Buyer registration.
  // ============================================================

  static const TextStyle logo = TextStyle(
    fontSize: 28,
    height: 1.1,
    fontWeight: FontWeight.w900,
    color: RegistrationColors.primary,
    letterSpacing: -0.6,
  );

  // ============================================================
  // APP BAR
  // ============================================================

  static const TextStyle appBarTitle = TextStyle(
    fontSize: 17,
    fontWeight: FontWeight.w800,
    color: RegistrationColors.textDark,
  );

  // ============================================================
  // MAIN TITLE
  // ============================================================

  static const TextStyle title = TextStyle(
    fontSize: 24,
    height: 1.15,
    fontWeight: FontWeight.w800,
    color: RegistrationColors.textDark,
  );

  // ============================================================
  // SUBTITLE
  // ============================================================

  static const TextStyle subtitle = TextStyle(
    fontSize: 13,
    height: 1.45,
    fontWeight: FontWeight.w400,
    color: RegistrationColors.textGrey,
  );

  // ============================================================
  // SECTION / FIELD TITLE
  // ============================================================

  static const TextStyle sectionTitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    color: RegistrationColors.textDark,
  );

  // ============================================================
  // INPUT TEXT
  // ============================================================

  static const TextStyle inputText = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w500,
    color: RegistrationColors.textDark,
  );

  // ============================================================
  // TERMS / SMALL TEXT
  // ============================================================

  static const TextStyle termsText = TextStyle(
    fontSize: 12,
    height: 1.45,
    fontWeight: FontWeight.w400,
    color: RegistrationColors.textGrey,
  );

  // ============================================================
  // ERROR TEXT
  // ============================================================

  static const TextStyle errorText = TextStyle(
    fontSize: 12,
    height: 1.3,
    fontWeight: FontWeight.w500,
    color: RegistrationColors.error,
  );

  // ============================================================
  // LINK TEXT
  // ============================================================

  static const TextStyle linkText = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w700,
    color: RegistrationColors.primary,
  );

  // ============================================================
  // LOGIN LINK TEXT
  //
  // Used by Buyer registration:
  // "Already have an account? Login"
  // ============================================================

  static const TextStyle loginLinkText = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w700,
    color: RegistrationColors.primary,
  );

  // ============================================================
  // BUTTON TEXT
  // ============================================================

  static const TextStyle buttonText = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    color: RegistrationColors.white,
  );

  // ============================================================
  // OUTLINED BUTTON TEXT
  // ============================================================

  static const TextStyle outlinedButtonText = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    color: RegistrationColors.primary,
  );

  // ============================================================
  // REGISTRATION CARD
  //
  // Used by:
  // - Registration carousel
  // - Buyer registration
  // - Rider registration
  // - Logistics registration
  // ============================================================

  static final BoxDecoration card = BoxDecoration(
    color: RegistrationColors.white,
    borderRadius: BorderRadius.circular(20),
    border: Border.all(color: RegistrationColors.border, width: 1),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.045),
        blurRadius: 18,
        offset: const Offset(0, 7),
      ),
    ],
  );

  // ============================================================
  // LOGO / ROLE ICON CONTAINER
  // ============================================================

  static final BoxDecoration logoContainer = BoxDecoration(
    color: RegistrationColors.lightPurple,
    borderRadius: BorderRadius.circular(18),
    border: Border.all(color: RegistrationColors.softPurple),
  );

  // ============================================================
  // PRIMARY BUTTON
  // ============================================================

  static final ButtonStyle primaryButton = ElevatedButton.styleFrom(
    backgroundColor: RegistrationColors.primary,
    foregroundColor: RegistrationColors.white,

    disabledBackgroundColor: RegistrationColors.primary.withValues(alpha: 0.55),

    disabledForegroundColor: RegistrationColors.white.withValues(alpha: 0.85),

    elevation: 0,

    minimumSize: const Size(double.infinity, 52),

    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),

    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  // ============================================================
  // OUTLINED BUTTON
  // ============================================================

  static final ButtonStyle outlinedButton = OutlinedButton.styleFrom(
    foregroundColor: RegistrationColors.primary,
    backgroundColor: RegistrationColors.white,

    minimumSize: const Size(double.infinity, 52),

    elevation: 0,

    side: const BorderSide(color: RegistrationColors.primary, width: 1.3),

    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),

    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  // ============================================================
  // TEXT BUTTON
  // ============================================================

  static final ButtonStyle textButton = TextButton.styleFrom(
    foregroundColor: RegistrationColors.primary,

    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
  );

  // ============================================================
  // INPUT DECORATION
  // ============================================================

  static InputDecoration inputDecoration({
    required String hint,
    IconData? icon,
  }) {
    return InputDecoration(
      hintText: hint,

      hintStyle: const TextStyle(
        color: RegistrationColors.textGrey,
        fontSize: 13,
        fontWeight: FontWeight.w400,
      ),

      prefixIcon: icon == null
          ? null
          : Icon(icon, color: RegistrationColors.primary, size: 21),

      filled: true,

      fillColor: RegistrationColors.inputBackground,

      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),

      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),

        borderSide: const BorderSide(color: RegistrationColors.border),
      ),

      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),

        borderSide: const BorderSide(
          color: RegistrationColors.border,
          width: 1,
        ),
      ),

      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),

        borderSide: const BorderSide(
          color: RegistrationColors.primary,
          width: 1.5,
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
          width: 1.5,
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

  // ============================================================
  // SMALL INFORMATION BOX
  // ============================================================

  static BoxDecoration infoBox({Color? backgroundColor}) {
    return BoxDecoration(
      color: backgroundColor ?? RegistrationColors.lightPurple,

      borderRadius: BorderRadius.circular(14),

      border: Border.all(color: RegistrationColors.softPurple),
    );
  }

  // ============================================================
  // SUCCESS BOX
  // ============================================================

  static BoxDecoration successBox() {
    return BoxDecoration(
      color: RegistrationColors.success.withValues(alpha: 0.08),

      borderRadius: BorderRadius.circular(14),

      border: Border.all(
        color: RegistrationColors.success.withValues(alpha: 0.25),
      ),
    );
  }

  // ============================================================
  // ERROR BOX
  // ============================================================

  static BoxDecoration errorBox() {
    return BoxDecoration(
      color: RegistrationColors.error.withValues(alpha: 0.07),

      borderRadius: BorderRadius.circular(14),

      border: Border.all(
        color: RegistrationColors.error.withValues(alpha: 0.25),
      ),
    );
  }

  // ============================================================
  // ROLE ICON CONTAINER
  //
  // Useful for Buyer / Rider / Logistics carousel cards.
  // ============================================================

  static BoxDecoration roleIconContainer({bool selected = false}) {
    return BoxDecoration(
      color: selected
          ? RegistrationColors.primary
          : RegistrationColors.lightPurple,

      borderRadius: BorderRadius.circular(22),

      border: Border.all(
        color: selected
            ? RegistrationColors.primary
            : RegistrationColors.softPurple,
      ),
    );
  }

  // ============================================================
  // UPLOAD CARD
  // ============================================================

  static BoxDecoration uploadCard({
    bool hasFile = false,
    bool hasError = false,
  }) {
    Color borderColor = RegistrationColors.border;

    if (hasError) {
      borderColor = RegistrationColors.error;
    } else if (hasFile) {
      borderColor = RegistrationColors.success;
    }

    return BoxDecoration(
      color: RegistrationColors.inputBackground,

      borderRadius: BorderRadius.circular(14),

      border: Border.all(color: borderColor, width: 1.2),
    );
  }
}
