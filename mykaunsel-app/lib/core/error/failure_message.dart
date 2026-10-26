import '../../l10n/app_localizations.dart';
import 'app_failure.dart';

/// The words to show a person for a failure, in their language. The server's own message is used where it is meant to
/// be read (validation, "sign-up is closed", maintenance); everything technical gets a plain sentence from the app.
String failureMessage(AppLocalizations l10n, AppFailure failure) {
  return switch (failure) {
    NetworkFailure() => l10n.noConnectionBody,
    RateLimitedFailure(:final retryAfterSeconds) =>
      retryAfterSeconds == null ? l10n.tooManyRequests : l10n.tooManyRequestsIn(retryAfterSeconds),
    ValidationFailure(:final message) => message.isEmpty ? l10n.somethingWentWrongBody : message,
    ForbiddenFailure(:final message) => message.isEmpty ? l10n.somethingWentWrongBody : message,
    EmailNotVerifiedFailure(:final message) => message.isEmpty ? l10n.somethingWentWrongBody : message,
    MaintenanceFailure(:final message) => message.isEmpty ? l10n.maintenanceTitle : message,
    UpdateRequiredFailure(:final message) => message.isEmpty ? l10n.updateRequiredBody : message,
    UnauthorizedFailure() => l10n.sessionExpired,
    NotFoundFailure() => l10n.notFoundBody,
    ServerFailure() => l10n.somethingWentWrongBody,
  };
}
