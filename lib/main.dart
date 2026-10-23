import 'package:flutter/material.dart';

import 'theme/app_theme.dart';
import 'pages/auth/login_page.dart';

void main() {
  runApp(const BiliHubApp());
}

class BiliHubApp extends StatelessWidget {
  const BiliHubApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,

      title: "BiliHub",

      theme: AppTheme.lightTheme,

      home: const LoginPage(),
    );
  }
}
