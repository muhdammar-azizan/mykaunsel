import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../l10n/app_localizations.dart';
import '../../../shared/password_rules.dart';
import '../../../shared/widgets/auth_scaffold.dart';
import '../../../shared/widgets/password_checklist.dart';
import '../../../shared/widgets/password_field.dart';
import 'account_controllers.dart';

/// Change the password: the current one first, then the new one twice. The server signs every OTHER phone out.
class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  ConsumerState<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _new = TextEditingController();
  final _confirm = TextEditingController();

  @override
  void dispose() {
    _current.dispose();
    _new.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final changed = await ref.read(passwordControllerProvider.notifier).change(currentPassword: _current.text, newPassword: _new.text);

    if (changed && mounted) {
      TextInput.finishAutofillContext();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AppLocalizations.of(context).passwordChanged)));
      context.pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final form = ref.watch(passwordControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.passwordTitle)),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: AutofillGroup(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(l10n.passwordNote, style: Theme.of(context).textTheme.bodyMedium),
                      const SizedBox(height: 20),
                      FailureBanner(failure: form.failure, hideFieldErrors: const ['current_password', 'password']),
                      PasswordField(
                        controller: _current,
                        label: l10n.currentPasswordLabel,
                        enabled: !form.submitting,
                        textInputAction: TextInputAction.next,
                        errorText: form.fieldError('current_password'),
                        validator: (value) => (value ?? '').isEmpty ? l10n.currentPasswordRequired : null,
                      ),
                      const SizedBox(height: 16),
                      PasswordField(
                        controller: _new,
                        label: l10n.newPasswordLabel,
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
                      ListenableBuilder(listenable: _new, builder: (context, _) => PasswordChecklist(password: _new.text)),
                      const SizedBox(height: 16),
                      PasswordField(
                        controller: _confirm,
                        label: l10n.confirmNewPasswordLabel,
                        enabled: !form.submitting,
                        autofillHints: const [AutofillHints.newPassword],
                        validator: (value) {
                          if ((value ?? '').isEmpty) return l10n.confirmPasswordRequired;

                          return value == _new.text ? null : l10n.passwordsDontMatch;
                        },
                        onSubmitted: (_) => _submit(),
                      ),
                      const SizedBox(height: 24),
                      FilledButton(
                        onPressed: form.submitting ? null : _submit,
                        child: form.submitting
                            ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                            : Text(l10n.changePasswordButton),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
