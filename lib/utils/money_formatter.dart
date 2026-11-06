class MoneyFormatter {
  const MoneyFormatter._();

  static String peso(
    dynamic value, {
    String fallback = '₱0.00',
  }) {
    final double? amount = _toDouble(value);

    if (amount == null) {
      return fallback;
    }

    return '₱${amount.toStringAsFixed(2)}';
  }

  static String pesoNoDecimals(
    dynamic value, {
    String fallback = '₱0',
  }) {
    final double? amount = _toDouble(value);

    if (amount == null) {
      return fallback;
    }

    return '₱${amount.toStringAsFixed(0)}';
  }

  static String number(
    dynamic value, {
    int decimalPlaces = 2,
    String fallback = '0.00',
  }) {
    final double? amount = _toDouble(value);

    if (amount == null) {
      return fallback;
    }

    return amount.toStringAsFixed(decimalPlaces);
  }

  static String signedPeso(
    dynamic value, {
    bool showPlusSign = false,
    String fallback = '₱0.00',
  }) {
    final double? amount = _toDouble(value);

    if (amount == null) {
      return fallback;
    }

    if (amount > 0 && showPlusSign) {
      return '+₱${amount.toStringAsFixed(2)}';
    }

    if (amount < 0) {
      return '-₱${amount.abs().toStringAsFixed(2)}';
    }

    return '₱${amount.toStringAsFixed(2)}';
  }

  static double? value(dynamic rawValue) {
    return _toDouble(rawValue);
  }

  static double? _toDouble(dynamic value) {
    if (value == null) {
      return null;
    }

    if (value is double) {
      return value;
    }

    if (value is int) {
      return value.toDouble();
    }

    if (value is num) {
      return value.toDouble();
    }

    final String text = value.toString().trim();

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return null;
    }

    final String cleaned = text
        .replaceAll('₱', '')
        .replaceAll(',', '')
        .replaceAll('PHP', '')
        .replaceAll('php', '')
        .trim();

    if (cleaned.isEmpty) {
      return null;
    }

    return double.tryParse(cleaned);
  }
}