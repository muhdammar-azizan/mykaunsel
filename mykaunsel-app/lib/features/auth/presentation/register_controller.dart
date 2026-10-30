import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../../shared/submit_state.dart';
import '../../platform_status/domain/platform_status.dart';
import 'pending_auth.dart';

/// Is individual sign-up open right now? Asked fresh every time the sign-up page opens (a Platform Admin may have
/// switched it since the app started). Null answer from the server = open; the server still refuses at the end if not.
final signUpOpenProvider = FutureProvider.autoDispose<RegistrationStatus>((ref) async {
  final status = await ref.read(platformStatusApiProvider).fetch();

  return status.registration['individual'] ?? const RegistrationStatus(open: true);
});

enum RegisterOutcome { created, failed }

final registerControllerProvider = NotifierProvider.autoDispose<RegisterController, SubmitState>(RegisterController.new);

class RegisterController extends Notifier<SubmitState> {
  @override
  SubmitState build() => SubmitState.idle;

  Future<RegisterOutcome> submit({required String name, required String email, required String password, required bool aiConsent}) async {
    if (state.submitting) {
      return RegisterOutcome.failed;
    }

    state = SubmitState.busy;
    final cleanEmail = email.trim().toLowerCase();

    try {
      await ref.read(authApiProvider).register(name: name.trim(), email: cleanEmail, password: password, aiConsent: aiConsent);

      // The next page ("check your email") reads this.
      ref.read(pendingVerificationEmailProvider.notifier).set(cleanEmail);
      if (ref.mounted) state = SubmitState.idle;

      return RegisterOutcome.created;
    } on AppFailure catch (failure) {
      if (ref.mounted) state = SubmitState(failure: failure);

      return RegisterOutcome.failed;
    }
  }
}
