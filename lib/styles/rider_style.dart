import 'package:flutter/material.dart';

class RiderColors {
  // ============================================================
  // MAIN COLORS
  // ============================================================

  static const Color primary = Color(0xFF5B3FE8);
  static const Color primaryDark = Color(0xFF4930C7);

  static const Color background = Color(0xFFF8F7FF);
  static const Color lightPurple = Color(0xFFF0EEFF);
  static const Color softPurple = Color(0xFFE8E4FF);

  static const Color textDark = Color(0xFF13264F);
  static const Color textGrey = Color(0xFF68738A);
  static const Color textLight = Color(0xFF8A94A6);

  static const Color border = Color(0xFFE6E3F2);
  static const Color white = Colors.white;

  // ============================================================
  // STATUS / GENERAL COLORS
  // ============================================================

  static const Color success = Color(0xFF22A06B);
  static const Color warning = Color(0xFFF59E0B);
  static const Color danger = Color(0xFFDC3545);

  static const Color online = Color(0xFF22A06B);
  static const Color earnings = Color(0xFFF59E0B);

  // ============================================================
  // DASHBOARD COLORS
  // ============================================================

  static const Color lightBlue = Color(0xFFEAF2FF);
  static const Color lightGreen = Color(0xFFE8F7EF);
  static const Color lightOrange = Color(0xFFFFF4DD);

  // ============================================================
  // DELIVERY STATUS COLORS
  // ============================================================

  static const Color pending = Color(0xFFF59E0B);
  static const Color delivering = Color(0xFF2864E8);
  static const Color completed = Color(0xFF22A06B);
  static const Color cancelled = Color(0xFFDC3545);

  // ============================================================
  // INPUT
  // ============================================================

  static const Color inputBackground = Color(0xFFF8F9FC);
}

class RiderStyle {
  // ============================================================
  // TEXT STYLES
  // ============================================================

  static const TextStyle welcome = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w700,
    color: RiderColors.primary,
  );

  static const TextStyle appBarTitle = TextStyle(
    fontSize: 17,
    fontWeight: FontWeight.w800,
    color: RiderColors.textDark,
  );

  static const TextStyle title = TextStyle(
    fontSize: 24,
    height: 1.15,
    fontWeight: FontWeight.w800,
    color: RiderColors.textDark,
  );

  static const TextStyle largeTitle = TextStyle(
    fontSize: 26,
    height: 1.15,
    fontWeight: FontWeight.w800,
    color: RiderColors.textDark,
  );

  static const TextStyle subtitle = TextStyle(
    fontSize: 13,
    height: 1.4,
    color: RiderColors.textGrey,
  );

  static const TextStyle sectionTitle = TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w800,
    color: RiderColors.textDark,
  );

  static const TextStyle cardTitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    color: RiderColors.textDark,
  );

  static const TextStyle small = TextStyle(
    fontSize: 11,
    height: 1.35,
    color: RiderColors.textGrey,
  );

  static const TextStyle body = TextStyle(
    fontSize: 14,
    height: 1.45,
    color: RiderColors.textDark,
  );

  static const TextStyle price = TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w800,
    color: RiderColors.primary,
  );

  static const TextStyle largePrice = TextStyle(
    fontSize: 20,
    fontWeight: FontWeight.w800,
    color: RiderColors.primary,
  );

  static const TextStyle buttonText = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    color: RiderColors.white,
  );

  static const TextStyle label = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w600,
    color: RiderColors.textDark,
  );

  static const TextStyle link = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w700,
    color: RiderColors.primary,
  );

  // ============================================================
  // CARD
  // ============================================================

  static final BoxDecoration card = BoxDecoration(
    color: RiderColors.white,
    borderRadius: BorderRadius.circular(16),
    border: Border.all(color: RiderColors.border),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.035),
        blurRadius: 12,
        offset: const Offset(0, 5),
      ),
    ],
  );

  static final BoxDecoration elevatedCard = BoxDecoration(
    color: RiderColors.white,
    borderRadius: BorderRadius.circular(18),
    border: Border.all(color: RiderColors.border),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.06),
        blurRadius: 18,
        offset: const Offset(0, 7),
      ),
    ],
  );

  // ============================================================
  // SEARCH
  // ============================================================

  static final BoxDecoration search = BoxDecoration(
    color: RiderColors.white,
    borderRadius: BorderRadius.circular(14),
    border: Border.all(color: RiderColors.border),
  );

  // ============================================================
  // ICON CONTAINER
  // ============================================================

  static BoxDecoration iconContainer({Color? color, double radius = 14}) {
    return BoxDecoration(
      color: color ?? RiderColors.lightPurple,
      borderRadius: BorderRadius.circular(radius),
    );
  }

  // ============================================================
  // PRIMARY BUTTON
  // ============================================================

  static final ButtonStyle primaryButton = ElevatedButton.styleFrom(
    backgroundColor: RiderColors.primary,
    foregroundColor: RiderColors.white,
    elevation: 0,
    minimumSize: const Size(double.infinity, 52),
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  // ============================================================
  // OUTLINED BUTTON
  // ============================================================

  static final ButtonStyle outlinedButton = OutlinedButton.styleFrom(
    foregroundColor: RiderColors.primary,
    backgroundColor: RiderColors.white,
    elevation: 0,
    minimumSize: const Size(double.infinity, 52),
    side: const BorderSide(color: RiderColors.primary),
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  // ============================================================
  // TEXT BUTTON
  // ============================================================

  static final ButtonStyle textButton = TextButton.styleFrom(
    foregroundColor: RiderColors.primary,
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
      hintStyle: const TextStyle(color: RiderColors.textGrey, fontSize: 13),
      prefixIcon: icon == null ? null : Icon(icon, color: RiderColors.primary),
      filled: true,
      fillColor: RiderColors.inputBackground,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: RiderColors.border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: RiderColors.border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: RiderColors.primary, width: 1.5),
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
