import 'package:flutter/foundation.dart';

/// Where unexpected errors go. The app only knows this interface; the real service (Firebase Crashlytics, decided
/// but not set up yet because it needs the Firebase project files) is plugged in by replacing [NoopCrashReporter]
/// in `main.dart`, with no change anywhere else.
///
/// PRIVACY: this is a counselling app. Only the error and its stack trace may be sent. Never a name, an email, a
/// message someone typed, a request body or a token.
abstract class CrashReporter {
  void report(Object error, StackTrace? stack, {bool fatal = false});
}

/// Does not send anything anywhere; prints in debug builds only.
class NoopCrashReporter implements CrashReporter {
  const NoopCrashReporter();

  @override
  void report(Object error, StackTrace? stack, {bool fatal = false}) {
    if (kDebugMode) {
      debugPrint('Unreported ${fatal ? 'fatal ' : ''}error: $error\n$stack');
    }
  }
}

/// Sends every uncaught Flutter and platform error to [reporter].
void wireCrashReporting(CrashReporter reporter) {
  final previous = FlutterError.onError;

  FlutterError.onError = (details) {
    reporter.report(details.exception, details.stack);
    previous?.call(details);
  };

  PlatformDispatcher.instance.onError = (error, stack) {
    reporter.report(error, stack, fatal: true);

    return true;
  };
}
