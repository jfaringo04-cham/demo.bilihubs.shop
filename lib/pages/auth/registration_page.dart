import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../styles/registration_style.dart';

class RegistrationPage extends StatefulWidget {
  const RegistrationPage({super.key});

  @override
  State<RegistrationPage> createState() => _RegistrationPageState();
}

class _RegistrationPageState extends State<RegistrationPage> {
  // ------------------------------------------------------------
  // FORM
  // ------------------------------------------------------------

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  // ------------------------------------------------------------
  // CONTROLLERS
  // ------------------------------------------------------------

  final TextEditingController firstNameController = TextEditingController();

  final TextEditingController lastNameController = TextEditingController();

  final TextEditingController emailController = TextEditingController();

  final TextEditingController phoneController = TextEditingController();

  final TextEditingController addressController = TextEditingController();

  final TextEditingController passwordController = TextEditingController();

  final TextEditingController confirmPasswordController =
      TextEditingController();

  // ------------------------------------------------------------
  // FOCUS NODES
  // ------------------------------------------------------------

  final FocusNode firstNameFocusNode = FocusNode();
  final FocusNode lastNameFocusNode = FocusNode();
  final FocusNode emailFocusNode = FocusNode();
  final FocusNode phoneFocusNode = FocusNode();
  final FocusNode addressFocusNode = FocusNode();
  final FocusNode passwordFocusNode = FocusNode();
  final FocusNode confirmPasswordFocusNode = FocusNode();

  // ------------------------------------------------------------
  // STATE
  // ------------------------------------------------------------

  bool hidePassword = true;
  bool hideConfirmPassword = true;
  bool agreeToTerms = false;
  bool showTermsError = false;
  bool isLoading = false;

  // ------------------------------------------------------------
  // REGISTER
  // ------------------------------------------------------------

  Future<void> registerBuyer() async {
    FocusScope.of(context).unfocus();

    if (isLoading) return;

    final isFormValid = _formKey.currentState?.validate() ?? false;

    setState(() {
      showTermsError = !agreeToTerms;
    });

    if (!isFormValid || !agreeToTerms) {
      return;
    }

    final firstName = firstNameController.text.trim();

    final lastName = lastNameController.text.trim();

    final email = emailController.text.trim().toLowerCase();

    final phone = phoneController.text.trim();

    final address = addressController.text.trim();

    // Never trim passwords automatically.
    final password = passwordController.text;

    setState(() {
      isLoading = true;
    });

    // ----------------------------------------------------------
    // TEMPORARY REGISTRATION
    // Replace this block with your API later.
    //
    // Example:
    //
    // await authService.registerBuyer(
    //   firstName: firstName,
    //   lastName: lastName,
    //   email: email,
    //   phone: phone,
    //   address: address,
    //   password: password,
    // );
    // ----------------------------------------------------------

    await Future.delayed(const Duration(milliseconds: 900));

    if (!mounted) return;

    setState(() {
      isLoading = false;
    });

    // Temporary values are referenced here so Dart
    // doesn't flag them as unused while API is not connected.
    debugPrint(
      "Registering buyer: "
      "$firstName $lastName | "
      "$email | "
      "$phone | "
      "$address | "
      "Password length: ${password.length}",
    );

    showMessage("Buyer account created successfully", isSuccess: true);

    await Future.delayed(const Duration(milliseconds: 700));

    if (!mounted) return;

    Navigator.pop(context);
  }

  // ------------------------------------------------------------
  // VALIDATORS
  // ------------------------------------------------------------

  String? validateFirstName(String? value) {
    final input = value?.trim() ?? "";

    if (input.isEmpty) {
      return "Please enter your first name";
    }

    if (input.length < 2) {
      return "First name is too short";
    }

    return null;
  }

  String? validateLastName(String? value) {
    final input = value?.trim() ?? "";

    if (input.isEmpty) {
      return "Please enter your last name";
    }

    if (input.length < 2) {
      return "Last name is too short";
    }

    return null;
  }

  String? validateEmail(String? value) {
    final input = value?.trim() ?? "";

    if (input.isEmpty) {
      return "Please enter your email address";
    }

    final emailRegex = RegExp(
      r'^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$',
    );

    if (!emailRegex.hasMatch(input)) {
      return "Please enter a valid email address";
    }

    return null;
  }

  String? validatePhone(String? value) {
    final input = value?.trim() ?? "";

    if (input.isEmpty) {
      return "Please enter your phone number";
    }

    final phoneRegex = RegExp(r'^09\d{9}$');

    if (!phoneRegex.hasMatch(input)) {
      return "Use format 09XXXXXXXXX";
    }

    return null;
  }

  String? validateAddress(String? value) {
    final input = value?.trim() ?? "";

    if (input.isEmpty) {
      return "Please enter your delivery address";
    }

    if (input.length < 8) {
      return "Please enter a more complete address";
    }

    return null;
  }

  String? validatePassword(String? value) {
    if (value == null || value.isEmpty) {
      return "Please create a password";
    }

    if (value.length < 6) {
      return "Password must contain at least 6 characters";
    }

    return null;
  }

  String? validateConfirmPassword(String? value) {
    if (value == null || value.isEmpty) {
      return "Please confirm your password";
    }

    if (value != passwordController.text) {
      return "Passwords do not match";
    }

    return null;
  }

  // ------------------------------------------------------------
  // MESSAGE
  // ------------------------------------------------------------

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

  // ------------------------------------------------------------
  // BUILD
  // ------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final screenSize = MediaQuery.of(context).size;

    final horizontalPadding = screenSize.width < 360 ? 16.0 : 22.0;

    return Scaffold(
      backgroundColor: RegistrationColors.background,

      appBar: AppBar(
        backgroundColor: RegistrationColors.background,

        elevation: 0,

        scrolledUnderElevation: 0,

        centerTitle: true,

        leading: IconButton(
          tooltip: "Back",

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
          "Create Account",
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

                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,

                            children: [
                              // --------------------------------
                              // LOGO
                              // --------------------------------

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
                                      "BiliHub",
                                      style: RegistrationStyle.logo,
                                    ),
                                  ],
                                ),
                              ),

                              const SizedBox(height: 28),

                              // --------------------------------
                              // TITLE
                              // --------------------------------
                              const Text(
                                "Create your account",
                                style: RegistrationStyle.title,
                              ),

                              const SizedBox(height: 7),

                              const Text(
                                "Register as a buyer and start shopping with BiliHub.",
                                style: RegistrationStyle.subtitle,
                              ),

                              const SizedBox(height: 28),

                              // --------------------------------
                              // FIRST NAME
                              // --------------------------------
                              const Text(
                                "First Name",
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

                                style: RegistrationStyle.inputText,

                                validator: validateFirstName,

                                onFieldSubmitted: (_) {
                                  lastNameFocusNode.requestFocus();
                                },

                                decoration: RegistrationStyle.inputDecoration(
                                  hint: "Enter your first name",
                                  icon: Icons.person_outline_rounded,
                                ),
                              ),

                              const SizedBox(height: 18),

                              // --------------------------------
                              // LAST NAME
                              // --------------------------------
                              const Text(
                                "Last Name",
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

                                style: RegistrationStyle.inputText,

                                validator: validateLastName,

                                onFieldSubmitted: (_) {
                                  emailFocusNode.requestFocus();
                                },

                                decoration: RegistrationStyle.inputDecoration(
                                  hint: "Enter your last name",
                                  icon: Icons.person_outline_rounded,
                                ),
                              ),

                              const SizedBox(height: 18),

                              // --------------------------------
                              // EMAIL
                              // --------------------------------
                              const Text(
                                "Email Address",
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

                                style: RegistrationStyle.inputText,

                                validator: validateEmail,

                                onFieldSubmitted: (_) {
                                  phoneFocusNode.requestFocus();
                                },

                                decoration: RegistrationStyle.inputDecoration(
                                  hint: "Enter your email address",
                                  icon: Icons.mail_outline_rounded,
                                ),
                              ),

                              const SizedBox(height: 18),

                              // --------------------------------
                              // PHONE
                              // --------------------------------
                              const Text(
                                "Phone Number",
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
                                  addressFocusNode.requestFocus();
                                },

                                decoration: RegistrationStyle.inputDecoration(
                                  hint: "09XXXXXXXXX",
                                  icon: Icons.phone_outlined,
                                ),
                              ),

                              const SizedBox(height: 18),

                              // --------------------------------
                              // ADDRESS
                              // --------------------------------
                              const Text(
                                "Delivery Address",
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

                                style: RegistrationStyle.inputText,

                                validator: validateAddress,

                                decoration: RegistrationStyle.inputDecoration(
                                  hint: "Enter your complete delivery address",
                                  icon: Icons.location_on_outlined,
                                ),
                              ),

                              const SizedBox(height: 18),

                              // --------------------------------
                              // PASSWORD
                              // --------------------------------
                              const Text(
                                "Password",
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
                                      hint: "Create a password",
                                      icon: Icons.lock_outline_rounded,
                                    ).copyWith(
                                      suffixIcon: IconButton(
                                        tooltip: hidePassword
                                            ? "Show password"
                                            : "Hide password",

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

                              const SizedBox(height: 18),

                              // --------------------------------
                              // CONFIRM PASSWORD
                              // --------------------------------
                              const Text(
                                "Confirm Password",
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
                                      hint: "Re-enter your password",
                                      icon: Icons.lock_reset_outlined,
                                    ).copyWith(
                                      suffixIcon: IconButton(
                                        tooltip: hideConfirmPassword
                                            ? "Show password"
                                            : "Hide password",

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

                              // --------------------------------
                              // TERMS
                              // --------------------------------
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
                                            "I agree to the Terms and Conditions and Privacy Policy.",
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
                                    "Please agree before creating your account",
                                    style: RegistrationStyle.errorText,
                                  ),
                                ),

                              const SizedBox(height: 22),

                              // --------------------------------
                              // CREATE ACCOUNT
                              // --------------------------------
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
                                            key: ValueKey("loading"),

                                            width: 22,
                                            height: 22,

                                            child: CircularProgressIndicator(
                                              strokeWidth: 2.2,
                                              color: Colors.white,
                                            ),
                                          )
                                        : const Text(
                                            "Create Account",
                                            key: ValueKey("create-account"),
                                            style: RegistrationStyle.buttonText,
                                          ),
                                  ),
                                ),
                              ),

                              const SizedBox(height: 20),

                              // --------------------------------
                              // BACK TO LOGIN
                              // --------------------------------
                              Center(
                                child: TextButton(
                                  onPressed: isLoading
                                      ? null
                                      : () {
                                          Navigator.pop(context);
                                        },

                                  style: RegistrationStyle.textButton,

                                  child: const Text(
                                    "Already have an account? Login",
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

  // ------------------------------------------------------------
  // DISPOSE
  // ------------------------------------------------------------

  @override
  void dispose() {
    firstNameController.dispose();
    lastNameController.dispose();
    emailController.dispose();
    phoneController.dispose();
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
