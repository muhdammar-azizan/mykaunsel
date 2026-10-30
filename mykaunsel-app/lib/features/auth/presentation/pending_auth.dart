import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Something one account screen hands to the next: the two-step challenge after the password, or the email address
/// that still has to be confirmed. Kept here (not in the address bar) so it never appears in a link.
class PendingValue extends Notifier<String?> {
  @override
  String? build() => null;

  void set(String? value) => state = value;
}

/// The challenge from "password correct, now the code" (works for 10 minutes on the server).
final pendingTwoFactorProvider = NotifierProvider<PendingValue, String?>(PendingValue.new);

/// Where to go after signing in again because the session ended on a booking screen. An in-app route only, kept in memory (never on disk, never
/// in a link). Set and read by the router; see [isSafeReturnPath].
final returnPathProvider = NotifierProvider<PendingValue, String?>(PendingValue.new);

/// A return destination is accepted only if it is exactly one of the booking routes with plain ids: no scheme, no host, no `..`, no other query.
/// So it cannot be an external address (no open redirect) and cannot carry anything but the two ids.
bool isSafeReturnPath(String? path) =>
    path != null && path.length <= 200 && RegExp(r'^/book/[A-Za-z0-9_-]{1,64}(/confirm\?slot=[A-Za-z0-9_-]{1,64})?$').hasMatch(path);

/// The address that is waiting to be confirmed.
final pendingVerificationEmailProvider = NotifierProvider<PendingValue, String?>(PendingValue.new);
