import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../models/parcel_model.dart';
import '../../sevices/api_services.dart';
import '../../styles/rider_style.dart';
import '../../widgets/custom_button.dart';

class DeliveryConfirmation extends StatefulWidget {
  final ParcelModel parcel;

  const DeliveryConfirmation({super.key, required this.parcel});

  @override
  State<DeliveryConfirmation> createState() => _DeliveryConfirmationState();
}

class _DeliveryConfirmationState extends State<DeliveryConfirmation> {
  final ImagePicker _imagePicker = ImagePicker();

  final TextEditingController _receiverController = TextEditingController();
  final TextEditingController _notesController = TextEditingController();

  bool _isLoading = false;
  bool _isLoadingShipment = true;
  bool _proofUploaded = false;

  String? _errorMessage;
  String? _selectedProofPath;

  late String _currentStatus;

  @override
  void initState() {
    super.initState();

    _currentStatus = _normalizeStatus(widget.parcel.status);

    _loadLatestShipment();
  }

  @override
  void dispose() {
    _receiverController.dispose();
    _notesController.dispose();

    super.dispose();
  }

  // ============================================================
  // STATUS HELPERS
  // ============================================================

  String _normalizeStatus(String status) {
    return status
        .trim()
        .toLowerCase()
        .replaceAll(' ', '_')
        .replaceAll('-', '_');
  }

  bool get _isAlreadyDelivered {
    return _currentStatus == 'delivered' || _currentStatus == 'completed';
  }

  bool get _isDeliveryFailed {
    return _currentStatus == 'delivery_failed' ||
        _currentStatus == 'failed' ||
        _currentStatus == 'failed_return_at_sorting_center';
  }

  bool get _isOutForDelivery {
    return _currentStatus == 'out_for_delivery';
  }

  bool get _canStartDelivery {
    if (_isAlreadyDelivered || _isDeliveryFailed || _isLoading) {
      return false;
    }

    return widget.parcel.id > 0 && !_isOutForDelivery;
  }

  bool get _canUploadProof {
    return widget.parcel.id > 0 &&
        _isOutForDelivery &&
        !_proofUploaded &&
        !_isLoading;
  }

  bool get _canConfirmDelivery {
    return widget.parcel.id > 0 &&
        _isOutForDelivery &&
        _proofUploaded &&
        !_isLoading;
  }

  // ============================================================
  // LOAD LATEST SHIPMENT
  // ============================================================

  Future<void> _loadLatestShipment() async {
    if (widget.parcel.id <= 0) {
      if (!mounted) return;

      setState(() {
        _isLoadingShipment = false;
        _errorMessage = 'Invalid shipment ID.';
      });

      return;
    }

    try {
      final Map<String, dynamic> result = await ApiService.getRiderDelivery(
        widget.parcel.id,
      );

      if (!mounted) return;

      if (result['success'] != true) {
        setState(() {
          _isLoadingShipment = false;

          final String message = result['message']?.toString().trim() ?? '';

          _errorMessage = message.isEmpty
              ? 'Unable to load the latest shipment information.'
              : message;
        });

        return;
      }

      final dynamic rawData = result['data'];

      Map<String, dynamic> shipment = <String, dynamic>{};

      if (rawData is Map<String, dynamic>) {
        shipment = rawData;
      } else if (rawData is Map) {
        shipment = Map<String, dynamic>.from(rawData);
      }

      final String serverStatus =
          shipment['status']?.toString().trim() ?? _currentStatus;

      final bool hasProof =
          shipment['has_proof_of_delivery'] == true ||
          (shipment['proof_of_delivery']?.toString().trim().isNotEmpty ??
              false);

      final String deliveredTo =
          shipment['delivered_to']?.toString().trim() ?? '';

      final String notes =
          shipment['delivery_notes']?.toString().trim() ??
          shipment['notes']?.toString().trim() ??
          '';

      setState(() {
        _currentStatus = _normalizeStatus(serverStatus);
        _proofUploaded = hasProof;
        _isLoadingShipment = false;
        _errorMessage = null;

        if (deliveredTo.isNotEmpty) {
          _receiverController.text = deliveredTo;
        }

        if (notes.isNotEmpty) {
          _notesController.text = notes;
        }
      });
    } catch (error) {
      debugPrint('Load rider shipment error: $error');

      if (!mounted) return;

      setState(() {
        _isLoadingShipment = false;
        _errorMessage =
            'Unable to load the latest shipment information. Please try again.';
      });
    }
  }

  // ============================================================
  // MARK OUT FOR DELIVERY
  // ============================================================

  Future<void> _markOutForDelivery() async {
    if (!_canStartDelivery) {
      return;
    }

    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Row(
            children: [
              Icon(Icons.local_shipping_rounded, color: RiderColors.primary),
              SizedBox(width: 10),
              Expanded(
                child: Text('Start Delivery', style: RiderStyle.sectionTitle),
              ),
            ],
          ),
          content: const Text(
            'Mark this parcel as Out for Delivery? '
            'The buyer will be able to see that the parcel is on the way.',
            style: RiderStyle.small,
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext, false);
              },
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () {
                Navigator.pop(dialogContext, true);
              },
              style: RiderStyle.primaryButton,
              child: const Text('Start Delivery', style: RiderStyle.buttonText),
            ),
          ],
        );
      },
    );

    if (!mounted || confirmed != true) {
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.updateRiderDeliveryStatus(
            shipmentId: widget.parcel.id,
            status: 'out_for_delivery',
          );

      if (!mounted) return;

      setState(() {
        _isLoading = false;
      });

      if (result['success'] == true) {
        setState(() {
          _currentStatus = 'out_for_delivery';
        });

        _showSuccessMessage(
          result['message']?.toString().trim().isNotEmpty == true
              ? result['message'].toString().trim()
              : 'Parcel is now out for delivery.',
        );

        return;
      }

      _showErrorMessage(
        _resultMessage(
          result,
          fallback: 'Unable to mark the parcel as out for delivery.',
        ),
      );
    } catch (error) {
      debugPrint('Out for delivery error: $error');

      if (!mounted) return;

      setState(() {
        _isLoading = false;
      });

      _showErrorMessage(
        'Unable to update the shipment. Please check your connection.',
      );
    }
  }

  // ============================================================
  // CHOOSE PROOF PHOTO
  // ============================================================

  Future<void> _chooseProofSource() async {
    if (_isLoading) {
      return;
    }

    final ImageSource? source = await showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (BuildContext sheetContext) {
        return SafeArea(
          child: Container(
            padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
            decoration: const BoxDecoration(
              color: RiderColors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: RiderColors.border,
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                const SizedBox(height: 18),
                const Text('Proof of Delivery', style: RiderStyle.sectionTitle),
                const SizedBox(height: 16),
                ListTile(
                  leading: const Icon(
                    Icons.camera_alt_outlined,
                    color: RiderColors.primary,
                  ),
                  title: const Text('Take Photo'),
                  onTap: () {
                    Navigator.pop(sheetContext, ImageSource.camera);
                  },
                ),
                ListTile(
                  leading: const Icon(
                    Icons.photo_library_outlined,
                    color: RiderColors.primary,
                  ),
                  title: const Text('Choose from Gallery'),
                  onTap: () {
                    Navigator.pop(sheetContext, ImageSource.gallery);
                  },
                ),
              ],
            ),
          ),
        );
      },
    );

    if (!mounted || source == null) {
      return;
    }

    try {
      final XFile? selected = await _imagePicker.pickImage(
        source: source,
        imageQuality: 80,
        maxWidth: 1600,
      );

      if (!mounted || selected == null) {
        return;
      }

      setState(() {
        _selectedProofPath = selected.path;
      });
    } catch (error) {
      debugPrint('Proof image picker error: $error');

      if (!mounted) return;

      _showErrorMessage('Unable to select the proof photo.');
    }
  }

  // ============================================================
  // UPLOAD PROOF
  // ============================================================

  Future<void> _uploadProof() async {
    if (!_canUploadProof) {
      return;
    }

    final String receiverName = _receiverController.text.trim();
    final String deliveryNotes = _notesController.text.trim();

    if (receiverName.isEmpty) {
      _showErrorMessage(
        'Please enter the name of the person who received the parcel.',
      );
      return;
    }

    final String? proofPath = _selectedProofPath;

    if (proofPath == null || proofPath.trim().isEmpty) {
      _showErrorMessage('Please take or choose a proof-of-delivery photo.');
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.uploadRiderDeliveryProof(
            shipmentId: widget.parcel.id,
            proofFilePath: proofPath,
            deliveredTo: receiverName,
            deliveryNotes: deliveryNotes.isEmpty ? null : deliveryNotes,
          );

      if (!mounted) return;

      setState(() {
        _isLoading = false;
      });

      if (result['success'] == true) {
        setState(() {
          _proofUploaded = true;
        });

        _showSuccessMessage(
          result['message']?.toString().trim().isNotEmpty == true
              ? result['message'].toString().trim()
              : 'Delivery proof uploaded successfully.',
        );

        return;
      }

      _showErrorMessage(
        _resultMessage(
          result,
          fallback: 'Unable to upload the delivery proof.',
        ),
      );
    } catch (error) {
      debugPrint('Delivery proof upload error: $error');

      if (!mounted) return;

      setState(() {
        _isLoading = false;
      });

      _showErrorMessage(
        'Unable to upload the delivery proof. Please try again.',
      );
    }
  }

  // ============================================================
  // CONFIRM DELIVERY
  // ============================================================

  Future<void> _confirmDelivery() async {
    if (!_canConfirmDelivery) {
      return;
    }

    final bool? confirmed = await _showConfirmationDialog();

    if (!mounted || confirmed != true) {
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.updateRiderDeliveryStatus(
            shipmentId: widget.parcel.id,
            status: 'delivered',
          );

      if (!mounted) {
        return;
      }

      setState(() {
        _isLoading = false;
      });

      if (result['success'] == true) {
        setState(() {
          _currentStatus = 'delivered';
        });

        final String message = result['message']?.toString().trim() ?? '';

        _showSuccessDialog(
          message.isEmpty
              ? 'The parcel has been successfully marked as delivered.'
              : message,
        );

        return;
      }

      _showErrorMessage(
        _resultMessage(
          result,
          fallback: 'Unable to confirm delivery. Please try again.',
        ),
      );
    } catch (error) {
      debugPrint('Delivery confirmation error: $error');

      if (!mounted) {
        return;
      }

      setState(() {
        _isLoading = false;
      });

      _showErrorMessage(
        'Unable to confirm delivery. Please check your connection and try again.',
      );
    }
  }

  // ============================================================
  // DELIVERY FAILED
  // ============================================================

  Future<void> _markDeliveryFailed() async {
    if (_isLoading || _isAlreadyDelivered || _isDeliveryFailed) {
      return;
    }

    if (!_isOutForDelivery) {
      _showErrorMessage(
        'The shipment must be Out for Delivery before it can be marked as failed.',
      );
      return;
    }

    final TextEditingController reasonController = TextEditingController();

    final String? reason = await showDialog<String>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Row(
            children: [
              Icon(Icons.warning_amber_rounded, color: RiderColors.danger),
              SizedBox(width: 10),
              Expanded(
                child: Text('Delivery Failed', style: RiderStyle.sectionTitle),
              ),
            ],
          ),
          content: TextField(
            controller: reasonController,
            minLines: 3,
            maxLines: 5,
            decoration: InputDecoration(
              hintText: 'Enter the reason for failed delivery...',
              filled: true,
              fillColor: RiderColors.background,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);
              },
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () {
                final String value = reasonController.text.trim();

                if (value.isEmpty) {
                  return;
                }

                Navigator.pop(dialogContext, value);
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: RiderColors.danger,
              ),
              child: const Text(
                'Confirm Failed',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        );
      },
    );

    reasonController.dispose();

    if (!mounted || reason == null || reason.trim().isEmpty) {
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      final Map<String, dynamic> result =
          await ApiService.updateRiderDeliveryStatus(
            shipmentId: widget.parcel.id,
            status: 'failed',
          );

      if (!mounted) return;

      setState(() {
        _isLoading = false;
      });

      if (result['success'] == true) {
        setState(() {
          _currentStatus = 'delivery_failed';
        });

        _showSuccessDialog(
          result['message']?.toString().trim().isNotEmpty == true
              ? result['message'].toString().trim()
              : 'Delivery has been marked as failed.',
          closePageTitle: 'Delivery Failed',
        );

        return;
      }

      _showErrorMessage(
        _resultMessage(
          result,
          fallback: 'Unable to mark the delivery as failed.',
        ),
      );
    } catch (error) {
      debugPrint('Delivery failed update error: $error');

      if (!mounted) return;

      setState(() {
        _isLoading = false;
      });

      _showErrorMessage('Unable to update the shipment. Please try again.');
    }
  }

  // ============================================================
  // CONFIRMATION DIALOG
  // ============================================================

  Future<bool?> _showConfirmationDialog() {
    return showDialog<bool>(
      context: context,
      barrierDismissible: !_isLoading,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Row(
            children: [
              Icon(
                Icons.check_circle_outline_rounded,
                color: RiderColors.success,
              ),
              SizedBox(width: 10),
              Expanded(
                child: Text('Confirm Delivery', style: RiderStyle.sectionTitle),
              ),
            ],
          ),
          content: const Text(
            'Confirm only after the customer has received the parcel. '
            'This will mark the shipment as delivered and update the buyer order.',
            style: RiderStyle.small,
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext, false);
              },
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () {
                Navigator.pop(dialogContext, true);
              },
              style: RiderStyle.primaryButton,
              child: const Text('Confirm', style: RiderStyle.buttonText),
            ),
          ],
        );
      },
    );
  }

  // ============================================================
  // SUCCESS DIALOG
  // ============================================================

  void _showSuccessDialog(
    String message, {
    String closePageTitle = 'Parcel Delivered Successfully',
  }) {
    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                height: 82,
                width: 82,
                decoration: const BoxDecoration(
                  color: RiderColors.lightGreen,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_circle_rounded,
                  color: RiderColors.success,
                  size: 54,
                ),
              ),
              const SizedBox(height: 18),
              Text(
                closePageTitle,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: RiderColors.textDark,
                  fontWeight: FontWeight.w800,
                  fontSize: 18,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: RiderStyle.small,
              ),
            ],
          ),
          actionsAlignment: MainAxisAlignment.center,
          actions: [
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.pop(dialogContext);

                  if (!mounted) {
                    return;
                  }

                  Navigator.pop(context, true);
                },
                style: RiderStyle.primaryButton,
                child: const Text('Done', style: RiderStyle.buttonText),
              ),
            ),
          ],
        );
      },
    );
  }

  // ============================================================
  // MESSAGES
  // ============================================================

  void _showSuccessMessage(String message) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: RiderColors.success,
        content: Text(message),
      ),
    );
  }

  void _showErrorMessage(String message) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: RiderColors.danger,
        content: Text(message),
      ),
    );
  }

  String _resultMessage(
    Map<String, dynamic> result, {
    required String fallback,
  }) {
    final String message = result['message']?.toString().trim() ?? '';

    if (message.isNotEmpty) {
      return message;
    }

    final dynamic rawErrors = result['errors'];

    if (rawErrors is Map) {
      for (final dynamic value in rawErrors.values) {
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }

        final String text = value?.toString().trim() ?? '';

        if (text.isNotEmpty) {
          return text;
        }
      }
    }

    return fallback;
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final ParcelModel parcel = widget.parcel;

    return Scaffold(
      backgroundColor: RiderColors.background,
      appBar: AppBar(
        backgroundColor: RiderColors.white,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        iconTheme: const IconThemeData(color: RiderColors.textDark),
        title: const Text('Delivery', style: RiderStyle.title),
      ),
      body: SafeArea(
        top: false,
        child: _isLoadingShipment
            ? const Center(
                child: CircularProgressIndicator(color: RiderColors.primary),
              )
            : RefreshIndicator(
                onRefresh: _loadLatestShipment,
                child: SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(
                    parent: BouncingScrollPhysics(),
                  ),
                  padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _statusHeader(),
                      const SizedBox(height: 16),

                      if (_errorMessage != null) ...[
                        _errorBanner(),
                        const SizedBox(height: 16),
                      ],

                      _parcelInformation(parcel),
                      const SizedBox(height: 18),

                      _deliveryProgressCard(),

                      if (_isOutForDelivery &&
                          !_isAlreadyDelivered &&
                          !_isDeliveryFailed) ...[
                        const SizedBox(height: 18),
                        _proofSection(),
                      ],

                      const SizedBox(height: 24),
                      _actionSection(),
                      const SizedBox(height: 14),

                      if (!_isAlreadyDelivered && !_isDeliveryFailed)
                        const Text(
                          'Delivery updates are synced to the server and can be reflected in buyer order tracking.',
                          textAlign: TextAlign.center,
                          style: RiderStyle.small,
                        ),
                    ],
                  ),
                ),
              ),
      ),
    );
  }

  // ============================================================
  // STATUS HEADER
  // ============================================================

  Widget _statusHeader() {
    IconData icon = Icons.local_shipping_rounded;
    Color iconColor = RiderColors.primary;
    Color background = RiderColors.lightPurple;

    String title = 'Delivery in Progress';
    String subtitle = 'Review the shipment and update its delivery status.';

    if (_isAlreadyDelivered) {
      icon = Icons.check_circle_rounded;
      iconColor = RiderColors.success;
      background = RiderColors.lightGreen;
      title = 'Delivery Completed';
      subtitle = 'This parcel has been delivered successfully.';
    } else if (_isDeliveryFailed) {
      icon = Icons.error_outline_rounded;
      iconColor = RiderColors.danger;
      title = 'Delivery Failed';
      subtitle = 'This parcel was not successfully delivered.';
    } else if (_isOutForDelivery) {
      icon = Icons.delivery_dining_rounded;
      title = 'Out for Delivery';
      subtitle = _proofUploaded
          ? 'Proof uploaded. You can now complete the delivery.'
          : 'Upload proof after the customer receives the parcel.';
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: RiderColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: RiderColors.border),
      ),
      child: Row(
        children: [
          Container(
            height: 48,
            width: 48,
            decoration: BoxDecoration(
              color: background,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(icon, color: iconColor, size: 25),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: RiderStyle.cardTitle),
                const SizedBox(height: 3),
                Text(subtitle, style: RiderStyle.small),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // ERROR BANNER
  // ============================================================

  Widget _errorBanner() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: RiderColors.danger.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: RiderColors.danger.withValues(alpha: 0.18)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline_rounded, color: RiderColors.danger),
          const SizedBox(width: 10),
          Expanded(child: Text(_errorMessage ?? '', style: RiderStyle.small)),
        ],
      ),
    );
  }

  // ============================================================
  // PARCEL INFORMATION
  // ============================================================

  Widget _parcelInformation(ParcelModel parcel) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Parcel Information', style: RiderStyle.sectionTitle),
          const SizedBox(height: 18),

          _infoRow(
            icon: Icons.qr_code_2_rounded,
            title: 'Tracking Number',
            value: _displayValue(
              parcel.trackingNumber,
              fallback: 'Tracking unavailable',
            ),
          ),

          _infoRow(
            icon: Icons.person_outline_rounded,
            title: 'Customer',
            value: _displayValue(parcel.customerName),
          ),

          _infoRow(
            icon: Icons.phone_outlined,
            title: 'Phone Number',
            value: _displayValue(parcel.customerPhone),
          ),

          _infoRow(
            icon: Icons.location_on_outlined,
            title: 'Delivery Address',
            value: _displayValue(parcel.deliveryAddress),
          ),

          _infoRow(
            icon: Icons.local_shipping_outlined,
            title: 'Status',
            value: _formatStatus(_currentStatus),
            isLast: true,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // DELIVERY PROGRESS
  // ============================================================

  Widget _deliveryProgressCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Delivery Progress', style: RiderStyle.sectionTitle),
          const SizedBox(height: 16),

          _progressItem(
            label: 'Out for Delivery',
            completed:
                _isOutForDelivery || _isAlreadyDelivered || _isDeliveryFailed,
          ),

          _progressLine(),

          _progressItem(
            label: 'Proof of Delivery',
            completed: _proofUploaded || _isAlreadyDelivered,
          ),

          _progressLine(),

          _progressItem(label: 'Delivered', completed: _isAlreadyDelivered),
        ],
      ),
    );
  }

  Widget _progressItem({required String label, required bool completed}) {
    return Row(
      children: [
        Container(
          height: 28,
          width: 28,
          decoration: BoxDecoration(
            color: completed ? RiderColors.lightGreen : RiderColors.background,
            shape: BoxShape.circle,
            border: Border.all(
              color: completed ? RiderColors.success : RiderColors.border,
            ),
          ),
          child: Icon(
            completed ? Icons.check_rounded : Icons.circle_outlined,
            size: 16,
            color: completed ? RiderColors.success : RiderColors.textGrey,
          ),
        ),
        const SizedBox(width: 11),
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              color: completed ? RiderColors.textDark : RiderColors.textGrey,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }

  Widget _progressLine() {
    return Container(
      margin: const EdgeInsets.only(left: 13, top: 4, bottom: 4),
      width: 2,
      height: 22,
      color: RiderColors.border,
    );
  }

  // ============================================================
  // PROOF SECTION
  // ============================================================

  Widget _proofSection() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: RiderStyle.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Proof of Delivery', style: RiderStyle.sectionTitle),
          const SizedBox(height: 6),
          const Text(
            'Provide the receiver name and a photo after the parcel has been received.',
            style: RiderStyle.small,
          ),
          const SizedBox(height: 18),

          TextField(
            controller: _receiverController,
            enabled: !_proofUploaded && !_isLoading,
            decoration: InputDecoration(
              labelText: 'Received By',
              hintText: 'Name of customer or receiver',
              prefixIcon: const Icon(Icons.person_outline_rounded),
              filled: true,
              fillColor: RiderColors.background,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),

          const SizedBox(height: 14),

          TextField(
            controller: _notesController,
            enabled: !_proofUploaded && !_isLoading,
            minLines: 2,
            maxLines: 4,
            decoration: InputDecoration(
              labelText: 'Delivery Notes',
              hintText: 'Optional notes',
              prefixIcon: const Icon(Icons.notes_rounded),
              filled: true,
              fillColor: RiderColors.background,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),

          const SizedBox(height: 16),

          if (_proofUploaded)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: RiderColors.lightGreen,
                borderRadius: BorderRadius.circular(13),
              ),
              child: const Row(
                children: [
                  Icon(Icons.verified_rounded, color: RiderColors.success),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Proof of delivery uploaded',
                      style: TextStyle(
                        color: RiderColors.success,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
            )
          else ...[
            if (_selectedProofPath != null) ...[
              ClipRRect(
                borderRadius: BorderRadius.circular(14),
                child: Image.file(
                  File(_selectedProofPath!),
                  width: double.infinity,
                  height: 190,
                  fit: BoxFit.cover,
                ),
              ),
              const SizedBox(height: 12),
            ],

            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: _isLoading ? null : _chooseProofSource,
                icon: const Icon(Icons.add_a_photo_outlined),
                label: Text(
                  _selectedProofPath == null
                      ? 'ADD PROOF PHOTO'
                      : 'CHANGE PROOF PHOTO',
                ),
                style: OutlinedButton.styleFrom(
                  foregroundColor: RiderColors.primary,
                  side: const BorderSide(color: RiderColors.primary),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  // ============================================================
  // ACTION SECTION
  // ============================================================

  Widget _actionSection() {
    if (_isAlreadyDelivered) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: RiderStyle.card,
        child: const Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.verified_rounded, color: RiderColors.success),
            SizedBox(width: 8),
            Text(
              'DELIVERY COMPLETED',
              style: TextStyle(
                color: RiderColors.success,
                fontWeight: FontWeight.w800,
                fontSize: 13,
              ),
            ),
          ],
        ),
      );
    }

    if (_isDeliveryFailed) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: RiderStyle.card,
        child: const Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline_rounded, color: RiderColors.danger),
            SizedBox(width: 8),
            Text(
              'DELIVERY FAILED',
              style: TextStyle(
                color: RiderColors.danger,
                fontWeight: FontWeight.w800,
                fontSize: 13,
              ),
            ),
          ],
        ),
      );
    }

    if (!_isOutForDelivery) {
      return CustomButton(
        text: 'MARK OUT FOR DELIVERY',
        icon: Icons.delivery_dining_rounded,
        isLoading: _isLoading,
        backgroundColor: RiderColors.primary,
        onPressed: _canStartDelivery ? _markOutForDelivery : null,
      );
    }

    return Column(
      children: [
        if (!_proofUploaded)
          CustomButton(
            text: 'UPLOAD DELIVERY PROOF',
            icon: Icons.cloud_upload_outlined,
            isLoading: _isLoading,
            backgroundColor: RiderColors.primary,
            onPressed: _canUploadProof ? _uploadProof : null,
          )
        else
          CustomButton(
            text: 'CONFIRM DELIVERY',
            icon: Icons.check_circle_outline_rounded,
            isLoading: _isLoading,
            backgroundColor: RiderColors.success,
            onPressed: _canConfirmDelivery ? _confirmDelivery : null,
          ),

        const SizedBox(height: 12),

        SizedBox(
          width: double.infinity,
          height: 48,
          child: OutlinedButton.icon(
            onPressed: _isLoading ? null : _markDeliveryFailed,
            icon: const Icon(Icons.error_outline_rounded),
            label: const Text('MARK DELIVERY FAILED'),
            style: OutlinedButton.styleFrom(
              foregroundColor: RiderColors.danger,
              side: const BorderSide(color: RiderColors.danger),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              textStyle: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // INFO ROW
  // ============================================================

  Widget _infoRow({
    required IconData icon,
    required String title,
    required String value,
    bool isLast = false,
  }) {
    return Padding(
      padding: EdgeInsets.only(bottom: isLast ? 0 : 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 36,
            width: 36,
            decoration: BoxDecoration(
              color: RiderColors.lightPurple,
              borderRadius: BorderRadius.circular(10),
            ),
            alignment: Alignment.center,
            child: Icon(icon, color: RiderColors.primary, size: 18),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: RiderStyle.small),
                const SizedBox(height: 3),
                Text(value, style: RiderStyle.cardTitle),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // TEXT HELPERS
  // ============================================================

  String _displayValue(String value, {String fallback = 'Not available'}) {
    final String cleaned = value.trim();

    if (cleaned.isEmpty || cleaned.toLowerCase() == 'null') {
      return fallback;
    }

    return cleaned;
  }

  String _formatStatus(String status) {
    final String cleaned = status
        .trim()
        .replaceAll('_', ' ')
        .replaceAll('-', ' ');

    if (cleaned.isEmpty) {
      return 'Not available';
    }

    return cleaned
        .split(RegExp(r'\s+'))
        .map((String word) {
          if (word.isEmpty) {
            return word;
          }

          return '${word[0].toUpperCase()}'
              '${word.substring(1).toLowerCase()}';
        })
        .join(' ');
  }
}
