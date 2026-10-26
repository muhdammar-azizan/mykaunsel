/// Everything that can go wrong when talking to the server, as ONE small set of types. The network layer turns raw
/// HTTP errors into these once, so no screen ever has to look at a status code.
sealed class AppFailure implements Exception {
  const AppFailure();
}

/// No internet, or the server did not answer in time.
class NetworkFailure extends AppFailure {
  const NetworkFailure({this.timedOut = false});

  final bool timedOut;
}

/// 401: no token, or it expired / was removed. The person has to sign in again.
class UnauthorizedFailure extends AppFailure {
  const UnauthorizedFailure();
}

/// 403 with code `email_not_verified`: the password was right but the address is not confirmed yet.
class EmailNotVerifiedFailure extends AppFailure {
  const EmailNotVerifiedFailure(this.message);

  final String message;
}

/// Any other 403, for example sign-up is closed (the message is what the Platform Admin wrote).
class ForbiddenFailure extends AppFailure {
  const ForbiddenFailure(this.message, {this.code});

  final String message;
  final String? code;
}

/// 422: something typed is wrong. `fieldErrors` maps a field name (`email`) to its messages.
class ValidationFailure extends AppFailure {
  const ValidationFailure(this.message, {this.fieldErrors = const {}});

  final String message;
  final Map<String, List<String>> fieldErrors;

  /// The first message for a field, if any.
  String? errorFor(String field) => fieldErrors[field]?.firstOrNull;
}

/// 426: this app version is too old for the server.
class UpdateRequiredFailure extends AppFailure {
  const UpdateRequiredFailure(this.message, {this.storeUrl});

  final String message;
  final String? storeUrl;
}

/// 404: it is not there, or this person may not see it (the server answers both the same on purpose).
class NotFoundFailure extends AppFailure {
  const NotFoundFailure();
}

/// 429: too many attempts.
class RateLimitedFailure extends AppFailure {
  const RateLimitedFailure({this.retryAfterSeconds});

  final int? retryAfterSeconds;
}

/// 503: maintenance mode. The message is what the Platform Admin wrote.
class MaintenanceFailure extends AppFailure {
  const MaintenanceFailure(this.message);

  final String message;
}

/// 5xx or an answer the app did not understand.
class ServerFailure extends AppFailure {
  const ServerFailure({this.statusCode});

  final int? statusCode;
}
