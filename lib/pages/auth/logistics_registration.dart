import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../../sevices/api_services.dart';
import '../../styles/registration_style.dart';

enum LogisticsDocumentType {
  secCertificate,
  bir2303,
  mayorsPermit,
  representativeId,
}

class LogisticsRegistrationPage extends StatefulWidget {
  const LogisticsRegistrationPage({super.key});

  @override
  State<LogisticsRegistrationPage> createState() =>
      _LogisticsRegistrationPageState();
}

class _LogisticsRegistrationPageState extends State<LogisticsRegistrationPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  // ============================================================
  // CONTROLLERS
  // ============================================================

  final TextEditingController companyNameController = TextEditingController();

  final TextEditingController secRegistrationController =
      TextEditingController();

  final TextEditingController tinController = TextEditingController();

  final TextEditingController streetAddressController = TextEditingController();

  final TextEditingController postalCodeController = TextEditingController();

  final TextEditingController contactFirstNameController =
      TextEditingController();

  final TextEditingController contactLastNameController =
      TextEditingController();

  final TextEditingController positionController = TextEditingController();

  final TextEditingController emailController = TextEditingController();

  final TextEditingController mobileController = TextEditingController();

  final TextEditingController passwordController = TextEditingController();

  final TextEditingController confirmPasswordController =
      TextEditingController();

  // ============================================================
  // FOCUS NODES
  // ============================================================

  final FocusNode companyNameFocusNode = FocusNode();
  final FocusNode secRegistrationFocusNode = FocusNode();
  final FocusNode tinFocusNode = FocusNode();
  final FocusNode streetAddressFocusNode = FocusNode();
  final FocusNode postalCodeFocusNode = FocusNode();
  final FocusNode contactFirstNameFocusNode = FocusNode();
  final FocusNode contactLastNameFocusNode = FocusNode();
  final FocusNode positionFocusNode = FocusNode();
  final FocusNode emailFocusNode = FocusNode();
  final FocusNode mobileFocusNode = FocusNode();
  final FocusNode passwordFocusNode = FocusNode();
  final FocusNode confirmPasswordFocusNode = FocusNode();

  // ============================================================
  // STATE
  // ============================================================

  final ImagePicker _imagePicker = ImagePicker();

  bool isSubmitting = false;
  bool _hasChanges = false;

  bool obscurePassword = true;
  bool obscureConfirmPassword = true;

  bool acceptedTerms = false;

  String? selectedRegion;
  String? selectedProvince;
  String? selectedCity;
  String? selectedBarangay;

  final Set<String> selectedServiceAreas = <String>{};

  String? serviceAreaError;

  // ============================================================
  // DOCUMENTS
  // ============================================================

  String? secCertificatePath;
  String? secCertificateName;
  String? secCertificateError;

  String? bir2303Path;
  String? bir2303Name;
  String? bir2303Error;

  String? mayorsPermitPath;
  String? mayorsPermitName;
  String? mayorsPermitError;

  String? representativeIdPath;
  String? representativeIdName;
  String? representativeIdError;

  // ============================================================
  // LOCATION OPTIONS
  // ============================================================

  final List<String> regions = const [
    'National Capital Region (NCR)',
    'Region IV-A (CALABARZON)',
    'Region XI (Davao Region)',
  ];

  final Map<String, List<String>> provincesByRegion = const {
    'National Capital Region (NCR)': ['Metro Manila'],
    'Region IV-A (CALABARZON)': [
      'Batangas',
      'Cavite',
      'Laguna',
      'Quezon',
      'Rizal',
    ],
    'Region XI (Davao Region)': ['Davao del Sur'],
  };

  final Map<String, List<String>> citiesByProvince = const {
    'Metro Manila': ['Pasig City'],
    'Batangas': ['Batangas City'],
    'Cavite': ['Dasmariñas City'],
    'Laguna': ['Santa Rosa City'],
    'Quezon': ['Lucena City'],
    'Rizal': ['Antipolo City'],
    'Davao del Sur': ['Davao City'],
  };

  final Map<String, List<String>> barangaysByCity = const {
    'Pasig City': ['Kapitolyo'],
    'Batangas City': ['Poblacion'],
    'Dasmariñas City': ['Poblacion'],
    'Santa Rosa City': ['Balibago'],
    'Lucena City': ['Barangay 1'],
    'Antipolo City': ['San Roque'],
    'Davao City': ['Buhangin'],
  };

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
  // VALIDATORS
  // ============================================================

  String? _validateCompanyName(String? value) {
    final input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the company name';
    }

    if (input.length < 2) {
      return 'Company name is too short';
    }

    if (input.length > 100) {
      return 'Company name must not exceed 100 characters';
    }

    return null;
  }

  String? _validateSecRegistration(String? value) {
    final input = value?.trim().toUpperCase() ?? '';

    if (input.isEmpty) {
      return 'Please enter the SEC Registration No.';
    }

    if (!RegExp(r'^[A-Z]{2}\d{9}$').hasMatch(input)) {
      return 'Use format: 2 letters followed by 9 digits';
    }

    return null;
  }

  String? _validateTin(String? value) {
    final input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the TIN';
    }

    if (!RegExp(r'^\d{3}-\d{3}-\d{3}-\d{3}$').hasMatch(input)) {
      return 'Use TIN format 000-000-000-000';
    }

    return null;
  }

  String? _validateStreetAddress(String? value) {
    final input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the street address';
    }

    if (input.length < 5) {
      return 'Please enter a complete street address';
    }

    if (input.length > 255) {
      return 'Street address is too long';
    }

    return null;
  }

  String? _validatePostalCode(String? value) {
    final input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the postal code';
    }

    if (!RegExp(r'^\d{4}$').hasMatch(input)) {
      return 'Postal code must contain exactly 4 digits';
    }

    return null;
  }

  String? _validateFirstName(String? value) {
    final input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the contact person\'s first name';
    }

    if (input.length < 2) {
      return 'First name must contain at least 2 characters';
    }

    if (input.length > 50) {
      return 'First name must not exceed 50 characters';
    }

    if (!RegExp(r"^[A-Za-zÑñ .'-]+$").hasMatch(input)) {
      return 'Only letters are allowed';
    }

    return null;
  }

  String? _validateLastName(String? value) {
    final input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the contact person\'s last name';
    }

    if (input.length < 2) {
      return 'Last name must contain at least 2 characters';
    }

    if (input.length > 50) {
      return 'Last name must not exceed 50 characters';
    }

    if (!RegExp(r"^[A-Za-zÑñ .'-]+$").hasMatch(input)) {
      return 'Only letters are allowed';
    }

    return null;
  }

  String? _validatePosition(String? value) {
    final input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the contact person\'s position';
    }

    if (input.length < 2) {
      return 'Please enter a valid position';
    }

    if (input.length > 80) {
      return 'Position must not exceed 80 characters';
    }

    return null;
  }

  String? _validateEmail(String? value) {
    final input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the email address';
    }

    if (input.contains(' ')) {
      return 'Email must not contain spaces';
    }

    final emailRegex = RegExp(
      r'^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$',
    );

    if (!emailRegex.hasMatch(input)) {
      return 'Please enter a valid email address';
    }

    return null;
  }

  String? _validateMobile(String? value) {
    final input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter the mobile number';
    }

    final localFormat = RegExp(r'^09\d{9}$');
    final internationalFormat = RegExp(r'^\+639\d{9}$');

    if (!localFormat.hasMatch(input) && !internationalFormat.hasMatch(input)) {
      return 'Use 09XXXXXXXXX or +639XXXXXXXXX';
    }

    return null;
  }

  String? _validatePassword(String? value) {
    final password = value ?? '';

    if (password.isEmpty) {
      return 'Please enter a password';
    }

    if (password.length < 8) {
      return 'Password must contain at least 8 characters';
    }

    if (password.length > 128) {
      return 'Password must not exceed 128 characters';
    }

    if (!RegExp(r'[A-Z]').hasMatch(password)) {
      return 'Password must include an uppercase letter';
    }

    if (!RegExp(r'[a-z]').hasMatch(password)) {
      return 'Password must include a lowercase letter';
    }

    if (!RegExp(r'\d').hasMatch(password)) {
      return 'Password must include a number';
    }

    if (!RegExp(r'[!@#$%^&*(),.?":{}|<>_\-+=/\\]').hasMatch(password)) {
      return 'Password must include a special character';
    }

    return null;
  }

  String? _validateConfirmPassword(String? value) {
    final input = value ?? '';

    if (input.isEmpty) {
      return 'Please confirm your password';
    }

    if (input != passwordController.text) {
      return 'Passwords do not match';
    }

    return null;
  }

  // ============================================================
  // LOCATION
  // ============================================================

  void _changeRegion(String? value) {
    setState(() {
      selectedRegion = value;
      selectedProvince = null;
      selectedCity = null;
      selectedBarangay = null;
      _hasChanges = true;
    });
  }

  void _changeProvince(String? value) {
    setState(() {
      selectedProvince = value;
      selectedCity = null;
      selectedBarangay = null;
      _hasChanges = true;
    });
  }

  void _changeCity(String? value) {
    setState(() {
      selectedCity = value;
      selectedBarangay = null;
      _hasChanges = true;
    });
  }

  void _changeBarangay(String? value) {
    setState(() {
      selectedBarangay = value;
      _hasChanges = true;
    });
  }

  // ============================================================
  // SERVICE AREA
  // ============================================================

  Future<void> _showServiceAreaPicker() async {
    if (isSubmitting) return;

    final temporarySelection = Set<String>.from(selectedServiceAreas);

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Text(
                      'Select Service Areas',
                      style: RegistrationStyle.sectionTitle,
                    ),

                    const SizedBox(height: 8),

                    const Text(
                      'Select at least one region your logistics company serves.',
                      style: RegistrationStyle.termsText,
                    ),

                    const SizedBox(height: 12),

                    ...regions.map((region) {
                      final selected = temporarySelection.contains(region);

                      return CheckboxListTile(
                        value: selected,
                        contentPadding: EdgeInsets.zero,
                        activeColor: RegistrationColors.primary,
                        title: Text(region),
                        onChanged: (value) {
                          setSheetState(() {
                            if (value == true) {
                              temporarySelection.add(region);
                            } else {
                              temporarySelection.remove(region);
                            }
                          });
                        },
                      );
                    }),

                    const SizedBox(height: 12),

                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        style: RegistrationStyle.primaryButton,
                        onPressed: () {
                          setState(() {
                            selectedServiceAreas
                              ..clear()
                              ..addAll(temporarySelection);

                            serviceAreaError = selectedServiceAreas.isEmpty
                                ? 'Select at least one service area'
                                : null;

                            _hasChanges = true;
                          });

                          Navigator.pop(sheetContext);
                        },
                        child: const Text(
                          'Done',
                          style: RegistrationStyle.buttonText,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  // ============================================================
  // DOCUMENT HELPERS
  // ============================================================

  String _documentTitle(LogisticsDocumentType type) {
    switch (type) {
      case LogisticsDocumentType.secCertificate:
        return 'SEC Certificate';

      case LogisticsDocumentType.bir2303:
        return 'BIR Form 2303';

      case LogisticsDocumentType.mayorsPermit:
        return 'Mayor\'s / Business Permit';

      case LogisticsDocumentType.representativeId:
        return 'Valid ID of Authorized Representative';
    }
  }

  String? _documentName(LogisticsDocumentType type) {
    switch (type) {
      case LogisticsDocumentType.secCertificate:
        return secCertificateName;

      case LogisticsDocumentType.bir2303:
        return bir2303Name;

      case LogisticsDocumentType.mayorsPermit:
        return mayorsPermitName;

      case LogisticsDocumentType.representativeId:
        return representativeIdName;
    }
  }

  String? _documentPath(LogisticsDocumentType type) {
    switch (type) {
      case LogisticsDocumentType.secCertificate:
        return secCertificatePath;

      case LogisticsDocumentType.bir2303:
        return bir2303Path;

      case LogisticsDocumentType.mayorsPermit:
        return mayorsPermitPath;

      case LogisticsDocumentType.representativeId:
        return representativeIdPath;
    }
  }

  String? _documentError(LogisticsDocumentType type) {
    switch (type) {
      case LogisticsDocumentType.secCertificate:
        return secCertificateError;

      case LogisticsDocumentType.bir2303:
        return bir2303Error;

      case LogisticsDocumentType.mayorsPermit:
        return mayorsPermitError;

      case LogisticsDocumentType.representativeId:
        return representativeIdError;
    }
  }

  void _setDocumentError(LogisticsDocumentType type, String? error) {
    switch (type) {
      case LogisticsDocumentType.secCertificate:
        secCertificateError = error;
        break;

      case LogisticsDocumentType.bir2303:
        bir2303Error = error;
        break;

      case LogisticsDocumentType.mayorsPermit:
        mayorsPermitError = error;
        break;

      case LogisticsDocumentType.representativeId:
        representativeIdError = error;
        break;
    }
  }

  void _saveDocument({
    required LogisticsDocumentType type,
    required String path,
    required String name,
  }) {
    switch (type) {
      case LogisticsDocumentType.secCertificate:
        secCertificatePath = path;
        secCertificateName = name;
        secCertificateError = null;
        break;

      case LogisticsDocumentType.bir2303:
        bir2303Path = path;
        bir2303Name = name;
        bir2303Error = null;
        break;

      case LogisticsDocumentType.mayorsPermit:
        mayorsPermitPath = path;
        mayorsPermitName = name;
        mayorsPermitError = null;
        break;

      case LogisticsDocumentType.representativeId:
        representativeIdPath = path;
        representativeIdName = name;
        representativeIdError = null;
        break;
    }
  }

  // ============================================================
  // UPLOAD OPTIONS
  // ============================================================

  Future<void> _showUploadOptions(LogisticsDocumentType type) async {
    if (isSubmitting) return;

    FocusScope.of(context).unfocus();

    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Upload ${_documentTitle(type)}',
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

                    _pickImage(type: type, source: ImageSource.camera);
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

                    _pickImage(type: type, source: ImageSource.gallery);
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
                    _pickFile(type);
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
    required LogisticsDocumentType type,
    required ImageSource source,
  }) async {
    try {
      final XFile? selected = await _imagePicker.pickImage(
        source: source,
        imageQuality: 85,
      );

      if (selected == null) return;

      await _validateAndSaveDocument(
        type: type,
        path: selected.path,
        name: selected.name,
      );
    } catch (_) {
      if (!mounted) return;

      _showMessage(
        'Unable to access the camera or gallery. '
        'Please check the app permission and try again.',
      );
    }
  }

  Future<void> _pickFile(LogisticsDocumentType type) async {
    try {
      final PlatformFile? file = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: const ['jpg', 'jpeg', 'png', 'pdf'],
      );

      if (file == null) return;

      if (file.path == null || file.path!.trim().isEmpty) {
        _showMessage('Unable to read the selected file.');
        return;
      }

      await _validateAndSaveDocument(
        type: type,
        path: file.path!,
        name: file.name,
      );
    } catch (_) {
      if (!mounted) return;

      _showMessage('Unable to open your files. Please try again.');
    }
  }

  Future<void> _validateAndSaveDocument({
    required LogisticsDocumentType type,
    required String path,
    required String name,
  }) async {
    final extension = name.split('.').last.toLowerCase();

    const acceptedExtensions = {'jpg', 'jpeg', 'png', 'pdf'};

    if (!acceptedExtensions.contains(extension)) {
      setState(() {
        _setDocumentError(type, 'Only JPG, PNG, and PDF files are allowed.');
      });

      _showMessage('Only JPG, PNG, and PDF files are allowed.');

      return;
    }

    try {
      final file = File(path);

      final size = await file.length();

      const maxSize = 5 * 1024 * 1024;

      if (size > maxSize) {
        setState(() {
          _setDocumentError(type, 'File must not be larger than 5 MB.');
        });

        _showMessage('File must not be larger than 5 MB.');

        return;
      }

      if (!mounted) return;

      setState(() {
        _saveDocument(type: type, path: path, name: name);

        _hasChanges = true;
      });
    } catch (_) {
      if (!mounted) return;

      setState(() {
        _setDocumentError(type, 'Unable to read the selected file.');
      });

      _showMessage('Unable to read the selected file.');
    }
  }

  // ============================================================
  // API ERROR MESSAGE
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

    final message = result['message']?.toString().trim() ?? '';

    if (message.isNotEmpty) {
      return message;
    }

    return 'Unable to create the Logistics account. '
        'Please check your information and try again.';
  }

  // ============================================================
  // SUBMIT REGISTRATION
  // ============================================================

  Future<void> _submitRegistration() async {
    if (isSubmitting) return;

    FocusScope.of(context).unfocus();

    final bool valid = _formKey.currentState?.validate() ?? false;

    setState(() {
      serviceAreaError = selectedServiceAreas.isEmpty
          ? 'Select at least one service area'
          : null;

      secCertificateError = secCertificatePath == null
          ? 'SEC Certificate is required'
          : null;

      bir2303Error = bir2303Path == null ? 'BIR Form 2303 is required' : null;

      mayorsPermitError = mayorsPermitPath == null
          ? 'Mayor\'s / Business Permit is required'
          : null;

      representativeIdError = representativeIdPath == null
          ? 'Authorized Representative ID is required'
          : null;
    });

    if (!valid) {
      _showMessage('Please check the highlighted fields.');
      return;
    }

    if (selectedRegion == null ||
        selectedProvince == null ||
        selectedCity == null ||
        selectedBarangay == null) {
      _showMessage('Please complete the company address.');
      return;
    }

    if (selectedServiceAreas.isEmpty) {
      _showMessage('Please select at least one service area.');
      return;
    }

    if (secCertificatePath == null ||
        bir2303Path == null ||
        mayorsPermitPath == null ||
        representativeIdPath == null) {
      _showMessage('Please upload all required documents.');
      return;
    }

    if (!acceptedTerms) {
      _showMessage('Please accept the Terms and Privacy Policy.');
      return;
    }

    setState(() {
      isSubmitting = true;
    });

    try {
      final Map<String, dynamic> result = await ApiService.registerLogistics(
        companyName: companyNameController.text.trim(),
        secRegistrationNumber: secRegistrationController.text
            .trim()
            .toUpperCase(),
        tin: tinController.text.trim(),

        region: selectedRegion!,
        province: selectedProvince!,
        city: selectedCity!,
        barangay: selectedBarangay!,

        streetAddress: streetAddressController.text.trim(),
        postalCode: postalCodeController.text.trim(),

        contactFirstName: contactFirstNameController.text.trim(),
        contactLastName: contactLastNameController.text.trim(),
        position: positionController.text.trim(),

        email: emailController.text.trim().toLowerCase(),
        mobileNumber: mobileController.text.trim(),

        serviceAreas: selectedServiceAreas.toList(),

        password: passwordController.text,
        passwordConfirmation: confirmPasswordController.text,

        secCertificateFilePath: secCertificatePath!,
        bir2303FilePath: bir2303Path!,
        mayorsPermitFilePath: mayorsPermitPath!,
        representativeIdFilePath: representativeIdPath!,
      );

      if (!mounted) return;

      setState(() {
        isSubmitting = false;
      });

      if (result['success'] != true) {
        _showMessage(_extractApiErrorMessage(result));
        return;
      }

      final String serverMessage = result['message']?.toString().trim() ?? '';

      final String successMessage = serverMessage.isNotEmpty
          ? serverMessage
          : 'Logistics registration submitted successfully. '
                'Please verify your email. Your account will remain '
                'pending until approved by BiliHub.';

      setState(() {
        _hasChanges = false;
      });

      _showMessage(successMessage, success: true);

      await Future.delayed(const Duration(milliseconds: 900));

      if (!mounted) return;

      Navigator.pop(context, true);
    } catch (_) {
      if (!mounted) return;

      setState(() {
        isSubmitting = false;
      });

      _showMessage(
        'Unable to connect to the server. '
        'Please check your internet connection and try again.',
      );
    }
  }

  // ============================================================
  // BACK
  // ============================================================

  Future<bool> _confirmLeave() async {
    if (!_hasChanges || isSubmitting) {
      return true;
    }

    final bool? result = await showDialog<bool>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: const Text('Discard changes?'),
          content: const Text(
            'You have unsaved logistics registration information. '
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
    final canLeave = await _confirmLeave();

    if (!canLeave || !mounted) return;

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
  // FIELD HELPERS
  // ============================================================

  Widget _fieldLabel(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(text, style: RegistrationStyle.sectionTitle),
    );
  }

  Widget _buildUploadCard({
    required LogisticsDocumentType type,
    required IconData icon,
  }) {
    final title = _documentTitle(type);
    final name = _documentName(type);
    final path = _documentPath(type);
    final error = _documentError(type);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: isSubmitting
                ? null
                : () {
                    _showUploadOptions(type);
                  },
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
                      : path != null
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
                      path != null ? Icons.check_circle_outline_rounded : icon,
                      color: path != null
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
                          name ?? 'Upload document',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: RegistrationStyle.termsText,
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(width: 8),

                  Icon(
                    path != null
                        ? Icons.edit_outlined
                        : Icons.upload_file_outlined,
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
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final Size screenSize = MediaQuery.of(context).size;

    final double horizontalPadding = screenSize.width < 360 ? 16 : 22;

    final List<String> provinces = selectedRegion == null
        ? const []
        : provincesByRegion[selectedRegion!] ?? const [];

    final List<String> cities = selectedProvince == null
        ? const []
        : citiesByProvince[selectedProvince!] ?? const [];

    final List<String> barangays = selectedCity == null
        ? const []
        : barangaysByCity[selectedCity!] ?? const [];

    return PopScope(
      canPop: !_hasChanges && !isSubmitting,
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
            onPressed: isSubmitting ? null : _handleBack,
            icon: const Icon(
              Icons.arrow_back_ios_new_rounded,
              color: RegistrationColors.textDark,
              size: 20,
            ),
          ),
          title: const Text(
            'Logistics Registration',
            style: RegistrationStyle.appBarTitle,
          ),
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
                  constraints: const BoxConstraints(maxWidth: 480),
                  padding: const EdgeInsets.all(24),
                  decoration: RegistrationStyle.card,
                  child: Form(
                    key: _formKey,
                    autovalidateMode: AutovalidateMode.onUserInteraction,
                    onChanged: _markChanged,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // ==========================
                        // HEADER
                        // ==========================

                        Center(
                          child: Container(
                            height: 64,
                            width: 64,
                            decoration: RegistrationStyle.logoContainer,
                            child: const Icon(
                              Icons.local_shipping_outlined,
                              color: RegistrationColors.primary,
                              size: 34,
                            ),
                          ),
                        ),

                        const SizedBox(height: 14),

                        const Center(
                          child: Text(
                            'Create Logistics Account',
                            textAlign: TextAlign.center,
                            style: RegistrationStyle.title,
                          ),
                        ),

                        const SizedBox(height: 8),

                        const Text(
                          'Register your logistics company to manage '
                          'parcel movement, hubs, riders and delivery '
                          'operations on BiliHub.',
                          textAlign: TextAlign.center,
                          style: RegistrationStyle.subtitle,
                        ),

                        const SizedBox(height: 28),

                        // ==========================
                        // COMPANY
                        // ==========================
                        const Text(
                          'Company Information',
                          style: RegistrationStyle.sectionTitle,
                        ),

                        const SizedBox(height: 16),

                        _fieldLabel('Company Name'),

                        TextFormField(
                          controller: companyNameController,
                          focusNode: companyNameFocusNode,
                          enabled: !isSubmitting,
                          textCapitalization: TextCapitalization.words,
                          textInputAction: TextInputAction.next,
                          validator: _validateCompanyName,
                          style: RegistrationStyle.inputText,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'Enter company name',
                            icon: Icons.business_outlined,
                          ),
                          onFieldSubmitted: (_) {
                            secRegistrationFocusNode.requestFocus();
                          },
                        ),

                        const SizedBox(height: 18),

                        _fieldLabel('SEC Registration No.'),

                        TextFormField(
                          controller: secRegistrationController,
                          focusNode: secRegistrationFocusNode,
                          enabled: !isSubmitting,
                          textCapitalization: TextCapitalization.characters,
                          textInputAction: TextInputAction.next,
                          maxLength: 11,
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                              RegExp(r'[A-Za-z0-9]'),
                            ),
                            LengthLimitingTextInputFormatter(11),
                          ],
                          validator: _validateSecRegistration,
                          style: RegistrationStyle.inputText,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'CS202012345',
                            icon: Icons.verified_outlined,
                          ).copyWith(counterText: ''),
                          onChanged: (value) {
                            final upper = value.toUpperCase();

                            if (upper != value) {
                              secRegistrationController.value =
                                  secRegistrationController.value.copyWith(
                                    text: upper,
                                    selection: TextSelection.collapsed(
                                      offset: upper.length,
                                    ),
                                  );
                            }
                          },
                          onFieldSubmitted: (_) {
                            tinFocusNode.requestFocus();
                          },
                        ),

                        const SizedBox(height: 18),

                        _fieldLabel('TIN'),

                        TextFormField(
                          controller: tinController,
                          focusNode: tinFocusNode,
                          enabled: !isSubmitting,
                          keyboardType: TextInputType.number,
                          textInputAction: TextInputAction.next,
                          maxLength: 15,
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                              RegExp(r'[0-9-]'),
                            ),
                            LengthLimitingTextInputFormatter(15),
                          ],
                          validator: _validateTin,
                          style: RegistrationStyle.inputText,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: '000-000-000-000',
                            icon: Icons.receipt_long_outlined,
                          ).copyWith(counterText: ''),
                        ),

                        const SizedBox(height: 28),

                        // ==========================
                        // ADDRESS
                        // ==========================
                        const Text(
                          'Company Address',
                          style: RegistrationStyle.sectionTitle,
                        ),

                        const SizedBox(height: 16),

                        _fieldLabel('Region'),

                        DropdownButtonFormField<String>(
                          initialValue: selectedRegion,
                          isExpanded: true,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'Select region',
                            icon: Icons.map_outlined,
                          ),
                          items: regions
                              .map(
                                (value) => DropdownMenuItem<String>(
                                  value: value,
                                  child: Text(
                                    value,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              )
                              .toList(),
                          validator: (value) {
                            if (value == null) {
                              return 'Please select a region';
                            }

                            return null;
                          },
                          onChanged: isSubmitting ? null : _changeRegion,
                        ),

                        const SizedBox(height: 18),

                        _fieldLabel('Province'),

                        DropdownButtonFormField<String>(
                          key: ValueKey('province-$selectedRegion'),
                          initialValue: selectedProvince,
                          isExpanded: true,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'Select province',
                            icon: Icons.location_city_outlined,
                          ),
                          items: provinces
                              .map(
                                (value) => DropdownMenuItem<String>(
                                  value: value,
                                  child: Text(value),
                                ),
                              )
                              .toList(),
                          validator: (value) {
                            if (value == null) {
                              return 'Please select a province';
                            }

                            return null;
                          },
                          onChanged: isSubmitting || selectedRegion == null
                              ? null
                              : _changeProvince,
                        ),

                        const SizedBox(height: 18),

                        _fieldLabel('City / Municipality'),

                        DropdownButtonFormField<String>(
                          key: ValueKey('city-$selectedProvince'),
                          initialValue: selectedCity,
                          isExpanded: true,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'Select city / municipality',
                            icon: Icons.apartment_outlined,
                          ),
                          items: cities
                              .map(
                                (value) => DropdownMenuItem<String>(
                                  value: value,
                                  child: Text(value),
                                ),
                              )
                              .toList(),
                          validator: (value) {
                            if (value == null) {
                              return 'Please select a city / municipality';
                            }

                            return null;
                          },
                          onChanged: isSubmitting || selectedProvince == null
                              ? null
                              : _changeCity,
                        ),

                        const SizedBox(height: 18),

                        _fieldLabel('Barangay'),

                        DropdownButtonFormField<String>(
                          key: ValueKey('barangay-$selectedCity'),
                          initialValue: selectedBarangay,
                          isExpanded: true,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'Select barangay',
                            icon: Icons.place_outlined,
                          ),
                          items: barangays
                              .map(
                                (value) => DropdownMenuItem<String>(
                                  value: value,
                                  child: Text(value),
                                ),
                              )
                              .toList(),
                          validator: (value) {
                            if (value == null) {
                              return 'Please select a barangay';
                            }

                            return null;
                          },
                          onChanged: isSubmitting || selectedCity == null
                              ? null
                              : _changeBarangay,
                        ),

                        const SizedBox(height: 18),

                        _fieldLabel('Street Address'),

                        TextFormField(
                          controller: streetAddressController,
                          focusNode: streetAddressFocusNode,
                          enabled: !isSubmitting,
                          textCapitalization: TextCapitalization.sentences,
                          textInputAction: TextInputAction.next,
                          maxLines: 2,
                          validator: _validateStreetAddress,
                          style: RegistrationStyle.inputText,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'House, unit, lot number and street',
                            icon: Icons.home_work_outlined,
                          ),
                          onFieldSubmitted: (_) {
                            postalCodeFocusNode.requestFocus();
                          },
                        ),

                        const SizedBox(height: 18),

                        _fieldLabel('Postal Code'),

                        TextFormField(
                          controller: postalCodeController,
                          focusNode: postalCodeFocusNode,
                          enabled: !isSubmitting,
                          keyboardType: TextInputType.number,
                          textInputAction: TextInputAction.next,
                          maxLength: 4,
                          inputFormatters: [
                            FilteringTextInputFormatter.digitsOnly,
                          ],
                          validator: _validatePostalCode,
                          style: RegistrationStyle.inputText,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: '0000',
                            icon: Icons.pin_drop_outlined,
                          ).copyWith(counterText: ''),
                          onFieldSubmitted: (_) {
                            contactFirstNameFocusNode.requestFocus();
                          },
                        ),

                        const SizedBox(height: 28),

                        // ==========================
                        // CONTACT PERSON
                        // ==========================
                        const Text(
                          'Contact Person',
                          style: RegistrationStyle.sectionTitle,
                        ),

                        const SizedBox(height: 16),

                        _fieldLabel('First Name'),

                        TextFormField(
                          controller: contactFirstNameController,
                          focusNode: contactFirstNameFocusNode,
                          enabled: !isSubmitting,
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
                          style: RegistrationStyle.inputText,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'Contact first name',
                            icon: Icons.person_outline_rounded,
                          ).copyWith(counterText: ''),
                          onFieldSubmitted: (_) {
                            contactLastNameFocusNode.requestFocus();
                          },
                        ),

                        const SizedBox(height: 18),

                        _fieldLabel('Last Name'),

                        TextFormField(
                          controller: contactLastNameController,
                          focusNode: contactLastNameFocusNode,
                          enabled: !isSubmitting,
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
                            hint: 'Contact last name',
                            icon: Icons.person_outline_rounded,
                          ).copyWith(counterText: ''),
                          onFieldSubmitted: (_) {
                            positionFocusNode.requestFocus();
                          },
                        ),

                        const SizedBox(height: 18),

                        _fieldLabel('Position'),

                        TextFormField(
                          controller: positionController,
                          focusNode: positionFocusNode,
                          enabled: !isSubmitting,
                          textCapitalization: TextCapitalization.words,
                          textInputAction: TextInputAction.next,
                          validator: _validatePosition,
                          style: RegistrationStyle.inputText,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'e.g. Operations Manager',
                            icon: Icons.work_outline_rounded,
                          ),
                          onFieldSubmitted: (_) {
                            emailFocusNode.requestFocus();
                          },
                        ),

                        const SizedBox(height: 18),

                        _fieldLabel('Email Address'),

                        TextFormField(
                          controller: emailController,
                          focusNode: emailFocusNode,
                          enabled: !isSubmitting,
                          keyboardType: TextInputType.emailAddress,
                          textInputAction: TextInputAction.next,
                          autocorrect: false,
                          enableSuggestions: false,
                          validator: _validateEmail,
                          style: RegistrationStyle.inputText,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: 'operations@company.com',
                            icon: Icons.mail_outline_rounded,
                          ),
                          onFieldSubmitted: (_) {
                            mobileFocusNode.requestFocus();
                          },
                        ),

                        const SizedBox(height: 18),

                        _fieldLabel('Mobile Number'),

                        TextFormField(
                          controller: mobileController,
                          focusNode: mobileFocusNode,
                          enabled: !isSubmitting,
                          keyboardType: TextInputType.phone,
                          textInputAction: TextInputAction.next,
                          maxLength: 13,
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                              RegExp(r'[0-9+]'),
                            ),
                          ],
                          validator: _validateMobile,
                          style: RegistrationStyle.inputText,
                          decoration: RegistrationStyle.inputDecoration(
                            hint: '09XXXXXXXXX',
                            icon: Icons.phone_outlined,
                          ).copyWith(counterText: ''),
                        ),

                        const SizedBox(height: 28),

                        // ==========================
                        // SERVICE AREAS
                        // ==========================
                        const Text(
                          'Service Areas',
                          style: RegistrationStyle.sectionTitle,
                        ),

                        const SizedBox(height: 8),

                        InkWell(
                          onTap: isSubmitting ? null : _showServiceAreaPicker,
                          borderRadius: BorderRadius.circular(14),
                          child: Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(15),
                            decoration: BoxDecoration(
                              color: RegistrationColors.inputBackground,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                color: serviceAreaError != null
                                    ? RegistrationColors.error
                                    : RegistrationColors.border,
                              ),
                            ),
                            child: Row(
                              children: [
                                const Icon(
                                  Icons.public_outlined,
                                  color: RegistrationColors.primary,
                                ),

                                const SizedBox(width: 12),

                                Expanded(
                                  child: Text(
                                    selectedServiceAreas.isEmpty
                                        ? 'Select service areas'
                                        : selectedServiceAreas.join(', '),
                                    style: RegistrationStyle.inputText,
                                  ),
                                ),

                                const Icon(
                                  Icons.keyboard_arrow_down_rounded,
                                  color: RegistrationColors.textGrey,
                                ),
                              ],
                            ),
                          ),
                        ),

                        if (serviceAreaError != null)
                          Padding(
                            padding: const EdgeInsets.only(left: 12, top: 6),
                            child: Text(
                              serviceAreaError!,
                              style: RegistrationStyle.errorText,
                            ),
                          ),

                        const SizedBox(height: 28),

                        // ==========================
                        // DOCUMENTS
                        // ==========================
                        const Text(
                          'Required Documents',
                          style: RegistrationStyle.sectionTitle,
                        ),

                        const SizedBox(height: 6),

                        const Text(
                          'Accepted formats: JPG, PNG, PDF. '
                          'Maximum 5 MB per file.',
                          style: RegistrationStyle.termsText,
                        ),

                        const SizedBox(height: 14),

                        _buildUploadCard(
                          type: LogisticsDocumentType.secCertificate,
                          icon: Icons.corporate_fare_outlined,
                        ),

                        const SizedBox(height: 12),

                        _buildUploadCard(
                          type: LogisticsDocumentType.bir2303,
                          icon: Icons.receipt_long_outlined,
                        ),

                        const SizedBox(height: 12),

                        _buildUploadCard(
                          type: LogisticsDocumentType.mayorsPermit,
                          icon: Icons.description_outlined,
                        ),

                        const SizedBox(height: 12),

                        _buildUploadCard(
                          type: LogisticsDocumentType.representativeId,
                          icon: Icons.badge_outlined,
                        ),

                        const SizedBox(height: 28),

                        // ==========================
                        // SECURITY
                        // ==========================
                        const Text(
                          'Account Security',
                          style: RegistrationStyle.sectionTitle,
                        ),

                        const SizedBox(height: 16),

                        _fieldLabel('Password'),

                        TextFormField(
                          controller: passwordController,
                          focusNode: passwordFocusNode,
                          enabled: !isSubmitting,
                          obscureText: obscurePassword,
                          autocorrect: false,
                          enableSuggestions: false,
                          textInputAction: TextInputAction.next,
                          validator: _validatePassword,
                          style: RegistrationStyle.inputText,
                          decoration:
                              RegistrationStyle.inputDecoration(
                                hint: 'Create a strong password',
                                icon: Icons.lock_outline_rounded,
                              ).copyWith(
                                suffixIcon: IconButton(
                                  onPressed: isSubmitting
                                      ? null
                                      : () {
                                          setState(() {
                                            obscurePassword = !obscurePassword;
                                          });
                                        },
                                  icon: Icon(
                                    obscurePassword
                                        ? Icons.visibility_outlined
                                        : Icons.visibility_off_outlined,
                                    color: RegistrationColors.textGrey,
                                  ),
                                ),
                              ),
                          onFieldSubmitted: (_) {
                            confirmPasswordFocusNode.requestFocus();
                          },
                        ),

                        const SizedBox(height: 8),

                        const Text(
                          'Use at least 8 characters with uppercase, '
                          'lowercase, number and special character.',
                          style: RegistrationStyle.termsText,
                        ),

                        const SizedBox(height: 18),

                        _fieldLabel('Confirm Password'),

                        TextFormField(
                          controller: confirmPasswordController,
                          focusNode: confirmPasswordFocusNode,
                          enabled: !isSubmitting,
                          obscureText: obscureConfirmPassword,
                          autocorrect: false,
                          enableSuggestions: false,
                          textInputAction: TextInputAction.done,
                          validator: _validateConfirmPassword,
                          style: RegistrationStyle.inputText,
                          decoration:
                              RegistrationStyle.inputDecoration(
                                hint: 'Enter password again',
                                icon: Icons.lock_reset_outlined,
                              ).copyWith(
                                suffixIcon: IconButton(
                                  onPressed: isSubmitting
                                      ? null
                                      : () {
                                          setState(() {
                                            obscureConfirmPassword =
                                                !obscureConfirmPassword;
                                          });
                                        },
                                  icon: Icon(
                                    obscureConfirmPassword
                                        ? Icons.visibility_outlined
                                        : Icons.visibility_off_outlined,
                                    color: RegistrationColors.textGrey,
                                  ),
                                ),
                              ),
                          onFieldSubmitted: (_) {
                            _submitRegistration();
                          },
                        ),

                        const SizedBox(height: 18),

                        // ==========================
                        // TERMS
                        // ==========================
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Checkbox(
                              value: acceptedTerms,
                              activeColor: RegistrationColors.primary,
                              onChanged: isSubmitting
                                  ? null
                                  : (value) {
                                      setState(() {
                                        acceptedTerms = value ?? false;

                                        _hasChanges = true;
                                      });
                                    },
                            ),

                            const SizedBox(width: 4),

                            const Expanded(
                              child: Padding(
                                padding: EdgeInsets.only(top: 12),
                                child: Text(
                                  'I agree to the BiliHub Terms and '
                                  'Conditions and Privacy Policy.',
                                  style: RegistrationStyle.termsText,
                                ),
                              ),
                            ),
                          ],
                        ),

                        const SizedBox(height: 12),

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
                                Icons.verified_user_outlined,
                                color: RegistrationColors.primary,
                                size: 22,
                              ),
                              SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  'After registration, your email must be '
                                  'verified. The Logistics account will '
                                  'then be submitted for admin approval.',
                                  style: RegistrationStyle.termsText,
                                ),
                              ),
                            ],
                          ),
                        ),

                        const SizedBox(height: 24),

                        // ==========================
                        // SUBMIT
                        // ==========================
                        SizedBox(
                          width: double.infinity,
                          height: 52,
                          child: ElevatedButton(
                            onPressed: isSubmitting
                                ? null
                                : _submitRegistration,
                            style: RegistrationStyle.primaryButton,
                            child: AnimatedSwitcher(
                              duration: const Duration(milliseconds: 200),
                              child: isSubmitting
                                  ? const SizedBox(
                                      key: ValueKey('loading'),
                                      height: 22,
                                      width: 22,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2.2,
                                        color: Colors.white,
                                      ),
                                    )
                                  : const Text(
                                      'Create Logistics Account',
                                      key: ValueKey('create'),
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
  // DISPOSE
  // ============================================================

  @override
  void dispose() {
    companyNameController.dispose();
    secRegistrationController.dispose();
    tinController.dispose();
    streetAddressController.dispose();
    postalCodeController.dispose();
    contactFirstNameController.dispose();
    contactLastNameController.dispose();
    positionController.dispose();
    emailController.dispose();
    mobileController.dispose();
    passwordController.dispose();
    confirmPasswordController.dispose();

    companyNameFocusNode.dispose();
    secRegistrationFocusNode.dispose();
    tinFocusNode.dispose();
    streetAddressFocusNode.dispose();
    postalCodeFocusNode.dispose();
    contactFirstNameFocusNode.dispose();
    contactLastNameFocusNode.dispose();
    positionFocusNode.dispose();
    emailFocusNode.dispose();
    mobileFocusNode.dispose();
    passwordFocusNode.dispose();
    confirmPasswordFocusNode.dispose();

    super.dispose();
  }
}
