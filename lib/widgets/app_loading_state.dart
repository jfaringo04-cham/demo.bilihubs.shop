import 'package:flutter/material.dart';

class AppLoadingState extends StatelessWidget {
  final String message;
  final double indicatorSize;
  final Color? color;
  final bool compact;

  const AppLoadingState({
    super.key,
    this.message = 'Loading...',
    this.indicatorSize = 32,
    this.color,
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
          vertical: compact ? 16 : 48,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: indicatorSize,
              height: indicatorSize,
              child: CircularProgressIndicator(
                strokeWidth: 2.8,
                valueColor: AlwaysStoppedAnimation<Color>(primaryColor),
              ),
            ),

            if (message.trim().isNotEmpty) ...[
              SizedBox(height: compact ? 12 : 18),

              Text(
                message,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
