import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../shared/submit_state.dart';
import '../domain/sign_in_result.dart';
import 'pending_auth.dart';

/// Where the sign-in form should go next.
enum SignInOutcome { signedIn, needsTwoFactor, needsEmailConfirmation, failed }

final signInControllerProvider = NotifierProvider.autoDispose<SignInController, SubmitState>(SignInController.new);

class SignInController extends Notifier<SubmitState> {
  @override
  SubmitState build() => SubmitState.idle;

  Future<SignInOutcome> submit({required String email, required String password}) async {
    if (state.submitting) {
      return SignInOutcome.failed;
    }

    state = SubmitState.busy;
    final cleanEmail = email.trim().toLowerCase();

    try {
      final result = await ref.read(authApiProvider).signIn(
            email: cleanEmail,
            password: password,
            deviceName: ref.read(appInfoProvider).deviceName,
          );

      switch (result) {
        case SignedInResult(:final token, :final user):
          await ref.read(sessionControllerProvider.notifier).signIn(token: token, user: user);
          if (ref.mounted) state = SubmitState.idle;

          return SignInOutcome.signedIn;
        case TwoFactorRequired(:final challenge):
          ref.read(pendingTwoFactorProvider.notifier).set(challenge);
          if (ref.mounted) state = SubmitState.idle;

          return SignInOutcome.needsTwoFactor;
      }
    } on EmailNotVerifiedFailure {
      ref.read(pendingVerificationEmailProvider.notifier).set(cleanEmail);
      if (ref.mounted) state = SubmitState.idle;

      return SignInOutcome.needsEmailConfirmation;
    } on AppFailure catch (failure) {
      if (ref.mounted) state = SubmitState(failure: failure);

      return SignInOutcome.failed;
    }
  }
}
