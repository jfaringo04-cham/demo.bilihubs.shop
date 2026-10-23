import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AuthStorage {
  static const FlutterSecureStorage _storage = FlutterSecureStorage();

  static const String _tokenKey = 'auth_token';
  static const String _userKey = 'auth_user';
  static const String _rememberMeKey = 'remember_me';

  // ============================================================
  // TOKEN
  // ============================================================

  static Future<void> saveToken(String token) async {
    final String normalizedToken = token.trim();

    if (normalizedToken.isEmpty) {
      return;
    }

    await _storage.write(key: _tokenKey, value: normalizedToken);
  }

  static Future<String?> getToken() async {
    final String? token = await _storage.read(key: _tokenKey);

    if (token == null || token.trim().isEmpty) {
      return null;
    }

    return token.trim();
  }

  // ============================================================
  // USER
  // ============================================================

  static Future<void> saveUser(Map<String, dynamic> user) async {
    if (user.isEmpty) {
      return;
    }

    await _storage.write(key: _userKey, value: jsonEncode(user));
  }

  static Future<Map<String, dynamic>?> getUser() async {
    final String? userJson = await _storage.read(key: _userKey);

    if (userJson == null || userJson.trim().isEmpty) {
      return null;
    }

    try {
      final dynamic decoded = jsonDecode(userJson);

      if (decoded is Map<String, dynamic>) {
        return decoded;
      }

      if (decoded is Map) {
        return Map<String, dynamic>.from(decoded);
      }

      return null;
    } catch (_) {
      return null;
    }
  }

  // ============================================================
  // UPDATE USER
  // ============================================================

  static Future<void> updateUser(Map<String, dynamic> updatedData) async {
    if (updatedData.isEmpty) {
      return;
    }

    final Map<String, dynamic>? currentUser = await getUser();

    if (currentUser == null) {
      return;
    }

    final Map<String, dynamic> updatedUser = {...currentUser, ...updatedData};

    await saveUser(updatedUser);
  }

  // ============================================================
  // REMEMBER ME
  // ============================================================

  static Future<void> setRememberMe(bool remember) async {
    await _storage.write(
      key: _rememberMeKey,
      value: remember ? 'true' : 'false',
    );
  }

  static Future<bool> getRememberMe() async {
    final String? value = await _storage.read(key: _rememberMeKey);

    return value == 'true';
  }

  static Future<void> clearRememberMe() async {
    await _storage.delete(key: _rememberMeKey);
  }

  // ============================================================
  // SESSION
  // ============================================================

  static Future<bool> isLoggedIn() async {
    final String? token = await getToken();

    final Map<String, dynamic>? user = await getUser();

    return token != null && token.isNotEmpty && user != null && user.isNotEmpty;
  }

  // ============================================================
  // SAVE COMPLETE SESSION
  // ============================================================

  static Future<void> saveSession({
    required String token,
    required Map<String, dynamic> user,
    required bool rememberMe,
  }) async {
    await saveToken(token);
    await saveUser(user);
    await setRememberMe(rememberMe);
  }

  // ============================================================
  // CLEAR LOGIN SESSION
  // ============================================================

  static Future<void> clearSession({bool clearRememberMe = true}) async {
    await _storage.delete(key: _tokenKey);

    await _storage.delete(key: _userKey);

    if (clearRememberMe) {
      await _storage.delete(key: _rememberMeKey);
    }
  }
}
