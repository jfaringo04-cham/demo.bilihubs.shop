import 'package:flutter/material.dart';

class RiderColors {
  static const Color background = Color(0xffF7F7F7);

  static const Color primary = Color(0xff2563EB);

  static const Color textDark = Color(0xff1F2937);

  static const Color online = Color(0xff22C55E);

  static const Color earnings = Color(0xffF59E0B);
}

class RiderStyle {
  static const TextStyle title = TextStyle(
    fontSize: 22,

    fontWeight: FontWeight.bold,

    color: RiderColors.textDark,
  );

  static const TextStyle sectionTitle = TextStyle(
    fontSize: 18,

    fontWeight: FontWeight.bold,

    color: RiderColors.textDark,
  );

  static const TextStyle cardTitle = TextStyle(
    fontSize: 16,

    fontWeight: FontWeight.w600,

    color: RiderColors.textDark,
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
