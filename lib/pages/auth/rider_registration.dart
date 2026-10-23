import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../../sevices/api_services.dart';
import '../../styles/registration_style.dart';

class RiderRegistrationPage extends StatefulWidget {
  const RiderRegistrationPage({super.key});

  @override
  State<RiderRegistrationPage> createState() => _RiderRegistrationPageState();
}

class _RiderRegistrationPageState extends State<RiderRegistrationPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  // ============================================================
  // CONTROLLERS
  // ============================================================

  final TextEditingController firstNameController = TextEditingController();

  final TextEditingController lastNameController = TextEditingController();

  final TextEditingController birthDateController = TextEditingController();

  final TextEditingController emailController = TextEditingController();

  final TextEditingController mobileController = TextEditingController();

  final TextEditingController licenseNumberController = TextEditingController();

  final TextEditingController licenseExpiryController = TextEditingController();

  final TextEditingController plateNumberController = TextEditingController();

  // ============================================================
  // FOCUS NODES
  // ============================================================

  final FocusNode firstNameFocusNode = FocusNode();
  final FocusNode lastNameFocusNode = FocusNode();
  final FocusNode emailFocusNode = FocusNode();
  final FocusNode mobileFocusNode = FocusNode();
  final FocusNode licenseNumberFocusNode = FocusNode();
  final FocusNode plateNumberFocusNode = FocusNode();

  // ============================================================
  // STATE
  // ============================================================

  final ImagePicker _imagePicker = ImagePicker();

  bool isSaving = false;
  bool _hasChanges = false;

  DateTime? selectedBirthDate;
  DateTime? selectedLicenseExpiry;

  String? selectedVehicleType;

  String? driverLicenseFilePath;
  String? driverLicenseFileName;

  String? vehicleOrCrFilePath;
  String? vehicleOrCrFileName;

  String? driverLicenseUploadError;
  String? vehicleOrCrUploadError;

  final List<String> vehicleTypes = const ['Motorcycle', 'Car', 'Van'];

  // ============================================================
  // CHANGE TRACKING
  // ============================================================

  void _markChanged() {
    if (!_hasChanges) {
      setState(() {
        _hasChanges = true;
      });
    }
  }

  // ============================================================
  // DATE HELPERS
  // ============================================================

  String _formatDateForDisplay(DateTime date) {
    final String month = date.month.toString().padLeft(2, '0');

    final String day = date.day.toString().padLeft(2, '0');

    return '$month/$day/${date.year}';
  }

  String _formatDateForApi(DateTime date) {
    final String month = date.month.toString().padLeft(2, '0');

    final String day = date.day.toString().padLeft(2, '0');

    return '${date.year}-$month-$day';
  }

  int _calculateAge(DateTime birthDate) {
    final DateTime today = DateTime.now();

    int age = today.year - birthDate.year;

    final bool birthdayNotReached =
        today.month < birthDate.month ||
        (today.month == birthDate.month && today.day < birthDate.day);

    if (birthdayNotReached) {
      age--;
    }

    return age;
  }

  Future<void> _pickBirthDate() async {
    if (isSaving) return;

    FocusScope.of(context).unfocus();

    final DateTime today = DateTime.now();

    final DateTime eighteenYearsAgo = DateTime(
      today.year - 18,
      today.month,
      today.day,
    );

    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: selectedBirthDate ?? eighteenYearsAgo,
      firstDate: DateTime(1900),
      lastDate: today,
      helpText: 'Select Rider Date of Birth',
      confirmText: 'Select',
      cancelText: 'Cancel',
    );

    if (picked == null || !mounted) return;

    setState(() {
      selectedBirthDate = picked;

      birthDateController.text = _formatDateForDisplay(picked);

      _hasChanges = true;
    });

    _formKey.currentState?.validate();
  }

  Future<void> _pickLicenseExpiry() async {
    if (isSaving) return;

    FocusScope.of(context).unfocus();

    final DateTime today = DateTime.now();

    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: selectedLicenseExpiry ?? today,
      firstDate: today,
      lastDate: DateTime(today.year + 20, 12, 31),
      helpText: 'Select License Expiry Date',
      confirmText: 'Select',
      cancelText: 'Cancel',
    );

    if (picked == null || !mounted) return;

    setState(() {
      selectedLicenseExpiry = picked;

      licenseExpiryController.text = _formatDateForDisplay(picked);

      _hasChanges = true;
    });

    _formKey.currentState?.validate();
  }

  // ============================================================
  // VALIDATORS
  // ============================================================

  String? _validateFirstName(String? value) {
    final String input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the rider\'s first name';
    }

    if (input.length < 2) {
      return 'First name must contain at least 2 characters';
    }

    if (input.length > 50) {
      return 'First name must not exceed 50 characters';
    }

    final RegExp nameRegex = RegExp(r"^[A-Za-zÑñ .'-]+$");

    if (!nameRegex.hasMatch(input)) {
      return 'Only letters are allowed';
    }

    if (!RegExp(r'[A-Za-zÑñ]').hasMatch(input)) {
      return 'Please enter a valid first name';
    }

    return null;
  }

  String? _validateLastName(String? value) {
    final String input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the rider\'s last name';
    }

    if (input.length < 2) {
      return 'Last name must contain at least 2 characters';
    }

    if (input.length > 50) {
      return 'Last name must not exceed 50 characters';
    }

    final RegExp nameRegex = RegExp(r"^[A-Za-zÑñ .'-]+$");

    if (!nameRegex.hasMatch(input)) {
      return 'Only letters are allowed';
    }

    if (!RegExp(r'[A-Za-zÑñ]').hasMatch(input)) {
      return 'Please enter a valid last name';
    }

    return null;
  }

  String? _validateBirthDate(String? value) {
    if (selectedBirthDate == null) {
      return 'Please select the rider\'s date of birth';
    }

    final int age = _calculateAge(selectedBirthDate!);

    if (age < 18) {
      return 'Rider must be at least 18 years old';
    }

    if (age > 120) {
      return 'Please select a valid date of birth';
    }

    return null;
  }

  String? _validateEmail(String? value) {
    final String input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the rider\'s email address';
    }

    if (input.contains(' ')) {
      return 'Email address must not contain spaces';
    }

    final RegExp emailRegex = RegExp(
      r'^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$',
    );

    if (!emailRegex.hasMatch(input)) {
      return 'Please enter a valid email address';
    }

    return null;
  }

  String? _validateMobile(String? value) {
    final String input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the rider\'s mobile number';
    }

    if (input.length != 11) {
      return 'Mobile number must contain exactly 11 digits';
    }

    if (!RegExp(r'^09\d{9}$').hasMatch(input)) {
      return 'Use a valid mobile number: 09XXXXXXXXX';
    }

    return null;
  }

  String? _validateLicenseNumber(String? value) {
    final String input = value?.trim().toUpperCase() ?? '';

    if (input.isEmpty) {
      return 'Please enter the driver\'s license number';
    }

    final RegExp licenseRegex = RegExp(r'^[A-Z]\d{2}-\d{2}-\d{6}$');

    if (!licenseRegex.hasMatch(input)) {
      return 'Use license format A00-00-000000';
    }

    return null;
  }

  String? _validateLicenseExpiry(String? value) {
    if (selectedLicenseExpiry == null) {
      return 'Please select the license expiry date';
    }

    final DateTime today = DateTime.now();

    final DateTime currentDate = DateTime(today.year, today.month, today.day);

    final DateTime expiry = DateTime(
      selectedLicenseExpiry!.year,
      selectedLicenseExpiry!.month,
      selectedLicenseExpiry!.day,
    );

    if (expiry.isBefore(currentDate)) {
      return 'Driver\'s license is already expired';
    }

    return null;
  }

  String? _validatePlateNumber(String? value) {
    final String input = value?.trim().toUpperCase() ?? '';

    if (input.isEmpty) {
      return 'Please enter the vehicle plate number';
    }

    if (input.length < 5 || input.length > 10) {
      return 'Please enter a valid plate number';
    }

    if (!RegExp(r'^[A-Z0-9 -]+$').hasMatch(input)) {
      return 'Plate number contains invalid characters';
    }

    return null;
  }

  // ============================================================
  // FILE HANDLING
  // ============================================================

  Future<void> _showUploadOptions({required bool isLicense}) async {
    if (isSaving) return;

    FocusScope.of(context).unfocus();

    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (BuildContext sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  isLicense
                      ? 'Upload Driver\'s License'
                      : 'Upload Vehicle OR/CR',
                  style: RegistrationStyle.sectionTitle,
                ),

                const SizedBox(height: 16),

                ListTile(
                  leading: const Icon(
                    Icons.camera_alt_outlined,
                    color: RegistrationColors.primary,
                  ),
                  title: const Text('Take Photo'),
                  subtitle: const Text('Use your camera'),
                  onTap: () {
                    Navigator.pop(sheetContext);

                    _pickImage(
                      source: ImageSource.camera,
                      isLicense: isLicense,
                    );
                  },
                ),

                ListTile(
                  leading: const Icon(
                    Icons.photo_library_outlined,
                    color: RegistrationColors.primary,
                  ),
                  title: const Text('Gallery'),
                  subtitle: const Text('Choose a JPG or PNG image'),
                  onTap: () {
                    Navigator.pop(sheetContext);

                    _pickImage(
                      source: ImageSource.gallery,
                      isLicense: isLicense,
                    );
                  },
                ),

                ListTile(
                  leading: const Icon(
                    Icons.folder_open_outlined,
                    color: RegistrationColors.primary,
                  ),
                  title: const Text('Files'),
                  subtitle: const Text('Choose JPG, PNG, or PDF'),
                  onTap: () {
                    Navigator.pop(sheetContext);

                    _pickFile(isLicense: isLicense);
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _pickImage({
    required ImageSource source,
    required bool isLicense,
  }) async {
    try {
      final XFile? selected = await _imagePicker.pickImage(
        source: source,
        imageQuality: 85,
      );

      if (selected == null) {
        return;
      }

      await _validateAndSaveFile(
        path: selected.path,
        name: selected.name,
        isLicense: isLicense,
      );
    } catch (_) {
      if (!mounted) return;

      _showMessage(
        'Unable to access the camera or gallery. '
        'Please check the app permission and try again.',
      );
    }
  }

  // ============================================================
  // FILE PICKER 13.1.0
  //
  // FilePickerResult was removed in file_picker 12+.
  // For a single file, FilePicker.pickFile() now returns
  // PlatformFile?.
  // ============================================================

  Future<void> _pickFile({required bool isLicense}) async {
    try {
      final PlatformFile? file = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: const ['jpg', 'jpeg', 'png', 'pdf'],
      );

      if (file == null) {
        return;
      }

      if (file.path == null || file.path!.trim().isEmpty) {
        _showMessage('Unable to read the selected file.');

        return;
      }

      await _validateAndSaveFile(
        path: file.path!,
        name: file.name,
        isLicense: isLicense,
      );
    } catch (_) {
      if (!mounted) return;

      _showMessage('Unable to open your files. Please try again.');
    }
  }

  Future<void> _validateAndSaveFile({
    required String path,
    required String name,
    required bool isLicense,
  }) async {
    final String extension = name.split('.').last.toLowerCase();

    const Set<String> acceptedExtensions = {'jpg', 'jpeg', 'png', 'pdf'};

    if (!acceptedExtensions.contains(extension)) {
      _setUploadError(isLicense, 'Only JPG, PNG, and PDF files are allowed.');

      return;
    }

    try {
      final File file = File(path);

      final int size = await file.length();

      const int maxSize = 5 * 1024 * 1024;

      if (size > maxSize) {
        _setUploadError(isLicense, 'File must not be larger than 5 MB.');

        return;
      }

      if (!mounted) return;

      setState(() {
        _hasChanges = true;

        if (isLicense) {
          driverLicenseFilePath = path;

          driverLicenseFileName = name;

          driverLicenseUploadError = null;
        } else {
          vehicleOrCrFilePath = path;

          vehicleOrCrFileName = name;

          vehicleOrCrUploadError = null;
        }
      });
    } catch (_) {
      _setUploadError(isLicense, 'Unable to read the selected file.');
    }
  }

  void _setUploadError(bool isLicense, String message) {
    if (!mounted) return;

    setState(() {
      if (isLicense) {
        driverLicenseUploadError = message;
      } else {
        vehicleOrCrUploadError = message;
      }
    });

    _showMessage(message);
  }

  // ============================================================
  // API ERROR HELPER
  // ============================================================

  String _extractApiErrorMessage(Map<String, dynamic> result) {
    final dynamic errors = result['errors'];

    if (errors is Map) {
      for (final dynamic value in errors.values) {
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }

        if (value != null && value.toString().trim().isNotEmpty) {
          return value.toString();
        }
      }
    }

    final String message = result['message']?.toString().trim() ?? '';

    if (message.isNotEmpty) {
      return message;
    }

    return 'Unable to create Rider account. '
        'Please check the information and try again.';
  }

  // ============================================================
  // SAVE RIDER
  // ============================================================

  Future<void> _saveRider() async {
    if (isSaving) return;

    FocusScope.of(context).unfocus();

    final bool formValid = _formKey.currentState?.validate() ?? false;

    setState(() {
      driverLicenseUploadError = driverLicenseFilePath == null
          ? 'Driver\'s License document is required'
          : null;

      vehicleOrCrUploadError = vehicleOrCrFilePath == null
          ? 'Vehicle OR/CR document is required'
          : null;
    });

    if (!formValid ||
        driverLicenseFilePath == null ||
        vehicleOrCrFilePath == null) {
      _showMessage('Please complete all required rider information.');

      return;
    }

    if (selectedVehicleType == null) {
      _showMessage('Please select the rider\'s vehicle type.');

      return;
    }

    if (selectedBirthDate == null) {
      _showMessage('Please select the rider\'s date of birth.');

      return;
    }

    if (selectedLicenseExpiry == null) {
      _showMessage('Please select the driver\'s license expiry date.');

      return;
    }

    setState(() {
      isSaving = true;
    });

    try {
      final Map<String, dynamic> result = await ApiService.createRider(
        firstName: firstNameController.text.trim(),

        lastName: lastNameController.text.trim(),

        birthday: _formatDateForApi(selectedBirthDate!),

        email: emailController.text.trim().toLowerCase(),

        mobileNumber: mobileController.text.trim(),

        driverLicenseNumber: licenseNumberController.text.trim().toUpperCase(),

        licenseExpiryDate: _formatDateForApi(selectedLicenseExpiry!),

        vehicleType: selectedVehicleType!,

        plateNumber: plateNumberController.text.trim().toUpperCase(),

        driverLicenseFilePath: driverLicenseFilePath!,

        vehicleOrCrFilePath: vehicleOrCrFilePath!,
      );

      if (!mounted) return;

      setState(() {
        isSaving = false;
      });

      if (result['success'] != true) {
        final String message = _extractApiErrorMessage(result);

        _showMessage(message);

        return;
      }

      final String message = result['message']?.toString().trim() ?? '';

      _showMessage(
        message.isNotEmpty
            ? message
            : 'Rider account created successfully. '
                  'An activation link has been sent to the rider.',
        success: true,
      );

      setState(() {
        _hasChanges = false;
      });

      await Future.delayed(const Duration(milliseconds: 900));

      if (!mounted) return;

      Navigator.pop(context, true);
    } catch (_) {
      if (!mounted) return;

      setState(() {
        isSaving = false;
      });

      _showMessage(
        'Unable to connect to the server. '
        'Please check your internet connection and try again.',
      );
    }
  }

  // ============================================================
  // BACK / UNSAVED DATA
  // ============================================================

  Future<bool> _confirmLeave() async {
    if (!_hasChanges || isSaving) {
      return true;
    }

    final bool? result = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          title: const Text('Discard changes?'),
          content: const Text(
            'You have unsaved rider information. '
            'Leaving this page will discard your changes.',
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext, false);
              },
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext, true);
              },
              child: const Text('Discard'),
            ),
          ],
        );
      },
    );

    return result ?? false;
  }

  Future<void> _handleBack() async {
    final bool canLeave = await _confirmLeave();

    if (!canLeave || !mounted) {
      return;
    }

    Navigator.pop(context);
  }

  // ============================================================
  // MESSAGE
  // ============================================================

  void _showMessage(String message, {bool success = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          margin: const EdgeInsets.all(16),
          backgroundColor: success
              ? RegistrationColors.success
              : RegistrationColors.textDark,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          content: Row(
            children: [
              Icon(
                success
                    ? Icons.check_circle_outline_rounded
                    : Icons.info_outline_rounded,
                color: Colors.white,
              ),

              const SizedBox(width: 10),

              Expanded(child: Text(message)),
            ],
          ),
        ),
      );
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final Size screenSize = MediaQuery.of(context).size;

    final double horizontalPadding = screenSize.width < 360 ? 16 : 22;

    return PopScope(
      canPop: !_hasChanges && !isSaving,

      onPopInvokedWithResult: (bool didPop, dynamic result) async {
        if (didPop) return;

        await _handleBack();
      },

      child: Scaffold(
        backgroundColor: RegistrationColors.background,

        appBar: AppBar(
          backgroundColor: RegistrationColors.background,

          elevation: 0,

          scrolledUnderElevation: 0,

          centerTitle: true,

          leading: IconButton(
            tooltip: 'Back',

            onPressed: isSaving ? null : _handleBack,

            icon: const Icon(
              Icons.arrow_back_ios_new_rounded,
              color: RegistrationColors.textDark,
              size: 20,
            ),
          ),

          title: const Text('Add Rider', style: RegistrationStyle.appBarTitle),
        ),

        body: SafeArea(
          top: false,

          child: GestureDetector(
            behavior: HitTestBehavior.opaque,

            onTap: () {
              FocusScope.of(context).unfocus();
            },

            child: SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,

              padding: EdgeInsets.fromLTRB(
                horizontalPadding,
                10,
                horizontalPadding,
                30,
              ),

              child: Center(
                child: Container(
                  width: double.infinity,

                  constraints: const BoxConstraints(maxWidth: 460),

                  padding: const EdgeInsets.all(24),

                  decoration: RegistrationStyle.card,

                  child: Form(
                    key: _formKey,

                    autovalidateMode: AutovalidateMode.onUserInteraction,

                    onChanged: _markChanged,

                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,

                      children: [
                        // ======================================
                        // HEADER
                        // ======================================

                        Center(
                          child: Container(
                            height: 64,
                            width: 64,

                            decoration: RegistrationStyle.logoContainer,

                            child: const Icon(
                              Icons.delivery_dining_outlined,
                              color: RegistrationColors.primary,
                              size: 34,
                            ),
                          ),
                        ),

                        const SizedBox(height: 14),

                        const Center(
                          child: Text(
                            'Create Rider Account',
                            textAlign: TextAlign.center,
                            style: RegistrationStyle.title,
                          ),
                        ),

                        const SizedBox(height: 8),

                        const Text(
                          'Add a rider to your logistics team. '
                          'After saving, the rider will receive an activation link '
                          'to create their password.',
                          textAlign: TextAlign.center,
                          style: RegistrationStyle.subtitle,
                        ),

                        const SizedBox(height: 28),

                        // ======================================
                        // FIRST NAME
                        // ======================================
                        _fieldLabel('First Name'),

                        TextFormField(
                          controller: firstNameController,

                          focusNode: firstNameFocusNode,

                          enabled: !isSaving,

                          keyboardType: TextInputType.name,

                          textCapitalization: TextCapitalization.words,

                          textInputAction: TextInputAction.next,

                          maxLength: 50,

                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                              RegExp(r"[A-Za-zÑñ .'-]"),
                            ),
                          ],

                          validator: _validateFirstName,

                          onFieldSubmitted: (_) {
                            lastNameFocusNode.requestFocus();
                          },

                          style: RegistrationStyle.inputText,

                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'Enter first name',
                            icon: Icons.person_outline_rounded,
                          ).copyWith(counterText: ''),
                        ),

                        const SizedBox(height: 18),

                        // ======================================
                        // LAST NAME
                        // ======================================
                        _fieldLabel('Last Name'),

                        TextFormField(
                          controller: lastNameController,

                          focusNode: lastNameFocusNode,

                          enabled: !isSaving,

                          keyboardType: TextInputType.name,

                          textCapitalization: TextCapitalization.words,

                          textInputAction: TextInputAction.next,

                          maxLength: 50,

                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                              RegExp(r"[A-Za-zÑñ .'-]"),
                            ),
                          ],

                          validator: _validateLastName,

                          style: RegistrationStyle.inputText,

                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'Enter last name',
                            icon: Icons.person_outline_rounded,
                          ).copyWith(counterText: ''),

                          onFieldSubmitted: (_) {
                            FocusScope.of(context).unfocus();

                            _pickBirthDate();
                          },
                        ),

                        const SizedBox(height: 18),

                        // ======================================
                        // DOB
                        // ======================================
                        _fieldLabel('Date of Birth'),

                        TextFormField(
                          controller: birthDateController,

                          readOnly: true,

                          showCursor: false,

                          enabled: !isSaving,

                          onTap: _pickBirthDate,

                          validator: _validateBirthDate,

                          style: RegistrationStyle.inputText,

                          decoration:
                              RegistrationStyle.inputDecoration(
                                hint: 'MM/DD/YYYY',
                                icon: Icons.cake_outlined,
                              ).copyWith(
                                suffixIcon: IconButton(
                                  tooltip: 'Select date',

                                  onPressed: isSaving ? null : _pickBirthDate,

                                  icon: const Icon(
                                    Icons.calendar_month_outlined,
                                    color: RegistrationColors.textGrey,
                                  ),
                                ),
                              ),
                        ),

                        const SizedBox(height: 8),

                        const Text(
                          'Rider must be at least 18 years old.',
                          style: RegistrationStyle.termsText,
                        ),

                        const SizedBox(height: 18),

                        // ======================================
                        // EMAIL
                        // ======================================
                        _fieldLabel('Email Address'),

                        TextFormField(
                          controller: emailController,

                          focusNode: emailFocusNode,

                          enabled: !isSaving,

                          keyboardType: TextInputType.emailAddress,

                          textInputAction: TextInputAction.next,

                          autocorrect: false,

                          enableSuggestions: false,

                          validator: _validateEmail,

                          style: RegistrationStyle.inputText,

                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'rider@example.com',
                            icon: Icons.mail_outline_rounded,
                          ),

                          onFieldSubmitted: (_) {
                            mobileFocusNode.requestFocus();
                          },
                        ),

                        const SizedBox(height: 18),

                        // ======================================
                        // MOBILE
                        // ======================================
                        _fieldLabel('Mobile Number'),

                        TextFormField(
                          controller: mobileController,

                          focusNode: mobileFocusNode,

                          enabled: !isSaving,

                          keyboardType: TextInputType.phone,

                          textInputAction: TextInputAction.next,

                          maxLength: 11,

                          inputFormatters: [
                            FilteringTextInputFormatter.digitsOnly,
                          ],

                          validator: _validateMobile,

                          style: RegistrationStyle.inputText,

                          decoration: RegistrationStyle.inputDecoration(
                            hint: '09XXXXXXXXX',
                            icon: Icons.phone_outlined,
                          ).copyWith(counterText: ''),

                          onFieldSubmitted: (_) {
                            licenseNumberFocusNode.requestFocus();
                          },
                        ),

                        const SizedBox(height: 18),

                        // ======================================
                        // DRIVER LICENSE NUMBER
                        // ======================================
                        _fieldLabel('Driver\'s License No.'),

                        TextFormField(
                          controller: licenseNumberController,

                          focusNode: licenseNumberFocusNode,

                          enabled: !isSaving,

                          keyboardType: TextInputType.text,

                          textCapitalization: TextCapitalization.characters,

                          textInputAction: TextInputAction.next,

                          maxLength: 13,

                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                              RegExp(r'[A-Za-z0-9-]'),
                            ),
                            LengthLimitingTextInputFormatter(13),
                          ],

                          validator: _validateLicenseNumber,

                          style: RegistrationStyle.inputText,

                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'A00-00-000000',
                            icon: Icons.badge_outlined,
                          ).copyWith(counterText: ''),

                          onChanged: (value) {
                            final String upper = value.toUpperCase();

                            if (upper != value) {
                              licenseNumberController.value =
                                  licenseNumberController.value.copyWith(
                                    text: upper,
                                    selection: TextSelection.collapsed(
                                      offset: upper.length,
                                    ),
                                  );
                            }
                          },

                          onFieldSubmitted: (_) {
                            FocusScope.of(context).unfocus();

                            _pickLicenseExpiry();
                          },
                        ),

                        const SizedBox(height: 18),

                        // ======================================
                        // LICENSE EXPIRY
                        // ======================================
                        _fieldLabel('License Expiry Date'),

                        TextFormField(
                          controller: licenseExpiryController,

                          readOnly: true,

                          showCursor: false,

                          enabled: !isSaving,

                          onTap: _pickLicenseExpiry,

                          validator: _validateLicenseExpiry,

                          style: RegistrationStyle.inputText,

                          decoration:
                              RegistrationStyle.inputDecoration(
                                hint: 'MM/DD/YYYY',
                                icon: Icons.event_available_outlined,
                              ).copyWith(
                                suffixIcon: IconButton(
                                  tooltip: 'Select expiry date',

                                  onPressed: isSaving
                                      ? null
                                      : _pickLicenseExpiry,

                                  icon: const Icon(
                                    Icons.calendar_month_outlined,
                                    color: RegistrationColors.textGrey,
                                  ),
                                ),
                              ),
                        ),

                        const SizedBox(height: 18),

                        // ======================================
                        // VEHICLE TYPE
                        // ======================================
                        _fieldLabel('Vehicle Type'),

                        DropdownButtonFormField<String>(
                          initialValue: selectedVehicleType,

                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'Select vehicle type',
                            icon: Icons.two_wheeler_outlined,
                          ),

                          items: vehicleTypes
                              .map(
                                (String type) => DropdownMenuItem<String>(
                                  value: type,
                                  child: Text(type),
                                ),
                              )
                              .toList(),

                          onChanged: isSaving
                              ? null
                              : (String? value) {
                                  setState(() {
                                    selectedVehicleType = value;

                                    _hasChanges = true;
                                  });
                                },

                          validator: (value) {
                            if (value == null || value.isEmpty) {
                              return 'Please select the vehicle type';
                            }

                            return null;
                          },
                        ),

                        const SizedBox(height: 18),

                        // ======================================
                        // PLATE NUMBER
                        // ======================================
                        _fieldLabel('Plate Number'),

                        TextFormField(
                          controller: plateNumberController,

                          focusNode: plateNumberFocusNode,

                          enabled: !isSaving,

                          keyboardType: TextInputType.text,

                          textCapitalization: TextCapitalization.characters,

                          textInputAction: TextInputAction.done,

                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                              RegExp(r'[A-Za-z0-9 -]'),
                            ),
                            LengthLimitingTextInputFormatter(10),
                          ],

                          validator: _validatePlateNumber,

                          style: RegistrationStyle.inputText,

                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'Enter plate number',
                            icon: Icons.directions_car_outlined,
                          ),

                          onChanged: (value) {
                            final String upper = value.toUpperCase();

                            if (upper != value) {
                              plateNumberController.value =
                                  plateNumberController.value.copyWith(
                                    text: upper,
                                    selection: TextSelection.collapsed(
                                      offset: upper.length,
                                    ),
                                  );
                            }
                          },
                        ),

                        const SizedBox(height: 24),

                        // ======================================
                        // DOCUMENTS
                        // ======================================
                        const Text(
                          'Required Documents',
                          style: RegistrationStyle.sectionTitle,
                        ),

                        const SizedBox(height: 6),

                        const Text(
                          'Accepted formats: JPG, PNG, PDF. Maximum 5 MB per file.',
                          style: RegistrationStyle.termsText,
                        ),

                        const SizedBox(height: 14),

                        _buildUploadCard(
                          title: 'Driver\'s License',

                          subtitle:
                              driverLicenseFileName ??
                              'Upload license document',

                          icon: Icons.badge_outlined,

                          hasFile: driverLicenseFilePath != null,

                          error: driverLicenseUploadError,

                          onTap: () {
                            _showUploadOptions(isLicense: true);
                          },
                        ),

                        const SizedBox(height: 12),

                        _buildUploadCard(
                          title: 'Vehicle OR/CR',

                          subtitle:
                              vehicleOrCrFileName ?? 'Upload vehicle OR/CR',

                          icon: Icons.description_outlined,

                          hasFile: vehicleOrCrFilePath != null,

                          error: vehicleOrCrUploadError,

                          onTap: () {
                            _showUploadOptions(isLicense: false);
                          },
                        ),

                        const SizedBox(height: 18),

                        Container(
                          padding: const EdgeInsets.all(14),

                          decoration: BoxDecoration(
                            color: RegistrationColors.lightPurple,

                            borderRadius: BorderRadius.circular(14),
                          ),

                          child: const Row(
                            crossAxisAlignment: CrossAxisAlignment.start,

                            children: [
                              Icon(
                                Icons.mark_email_read_outlined,
                                color: RegistrationColors.primary,
                                size: 22,
                              ),

                              SizedBox(width: 10),

                              Expanded(
                                child: Text(
                                  'The rider will receive an activation email after the account is created. '
                                  'The rider will create their own password from that activation link.',
                                  style: RegistrationStyle.termsText,
                                ),
                              ),
                            ],
                          ),
                        ),

                        const SizedBox(height: 24),

                        // ======================================
                        // SAVE RIDER
                        // ======================================
                        SizedBox(
                          width: double.infinity,

                          height: 52,

                          child: ElevatedButton(
                            onPressed: isSaving ? null : _saveRider,

                            style: RegistrationStyle.primaryButton,

                            child: AnimatedSwitcher(
                              duration: const Duration(milliseconds: 200),

                              child: isSaving
                                  ? const SizedBox(
                                      key: ValueKey('saving-rider'),
                                      height: 22,
                                      width: 22,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2.2,
                                        color: Colors.white,
                                      ),
                                    )
                                  : const Text(
                                      'Save Rider',
                                      key: ValueKey('save-rider'),
                                      style: RegistrationStyle.buttonText,
                                    ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // SMALL WIDGETS
  // ============================================================

  Widget _fieldLabel(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(text, style: RegistrationStyle.sectionTitle),
    );
  }

  Widget _buildUploadCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required bool hasFile,
    required String? error,
    required VoidCallback onTap,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        Material(
          color: Colors.transparent,

          child: InkWell(
            onTap: isSaving ? null : onTap,

            borderRadius: BorderRadius.circular(14),

            child: Container(
              width: double.infinity,

              padding: const EdgeInsets.all(15),

              decoration: BoxDecoration(
                color: RegistrationColors.inputBackground,

                borderRadius: BorderRadius.circular(14),

                border: Border.all(
                  color: error != null
                      ? RegistrationColors.error
                      : hasFile
                      ? RegistrationColors.success
                      : RegistrationColors.border,
                  width: 1.2,
                ),
              ),

              child: Row(
                children: [
                  Container(
                    height: 42,
                    width: 42,

                    decoration: BoxDecoration(
                      color: RegistrationColors.lightPurple,

                      borderRadius: BorderRadius.circular(12),
                    ),

                    child: Icon(
                      hasFile ? Icons.check_circle_outline_rounded : icon,

                      color: hasFile
                          ? RegistrationColors.success
                          : RegistrationColors.primary,
                    ),
                  ),

                  const SizedBox(width: 12),

                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,

                      children: [
                        Text(title, style: RegistrationStyle.sectionTitle),

                        const SizedBox(height: 3),

                        Text(
                          subtitle,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: RegistrationStyle.termsText,
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(width: 8),

                  Icon(
                    hasFile ? Icons.edit_outlined : Icons.upload_file_outlined,
                    color: RegistrationColors.primary,
                  ),
                ],
              ),
            ),
          ),
        ),

        if (error != null)
          Padding(
            padding: const EdgeInsets.only(left: 12, top: 6),

            child: Text(error, style: RegistrationStyle.errorText),
          ),
      ],
    );
  }

  // ============================================================
  // DISPOSE
  // ============================================================

  @override
  void dispose() {
    firstNameController.dispose();

    lastNameController.dispose();

    birthDateController.dispose();

    emailController.dispose();

    mobileController.dispose();

    licenseNumberController.dispose();

    licenseExpiryController.dispose();

    plateNumberController.dispose();

    firstNameFocusNode.dispose();

    lastNameFocusNode.dispose();

    emailFocusNode.dispose();

    mobileFocusNode.dispose();

    licenseNumberFocusNode.dispose();

    plateNumberFocusNode.dispose();

    super.dispose();
  }
}
