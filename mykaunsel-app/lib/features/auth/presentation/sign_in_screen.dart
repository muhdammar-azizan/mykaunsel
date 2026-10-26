import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/session/session_state.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/auth_scaffold.dart';
import '../../../shared/widgets/password_field.dart';
import 'sign_in_controller.dart';

class SignInScreen extends ConsumerStatefulWidget {
  const SignInScreen({super.key});

  @override
  ConsumerState<SignInScreen> createState() => _SignInScreenState();
}

class _SignInScreenState extends ConsumerState<SignInScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final outcome = await ref.read(signInControllerProvider.notifier).submit(email: _email.text, password: _password.text);

    if (!mounted) {
      return;
    }

    switch (outcome) {
      case SignInOutcome.signedIn:
        // Tells the phone the password was accepted, so its password manager offers to save it.
        TextInput.finishAutofillContext();
      case SignInOutcome.needsTwoFactor:
        context.go(AppRoutes.twoFactor);
      case SignInOutcome.needsEmailConfirmation:
        context.go(AppRoutes.verifyEmail);
      case SignInOutcome.failed:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final form = ref.watch(signInControllerProvider);
    final session = ref.watch(sessionControllerProvider);
    final expired = session is SessionSignedOut && session.reason == SignedOutReason.sessionExpired;

    return AuthScaffold(
      title: l10n.signInTitle,
      subtitle: l10n.signInSubtitle,
      children: [
        if (expired)
          Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: Text(l10n.sessionExpired, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyMedium),
          ),
        FailureBanner(failure: form.failure, hideFieldErrors: const ['email', 'password']),
        Form(
          key: _formKey,
          child: AutofillGroup(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _email,
                  enabled: !form.submitting,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  autofillHints: const [AutofillHints.email],
                  autocorrect: false,
                  enableSuggestions: false,
                  validator: (value) => (value ?? '').trim().isEmpty ? l10n.emailRequired : null,
                  decoration: InputDecoration(labelText: l10n.emailLabel, errorText: form.fieldError('email')),
                ),
                const SizedBox(height: 16),
                PasswordField(
                  controller: _password,
                  label: l10n.passwordLabel,
                  enabled: !form.submitting,
                  errorText: form.fieldError('password'),
                  validator: (value) => (value ?? '').isEmpty ? l10n.passwordRequired : null,
                  onSubmitted: (_) => _submit(),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: form.submitting ? null : _submit,
          child: form.submitting
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
              : Text(l10n.signInButton),
        ),
        const SizedBox(height: 8),
        // push, not go: the back button returns here.
        TextButton(onPressed: form.submitting ? null : () => context.push(AppRoutes.forgotPassword), child: Text(l10n.forgotPasswordLink)),
        const SizedBox(height: 8),
        Wrap(
          alignment: WrapAlignment.center,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Text(l10n.noAccountYet),
            TextButton(onPressed: form.submitting ? null : () => context.push(AppRoutes.signUp), child: Text(l10n.createAccountLink)),
          ],
        ),
      ],
    );
  }
}
