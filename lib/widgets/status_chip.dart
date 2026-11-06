import 'package:flutter/material.dart';

class StatusChip extends StatelessWidget {
  final String status;

  const StatusChip({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    final String normalized = _normalizeStatus(status);
    final _StatusStyle statusData = _getStatusStyle(normalized);

    return Container(
      constraints: const BoxConstraints(maxWidth: 150),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: statusData.color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: statusData.color.withValues(alpha: 0.18)),
      ),
      child: Text(
        _formatStatus(normalized),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        textAlign: TextAlign.center,
        style: TextStyle(
          color: statusData.color,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  // ============================================================
  // NORMALIZE STATUS
  // ============================================================

  String _normalizeStatus(String value) {
    return value
        .trim()
        .toLowerCase()
        .replaceAll('-', '_')
        .replaceAll(RegExp(r'\s+'), '_');
  }

  // ============================================================
  // STATUS STYLE
  // ============================================================

  _StatusStyle _getStatusStyle(String status) {
    switch (status) {
      // ========================================================
      // SUCCESS / COMPLETED / VERIFIED
      // ========================================================

      case 'delivered':
      case 'completed':
      case 'confirmed':
      case 'approved':
      case 'verified':
      case 'paid':
      case 'credited':
      case 'released':
      case 'wallet_credited':
        return const _StatusStyle(Colors.green);

      // ========================================================
      // FINAL-MILE DELIVERY
      // ========================================================

      case 'out_for_delivery':
      case 'shipped':
        return const _StatusStyle(Colors.blue);

      // ========================================================
      // TRANSIT / LINE HAUL
      // ========================================================

      case 'in_transit':
      case 'linehaul_in_transit':
      case 'in_hub_transfer':
        return const _StatusStyle(Colors.indigo);

      case 'linehaul_assigned':
        return const _StatusStyle(Colors.deepPurple);

      // ========================================================
      // HUB / SORTING
      // ========================================================

      case 'at_sorting_center':
      case 'at_origin_hub':
      case 'at_destination_hub':
      case 'received':
      case 'scanned':
        return const _StatusStyle(Colors.blueGrey);

      case 'sorting':
      case 'sorted':
        return const _StatusStyle(Colors.deepOrange);

      case 'staged':
        return const _StatusStyle(Colors.teal);

      // ========================================================
      // PROCESSING / PREPARING
      // ========================================================

      case 'processing':
      case 'preparing':
        return const _StatusStyle(Colors.orange);

      // ========================================================
      // PENDING / ASSIGNMENT / COD VERIFICATION
      // ========================================================

      case 'pending':
      case 'awaiting_payment':
      case 'awaiting_assignment':
      case 'submitted':
      case 'for_verification':
      case 'eligible':
        return const _StatusStyle(Colors.amber);

      case 'assigned':
        return const _StatusStyle(Colors.deepOrange);

      case 'ready_for_pickup':
        return const _StatusStyle(Colors.orange);

      case 'picked_up':
        return const _StatusStyle(Colors.blueAccent);

      // ========================================================
      // FAILED / CANCELLED / REJECTED
      // ========================================================

      case 'cancelled':
      case 'canceled':
      case 'failed':
      case 'delivery_failed':
      case 'rejected':
        return const _StatusStyle(Colors.red);

      case 'failed_return_at_sorting_center':
        return const _StatusStyle(Colors.redAccent);

      // ========================================================
      // DEFAULT
      // ========================================================

      default:
        return const _StatusStyle(Colors.blueGrey);
    }
  }

  // ============================================================
  // FORMAT STATUS
  // ============================================================

  String _formatStatus(String status) {
    if (status.isEmpty) {
      return 'Unknown';
    }

    switch (status) {
      case 'out_for_delivery':
        return 'Out for Delivery';

      case 'in_transit':
        return 'In Transit';

      case 'ready_for_pickup':
        return 'Ready for Pickup';

      case 'picked_up':
        return 'Picked Up';

      case 'at_sorting_center':
        return 'At Sorting Center';

      case 'at_origin_hub':
        return 'At Origin Hub';

      case 'at_destination_hub':
        return 'At Destination Hub';

      case 'in_hub_transfer':
        return 'In Hub Transfer';

      case 'linehaul_assigned':
        return 'Line-Haul Assigned';

      case 'linehaul_in_transit':
        return 'Line-Haul In Transit';

      case 'linehaul_completed':
        return 'Line-Haul Completed';

      case 'delivery_failed':
        return 'Delivery Failed';

      case 'failed_return_at_sorting_center':
        return 'Returned to Sorting Center';

      case 'for_verification':
        return 'For Verification';

      case 'awaiting_payment':
        return 'Awaiting Payment';

      case 'awaiting_assignment':
        return 'Awaiting Assignment';

      case 'wallet_credited':
        return 'Wallet Credited';

      default:
        return status
            .split('_')
            .where((String part) => part.isNotEmpty)
            .map(
              (String part) =>
                  '${part[0].toUpperCase()}'
                  '${part.substring(1).toLowerCase()}',
            )
            .join(' ');
    }
  }
}

// ============================================================
// STATUS STYLE MODEL
// ============================================================

class _StatusStyle {
  final Color color;

  const _StatusStyle(this.color);
}
