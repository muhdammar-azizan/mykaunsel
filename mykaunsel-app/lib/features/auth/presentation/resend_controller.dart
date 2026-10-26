import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';

class ResendState {
  const ResendState({this.sending = false, this.secondsLeft = 0, this.sent = false, this.failure});

  final bool sending;

  /// Counts down after a send; the button is off until it reaches 0.
  final int secondsLeft;

  /// True once an email has been sent from this screen (shows the "we sent it again" note).
  final bool sent;
  final AppFailure? failure;

  bool get canSend => !sending && secondsLeft == 0;
}

final resendControllerProvider = NotifierProvider.autoDispose<ResendController, ResendState>(ResendController.new);

/// "Send the email again", with a wait between sends so the button cannot be hammered (the server also limits it:
/// 3 per 10 minutes for one address).
class ResendController extends Notifier<ResendState> {
  static const cooldownSeconds = 60;

  Timer? _timer;

  @override
  ResendState build() {
    ref.onDispose(() => _timer?.cancel());

    return const ResendState();
  }

  Future<void> send(String email) async {
    if (!state.canSend) {
      return;
    }

    state = const ResendState(sending: true);

    try {
      await ref.read(authApiProvider).resendVerification(email);
      if (!ref.mounted) return;

      state = const ResendState(secondsLeft: cooldownSeconds, sent: true);
      _startCountdown();
    } on RateLimitedFailure catch (failure) {
      if (!ref.mounted) return;

      // The server says wait: respect it (at least the normal wait).
      state = ResendState(secondsLeft: failure.retryAfterSeconds ?? cooldownSeconds, failure: failure);
      _startCountdown();
    } on AppFailure catch (failure) {
      if (ref.mounted) state = ResendState(failure: failure);
    }
  }

  void _startCountdown() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!ref.mounted) {
        timer.cancel();

        return;
      }

      final left = state.secondsLeft - 1;

      if (left <= 0) {
        timer.cancel();
      }

      state = ResendState(secondsLeft: left < 0 ? 0 : left, sent: state.sent, failure: state.failure);
    });
  }
}
