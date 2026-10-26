import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../shared/submit_state.dart';
import 'pending_auth.dart';

enum TwoFactorOutcome { signedIn, expired, failed }

final twoFactorControllerProvider = NotifierProvider.autoDispose<TwoFactorController, SubmitState>(TwoFactorController.new);

class TwoFactorController extends Notifier<SubmitState> {
  @override
  SubmitState build() => SubmitState.idle;

  /// Exactly one of [code] / [recoveryCode] is given.
  Future<TwoFactorOutcome> submit({String? code, String? recoveryCode}) async {
    final challenge = ref.read(pendingTwoFactorProvider);

    if (challenge == null) {
      return TwoFactorOutcome.expired;
    }

    if (state.submitting) {
      return TwoFactorOutcome.failed;
    }

    state = SubmitState.busy;

    try {
      final result = await ref.read(authApiProvider).twoFactor(
            challenge: challenge,
            code: code?.trim(),
            recoveryCode: recoveryCode?.trim(),
            deviceName: ref.read(appInfoProvider).deviceName,
          );

      ref.read(pendingTwoFactorProvider.notifier).set(null);
      await ref.read(sessionControllerProvider.notifier).signIn(token: result.token, user: result.user);
      if (ref.mounted) state = SubmitState.idle;

      return TwoFactorOutcome.signedIn;
    } on ValidationFailure catch (failure) {
      // The server says the challenge itself is no good (too old, or too many wrong codes): start over.
      if (failure.fieldErrors.containsKey('challenge')) {
        ref.read(pendingTwoFactorProvider.notifier).set(null);
        if (ref.mounted) state = SubmitState.idle;

        return TwoFactorOutcome.expired;
      }

      if (ref.mounted) state = SubmitState(failure: failure);

      return TwoFactorOutcome.failed;
    } on AppFailure catch (failure) {
      if (ref.mounted) state = SubmitState(failure: failure);

      return TwoFactorOutcome.failed;
    }
  }
}
