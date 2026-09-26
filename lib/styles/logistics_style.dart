import 'package:flutter/material.dart';

class LogisticsColors {
  static const Color background = Color(0xffF7F7F7);

  static const Color primary = Color(0xff2563EB);
}

class LogisticsStyle {
  static const TextStyle title = TextStyle(
    fontSize: 22,

    fontWeight: FontWeight.bold,

    color: Colors.black,
  );

  static const TextStyle sectionTitle = TextStyle(
    fontSize: 18,

    fontWeight: FontWeight.bold,

    color: Colors.black,
  );

  static const TextStyle cardTitle = TextStyle(
    fontSize: 16,

    fontWeight: FontWeight.w600,

    color: Colors.black,
  );

  static const TextStyle small = TextStyle(fontSize: 13, color: Colors.grey);

  static BoxDecoration card = BoxDecoration(
    color: Colors.white,

    borderRadius: BorderRadius.circular(18),

    boxShadow: [
      BoxShadow(color: Colors.black12, blurRadius: 8, offset: Offset(0, 3)),
    ],
  );
}
