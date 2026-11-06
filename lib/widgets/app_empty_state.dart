import 'package:flutter/material.dart';

class AppEmptyState extends StatelessWidget {
  final String title;
  final String message;
  final IconData icon;
  final Color? color;
  final String? actionLabel;
  final VoidCallback? onAction;
  final bool compact;

  const AppEmptyState({
    super.key,
    this.title = 'No Data Available',
    this.message = 'There is nothing to display right now.',
    this.icon = Icons.inbox_outlined,
    this.color,
    this.actionLabel,
    this.onAction,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    final Color primaryColor = color ?? theme.colorScheme.primary;

    return Center(
      child: Padding(
        padding: EdgeInsets.symmetric(
          horizontal: 24,
          vertical: compact ? 18 : 48,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // ======================================================
            // EMPTY STATE ICON
            // ======================================================

            Container(
              width: compact ? 58 : 76,
              height: compact ? 58 : 76,
              decoration: BoxDecoration(
                color: primaryColor.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(22),
              ),
              child: Icon(icon, color: primaryColor, size: compact ? 30 : 38),
            ),

            SizedBox(height: compact ? 14 : 20),

            // ======================================================
            // TITLE
            // ======================================================
            Text(
              title,
              textAlign: TextAlign.center,
              style: theme.textTheme.titleMedium?.copyWith(
                color: theme.colorScheme.onSurface,
                fontWeight: FontWeight.w800,
              ),
            ),

            const SizedBox(height: 8),

            // ======================================================
            // MESSAGE
            // ======================================================
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
                height: 1.5,
              ),
            ),

            // ======================================================
            // OPTIONAL ACTION BUTTON
            // ======================================================
            if (onAction != null &&
                actionLabel != null &&
                actionLabel!.trim().isNotEmpty) ...[
              SizedBox(height: compact ? 16 : 24),

              FilledButton.icon(
                onPressed: onAction,
                icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                label: Text(actionLabel!),
                style: FilledButton.styleFrom(
                  backgroundColor: primaryColor,
                  foregroundColor: Colors.white,
                  minimumSize: const Size(140, 46),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 20,
                    vertical: 12,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
