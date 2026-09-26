import 'package:flutter/material.dart';

class DashboardCard extends StatelessWidget {
  final String title;

  final String value;

  final IconData icon;

  final Color color;

  final VoidCallback? onTap;

  const DashboardCard({
    super.key,

    required this.title,

    required this.value,

    required this.icon,

    required this.color,

    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,

      child: Container(
        padding: const EdgeInsets.all(16),

        decoration: BoxDecoration(
          color: Colors.white,

          borderRadius: BorderRadius.circular(20),

          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: .05),

              blurRadius: 15,

              offset: const Offset(0, 8),
            ),
          ],
        ),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            Container(
              height: 45,

              width: 45,

              decoration: BoxDecoration(
                color: color.withValues(alpha: .1),

                borderRadius: BorderRadius.circular(14),
              ),

              child: Icon(icon, color: color, size: 25),
            ),

            const Spacer(),

            Text(
              value,

              style: TextStyle(
                fontSize: 24,

                fontWeight: FontWeight.bold,

                color: color,
              ),
            ),

            const SizedBox(height: 5),

            Text(
              title,

              maxLines: 2,

              overflow: TextOverflow.ellipsis,

              style: const TextStyle(
                fontSize: 13,

                color: Colors.grey,

                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
