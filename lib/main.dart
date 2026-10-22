import 'package:flutter/material.dart';

import 'theme/app_theme.dart';
import 'pages/auth/login_page.dart';
import 'pages/buyer/buyer_navigation.dart';
import 'pages/rider/rider_navigation.dart';
import 'pages/logistics/logistics_navigation.dart';
import 'sevices/auth_storage.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const BiliHubApp());
}

class BiliHubApp extends StatelessWidget {
  const BiliHubApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'BiliHub',
      theme: AppTheme.lightTheme,
      home: const _SessionGate(),
    );
  }
}

class _SessionGate extends StatefulWidget {
  const _SessionGate();

  @override
  State<_SessionGate> createState() => _SessionGateState();
}

class _SessionGateState extends State<_SessionGate> {
  Widget? _destination;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    final rememberMe = await AuthStorage.getRememberMe();

    if (!rememberMe) {
      await AuthStorage.clearSession();
      if (!mounted) return;
      setState(() => _destination = const LoginPage());
      return;
    }

    final token = await AuthStorage.getToken();
    final user = await AuthStorage.getUser();

    if (token == null || token.trim().isEmpty || user == null) {
      await AuthStorage.clearSession();
      if (!mounted) return;
      setState(() => _destination = const LoginPage());
      return;
    }

    final role = _extractRole(user);
    Widget destination;

    switch (role) {
      case 'buyer':
        destination = BuyerNavigation(user: user);
        break;
      case 'rider':
        destination = const RiderNavigation();
        break;
      case 'logistics':
      case 'logistic_owner':
        destination = const LogisticsNavigation();
        break;
      default:
        await AuthStorage.clearSession();
        destination = const LoginPage();
    }

    if (!mounted) return;
    setState(() => _destination = destination);
  }

  String? _extractRole(Map<String, dynamic> user) {
    final roles = user['roles'];
    if (roles is List && roles.isNotEmpty) {
      final firstRole = roles.first;
      if (firstRole is Map) {
        return firstRole['name']?.toString().toLowerCase();
      }
      return firstRole.toString().toLowerCase();
    }

    return user['role']?.toString().toLowerCase();
  }

  @override
  Widget build(BuildContext context) {
    final destination = _destination;

    if (destination != null) {
      return destination;
    }

    return const Scaffold(
      body: Center(child: CircularProgressIndicator()),
    );
  }
}
