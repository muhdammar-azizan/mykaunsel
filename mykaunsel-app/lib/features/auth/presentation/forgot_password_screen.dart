import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/auth_scaffold.dart';
import 'forgot_password_controller.dart';

class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    await ref.read(forgotPasswordControllerProvider.notifier).submit(_email.text);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final state = ref.watch(forgotPasswordControllerProvider);

    // The link was requested: the same page for every address (the server never says whether the account exists).
    if (state.sentTo != null) {
      return AuthScaffold(
        title: l10n.forgotSentTitle,
        subtitle: l10n.forgotSentBody(state.sentTo!),
        showBack: true,
        children: [
          FilledButton(onPressed: () => context.go(AppRoutes.signIn), child: Text(l10n.backToSignIn)),
          const SizedBox(height: 8),
          TextButton(onPressed: ref.read(forgotPasswordControllerProvider.notifier).reset, child: Text(l10n.useDifferentEmail)),
        ],
      );
    }

    return AuthScaffold(
      title: l10n.forgotTitle,
      subtitle: l10n.forgotSubtitle,
      showBack: true,
      children: [
        FailureBanner(failure: state.failure),
        Form(
          key: _formKey,
          child: TextFormField(
            controller: _email,
            enabled: !state.submitting,
            autofocus: true,
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.done,
            autofillHints: const [AutofillHints.email],
            autocorrect: false,
            enableSuggestions: false,
            onFieldSubmitted: (_) => _submit(),
            validator: (value) => (value ?? '').trim().isEmpty ? l10n.emailRequired : null,
            decoration: InputDecoration(labelText: l10n.emailLabel),
          ),
        ),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: state.submitting ? null : _submit,
          child: state.submitting
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
              : Text(l10n.sendLinkButton),
        ),
        const SizedBox(height: 8),
        TextButton(onPressed: () => context.go(AppRoutes.signIn), child: Text(l10n.backToSignIn)),
      ],
    );
  }
}
