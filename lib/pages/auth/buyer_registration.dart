import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../sevices/api_services.dart';
import '../../styles/registration_style.dart';

class RegistrationPage extends StatefulWidget {
  const RegistrationPage({super.key});

  @override
  State<RegistrationPage> createState() => _RegistrationPageState();
}

class _RegistrationPageState extends State<RegistrationPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  // Controllers
  final TextEditingController firstNameController = TextEditingController();
  final TextEditingController lastNameController = TextEditingController();
  final TextEditingController emailController = TextEditingController();
  final TextEditingController phoneController = TextEditingController();
  final TextEditingController birthDateController = TextEditingController();
  final TextEditingController addressController = TextEditingController();
  final TextEditingController passwordController = TextEditingController();
  final TextEditingController confirmPasswordController =
      TextEditingController();

  // Focus nodes
  final FocusNode firstNameFocusNode = FocusNode();
  final FocusNode lastNameFocusNode = FocusNode();
  final FocusNode emailFocusNode = FocusNode();
  final FocusNode phoneFocusNode = FocusNode();
  final FocusNode addressFocusNode = FocusNode();
  final FocusNode passwordFocusNode = FocusNode();
  final FocusNode confirmPasswordFocusNode = FocusNode();

  // State
  bool hidePassword = true;
  bool hideConfirmPassword = true;
  bool agreeToTerms = false;
  bool showTermsError = false;
  bool isLoading = false;

  DateTime? selectedBirthDate;

  // ============================================================
  // REGISTER BUYER
  // ============================================================

  Future<void> registerBuyer() async {
    FocusScope.of(context).unfocus();

    if (isLoading) return;

    final bool formValid = _formKey.currentState?.validate() ?? false;

    setState(() {
      showTermsError = !agreeToTerms;
    });

    if (!formValid || !agreeToTerms) {
      return;
    }

    if (selectedBirthDate == null) {
      showMessage('Please select your date of birth.');
      return;
    }

    final String firstName = firstNameController.text.trim();
    final String lastName = lastNameController.text.trim();
    final String email = emailController.text.trim().toLowerCase();
    final String phone = phoneController.text.trim();
    final String address = addressController.text.trim();

    final String password = passwordController.text;
    final String confirmPassword = confirmPasswordController.text;

    final int age = _calculateAge(selectedBirthDate!);

    setState(() {
      isLoading = true;
    });

    try {
      final Map<String, dynamic> result = await ApiService.registerBuyer(
        firstName: firstName,
        lastName: lastName,
        email: email,
        phone: phone,
        birthday: _formatDateForApi(selectedBirthDate!),
        age: age,
        address: address,
        password: password,
        passwordConfirmation: confirmPassword,
      );

      if (!mounted) return;

      setState(() {
        isLoading = false;
      });

      if (result['success'] != true) {
        showMessage(_extractApiErrorMessage(result));
        return;
      }

      final String serverMessage = result['message']?.toString().trim() ?? '';

      final String successMessage = serverMessage.isNotEmpty
          ? serverMessage
          : 'Registration submitted successfully. '
                'Your account is pending approval.';

      showMessage(successMessage, isSuccess: true);

      await Future.delayed(const Duration(milliseconds: 900));

      if (!mounted) return;

      Navigator.pop(context);
    } catch (_) {
      if (!mounted) return;

      setState(() {
        isLoading = false;
      });

      showMessage(
        'Unable to connect to the server. '
        'Please check your internet connection and try again.',
      );
    }
  }

  // ============================================================
  // API ERROR
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

    return 'Unable to create your account. '
        'Please check your information and try again.';
  }

  // ============================================================
  // DATE OF BIRTH
  // ============================================================

  Future<void> _pickBirthDate() async {
    if (isLoading) return;

    FocusScope.of(context).unfocus();

    final DateTime today = DateTime.now();

    final DateTime eighteenYearsAgo = DateTime(
      today.year - 18,
      today.month,
      today.day,
    );

    final DateTime initialDate = selectedBirthDate ?? eighteenYearsAgo;

    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime(1900, 1, 1),
      lastDate: today,
      helpText: 'Select Date of Birth',
      cancelText: 'Cancel',
      confirmText: 'Select',
      fieldLabelText: 'Date of Birth',
      errorFormatText: 'Enter a valid date',
      errorInvalidText: 'Enter a valid date',
    );

    if (picked == null || !mounted) return;

    setState(() {
      selectedBirthDate = picked;
      birthDateController.text = _formatDateForDisplay(picked);
    });

    _formKey.currentState?.validate();

    Future.delayed(const Duration(milliseconds: 200), () {
      if (mounted) {
        addressFocusNode.requestFocus();
      }
    });
  }

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

    final bool hasNotHadBirthdayYet =
        today.month < birthDate.month ||
        (today.month == birthDate.month && today.day < birthDate.day);

    if (hasNotHadBirthdayYet) {
      age--;
    }

    return age;
  }

  // ============================================================
  // FIRST NAME
  // ============================================================

  String? validateFirstName(String? value) {
    final String input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter your first name';
    }

    if (input.length < 2) {
      return 'First name must contain at least 2 characters';
    }

    if (input.length > 50) {
      return 'First name must not exceed 50 characters';
    }

    final RegExp validName = RegExp(r"^[A-Za-zÑñ .'-]+$");

    if (!validName.hasMatch(input)) {
      return 'First name must contain letters only';
    }

    if (!RegExp(r'[A-Za-zÑñ]').hasMatch(input)) {
      return 'Please enter a valid first name';
    }

    return null;
  }

  // ============================================================
  // LAST NAME
  // ============================================================

  String? validateLastName(String? value) {
    final String input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter your last name';
    }

    if (input.length < 2) {
      return 'Last name must contain at least 2 characters';
    }

    if (input.length > 50) {
      return 'Last name must not exceed 50 characters';
    }

    final RegExp validName = RegExp(r"^[A-Za-zÑñ .'-]+$");

    if (!validName.hasMatch(input)) {
      return 'Last name must contain letters only';
    }

    if (!RegExp(r'[A-Za-zÑñ]').hasMatch(input)) {
      return 'Please enter a valid last name';
    }

    return null;
  }

  // ============================================================
  // EMAIL
  // ============================================================

  String? validateEmail(String? value) {
    final String input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter your email address';
    }

    if (input.length > 254) {
      return 'Email address is too long';
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

  // ============================================================
  // PHONE
  // ============================================================

  String? validatePhone(String? value) {
    final String input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter your phone number';
    }

    if (!RegExp(r'^09\d{9}$').hasMatch(input)) {
      return 'Use a valid Philippine mobile number: 09XXXXXXXXX';
    }

    return null;
  }

  // ============================================================
  // DATE OF BIRTH
  // ============================================================

  String? validateBirthDate(String? value) {
    if (selectedBirthDate == null) {
      return 'Please select your date of birth';
    }

    final DateTime today = DateTime.now();

    final DateTime selected = DateTime(
      selectedBirthDate!.year,
      selectedBirthDate!.month,
      selectedBirthDate!.day,
    );

    final DateTime currentDate = DateTime(today.year, today.month, today.day);

    if (selected.isAfter(currentDate)) {
      return 'Date of birth cannot be in the future';
    }

    final int age = _calculateAge(selected);

    if (age < 18) {
      return 'You must be at least 18 years old to register';
    }

    if (age > 120) {
      return 'Please enter a valid date of birth';
    }

    return null;
  }

  // ============================================================
  // ADDRESS
  // ============================================================

  String? validateAddress(String? value) {
    final String input = value?.trim() ?? '';

    if (input.isEmpty) {
      return 'Please enter your delivery address';
    }

    if (input.length < 8) {
      return 'Please enter a more complete delivery address';
    }

    if (input.length > 255) {
      return 'Delivery address must not exceed 255 characters';
    }

    return null;
  }

  // ============================================================
  // PASSWORD
  // ============================================================

  String? validatePassword(String? value) {
    if (value == null || value.isEmpty) {
      return 'Please create a password';
    }

    if (value.length < 8) {
      return 'Password must contain at least 8 characters';
    }

    if (value.length > 128) {
      return 'Password must not exceed 128 characters';
    }

    if (!RegExp(r'[A-Z]').hasMatch(value)) {
      return 'Password must contain an uppercase letter';
    }

    if (!RegExp(r'[a-z]').hasMatch(value)) {
      return 'Password must contain a lowercase letter';
    }

    if (!RegExp(r'[0-9]').hasMatch(value)) {
      return 'Password must contain a number';
    }

    if (!RegExp(r'[^A-Za-z0-9]').hasMatch(value)) {
      return 'Password must contain a special character';
    }

    return null;
  }

  // ============================================================
  // CONFIRM PASSWORD
  // ============================================================

  String? validateConfirmPassword(String? value) {
    if (value == null || value.isEmpty) {
      return 'Please confirm your password';
    }

    if (value != passwordController.text) {
      return 'Passwords do not match';
    }

    return null;
  }

  // ============================================================
  // SNACKBAR
  // ============================================================

  void showMessage(String message, {bool isSuccess = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Row(
            children: [
              Icon(
                isSuccess
                    ? Icons.check_circle_outline_rounded
                    : Icons.info_outline_rounded,
                color: Colors.white,
                size: 21,
              ),
              const SizedBox(width: 10),
              Expanded(child: Text(message)),
            ],
          ),
          behavior: SnackBarBehavior.floating,
          backgroundColor: isSuccess
              ? RegistrationColors.success
              : RegistrationColors.textDark,
          margin: const EdgeInsets.all(16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
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

    final double horizontalPadding = screenSize.width < 360 ? 16.0 : 22.0;

    return Scaffold(
      backgroundColor: RegistrationColors.background,

      appBar: AppBar(
        backgroundColor: RegistrationColors.background,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        leading: IconButton(
          tooltip: 'Back',
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: RegistrationColors.textDark,
            size: 20,
          ),
          onPressed: isLoading
              ? null
              : () {
                  Navigator.pop(context);
                },
        ),
        title: const Text(
          'Create Account',
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
          child: LayoutBuilder(
            builder: (context, constraints) {
              return SingleChildScrollView(
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                padding: EdgeInsets.fromLTRB(
                  horizontalPadding,
                  10,
                  horizontalPadding,
                  24,
                ),
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: constraints.maxHeight - 34,
                  ),
                  child: Center(
                    child: Container(
                      width: double.infinity,
                      constraints: const BoxConstraints(maxWidth: 430),
                      padding: EdgeInsets.symmetric(
                        horizontal: screenSize.width < 360 ? 18 : 24,
                        vertical: 28,
                      ),
                      decoration: RegistrationStyle.card,
                      child: AutofillGroup(
                        child: Form(
                          key: _formKey,
                          autovalidateMode: AutovalidateMode.onUserInteraction,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              // ==================================
                              // LOGO
                              // ==================================

                              Center(
                                child: Column(
                                  children: [
                                    Container(
                                      height: 62,
                                      width: 62,
                                      decoration:
                                          RegistrationStyle.logoContainer,
                                      child: const Icon(
                                        Icons.person_add_alt_1_outlined,
                                        color: RegistrationColors.primary,
                                        size: 31,
                                      ),
                                    ),
                                    const SizedBox(height: 12),
                                    const Text(
                                      'BiliHub',
                                      style: RegistrationStyle.logo,
                                    ),
                                  ],
                                ),
                              ),

                              const SizedBox(height: 28),

                              const Text(
                                'Create your account',
                                style: RegistrationStyle.title,
                              ),

                              const SizedBox(height: 7),

                              const Text(
                                'Register as a buyer and start shopping with BiliHub.',
                                style: RegistrationStyle.subtitle,
                              ),

                              const SizedBox(height: 28),

                              // ==================================
                              // FIRST NAME
                              // ==================================
                              const Text(
                                'First Name',
                                style: RegistrationStyle.sectionTitle,
                              ),

                              const SizedBox(height: 8),

                              TextFormField(
                                controller: firstNameController,
                                focusNode: firstNameFocusNode,
                                enabled: !isLoading,
                                keyboardType: TextInputType.name,
                                textCapitalization: TextCapitalization.words,
                                textInputAction: TextInputAction.next,
                                autofillHints: const [AutofillHints.givenName],
                                maxLength: 50,
                                inputFormatters: [
                                  FilteringTextInputFormatter.allow(
                                    RegExp(r"[A-Za-zÑñ .'-]"),
                                  ),
                                ],
                                style: RegistrationStyle.inputText,
                                validator: validateFirstName,
                                onFieldSubmitted: (_) {
                                  lastNameFocusNode.requestFocus();
                                },
                                decoration: RegistrationStyle.inputDecoration(
                                  hint: 'Enter your first name',
                                  icon: Icons.person_outline_rounded,
                                ).copyWith(counterText: ''),
                              ),

                              const SizedBox(height: 18),

                              // ==================================
                              // LAST NAME
                              // ==================================
                              const Text(
                                'Last Name',
                                style: RegistrationStyle.sectionTitle,
                              ),

                              const SizedBox(height: 8),

                              TextFormField(
                                controller: lastNameController,
                                focusNode: lastNameFocusNode,
                                enabled: !isLoading,
                                keyboardType: TextInputType.name,
                                textCapitalization: TextCapitalization.words,
                                textInputAction: TextInputAction.next,
                                autofillHints: const [AutofillHints.familyName],
                                maxLength: 50,
                                inputFormatters: [
                                  FilteringTextInputFormatter.allow(
                                    RegExp(r"[A-Za-zÑñ .'-]"),
                                  ),
                                ],
                                style: RegistrationStyle.inputText,
                                validator: validateLastName,
                                onFieldSubmitted: (_) {
                                  emailFocusNode.requestFocus();
                                },
                                decoration: RegistrationStyle.inputDecoration(
                                  hint: 'Enter your last name',
                                  icon: Icons.person_outline_rounded,
                                ).copyWith(counterText: ''),
                              ),

                              const SizedBox(height: 18),

                              // ==================================
                              // EMAIL
                              // ==================================
                              const Text(
                                'Email Address',
                                style: RegistrationStyle.sectionTitle,
                              ),

                              const SizedBox(height: 8),

                              TextFormField(
                                controller: emailController,
                                focusNode: emailFocusNode,
                                enabled: !isLoading,
                                keyboardType: TextInputType.emailAddress,
                                textInputAction: TextInputAction.next,
                                autofillHints: const [AutofillHints.email],
                                autocorrect: false,
                                enableSuggestions: false,
                                style: RegistrationStyle.inputText,
                                validator: validateEmail,
                                onFieldSubmitted: (_) {
                                  phoneFocusNode.requestFocus();
                                },
                                decoration: RegistrationStyle.inputDecoration(
                                  hint: 'Enter your email address',
                                  icon: Icons.mail_outline_rounded,
                                ),
                              ),

                              const SizedBox(height: 18),

                              // ==================================
                              // PHONE NUMBER
                              // ==================================
                              const Text(
                                'Phone Number',
                                style: RegistrationStyle.sectionTitle,
                              ),

                              const SizedBox(height: 8),

                              TextFormField(
                                controller: phoneController,
                                focusNode: phoneFocusNode,
                                enabled: !isLoading,
                                keyboardType: TextInputType.phone,
                                textInputAction: TextInputAction.next,
                                autofillHints: const [
                                  AutofillHints.telephoneNumber,
                                ],
                                inputFormatters: [
                                  FilteringTextInputFormatter.digitsOnly,
                                  LengthLimitingTextInputFormatter(11),
                                ],
                                style: RegistrationStyle.inputText,
                                validator: validatePhone,
                                onFieldSubmitted: (_) {
                                  FocusScope.of(context).unfocus();
                                  _pickBirthDate();
                                },
                                decoration: RegistrationStyle.inputDecoration(
                                  hint: '09XXXXXXXXX',
                                  icon: Icons.phone_outlined,
                                ),
                              ),

                              const SizedBox(height: 18),

                              // ==================================
                              // DATE OF BIRTH
                              // ==================================
                              const Text(
                                'Date of Birth',
                                style: RegistrationStyle.sectionTitle,
                              ),

                              const SizedBox(height: 8),

                              TextFormField(
                                controller: birthDateController,
                                enabled: !isLoading,
                                readOnly: true,
                                showCursor: false,
                                validator: validateBirthDate,
                                style: RegistrationStyle.inputText,
                                onTap: _pickBirthDate,
                                decoration:
                                    RegistrationStyle.inputDecoration(
                                      hint: 'MM/DD/YYYY',
                                      icon: Icons.cake_outlined,
                                    ).copyWith(
                                      suffixIcon: IconButton(
                                        tooltip: 'Select date of birth',
                                        onPressed: isLoading
                                            ? null
                                            : _pickBirthDate,
                                        icon: const Icon(
                                          Icons.calendar_month_outlined,
                                          color: RegistrationColors.textGrey,
                                        ),
                                      ),
                                    ),
                              ),

                              const SizedBox(height: 8),

                              const Padding(
                                padding: EdgeInsets.only(left: 4),
                                child: Text(
                                  'You must be at least 18 years old to register.',
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: RegistrationColors.textGrey,
                                  ),
                                ),
                              ),

                              const SizedBox(height: 18),

                              // ==================================
                              // ADDRESS
                              // ==================================
                              const Text(
                                'Delivery Address',
                                style: RegistrationStyle.sectionTitle,
                              ),

                              const SizedBox(height: 8),

                              TextFormField(
                                controller: addressController,
                                focusNode: addressFocusNode,
                                enabled: !isLoading,
                                keyboardType: TextInputType.streetAddress,
                                textCapitalization:
                                    TextCapitalization.sentences,
                                textInputAction: TextInputAction.newline,
                                autofillHints: const [
                                  AutofillHints.fullStreetAddress,
                                ],
                                minLines: 2,
                                maxLines: 3,
                                maxLength: 255,
                                style: RegistrationStyle.inputText,
                                validator: validateAddress,
                                decoration: RegistrationStyle.inputDecoration(
                                  hint: 'Enter your complete delivery address',
                                  icon: Icons.location_on_outlined,
                                ).copyWith(counterText: ''),
                              ),

                              const SizedBox(height: 18),

                              // ==================================
                              // PASSWORD
                              // ==================================
                              const Text(
                                'Password',
                                style: RegistrationStyle.sectionTitle,
                              ),

                              const SizedBox(height: 8),

                              TextFormField(
                                controller: passwordController,
                                focusNode: passwordFocusNode,
                                enabled: !isLoading,
                                obscureText: hidePassword,
                                keyboardType: TextInputType.visiblePassword,
                                textInputAction: TextInputAction.next,
                                autofillHints: const [
                                  AutofillHints.newPassword,
                                ],
                                autocorrect: false,
                                enableSuggestions: false,
                                style: RegistrationStyle.inputText,
                                validator: validatePassword,
                                onFieldSubmitted: (_) {
                                  confirmPasswordFocusNode.requestFocus();
                                },
                                decoration:
                                    RegistrationStyle.inputDecoration(
                                      hint: 'Create a password',
                                      icon: Icons.lock_outline_rounded,
                                    ).copyWith(
                                      suffixIcon: IconButton(
                                        tooltip: hidePassword
                                            ? 'Show password'
                                            : 'Hide password',
                                        icon: Icon(
                                          hidePassword
                                              ? Icons.visibility_off_outlined
                                              : Icons.visibility_outlined,
                                          color: RegistrationColors.textGrey,
                                          size: 21,
                                        ),
                                        onPressed: isLoading
                                            ? null
                                            : () {
                                                setState(() {
                                                  hidePassword = !hidePassword;
                                                });
                                              },
                                      ),
                                    ),
                              ),

                              const SizedBox(height: 8),

                              const Padding(
                                padding: EdgeInsets.only(left: 4),
                                child: Text(
                                  'Use at least 8 characters with uppercase, lowercase, number, and special character.',
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: RegistrationColors.textGrey,
                                  ),
                                ),
                              ),

                              const SizedBox(height: 18),

                              // ==================================
                              // CONFIRM PASSWORD
                              // ==================================
                              const Text(
                                'Confirm Password',
                                style: RegistrationStyle.sectionTitle,
                              ),

                              const SizedBox(height: 8),

                              TextFormField(
                                controller: confirmPasswordController,
                                focusNode: confirmPasswordFocusNode,
                                enabled: !isLoading,
                                obscureText: hideConfirmPassword,
                                keyboardType: TextInputType.visiblePassword,
                                textInputAction: TextInputAction.done,
                                autofillHints: const [
                                  AutofillHints.newPassword,
                                ],
                                autocorrect: false,
                                enableSuggestions: false,
                                style: RegistrationStyle.inputText,
                                validator: validateConfirmPassword,
                                onFieldSubmitted: (_) {
                                  registerBuyer();
                                },
                                decoration:
                                    RegistrationStyle.inputDecoration(
                                      hint: 'Re-enter your password',
                                      icon: Icons.lock_reset_outlined,
                                    ).copyWith(
                                      suffixIcon: IconButton(
                                        tooltip: hideConfirmPassword
                                            ? 'Show password'
                                            : 'Hide password',
                                        icon: Icon(
                                          hideConfirmPassword
                                              ? Icons.visibility_off_outlined
                                              : Icons.visibility_outlined,
                                          color: RegistrationColors.textGrey,
                                          size: 21,
                                        ),
                                        onPressed: isLoading
                                            ? null
                                            : () {
                                                setState(() {
                                                  hideConfirmPassword =
                                                      !hideConfirmPassword;
                                                });
                                              },
                                      ),
                                    ),
                              ),

                              const SizedBox(height: 14),

                              // ==================================
                              // TERMS
                              // ==================================
                              InkWell(
                                borderRadius: BorderRadius.circular(8),
                                onTap: isLoading
                                    ? null
                                    : () {
                                        setState(() {
                                          agreeToTerms = !agreeToTerms;

                                          if (agreeToTerms) {
                                            showTermsError = false;
                                          }
                                        });
                                      },
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(
                                    vertical: 4,
                                  ),
                                  child: Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      SizedBox(
                                        width: 24,
                                        height: 24,
                                        child: Checkbox(
                                          value: agreeToTerms,
                                          activeColor:
                                              RegistrationColors.primary,
                                          side: BorderSide(
                                            color: showTermsError
                                                ? RegistrationColors.error
                                                : RegistrationColors.border,
                                            width: 1.4,
                                          ),
                                          shape: RoundedRectangleBorder(
                                            borderRadius: BorderRadius.circular(
                                              5,
                                            ),
                                          ),
                                          onChanged: isLoading
                                              ? null
                                              : (value) {
                                                  setState(() {
                                                    agreeToTerms =
                                                        value ?? false;

                                                    if (agreeToTerms) {
                                                      showTermsError = false;
                                                    }
                                                  });
                                                },
                                        ),
                                      ),

                                      const SizedBox(width: 10),

                                      const Expanded(
                                        child: Padding(
                                          padding: EdgeInsets.only(top: 3),
                                          child: Text(
                                            'I agree to the Terms and Conditions and Privacy Policy.',
                                            style: RegistrationStyle.termsText,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),

                              if (showTermsError)
                                const Padding(
                                  padding: EdgeInsets.only(left: 34, top: 4),
                                  child: Text(
                                    'Please agree before creating your account',
                                    style: RegistrationStyle.errorText,
                                  ),
                                ),

                              const SizedBox(height: 22),

                              // ==================================
                              // CREATE ACCOUNT
                              // ==================================
                              SizedBox(
                                width: double.infinity,
                                height: 52,
                                child: ElevatedButton(
                                  onPressed: isLoading ? null : registerBuyer,
                                  style: RegistrationStyle.primaryButton,
                                  child: AnimatedSwitcher(
                                    duration: const Duration(milliseconds: 200),
                                    child: isLoading
                                        ? const SizedBox(
                                            key: ValueKey('loading'),
                                            width: 22,
                                            height: 22,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2.2,
                                              color: Colors.white,
                                            ),
                                          )
                                        : const Text(
                                            'Create Account',
                                            key: ValueKey('create-account'),
                                            style: RegistrationStyle.buttonText,
                                          ),
                                  ),
                                ),
                              ),

                              const SizedBox(height: 20),

                              // ==================================
                              // LOGIN
                              // ==================================
                              Center(
                                child: TextButton(
                                  onPressed: isLoading
                                      ? null
                                      : () {
                                          Navigator.pop(context);
                                        },
                                  style: RegistrationStyle.textButton,
                                  child: const Text(
                                    'Already have an account? Login',
                                    style: RegistrationStyle.loginLinkText,
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
              );
            },
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
    firstNameController.dispose();
    lastNameController.dispose();
    emailController.dispose();
    phoneController.dispose();
    birthDateController.dispose();
    addressController.dispose();
    passwordController.dispose();
    confirmPasswordController.dispose();

    firstNameFocusNode.dispose();
    lastNameFocusNode.dispose();
    emailFocusNode.dispose();
    phoneFocusNode.dispose();
    addressFocusNode.dispose();
    passwordFocusNode.dispose();
    confirmPasswordFocusNode.dispose();

    super.dispose();
  }
}
