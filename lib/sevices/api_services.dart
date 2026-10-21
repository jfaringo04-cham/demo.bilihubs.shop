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
        final dynamic productData = decoded['data'];

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
