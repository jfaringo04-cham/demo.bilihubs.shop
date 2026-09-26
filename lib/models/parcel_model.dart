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
      id: json['id'] ?? 0,

      trackingNumber: json['tracking_number'] ?? "",

      customerName: json['customer_name'] ?? "",

      customerPhone: json['customer_phone'] ?? "",

      deliveryAddress: json['delivery_address'] ?? "",

      riderName: json['rider_name'] ?? "",

      riderId: json['rider_id'] ?? "",

      status: json['status'] ?? "",

      orderDate: json['order_date'] ?? "",

      deliveryEarnings:
          double.tryParse(json['delivery_earnings'].toString()) ?? 0.0,

      latitude: double.tryParse(json['latitude'].toString()) ?? 0.0,

      longitude: double.tryParse(json['longitude'].toString()) ?? 0.0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "id": id,

      "tracking_number": trackingNumber,

      "customer_name": customerName,

      "customer_phone": customerPhone,

      "delivery_address": deliveryAddress,

      "rider_name": riderName,

      "rider_id": riderId,

      "status": status,

      "order_date": orderDate,

      "delivery_earnings": deliveryEarnings,

      "latitude": latitude,

      "longitude": longitude,
    };
  }
}
