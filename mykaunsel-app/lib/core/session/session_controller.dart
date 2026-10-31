import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/domain/app_user.dart';
import '../error/app_failure.dart';
import '../providers.dart';
import 'session_state.dart';

final sessionControllerProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);

/// Decides where the person is: starting up, in maintenance, must update, signed out or signed in.
///
/// On start it asks the server in this order: maintenance? → app too old? → is there a saved token, and does it still
/// work? Later, any call that fails with "maintenance", "update needed" or "token no longer valid" arrives at
/// [handleFailure], so no screen has to deal with those itself.
class SessionController extends Notifier<SessionState> {
  @override
  SessionState build() {
    Future.microtask(start);

    return const SessionStarting();
  }

  /// (Re)starts from the top. Also what every "Try again" button calls.
  Future<void> start() async {
    state = const SessionStarting();

    try {
      final status = await ref.read(platformStatusApiProvider).fetch();
      if (!ref.mounted) return;

      if (status.maintenance.isOn) {
        state = SessionMaintenance(status.maintenance.message ?? '');

        return;
      }

      if (status.app.updateRequired) {
        state = SessionUpdateRequired(message: status.app.message ?? '', storeUrl: status.app.storeUrl);

        return;
      }

      final token = await ref.read(tokenStorageProvider).read();
      if (!ref.mounted) return;

      if (token == null) {
        state = const SessionSignedOut();

        return;
      }

      final user = await ref.read(authApiProvider).me();
      if (!ref.mounted) return;

      state = SessionSignedIn(user);
    } on UnauthorizedFailure {
      await ref.read(tokenStorageProvider).clear();
      if (ref.mounted) state = const SessionSignedOut(reason: SignedOutReason.sessionExpired);
    } on AppFailure catch (failure) {
      if (!ref.mounted) return;

      state = switch (failure) {
        MaintenanceFailure(:final message) => SessionMaintenance(message),
        UpdateRequiredFailure(:final message, :final storeUrl) => SessionUpdateRequired(message: message, storeUrl: storeUrl),
        _ => SessionStartupFailed(failure),
      };
    }
  }

  /// The person changed something about themselves (name, time zone): keep what the whole app shows up to date.
  void updateUser(AppUser user) {
    if (state is SessionSignedIn) {
      state = SessionSignedIn(user);
    }
  }

  /// After a successful sign-in / sign-up step that gave a token.
  Future<void> signIn({required String token, required AppUser user}) async {
    await ref.read(tokenStorageProvider).write(token);
    if (ref.mounted) state = SessionSignedIn(user);
  }

  /// Signs out of this device. Works without a connection: the token is always removed from the phone; if the server
  /// could not be told, the token just stays valid there until it expires (30 days) or is removed another way.
  ///
  /// With [everywhere] every device signed in to the account is signed out (lost phone, worried about a password).
  Future<void> signOut({bool everywhere = false}) async {
    try {
      final api = ref.read(authApiProvider);

      await (everywhere ? api.logoutAll() : api.logout());
    } on AppFailure {
      // Offline or the token was already invalid: sign out here anyway.
    }

    await ref.read(tokenStorageProvider).clear();
    if (ref.mounted) state = const SessionSignedOut(reason: SignedOutReason.signedOutByUser);
  }

  /// The account was deleted: the server already ended every sign-in, so nothing is asked of it. Only this phone forgets.
  Future<void> accountDeleted() async {
    await ref.read(tokenStorageProvider).clear();
    if (ref.mounted) state = const SessionSignedOut(reason: SignedOutReason.accountDeleted);
  }

  /// Called for every failed API call (see apiClientProvider).
  void handleFailure(AppFailure failure, {required bool hadToken}) {
    // start() deals with its own failures.
    if (state is SessionStarting) {
      return;
    }

    switch (failure) {
      case UnauthorizedFailure() when hadToken && state is SessionSignedIn:
        unawaited(_expire());
      case MaintenanceFailure(:final message):
        state = SessionMaintenance(message);
      case UpdateRequiredFailure(:final message, :final storeUrl):
        state = SessionUpdateRequired(message: message, storeUrl: storeUrl);
      default:
        break;
    }
  }

  Future<void> _expire() async {
    // Set first so a second 401 arriving meanwhile finds the person already signed out.
    state = const SessionSignedOut(reason: SignedOutReason.sessionExpired);
    await ref.read(tokenStorageProvider).clear();
  }
}
