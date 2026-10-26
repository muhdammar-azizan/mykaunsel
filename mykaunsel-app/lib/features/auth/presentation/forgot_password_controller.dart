import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';

class ForgotPasswordState {
  const ForgotPasswordState({this.submitting = false, this.sentTo, this.failure});

  final bool submitting;

  /// The address the link was requested for; once set the page shows "check your email".
  final String? sentTo;
  final AppFailure? failure;
}

final forgotPasswordControllerProvider = NotifierProvider.autoDispose<ForgotPasswordController, ForgotPasswordState>(ForgotPasswordController.new);

class ForgotPasswordController extends Notifier<ForgotPasswordState> {
  @override
  ForgotPasswordState build() => const ForgotPasswordState();

  Future<void> submit(String email) async {
    if (state.submitting) {
      return;
    }

    state = const ForgotPasswordState(submitting: true);
    final cleanEmail = email.trim().toLowerCase();

    try {
      await ref.read(authApiProvider).forgotPassword(cleanEmail);
      // The server gives the same answer whether or not the address has an account, and so does the app.
      if (ref.mounted) state = ForgotPasswordState(sentTo: cleanEmail);
    } on AppFailure catch (failure) {
      if (ref.mounted) state = ForgotPasswordState(failure: failure);
    }
  }

  /// "Use a different email".
  void reset() => state = const ForgotPasswordState();
}
