import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/auth_scaffold.dart';
import 'pending_auth.dart';
import 'resend_controller.dart';

/// "Check your email". Reached after a sign-in with an address that is not confirmed yet (and, in the next phase, after
/// signing up). The link in the email opens in the browser; the person then comes back here and signs in.
class VerifyEmailScreen extends ConsumerWidget {
  const VerifyEmailScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final email = ref.watch(pendingVerificationEmailProvider) ?? '';
    final resend = ref.watch(resendControllerProvider);

    return AuthScaffold(
      title: l10n.verifyEmailTitle,
      subtitle: l10n.verifyEmailBody(email),
      children: [
        FailureBanner(failure: resend.failure),
        if (resend.sent)
          Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: Semantics(liveRegion: true, child: Text(l10n.emailResent, textAlign: TextAlign.center)),
          ),
        OutlinedButton(
          onPressed: resend.canSend ? () => ref.read(resendControllerProvider.notifier).send(email) : null,
          child: resend.sending
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.5))
              : Text(resend.secondsLeft > 0 ? l10n.resendEmailIn(resend.secondsLeft) : l10n.resendEmail),
        ),
        const SizedBox(height: 8),
        FilledButton(
          onPressed: () {
            ref.read(pendingVerificationEmailProvider.notifier).set(null);
            context.go(AppRoutes.signIn);
          },
          child: Text(l10n.backToSignIn),
        ),
      ],
    );
  }
}
