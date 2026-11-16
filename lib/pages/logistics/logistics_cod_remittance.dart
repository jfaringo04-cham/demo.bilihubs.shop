import 'package:flutter/material.dart';

import '../../services/logistics_cod_api.dart';
import '../../styles/logistics_style.dart';

class LogisticsCodRemittance extends StatefulWidget {
  const LogisticsCodRemittance({super.key});

  @override
  State<LogisticsCodRemittance> createState() => _LogisticsCodRemittanceState();
}

class _LogisticsCodRemittanceState extends State<LogisticsCodRemittance> {
  bool _loading = true;
  bool _processing = false;

  String? _error;

  List<Map<String, dynamic>> _riderRemittances = [];
  List<Map<String, dynamic>> _adminRemittances = [];

  Map<String, dynamic> _custodyWallet = {};

  final TextEditingController _amountController = TextEditingController();

  final TextEditingController _referenceController = TextEditingController();

  final TextEditingController _notesController = TextEditingController();

  String _transferMethod = 'cash';

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _amountController.dispose();
    _referenceController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Map<String, dynamic> _map(dynamic value) {
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }

    return <String, dynamic>{};
  }

  List<Map<String, dynamic>> _list(dynamic value) {
    if (value is List) {
      return value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();
    }

    if (value is Map) {
      final Map<String, dynamic> map = _map(value);

      if (map['data'] is List) {
        return _list(map['data']);
      }

      if (map['items'] is List) {
        return _list(map['items']);
      }
    }

    return <Map<String, dynamic>>[];
  }

  List<Map<String, dynamic>> _findList(
    Map<String, dynamic> data,
    List<String> keys,
  ) {
    for (final String key in keys) {
      if (data.containsKey(key)) {
        return _list(data[key]);
      }
    }

    return <Map<String, dynamic>>[];
  }

  int _integer(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();

    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  int _minor(Map<String, dynamic> item) {
    return _integer(
      item['total_minor'] ?? item['amount_minor'] ?? item['balance_minor'] ?? 0,
    );
  }

  String _peso(int minor) {
    final bool negative = minor < 0;
    final int absolute = minor.abs();

    final String whole = (absolute ~/ 100).toString().replaceAllMapped(
      RegExp(r'\B(?=(\d{3})+(?!\d))'),
      (match) => ',',
    );

    final String cents = (absolute % 100).toString().padLeft(2, '0');

    return '${negative ? '-' : ''}₱$whole.$cents';
  }

  String _text(dynamic value, {String fallback = '—'}) {
    final String result = value?.toString().trim() ?? '';

    if (result.isEmpty || result == 'null') {
      return fallback;
    }

    return result;
  }

  String _date(dynamic value) {
    final String raw = _text(value, fallback: '');

    final DateTime? parsed = DateTime.tryParse(raw);

    if (parsed == null) {
      return raw.isEmpty ? 'Not available' : raw;
    }

    final DateTime local = parsed.toLocal();

    final String month = local.month.toString().padLeft(2, '0');
    final String day = local.day.toString().padLeft(2, '0');
    final String hour = local.hour.toString().padLeft(2, '0');
    final String minute = local.minute.toString().padLeft(2, '0');

    return '${local.year}-$month-$day $hour:$minute';
  }

  Future<void> _load() async {
    if (!mounted || _processing) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    final results = await Future.wait([
      LogisticsCodApi.getRiderRemittances(),
      LogisticsCodApi.getAdminRemittances(),
    ]);

    if (!mounted) return;

    final Map<String, dynamic> riderResponse = results[0];
    final Map<String, dynamic> adminResponse = results[1];

    final Map<String, dynamic> riderData = _map(riderResponse['data']);

    final Map<String, dynamic> adminData = _map(adminResponse['data']);

    final List<String> errors = <String>[];

    if (riderResponse['success'] != true) {
      errors.add(
        _text(
          riderResponse['message'],
          fallback: 'Cannot load Rider remittances.',
        ),
      );
    }

    if (adminResponse['success'] != true) {
      errors.add(
        _text(
          adminResponse['message'],
          fallback: 'Cannot load COD custody wallet.',
        ),
      );
    }

    setState(() {
      if (riderResponse['success'] == true) {
        _riderRemittances = _findList(riderData, <String>[
          'remittances',
          'rider_remittances',
          'data',
        ]);

        if (_riderRemittances.isEmpty && riderResponse['data'] is List) {
          _riderRemittances = _list(riderResponse['data']);
        }
      }

      if (adminResponse['success'] == true) {
        _adminRemittances = _findList(adminData, <String>[
          'remittances',
          'transfers',
          'admin_remittances',
        ]);

        _custodyWallet = _map(
          adminData['wallet'] ??
              adminData['cod_wallet'] ??
              adminData['custody_wallet'],
        );

        if (_custodyWallet.isEmpty &&
            (adminData.containsKey('available_balance_minor') ||
                adminData.containsKey('pending_balance_minor'))) {
          _custodyWallet = adminData;
        }
      }

      _loading = false;
      _error = errors.isEmpty ? null : errors.join('\n');
    });
  }

  int get _availableMinor =>
      _integer(_custodyWallet['available_balance_minor']);

  int get _pendingMinor => _integer(_custodyWallet['pending_balance_minor']);

  bool get _hasPendingAdminTransfer {
    if (_custodyWallet['has_pending_transfer'] == true) {
      return true;
    }

    return _adminRemittances.any(
      (item) => _text(item['status']).toLowerCase() == 'pending',
    );
  }

  int? _pesoInputToMinor(String input) {
    final String value = input.trim();

    if (!RegExp(r'^(?:0|[1-9]\d*)(?:\.\d{1,2})?$').hasMatch(value)) {
      return null;
    }

    final List<String> parts = value.split('.');
    final int? pesos = int.tryParse(parts[0]);

    if (pesos == null) return null;

    final int cents = parts.length == 2
        ? int.parse(parts[1].padRight(2, '0'))
        : 0;

    return pesos * 100 + cents;
  }

  Future<bool> _confirmDialog({
    required String title,
    required String message,
    required String action,
  }) async {
    if (!mounted) return false;

    return await showDialog<bool>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: Text(title),
            content: Text(message),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext, false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(dialogContext, true),
                child: Text(action),
              ),
            ],
          ),
        ) ??
        false;
  }

  void _notice(String message) {
    if (!mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
    );
  }

  Future<void> _confirmRider(Map<String, dynamic> item) async {
    if (_processing) return;

    final int id = _integer(item['id']);

    if (id <= 0) return;

    final bool approved = await _confirmDialog(
      title: 'Confirm COD Received',
      message:
          'Confirm that Logistics has physically received '
          '${_peso(_minor(item))} from the Rider?\n\n'
          'This will credit the Logistics COD custody wallet.',
      action: 'Confirm Received',
    );

    if (!approved || !mounted) return;

    setState(() => _processing = true);

    final result = await LogisticsCodApi.confirmRiderRemittance(id);

    if (!mounted) return;

    setState(() => _processing = false);

    _notice(
      _text(
        result['message'],
        fallback: result['success'] == true
            ? 'COD remittance confirmed.'
            : 'Unable to confirm remittance.',
      ),
    );

    await _load();
  }

  Future<void> _rejectRider(Map<String, dynamic> item) async {
    if (_processing) return;

    final int id = _integer(item['id']);
    if (id <= 0) return;

    final controller = TextEditingController();

    final String? reason = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Reject COD Remittance'),
        content: TextField(
          controller: controller,
          autofocus: true,
          maxLines: 3,
          maxLength: 500,
          decoration: const InputDecoration(
            labelText: 'Rejection reason',
            hintText: 'Explain why cash was not accepted',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.pop(dialogContext, controller.text.trim()),
            child: const Text('Reject'),
          ),
        ],
      ),
    );

    controller.dispose();

    if (!mounted || reason == null) return;

    if (reason.isEmpty) {
      _notice('Please provide a rejection reason.');
      return;
    }

    setState(() => _processing = true);

    final result = await LogisticsCodApi.rejectRiderRemittance(
      remittanceId: id,
      reason: reason,
    );

    if (!mounted) return;

    setState(() => _processing = false);

    _notice(
      _text(
        result['message'],
        fallback: result['success'] == true
            ? 'COD remittance rejected.'
            : 'Unable to reject remittance.',
      ),
    );

    await _load();
  }

  Future<void> _submitAdminTransfer() async {
    if (_processing || _loading) return;

    final String amount = _amountController.text.trim();
    final int? amountMinor = _pesoInputToMinor(amount);

    if (amountMinor == null || amountMinor <= 0) {
      _notice('Enter a valid COD amount.');
      return;
    }

    if (amountMinor > _availableMinor) {
      _notice('Amount exceeds available COD custody.');
      return;
    }

    if (_hasPendingAdminTransfer) {
      _notice('There is already a pending Admin remittance.');
      return;
    }

    final bool approved = await _confirmDialog(
      title: 'Remit COD to Admin',
      message:
          'Submit ${_peso(amountMinor)} using '
          '${_transferMethod.replaceAll('_', ' ')}?\n\n'
          'This amount will be reserved from your available '
          'COD custody balance until Admin confirmation.',
      action: 'Submit Remittance',
    );

    if (!approved || !mounted) return;

    setState(() => _processing = true);

    final result = await LogisticsCodApi.submitToAdmin(
      amount: amount,
      transferMethod: _transferMethod,
      transferReference: _referenceController.text,
      notes: _notesController.text,
    );

    if (!mounted) return;

    setState(() => _processing = false);

    if (result['success'] == true) {
      _amountController.clear();
      _referenceController.clear();
      _notesController.clear();
    }

    _notice(
      _text(
        result['message'],
        fallback: result['success'] == true
            ? 'Admin COD remittance submitted.'
            : 'Unable to submit COD remittance.',
      ),
    );

    await _load();
  }

  Widget _panel({required String title, required Widget child}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: LogisticsStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: LogisticsStyle.sectionTitle),
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }

  Widget _moneyCard(String title, int minor, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: LogisticsColors.lightPurple,
        borderRadius: BorderRadius.circular(13),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: LogisticsColors.primary),
          const SizedBox(height: 9),
          Text(title, style: LogisticsStyle.small),
          const SizedBox(height: 5),
          Text(
            _peso(minor),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: LogisticsStyle.cardTitle,
          ),
        ],
      ),
    );
  }

  Widget _statusLabel(dynamic value) {
    final String status = _text(value, fallback: 'unknown').toLowerCase();

    Color color = LogisticsColors.primary;

    if (status == 'pending') {
      color = LogisticsColors.warning;
    } else if (status == 'confirmed') {
      color = LogisticsColors.success;
    } else if (status == 'rejected') {
      color = LogisticsColors.danger;
    }

    return Text(
      status.toUpperCase(),
      style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 11),
    );
  }

  Widget _riderItem(Map<String, dynamic> item) {
    final String status = _text(item['status']).toLowerCase();

    final Map<String, dynamic> rider = _map(item['rider']);

    final String riderName = _text(
      item['rider_name'] ?? rider['name'],
      fallback: 'Rider',
    );

    final dynamic paymentCount =
        item['payments_count'] ??
        (item['payments'] is List ? (item['payments'] as List).length : null);

    return Container(
      margin: const EdgeInsets.only(bottom: 11),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: LogisticsColors.background,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: LogisticsColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  _text(item['reference'], fallback: 'COD Remittance'),
                  style: LogisticsStyle.cardTitle,
                ),
              ),
              _statusLabel(status),
            ],
          ),
          const SizedBox(height: 9),
          Text('Rider: $riderName', style: LogisticsStyle.small),
          if (paymentCount != null)
            Text('Payments: $paymentCount', style: LogisticsStyle.small),
          const SizedBox(height: 5),
          Text(_peso(_minor(item)), style: LogisticsStyle.sectionTitle),
          const SizedBox(height: 5),
          Text(_date(item['submitted_at']), style: LogisticsStyle.small),
          if (status == 'rejected')
            Padding(
              padding: const EdgeInsets.only(top: 7),
              child: Text(
                'Reason: ${_text(item['rejection_reason'])}',
                style: LogisticsStyle.small,
              ),
            ),
          if (status == 'pending') ...[
            const SizedBox(height: 13),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _processing ? null : () => _rejectRider(item),
                    child: const Text('Reject'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: FilledButton(
                    onPressed: _processing ? null : () => _confirmRider(item),
                    child: const Text('Confirm Received'),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _adminHistoryItem(Map<String, dynamic> item) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: LogisticsColors.background,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: LogisticsColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  _text(item['reference']),
                  style: LogisticsStyle.cardTitle,
                ),
              ),
              _statusLabel(item['status']),
            ],
          ),
          const SizedBox(height: 7),
          Text(_peso(_minor(item)), style: LogisticsStyle.cardTitle),
          const SizedBox(height: 5),
          Text(
            'Method: ${_text(item['transfer_method'])}',
            style: LogisticsStyle.small,
          ),
          Text(
            'Submitted: ${_date(item['submitted_at'])}',
            style: LogisticsStyle.small,
          ),
          if (_text(item['rejection_reason'], fallback: '').isNotEmpty)
            Text(
              'Reason: ${item['rejection_reason']}',
              style: LogisticsStyle.small,
            ),
        ],
      ),
    );
  }

  Widget _riderSection() {
    final List<Map<String, dynamic>> sorted = List<Map<String, dynamic>>.from(
      _riderRemittances,
    );

    sorted.sort((a, b) {
      final bool aPending = _text(a['status']).toLowerCase() == 'pending';
      final bool bPending = _text(b['status']).toLowerCase() == 'pending';

      if (aPending == bPending) return 0;
      return aPending ? -1 : 1;
    });

    return _panel(
      title: 'Rider COD Remittances',
      child: sorted.isEmpty
          ? const Text('No Rider COD remittances found.')
          : Column(children: sorted.map(_riderItem).toList()),
    );
  }

  Widget _custodySection() {
    return _panel(
      title: 'COD Custody Wallet',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Collected COD cash held by Logistics. '
            'This is separate from your earnings wallet.',
            style: LogisticsStyle.small,
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _moneyCard(
                  'Available COD',
                  _availableMinor,
                  Icons.payments_outlined,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _moneyCard(
                  'Pending to Admin',
                  _pendingMinor,
                  Icons.hourglass_top_rounded,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _adminTransferSection() {
    return _panel(
      title: 'Remit COD to Admin',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: _amountController,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(
              labelText: 'Amount in pesos',
              prefixText: '₱ ',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _transferMethod,
            decoration: const InputDecoration(
              labelText: 'Transfer Method',
              border: OutlineInputBorder(),
            ),
            items: const [
              DropdownMenuItem(value: 'cash', child: Text('Cash')),
              DropdownMenuItem(
                value: 'bank_transfer',
                child: Text('Bank Transfer'),
              ),
              DropdownMenuItem(value: 'gcash', child: Text('GCash')),
              DropdownMenuItem(value: 'maya', child: Text('Maya')),
              DropdownMenuItem(value: 'other', child: Text('Other')),
            ],
            onChanged: _processing
                ? null
                : (value) {
                    if (value != null) {
                      setState(() => _transferMethod = value);
                    }
                  },
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _referenceController,
            maxLength: 100,
            decoration: const InputDecoration(
              labelText: 'Transfer Reference (optional)',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notesController,
            maxLines: 2,
            maxLength: 500,
            decoration: const InputDecoration(
              labelText: 'Notes (optional)',
              border: OutlineInputBorder(),
            ),
          ),
          if (_hasPendingAdminTransfer)
            const Padding(
              padding: EdgeInsets.only(bottom: 12),
              child: Text(
                'An Admin COD transfer is already pending. '
                'Wait for Admin confirmation or rejection.',
                style: TextStyle(color: LogisticsColors.warning),
              ),
            ),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed:
                  _processing ||
                      _loading ||
                      _availableMinor <= 0 ||
                      _hasPendingAdminTransfer
                  ? null
                  : _submitAdminTransfer,
              icon: _processing
                  ? const SizedBox(
                      height: 16,
                      width: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.send_outlined),
              label: const Text('Submit to Admin'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _adminHistorySection() {
    return _panel(
      title: 'Logistics to Admin History',
      child: _adminRemittances.isEmpty
          ? const Text('No COD transfers to Admin yet.')
          : Column(children: _adminRemittances.map(_adminHistoryItem).toList()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: LogisticsColors.background,
      appBar: AppBar(
        title: const Text('COD Remittance'),
        backgroundColor: LogisticsColors.white,
        foregroundColor: LogisticsColors.textDark,
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _processing ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            if (_loading)
              const Padding(
                padding: EdgeInsets.all(20),
                child: Center(child: CircularProgressIndicator()),
              ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 15),
                child: Text(
                  _error!,
                  style: const TextStyle(color: LogisticsColors.danger),
                ),
              ),
            if (!_loading) ...[
              _riderSection(),
              const SizedBox(height: 18),
              _custodySection(),
              const SizedBox(height: 18),
              _adminTransferSection(),
              const SizedBox(height: 18),
              _adminHistorySection(),
              const SizedBox(height: 25),
            ],
          ],
        ),
      ),
    );
  }
}
