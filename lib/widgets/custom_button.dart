import 'package:flutter/material.dart';

class CustomButton extends StatelessWidget {
  final String text;

  final VoidCallback? onPressed;

  final IconData? icon;

  final bool isLoading;

  final Color? backgroundColor;

  final Color? textColor;

  const CustomButton({
    super.key,

    required this.text,

    required this.onPressed,

    this.icon,

    this.isLoading = false,

    this.backgroundColor,

    this.textColor,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,

      height: 55,

      child: ElevatedButton(
        onPressed: isLoading ? null : onPressed,

        style: ElevatedButton.styleFrom(
          backgroundColor: backgroundColor ?? Colors.blue,

          disabledBackgroundColor: Colors.blue.withValues(alpha: .5),

          elevation: 0,

          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),

        child: isLoading
            ? const SizedBox(
                height: 22,

                width: 22,

                child: CircularProgressIndicator(
                  strokeWidth: 2,

                  color: Colors.white,
                ),
              )
            : Row(
                mainAxisAlignment: MainAxisAlignment.center,

                mainAxisSize: MainAxisSize.min,

                children: [
                  if (icon != null)
                    Icon(icon, color: textColor ?? Colors.white),

                  if (icon != null) const SizedBox(width: 8),

                  Text(
                    text,

                    style: TextStyle(
                      color: textColor ?? Colors.white,

                      fontWeight: FontWeight.bold,

                      fontSize: 15,
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}
