import 'package:flutter/material.dart';

// ============================================================
// BUYER COLORS
// Universal color system for all Buyer pages
// ============================================================

class BuyerColors {
  // ------------------------------------------------------------
  // MAIN COLORS
  // ------------------------------------------------------------

  static const Color primary = Color(0xFF5B3FE8);

  static const Color primaryDark = Color(0xFF4930C7);

  static const Color background = Color(0xFFF8F7FF);

  static const Color lightPurple = Color(0xFFF0EEFF);

  static const Color softPurple = Color(0xFFE8E4FF);

  // ------------------------------------------------------------
  // TEXT COLORS
  // ------------------------------------------------------------

  static const Color textDark = Color(0xFF13264F);

  static const Color textGrey = Color(0xFF68738A);

  static const Color textLight = Color(0xFFA0A4B3);

  // ------------------------------------------------------------
  // OTHER COLORS
  // ------------------------------------------------------------

  static const Color border = Color(0xFFE6E3F2);

  static const Color white = Colors.white;

  static const Color success = Color(0xFF22A06B);

  static const Color warning = Color(0xFFF59E0B);

  static const Color danger = Color(0xFFDC3545);

  // ------------------------------------------------------------
  // ORDER STATUS COLORS
  // ------------------------------------------------------------

  static const Color deliveredBackground = Color(0xFFE8F7EE);

  static const Color deliveredText = Color(0xFF22A06B);

  static const Color processingBackground = Color(0xFFFFF4DD);

  static const Color processingText = Color(0xFFF59E0B);

  static const Color shippedBackground = Color(0xFFE8F0FF);

  static const Color shippedText = Color(0xFF4B72E8);

  static const Color cancelledBackground = Color(0xFFFFEAEA);

  static const Color cancelledText = Color(0xFFDC3545);

  // ------------------------------------------------------------
  // INPUT COLORS
  // ------------------------------------------------------------

  static const Color inputBackground = Color(0xFFFBFAFF);
}

// ============================================================
// BUYER STYLE
// Universal style system for all Buyer pages
// ============================================================

class BuyerStyle {
  // ------------------------------------------------------------
  // TEXT STYLES
  // ------------------------------------------------------------

  static const TextStyle welcome = TextStyle(
    fontSize: 13,
    color: BuyerColors.primary,
    fontWeight: FontWeight.w700,
  );

  static const TextStyle appBarTitle = TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w700,
    color: BuyerColors.textDark,
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
    height: 1.35,
    color: BuyerColors.textGrey,
  );

  static const TextStyle body = TextStyle(
    fontSize: 13,
    height: 1.45,
    color: BuyerColors.textGrey,
  );

  static const TextStyle price = TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w800,
    color: BuyerColors.primary,
  );

  static const TextStyle largePrice = TextStyle(
    fontSize: 18,
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

  static const TextStyle link = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w600,
    color: BuyerColors.primary,
  );

  // ------------------------------------------------------------
  // CARD
  // ------------------------------------------------------------

  static final BoxDecoration card = BoxDecoration(
    color: BuyerColors.white,

    borderRadius: BorderRadius.circular(18),

    border: Border.all(color: BuyerColors.border),

    boxShadow: [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.035),
        blurRadius: 14,
        offset: const Offset(0, 6),
      ),
    ],
  );

  // ------------------------------------------------------------
  // ELEVATED CARD
  // ------------------------------------------------------------

  static final BoxDecoration elevatedCard = BoxDecoration(
    color: BuyerColors.white,

    borderRadius: BorderRadius.circular(20),

    border: Border.all(color: BuyerColors.border.withValues(alpha: 0.75)),

    boxShadow: [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.045),
        blurRadius: 18,
        offset: const Offset(0, 8),
      ),
    ],
  );

  // ------------------------------------------------------------
  // SEARCH
  // ------------------------------------------------------------

  static final BoxDecoration search = BoxDecoration(
    color: BuyerColors.white,

    borderRadius: BorderRadius.circular(14),

    border: Border.all(color: BuyerColors.border),
  );

  // ------------------------------------------------------------
  // PRIMARY BUTTON
  // ------------------------------------------------------------

  static final ButtonStyle primaryButton = ElevatedButton.styleFrom(
    backgroundColor: BuyerColors.primary,

    foregroundColor: BuyerColors.white,

    disabledBackgroundColor: BuyerColors.primary.withValues(alpha: 0.45),

    disabledForegroundColor: BuyerColors.white.withValues(alpha: 0.75),

    elevation: 0,

    minimumSize: const Size(double.infinity, 52),

    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),

    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  // ------------------------------------------------------------
  // OUTLINED BUTTON
  // ------------------------------------------------------------

  static final ButtonStyle outlinedButton = OutlinedButton.styleFrom(
    foregroundColor: BuyerColors.primary,

    backgroundColor: BuyerColors.white,

    elevation: 0,

    minimumSize: const Size(double.infinity, 52),

    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),

    side: const BorderSide(color: BuyerColors.primary, width: 1.2),

    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
  );

  // ------------------------------------------------------------
  // TEXT BUTTON
  // ------------------------------------------------------------

  static final ButtonStyle textButton = TextButton.styleFrom(
    foregroundColor: BuyerColors.primary,

    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),

    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
  );

  // ------------------------------------------------------------
  // INPUT DECORATION
  // ------------------------------------------------------------

  static InputDecoration inputDecoration({
    required String hint,
    IconData? icon,
  }) {
    return InputDecoration(
      hintText: hint,

      hintStyle: const TextStyle(color: BuyerColors.textGrey, fontSize: 13),

      prefixIcon: icon == null
          ? null
          : Icon(icon, color: BuyerColors.primary, size: 21),

      filled: true,

      fillColor: BuyerColors.inputBackground,

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

      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),

        borderSide: const BorderSide(color: BuyerColors.danger),
      ),

      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),

        borderSide: const BorderSide(color: BuyerColors.danger, width: 1.5),
      ),
    );
  }

  // ------------------------------------------------------------
  // STATUS BADGE
  // ------------------------------------------------------------

  static BoxDecoration statusBadge(Color backgroundColor) {
    return BoxDecoration(
      color: backgroundColor,

      borderRadius: BorderRadius.circular(20),
    );
  }

  // ------------------------------------------------------------
  // ICON CONTAINER
  // ------------------------------------------------------------

  static BoxDecoration iconContainer({Color? backgroundColor}) {
    return BoxDecoration(
      color: backgroundColor ?? BuyerColors.lightPurple,

      borderRadius: BorderRadius.circular(14),
    );
  }
}

// ============================================================
// BACKWARD COMPATIBILITY
//
// Existing buyer_home.dart can continue using:
// BuyerHomeColors
// BuyerHomeStyle
// ============================================================

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
}

class BuyerHomeStyle {
  static const TextStyle welcome = BuyerStyle.welcome;

  static const TextStyle title = BuyerStyle.largeTitle;

  static const TextStyle subtitle = BuyerStyle.subtitle;

  static const TextStyle sectionTitle = BuyerStyle.sectionTitle;

  static const TextStyle category = BuyerStyle.category;

  static const TextStyle productTitle = BuyerStyle.productTitle;

  static const TextStyle small = BuyerStyle.small;

  static const TextStyle price = BuyerStyle.price;

  static const TextStyle cardTitle = BuyerStyle.cardTitle;

  static const TextStyle buttonText = BuyerStyle.buttonText;

  static final BoxDecoration card = BuyerStyle.card;

  static final BoxDecoration search = BuyerStyle.search;
}
