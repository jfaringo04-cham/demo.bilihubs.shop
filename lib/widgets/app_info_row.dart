import 'package:flutter/material.dart';

class AppInfoRow extends StatelessWidget {
  final String label;
  final String? value;
  final IconData? icon;
  final Color? iconColor;
  final Color? iconBackgroundColor;
  final Color? labelColor;
  final Color? valueColor;
  final Widget? trailing;
  final VoidCallback? onTap;

  final bool showIconBackground;
  final bool compact;
  final bool selectable;
  final bool showDivider;

  final int? maxLines;
  final String fallback;

  const AppInfoRow({
    super.key,
    required this.label,
    required this.value,
    this.icon,
    this.iconColor,
    this.iconBackgroundColor,
    this.labelColor,
    this.valueColor,
    this.trailing,
    this.onTap,
    this.showIconBackground = true,
    this.compact = false,
    this.selectable = false,
    this.showDivider = false,
    this.maxLines,
    this.fallback = 'Not available',
  });

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme colors = theme.colorScheme;

    final String displayValue = _displayValue(value);

    final Color resolvedIconColor = iconColor ?? colors.primary;

    final Color resolvedIconBackground =
        iconBackgroundColor ?? colors.primary.withValues(alpha: 0.09);

    final Color resolvedLabelColor = labelColor ?? colors.onSurfaceVariant;

    final Color resolvedValueColor = valueColor ?? colors.onSurface;

    final Widget valueWidget = selectable
        ? SelectableText(
            displayValue,
            style: TextStyle(
              color: resolvedValueColor,
              fontSize: compact ? 12 : 14,
              fontWeight: FontWeight.w600,
              height: 1.4,
            ),
          )
        : Text(
            displayValue,
            maxLines: maxLines,
            overflow: maxLines == null
                ? TextOverflow.visible
                : TextOverflow.ellipsis,
            style: TextStyle(
              color: resolvedValueColor,
              fontSize: compact ? 12 : 14,
              fontWeight: FontWeight.w600,
              height: 1.4,
            ),
          );

    final Widget content = Padding(
      padding: EdgeInsets.symmetric(vertical: compact ? 6 : 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ======================================================
          // OPTIONAL ICON
          // ======================================================

          if (icon != null) ...[
            showIconBackground
                ? Container(
                    width: compact ? 32 : 38,
                    height: compact ? 32 : 38,
                    decoration: BoxDecoration(
                      color: resolvedIconBackground,
                      borderRadius: BorderRadius.circular(compact ? 9 : 11),
                    ),
                    child: Icon(
                      icon,
                      size: compact ? 17 : 20,
                      color: resolvedIconColor,
                    ),
                  )
                : Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Icon(
                      icon,
                      size: compact ? 17 : 20,
                      color: resolvedIconColor,
                    ),
                  ),

            SizedBox(width: compact ? 9 : 12),
          ],

          // ======================================================
          // LABEL AND VALUE
          // ======================================================
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    color: resolvedLabelColor,
                    fontSize: compact ? 11 : 12,
                    fontWeight: FontWeight.w500,
                  ),
                ),

                SizedBox(height: compact ? 3 : 5),

                valueWidget,
              ],
            ),
          ),

          // ======================================================
          // OPTIONAL TRAILING WIDGET
          // ======================================================
          if (trailing != null) ...[const SizedBox(width: 8), trailing!],

          if (onTap != null && trailing == null) ...[
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

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        onTap != null
            ? Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: onTap,
                  borderRadius: BorderRadius.circular(12),
                  child: content,
                ),
              )
            : content,

        if (showDivider)
          Divider(height: 1, thickness: 1, color: colors.outlineVariant),
      ],
    );
  }

  // ============================================================
  // VALUE FORMATTER
  // ============================================================

  String _displayValue(String? rawValue) {
    final String cleaned = rawValue?.trim() ?? '';

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return fallback;
    }

    return cleaned;
  }
}
