import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';

class AiConsentState {
  const AiConsentState({this.busy = false, this.failure});

  final bool busy;

  /// Why the last change did not go through (so the screen can say so and offer to try again).
  final AppFailure? failure;
}

final aiConsentControllerProvider = NotifierProvider.autoDispose<AiConsentController, AiConsentState>(AiConsentController.new);

/// Agreeing to (or withdrawing) AI processing, which the chat needs. The server records every change and decides; the person on screen is
/// updated from its answer, so the whole app agrees at once.
class AiConsentController extends Notifier<AiConsentState> {
  @override
  AiConsentState build() => const AiConsentState();

  /// True once the server has the new state.
  Future<bool> set(bool consent) async {
    if (state.busy) return false;

    state = const AiConsentState(busy: true);

    try {
      final user = await ref.read(accountApiProvider).setAiConsent(consent);

      if (!ref.mounted) return true;

      ref.read(sessionControllerProvider.notifier).updateUser(user);
      state = const AiConsentState();

      return true;
    } on AppFailure catch (failure) {
      if (ref.mounted) state = AiConsentState(failure: failure);

      return false;
    }
  }
}
