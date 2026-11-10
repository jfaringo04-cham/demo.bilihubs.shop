import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
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
          'email': email.trim().toLowerCase(),
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
  // BUYER REGISTRATION
  // POST /api/auth/register
  // ============================================================

  static Future<Map<String, dynamic>> registerBuyer({
    required String firstName,
    required String lastName,
    required String email,
    required String phone,
    required String birthday,
    required int age,
    required String address,
    required String password,
    required String passwordConfirmation,
  }) async {
    try {
      final String normalizedFirstName = firstName.trim();
      final String normalizedLastName = lastName.trim();
      final String normalizedEmail = email.trim().toLowerCase();
      final String normalizedPhone = phone.trim();
      final String normalizedBirthday = birthday.trim();
      final String normalizedAddress = address.trim();

      final String fullName = [
        normalizedFirstName,
        normalizedLastName,
      ].where((part) => part.isNotEmpty).join(' ');

      final response = await http.post(
        Uri.parse('$baseUrl/auth/register'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'name': fullName,
          'email': normalizedEmail,
          'password': password,
          'password_confirmation': passwordConfirmation,
          'mobile_number': normalizedPhone,
          'role': 'buyer',
          'first_name': normalizedFirstName,
          'last_name': normalizedLastName,
          'birthday': normalizedBirthday,
          'age': age,
          'address': normalizedAddress,
        }),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if ((response.statusCode == 200 || response.statusCode == 201) &&
          decoded is Map<String, dynamic>) {
        return {
          'success': true,
          'status_code': response.statusCode,
          'message': _getMessage(
            decoded,
            fallback:
                'Registration submitted successfully. '
                'Your account is pending approval.',
          ),
          'user': decoded['user'],
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,
          'message': _getMessage(
            decoded,
            fallback: 'Please check your registration information.',
          ),
          'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
        };
      }

      if (response.statusCode == 429) {
        return {
          'success': false,
          'status_code': 429,
          'message':
              'Too many registration attempts. '
              'Please wait and try again.',
        };
      }

      if (response.statusCode >= 500) {
        return {
          'success': false,
          'status_code': response.statusCode,
          'message':
              'The BiliHub server is temporarily unavailable. '
              'Please try again.',
        };
      }

      return {
        'success': false,
        'status_code': response.statusCode,
        'message': _getMessage(
          decoded,
          fallback:
              'Unable to create your account. '
              'Please try again.',
        ),
        'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
      };
    } catch (_) {
      return {
        'success': false,
        'message':
            'Unable to connect to the server. '
            'Please check your internet connection '
            'and try again.',
      };
    }
  }

  // ============================================================
  // LOGISTICS REGISTRATION
  // POST /api/auth/register
  // ============================================================

  static Future<Map<String, dynamic>> registerLogistics({
    required String companyName,
    required String secRegistrationNumber,
    required String tin,
    required String region,
    required String province,
    required String city,
    required String barangay,
    required String streetAddress,
    required String postalCode,
    required String contactFirstName,
    required String contactLastName,
    required String position,
    required String email,
    required String mobileNumber,
    required List<String> serviceAreas,
    required String password,
    required String passwordConfirmation,
    required String secCertificateFilePath,
    required String bir2303FilePath,
    required String mayorsPermitFilePath,
    required String representativeIdFilePath,
  }) async {
    try {
      final String firstName = contactFirstName.trim();
      final String lastName = contactLastName.trim();

      final List<String> normalizedAreas = serviceAreas
          .map((String area) => area.trim())
          .where((String area) => area.isNotEmpty)
          .toSet()
          .toList();

      final http.MultipartRequest request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/auth/register'),
      );

      request.headers.addAll({'Accept': 'application/json'});

      request.fields.addAll({
        'name': [
          firstName,
          lastName,
        ].where((part) => part.isNotEmpty).join(' '),
        'first_name': firstName,
        'last_name': lastName,
        'email': email.trim().toLowerCase(),
        'mobile_number': mobileNumber.trim(),
        'password': password,
        'password_confirmation': passwordConfirmation,
        'role': 'logistics',
        'company_name': companyName.trim(),
        'sec_registration_number': secRegistrationNumber.trim().toUpperCase(),
        'tin': tin.trim(),
        'region': region.trim(),
        'province': province.trim(),
        'city': city.trim(),
        'barangay': barangay.trim(),
        'street_address': streetAddress.trim(),
        'postal_code': postalCode.trim(),
        'position': position.trim(),
        'terms_accepted': '1',
      });

      for (int i = 0; i < normalizedAreas.length; i++) {
        request.fields['service_areas[$i]'] = normalizedAreas[i];
      }

      request.files.addAll([
        await http.MultipartFile.fromPath(
          'sec_certificate',
          secCertificateFilePath.trim(),
        ),
        await http.MultipartFile.fromPath('bir_2303', bir2303FilePath.trim()),
        await http.MultipartFile.fromPath(
          'mayors_permit',
          mayorsPermitFilePath.trim(),
        ),
        await http.MultipartFile.fromPath(
          'representative_id',
          representativeIdFilePath.trim(),
        ),
      ]);

      final http.StreamedResponse streamedResponse = await request.send();

      final http.Response response = await http.Response.fromStream(
        streamedResponse,
      );

      final dynamic decoded = _decodeResponse(response.body);

      if ((response.statusCode == 200 || response.statusCode == 201) &&
          decoded is Map<String, dynamic>) {
        final dynamic rawUser = decoded['user'] ?? decoded['data'];

        return {
          'success': true,
          'status_code': response.statusCode,
          'message': _getMessage(
            decoded,
            fallback:
                'Logistics registration submitted successfully. '
                'Please verify your email. Your account will remain '
                'pending until approved by BiliHub.',
          ),
          'user': rawUser is Map
              ? Map<String, dynamic>.from(rawUser)
              : <String, dynamic>{},
          'requires_verification': decoded['requires_verification'] ?? true,
          'status': decoded['status'] ?? 'pending_approval',
        };
      }

      if (response.statusCode == 413) {
        return {
          'success': false,
          'status_code': 413,
          'message':
              'One or more uploaded documents are too large. '
              'Please use files not larger than 5 MB each.',
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,
          'message': _getMessage(
            decoded,
            fallback:
                'Please check the Logistics registration '
                'information and uploaded documents.',
          ),
          'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
        };
      }

      if (response.statusCode == 429) {
        return {
          'success': false,
          'status_code': 429,
          'message':
              'Too many registration attempts. '
              'Please wait and try again.',
        };
      }

      if (response.statusCode >= 500) {
        return {
          'success': false,
          'status_code': response.statusCode,
          'message':
              'The BiliHub server is temporarily unavailable. '
              'Please try again.',
        };
      }

      return {
        'success': false,
        'status_code': response.statusCode,
        'message': _getMessage(
          decoded,
          fallback:
              'Unable to create the Logistics account. '
              'Please try again.',
        ),
        'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
      };
    } catch (_) {
      return {
        'success': false,
        'message':
            'Unable to connect to the server. '
            'Please check your internet connection '
            'and try again.',
      };
    }
  }

  // ============================================================
  // CREATE RIDER
  // POST /api/logistic/riders
  // ============================================================

  static Future<Map<String, dynamic>> createRider({
    required String firstName,
    required String lastName,
    required String birthday,
    required String email,
    required String mobileNumber,
    required String driverLicenseNumber,
    required String licenseExpiryDate,
    required String vehicleType,
    required String plateNumber,
    required String driverLicenseFilePath,
    required String vehicleOrCrFilePath,
  }) async {
    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message':
              'Authentication token not found. '
              'Please login again.',
        };
      }

      final http.MultipartRequest request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/logistic/riders'),
      );

      request.headers.addAll({
        'Accept': 'application/json',
        'Authorization': 'Bearer ${token.trim()}',
      });

      request.fields.addAll({
        'first_name': firstName.trim(),
        'last_name': lastName.trim(),
        'birthday': birthday.trim(),
        'email': email.trim().toLowerCase(),
        'mobile_number': mobileNumber.trim(),
        'driver_license_number': driverLicenseNumber.trim().toUpperCase(),
        'license_expiry_date': licenseExpiryDate.trim(),
        'vehicle_type': vehicleType.trim(),
        'plate_number': plateNumber.trim().toUpperCase(),
      });

      request.files.addAll([
        await http.MultipartFile.fromPath(
          'driver_license_file',
          driverLicenseFilePath.trim(),
        ),
        await http.MultipartFile.fromPath(
          'vehicle_or_cr_file',
          vehicleOrCrFilePath.trim(),
        ),
      ]);

      final http.StreamedResponse streamedResponse = await request.send();

      final http.Response response = await http.Response.fromStream(
        streamedResponse,
      );

      final dynamic decoded = _decodeResponse(response.body);

      if ((response.statusCode == 200 || response.statusCode == 201) &&
          decoded is Map<String, dynamic>) {
        final dynamic rawRider = decoded['rider'] ?? decoded['data'];

        return {
          'success': true,
          'status_code': response.statusCode,
          'message': _getMessage(
            decoded,
            fallback:
                'Rider account created successfully. '
                'An activation link has been sent to the rider.',
          ),
          'rider': rawRider is Map
              ? Map<String, dynamic>.from(rawRider)
              : <String, dynamic>{},
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,
          'message': _getMessage(
            decoded,
            fallback:
                'Please check the Rider information '
                'and documents.',
          ),
          'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'Only an approved Logistics account '
            'can create Rider accounts.',
        notFoundMessage:
            'Rider creation API is not available yet. '
            'Please update the Laravel backend.',
        fallback:
            'Unable to create Rider account. '
            'Please try again.',
      );
    } catch (_) {
      return {
        'success': false,
        'message':
            'Unable to connect to the server. '
            'Please check your internet connection '
            'and try again.',
      };
    }
  }

  // ============================================================
  // BUYER SAVED ADDRESSES
  // GET /api/addresses
  // ============================================================

  static Future<Map<String, dynamic>> getBuyerAddresses() async {
    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message':
              'Authentication token not found. '
              'Please login again.',
        };
      }

      final http.Response response = await http.get(
        Uri.parse('$baseUrl/addresses'),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        return {
          'success': true,
          'status_code': 200,
          'data': _extractList(decoded, keys: const ['data', 'addresses']),
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This account cannot access '
            'buyer addresses.',
        notFoundMessage:
            'Buyer address API '
            'is not available.',
        fallback: 'Unable to load saved addresses.',
      );
    } catch (_) {
      return {
        'success': false,
        'message':
            'Unable to load saved addresses. '
            'Please check your internet connection '
            'and try again.',
      };
    }
  }

  // ============================================================
  // BUYER VOUCHERS
  // ============================================================

  /// GET /api/buyer/vouchers
  /// Loads active vouchers that the authenticated buyer has not claimed.
  static Future<Map<String, dynamic>> getBuyerVouchers() async {
    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Session expired. Please login again.',
        };
      }

      final http.Response response = await http
          .get(
            Uri.parse('$baseUrl/buyer/vouchers'),
            headers: _authHeaders(token),
          )
          .timeout(const Duration(seconds: 25));

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map) {
        return {
          'success': decoded['success'] != false,
          'status_code': 200,
          'data': _extractList(decoded, keys: const ['data', 'vouchers']),
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'You cannot access buyer vouchers.',
        notFoundMessage: 'Buyer voucher API is not available.',
        fallback: 'Unable to load available vouchers.',
      );
    } on TimeoutException {
      return {
        'success': false,
        'message': 'Loading vouchers timed out. Please try again.',
      };
    } catch (error) {
      debugPrint('Get buyer vouchers error: $error');
      return {
        'success': false,
        'message': 'Unable to load vouchers. Please check your connection.',
      };
    }
  }

  /// GET /api/buyer/vouchers/claimed
  /// Loads all vouchers claimed by the authenticated buyer.
  static Future<Map<String, dynamic>> getClaimedBuyerVouchers() async {
    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Session expired. Please login again.',
        };
      }

      final http.Response response = await http
          .get(
            Uri.parse('$baseUrl/buyer/vouchers/claimed'),
            headers: _authHeaders(token),
          )
          .timeout(const Duration(seconds: 25));

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map) {
        return {
          'success': decoded['success'] != false,
          'status_code': 200,
          'data': _extractList(decoded, keys: const ['data', 'vouchers']),
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'You cannot access your claimed vouchers.',
        notFoundMessage: 'Claimed vouchers API is not available.',
        fallback: 'Unable to load your claimed vouchers.',
      );
    } on TimeoutException {
      return {
        'success': false,
        'message': 'Loading claimed vouchers timed out. Please try again.',
      };
    } catch (error) {
      debugPrint('Get claimed vouchers error: $error');
      return {
        'success': false,
        'message': 'Unable to load claimed vouchers. Please try again.',
      };
    }
  }

  /// POST /api/buyer/vouchers/{voucherId}/claim
  /// Claims a voucher for the authenticated buyer.
  static Future<Map<String, dynamic>> claimBuyerVoucher({
    required int voucherId,
  }) async {
    if (voucherId <= 0) {
      return {
        'success': false,
        'status_code': 422,
        'message': 'Invalid voucher ID.',
      };
    }

    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Session expired. Please login again.',
        };
      }

      final http.Response response = await http
          .post(
            Uri.parse('$baseUrl/buyer/vouchers/$voucherId/claim'),
            headers: _authHeaders(token, json: true),
            body: jsonEncode(<String, dynamic>{}),
          )
          .timeout(const Duration(seconds: 25));

      final dynamic decoded = _decodeResponse(response.body);

      if ((response.statusCode == 200 || response.statusCode == 201) &&
          decoded is Map &&
          decoded['success'] != false) {
        final dynamic rawData = decoded['data'];
        return {
          'success': true,
          'status_code': response.statusCode,
          'message': _getMessage(
            decoded,
            fallback: 'Voucher successfully claimed.',
          ),
          'data': rawData is Map
              ? Map<String, dynamic>.from(rawData)
              : <String, dynamic>{},
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,
          'message': _getMessage(decoded, fallback: 'Unable to claim voucher.'),
          'errors': decoded is Map ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'You cannot claim this voucher.',
        notFoundMessage: 'Voucher was not found.',
        fallback: 'Unable to claim voucher. Please try again.',
      );
    } on TimeoutException {
      return {
        'success': false,
        'message': 'Claim request timed out. Please refresh your vouchers.',
      };
    } catch (error) {
      debugPrint('Claim buyer voucher error: $error');
      return {
        'success': false,
        'message': 'Unable to claim voucher. Please check your connection.',
      };
    }
  }

  /// POST /api/buyer/vouchers/preview
  /// Previews a claimed voucher against the exact items selected for checkout.
  static Future<Map<String, dynamic>> previewBuyerVoucher({
    required String voucherCode,
    required List<Map<String, dynamic>> items,
  }) async {
    final String normalizedCode = voucherCode.trim().toUpperCase();

    if (normalizedCode.isEmpty) {
      return {
        'success': false,
        'status_code': 422,
        'message': 'Please enter a voucher code.',
      };
    }

    if (items.isEmpty) {
      return {
        'success': false,
        'status_code': 422,
        'message': 'There are no selected checkout items to preview.',
      };
    }

    final List<Map<String, dynamic>> normalizedItems = <Map<String, dynamic>>[];

    for (final Map<String, dynamic> item in items) {
      final int productId =
          int.tryParse(item['product_id']?.toString() ?? '') ?? 0;
      final int quantity =
          int.tryParse(item['quantity']?.toString() ?? '') ?? 0;
      final int? variantId = item['variant_id'] == null
          ? null
          : int.tryParse(item['variant_id'].toString());
      final int? sizeId = item['size_id'] == null
          ? null
          : int.tryParse(item['size_id'].toString());

      if (productId <= 0 || quantity <= 0) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'One or more checkout items are invalid.',
        };
      }

      if (item['variant_id'] != null && (variantId == null || variantId <= 0)) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'One or more selected product variants are invalid.',
        };
      }

      if (item['size_id'] != null && (sizeId == null || sizeId <= 0)) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'One or more selected product sizes are invalid.',
        };
      }

      normalizedItems.add({
        'product_id': productId,
        'variant_id': variantId,
        'size_id': sizeId,
        'quantity': quantity,
      });
    }

    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Session expired. Please login again.',
        };
      }

      final http.Response response = await http
          .post(
            Uri.parse('$baseUrl/buyer/vouchers/preview'),
            headers: _authHeaders(token, json: true),
            body: jsonEncode({
              'voucher_code': normalizedCode,
              'items': normalizedItems,
            }),
          )
          .timeout(const Duration(seconds: 25));

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 &&
          decoded is Map &&
          decoded['success'] != false) {
        final dynamic rawData = decoded['data'];
        return {
          'success': true,
          'status_code': 200,
          'message': _getMessage(
            decoded,
            fallback: 'Voucher preview calculated successfully.',
          ),
          'data': rawData is Map
              ? Map<String, dynamic>.from(rawData)
              : <String, dynamic>{},
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,
          'message': _getMessage(
            decoded,
            fallback: 'Voucher cannot be applied.',
          ),
          'errors': decoded is Map ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'You cannot preview this voucher.',
        notFoundMessage: 'Voucher preview API is not available.',
        fallback: 'Unable to preview voucher.',
      );
    } on TimeoutException {
      return {
        'success': false,
        'message': 'Voucher preview timed out. Please try again.',
      };
    } catch (error) {
      debugPrint('Preview buyer voucher error: $error');
      return {
        'success': false,
        'message': 'Unable to preview voucher. Please check your connection.',
      };
    }
  }

  // ============================================================
  // PLACE BUYER ORDER
  // POST /api/orders
  // ============================================================

  static Future<Map<String, dynamic>> createOrder({
    required int addressId,
    required String paymentMethod,
    required List<Map<String, dynamic>> items,
    String? notes,
    String? voucherCode,
  }) async {
    if (addressId <= 0) {
      return {
        'success': false,
        'status_code': 422,
        'message': 'Please select a valid delivery address.',
      };
    }

    const Set<String> allowedPaymentMethods = {
      'cod',
      'gcash',
      'maya',
      'card',
      'bank_transfer',
    };

    final String normalizedPaymentMethod = paymentMethod.trim().toLowerCase();

    if (!allowedPaymentMethods.contains(normalizedPaymentMethod)) {
      return {
        'success': false,
        'status_code': 422,
        'message': 'Please select a valid payment method.',
      };
    }

    if (items.isEmpty) {
      return {
        'success': false,
        'status_code': 422,
        'message': 'Your checkout has no items.',
      };
    }

    final List<Map<String, dynamic>> normalizedItems = <Map<String, dynamic>>[];

    for (final Map<String, dynamic> item in items) {
      final int productId =
          int.tryParse(item['product_id']?.toString() ?? '') ?? 0;
      final int quantity =
          int.tryParse(item['quantity']?.toString() ?? '') ?? 0;

      final int? variantId = item['variant_id'] == null
          ? null
          : int.tryParse(item['variant_id'].toString());
      final int? sizeId = item['size_id'] == null
          ? null
          : int.tryParse(item['size_id'].toString());

      if (productId <= 0 || quantity <= 0) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'One or more checkout items are invalid.',
        };
      }

      if (item['variant_id'] != null && (variantId == null || variantId <= 0)) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'One or more selected product variants are invalid.',
        };
      }

      if (item['size_id'] != null && (sizeId == null || sizeId <= 0)) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'One or more selected product sizes are invalid.',
        };
      }

      normalizedItems.add({
        'product_id': productId,
        'variant_id': variantId,
        'size_id': sizeId,
        'quantity': quantity,
      });
    }

    final String normalizedVoucherCode =
        voucherCode?.trim().toUpperCase() ?? '';
    final String normalizedNotes = notes?.trim() ?? '';

    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found. Please login again.',
        };
      }

      // Payload matches OrderController@store:
      // address_id, payment_method, notes, voucher_code, items[].
      final Map<String, dynamic> requestBody = {
        'address_id': addressId,
        'payment_method': normalizedPaymentMethod,
        'notes': normalizedNotes.isNotEmpty ? normalizedNotes : null,
        'items': normalizedItems,
        if (normalizedVoucherCode.isNotEmpty)
          'voucher_code': normalizedVoucherCode,
      };

      final http.Response response = await http
          .post(
            Uri.parse('$baseUrl/orders'),
            headers: _authHeaders(token, json: true),
            body: jsonEncode(requestBody),
          )
          .timeout(const Duration(seconds: 45));

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        if (decoded is! Map) {
          return {
            'success': false,
            'status_code': response.statusCode,
            'message': 'The server returned an invalid order response.',
          };
        }

        final Map<String, dynamic> responseMap = Map<String, dynamic>.from(
          decoded,
        );
        final dynamic rawData = responseMap['data'];

        if (rawData is! Map) {
          return {
            'success': false,
            'status_code': response.statusCode,
            'message': _getMessage(
              responseMap,
              fallback: 'The order response did not contain order details.',
            ),
          };
        }

        final Map<String, dynamic> orderData = Map<String, dynamic>.from(
          rawData,
        );

        return {
          'success': true,
          'status_code': response.statusCode,
          'message': _getMessage(
            responseMap,
            fallback: 'Order placed successfully.',
          ),
          'data': orderData,
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,
          'message': _getMessage(
            decoded,
            fallback: 'Please check your order information.',
          ),
          'errors': decoded is Map ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'This account cannot place buyer orders.',
        notFoundMessage: 'Buyer order API is not available.',
        fallback: 'Unable to place your order.',
      );
    } on TimeoutException {
      // The server may have completed checkout before the response timed out.
      // Refresh the buyer's order list before attempting checkout again.
      return {
        'success': false,
        'message':
            'The order request timed out. Please check your order history '
            'before trying again to avoid placing a duplicate order.',
        'result_uncertain': true,
      };
    } catch (error) {
      debugPrint('Create buyer order error: $error');
      return {
        'success': false,
        'message':
            'Unable to place your order. Please check your internet '
            'connection and try again.',
      };
    }
  }

  // ============================================================
  // RIDER DASHBOARD
  // ============================================================

  // ============================================================
  // RIDER PICKUP OFFERS
  // Laravel:
  // GET  /api/rider/pickup-offers
  // POST /api/rider/pickup-offers/{pickupRequest}/accept
  // POST /api/rider/pickup-offers/{pickupRequest}/decline
  // ============================================================

  /// Get active seller pickup offers for the authenticated rider.
  ///
  /// These are not assigned deliveries yet.
  /// A shipment becomes assigned only after a successful acceptance.
  static Future<Map<String, dynamic>> getRiderPickupOffers() async {
    return _riderPickupOfferRequest(
      method: 'GET',
      path: '/rider/pickup-offers',
    );
  }

  /// Accept an offered seller pickup.
  ///
  /// offerId must be the PickupRequest ID, not shipmentId.
  static Future<Map<String, dynamic>> acceptRiderPickupOffer({
    required int offerId,
  }) async {
    if (offerId <= 0) {
      return <String, dynamic>{
        'success': false,
        'status_code': 422,
        'message': 'Invalid pickup offer ID.',
      };
    }

    return _riderPickupOfferRequest(
      method: 'POST',
      path: '/rider/pickup-offers/$offerId/accept',
    );
  }

  /// Decline an offered seller pickup.
  ///
  /// Laravel handles reassignment to another eligible rider.
  static Future<Map<String, dynamic>> declineRiderPickupOffer({
    required int offerId,
  }) async {
    if (offerId <= 0) {
      return <String, dynamic>{
        'success': false,
        'status_code': 422,
        'message': 'Invalid pickup offer ID.',
      };
    }

    return _riderPickupOfferRequest(
      method: 'POST',
      path: '/rider/pickup-offers/$offerId/decline',
    );
  }

  // ============================================================
  // SHARED RIDER PICKUP OFFER REQUEST
  // ============================================================

  static Future<Map<String, dynamic>> _riderPickupOfferRequest({
    required String method,
    required String path,
  }) async {
    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return <String, dynamic>{
          'success': false,
          'status_code': 401,
          'message':
              'Authentication token not found. '
              'Please log in again.',
        };
      }

      final Uri uri = Uri.parse('$baseUrl$path');

      final Map<String, String> headers = _authHeaders(
        token,
        json: method == 'POST',
      );

      late final http.Response response;

      if (method == 'GET') {
        response = await http
            .get(uri, headers: headers)
            .timeout(const Duration(seconds: 25));
      } else if (method == 'POST') {
        response = await http
            .post(uri, headers: headers, body: jsonEncode(<String, dynamic>{}))
            .timeout(const Duration(seconds: 25));
      } else {
        return <String, dynamic>{
          'success': false,
          'status_code': 422,
          'message': 'Unsupported pickup request method.',
        };
      }

      final dynamic decoded = _decodeResponse(response.body);

      // ========================================================
      // SUCCESS
      // ========================================================

      if (response.statusCode >= 200 && response.statusCode < 300) {
        if (decoded is! Map) {
          return <String, dynamic>{
            'success': false,
            'status_code': response.statusCode,
            'message': 'Invalid pickup API response.',
          };
        }

        final Map<String, dynamic> result = Map<String, dynamic>.from(decoded);

        if (result['success'] != true) {
          return <String, dynamic>{
            'success': false,
            'status_code': response.statusCode,
            'message': _getMessage(
              result,
              fallback: 'Pickup request was not successful.',
            ),
          };
        }

        // Preserve the original API response, including:
        // data, meta, message, offer_id, shipment_id.
        //
        // AvailablePickups reads response['data'].
        return <String, dynamic>{
          ...result,
          'success': true,
          'status_code': response.statusCode,
        };
      }

      // ========================================================
      // EXPIRED / ALREADY ACCEPTED / CONFLICT
      // ========================================================

      if (response.statusCode == 409) {
        return <String, dynamic>{
          'success': false,
          'status_code': 409,
          'message': _getMessage(
            decoded,
            fallback:
                'This pickup offer is no longer available. '
                'Please refresh your pickup offers.',
          ),
        };
      }

      // ========================================================
      // INVALID INPUT
      // ========================================================

      if (response.statusCode == 422) {
        return <String, dynamic>{
          'success': false,
          'status_code': 422,
          'message': _getMessage(
            decoded,
            fallback: 'Invalid pickup offer request.',
          ),
          'errors': decoded is Map ? decoded['errors'] : null,
        };
      }

      // ========================================================
      // AUTH / FORBIDDEN / NOT FOUND / SERVER ERRORS
      // ========================================================

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'You are not authorized to access '
            'or respond to this pickup offer.',
        notFoundMessage:
            'Pickup offer endpoint was not found. '
            'Please check that the latest Laravel '
            'backend has been deployed.',
        fallback:
            'Unable to process the pickup request. '
            'Please try again.',
      );
    } on TimeoutException {
      return <String, dynamic>{
        'success': false,
        'message':
            'The pickup request timed out. '
            'Please refresh and verify the offer status '
            'before trying again.',
      };
    } catch (error) {
      debugPrint('Rider pickup offer API error: $error');

      return <String, dynamic>{
        'success': false,
        'message':
            'Unable to connect to the pickup service. '
            'Please check your connection and try again.',
      };
    }
  }

  static Future<Map<String, dynamic>> getRiderDashboard() async {
    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final http.Response response = await http.get(
        Uri.parse('$baseUrl/rider/dashboard'),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map<String, dynamic>) {
        final Map<String, dynamic> root = _extractMapRoot(decoded);

        final Map<String, dynamic> rider = _extractMapByKeys(
          root,
          const <String>['rider', 'profile', 'user'],
        );

        final Map<String, dynamic> overview = _extractMapByKeys(
          root,
          const <String>['overview', 'summary', 'stats', 'statistics'],
        );

        final List<dynamic> tasks = _extractList(
          root,
          keys: const <String>[
            'tasks',
            'deliveries',
            'orders',
            'shipments',
            'recent_tasks',
          ],
        );

        return {
          'success': true,
          'status_code': 200,
          'data': root,
          'rider': rider,
          'overview': overview,
          'tasks': tasks,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This account cannot access '
            'the rider dashboard.',
        notFoundMessage:
            'Rider dashboard API '
            'is not available yet.',
        fallback: 'Unable to load rider dashboard.',
      );
    } catch (e) {
      return {
        'success': false,
        'message':
            'Unable to load '
            'rider dashboard: $e',
      };
    }
  }

  // ============================================================
  // RIDER DELIVERIES
  // GET /api/rider/orders
  // ============================================================

  static Future<Map<String, dynamic>> getRiderDeliveries() async {
    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        debugPrint('=== RIDER DELIVERIES ===');
        debugPrint('NO AUTH TOKEN FOUND');
        debugPrint('========================');

        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final Uri uri = Uri.parse('$baseUrl/rider/orders');

      debugPrint('');
      debugPrint('======================================');
      debugPrint('=== RIDER DELIVERIES API REQUEST ===');
      debugPrint('URL: $uri');
      debugPrint('======================================');

      final http.Response response = await http.get(
        uri,
        headers: _authHeaders(token),
      );

      debugPrint('');
      debugPrint('======================================');
      debugPrint('=== RIDER DELIVERIES API RESPONSE ===');
      debugPrint('STATUS: ${response.statusCode}');
      debugPrint('BODY: ${response.body}');
      debugPrint('======================================');
      debugPrint('');

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        final List<dynamic> deliveries = _extractList(
          decoded,
          keys: const <String>['data', 'deliveries', 'orders', 'shipments'],
        );

        final Map<String, dynamic> meta = _extractMeta(decoded);

        debugPrint('RIDER DELIVERIES COUNT: ${deliveries.length}');

        for (int index = 0; index < deliveries.length; index++) {
          final dynamic item = deliveries[index];

          if (item is Map) {
            final Map<String, dynamic> shipment = Map<String, dynamic>.from(
              item,
            );

            debugPrint(
              'RIDER ITEM #${index + 1}: '
              'id=${shipment['id']} | '
              'tracking=${shipment['tracking_number']} | '
              'assignment=${shipment['assignment_type']} | '
              'pickup_rider_id=${shipment['pickup_rider_id']} | '
              'linehaul_rider_id=${shipment['linehaul_rider_id']} | '
              'rider_id=${shipment['rider_id']} | '
              'status=${shipment['status']} | '
              'routing_status=${shipment['routing_status']} | '
              'linehaul_status=${shipment['linehaul_status']} | '
              'origin_hub=${shipment['origin_hub_name']} | '
              'destination_hub=${shipment['destination_hub_name']} | '
              'sorting_status=${shipment['sorting_status']} | '
              'received=${shipment['received_by_sorting_center']}',
            );
          }
        }

        return {
          'success': true,
          'status_code': 200,
          'data': deliveries,
          'deliveries': deliveries,
          'meta': meta,
        };
      }

      final Map<String, dynamic> error = _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'This account cannot access rider deliveries.',
        notFoundMessage: 'Rider deliveries API is not available yet.',
        fallback: 'Unable to load rider deliveries.',
      );

      debugPrint('RIDER DELIVERIES ERROR: ${error['message']}');

      return error;
    } catch (e, stackTrace) {
      debugPrint('');
      debugPrint('======================================');
      debugPrint('=== RIDER DELIVERIES API ERROR ===');
      debugPrint('ERROR: $e');
      debugPrint('STACK TRACE: $stackTrace');
      debugPrint('======================================');
      debugPrint('');

      return {
        'success': false,
        'message': 'Unable to load rider deliveries: $e',
      };
    }
  }

  // ============================================================
  // SINGLE RIDER DELIVERY
  // GET /api/rider/orders/{shipment}
  // ============================================================

  static Future<Map<String, dynamic>> getRiderDelivery(int shipmentId) async {
    try {
      if (shipmentId <= 0) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Invalid shipment ID.',
        };
      }

      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final http.Response response = await http.get(
        Uri.parse('$baseUrl/rider/orders/$shipmentId'),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map<String, dynamic>) {
        final Map<String, dynamic> root = _extractMapRoot(decoded);

        Map<String, dynamic> shipment = _extractMapByKeys(root, const <String>[
          'shipment',
          'order',
          'delivery',
        ]);

        if (shipment.isEmpty) {
          shipment = root;
        }

        return {
          'success': true,
          'status_code': 200,
          'data': shipment,
          'shipment': shipment,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This shipment is not assigned '
            'to this rider.',
        notFoundMessage: 'Rider shipment was not found.',
        fallback: 'Unable to load rider shipment.',
      );
    } catch (e) {
      return {
        'success': false,
        'message':
            'Unable to load '
            'rider shipment: $e',
      };
    }
  }

  // ============================================================
  // RIDER WAYBILL QR SCANNER
  // POST /api/rider/scan
  // ============================================================

  static Future<Map<String, dynamic>> scanRiderWaybill({
    required String qrPayload,
  }) async {
    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final String normalizedPayload = qrPayload.trim();

      if (normalizedPayload.isEmpty) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'QR code is empty.',
        };
      }

      final http.Response response = await http.post(
        Uri.parse('$baseUrl/rider/scan'),
        headers: _authHeaders(token, json: true),
        body: jsonEncode({'qr_payload': normalizedPayload}),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        if (decoded is Map<String, dynamic>) {
          final Map<String, dynamic> root = _extractMapRoot(decoded);

          Map<String, dynamic> shipment = _extractMapByKeys(
            root,
            const <String>['shipment', 'delivery', 'order'],
          );

          if (shipment.isEmpty &&
              (root.containsKey('tracking_number') ||
                  root.containsKey('shipment_id'))) {
            shipment = root;
          }

          final String scanType = _firstString(<dynamic>[
            decoded['scan_type'],
            root['scan_type'],
            root['type'],
          ]);

          return {
            'success': true,
            'status_code': response.statusCode,
            'message': _getMessage(
              decoded,
              fallback: 'Waybill scanned successfully.',
            ),
            'scan_type': scanType,
            'shipment': shipment,
            'data': root,
          };
        }

        return {
          'success': true,
          'status_code': response.statusCode,
          'message': 'Waybill scanned successfully.',
          'scan_type': '',
          'shipment': <String, dynamic>{},
          'data': <String, dynamic>{},
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,
          'message': _getMessage(
            decoded,
            fallback:
                'The scanned QR code '
                'could not be processed.',
          ),
          'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This parcel is not assigned '
            'to you or cannot be scanned '
            'by this rider.',
        notFoundMessage:
            'The shipment for this QR code '
            'was not found.',
        fallback:
            'Unable to process the '
            'scanned waybill.',
      );
    } catch (e) {
      return {'success': false, 'message': 'Unable to scan waybill: $e'};
    }
  }

  // ============================================================
  // RIDER SELLER PICKUP - MARK PICKED UP / IN TRANSIT
  // POST /api/rider/pickups/{shipment}/picked-up
  // ============================================================

  static Future<Map<String, dynamic>> markRiderSellerPickupInTransit({
    required int shipmentId,
  }) async {
    try {
      if (shipmentId <= 0) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Invalid shipment ID.',
        };
      }

      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final http.Response response = await http.post(
        Uri.parse('$baseUrl/rider/pickups/$shipmentId/picked-up'),
        headers: _authHeaders(token, json: true),
        body: jsonEncode(<String, dynamic>{}),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        final Map<String, dynamic> root = decoded is Map<String, dynamic>
            ? _extractMapRoot(decoded)
            : <String, dynamic>{};

        return {
          'success': true,
          'status_code': response.statusCode,
          'message': _getMessage(
            decoded,
            fallback: 'Seller pickup marked as in transit.',
          ),
          'data': root,
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,
          'message': _getMessage(
            decoded,
            fallback: 'This seller pickup cannot be marked as in transit.',
          ),
          'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This shipment is not assigned to you for seller pickup.',
        notFoundMessage:
            'Seller pickup API is not available yet. '
            'The Laravel pickup endpoint still needs to be added.',
        fallback: 'Unable to update seller pickup status.',
      );
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to update seller pickup: $e',
      };
    }
  }

  // ============================================================
  // RIDER SELLER PICKUP - ARRIVED AT SORTING CENTER
  // POST /api/rider/pickups/{shipment}/deliver-to-sorting-center
  // ============================================================

  static Future<Map<String, dynamic>> deliverRiderPickupToSortingCenter({
    required int shipmentId,
    String? notes,
  }) async {
    try {
      if (shipmentId <= 0) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Invalid shipment ID.',
        };
      }

      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final String normalizedNotes = notes?.trim() ?? '';
      final Map<String, dynamic> requestBody = <String, dynamic>{};

      if (normalizedNotes.isNotEmpty) {
        requestBody['notes'] = normalizedNotes;
      }

      final http.Response response = await http.post(
        Uri.parse(
          '$baseUrl/rider/pickups/'
          '$shipmentId/deliver-to-sorting-center',
        ),
        headers: _authHeaders(token, json: true),
        body: jsonEncode(requestBody),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        final Map<String, dynamic> root = decoded is Map<String, dynamic>
            ? _extractMapRoot(decoded)
            : <String, dynamic>{};

        return {
          'success': true,
          'status_code': response.statusCode,
          'message': _getMessage(
            decoded,
            fallback:
                'Parcel arrived at the sorting center. '
                'Awaiting Logistics confirmation.',
          ),
          'data': root,
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,
          'message': _getMessage(
            decoded,
            fallback:
                'This parcel cannot be handed over '
                'to the sorting center yet.',
          ),
          'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This shipment is not assigned to you for seller pickup.',
        notFoundMessage:
            'Sorting-center handoff API is not available yet. '
            'The Laravel pickup endpoint still needs to be added.',
        fallback:
            'Unable to mark this parcel as arrived '
            'at the sorting center.',
      );
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to update sorting-center handoff: $e',
      };
    }
  }

  // ============================================================
  // RIDER - SHIPMENT COD DETAILS
  // GET /api/rider/shipments/{shipment}/cod
  // ============================================================

  static Future<Map<String, dynamic>> getRiderShipmentCod({
    required int shipmentId,
  }) async {
    if (shipmentId <= 0) {
      return <String, dynamic>{
        'success': false,
        'status_code': 422,
        'message': 'Invalid shipment ID.',
      };
    }

    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return <String, dynamic>{
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found. Please log in again.',
        };
      }

      final http.Response response = await http
          .get(
            Uri.parse('$baseUrl/rider/shipments/$shipmentId/cod'),
            headers: _authHeaders(token),
          )
          .timeout(const Duration(seconds: 30));

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        if (decoded is! Map || decoded['success'] != true) {
          return <String, dynamic>{
            'success': false,
            'status_code': 200,
            'message': 'Unable to verify COD details from the server.',
          };
        }

        final dynamic rawData = decoded['data'];
        if (rawData is! Map) {
          return <String, dynamic>{
            'success': false,
            'status_code': 200,
            'message': 'Invalid COD details returned by the server.',
          };
        }

        return <String, dynamic>{
          'success': true,
          'status_code': 200,
          'message': _getMessage(
            Map<String, dynamic>.from(decoded),
            fallback: 'COD details loaded successfully.',
          ),
          'data': Map<String, dynamic>.from(rawData),
        };
      }

      if (response.statusCode == 422 || response.statusCode == 409) {
        return <String, dynamic>{
          'success': false,
          'status_code': response.statusCode,
          'message': _getMessage(
            decoded,
            fallback: 'COD information could not be verified.',
          ),
          'errors': decoded is Map ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'You cannot view COD details for this shipment.',
        notFoundMessage: 'Shipment COD API was not found on the server.',
        fallback: 'Unable to retrieve shipment COD details.',
      );
    } on TimeoutException {
      return <String, dynamic>{
        'success': false,
        'message': 'COD details request timed out. Please refresh.',
      };
    } catch (error) {
      debugPrint('Rider shipment COD details error: $error');
      return <String, dynamic>{
        'success': false,
        'message': 'Unable to load COD details. Check your connection.',
      };
    }
  }

  // ============================================================
  // RIDER - COLLECT SHIPMENT COD
  // POST /api/rider/shipments/{shipment}/collect
  // ============================================================

  static Future<Map<String, dynamic>> collectRiderShipmentCod({
    required int shipmentId,
    required String amountCollected,
  }) async {
    if (shipmentId <= 0) {
      return <String, dynamic>{
        'success': false,
        'status_code': 422,
        'message': 'Invalid shipment ID.',
      };
    }

    final String amount = amountCollected.trim();
    if (!RegExp(r'^(?:0|[1-9]\d*)(?:\.\d{1,2})?$').hasMatch(amount) ||
        (double.tryParse(amount) ?? 0) <= 0) {
      return <String, dynamic>{
        'success': false,
        'status_code': 422,
        'message': 'Enter a valid COD amount with up to two decimals.',
      };
    }

    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return <String, dynamic>{
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found. Please log in again.',
        };
      }

      final http.Response response = await http
          .post(
            Uri.parse('$baseUrl/rider/shipments/$shipmentId/collect'),
            headers: _authHeaders(token, json: true),
            body: jsonEncode(<String, dynamic>{'amount_collected': amount}),
          )
          .timeout(const Duration(seconds: 30));

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        if (decoded is! Map || decoded['success'] != true) {
          return <String, dynamic>{
            'success': false,
            'status_code': response.statusCode,
            'message': _getMessage(
              decoded,
              fallback:
                  'Collection could not be confirmed. Refresh COD status.',
            ),
          };
        }

        final dynamic rawData = decoded['data'];
        return <String, dynamic>{
          'success': true,
          'status_code': response.statusCode,
          'message': _getMessage(
            Map<String, dynamic>.from(decoded),
            fallback: 'Shipment COD recorded successfully.',
          ),
          'data': rawData is Map
              ? Map<String, dynamic>.from(rawData)
              : <String, dynamic>{},
        };
      }

      if (response.statusCode == 422 || response.statusCode == 409) {
        return <String, dynamic>{
          'success': false,
          'status_code': response.statusCode,
          'message': _getMessage(
            decoded,
            fallback: 'COD collection was rejected. Check COD status.',
          ),
          'errors': decoded is Map ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage: 'You cannot collect COD for this shipment.',
        notFoundMessage: 'Shipment COD collection API was not found.',
        fallback: 'Collection not confirmed. Check COD status before retrying.',
      );
    } on TimeoutException {
      // The server may have committed the payment before the timeout.
      return <String, dynamic>{
        'success': false,
        'message':
            'Collection request timed out. Do not collect cash again. '
            'Refresh COD status before retrying.',
      };
    } catch (error) {
      debugPrint('Rider shipment COD collection error: $error');
      return <String, dynamic>{
        'success': false,
        'message':
            'Collection result is uncertain. Do not collect cash again. '
            'Verify COD status before retrying.',
      };
    }
  }

  // ============================================================
  // RIDER EARNINGS
  // ============================================================

  static Future<Map<String, dynamic>> getRiderEarnings() async {
    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final http.Response response = await http.get(
        Uri.parse('$baseUrl/rider/earnings'),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        if (decoded is Map<String, dynamic>) {
          final Map<String, dynamic> root = _extractMapRoot(decoded);

          final Map<String, dynamic> earnings = root['earnings'] is Map
              ? Map<String, dynamic>.from(root['earnings'])
              : root;

          return {'success': true, 'status_code': 200, 'data': earnings};
        }

        return {
          'success': true,
          'status_code': 200,
          'data': <String, dynamic>{},
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This account cannot access '
            'rider earnings.',
        notFoundMessage:
            'Rider earnings API '
            'is not available yet.',
        fallback: 'Unable to load rider earnings.',
      );
    } catch (e) {
      return {
        'success': false,
        'message':
            'Unable to load '
            'rider earnings: $e',
      };
    }
  }

  // ============================================================
  // UPLOAD RIDER DELIVERY PROOF
  // POST /api/rider/orders/{shipment}/proof
  // ============================================================

  static Future<Map<String, dynamic>> uploadRiderDeliveryProof({
    required int shipmentId,
    required String proofFilePath,
    required String deliveredTo,
    String? deliverySignature,
    String? deliveryNotes,
  }) async {
    try {
      if (shipmentId <= 0) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Invalid shipment ID.',
        };
      }

      final String normalizedProofPath = proofFilePath.trim();

      if (normalizedProofPath.isEmpty) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Proof-of-delivery photo is required.',
        };
      }

      final String normalizedDeliveredTo = deliveredTo.trim();

      if (normalizedDeliveredTo.isEmpty) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Receiver name is required.',
        };
      }

      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final http.MultipartRequest request = http.MultipartRequest(
        'POST',
        Uri.parse(
          '$baseUrl/rider/orders/'
          '$shipmentId/proof',
        ),
      );

      request.headers.addAll({
        'Accept': 'application/json',
        'Authorization': 'Bearer ${token.trim()}',
      });

      request.fields['delivered_to'] = normalizedDeliveredTo;

      if (deliverySignature != null && deliverySignature.trim().isNotEmpty) {
        request.fields['delivery_signature'] = deliverySignature.trim();
      }

      if (deliveryNotes != null && deliveryNotes.trim().isNotEmpty) {
        request.fields['delivery_notes'] = deliveryNotes.trim();
      }

      request.files.add(
        await http.MultipartFile.fromPath(
          'proof_of_delivery',
          normalizedProofPath,
        ),
      );

      final http.StreamedResponse streamedResponse = await request.send();

      final http.Response response = await http.Response.fromStream(
        streamedResponse,
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        if (decoded is Map<String, dynamic>) {
          final dynamic rawData = decoded['data'];

          return {
            'success': true,
            'status_code': response.statusCode,
            'message': _getMessage(
              decoded,
              fallback: 'Delivery proof uploaded successfully.',
            ),
            'data': rawData is Map
                ? Map<String, dynamic>.from(rawData)
                : <String, dynamic>{},
          };
        }

        return {
          'success': true,
          'status_code': response.statusCode,
          'message': 'Delivery proof uploaded successfully.',
          'data': <String, dynamic>{},
        };
      }

      if (response.statusCode == 413) {
        return {
          'success': false,
          'status_code': 413,
          'message':
              'The proof photo is too large. '
              'Please use an image not larger than 5 MB.',
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,
          'message': _getMessage(
            decoded,
            fallback:
                'Please check the proof-of-delivery information '
                'and try again.',
          ),
          'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This shipment is not assigned '
            'to this rider.',
        notFoundMessage:
            'Rider proof-of-delivery API '
            'was not found.',
        fallback:
            'Unable to upload '
            'proof of delivery.',
      );
    } catch (_) {
      return {
        'success': false,
        'message':
            'Unable to upload proof of delivery. '
            'Please check your internet connection '
            'and try again.',
      };
    }
  }

  // ============================================================
  // UPDATE RIDER DELIVERY STATUS
  // PUT /api/rider/orders/{shipment}/status
  // ============================================================

  static Future<Map<String, dynamic>> updateRiderDeliveryStatus({
    required int shipmentId,
    required String status,
    String? failureReason,
    String? deliveryNotes,
  }) async {
    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      if (shipmentId <= 0) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Invalid shipment ID.',
        };
      }

      String normalizedStatus = status
          .trim()
          .toLowerCase()
          .replaceAll(' ', '_')
          .replaceAll('-', '_');

      if (normalizedStatus == 'failed') {
        normalizedStatus = 'delivery_failed';
      }

      const Set<String> allowedStatuses = <String>{
        'in_transit',
        'out_for_delivery',
        'delivered',
        'delivery_failed',
      };

      if (!allowedStatuses.contains(normalizedStatus)) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Invalid rider delivery status.',
        };
      }

      final String normalizedFailureReason = failureReason?.trim() ?? '';

      final String normalizedDeliveryNotes = deliveryNotes?.trim() ?? '';

      final Map<String, dynamic> requestBody = <String, dynamic>{
        'status': normalizedStatus,
      };

      if (normalizedDeliveryNotes.isNotEmpty) {
        requestBody['delivery_notes'] = normalizedDeliveryNotes;
      }

      if (normalizedStatus == 'delivery_failed') {
        final String reason = normalizedFailureReason.isNotEmpty
            ? normalizedFailureReason
            : normalizedDeliveryNotes.isNotEmpty
            ? normalizedDeliveryNotes
            : 'Delivery failed.';

        requestBody['failure_reason'] = reason;
      }

      final http.Response response = await http.put(
        Uri.parse(
          '$baseUrl/rider/orders/'
          '$shipmentId/status',
        ),
        headers: _authHeaders(token, json: true),
        body: jsonEncode(requestBody),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        if (decoded is Map<String, dynamic>) {
          final dynamic rawData = decoded['data'];

          return {
            'success': true,
            'status_code': response.statusCode,
            'message': _getMessage(
              decoded,
              fallback: 'Delivery status updated successfully.',
            ),
            'data': rawData is Map
                ? Map<String, dynamic>.from(rawData)
                : <String, dynamic>{},
          };
        }

        return {
          'success': true,
          'status_code': response.statusCode,
          'message': 'Delivery status updated successfully.',
          'data': <String, dynamic>{},
        };
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
        forbiddenMessage:
            'This shipment is not assigned '
            'to this rider.',
        notFoundMessage: 'Rider shipment was not found.',
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
  // GET /api/logistic/dashboard
  // ============================================================

  static Future<Map<String, dynamic>> getLogisticsDashboard() async {
    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final http.Response response = await http.get(
        Uri.parse('$baseUrl/logistic/dashboard'),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map<String, dynamic>) {
        final dynamic rawData = decoded['data'];

        if (rawData is Map) {
          final Map<String, dynamic> data = Map<String, dynamic>.from(rawData);

          return {
            'success': true,
            'status_code': 200,
            'data': data,
            'logistic': data['logistic'] is Map
                ? Map<String, dynamic>.from(data['logistic'])
                : <String, dynamic>{},
            'shipments': data['shipments'] is Map
                ? Map<String, dynamic>.from(data['shipments'])
                : <String, dynamic>{},
            'riders': data['riders'] is Map
                ? Map<String, dynamic>.from(data['riders'])
                : <String, dynamic>{},
            'hubs': data['hubs'] is Map
                ? Map<String, dynamic>.from(data['hubs'])
                : <String, dynamic>{},
            'alerts_count': data['alerts_count'] ?? 0,
            'recent_shipments': data['recent_shipments'] is List
                ? List<dynamic>.from(data['recent_shipments'])
                : <dynamic>[],
          };
        }

        return {
          'success': true,
          'status_code': 200,
          'data': <String, dynamic>{},
          'logistic': <String, dynamic>{},
          'shipments': <String, dynamic>{},
          'riders': <String, dynamic>{},
          'hubs': <String, dynamic>{},
          'alerts_count': 0,
          'recent_shipments': <dynamic>[],
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This account cannot access '
            'the logistics dashboard.',
        notFoundMessage:
            'Logistics dashboard endpoint was not found on the server. '
            'Please verify that the latest Laravel routes are deployed.',
        fallback:
            'Unable to load '
            'logistics dashboard.',
      );
    } catch (e) {
      return {
        'success': false,
        'message':
            'Unable to load '
            'logistics dashboard: $e',
      };
    }
  }

  // ============================================================
  // LOGISTICS SHIPMENTS
  // GET /api/logistic/shipments
  // ============================================================

  static Future<Map<String, dynamic>> getLogisticsShipments({
    String? status,
    String? sortingStatus,
    String? search,
    int page = 1,
    int perPage = 100,
  }) async {
    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final Map<String, String> queryParameters = {
        'page': page.toString(),
        'per_page': perPage.toString(),
      };

      if (status != null && status.trim().isNotEmpty) {
        queryParameters['status'] = status.trim();
      }

      if (sortingStatus != null && sortingStatus.trim().isNotEmpty) {
        queryParameters['sorting_status'] = sortingStatus.trim();
      }

      if (search != null && search.trim().isNotEmpty) {
        queryParameters['search'] = search.trim();
      }

      final Uri uri = Uri.parse('$baseUrl/logistic/shipments')
          .replace(queryParameters: queryParameters);

      final http.Response response = await http.get(
        uri,
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        return {
          'success': true,
          'status_code': 200,
          'data': _extractList(
            decoded,
            keys: const ['data', 'shipments', 'parcels'],
          ),
          'meta': decoded is Map<String, dynamic> && decoded['meta'] is Map
              ? Map<String, dynamic>.from(decoded['meta'])
              : <String, dynamic>{},
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This account cannot access '
            'logistics shipments.',
        notFoundMessage:
            'Logistics shipments endpoint was not found on the server. '
            'Please verify that the latest Laravel routes are deployed.',
        fallback:
            'Unable to load '
            'logistics shipments.',
      );
    } catch (e) {
      return {
        'success': false,
        'message':
            'Unable to load '
            'logistics shipments: $e',
      };
    }
  }

  // ============================================================
  // SINGLE LOGISTICS SHIPMENT
  // GET /api/logistic/shipments/{shipment}
  // ============================================================

  static Future<Map<String, dynamic>> getLogisticsShipment(
    int shipmentId,
  ) async {
    try {
      if (shipmentId <= 0) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Invalid shipment ID.',
        };
      }

      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final http.Response response = await http.get(
        Uri.parse('$baseUrl/logistic/shipments/$shipmentId'),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 && decoded is Map<String, dynamic>) {
        final dynamic rawData = decoded['data'];

        if (rawData is Map) {
          return {
            'success': true,
            'status_code': 200,
            'data': Map<String, dynamic>.from(rawData),
          };
        }

        return {
          'success': false,
          'status_code': 500,
          'message': 'Invalid shipment response from server.',
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This shipment does not belong '
            'to this Logistics account.',
        notFoundMessage: 'Logistics shipment was not found.',
        fallback:
            'Unable to load '
            'logistics shipment.',
      );
    } catch (e) {
      return {
        'success': false,
        'message':
            'Unable to load '
            'logistics shipment: $e',
      };
    }
  }

  // ============================================================
  // PROCESS LOGISTICS SHIPMENT
  // POST /api/logistic/shipments/{shipment}/{action}
  // ============================================================

  static Future<Map<String, dynamic>> processLogisticsShipment({
    required int shipmentId,
    required String action,
    String? deliveryZone,
    String? deliveryType,
    String? sortingArea,
    String? rackNumber,
  }) async {
    try {
      if (shipmentId <= 0) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Invalid shipment ID.',
        };
      }

      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      const Set<String> allowedActions = {'receive', 'scan', 'sort', 'stage'};

      final String normalizedAction = action.trim().toLowerCase();

      if (!allowedActions.contains(normalizedAction)) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Invalid logistics action.',
        };
      }

      final Map<String, dynamic> requestBody = <String, dynamic>{};

      if (normalizedAction == 'scan') {
        final String normalizedZone = deliveryZone?.trim() ?? '';

        final String normalizedType = deliveryType?.trim().toLowerCase() ?? '';

        const Set<String> allowedDeliveryTypes = {
          'standard',
          'same_day',
          'express',
        };

        if (normalizedZone.isEmpty) {
          return {
            'success': false,
            'status_code': 422,
            'message': 'Delivery zone is required.',
          };
        }

        if (!allowedDeliveryTypes.contains(normalizedType)) {
          return {
            'success': false,
            'status_code': 422,
            'message': 'Please select a valid delivery type.',
          };
        }

        requestBody['delivery_zone'] = normalizedZone;

        requestBody['delivery_type'] = normalizedType;
      }

      if (normalizedAction == 'sort') {
        final String normalizedArea = sortingArea?.trim() ?? '';

        final String normalizedRack = rackNumber?.trim() ?? '';

        if (normalizedArea.isEmpty) {
          return {
            'success': false,
            'status_code': 422,
            'message': 'Sorting area is required.',
          };
        }

        if (normalizedRack.isEmpty) {
          return {
            'success': false,
            'status_code': 422,
            'message': 'Rack number is required.',
          };
        }

        requestBody['sorting_area'] = normalizedArea;

        requestBody['rack_number'] = normalizedRack;
      }

      final http.Response response = await http.post(
        Uri.parse(
          '$baseUrl/logistic/shipments/'
          '$shipmentId/$normalizedAction',
        ),
        headers: _authHeaders(token, json: true),
        body: jsonEncode(requestBody),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        if (decoded is Map<String, dynamic>) {
          final dynamic rawData = decoded['data'];

          return {
            'success': true,
            'status_code': response.statusCode,
            'message': _getMessage(
              decoded,
              fallback: 'Parcel updated successfully.',
            ),
            'data': rawData is Map
                ? Map<String, dynamic>.from(rawData)
                : <String, dynamic>{},
          };
        }

        return {
          'success': true,
          'status_code': response.statusCode,
          'message': 'Parcel updated successfully.',
          'data': <String, dynamic>{},
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,
          'message': _getMessage(
            decoded,
            fallback:
                'This parcel cannot perform '
                'that logistics action.',
          ),
          'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This account cannot process '
            'this shipment.',
        notFoundMessage:
            'The requested Logistics parcel action was not found on the server. '
            'Please verify that the latest Laravel routes are deployed.',
        fallback:
            'Unable to process '
            'logistics shipment.',
      );
    } catch (e) {
      return {
        'success': false,
        'message':
            'Unable to process '
            'logistics shipment: $e',
      };
    }
  }

  // ============================================================
  // LOGISTICS AVAILABLE FINAL-MILE RIDERS
  // GET /api/logistic/shipments/{shipment}/available-riders
  // ============================================================

  static Future<Map<String, dynamic>> getLogisticsAvailableRiders({
    required int shipmentId,
  }) async {
    try {
      if (shipmentId <= 0) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Invalid shipment ID.',
        };
      }

      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final http.Response response = await http.get(
        Uri.parse(
          '$baseUrl/logistic/shipments/'
          '$shipmentId/available-riders',
        ),
        headers: _authHeaders(token),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200) {
        final List<dynamic> riders = _extractList(
          decoded,
          keys: const <String>[
            'data',
            'riders',
            'available_riders',
            'eligible_riders',
          ],
        );

        return {
          'success': true,
          'status_code': 200,
          'data': riders,
          'riders': riders,
          'meta': _extractMeta(decoded),
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This Logistics account cannot view '
            'available riders for this shipment.',
        notFoundMessage:
            'Available-riders API is not available yet. '
            'Please add the Laravel endpoint for eligible '
            'final-mile riders.',
        fallback: 'Unable to load available final-mile riders.',
      );
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to load available final-mile riders: $e',
      };
    }
  }

  // ============================================================
  // LOGISTICS ASSIGN FINAL-MILE RIDER
  // POST /api/logistic/shipments/{shipment}/assign
  // ============================================================

  static Future<Map<String, dynamic>> assignLogisticsFinalMileRider({
    required int shipmentId,
    required int riderId,
  }) async {
    try {
      if (shipmentId <= 0) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Invalid shipment ID.',
        };
      }

      if (riderId <= 0) {
        return {
          'success': false,
          'status_code': 422,
          'message': 'Please select a valid rider.',
        };
      }

      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return {
          'success': false,
          'status_code': 401,
          'message': 'Authentication token not found.',
        };
      }

      final http.Response response = await http.post(
        Uri.parse(
          '$baseUrl/logistic/shipments/'
          '$shipmentId/assign',
        ),
        headers: _authHeaders(token, json: true),
        body: jsonEncode(<String, dynamic>{'rider_id': riderId}),
      );

      final dynamic decoded = _decodeResponse(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        final Map<String, dynamic> root = decoded is Map<String, dynamic>
            ? _extractMapRoot(decoded)
            : <String, dynamic>{};

        Map<String, dynamic> shipment = _extractMapByKeys(root, const <String>[
          'shipment',
          'parcel',
          'order',
        ]);

        if (shipment.isEmpty &&
            (root.containsKey('tracking_number') ||
                root.containsKey('shipment_id') ||
                root.containsKey('id'))) {
          shipment = root;
        }

        final Map<String, dynamic> rider = _extractMapByKeys(
          root,
          const <String>['rider', 'assigned_rider', 'delivery_rider'],
        );

        return {
          'success': true,
          'status_code': response.statusCode,
          'message': _getMessage(
            decoded,
            fallback: 'Final-mile rider assigned successfully.',
          ),
          'data': root,
          'shipment': shipment,
          'rider': rider,
        };
      }

      if (response.statusCode == 422) {
        return {
          'success': false,
          'status_code': 422,
          'message': _getMessage(
            decoded,
            fallback:
                'This rider cannot be assigned '
                'to the selected shipment.',
          ),
          'errors': decoded is Map<String, dynamic> ? decoded['errors'] : null,
        };
      }

      return _authApiError(
        response.statusCode,
        decoded,
        forbiddenMessage:
            'This Logistics account cannot assign '
            'a rider to this shipment.',
        notFoundMessage:
            'Final-mile rider assignment API was not found. '
            'Please verify the latest Laravel routes.',
        fallback: 'Unable to assign final-mile rider.',
      );
    } catch (e) {
      return {
        'success': false,
        'message': 'Unable to assign final-mile rider: $e',
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

      final Uri uri = Uri.parse('$baseUrl/products')
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
          fallback:
              'Unable to load '
              'product details.',
        ),
      };
    } catch (e) {
      return {
        'success': false,
        'message':
            'Unable to load '
            'product details: $e',
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

    if (decoded is! Map) {
      return <dynamic>[];
    }

    final Map<String, dynamic> map = Map<String, dynamic>.from(decoded);

    for (final String key in keys) {
      final dynamic value = map[key];

      if (value is List) {
        return List<dynamic>.from(value);
      }

      if (value is Map) {
        final List<dynamic> nested = _extractList(value, keys: keys);

        if (nested.isNotEmpty) {
          return nested;
        }
      }
    }

    final dynamic data = map['data'];

    if (data is List) {
      return List<dynamic>.from(data);
    }

    if (data is Map) {
      final List<dynamic> nested = _extractList(data, keys: keys);

      if (nested.isNotEmpty) {
        return nested;
      }
    }

    return <dynamic>[];
  }

  static Map<String, dynamic> _extractMapRoot(Map<String, dynamic> decoded) {
    final dynamic data = decoded['data'];

    if (data is Map) {
      return Map<String, dynamic>.from(data);
    }

    return Map<String, dynamic>.from(decoded);
  }

  static Map<String, dynamic> _extractMapByKeys(
    Map<String, dynamic> source,
    List<String> keys,
  ) {
    for (final String key in keys) {
      final dynamic value = source[key];

      if (value is Map) {
        return Map<String, dynamic>.from(value);
      }
    }

    return <String, dynamic>{};
  }

  static Map<String, dynamic> _extractMeta(dynamic decoded) {
    if (decoded is! Map) {
      return <String, dynamic>{};
    }

    final Map<String, dynamic> map = Map<String, dynamic>.from(decoded);

    final dynamic directMeta = map['meta'];

    if (directMeta is Map) {
      return Map<String, dynamic>.from(directMeta);
    }

    final dynamic data = map['data'];

    if (data is Map) {
      final Map<String, dynamic> nested = Map<String, dynamic>.from(data);

      final dynamic nestedMeta = nested['meta'];

      if (nestedMeta is Map) {
        return Map<String, dynamic>.from(nestedMeta);
      }

      final Map<String, dynamic> pagination = <String, dynamic>{};

      for (final String key in <String>[
        'current_page',
        'last_page',
        'per_page',
        'total',
        'from',
        'to',
      ]) {
        if (nested.containsKey(key)) {
          pagination[key] = nested[key];
        }
      }

      if (pagination.isNotEmpty) {
        return pagination;
      }
    }

    return <String, dynamic>{};
  }

  static String _firstString(List<dynamic> values) {
    for (final dynamic value in values) {
      final String text = value?.toString().trim() ?? '';

      if (text.isNotEmpty && text.toLowerCase() != 'null') {
        return text;
      }
    }

    return '';
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
        'message':
            'Session expired. '
            'Please login again.',
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
