import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import 'api_services.dart';
import 'auth_storage.dart';

class LogisticsCodApi {
  static const Duration _timeout = Duration(seconds: 30);

  static Future<Map<String, dynamic>> getRiderRemittances({int page = 1}) {
    return _request(
      method: 'GET',
      path: '/logistic/cod-remittances',
      query: <String, String>{'page': page.toString(), 'per_page': '50'},
    );
  }

  static Future<Map<String, dynamic>> confirmRiderRemittance(int remittanceId) {
    if (remittanceId <= 0) {
      return Future<Map<String, dynamic>>.value(<String, dynamic>{
        'success': false,
        'message': 'Invalid remittance ID.',
      });
    }

    return _request(
      method: 'POST',
      path: '/logistic/cod-remittances/$remittanceId/confirm',
    );
  }

  static Future<Map<String, dynamic>> rejectRiderRemittance({
    required int remittanceId,
    required String reason,
  }) {
    if (remittanceId <= 0 || reason.trim().isEmpty) {
      return Future<Map<String, dynamic>>.value(<String, dynamic>{
        'success': false,
        'message': 'A valid remittance and rejection reason are required.',
      });
    }

    return _request(
      method: 'POST',
      path: '/logistic/cod-remittances/$remittanceId/reject',
      body: <String, dynamic>{'rejection_reason': reason.trim()},
    );
  }

  static Future<Map<String, dynamic>> getAdminRemittances({int page = 1}) {
    return _request(
      method: 'GET',
      path: '/logistic/cod-admin-remittances',
      query: <String, String>{'page': page.toString(), 'per_page': '50'},
    );
  }

  static Future<Map<String, dynamic>> submitToAdmin({
    required String amount,
    required String transferMethod,
    String? transferReference,
    String? notes,
  }) {
    final String normalizedAmount = amount.trim();

    final bool validAmount =
        RegExp(r'^(?:0|[1-9]\d*)(?:\.\d{1,2})?$').hasMatch(normalizedAmount) &&
        (double.tryParse(normalizedAmount) ?? 0) > 0;

    if (!validAmount) {
      return Future<Map<String, dynamic>>.value(<String, dynamic>{
        'success': false,
        'message': 'Enter a valid amount with up to two decimals.',
      });
    }

    const Set<String> allowedMethods = <String>{
      'cash',
      'bank_transfer',
      'gcash',
      'maya',
      'other',
    };

    if (!allowedMethods.contains(transferMethod)) {
      return Future<Map<String, dynamic>>.value(<String, dynamic>{
        'success': false,
        'message': 'Invalid transfer method.',
      });
    }

    return _request(
      method: 'POST',
      path: '/logistic/cod-admin-remittances',
      body: <String, dynamic>{
        'amount': normalizedAmount,
        'transfer_method': transferMethod,
        if ((transferReference ?? '').trim().isNotEmpty)
          'transfer_reference': transferReference!.trim(),
        if ((notes ?? '').trim().isNotEmpty) 'notes': notes!.trim(),
      },
    );
  }

  static Future<Map<String, dynamic>> _request({
    required String method,
    required String path,
    Map<String, String>? query,
    Map<String, dynamic>? body,
  }) async {
    bool requestStarted = false;

    try {
      final String? token = await AuthStorage.getToken();

      if (token == null || token.trim().isEmpty) {
        return <String, dynamic>{
          'success': false,
          'status_code': 401,
          'message': 'Session expired. Please log in again.',
        };
      }

      final Uri uri = Uri.parse('${ApiService.baseUrl}$path')
          .replace(queryParameters: query);

      final Map<String, String> headers = <String, String>{
        'Accept': 'application/json',
        'Authorization': 'Bearer ${token.trim()}',
        if (method == 'POST') 'Content-Type': 'application/json',
      };

      late final http.Response response;

      requestStarted = true;

      if (method == 'GET') {
        response = await http.get(uri, headers: headers).timeout(_timeout);
      } else {
        response = await http
            .post(
              uri,
              headers: headers,
              body: jsonEncode(body ?? <String, dynamic>{}),
            )
            .timeout(_timeout);
      }

      dynamic decoded;

      try {
        decoded = jsonDecode(response.body);
      } catch (_) {
        decoded = null;
      }

      final Map<String, dynamic> result = decoded is Map
          ? Map<String, dynamic>.from(decoded)
          : <String, dynamic>{};

      if (response.statusCode >= 200 && response.statusCode < 300) {
        if (decoded is! Map || result['success'] != true) {
          return <String, dynamic>{
            'success': false,
            'status_code': response.statusCode,
            'result_uncertain': method == 'POST',
            'message':
                'Unable to verify the server response. '
                'Refresh the remittance status.',
          };
        }

        return <String, dynamic>{
          ...result,
          'success': true,
          'status_code': response.statusCode,
        };
      }

      final String serverMessage = result['message']?.toString().trim() ?? '';

      String fallback;

      switch (response.statusCode) {
        case 401:
          fallback = 'Session expired. Please log in again.';
        case 403:
          fallback = 'You are not authorized to perform this action.';
        case 404:
          fallback =
              'COD endpoint was not found. '
              'Check that the Laravel backend is deployed.';
        case 409:
        case 422:
          fallback =
              'This remittance cannot be processed. '
              'Refresh its status.';
        default:
          fallback = 'Unable to process the COD request.';
      }

      return <String, dynamic>{
        'success': false,
        'status_code': response.statusCode,
        'message': serverMessage.isNotEmpty ? serverMessage : fallback,
        'errors': result['errors'],
      };
    } on TimeoutException {
      return <String, dynamic>{
        'success': false,
        'result_uncertain': method == 'POST' && requestStarted,
        'message': method == 'POST'
            ? 'The request timed out. It may have been processed. '
                  'Refresh the remittance status before trying again.'
            : 'Loading COD information timed out. Please refresh.',
      };
    } catch (error) {
      debugPrint('Logistics COD API error: $error');

      return <String, dynamic>{
        'success': false,
        'result_uncertain': method == 'POST' && requestStarted,
        'message': method == 'POST' && requestStarted
            ? 'The result could not be confirmed. '
                  'Refresh the remittance status before trying again.'
            : 'Unable to connect to the server.',
      };
    }
  }
}
