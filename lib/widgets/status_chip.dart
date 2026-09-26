import 'package:flutter/material.dart';

class StatusChip extends StatelessWidget {
  final String status;

  const StatusChip({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    final statusData = _getStatusStyle(status);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),

      decoration: BoxDecoration(
        color: statusData.color.withValues(alpha: .12),

        borderRadius: BorderRadius.circular(20),
      ),

      child: Text(
        status,

        style: TextStyle(
          color: statusData.color,

          fontSize: 12,

          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  _StatusStyle _getStatusStyle(String status) {
    switch (status.toLowerCase()) {
      case "delivered":
        return _StatusStyle(Colors.green);

      case "completed":
        return _StatusStyle(Colors.green);

      case "out for delivery":
        return _StatusStyle(Colors.blue);

      case "in transit":
        return _StatusStyle(Colors.indigo);

      case "preparing":
        return _StatusStyle(Colors.orange);

      case "pending":
        return _StatusStyle(Colors.amber);

      case "cancelled":
        return _StatusStyle(Colors.red);

      default:
        return _StatusStyle(Colors.grey);
    }
  }
}

class _StatusStyle {
  final Color color;

  const _StatusStyle(this.color);
}
