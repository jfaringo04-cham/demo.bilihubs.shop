import 'package:flutter/material.dart';

class BuyerColors {
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

  static const Color border = Color(0xFFE6E3F2);
  static const Color white = Colors.white;

  // ============================================================
  // GENERAL STATUS COLORS
  // ============================================================

  static const Color success = Color(0xFF22A06B);
  static const Color warning = Color(0xFFF59E0B);
  static const Color danger = Color(0xFFDC3545);

  // ============================================================
  // ORDER STATUS COLORS
  // ============================================================

  static const Color deliveredText = Color(0xFF16834B);
  static const Color processingText = Color(0xFFB26A00);
  static const Color shippedText = Color(0xFF2864E8);
  static const Color cancelledText = Color(0xFFD32F2F);

  static const Color deliveredBackground = Color(0xFFE8F7EF);
  static const Color processingBackground = Color(0xFFFFF4DD);
  static const Color shippedBackground = Color(0xFFEAF2FF);
  static const Color cancelledBackground = Color(0xFFFFEAEA);
}

class BuyerStyle {
  // ============================================================
  // TEXT STYLES
  // ============================================================

  static const TextStyle welcome = TextStyle(
    fontSize: 13,
    color: BuyerColors.primary,
    fontWeight: FontWeight.w700,
  );

  static const TextStyle title = TextStyle(
    fontSize: 24,
    height: 1.15,
    fontWeight: FontWeight.w800,
    color: BuyerColors.textDark,
  );

  static const TextStyle largeTitle = TextStyle(
    fontSize: 26,
    height: 1.15,
    fontWeight: FontWeight.w800,
    color: BuyerColors.textDark,
  );

  static const TextStyle appBarTitle = TextStyle(
    fontSize: 17,
    fontWeight: FontWeight.w800,
    color: BuyerColors.textDark,
  );

  static const TextStyle body = TextStyle(
    fontSize: 14,
    height: 1.45,
    color: BuyerColors.textDark,
  );

  static const TextStyle subtitle = TextStyle(
    fontSize: 13,
    height: 1.4,
    color: BuyerColors.textGrey,
  );

  static const TextStyle sectionTitle = TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w800,
    color: BuyerColors.textDark,
  );

  static const TextStyle category = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w600,
    color: BuyerColors.textDark,
  );

  static const TextStyle cardTitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    color: BuyerColors.textDark,
  );

  static const TextStyle productTitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    color: BuyerColors.textDark,
  );

  static const TextStyle small = TextStyle(
    fontSize: 11,
    color: BuyerColors.textGrey,
  );

  static const TextStyle price = TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w800,
    color: BuyerColors.primary,
  );

  static const TextStyle largePrice = TextStyle(
    fontSize: 20,
    fontWeight: FontWeight.w800,
    color: BuyerColors.primary,
  );

  static const TextStyle buttonText = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    color: BuyerColors.white,
  );

  static const TextStyle label = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w600,
    color: BuyerColors.textDark,
  );

  // ============================================================
  // CARD STYLES
  // ============================================================

  static final BoxDecoration card = BoxDecoration(
    color: BuyerColors.white,
    borderRadius: BorderRadius.circular(16),
    border: Border.all(color: BuyerColors.border),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.035),
        blurRadius: 12,
        offset: const Offset(0, 5),
      ),
    ],
  );

  static final BoxDecoration elevatedCard = BoxDecoration(
    color: BuyerColors.white,
    borderRadius: BorderRadius.circular(18),
    border: Border.all(color: BuyerColors.border),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.06),
        blurRadius: 18,
        offset: const Offset(0, 7),
      ),
    ],
  );

  static final BoxDecoration search = BoxDecoration(
    color: BuyerColors.white,
    borderRadius: BorderRadius.circular(14),
    border: Border.all(color: BuyerColors.border),
  );

  // ============================================================
  // ICON CONTAINER
  // ============================================================

  static BoxDecoration iconContainer({Color? color, double radius = 14}) {
    return BoxDecoration(
      color: color ?? BuyerColors.lightPurple,
      borderRadius: BorderRadius.circular(radius),
    );
  }

  // ============================================================
  // BUTTONS
  // ============================================================

  static final ButtonStyle primaryButton = ElevatedButton.styleFrom(
    backgroundColor: BuyerColors.primary,
    foregroundColor: BuyerColors.white,
    elevation: 0,
    minimumSize: const Size(double.infinity, 52),
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  static final ButtonStyle outlinedButton = OutlinedButton.styleFrom(
    foregroundColor: BuyerColors.primary,
    backgroundColor: BuyerColors.white,
    elevation: 0,
    minimumSize: const Size(double.infinity, 52),
    side: const BorderSide(color: BuyerColors.primary),
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
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
      hintStyle: const TextStyle(color: BuyerColors.textGrey, fontSize: 13),

      prefixIcon: icon == null ? null : Icon(icon, color: BuyerColors.primary),

      filled: true,
      fillColor: BuyerColors.white,

      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),

      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: BuyerColors.border),
      ),

      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: BuyerColors.border),
      ),

      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: BuyerColors.primary, width: 1.5),
      ),
    );
  }
}

// ================================================================
// BUYER HOME COLORS
// ================================================================

class BuyerHomeColors {
  static const Color primary = BuyerColors.primary;
  static const Color primaryDark = BuyerColors.primaryDark;

  static const Color background = BuyerColors.background;
  static const Color lightPurple = BuyerColors.lightPurple;
  static const Color softPurple = BuyerColors.softPurple;

  static const Color textDark = BuyerColors.textDark;
  static const Color textGrey = BuyerColors.textGrey;

  static const Color border = BuyerColors.border;
  static const Color white = BuyerColors.white;

  static const Color success = BuyerColors.success;
  static const Color warning = BuyerColors.warning;
  static const Color danger = BuyerColors.danger;

  static const Color deliveredText = BuyerColors.deliveredText;
  static const Color processingText = BuyerColors.processingText;
  static const Color shippedText = BuyerColors.shippedText;
  static const Color cancelledText = BuyerColors.cancelledText;

  static const Color deliveredBackground = BuyerColors.deliveredBackground;

  static const Color processingBackground = BuyerColors.processingBackground;

  static const Color shippedBackground = BuyerColors.shippedBackground;

  static const Color cancelledBackground = BuyerColors.cancelledBackground;
}

// ================================================================
// BUYER HOME STYLE
// ================================================================

class BuyerHomeStyle {
  static const TextStyle welcome = BuyerStyle.welcome;

  static const TextStyle title = BuyerStyle.largeTitle;

  static const TextStyle largeTitle = BuyerStyle.largeTitle;

  static const TextStyle subtitle = BuyerStyle.subtitle;

  static const TextStyle sectionTitle = BuyerStyle.sectionTitle;

  static const TextStyle category = BuyerStyle.category;

  static const TextStyle cardTitle = BuyerStyle.cardTitle;

  static const TextStyle productTitle = BuyerStyle.productTitle;

  static const TextStyle small = BuyerStyle.small;

  static const TextStyle price = BuyerStyle.price;

  static const TextStyle largePrice = BuyerStyle.largePrice;

  static const TextStyle buttonText = BuyerStyle.buttonText;

  static final BoxDecoration card = BuyerStyle.card;

  static final BoxDecoration search = BuyerStyle.search;
}
