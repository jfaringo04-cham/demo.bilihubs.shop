class DateFormatter {
  const DateFormatter._();

  static String dateTime(dynamic value, {String fallback = '—'}) {
    final DateTime? date = _toDateTime(value);

    if (date == null) {
      return fallback;
    }

    final DateTime local = date.toLocal();

    final String month = _monthName(local.month);

    final int hour = local.hour == 0
        ? 12
        : local.hour > 12
        ? local.hour - 12
        : local.hour;

    final String minute = local.minute.toString().padLeft(2, '0');

    final String period = local.hour >= 12 ? 'PM' : 'AM';

    return '$month ${local.day}, ${local.year} • '
        '$hour:$minute $period';
  }

  static String dateOnly(dynamic value, {String fallback = '—'}) {
    final DateTime? date = _toDateTime(value);

    if (date == null) {
      return fallback;
    }

    final DateTime local = date.toLocal();

    return '${_monthName(local.month)} '
        '${local.day}, ${local.year}';
  }

  static String shortDate(dynamic value, {String fallback = '—'}) {
    final DateTime? date = _toDateTime(value);

    if (date == null) {
      return fallback;
    }

    final DateTime local = date.toLocal();

    final String month = local.month.toString().padLeft(2, '0');

    final String day = local.day.toString().padLeft(2, '0');

    return '$month/$day/${local.year}';
  }

  static String timeOnly(dynamic value, {String fallback = '—'}) {
    final DateTime? date = _toDateTime(value);

    if (date == null) {
      return fallback;
    }

    final DateTime local = date.toLocal();

    final int hour = local.hour == 0
        ? 12
        : local.hour > 12
        ? local.hour - 12
        : local.hour;

    final String minute = local.minute.toString().padLeft(2, '0');

    final String period = local.hour >= 12 ? 'PM' : 'AM';

    return '$hour:$minute $period';
  }

  static DateTime? value(dynamic rawValue) {
    return _toDateTime(rawValue);
  }

  static DateTime? _toDateTime(dynamic value) {
    if (value == null) {
      return null;
    }

    if (value is DateTime) {
      return value;
    }

    final String text = value.toString().trim();

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return null;
    }

    return DateTime.tryParse(text);
  }

  static String _monthName(int month) {
    const List<String> months = <String>[
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];

    if (month < 1 || month > 12) {
      return '';
    }

    return months[month - 1];
  }
}
