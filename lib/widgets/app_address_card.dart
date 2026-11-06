import 'package:flutter/material.dart';

import '../utils/address_formatter.dart';

class AppAddressCard extends StatelessWidget {
  final String? recipientName;
  final String? phoneNumber;
  final String? address;
  final String title;
  final IconData icon;
  final Color? accentColor;
  final VoidCallback? onTap;
  final Widget? trailing;
  final bool compact;
  final bool showTitle;
  final bool showBorder;
  final String fallback;

  const AppAddressCard({
    super.key,
    this.recipientName,
    this.phoneNumber,
    this.address,
    this.title = 'Delivery Address',
    this.icon = Icons.location_on_rounded,
    this.accentColor,
    this.onTap,
    this.trailing,
    this.compact = false,
    this.showTitle = true,
    this.showBorder = true,
    this.fallback = 'Address not available',
  });

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme colors = theme.colorScheme;

    final Color primaryColor = accentColor ?? colors.primary;

    final String name = _cleanValue(recipientName);
    final String phone = _cleanValue(phoneNumber);

    final List<String> addressLines = AddressFormatter.lines(
      address ?? '',
    );

    final bool hasAddress = addressLines.isNotEmpty &&
        addressLines.any(
          (String line) => line.trim().isNotEmpty,
        );

    final Widget content = Container(
      width: double.infinity,
      padding: EdgeInsets.all(compact ? 12 : 16),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(16),
        border: showBorder
            ? Border.all(
                color: colors.outlineVariant,
              )
            : null,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ======================================================
          // ADDRESS ICON
          // ======================================================

          Container(
            width: compact ? 38 : 44,
            height: compact ? 38 : 44,
            decoration: BoxDecoration(
              color: primaryColor.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              icon,
              color: primaryColor,
              size: compact ? 20 : 23,
            ),
          ),

          SizedBox(width: compact ? 10 : 12),

          // ======================================================
          // ADDRESS INFORMATION
          // ======================================================

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (showTitle) ...[
                  Text(
                    title.toUpperCase(),
                    style: TextStyle(
                      color: primaryColor,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.5,
                    ),
                  ),

                  SizedBox(height: compact ? 7 : 10),
                ],

                // ==================================================
                // RECIPIENT NAME
                // ==================================================

                if (name.isNotEmpty) ...[
                  Text(
                    name,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: colors.onSurface,
                      fontWeight: FontWeight.w800,
                    ),
                  ),

                  const SizedBox(height: 4),
                ],

                // ==================================================
                // PHONE NUMBER
                // ==================================================

                if (phone.isNotEmpty) ...[
                  Row(
                    children: [
                      Icon(
                        Icons.phone_outlined,
                        size: 14,
                        color: colors.onSurfaceVariant,
                      ),

                      const SizedBox(width: 6),

                      Expanded(
                        child: Text(
                          phone,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  ),

                  SizedBox(height: compact ? 8 : 10),
                ],

                // ==================================================
                // COMPLETE ADDRESS
                // ==================================================

                if (hasAddress)
                  ...addressLines.map(
                    (String line) => Padding(
                      padding: const EdgeInsets.only(bottom: 4),
                      child: Text(
                        line,
                        softWrap: true,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: colors.onSurface,
                          height: 1.45,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  )
                else
                  Text(
                    fallback,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: colors.onSurfaceVariant,
                      fontStyle: FontStyle.italic,
                    ),
                  ),
              ],
            ),
          ),

          // ======================================================
          // OPTIONAL TRAILING WIDGET
          // ======================================================

          if (trailing != null) ...[
            const SizedBox(width: 8),
            trailing!,
          ] else if (onTap != null) ...[
            const SizedBox(width: 8),
            Icon(
              Icons.chevron_right_rounded,
              size: 20,
              color: colors.onSurfaceVariant,
            ),
          ],
        ],
      ),
    );

    if (onTap == null) {
      return content;
    }

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: content,
      ),
    );
  }

  // ============================================================
  // CLEAN TEXT VALUES
  // ============================================================

  String _cleanValue(String? value) {
    final String cleaned = value?.trim() ?? '';

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return '';
    }

    return cleaned;
  }
}