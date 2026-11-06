import 'package:flutter/material.dart';

class AppErrorState extends StatelessWidget {
  final String message;
  final String title;
  final VoidCallback? onRetry;
  final String retryLabel;
  final IconData icon;
  final Color? color;
  final bool compact;

  const AppErrorState({
    super.key,
    this.title = 'Something Went Wrong',
    this.message = 'Unable to load data. Please try again.',
    this.onRetry,
    this.retryLabel = 'Try Again',
    this.icon = Icons.cloud_off_outlined,
    this.color,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    final Color primaryColor = color ?? theme.colorScheme.primary;

    final Color textColor = theme.colorScheme.onSurface;

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
            // ERROR ICON
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
            // ERROR TITLE
            // ======================================================
            Text(
              title,
              textAlign: TextAlign.center,
              style: theme.textTheme.titleMedium?.copyWith(
                color: textColor,
                fontWeight: FontWeight.w800,
              ),
            ),

            const SizedBox(height: 8),

            // ======================================================
            // ERROR MESSAGE
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
            // RETRY BUTTON
            // ======================================================
            if (onRetry != null) ...[
              SizedBox(height: compact ? 16 : 24),

              FilledButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded, size: 19),
                label: Text(retryLabel),
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
