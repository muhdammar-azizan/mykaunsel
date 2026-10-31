import '../../features/auth/domain/app_user.dart';
import '../error/app_failure.dart';

/// Why the person is signed out (so the sign-in screen can say so).
enum SignedOutReason { none, sessionExpired, signedOutByUser, accountDeleted }

/// The ONE answer to "where is this person right now?". The router only reads this, so nobody can end up on a screen
/// that does not match their situation.
sealed class SessionState {
  const SessionState();
}

/// Asking the server (maintenance? update needed? is the saved token still good?).
class SessionStarting extends SessionState {
  const SessionStarting();
}

class SessionMaintenance extends SessionState {
  const SessionMaintenance(this.message);

  /// What the Platform Admin wrote; may be empty.
  final String message;
}

class SessionUpdateRequired extends SessionState {
  const SessionUpdateRequired({this.message = '', this.storeUrl});

  final String message;
  final String? storeUrl;
}

/// Could not find out (no connection, server error). The saved token is kept; "try again" starts over.
class SessionStartupFailed extends SessionState {
  const SessionStartupFailed(this.failure);

  final AppFailure failure;
}

class SessionSignedOut extends SessionState {
  const SessionSignedOut({this.reason = SignedOutReason.none});

  final SignedOutReason reason;
}

class SessionSignedIn extends SessionState {
  const SessionSignedIn(this.user);

  final AppUser user;
}
