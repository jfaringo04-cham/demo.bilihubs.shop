class ParcelModel {
  final int id;

  final String trackingNumber;

  final String customerName;

  final String customerPhone;

  final String deliveryAddress;

  final String pickupAddress;

  final String riderName;

  final String riderId;

  final String status;

  final String orderDate;

  final double deliveryEarnings;

  final double latitude;

  final double longitude;

  // ============================================================
  // ASSIGNMENT
  // ============================================================

  final String assignmentType;

  final bool isFinalMileRider;

  final bool isPickupRider;

  final bool isLinehaulRider;

  final String pickupRiderId;

  final String linehaulRiderId;

  final String linehaulRiderName;

  // ============================================================
  // BUYER / ORDER STATUS
  // ============================================================

  final String buyerOrderStatus;

  final String buyerDeliveryStatus;

  // ============================================================
  // HUB ROUTING / LINE-HAUL
  // ============================================================

  final String routingStatus;

  final int? originHubId;

  final String originHubName;

  final int? destinationHubId;

  final String destinationHubName;

  final int? currentHubId;

  final String currentHubName;

  final String linehaulStatus;

  final String requiredVehicleType;

  final double parcelWeightKg;

  final double estimatedDistanceKm;

  // ============================================================
  // SORTING CENTER
  // ============================================================

  final bool receivedBySortingCenter;

  final String sortingStatus;

  final String stagedAt;

  // ============================================================
  // PROOF OF DELIVERY
  // ============================================================

  final bool hasProofOfDelivery;

  final String proofOfDelivery;

  final String deliverySignature;

  final String deliveredTo;

  final String deliveryNotes;

  // ============================================================
  // RIDER LOCATION
  // ============================================================

  final double riderLatitude;

  final double riderLongitude;

  // ============================================================
  // DATES
  // ============================================================

  final String createdAt;

  final String updatedAt;

  final String pickedUpAt;

  final String hubPickedUpAt;

  final String originHubReceivedAt;

  final String transferDispatchedAt;

  final String destinationHubReceivedAt;

  final String linehaulAssignedAt;

  final String linehaulStartedAt;

  final String linehaulCompletedAt;

  final String deliveredAt;

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
    this.pickupAddress = '',
    this.assignmentType = '',
    this.isFinalMileRider = false,
    this.isPickupRider = false,
    this.isLinehaulRider = false,
    this.pickupRiderId = '',
    this.linehaulRiderId = '',
    this.linehaulRiderName = '',
    this.buyerOrderStatus = '',
    this.buyerDeliveryStatus = '',
    this.routingStatus = '',
    this.originHubId,
    this.originHubName = '',
    this.destinationHubId,
    this.destinationHubName = '',
    this.currentHubId,
    this.currentHubName = '',
    this.linehaulStatus = '',
    this.requiredVehicleType = '',
    this.parcelWeightKg = 0.0,
    this.estimatedDistanceKm = 0.0,
    this.receivedBySortingCenter = false,
    this.sortingStatus = '',
    this.stagedAt = '',
    this.hasProofOfDelivery = false,
    this.proofOfDelivery = '',
    this.deliverySignature = '',
    this.deliveredTo = '',
    this.deliveryNotes = '',
    this.riderLatitude = 0.0,
    this.riderLongitude = 0.0,
    this.createdAt = '',
    this.updatedAt = '',
    this.pickedUpAt = '',
    this.hubPickedUpAt = '',
    this.originHubReceivedAt = '',
    this.transferDispatchedAt = '',
    this.destinationHubReceivedAt = '',
    this.linehaulAssignedAt = '',
    this.linehaulStartedAt = '',
    this.linehaulCompletedAt = '',
    this.deliveredAt = '',
  });

  factory ParcelModel.fromJson(Map<String, dynamic> json) {
    return ParcelModel(
      // =========================================================
      // IDENTIFICATION
      // =========================================================

      id:
          int.tryParse(
            json['shipment_id']?.toString() ?? json['id']?.toString() ?? '',
          ) ??
          0,

      trackingNumber: _toString(json['tracking_number']),

      // =========================================================
      // CUSTOMER
      // =========================================================
      customerName: _toString(json['customer_name']),

      customerPhone: _toString(json['customer_phone']),

      // =========================================================
      // ADDRESS
      // =========================================================
      deliveryAddress: _toString(json['delivery_address']),

      pickupAddress: _toString(json['pickup_address']),

      // =========================================================
      // FINAL-MILE RIDER
      // =========================================================
      riderName: _toString(json['rider_name']),

      riderId: _toString(json['rider_id']),

      // =========================================================
      // SHIPMENT STATUS
      // =========================================================
      status: _toString(json['status']),

      orderDate: _toString(json['order_date']),

      deliveryEarnings: _toDouble(json['delivery_earnings']),

      // =========================================================
      // LOCATION
      // =========================================================
      latitude: _toDouble(json['latitude']),

      longitude: _toDouble(json['longitude']),

      riderLatitude: _toDouble(json['rider_latitude']),

      riderLongitude: _toDouble(json['rider_longitude']),

      // =========================================================
      // ASSIGNMENT
      // =========================================================
      assignmentType: _toString(json['assignment_type']),

      isFinalMileRider: _toBool(json['is_final_mile_rider']),

      isPickupRider: _toBool(json['is_pickup_rider']),

      isLinehaulRider: _toBool(json['is_linehaul_rider']),

      pickupRiderId: _toString(json['pickup_rider_id']),

      linehaulRiderId: _toString(json['linehaul_rider_id']),

      linehaulRiderName: _toString(json['linehaul_rider_name']),

      // =========================================================
      // BUYER / ORDER STATUS
      // =========================================================
      buyerOrderStatus: _toString(json['buyer_order_status']),

      buyerDeliveryStatus: _toString(json['buyer_delivery_status']),

      // =========================================================
      // HUB ROUTING / LINE-HAUL
      // =========================================================
      routingStatus: _toString(json['routing_status']),

      originHubId: _toNullableInt(json['origin_hub_id']),

      originHubName: _toString(json['origin_hub_name']),

      destinationHubId: _toNullableInt(json['destination_hub_id']),

      destinationHubName: _toString(json['destination_hub_name']),

      currentHubId: _toNullableInt(json['current_hub_id']),

      currentHubName: _toString(json['current_hub_name']),

      linehaulStatus: _toString(json['linehaul_status']),

      requiredVehicleType: _toString(json['required_vehicle_type']),

      parcelWeightKg: _toDouble(json['parcel_weight_kg']),

      estimatedDistanceKm: _toDouble(json['estimated_distance_km']),

      // =========================================================
      // SORTING CENTER
      // =========================================================
      receivedBySortingCenter: _toBool(json['received_by_sorting_center']),

      sortingStatus: _toString(json['sorting_status']),

      stagedAt: _toString(json['staged_at']),

      // =========================================================
      // PROOF OF DELIVERY
      // =========================================================
      hasProofOfDelivery: _toBool(json['has_proof_of_delivery']),

      proofOfDelivery: _toString(json['proof_of_delivery']),

      deliverySignature: _toString(json['delivery_signature']),

      deliveredTo: _toString(json['delivered_to']),

      deliveryNotes: _toString(json['delivery_notes']),

      // =========================================================
      // DATES
      // =========================================================
      createdAt: _toString(json['created_at']),

      updatedAt: _toString(json['updated_at']),

      pickedUpAt: _toString(json['picked_up_at']),

      hubPickedUpAt: _toString(json['hub_picked_up_at']),

      originHubReceivedAt: _toString(json['origin_hub_received_at']),

      transferDispatchedAt: _toString(json['transfer_dispatched_at']),

      destinationHubReceivedAt: _toString(json['destination_hub_received_at']),

      linehaulAssignedAt: _toString(json['linehaul_assigned_at']),

      linehaulStartedAt: _toString(json['linehaul_started_at']),

      linehaulCompletedAt: _toString(json['linehaul_completed_at']),

      deliveredAt: _toString(json['delivered_at']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      // =========================================================
      // IDENTIFICATION
      // =========================================================

      'id': id,
      'shipment_id': id,
      'tracking_number': trackingNumber,

      // =========================================================
      // CUSTOMER
      // =========================================================
      'customer_name': customerName,
      'customer_phone': customerPhone,

      // =========================================================
      // ADDRESS
      // =========================================================
      'delivery_address': deliveryAddress,
      'pickup_address': pickupAddress,

      // =========================================================
      // SHIPMENT
      // =========================================================
      'status': status,
      'order_date': orderDate,
      'delivery_earnings': deliveryEarnings,

      // =========================================================
      // ASSIGNMENT
      // =========================================================
      'assignment_type': assignmentType,
      'is_final_mile_rider': isFinalMileRider,
      'is_pickup_rider': isPickupRider,
      'is_linehaul_rider': isLinehaulRider,
      'pickup_rider_id': pickupRiderId,
      'linehaul_rider_id': linehaulRiderId,
      'linehaul_rider_name': linehaulRiderName,

      // =========================================================
      // BUYER / ORDER STATUS
      // =========================================================
      'buyer_order_status': buyerOrderStatus,
      'buyer_delivery_status': buyerDeliveryStatus,

      // =========================================================
      // HUB ROUTING / LINE-HAUL
      // =========================================================
      'routing_status': routingStatus,
      'origin_hub_id': originHubId,
      'origin_hub_name': originHubName,
      'destination_hub_id': destinationHubId,
      'destination_hub_name': destinationHubName,
      'current_hub_id': currentHubId,
      'current_hub_name': currentHubName,
      'linehaul_status': linehaulStatus,
      'required_vehicle_type': requiredVehicleType,
      'parcel_weight_kg': parcelWeightKg,
      'estimated_distance_km': estimatedDistanceKm,

      // =========================================================
      // SORTING CENTER
      // =========================================================
      'received_by_sorting_center': receivedBySortingCenter,
      'sorting_status': sortingStatus,
      'staged_at': stagedAt,

      // =========================================================
      // PROOF
      // =========================================================
      'has_proof_of_delivery': hasProofOfDelivery,
      'proof_of_delivery': proofOfDelivery,
      'delivery_signature': deliverySignature,
      'delivered_to': deliveredTo,
      'delivery_notes': deliveryNotes,

      // =========================================================
      // RIDER
      // =========================================================
      'rider_id': riderId,
      'rider_name': riderName,

      // =========================================================
      // LOCATION
      // =========================================================
      'latitude': latitude,
      'longitude': longitude,
      'rider_latitude': riderLatitude,
      'rider_longitude': riderLongitude,

      // =========================================================
      // DATES
      // =========================================================
      'created_at': createdAt,
      'updated_at': updatedAt,
      'picked_up_at': pickedUpAt,
      'hub_picked_up_at': hubPickedUpAt,
      'origin_hub_received_at': originHubReceivedAt,
      'transfer_dispatched_at': transferDispatchedAt,
      'destination_hub_received_at': destinationHubReceivedAt,
      'linehaul_assigned_at': linehaulAssignedAt,
      'linehaul_started_at': linehaulStartedAt,
      'linehaul_completed_at': linehaulCompletedAt,
      'delivered_at': deliveredAt,
    };
  }

  // ============================================================
  // LOCATION HELPERS
  // ============================================================

  bool get hasLocation {
    return latitude != 0.0 || longitude != 0.0;
  }

  bool get hasRiderLocation {
    return riderLatitude != 0.0 || riderLongitude != 0.0;
  }

  // ============================================================
  // STATUS HELPERS
  // ============================================================

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

  bool get isAtSortingCenter {
    return normalizedStatus == 'at_sorting_center';
  }

  bool get isOutForDelivery {
    return normalizedStatus == 'out_for_delivery';
  }

  bool get isInTransit {
    return normalizedStatus == 'in_transit';
  }

  bool get isStaged {
    return normalizedSortingStatus == 'staged' || stagedAt.trim().isNotEmpty;
  }

  // ============================================================
  // ASSIGNMENT HELPERS
  // ============================================================

  bool get isSellerPickupAssignment {
    if (isPickupRider) {
      return true;
    }

    return normalizedAssignmentType == 'seller_pickup' ||
        normalizedAssignmentType == 'pickup_and_delivery' ||
        normalizedAssignmentType == 'pickup_and_linehaul' ||
        normalizedAssignmentType == 'pickup_linehaul_and_delivery';
  }

  bool get isLinehaulAssignment {
    if (isLinehaulRider) {
      return true;
    }

    return normalizedAssignmentType == 'linehaul' ||
        normalizedAssignmentType == 'pickup_and_linehaul' ||
        normalizedAssignmentType == 'linehaul_and_delivery' ||
        normalizedAssignmentType == 'pickup_linehaul_and_delivery';
  }

  bool get isFinalMileAssignment {
    if (isFinalMileRider) {
      return true;
    }

    return normalizedAssignmentType == 'final_mile' ||
        normalizedAssignmentType == 'pickup_and_delivery' ||
        normalizedAssignmentType == 'linehaul_and_delivery' ||
        normalizedAssignmentType == 'pickup_linehaul_and_delivery';
  }

  // ============================================================
  // LINE-HAUL HELPERS
  // ============================================================

  bool get requiresHubTransfer {
    if (originHubId == null || destinationHubId == null) {
      return false;
    }

    return originHubId != destinationHubId;
  }

  bool get isLinehaulAwaitingAssignment {
    return normalizedLinehaulStatus == 'awaiting_assignment';
  }

  bool get isLinehaulAssigned {
    return normalizedLinehaulStatus == 'assigned';
  }

  bool get isLinehaulInTransit {
    return normalizedLinehaulStatus == 'in_transit';
  }

  bool get isLinehaulCompleted {
    return normalizedLinehaulStatus == 'completed';
  }

  bool get isLinehaulNotRequired {
    return normalizedLinehaulStatus == 'not_required';
  }

  // ============================================================
  // ROUTING HELPERS
  // ============================================================

  bool get isAtOriginHub {
    return normalizedRoutingStatus == 'at_origin_hub';
  }

  bool get isInHubTransfer {
    return normalizedRoutingStatus == 'in_hub_transfer';
  }

  bool get isAtDestinationHub {
    return normalizedRoutingStatus == 'at_destination_hub';
  }

  // ============================================================
  // NORMALIZED VALUES
  // ============================================================

  String get normalizedStatus {
    return _normalize(status);
  }

  String get normalizedAssignmentType {
    return _normalize(assignmentType);
  }

  String get normalizedLinehaulStatus {
    return _normalize(linehaulStatus);
  }

  String get normalizedRoutingStatus {
    return _normalize(routingStatus);
  }

  String get normalizedSortingStatus {
    return _normalize(sortingStatus);
  }

  // ============================================================
  // DISPLAY HELPERS
  // ============================================================

  String get assignmentLabel {
    if (isLinehaulAssignment &&
        isSellerPickupAssignment &&
        isFinalMileAssignment) {
      return 'Pickup / Line Haul / Final Mile';
    }

    if (isSellerPickupAssignment && isLinehaulAssignment) {
      return 'Seller Pickup / Line Haul';
    }

    if (isLinehaulAssignment && isFinalMileAssignment) {
      return 'Line Haul / Final Mile';
    }

    if (isSellerPickupAssignment && isFinalMileAssignment) {
      return 'Seller Pickup / Final Mile';
    }

    if (isLinehaulAssignment) {
      return 'Line Haul';
    }

    if (isSellerPickupAssignment) {
      return 'Seller Pickup';
    }

    if (isFinalMileAssignment) {
      return 'Final Mile';
    }

    return 'Rider Assignment';
  }

  String get routeLabel {
    final origin = originHubName.trim();
    final destination = destinationHubName.trim();

    if (origin.isNotEmpty && destination.isNotEmpty) {
      return '$origin → $destination';
    }

    if (origin.isNotEmpty) {
      return origin;
    }

    if (destination.isNotEmpty) {
      return destination;
    }

    return '';
  }

  // ============================================================
  // PRIVATE PARSERS
  // ============================================================

  static String _normalize(String value) {
    return value.trim().toLowerCase().replaceAll('-', '_').replaceAll(' ', '_');
  }

  static String _toString(dynamic value) {
    if (value == null) {
      return '';
    }

    final text = value.toString().trim();

    if (text.toLowerCase() == 'null') {
      return '';
    }

    return text;
  }

  static bool _toBool(dynamic value) {
    if (value == null) {
      return false;
    }

    if (value is bool) {
      return value;
    }

    if (value is num) {
      return value != 0;
    }

    final text = value.toString().trim().toLowerCase();

    return text == 'true' || text == '1' || text == 'yes' || text == 'y';
  }

  static int? _toNullableInt(dynamic value) {
    if (value == null) {
      return null;
    }

    final text = value.toString().trim();

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return null;
    }

    return int.tryParse(text);
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
