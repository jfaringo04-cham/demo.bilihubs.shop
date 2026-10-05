import 'package:flutter/material.dart';

class LogisticsColors {
  // ============================================================
  // PRIMARY PURPLE THEME
  // ============================================================

  static const Color primary = Color(0xFF5B3FE8);
  static const Color primaryDark = Color(0xFF4930C7);

  // ============================================================
  // BACKGROUNDS
  // ============================================================

  static const Color background = Color(0xFFF8F7FF);
  static const Color lightPurple = Color(0xFFF0EEFF);
  static const Color softPurple = Color(0xFFE8E4FF);

  // Light green background for online/success status
  static const Color lightGreen = Color(0xFFE8F7EF);

  // ============================================================
  // TEXT
  // ============================================================

  static const Color textDark = Color(0xFF13264F);
  static const Color textGrey = Color(0xFF68738A);
  static const Color textLight = Color(0xFF8A94A6);

  // ============================================================
  // OTHER COLORS
  // ============================================================

  static const Color border = Color(0xFFE6E3F2);

  static const Color white = Colors.white;

  static const Color success = Color(0xFF22A06B);
  static const Color warning = Color(0xFFF59E0B);
  static const Color danger = Color(0xFFDC3545);

  static const Color inputBackground = Color(0xFFF8F9FC);
}

class LogisticsStyle {
  // ============================================================
  // TEXT STYLES
  // ============================================================

  static const TextStyle title = TextStyle(
    fontSize: 24,
    height: 1.15,
    fontWeight: FontWeight.w800,
    color: LogisticsColors.textDark,
  );

  static const TextStyle largeTitle = TextStyle(
    fontSize: 26,
    height: 1.15,
    fontWeight: FontWeight.w800,
    color: LogisticsColors.textDark,
  );

  static const TextStyle sectionTitle = TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w800,
    color: LogisticsColors.textDark,
  );

  static const TextStyle cardTitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    color: LogisticsColors.textDark,
  );

  static const TextStyle body = TextStyle(
    fontSize: 14,
    height: 1.45,
    color: LogisticsColors.textDark,
  );

  static const TextStyle subtitle = TextStyle(
    fontSize: 13,
    height: 1.4,
    color: LogisticsColors.textGrey,
  );

  static const TextStyle small = TextStyle(
    fontSize: 12,
    height: 1.35,
    color: LogisticsColors.textGrey,
  );

  static const TextStyle price = TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w800,
    color: LogisticsColors.primary,
  );

  static const TextStyle largePrice = TextStyle(
    fontSize: 20,
    fontWeight: FontWeight.w800,
    color: LogisticsColors.primary,
  );

  static const TextStyle buttonText = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    color: LogisticsColors.white,
  );

  static const TextStyle label = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w600,
    color: LogisticsColors.textDark,
  );

  static const TextStyle link = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w700,
    color: LogisticsColors.primary,
  );

  // ============================================================
  // CARD
  // ============================================================

  static final BoxDecoration card = BoxDecoration(
    color: LogisticsColors.white,
    borderRadius: BorderRadius.circular(18),
    border: Border.all(color: LogisticsColors.border),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.035),
        blurRadius: 12,
        offset: const Offset(0, 5),
      ),
    ],
  );

  // ============================================================
  // ELEVATED CARD
  // ============================================================

  static final BoxDecoration elevatedCard = BoxDecoration(
    color: LogisticsColors.white,
    borderRadius: BorderRadius.circular(20),
    border: Border.all(color: LogisticsColors.border),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.06),
        blurRadius: 18,
        offset: const Offset(0, 7),
      ),
    ],
  );

  // ============================================================
  // ICON CONTAINER
  // ============================================================

  static BoxDecoration iconContainer({Color? color, double radius = 14}) {
    return BoxDecoration(
      color: color ?? LogisticsColors.lightPurple,
      borderRadius: BorderRadius.circular(radius),
    );
  }

  // ============================================================
  // PRIMARY BUTTON
  // ============================================================

  static final ButtonStyle primaryButton = ElevatedButton.styleFrom(
    backgroundColor: LogisticsColors.primary,
    foregroundColor: LogisticsColors.white,
    elevation: 0,
    minimumSize: const Size(double.infinity, 52),
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  // ============================================================
  // OUTLINED BUTTON
  // ============================================================

  static final ButtonStyle outlinedButton = OutlinedButton.styleFrom(
    foregroundColor: LogisticsColors.primary,
    backgroundColor: LogisticsColors.white,
    elevation: 0,
    minimumSize: const Size(double.infinity, 52),
    side: const BorderSide(color: LogisticsColors.primary),
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  // ============================================================
  // TEXT BUTTON
  // ============================================================

  static final ButtonStyle textButton = TextButton.styleFrom(
    foregroundColor: LogisticsColors.primary,
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
      hintStyle: const TextStyle(color: LogisticsColors.textGrey, fontSize: 13),
      prefixIcon: icon == null
          ? null
          : Icon(icon, color: LogisticsColors.primary),
      filled: true,
      fillColor: LogisticsColors.inputBackground,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: LogisticsColors.border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: LogisticsColors.border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(
          color: LogisticsColors.primary,
          width: 1.5,
        ),
      ),
    );
  }

  // ============================================================
  // STATUS BADGE
  // ============================================================

  static BoxDecoration statusBadge({required Color color}) {
    return BoxDecoration(
      color: color.withValues(alpha: 0.10),
      borderRadius: BorderRadius.circular(20),
    );
  }
}
