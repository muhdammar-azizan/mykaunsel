import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/auth_scaffold.dart';
import 'pending_auth.dart';
import 'two_factor_controller.dart';

class TwoFactorScreen extends ConsumerStatefulWidget {
  const TwoFactorScreen({super.key});

  @override
  ConsumerState<TwoFactorScreen> createState() => _TwoFactorScreenState();
}

class _TwoFactorScreenState extends ConsumerState<TwoFactorScreen> {
  final _formKey = GlobalKey<FormState>();
  final _code = TextEditingController();
  bool _useRecovery = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  void _toggleMode() {
    setState(() {
      _useRecovery = !_useRecovery;
      _code.clear();
    });
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final outcome = await ref.read(twoFactorControllerProvider.notifier).submit(
          code: _useRecovery ? null : _code.text,
          recoveryCode: _useRecovery ? _code.text : null,
        );

    if (!mounted) {
      return;
    }

    if (outcome == TwoFactorOutcome.expired) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AppLocalizations.of(context).signInExpired)));
      context.go(AppRoutes.signIn);
    }
    // signedIn: the session changed and the router takes the person home. failed: the message is shown below.
  }

  void _back() {
    ref.read(pendingTwoFactorProvider.notifier).set(null);
    context.go(AppRoutes.signIn);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final form = ref.watch(twoFactorControllerProvider);

    return AuthScaffold(
      title: l10n.twoFactorTitle,
      subtitle: _useRecovery ? l10n.twoFactorRecoveryBody : l10n.twoFactorBody,
      children: [
        FailureBanner(failure: form.failure, hideFieldErrors: const ['code', 'recovery_code']),
        Form(
          key: _formKey,
          child: TextFormField(
            controller: _code,
            enabled: !form.submitting,
            autofocus: true,
            keyboardType: _useRecovery ? TextInputType.text : TextInputType.number,
            textInputAction: TextInputAction.done,
            autofillHints: _useRecovery ? null : const [AutofillHints.oneTimeCode],
            autocorrect: false,
            enableSuggestions: false,
            maxLength: _useRecovery ? 32 : 6,
            inputFormatters: _useRecovery ? null : [FilteringTextInputFormatter.digitsOnly],
            onFieldSubmitted: (_) => _submit(),
            validator: (value) {
              final text = (value ?? '').trim();

              if (_useRecovery) {
                return text.isEmpty ? l10n.recoveryCodeRequired : null;
              }

              return text.length == 6 ? null : l10n.codeRequired;
            },
            decoration: InputDecoration(
              labelText: _useRecovery ? l10n.recoveryCodeLabel : l10n.codeLabel,
              counterText: '',
              errorText: form.fieldError(_useRecovery ? 'recovery_code' : 'code'),
            ),
          ),
        ),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: form.submitting ? null : _submit,
          child: form.submitting
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
              : Text(l10n.verifyButton),
        ),
        const SizedBox(height: 8),
        TextButton(onPressed: form.submitting ? null : _toggleMode, child: Text(_useRecovery ? l10n.useAuthenticatorCode : l10n.useRecoveryCode)),
        TextButton(onPressed: form.submitting ? null : _back, child: Text(l10n.backToSignIn)),
      ],
    );
  }
}
