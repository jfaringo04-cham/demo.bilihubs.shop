import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AuthStorage {
  static const FlutterSecureStorage _storage = FlutterSecureStorage();

  static const String _tokenKey = 'auth_token';
  static const String _userKey = 'auth_user';
  static const String _rememberKey = 'remember_me';

  // ============================================================
  // TOKEN
  // ============================================================

  // Save Laravel Sanctum token.
  static Future<void> saveToken(String token) async {
    await _storage.write(key: _tokenKey, value: token);
  }

  // Get saved Laravel Sanctum token.
  static Future<String?> getToken() async {
    return _storage.read(key: _tokenKey);
  }

  // ============================================================
  // USER
  // ============================================================

  // Save logged-in user.
  static Future<void> saveUser(Map<String, dynamic> user) async {
    await _storage.write(key: _userKey, value: jsonEncode(user));
  }

  // Get logged-in user.
  static Future<Map<String, dynamic>?> getUser() async {
    final userJson = await _storage.read(key: _userKey);

    if (userJson == null || userJson.isEmpty) {
      return null;
    }

    try {
      final decoded = jsonDecode(userJson);

      if (decoded is Map<String, dynamic>) {
        return decoded;
      }

      if (decoded is Map) {
        return Map<String, dynamic>.from(decoded);
      }

      return null;
    } catch (e) {
      return null;
    }
  }

  // ============================================================
  // UPDATE USER
  // ============================================================

  // Update fields of the saved logged-in user.
  //
  // Example:
  // await AuthStorage.updateUser({
  //   'name': 'Juan Dela Cruz',
  //   'phone': '09123456789',
  //   'address': 'Calamba, Laguna',
  // });
  static Future<void> updateUser(Map<String, dynamic> updatedData) async {
    final currentUser = await getUser();

    if (currentUser == null) {
      return;
    }

    final updatedUser = <String, dynamic>{...currentUser, ...updatedData};

    await saveUser(updatedUser);
  }


  // ============================================================
  // REMEMBER ME
  // ============================================================

  static Future<void> saveRememberMe(bool value) async {
    await _storage.write(key: _rememberKey, value: value ? '1' : '0');
  }

  static Future<bool> getRememberMe() async {
    final value = await _storage.read(key: _rememberKey);
    return value == '1';
  }

  // ============================================================
  // SESSION
  // ============================================================

  // Check if user has a saved login session.
  static Future<bool> isLoggedIn() async {
    final token = await getToken();

    return token != null && token.isNotEmpty;
  }

  // Remove login session.
  static Future<void> clearSession() async {
    await _storage.delete(key: _tokenKey);

    await _storage.delete(key: _userKey);

    await _storage.delete(key: _rememberKey);
  }
}
