class ParcelModel {
  final int id;

  final String trackingNumber;

  final String customerName;

  final String customerPhone;

  final String deliveryAddress;

  final String riderName;

  final String riderId;

  final String status;

  final String orderDate;

  final double deliveryEarnings;

  final double latitude;

  final double longitude;

  ParcelModel({
    required this.id,
    required this.trackingNumber,
    required this.customerName,
    required this.customerPhone,
    required this.deliveryAddress,
    required this.riderName,
    required this.riderId,
    required this.status,
    required this.orderDate,
    required this.deliveryEarnings,
    required this.latitude,
    required this.longitude,
  });

  factory ParcelModel.fromJson(Map<String, dynamic> json) {
    return ParcelModel(
      id:
          int.tryParse(
            json['shipment_id']?.toString() ?? json['id']?.toString() ?? '',
          ) ??
          0,

      trackingNumber: json['tracking_number']?.toString() ?? '',

      customerName: json['customer_name']?.toString() ?? '',

      customerPhone: json['customer_phone']?.toString() ?? '',

      deliveryAddress: json['delivery_address']?.toString() ?? '',

      riderName: json['rider_name']?.toString() ?? '',

      riderId: json['rider_id']?.toString() ?? '',

      status: json['status']?.toString() ?? '',

      orderDate: json['order_date']?.toString() ?? '',

      deliveryEarnings: _toDouble(json['delivery_earnings']),

      latitude: _toDouble(json['latitude']),

      longitude: _toDouble(json['longitude']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'shipment_id': id,
      'tracking_number': trackingNumber,
      'customer_name': customerName,
      'customer_phone': customerPhone,
      'delivery_address': deliveryAddress,
      'rider_name': riderName,
      'rider_id': riderId,
      'status': status,
      'order_date': orderDate,
      'delivery_earnings': deliveryEarnings,
      'latitude': latitude,
      'longitude': longitude,
    };
  }

  bool get hasLocation {
    return latitude != 0.0 || longitude != 0.0;
  }

  bool get isDelivered {
    return normalizedStatus == 'delivered';
  }

  bool get isFailed {
    return normalizedStatus == 'failed' ||
        normalizedStatus == 'delivery_failed' ||
        normalizedStatus == 'cancelled' ||
        normalizedStatus == 'failed_return_at_sorting_center';
  }

  bool get isActive {
    return !isDelivered && !isFailed;
  }

  String get normalizedStatus {
    return status.trim().toLowerCase().replaceAll(' ', '_');
  }

  static double _toDouble(dynamic value) {
    if (value == null) {
      return 0.0;
    }

    final text = value.toString().trim();

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return 0.0;
    }

    return double.tryParse(text) ?? 0.0;
  }
}
