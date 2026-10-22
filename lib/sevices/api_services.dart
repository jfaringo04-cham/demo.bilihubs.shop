import 'dart:convert';

import 'package:http/http.dart' as http;

import 'auth_storage.dart';

class ApiService {
  static const String baseUrl = 'https://demo-bilihubs-shop.onrender.com/api';

  // ============================================================
  // TEST CONNECTION
  // ============================================================

  static Future<Map<String, dynamic>> testConnection() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/test'),
        headers: {'Accept': 'application/json'},
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        if (decoded is Map<String, dynamic>) {
          return {'success': true, ...decoded};
        }

        return {'success': true, 'data': decoded};
      }

      return {
        'success': false,
        'status_code': response.statusCode,
        'message': _getMessage(
          decoded,
          fallback: 'Server returned status code ${response.statusCode}',
        ),
      };
    } catch (e) {
      return {'success': false, 'message': 'Unable to connect to server: $e'};
    }
  }

  // ============================================================
  // LOGIN
  // ============================================================

  static Future<Map<String, dynamic>> login({
    required String email,
    required String password,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/auth/login'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'email': email,
          'password': password,
          'device_name': 'BiliHub Android',
        }),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map<String, dynamic>) {
        return {
          'success': true,
          'user': decoded['user'],
          'token': decoded['token'],
          'token_type': decoded['token_type'] ?? 'Bearer',
        };
      }

      return {
        'success': false,
        'status_code': response.statusCode,
        'message': _getMessage(decoded, fallback: 'Login failed.'),
        'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
      };
    } catch (e) {
      return {'success': false, 'message': 'Unable to connect to server: $e'};
    }
  }

  // ============================================================
  // REGISTER BUYER
  // ============================================================

  static Future<Map<String, dynamic>> registerBuyer({
    required String firstName,
    required String lastName,
    required String email,
    required String phone,
    required String password,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/auth/register'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'name': '$firstName $lastName'.trim(),
          'first_name': firstName,
          'last_name': lastName,
          'email': email,
          'mobile_number': phone,
          'password': password,
          'password_confirmation': password,
          'role': 'buyer',
        }),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if ((response.statusCode == 200 || response.statusCode == 201) &&
          decoded is Map<String, dynamic>) {
        return {
          'success': true,
          'message': _getMessage(
            decoded,
            fallback: 'Registration submitted successfully.',
          ),
          'user': decoded['user'],
        };
      }

      return {
        'success': false,
        'status_code': response.statusCode,
        'message': _getValidationMessage(
          decoded,
          fallback: 'Registration failed.',
        ),
        'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to register account: $e',
      };
    }
  }

  // ============================================================
  // FORGOT PASSWORD
  // ============================================================

  static Future<Map<String, dynamic>> forgotPassword({
    required String email,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/auth/forgot-password'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({'email': email}),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode >= 200 && response.statusCode < 300) {
        return {
          'success': true,
          'message': _getMessage(
            decoded,
            fallback: 'Password reset link sent to your email.',
          ),
        };
      }

      return {
        'success': false,
        'status_code': response.statusCode,
        'message': _getValidationMessage(
          decoded,
          fallback: 'Unable to send password reset link.',
        ),
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to send password reset link: $e',
      };
    }
  }

  // ============================================================
  // CURRENT USER / PROFILE
  // ============================================================

  static Future<Map<String, dynamic>> getCurrentUser() async {
    try {
      final token = await AuthStorage.getToken();
      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final response = await http.get(
        Uri.parse('$baseUrl/auth/user'),
        headers: _authHeaders(token),
      );
      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map<String, dynamic>) {
        final dynamic user = decoded['user'];
        if (user is Map) {
          return {
            'success': true,
            'user': Map<String, dynamic>.from(user),
          };
        }
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'This account cannot access this profile.',
        notFoundMessage: 'Profile not found.',
        fallback: 'Unable to load profile.',
      );
    } catch (e) {
      return {'success': false, 'message': 'Unable to load profile: $e'};
    }
  }

  static Future<Map<String, dynamic>> updateProfile({
    required String name,
    required String mobileNumber,
  }) async {
    try {
      final token = await AuthStorage.getToken();
      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final response = await http.put(
        Uri.parse('$baseUrl/auth/profile'),
        headers: _authHeaders(token, json: true),
        body: jsonEncode({
          'name': name,
          'mobile_number': mobileNumber,
        }),
      );
      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode >= 200 &&
          response.statusCode < 300 &&
          decoded is Map<String, dynamic>) {
        final dynamic user = decoded['user'];
        return {
          'success': true,
          'message': _getMessage(decoded, fallback: 'Profile updated.'),
          if (user is Map) 'user': Map<String, dynamic>.from(user),
        };
      }

      return {
        ..._authApiError(
          response.statusCode,
          decoded,
          forbiddenMessage: 'You cannot update this profile.',
          notFoundMessage: 'Profile not found.',
          fallback: 'Unable to update profile.',
        ),
        'message': _getValidationMessage(
          decoded,
          fallback: 'Unable to update profile.',
        ),
      };
    } catch (e) {
      return {'success': false, 'message': 'Unable to update profile: $e'};
    }
  }

  static Future<Map<String, dynamic>> logout() async {
    try {
      final token = await AuthStorage.getToken();
      if (token == null || token.trim().isEmpty) {
        return {'success': true, 'message': 'Logged out.'};
      }

      final response = await http.post(
        Uri.parse('$baseUrl/auth/logout'),
        headers: _authHeaders(token),
      );
      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode >= 200 && response.statusCode < 300) {
        return {
          'success': true,
          'message': _getMessage(decoded, fallback: 'Logged out successfully.'),
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'Unable to logout this account.',
        notFoundMessage: 'Session not found.',
        fallback: 'Unable to logout.',
      );
    } catch (e) {
      return {'success': false, 'message': 'Unable to logout: $e'};
    }
  }

  // ============================================================
  // BUYER ADDRESSES
  // ============================================================

  static Future<Map<String, dynamic>> getAddresses() async {
    try {
      final token = await AuthStorage.getToken();
      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final response = await http.get(
        Uri.parse('$baseUrl/addresses'),
        headers: _authHeaders(token),
      );
      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        return {
          'success': true,
          'data': _extractList(decoded, keys: const ['data', 'addresses']),
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'Unable to access your addresses.',
        notFoundMessage: 'No saved addresses found.',
        fallback: 'Unable to load addresses.',
      );
    } catch (e) {
      return {'success': false, 'message': 'Unable to load addresses: $e'};
    }
  }

  static Future<Map<String, dynamic>> saveAddress({
    int? addressId,
    required String addressLine1,
    required String city,
    required String province,
    required String phone,
    String postalCode = '',
    String label = 'Home',
    bool isDefault = true,
  }) async {
    try {
      final token = await AuthStorage.getToken();
      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final uri = addressId == null
          ? Uri.parse('$baseUrl/addresses')
          : Uri.parse('$baseUrl/addresses/$addressId');

      final body = jsonEncode({
        'label': label,
        'address_line1': addressLine1,
        'city': city,
        'province': province,
        if (postalCode.trim().isNotEmpty) 'postal_code': postalCode.trim(),
        'country': 'Philippines',
        'phone': phone,
        'is_default': isDefault,
      });

      final response = addressId == null
          ? await http.post(uri, headers: _authHeaders(token, json: true), body: body)
          : await http.put(uri, headers: _authHeaders(token, json: true), body: body);

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode >= 200 &&
          response.statusCode < 300 &&
          decoded is Map<String, dynamic>) {
        final dynamic data = decoded['data'];
        return {
          'success': true,
          'message': _getMessage(decoded, fallback: 'Address saved.'),
          if (data is Map) 'data': Map<String, dynamic>.from(data),
        };
      }

      return {
        'success': false,
        'status_code': response.statusCode,
        'message': _getValidationMessage(
          decoded,
          fallback: 'Unable to save delivery address.',
        ),
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to save delivery address: $e',
      };
    }
  }

  // ============================================================
  // BUYER ORDERS
  // ============================================================

  static Future<Map<String, dynamic>> placeOrder({
    required int addressId,
    required List<Map<String, dynamic>> items,
    String paymentMethod = 'cod',
    String? notes,
  }) async {
    try {
      final token = await AuthStorage.getToken();
      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final response = await http.post(
        Uri.parse('$baseUrl/orders'),
        headers: _authHeaders(token, json: true),
        body: jsonEncode({
          'address_id': addressId,
          'payment_method': paymentMethod,
          'items': items,
          if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
        }),
      );
      final dynamic decoded = _decodeResponse(response.body);

      if ((response.statusCode == 200 || response.statusCode == 201) &&
          decoded is Map<String, dynamic>) {
        return {
          'success': true,
          'message': _getMessage(decoded, fallback: 'Order placed successfully.'),
          'data': decoded['data'],
        };
      }

      return {
        'success': false,
        'status_code': response.statusCode,
        'message': _getValidationMessage(
          decoded,
          fallback: 'Unable to place order.',
        ),
      };
    } catch (e) {
      return {'success': false, 'message': 'Unable to place order: $e'};
    }
  }

  static Future<Map<String, dynamic>> getOrders({
    String? status,
    int perPage = 50,
  }) async {
    try {
      final token = await AuthStorage.getToken();
      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final query = <String, String>{'per_page': perPage.toString()};
      if (status != null && status.trim().isNotEmpty) {
        query['status'] = status.trim();
      }

      final uri = Uri.parse('$baseUrl/orders').replace(queryParameters: query);
      final response = await http.get(uri, headers: _authHeaders(token));
      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        return {
          'success': true,
          'data': _extractList(decoded, keys: const ['data', 'orders']),
          if (decoded is Map<String, dynamic>) 'meta': decoded['meta'],
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'Unable to access your orders.',
        notFoundMessage: 'No orders found.',
        fallback: 'Unable to load orders.',
      );
    } catch (e) {
      return {'success': false, 'message': 'Unable to load orders: $e'};
    }
  }

  static Future<Map<String, dynamic>> getOrder(int orderId) async {
    try {
      final token = await AuthStorage.getToken();
      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final response = await http.get(
        Uri.parse('$baseUrl/orders/$orderId'),
        headers: _authHeaders(token),
      );
      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map<String, dynamic>) {
        final dynamic data = decoded['data'];
        if (data is Map) {
          return {'success': true, 'data': Map<String, dynamic>.from(data)};
        }
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'You cannot access this order.',
        notFoundMessage: 'Order not found.',
        fallback: 'Unable to load order.',
      );
    } catch (e) {
      return {'success': false, 'message': 'Unable to load order: $e'};
    }
  }

  static Future<Map<String, dynamic>> getOrderShipments(int orderId) async {
    try {
      final token = await AuthStorage.getToken();
      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final response = await http.get(
        Uri.parse('$baseUrl/orders/$orderId/shipment'),
        headers: _authHeaders(token),
      );
      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map<String, dynamic>) {
        final dynamic data = decoded['data'];
        if (data is Map) {
          return {'success': true, 'data': Map<String, dynamic>.from(data)};
        }
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'You cannot access this shipment.',
        notFoundMessage: 'No shipment is available for this order yet.',
        fallback: 'Unable to load shipment tracking.',
      );
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to load shipment tracking: $e',
      };
    }
  }

  // ============================================================
  // RIDER DASHBOARD
  // ============================================================

  static Future<Map<String, dynamic>> getRiderDashboard() async {
    try {
      final token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final response = await http.get(
        Uri.parse('$baseUrl/rider/dashboard'),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map<String, dynamic>) {
        return {
          'success': true,

          'rider': decoded['rider'] is Map
              ? Map<String, dynamic>.from(decoded['rider'])
              : <String, dynamic>{},

          'overview': decoded['overview'] is Map
              ? Map<String, dynamic>.from(decoded['overview'])
              : <String, dynamic>{},

          'tasks': decoded['tasks'] is List
              ? List<dynamic>.from(decoded['tasks'])
              : <dynamic>[],
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'This account cannot access the rider dashboard.',
        notFoundMessage: 'Rider dashboard API is not available yet.',
        fallback: 'Unable to load rider dashboard.',
      );
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to load rider dashboard: $e',
      };
    }
  }

  // ============================================================
  // RIDER DELIVERIES
  //
  // GET /api/rider/orders
  // ============================================================

  static Future<Map<String, dynamic>> getRiderDeliveries() async {
    try {
      final token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final response = await http.get(
        Uri.parse('$baseUrl/rider/orders'),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        return {
          'success': true,

          'data': _extractList(
            decoded,
            keys: const ['data', 'deliveries', 'orders', 'shipments'],
          ),
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'This account cannot access rider deliveries.',
        notFoundMessage: 'Rider deliveries API is not available yet.',
        fallback: 'Unable to load rider deliveries.',
      );
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to load rider deliveries: $e',
      };
    }
  }

  // ============================================================
  // GET SINGLE RIDER DELIVERY
  //
  // GET /api/rider/orders/{shipment}
  //
  // IMPORTANT:
  // shipmentId is the Shipment ID.
  // ============================================================

  static Future<Map<String, dynamic>> getRiderDelivery(int shipmentId) async {
    try {
      if (shipmentId <= 0) {
        return {'success': false, 'message': 'Invalid shipment ID.'};
      }

      final token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final response = await http.get(
        Uri.parse('$baseUrl/rider/orders/$shipmentId'),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        if (decoded is Map<String, dynamic>) {
          final dynamic rawData = decoded['data'];

          if (rawData is Map) {
            return {
              'success': true,
              'data': Map<String, dynamic>.from(rawData),
            };
          }

          return {'success': true, 'data': Map<String, dynamic>.from(decoded)};
        }

        return {
          'success': false,
          'message': 'Invalid shipment response from server.',
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'This shipment is not assigned to this rider.',
        notFoundMessage: 'Rider shipment was not found.',
        fallback: 'Unable to load rider shipment.',
      );
    } catch (e) {
      return {'success': false, 'message': 'Unable to load rider shipment: $e'};
    }
  }

  // ============================================================
  // RIDER WAYBILL QR SCANNER
  //
  // POST /api/rider/scan
  //
  // qrPayload must be the raw QR value scanned from the website
  // waybill.
  //
  // Laravel QR format:
  //
  // {
  //   "t": tracking_number,
  //   "q": qr_token,
  //   "so": seller_order_id
  // }
  // ============================================================

  static Future<Map<String, dynamic>> scanRiderWaybill({
    required String qrPayload,
  }) async {
    try {
      final token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final normalizedPayload = qrPayload.trim();

      if (normalizedPayload.isEmpty) {
        return {'success': false, 'message': 'QR code is empty.'};
      }

      final response = await http.post(
        Uri.parse('$baseUrl/rider/scan'),
        headers: _authHeaders(token, json: true),
        body: jsonEncode({'qr_payload': normalizedPayload}),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        if (decoded is Map<String, dynamic>) {
          final dynamic rawShipment = decoded['shipment'];

          return {
            'success': true,

            'message': _getMessage(
              decoded,
              fallback: 'Waybill scanned successfully.',
            ),

            'scan_type': decoded['scan_type']?.toString() ?? '',

            'shipment': rawShipment is Map
                ? Map<String, dynamic>.from(rawShipment)
                : <String, dynamic>{},
          };
        }

        return {
          'success': true,
          'message': 'Waybill scanned successfully.',
          'scan_type': '',
          'shipment': <String, dynamic>{},
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,

          'message': _getMessage(
            decoded,
            fallback: 'The scanned QR code could not be processed.',
          ),

          'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'This parcel is not assigned to you or cannot be scanned by this rider.',
        notFoundMessage: 'The shipment for this QR code was not found.',
        fallback: 'Unable to process the scanned waybill.',
      );
    } catch (e) {
      return {'success': false, 'message': 'Unable to scan waybill: $e'};
    }
  }

  // ============================================================
  // RIDER EARNINGS
  //
  // NOTE:
  // Backend endpoint is not yet enabled.
  // Keep this method for existing Flutter screens.
  // ============================================================

  static Future<Map<String, dynamic>> getRiderEarnings() async {
    try {
      final token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final response = await http.get(
        Uri.parse('$baseUrl/rider/earnings'),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        if (decoded is Map<String, dynamic>) {
          final dynamic earningsData = decoded['data'] ?? decoded;

          return {
            'success': true,

            'data': earningsData is Map
                ? Map<String, dynamic>.from(earningsData)
                : <String, dynamic>{},
          };
        }

        return {'success': true, 'data': <String, dynamic>{}};
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'This account cannot access rider earnings.',
        notFoundMessage: 'Rider earnings API is not available yet.',
        fallback: 'Unable to load rider earnings.',
      );
    } catch (e) {
      return {'success': false, 'message': 'Unable to load rider earnings: $e'};
    }
  }

  // ============================================================
  // UPDATE RIDER DELIVERY STATUS
  //
  // NOTE:
  // Backend endpoint is not yet enabled.
  //
  // Kept so existing Flutter screens still compile.
  // ============================================================

  static Future<Map<String, dynamic>> updateRiderDeliveryStatus({
    required int orderId,
    required String status,
  }) async {
    try {
      final token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      if (orderId <= 0) {
        return {'success': false, 'message': 'Invalid delivery ID.'};
      }

      final normalizedStatus = status.trim();

      if (normalizedStatus.isEmpty) {
        return {'success': false, 'message': 'Delivery status is required.'};
      }

      final response = await http.put(
        Uri.parse('$baseUrl/rider/orders/$orderId/status'),
        headers: _authHeaders(token, json: true),
        body: jsonEncode({'status': normalizedStatus}),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        return {'success': true, 'data': decoded};
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,

          'message': _getMessage(
            decoded,
            fallback: 'The delivery status could not be updated.',
          ),

          'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'This account cannot update this delivery.',
        notFoundMessage: 'Delivery status API is not available yet.',
        fallback: 'Unable to update delivery status.',
      );
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to update delivery status: $e',
      };
    }
  }

  // ============================================================
  // LOGISTICS DASHBOARD
  //
  // Existing method preserved.
  // Backend API is still TODO.
  // ============================================================

  static Future<Map<String, dynamic>> getLogisticsDashboard() async {
    try {
      final token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final response = await http.get(
        Uri.parse('$baseUrl/logistic/dashboard'),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map<String, dynamic>) {
        final dynamic logisticsData =
            decoded['logistics'] ?? decoded['logistic'];

        return {
          'success': true,

          'logistics': logisticsData is Map
              ? Map<String, dynamic>.from(logisticsData)
              : <String, dynamic>{},

          'overview': decoded['overview'] is Map
              ? Map<String, dynamic>.from(decoded['overview'])
              : <String, dynamic>{},

          'activity': decoded['activity'] is Map
              ? Map<String, dynamic>.from(decoded['activity'])
              : <String, dynamic>{},
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'This account cannot access the logistics dashboard.',
        notFoundMessage: 'Logistics dashboard API is not available yet.',
        fallback: 'Unable to load logistics dashboard.',
      );
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to load logistics dashboard: $e',
      };
    }
  }

  // ============================================================
  // LOGISTICS SHIPMENTS
  //
  // Existing method preserved.
  // Backend API is still TODO.
  // ============================================================

  static Future<Map<String, dynamic>> getLogisticsShipments() async {
    try {
      final token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      final response = await http.get(
        Uri.parse('$baseUrl/logistic/shipments'),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        return {
          'success': true,

          'data': _extractList(
            decoded,
            keys: const ['data', 'shipments', 'parcels'],
          ),
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'This account cannot access logistics shipments.',
        notFoundMessage: 'Logistics shipments API is not available yet.',
        fallback: 'Unable to load logistics shipments.',
      );
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to load logistics shipments: $e',
      };
    }
  }

  // ============================================================
  // PROCESS LOGISTICS SHIPMENT
  //
  // Existing method preserved.
  // Backend API is still TODO.
  // ============================================================

  static Future<Map<String, dynamic>> processLogisticsShipment({
    required int shipmentId,
    required String action,
  }) async {
    try {
      final token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {'success': false, 'message': 'Authentication token not found.'};
      }

      const allowedActions = {'receive', 'scan', 'sort', 'stage'};

      final normalizedAction = action.trim().toLowerCase();

      if (!allowedActions.contains(normalizedAction)) {
        return {'success': false, 'message': 'Invalid logistics action.'};
      }

      final response = await http.post(
        Uri.parse(
          '$baseUrl/logistic/shipments/'
          '$shipmentId/$normalizedAction',
        ),
        headers: _authHeaders(token, json: true),
        body: jsonEncode({}),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        String message = 'Parcel updated successfully.';

        if (decoded is Map<String, dynamic> && decoded['message'] != null) {
          message = decoded['message'].toString();
        }

        return {'success': true, 'message': message, 'data': decoded};
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,

          'message': _getMessage(
            decoded,
            fallback: 'This parcel cannot perform that logistics action.',
          ),

          'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'This account cannot process this shipment.',
        notFoundMessage:
            'Logistics parcel processing API is not available yet.',
        fallback: 'Unable to process logistics shipment.',
      );
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to process logistics shipment: $e',
      };
    }
  }

  // ============================================================
  // GET PRODUCTS
  // ============================================================

  static Future<Map<String, dynamic>> getProducts({
    String? search,
    int? categoryId,
    double? minPrice,
    double? maxPrice,
    int? sizeId,
    String? sortBy,
    String? sortOrder,
    int page = 1,
    int perPage = 100,
  }) async {
    try {
      final Map<String, String> queryParameters = {
        'page': page.toString(),
        'per_page': perPage.toString(),
      };

      if (search != null && search.trim().isNotEmpty) {
        queryParameters['search'] = search.trim();
      }

      if (categoryId != null && categoryId > 0) {
        queryParameters['category_id'] = categoryId.toString();
      }

      if (minPrice != null) {
        queryParameters['min_price'] = minPrice.toString();
      }

      if (maxPrice != null) {
        queryParameters['max_price'] = maxPrice.toString();
      }

      if (sizeId != null && sizeId > 0) {
        queryParameters['size_id'] = sizeId.toString();
      }

      if (sortBy != null && sortBy.trim().isNotEmpty) {
        queryParameters['sort_by'] = sortBy.trim();
      }

      if (sortOrder != null && sortOrder.trim().isNotEmpty) {
        queryParameters['sort_order'] = sortOrder.trim();
      }

      final uri = Uri.parse('$baseUrl/products')
          .replace(queryParameters: queryParameters);

      final response = await http.get(
        uri,
        headers: {'Accept': 'application/json'},
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        return {
          'success': true,

          'data': _extractList(decoded, keys: const ['data', 'products']),

          'meta': decoded is Map<String, dynamic> && decoded['meta'] is Map
              ? Map<String, dynamic>.from(decoded['meta'])
              : <String, dynamic>{},
        };
      }

      return {
        'success': false,
        'status_code': response.statusCode,

        'message': _getMessage(decoded, fallback: 'Failed to load products.'),
      };
    } catch (e) {
      return {'success': false, 'message': 'Unable to load products: $e'};
    }
  }

  // ============================================================
  // GET SINGLE PRODUCT
  // ============================================================

  static Future<Map<String, dynamic>> getProduct(int productId) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/products/$productId'),
        headers: {'Accept': 'application/json'},
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map<String, dynamic>) {
        final dynamic productData = decoded['data'] ?? decoded;

        if (productData is Map<String, dynamic>) {
          return {'success': true, 'data': productData};
        }

        if (productData is Map) {
          return {
            'success': true,
            'data': Map<String, dynamic>.from(productData),
          };
        }
      }

      return {
        'success': false,
        'status_code': response.statusCode,

        'message': _getMessage(
          decoded,
          fallback: 'Unable to load product details.',
        ),
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to load product details: $e',
      };
    }
  }

  // ============================================================
  // GET PRODUCT CATEGORIES
  // ============================================================

  static Future<Map<String, dynamic>> getCategories() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/products/categories'),
        headers: {'Accept': 'application/json'},
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        return {
          'success': true,

          'data': _extractList(decoded, keys: const ['data', 'categories']),
        };
      }

      return {
        'success': false,
        'status_code': response.statusCode,

        'message': _getMessage(decoded, fallback: 'Unable to load categories.'),
      };
    } catch (e) {
      return {'success': false, 'message': 'Unable to load categories: $e'};
    }
  }

  // ============================================================
  // GET PRODUCT SIZES
  // ============================================================

  static Future<Map<String, dynamic>> getSizes() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/products/sizes'),
        headers: {'Accept': 'application/json'},
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        return {
          'success': true,

          'data': _extractList(decoded, keys: const ['data', 'sizes']),
        };
      }

      return {
        'success': false,
        'status_code': response.statusCode,

        'message': _getMessage(decoded, fallback: 'Unable to load sizes.'),
      };
    } catch (e) {
      return {'success': false, 'message': 'Unable to load sizes: $e'};
    }
  }

  // ============================================================
  // AUTH HEADERS
  // ============================================================

  static Map<String, String> _authHeaders(String token, {bool json = false}) {
    return {
      'Accept': 'application/json',

      if (json) 'Content-Type': 'application/json',

      'Authorization': 'Bearer $token',
    };
  }

  // ============================================================
  // EXTRACT LIST
  // ============================================================

  static List<dynamic> _extractList(
    dynamic decoded, {
    required List<String> keys,
  }) {
    if (decoded is List) {
      return List<dynamic>.from(decoded);
    }

    if (decoded is! Map<String, dynamic>) {
      return <dynamic>[];
    }

    for (final key in keys) {
      final dynamic value = decoded[key];

      if (value is List) {
        return List<dynamic>.from(value);
      }

      if (value is Map) {
        final dynamic nested = value['data'];

        if (nested is List) {
          return List<dynamic>.from(nested);
        }
      }
    }

    return <dynamic>[];
  }

  // ============================================================
  // AUTH API ERROR
  // ============================================================

  static Map<String, dynamic> _authApiError(
    int statusCode,
    dynamic decoded, {
    required String forbiddenMessage,
    required String notFoundMessage,
    required String fallback,
  }) {
    if (statusCode == 401) {
      return {
        'success': false,
        'status_code': 401,
        'message': 'Session expired. Please login again.',
      };
    }

    if (statusCode == 403) {
      return {
        'success': false,
        'status_code': 403,
        'message': _getMessage(decoded, fallback: forbiddenMessage),
      };
    }

    if (statusCode == 404) {
      return {
        'success': false,
        'status_code': 404,
        'message': _getMessage(decoded, fallback: notFoundMessage),
      };
    }

    return {
      'success': false,

      'status_code': statusCode,

      'message': _getMessage(decoded, fallback: fallback),
    };
  }

  // ============================================================
  // JSON DECODER
  // ============================================================

  static dynamic _decodeResponse(String body) {
    if (body.trim().isEmpty) {
      return null;
    }

    try {
      return jsonDecode(body);
    } catch (_) {
      return body;
    }
  }

  // ============================================================
  // VALIDATION / ERROR MESSAGE
  // ============================================================

  static String _getValidationMessage(dynamic data, {required String fallback}) {
    if (data is Map<String, dynamic>) {
      final dynamic message = data['message'];
      if (message != null && message.toString().trim().isNotEmpty &&
          message.toString().trim().toLowerCase() != 'validation failed.') {
        return message.toString().trim();
      }

      final dynamic errors = data['errors'];
      if (errors is Map) {
        for (final dynamic value in errors.values) {
          if (value is List && value.isNotEmpty) {
            return value.first.toString();
          }
          if (value != null && value.toString().trim().isNotEmpty) {
            return value.toString();
          }
        }
      }
    }

    return fallback;
  }

  // ============================================================
  // ERROR MESSAGE
  // ============================================================

  static String _getMessage(dynamic data, {required String fallback}) {
    if (data is Map<String, dynamic>) {
      final dynamic message = data['message'];

      if (message != null && message.toString().trim().isNotEmpty) {
        return message.toString();
      }
    }

    return fallback;
  }
}
