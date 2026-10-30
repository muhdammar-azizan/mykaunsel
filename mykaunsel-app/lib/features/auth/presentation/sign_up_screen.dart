import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/error/failure_message.dart';
import '../../../core/router/app_router.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/password_rules.dart';
import '../../../shared/widgets/auth_scaffold.dart';
import '../../../shared/widgets/password_checklist.dart';
import '../../../shared/widgets/password_field.dart';
import '../../chat/presentation/ai_consent_widgets.dart';
import 'register_controller.dart';

/// Creating an individual account. First asks the server whether sign-up is open (a Platform Admin can close it from
/// Registration Control), and shows the admin's own message when it is closed.
class SignUpScreen extends ConsumerWidget {
  const SignUpScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final gate = ref.watch(signUpOpenProvider);

    return gate.when(
      loading: () => AuthScaffold(
        title: l10n.signUpCheckingTitle,
        showBack: true,
        children: const [Center(child: CircularProgressIndicator())],
      ),
      error: (error, _) => AuthScaffold(
        title: l10n.somethingWentWrongTitle,
        showBack: true,
        children: [
          Text(error is AppFailure ? failureMessage(l10n, error) : l10n.somethingWentWrongBody, textAlign: TextAlign.center),
          const SizedBox(height: 24),
          FilledButton(onPressed: () => ref.invalidate(signUpOpenProvider), child: Text(l10n.tryAgain)),
        ],
      ),
      data: (status) {
        if (!status.open) {
          return AuthScaffold(
            title: l10n.signUpClosedTitle,
            subtitle: status.message,
            showBack: true,
            children: [
              FilledButton(onPressed: () => context.go(AppRoutes.signIn), child: Text(l10n.backToSignIn)),
            ],
          );
        }

        return const _SignUpForm();
      },
    );
  }
}

class _SignUpForm extends ConsumerStatefulWidget {
  const _SignUpForm();

  @override
  ConsumerState<_SignUpForm> createState() => _SignUpFormState();
}

class _SignUpFormState extends ConsumerState<_SignUpForm> {
  static final _emailPattern = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _aiConsent = false;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final outcome = await ref.read(registerControllerProvider.notifier).submit(name: _name.text, email: _email.text, password: _password.text, aiConsent: _aiConsent);

    if (!mounted || outcome != RegisterOutcome.created) {
      return;
    }

    TextInput.finishAutofillContext();
    context.go(AppRoutes.verifyEmail);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final form = ref.watch(registerControllerProvider);

    return AuthScaffold(
      title: l10n.signUpTitle,
      subtitle: l10n.signUpSubtitle,
      showBack: true,
      children: [
        FailureBanner(failure: form.failure, hideFieldErrors: const ['name', 'email', 'password']),
        Form(
          key: _formKey,
          child: AutofillGroup(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _name,
                  enabled: !form.submitting,
                  textCapitalization: TextCapitalization.words,
                  textInputAction: TextInputAction.next,
                  autofillHints: const [AutofillHints.name],
                  validator: (value) => (value ?? '').trim().isEmpty ? l10n.nameRequired : null,
                  decoration: InputDecoration(labelText: l10n.nameLabel, errorText: form.fieldError('name')),
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _email,
                  enabled: !form.submitting,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  autofillHints: const [AutofillHints.email],
                  autocorrect: false,
                  enableSuggestions: false,
                  validator: (value) {
                    final text = (value ?? '').trim();

                    if (text.isEmpty) return l10n.emailRequired;

                    return _emailPattern.hasMatch(text) ? null : l10n.emailInvalid;
                  },
                  decoration: InputDecoration(labelText: l10n.emailLabel, errorText: form.fieldError('email')),
                ),
                const SizedBox(height: 16),
                PasswordField(
                  controller: _password,
                  label: l10n.passwordLabel,
                  enabled: !form.submitting,
                  textInputAction: TextInputAction.next,
                  autofillHints: const [AutofillHints.newPassword],
                  errorText: form.fieldError('password'),
                  validator: (value) {
                    final text = value ?? '';

                    if (text.isEmpty) return l10n.passwordRequired;

                    return PasswordRules.of(text).isStrong ? null : l10n.passwordMustHave;
                  },
                ),
                ListenableBuilder(listenable: _password, builder: (context, _) => PasswordChecklist(password: _password.text)),
                const SizedBox(height: 16),
                PasswordField(
                  controller: _confirm,
                  label: l10n.confirmPasswordLabel,
                  enabled: !form.submitting,
                  autofillHints: const [AutofillHints.newPassword],
                  validator: (value) {
                    if ((value ?? '').isEmpty) return l10n.confirmPasswordRequired;

                    return value == _password.text ? null : l10n.passwordsDontMatch;
                  },
                  onSubmitted: (_) => _submit(),
                ),
                const SizedBox(height: 8),
                // Agreeing to AI processing is part of making an account (the chat needs it); it can be withdrawn later in Account.
                FormField<bool>(
                  initialValue: _aiConsent,
                  validator: (value) => value == true ? null : l10n.signUpConsentRequired,
                  builder: (field) => Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        controlAffinity: ListTileControlAffinity.leading,
                        enabled: !form.submitting,
                        value: field.value ?? false,
                        title: Text(l10n.signUpConsent),
                        onChanged: (value) {
                          field.didChange(value);
                          setState(() => _aiConsent = value ?? false);
                        },
                      ),
                      Align(alignment: AlignmentDirectional.centerStart, child: TextButton(onPressed: () => showAiConsentInfo(context), child: Text(l10n.signUpConsentMore))),
                      if (field.hasError)
                        Padding(
                          padding: const EdgeInsets.only(left: 12),
                          child: Semantics(liveRegion: true, child: Text(field.errorText!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
                        ),
                    ],
                  ),
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
              : Text(l10n.createAccountButton),
        ),
        const SizedBox(height: 8),
        Wrap(
          alignment: WrapAlignment.center,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Text(l10n.alreadyHaveAccount),
            TextButton(onPressed: () => context.go(AppRoutes.signIn), child: Text(l10n.signInLink)),
          ],
        ),
      ],
    );
  }
}
