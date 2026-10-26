import '../core/error/app_failure.dart';

/// What a form is doing: idle, sending (the button waits), or the last attempt failed with [failure].
class SubmitState {
  const SubmitState({this.submitting = false, this.failure});

  static const idle = SubmitState();
  static const busy = SubmitState(submitting: true);

  final bool submitting;
  final AppFailure? failure;

  /// The message the server gave for one field, for example `email`.
  String? fieldError(String field) {
    final f = failure;

    return f is ValidationFailure ? f.errorFor(field) : null;
  }
}
