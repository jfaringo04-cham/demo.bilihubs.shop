import 'dart:convert';

class AddressFormatter {
  const AddressFormatter._();

  static String clean(dynamic value) {
    final String address = _fromDynamic(value);

    if (address.isEmpty) {
      return '';
    }

    return _organize(address);
  }

  static List<String> lines(dynamic value) {
    final String cleaned = clean(value);

    if (cleaned.isEmpty) {
      return <String>['Address unavailable'];
    }

    return cleaned
        .split('\n')
        .map((String line) => line.trim())
        .where((String line) => line.isNotEmpty)
        .toList();
  }

  static String display(
    dynamic value, {
    String fallback = 'Address unavailable',
  }) {
    final String cleaned = clean(value);

    if (cleaned.isEmpty) {
      return fallback;
    }

    return cleaned;
  }

  static String _fromDynamic(dynamic value) {
    if (value == null) {
      return '';
    }

    if (value is String) {
      final String text = value.trim();

      if (text.isEmpty || text.toLowerCase() == 'null') {
        return '';
      }

      if (_looksLikeJson(text)) {
        try {
          final dynamic decoded = jsonDecode(text);

          return _fromDynamic(decoded);
        } catch (_) {
          return text;
        }
      }

      return text;
    }

    if (value is num) {
      return value.toString();
    }

    if (value is List) {
      for (final dynamic item in value) {
        final String address = _fromDynamic(item);

        if (address.isNotEmpty) {
          return address;
        }
      }

      return '';
    }

    if (value is! Map) {
      return '';
    }

    final Map<String, dynamic> map = Map<String, dynamic>.from(value);

    for (final String key in <String>[
      'full_address',
      'formatted_address',
      'complete_address',
      'delivery_address',
      'shipping_address',
      'pickup_address',
      'seller_address',
      'recipient_address',
      'buyer_address',
      'address_line',
      'address',
    ]) {
      final dynamic candidate = map[key];

      if (candidate == null) {
        continue;
      }

      final String address = _fromDynamic(candidate);

      if (address.isNotEmpty) {
        return address;
      }
    }

    final List<String> parts = <String>[];

    void addPart(dynamic rawPart) {
      final String part = rawPart?.toString().trim() ?? '';

      if (part.isEmpty || part.toLowerCase() == 'null') {
        return;
      }

      final bool duplicate = parts.any(
        (String existing) =>
            existing.toLowerCase().trim() == part.toLowerCase().trim(),
      );

      if (!duplicate) {
        parts.add(part);
      }
    }

    addPart(map['house_number']);
    addPart(map['unit']);
    addPart(map['building']);
    addPart(map['street_address']);
    addPart(map['street']);
    addPart(map['subdivision']);
    addPart(map['village']);
    addPart(map['sitio']);
    addPart(map['purok']);
    addPart(map['barangay']);
    addPart(map['district']);
    addPart(map['city']);
    addPart(map['municipality']);
    addPart(map['province']);
    addPart(map['region']);
    addPart(map['postal_code']);
    addPart(map['zip_code']);
    addPart(map['country']);

    return parts.join(', ');
  }

  static bool _looksLikeJson(String value) {
    final String text = value.trim();

    return (text.startsWith('{') && text.endsWith('}')) ||
        (text.startsWith('[') && text.endsWith(']'));
  }

  static String _organize(String value) {
    String address = value.trim();

    if (address.isEmpty || address.toLowerCase() == 'null') {
      return '';
    }

    if (_looksLikeJson(address)) {
      try {
        final dynamic decoded = jsonDecode(address);

        final String extracted = _fromDynamic(decoded);

        if (extracted.isNotEmpty) {
          address = extracted;
        }
      } catch (_) {
        // Keep the original text if it is not valid JSON.
      }
    }

    address = address
        .replaceAll(RegExp(r'[\r\n]+'), ',')
        .replaceAll(RegExp(r'\s+'), ' ')
        .replaceAll(RegExp(r',\s*,+'), ',')
        .replaceAll(RegExp(r'^\s*,+|,+\s*$'), '');

    final List<String> rawParts = address
        .split(',')
        .map((String part) => part.trim())
        .where((String part) => part.isNotEmpty)
        .toList();

    final List<String> uniqueParts = <String>[];

    for (final String part in rawParts) {
      final String normalized = part.toLowerCase().trim();

      final bool duplicate = uniqueParts.any(
        (String existing) => existing.toLowerCase().trim() == normalized,
      );

      if (!duplicate) {
        uniqueParts.add(part);
      }
    }

    if (uniqueParts.isEmpty) {
      return '';
    }

    final List<String> lines = <String>[];

    for (int index = 0; index < uniqueParts.length; index += 2) {
      if (index + 1 < uniqueParts.length) {
        lines.add('${uniqueParts[index]}, ${uniqueParts[index + 1]}');
      } else {
        lines.add(uniqueParts[index]);
      }
    }

    return lines.join('\n');
  }
}
