import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../models/parcel_model.dart';
import '../../services/api_services.dart';
import '../../styles/rider_style.dart';
import '../../widgets/app_address_card.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_info_row.dart';
import '../../widgets/app_loading_state.dart';
import '../../widgets/status_chip.dart';

class DeliveryConfirmation extends StatefulWidget {
  final ParcelModel parcel;

  const DeliveryConfirmation({super.key, required this.parcel});

  @override
  State<DeliveryConfirmation> createState() => _DeliveryConfirmationState();
}

class _DeliveryConfirmationState extends State<DeliveryConfirmation> {
  static const Duration _requestTimeout = Duration(seconds: 30);

  final ImagePicker _imagePicker = ImagePicker();

  final TextEditingController _receiverController = TextEditingController();

  final TextEditingController _notesController = TextEditingController();

  late ParcelModel _parcel;

  int _requestId = 0;

  bool _isLoadingShipment = true;
  bool _isRefreshing = false;
  bool _isProcessing = false;
  bool _latestVerified = false;

  bool _hasChanges = false;
  bool _proofUploaded = false;
  bool _isFinalMileAssignment = false;
  bool _receivedBySortingCenter = false;
  bool _hubQrConfirmed = false;

  // COD details are always obtained from Laravel, never estimated locally.
  bool _codDetailsVerified = false;
  bool _isLoadingCod = false;
  bool _isCodOrder = false;
  bool _codCollected = false;
  String _codAmount = '';
  int _codRequestId = 0;

  // Avoid recursive PopScope callbacks when returning changed=true.
  bool _allowPop = false;

  String _currentStatus = '';
  String _sortingStatus = '';
  String _stagedAt = '';
  String _hubPickedUpAt = '';
  String? _selectedProofPath;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();

    _parcel = widget.parcel;
    _currentStatus = _normalize(_parcel.status);
    _sortingStatus = _parcel.normalizedSortingStatus;
    _stagedAt = _parcel.stagedAt.trim();

    _proofUploaded = _parcel.hasProofOfDelivery;
    _isFinalMileAssignment = _parcel.isFinalMileAssignment;
    _receivedBySortingCenter = _parcel.receivedBySortingCenter;

    _receiverController.text = _parcel.deliveredTo.trim();
    _notesController.text = _parcel.deliveryNotes.trim();

    _loadLatestShipment();
  }

  @override
  void dispose() {
    _requestId++;
    _codRequestId++;
    _receiverController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  // ============================================================
  // STATUS AND AUTHORIZATION HELPERS
  // ============================================================

  String _normalize(String value) {
    return value.trim().toLowerCase().replaceAll('-', '_').replaceAll(' ', '_');
  }

  bool _asBool(dynamic value) {
    if (value is bool) return value;
    if (value is num) return value != 0;

    final String text = value?.toString().trim().toLowerCase() ?? '';

    return const <String>{'true', '1', 'yes', 'y'}.contains(text);
  }

  String _clean(dynamic value) {
    final String text = value?.toString().trim() ?? '';

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return '';
    }

    return text;
  }

  String _firstText(List<dynamic> values) {
    for (final dynamic value in values) {
      final String text = _clean(value);
      if (text.isNotEmpty) return text;
    }
    return '';
  }

  bool _containsFinalMile(String value) {
    return const <String>{
      'final_mile',
      'pickup_and_delivery',
      'linehaul_and_delivery',
      'pickup_linehaul_and_delivery',
    }.contains(_normalize(value));
  }

  bool get _isDelivered =>
      _currentStatus == 'delivered' || _currentStatus == 'completed';

  bool get _isFailed => const <String>{
    'delivery_failed',
    'failed',
    'failed_return_at_sorting_center',
  }.contains(_currentStatus);

  bool get _isCancelled =>
      _currentStatus == 'cancelled' || _currentStatus == 'canceled';

  bool get _isFinalized => _isDelivered || _isFailed || _isCancelled;

  bool get _isOutForDelivery => _currentStatus == 'out_for_delivery';

  bool get _isPickedUp => _currentStatus == 'picked_up';

  bool get _sortingReady =>
      _receivedBySortingCenter &&
      _normalize(_sortingStatus) == 'staged' &&
      _stagedAt.isNotEmpty;

  bool get _canPerformAction =>
      mounted &&
      _latestVerified &&
      !_isRefreshing &&
      !_isLoadingShipment &&
      !_isProcessing &&
      _isFinalMileAssignment &&
      !_isFinalized &&
      _parcel.id > 0;

  // Requires the Laravel-confirmed Hub QR scan.
  bool get _canStartDelivery =>
      _canPerformAction && _sortingReady && _hubQrConfirmed && _isPickedUp;

  bool get _canUploadProof =>
      _canPerformAction &&
      _hubQrConfirmed &&
      _isOutForDelivery &&
      !_proofUploaded;

  bool get _canConfirmDelivery =>
      _canPerformAction &&
      _hubQrConfirmed &&
      _isOutForDelivery &&
      _proofUploaded &&
      _codDetailsVerified &&
      (!_isCodOrder || _codCollected);

  bool get _canMarkFailed =>
      _canPerformAction && _hubQrConfirmed && _isOutForDelivery;

  // ============================================================
  // LOAD / VERIFY LATEST SHIPMENT
  // ============================================================

  Future<void> _loadLatestShipment() async {
    if (!mounted || _isRefreshing || _isProcessing) {
      return;
    }

    final int requestId = ++_requestId;

    setState(() {
      _isRefreshing = true;
      _isLoadingShipment = !_latestVerified;
      _latestVerified = false;
      _codDetailsVerified = false;
      _errorMessage = null;
    });

    if (_parcel.id <= 0) {
      _setLoadError(requestId, 'Invalid shipment ID.');
      return;
    }

    try {
      final Map<String, dynamic> result = await ApiService.getRiderDelivery(
        _parcel.id,
      ).timeout(_requestTimeout);

      if (!mounted || requestId != _requestId) return;

      if (result['success'] != true) {
        _setLoadError(
          requestId,
          _responseMessage(result, 'Unable to verify this shipment.'),
        );
        return;
      }

      dynamic raw = result['data'] ?? result['shipment'];

      if (raw is Map && raw['shipment'] is Map) {
        raw = raw['shipment'];
      }

      if (raw is! Map) {
        _setLoadError(requestId, 'Laravel returned invalid shipment data.');
        return;
      }

      final Map<String, dynamic> data = Map<String, dynamic>.from(raw);

      // Preserve required ParcelModel fallback fields.
      data['id'] ??= data['shipment_id'] ?? 0;
      data['tracking_number'] ??= data['tracking_code'] ?? '';
      data['status'] ??= data['shipment_status'] ?? '';
      data['customer_name'] ??= '';
      data['customer_phone'] ??= '';
      data['delivery_address'] ??= '';
      data['rider_name'] ??= '';
      data['order_date'] ??= data['created_at'] ?? '';
      data['delivery_earnings'] ??= 0;

      final ParcelModel latest = ParcelModel.fromJson(data);

      if (latest.id != _parcel.id) {
        _setLoadError(requestId, 'Laravel returned an unexpected shipment.');
        return;
      }

      final bool finalMile =
          latest.isFinalMileAssignment ||
          _asBool(data['is_final_mile_rider']) ||
          _containsFinalMile(_clean(data['assignment_type']));

      final bool received =
          latest.receivedBySortingCenter ||
          _asBool(data['received_by_sorting_center']);

      final String sortingStatus = _firstText([
        data['sorting_status'],
        latest.normalizedSortingStatus,
      ]);

      final String stagedAt = _firstText([data['staged_at'], latest.stagedAt]);

      final String hubPickedUpAt = _clean(data['hub_picked_up_at']);

      // The revised Laravel OrderController explicitly
      // returns this authenticated-rider-specific flag.
      // Missing/false is treated as NOT verified.
      final bool hubQrConfirmed =
          _asBool(data['hub_pickup_scan_confirmed']) &&
          hubPickedUpAt.isNotEmpty;

      final bool hasProof =
          _asBool(data['has_proof_of_delivery']) ||
          _clean(data['proof_of_delivery']).isNotEmpty ||
          latest.hasProofOfDelivery;

      final String receiver = _firstText([
        data['delivered_to'],
        latest.deliveredTo,
      ]);

      final String notes = _firstText([
        data['delivery_notes'],
        data['notes'],
        latest.deliveryNotes,
      ]);

      if (!mounted || requestId != _requestId) return;

      setState(() {
        _parcel = latest;

        _currentStatus = _normalize(
          _firstText([data['status'], latest.status]),
        );

        _sortingStatus = sortingStatus;
        _stagedAt = stagedAt;

        _isFinalMileAssignment = finalMile;
        _receivedBySortingCenter = received;
        _hubPickedUpAt = hubPickedUpAt;
        _hubQrConfirmed = hubQrConfirmed;

        _proofUploaded = hasProof;

        _latestVerified = true;
        _isRefreshing = false;
        _isLoadingShipment = false;
        _errorMessage = null;

        if (receiver.isNotEmpty && _receiverController.text.trim().isEmpty) {
          _receiverController.text = receiver;
        }

        if (notes.isNotEmpty && _notesController.text.trim().isEmpty) {
          _notesController.text = notes;
        }

        if (hasProof) {
          _selectedProofPath = null;
        }
      });

      // Verify payment independently. A COD API failure keeps
      // delivery confirmation disabled (fail closed).
      await _loadCodDetails();
    } on TimeoutException {
      _setLoadError(
        requestId,
        'Shipment verification timed out. '
        'Refresh before continuing.',
      );
    } catch (error) {
      debugPrint('Delivery verification error: $error');

      _setLoadError(requestId, 'Unable to verify shipment with BiliHub.');
    }
  }

  void _setLoadError(int requestId, String message) {
    if (!mounted || requestId != _requestId) return;

    setState(() {
      _latestVerified = false;
      _codDetailsVerified = false;
      _isRefreshing = false;
      _isLoadingShipment = false;
      _errorMessage = message;
    });
  }

  // ============================================================
  // COD DETAILS / CASH COLLECTION
  // ============================================================

  Future<void> _loadCodDetails() async {
    if (!mounted || _parcel.id <= 0 || _isLoadingCod) return;

    final int codRequestId = ++_codRequestId;

    setState(() {
      _isLoadingCod = true;
      _codDetailsVerified = false;
      _codAmount = '';
    });

    try {
      final Map<String, dynamic> response =
          await ApiService.getRiderShipmentCod(shipmentId: _parcel.id)
              .timeout(_requestTimeout);

      if (!mounted || codRequestId != _codRequestId) return;

      if (response['success'] != true) {
        _showMessage(
          _responseMessage(response, 'Unable to verify COD collection status.'),
        );
        return;
      }

      final dynamic raw = response['data'];
      if (raw is! Map) {
        _showMessage('Laravel returned invalid COD details.');
        return;
      }

      final Map<String, dynamic> data = Map<String, dynamic>.from(raw);
      final int responseShipmentId =
          int.tryParse(data['shipment_id']?.toString() ?? '') ?? 0;

      if (responseShipmentId != _parcel.id) {
        _showMessage('COD details do not match this shipment.');
        return;
      }

      final bool isCod = _asBool(data['is_cod']);
      final String amount = _clean(data['amount']);
      final int minor =
          int.tryParse(data['amount_minor']?.toString() ?? '') ?? -1;

      if (isCod && (minor <= 0 || amount.isEmpty)) {
        _showMessage('The COD amount is not valid.');
        return;
      }

      setState(() {
        _isCodOrder = isCod;
        _codCollected = isCod && _asBool(data['cod_collected']);
        _codAmount = isCod ? amount : '';
        _codDetailsVerified = true;
      });
    } on TimeoutException {
      if (mounted) {
        _showMessage(
          'COD verification timed out. Refresh before confirming delivery.',
        );
      }
    } catch (error) {
      debugPrint('COD details error: $error');
      if (mounted) {
        _showMessage('Unable to verify COD details from Laravel.');
      }
    } finally {
      if (mounted && codRequestId == _codRequestId) {
        setState(() {
          _isLoadingCod = false;
        });
      }
    }
  }

  Future<void> _collectCodPayment() async {
    if (!_canPerformAction ||
        !_isOutForDelivery ||
        !_hubQrConfirmed ||
        !_codDetailsVerified ||
        _isLoadingCod ||
        !_isCodOrder ||
        _codCollected ||
        _codAmount.isEmpty) {
      return;
    }

    final bool confirmed = await _confirmAction(
      title: 'Confirm COD Collection',
      message:
          'Tracking: ${_parcel.trackingNumber}\n\n'
          'Exact cash amount: ₱$_codAmount\n\n'
          'Have you PHYSICALLY received this cash from the buyer? '
          'Do not confirm until the full amount is in your possession.',
      confirmLabel: 'CASH RECEIVED',
    );

    if (!mounted ||
        !confirmed ||
        !_canPerformAction ||
        !_codDetailsVerified ||
        _codCollected) {
      return;
    }

    setState(() {
      _isProcessing = true;
      _codDetailsVerified = false;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.collectRiderShipmentCod(
            shipmentId: _parcel.id,
            amountCollected: _codAmount,
          ).timeout(_requestTimeout);

      if (!mounted) return;

      if (result['success'] == true) {
        _hasChanges = true;
        _showMessage(
          _responseMessage(result, 'COD collection recorded successfully.'),
          success: true,
        );
      } else {
        _showMessage(
          _responseMessage(
            result,
            'Collection could not be confirmed. Verify status before retrying.',
          ),
        );
      }
    } on TimeoutException {
      if (mounted) {
        _showMessage(
          'The request timed out. Cash may already be recorded. '
          'Do not collect payment a second time.',
        );
      }
    } catch (error) {
      debugPrint('COD collect error: $error');
      if (mounted) {
        _showMessage(
          'Collection result uncertain. Check COD status before retrying.',
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isProcessing = false;
        });
        await _loadCodDetails();
      }
    }
  }

  // ============================================================
  // MESSAGES AND CONFIRMATION
  // ============================================================

  String _responseMessage(Map<String, dynamic> response, String fallback) {
    final String message = _clean(response['message']);

    if (message.isNotEmpty) return message;

    final dynamic errors = response['errors'];

    if (errors is Map) {
      for (final dynamic value in errors.values) {
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }

        final String text = _clean(value);
        if (text.isNotEmpty) return text;
      }
    }

    return fallback;
  }

  void _showMessage(String message, {bool success = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: success ? RiderColors.success : RiderColors.danger,
          content: Text(message),
        ),
      );
  }

  Future<bool> _confirmAction({
    required String title,
    required String message,
    required String confirmLabel,
    bool destructive = false,
  }) async {
    final bool? result = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          title: Text(title),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: destructive
                    ? RiderColors.danger
                    : RiderColors.primary,
              ),
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(confirmLabel),
            ),
          ],
        );
      },
    );

    return result == true;
  }

  Future<void> _refreshAfterUncertainResult() async {
    if (!mounted) return;

    _showMessage(
      'The request may already have been processed. '
      'Checking the latest server status...',
    );

    await _loadLatestShipment();
  }

  void _finishPage() {
    if (!mounted || _isProcessing || _allowPop) return;
    setState(() {
      _allowPop = true;
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) Navigator.of(context).pop(_hasChanges);
    });
  }

  Future<void> _showCompletionDialog({
    required String title,
    required String message,
    required bool success,
  }) async {
    if (!mounted) return;

    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          title: Row(
            children: [
              Icon(
                success
                    ? Icons.check_circle_rounded
                    : Icons.error_outline_rounded,
                color: success ? RiderColors.success : RiderColors.danger,
              ),
              const SizedBox(width: 10),
              Expanded(child: Text(title)),
            ],
          ),
          content: Text(message),
          actions: [
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('Done'),
            ),
          ],
        );
      },
    );

    if (mounted) _finishPage();
  }

  // ============================================================
  // OUT FOR DELIVERY
  // ============================================================

  Future<void> _markOutForDelivery() async {
    if (!_canStartDelivery) {
      _showMessage(
        'Hub QR pickup must be confirmed before '
        'starting final-mile delivery.',
      );
      return;
    }

    final bool confirmed = await _confirmAction(
      title: 'Start Final-Mile Delivery',
      message:
          'Tracking: ${_parcel.trackingNumber}\n\n'
          'Hub QR pickup is confirmed by BiliHub.\n\n'
          'Have you collected this parcel and are '
          'you ready to deliver it to the buyer?',
      confirmLabel: 'START DELIVERY',
    );

    if (!mounted || !confirmed || !_canStartDelivery) {
      return;
    }

    setState(() {
      _isProcessing = true;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.updateRiderDeliveryStatus(
            shipmentId: _parcel.id,
            status: 'out_for_delivery',
          ).timeout(_requestTimeout);

      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      if (result['success'] == true) {
        _hasChanges = true;

        _showMessage(
          _responseMessage(result, 'Parcel marked as out for delivery.'),
          success: true,
        );
      } else {
        _showMessage(_responseMessage(result, 'Unable to start delivery.'));
      }

      await _loadLatestShipment();
    } on TimeoutException {
      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      await _refreshAfterUncertainResult();
    } catch (error) {
      debugPrint('Start delivery error: $error');

      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      await _refreshAfterUncertainResult();
    }
  }

  // ============================================================
  // PHOTO SELECTION
  // ============================================================

  Future<void> _chooseProofPhoto() async {
    if (!_canUploadProof) return;

    final ImageSource? source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (BuildContext sheetContext) {
        return SafeArea(
          child: Wrap(
            children: [
              const ListTile(
                title: Text(
                  'Select Proof of Delivery',
                  style: RiderStyle.sectionTitle,
                ),
              ),
              ListTile(
                leading: const Icon(
                  Icons.camera_alt_outlined,
                  color: RiderColors.primary,
                ),
                title: const Text('Take Photo'),
                onTap: () => Navigator.pop(sheetContext, ImageSource.camera),
              ),
              ListTile(
                leading: const Icon(
                  Icons.photo_library_outlined,
                  color: RiderColors.primary,
                ),
                title: const Text('Choose from Gallery'),
                onTap: () => Navigator.pop(sheetContext, ImageSource.gallery),
              ),
            ],
          ),
        );
      },
    );

    if (!mounted || source == null || !_canUploadProof) {
      return;
    }

    try {
      final XFile? selected = await _imagePicker.pickImage(
        source: source,
        imageQuality: 80,
        maxWidth: 1600,
      );

      if (!mounted || selected == null) return;

      setState(() {
        _selectedProofPath = selected.path;
      });
    } catch (error) {
      debugPrint('Proof image picker error: $error');

      _showMessage('Unable to select a proof photo.');
    }
  }

  // ============================================================
  // UPLOAD PROOF
  // ============================================================

  Future<void> _uploadProof() async {
    if (!_canUploadProof) return;

    final String receiverName = _receiverController.text.trim();

    final String notes = _notesController.text.trim();

    final String? photoPath = _selectedProofPath;

    if (receiverName.isEmpty) {
      _showMessage(
        'Enter the name of the person '
        'who received the parcel.',
      );
      return;
    }

    if (photoPath == null || photoPath.isEmpty) {
      _showMessage('Take or choose a proof-of-delivery photo.');
      return;
    }

    final bool exists = await File(photoPath).exists();

    if (!mounted) return;

    if (!exists) {
      _showMessage('The selected photo could not be found.');
      return;
    }

    if (!_canUploadProof) return;

    final bool confirmed = await _confirmAction(
      title: 'Upload Proof of Delivery',
      message:
          'Receiver: $receiverName\n\n'
          'Confirm that the parcel was physically '
          'received by this person and the photo '
          'is valid delivery evidence.',
      confirmLabel: 'UPLOAD PROOF',
    );

    if (!mounted || !confirmed || !_canUploadProof) {
      return;
    }

    setState(() {
      _isProcessing = true;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.uploadRiderDeliveryProof(
            shipmentId: _parcel.id,
            proofFilePath: photoPath,
            deliveredTo: receiverName,
            deliveryNotes: notes.isEmpty ? null : notes,
          ).timeout(_requestTimeout);

      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      if (result['success'] == true) {
        _hasChanges = true;

        _showMessage(
          _responseMessage(result, 'Proof of delivery uploaded.'),
          success: true,
        );
      } else {
        _showMessage(
          _responseMessage(result, 'Unable to upload delivery proof.'),
        );
      }

      await _loadLatestShipment();
    } on TimeoutException {
      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      await _refreshAfterUncertainResult();
    } catch (error) {
      debugPrint('Proof upload error: $error');

      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      await _refreshAfterUncertainResult();
    }
  }

  // ============================================================
  // CONFIRM DELIVERED
  // ============================================================

  Future<void> _confirmDelivery() async {
    if (!_canConfirmDelivery) return;

    final bool confirmed = await _confirmAction(
      title: 'Confirm Parcel Delivery',
      message:
          'Have you handed the parcel to the '
          'customer or authorized receiver?\n\n'
          'Delivery proof has been recorded.\n\n'
          'If this is a COD order, payment must '
          'also be recorded in BiliHub before '
          'the server will accept Delivered.',
      confirmLabel: 'CONFIRM DELIVERED',
    );

    if (!mounted || !confirmed || !_canConfirmDelivery) {
      return;
    }

    setState(() {
      _isProcessing = true;
    });

    try {
      final String notes = _notesController.text.trim();

      final Map<String, dynamic> result =
          await ApiService.updateRiderDeliveryStatus(
            shipmentId: _parcel.id,
            status: 'delivered',
            deliveryNotes: notes.isEmpty ? null : notes,
          ).timeout(_requestTimeout);

      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      if (result['success'] == true) {
        _hasChanges = true;

        await _showCompletionDialog(
          title: 'Parcel Delivered',
          message: _responseMessage(
            result,
            'The shipment was marked as delivered.',
          ),
          success: true,
        );
      } else {
        // Show Laravel COD/validation messages.
        // Do NOT fabricate a COD payment record.
        _showMessage(
          _responseMessage(
            result,
            'Unable to confirm delivery. '
            'Verify proof and COD collection.',
          ),
        );

        await _loadLatestShipment();
      }
    } on TimeoutException {
      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      await _refreshAfterUncertainResult();
    } catch (error) {
      debugPrint('Confirm delivery error: $error');

      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      await _refreshAfterUncertainResult();
    }
  }

  // ============================================================
  // MARK DELIVERY FAILED
  // ============================================================

  Future<void> _markDeliveryFailed() async {
    if (!_canMarkFailed) return;

    String reasonText = '';

    final String? reason = await showDialog<String>(
      context: context,
      builder: (BuildContext dialogContext) {
        return StatefulBuilder(
          builder: (BuildContext dialogContext, StateSetter updateDialog) {
            return AlertDialog(
              title: const Text('Report Failed Delivery'),
              content: TextField(
                minLines: 3,
                maxLines: 5,
                maxLength: 500,
                onChanged: (String value) {
                  updateDialog(() {
                    reasonText = value;
                  });
                },
                decoration: const InputDecoration(
                  hintText: 'Enter why delivery failed...',
                  border: OutlineInputBorder(),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(dialogContext),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: RiderColors.danger,
                  ),
                  onPressed: reasonText.trim().isEmpty
                      ? null
                      : () => Navigator.pop(dialogContext, reasonText.trim()),
                  child: const Text('Continue'),
                ),
              ],
            );
          },
        );
      },
    );

    if (!mounted || reason == null || reason.isEmpty || !_canMarkFailed) {
      return;
    }

    final bool confirmed = await _confirmAction(
      title: 'Confirm Failed Delivery',
      message:
          'Reason:\n$reason\n\n'
          'This action reports a failed delivery '
          'to BiliHub.',
      confirmLabel: 'CONFIRM FAILED',
      destructive: true,
    );

    if (!mounted || !confirmed || !_canMarkFailed) {
      return;
    }

    setState(() {
      _isProcessing = true;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.updateRiderDeliveryStatus(
            shipmentId: _parcel.id,
            status: 'delivery_failed',
            failureReason: reason,
            deliveryNotes: reason,
          ).timeout(_requestTimeout);

      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      if (result['success'] == true) {
        _hasChanges = true;

        await _showCompletionDialog(
          title: 'Delivery Failure Recorded',
          message: _responseMessage(result, 'Delivery failure was recorded.'),
          success: false,
        );
      } else {
        _showMessage(
          _responseMessage(result, 'Unable to report failed delivery.'),
        );

        await _loadLatestShipment();
      }
    } on TimeoutException {
      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      await _refreshAfterUncertainResult();
    } catch (error) {
      debugPrint('Delivery failure error: $error');

      if (!mounted) return;

      setState(() {
        _isProcessing = false;
      });

      await _refreshAfterUncertainResult();
    }
  }

  // ============================================================
  // MAIN UI
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: _allowPop || (!_hasChanges && !_isProcessing),
      onPopInvokedWithResult: (bool didPop, Object? result) {
        if (didPop || _isProcessing) return;

        if (_hasChanges) {
          _finishPage();
        }
      },
      child: Scaffold(
        backgroundColor: RiderColors.background,
        appBar: AppBar(
          backgroundColor: RiderColors.white,
          surfaceTintColor: Colors.transparent,
          title: const Text('Final-Mile Delivery', style: RiderStyle.title),
          actions: [
            IconButton(
              tooltip: 'Refresh shipment',
              onPressed: _isRefreshing || _isProcessing
                  ? null
                  : _loadLatestShipment,
              icon: const Icon(
                Icons.refresh_rounded,
                color: RiderColors.primary,
              ),
            ),
          ],
        ),
        body: SafeArea(
          top: false,
          child: _isLoadingShipment && !_latestVerified
              ? const Center(
                  child: AppLoadingState(
                    message: 'Verifying delivery assignment...',
                    color: RiderColors.primary,
                  ),
                )
              : RefreshIndicator(
                  color: RiderColors.primary,
                  onRefresh: _loadLatestShipment,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
                    children: [
                      if (_isRefreshing) ...[
                        const LinearProgressIndicator(
                          color: RiderColors.primary,
                        ),
                        const SizedBox(height: 14),
                      ],
                      if (!_latestVerified)
                        AppErrorState(
                          title: 'Shipment Verification Failed',
                          message:
                              _errorMessage ?? 'Unable to verify shipment.',
                          color: RiderColors.primary,
                          onRetry: _loadLatestShipment,
                        )
                      else
                        _buildContent(),
                    ],
                  ),
                ),
        ),
      ),
    );
  }

  Widget _buildContent() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _statusCard(),
        const SizedBox(height: 16),

        if (!_isFinalMileAssignment)
          _notice(
            'This shipment is not assigned to you '
            'for final-mile delivery.',
            icon: Icons.lock_outline_rounded,
          )
        else ...[
          _parcelCard(),
          const SizedBox(height: 16),
          _readinessCard(),
          const SizedBox(height: 16),
          _progressCard(),
          const SizedBox(height: 16),
          _codCard(),
          const SizedBox(height: 16),

          if (_isOutForDelivery && !_isFinalized) ...[
            _proofCard(),
            const SizedBox(height: 16),
          ],

          _actionsCard(),
        ],
      ],
    );
  }

  // ============================================================
  // STATUS
  // ============================================================

  Widget _statusCard() {
    String title = 'Delivery Assignment';
    IconData icon = Icons.local_shipping_outlined;

    if (!_isFinalMileAssignment) {
      title = 'Final-Mile Access Required';
      icon = Icons.lock_outline_rounded;
    } else if (_isDelivered) {
      title = 'Delivery Completed';
      icon = Icons.check_circle_rounded;
    } else if (_isFailed) {
      title = 'Delivery Failed';
      icon = Icons.error_outline_rounded;
    } else if (_isCancelled) {
      title = 'Shipment Cancelled';
      icon = Icons.cancel_outlined;
    } else if (_isOutForDelivery) {
      title = 'Out for Delivery';
      icon = Icons.delivery_dining_rounded;
    } else if (!_hubQrConfirmed) {
      title = 'Waiting for Hub QR Scan';
      icon = Icons.qr_code_scanner_rounded;
    } else {
      title = 'Ready for Final-Mile Delivery';
    }

    return _card(
      title: title,
      icon: icon,
      children: [
        StatusChip(status: _currentStatus),
        const SizedBox(height: 10),
        Text(
          _isDelivered
              ? 'The parcel has been delivered.'
              : _isOutForDelivery
              ? 'Complete delivery and record '
                    'proof from the receiver.'
              : 'Follow the required pickup and '
                    'delivery confirmations.',
          style: RiderStyle.small,
        ),
      ],
    );
  }

  // ============================================================
  // PARCEL INFORMATION
  // ============================================================

  Widget _parcelCard() {
    return _card(
      title: 'Parcel Information',
      icon: Icons.inventory_2_outlined,
      children: [
        AppInfoRow(
          label: 'Tracking Number',
          value: _parcel.trackingNumber,
          icon: Icons.qr_code_2_rounded,
          iconColor: RiderColors.primary,
          selectable: true,
          compact: true,
        ),
        const SizedBox(height: 12),
        AppAddressCard(
          title: 'Delivery Address',
          recipientName: _parcel.customerName,
          phoneNumber: _parcel.customerPhone,
          address: _parcel.deliveryAddress,
          accentColor: RiderColors.primary,
          compact: true,
        ),
        if (_parcel.destinationHubName.trim().isNotEmpty) ...[
          const SizedBox(height: 12),
          AppInfoRow(
            label: 'Destination Hub',
            value: _parcel.destinationHubName,
            icon: Icons.warehouse_outlined,
            iconColor: RiderColors.primary,
            compact: true,
          ),
        ],
      ],
    );
  }

  // ============================================================
  // LOGISTICS + QR READINESS
  // ============================================================

  Widget _readinessCard() {
    return _card(
      title: 'Pickup Requirements',
      icon: Icons.fact_check_outlined,
      children: [
        _checkRow('Received by Logistics', _receivedBySortingCenter),
        const SizedBox(height: 11),
        _checkRow('Sorted and Staged', _sortingReady),
        const SizedBox(height: 11),
        _checkRow('Hub QR Pickup Confirmed', _hubQrConfirmed),
        if (_hubPickedUpAt.isNotEmpty) ...[
          const SizedBox(height: 10),
          AppInfoRow(
            label: 'Hub Pickup Timestamp',
            value: _hubPickedUpAt,
            icon: Icons.schedule_rounded,
            iconColor: RiderColors.primary,
            compact: true,
          ),
        ],
        if (!_hubQrConfirmed && !_isFinalized) ...[
          const SizedBox(height: 14),
          _notice(
            'You must scan the original BiliHub '
            'waybill QR at the hub. Return to '
            'My Deliveries and select SCAN HUB QR.',
            icon: Icons.qr_code_scanner_rounded,
          ),
        ],
      ],
    );
  }

  // ============================================================
  // PROGRESS
  // ============================================================

  Widget _progressCard() {
    return _card(
      title: 'Delivery Progress',
      icon: Icons.route_outlined,
      children: [
        _checkRow('Staged by Logistics', _sortingReady),
        const SizedBox(height: 12),
        _checkRow('Hub QR Scanned', _hubQrConfirmed),
        const SizedBox(height: 12),
        _checkRow('Out for Delivery', _isOutForDelivery || _isDelivered),
        const SizedBox(height: 12),
        _checkRow('Proof Uploaded', _proofUploaded),
        const SizedBox(height: 12),
        _checkRow('Delivered', _isDelivered),
      ],
    );
  }

  Widget _checkRow(String label, bool completed) {
    return Row(
      children: [
        Icon(
          completed ? Icons.check_circle_rounded : Icons.schedule_rounded,
          size: 20,
          color: completed ? RiderColors.success : RiderColors.warning,
        ),
        const SizedBox(width: 10),
        Expanded(child: Text(label, style: RiderStyle.small)),
      ],
    );
  }

  // ============================================================
  // COD STATUS CARD
  // ============================================================

  Widget _codCard() {
    return _card(
      title: 'Payment Collection',
      icon: Icons.account_balance_wallet_outlined,
      children: [
        if (!_codDetailsVerified)
          _notice(
            _isLoadingCod
                ? 'Checking the payment status with Laravel...'
                : 'Payment status could not be verified. '
                      'Refresh before confirming delivery.',
            icon: Icons.info_outline_rounded,
          )
        else if (!_isCodOrder)
          _notice(
            'This is not a Cash on Delivery shipment. '
            'No cash collection is required.',
            icon: Icons.verified_outlined,
          )
        else ...[
          AppInfoRow(
            label: 'Amount to Collect',
            value: '₱$_codAmount',
            icon: Icons.payments_outlined,
            iconColor: RiderColors.primary,
          ),
          const SizedBox(height: 12),
          if (_codCollected)
            _notice(
              'COD payment is recorded by BiliHub. '
              'Do not collect cash again.',
              icon: Icons.verified_rounded,
            )
          else
            _notice(
              'Collect the exact amount in cash before pressing '
              'CASH RECEIVED. This is a financial transaction.',
              icon: Icons.info_outline_rounded,
            ),
          if (!_codCollected && _isOutForDelivery && !_isFinalized) ...[
            const SizedBox(height: 14),
            _actionButton(
              label: 'COLLECT COD PAYMENT',
              icon: Icons.payments_rounded,
              onPressed: _canPerformAction && !_isLoadingCod
                  ? _collectCodPayment
                  : null,
            ),
          ],
        ],
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: _isProcessing || _isLoadingCod ? null : _loadCodDetails,
          icon: const Icon(Icons.refresh_rounded),
          label: const Text('REFRESH PAYMENT STATUS'),
        ),
      ],
    );
  }

  // ============================================================
  // DELIVERY PROOF UI
  // ============================================================

  Widget _proofCard() {
    return _card(
      title: 'Proof of Delivery',
      icon: Icons.photo_camera_outlined,
      children: [
        const Text(
          'Upload proof only after the parcel '
          'has been handed to the receiver.',
          style: RiderStyle.small,
        ),
        const SizedBox(height: 14),

        if (_proofUploaded)
          _notice(
            'Proof of delivery has been '
            'recorded by BiliHub.',
            icon: Icons.verified_rounded,
          )
        else ...[
          TextField(
            controller: _receiverController,
            enabled: _canUploadProof,
            maxLength: 120,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(
              labelText: 'Received By',
              hintText: 'Receiver full name',
              prefixIcon: Icon(Icons.person_outline_rounded),
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _notesController,
            enabled: _canUploadProof,
            maxLength: 500,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Delivery Notes',
              hintText: 'Optional',
              prefixIcon: Icon(Icons.notes_outlined),
              border: OutlineInputBorder(),
            ),
          ),

          if (_selectedProofPath != null) ...[
            const SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.file(
                File(_selectedProofPath!),
                height: 180,
                width: double.infinity,
                fit: BoxFit.cover,
                errorBuilder:
                    (
                      BuildContext context,
                      Object error,
                      StackTrace? stackTrace,
                    ) {
                      return const SizedBox(
                        height: 80,
                        child: Center(child: Text('Unable to preview photo.')),
                      );
                    },
              ),
            ),
          ],

          const SizedBox(height: 12),

          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: _canUploadProof ? _chooseProofPhoto : null,
              icon: const Icon(Icons.add_a_photo_outlined),
              label: Text(
                _selectedProofPath == null
                    ? 'ADD PROOF PHOTO'
                    : 'CHANGE PROOF PHOTO',
              ),
            ),
          ),
        ],
      ],
    );
  }

  // ============================================================
  // ACTIONS
  // ============================================================

  Widget _actionsCard() {
    if (_isDelivered) {
      return _notice(
        'Delivery completed successfully.',
        icon: Icons.check_circle_rounded,
      );
    }

    if (_isFailed) {
      return _notice(
        'This delivery was marked as failed.',
        icon: Icons.error_outline_rounded,
      );
    }

    if (_isCancelled) {
      return _notice(
        'This shipment was cancelled.',
        icon: Icons.cancel_outlined,
      );
    }

    if (!_isFinalMileAssignment) {
      return const SizedBox.shrink();
    }

    return _card(
      title: 'Delivery Actions',
      icon: Icons.task_alt_outlined,
      children: [
        if (_isProcessing) ...[
          const LinearProgressIndicator(color: RiderColors.primary),
          const SizedBox(height: 14),
        ],

        if (!_isOutForDelivery) ...[
          if (!_hubQrConfirmed)
            _notice(
              'Hub QR confirmation is required '
              'before starting delivery.',
              icon: Icons.qr_code_scanner_rounded,
            )
          else if (!_sortingReady)
            _notice('Waiting for Logistics staging confirmation.'),

          const SizedBox(height: 12),

          _actionButton(
            label: 'MARK OUT FOR DELIVERY',
            icon: Icons.delivery_dining_rounded,
            onPressed: _canStartDelivery ? _markOutForDelivery : null,
          ),
        ] else ...[
          if (!_proofUploaded)
            _actionButton(
              label: 'UPLOAD DELIVERY PROOF',
              icon: Icons.cloud_upload_outlined,
              onPressed: _canUploadProof ? _uploadProof : null,
            )
          else
            _actionButton(
              label: 'CONFIRM DELIVERY',
              icon: Icons.check_circle_outline_rounded,
              color: RiderColors.success,
              onPressed: _canConfirmDelivery ? _confirmDelivery : null,
            ),

          const SizedBox(height: 12),

          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: _canMarkFailed ? _markDeliveryFailed : null,
              icon: const Icon(Icons.error_outline_rounded),
              label: const Text('MARK DELIVERY FAILED'),
              style: OutlinedButton.styleFrom(
                foregroundColor: RiderColors.danger,
                side: const BorderSide(color: RiderColors.danger),
              ),
            ),
          ),

          const SizedBox(height: 12),

          if (!_codDetailsVerified)
            _notice(
              'Payment verification required before Confirm Delivered.',
              icon: Icons.info_outline_rounded,
            )
          else if (_isCodOrder && !_codCollected)
            _notice(
              'Outstanding COD: ₱$_codAmount. '
              'Use COLLECT COD PAYMENT above before confirming delivery.',
              icon: Icons.account_balance_wallet_outlined,
            )
          else if (_isCodOrder && _codCollected)
            _notice(
              'COD payment recorded. You may confirm delivery '
              'after uploading proof.',
              icon: Icons.verified_rounded,
            ),
        ],
      ],
    );
  }

  Widget _actionButton({
    required String label,
    required IconData icon,
    required VoidCallback? onPressed,
    Color color = RiderColors.primary,
  }) {
    return SizedBox(
      width: double.infinity,
      height: 50,
      child: ElevatedButton.icon(
        onPressed: onPressed,
        icon: Icon(icon),
        label: Text(label),
        style: ElevatedButton.styleFrom(
          foregroundColor: Colors.white,
          backgroundColor: color,
          disabledBackgroundColor: RiderColors.border,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // SHARED UI
  // ============================================================

  Widget _card({
    required String title,
    required IconData icon,
    required List<Widget> children,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: RiderColors.primary, size: 22),
              const SizedBox(width: 10),
              Expanded(child: Text(title, style: RiderStyle.sectionTitle)),
            ],
          ),
          const SizedBox(height: 14),
          ...children,
        ],
      ),
    );
  }

  Widget _notice(String message, {IconData icon = Icons.info_outline_rounded}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: RiderColors.background,
        border: Border.all(color: RiderColors.border),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: RiderColors.primary, size: 20),
          const SizedBox(width: 10),
          Expanded(child: Text(message, style: RiderStyle.small)),
        ],
      ),
    );
  }
}
