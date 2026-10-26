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

/// The address that is waiting to be confirmed.
final pendingVerificationEmailProvider = NotifierProvider<PendingValue, String?>(PendingValue.new);
