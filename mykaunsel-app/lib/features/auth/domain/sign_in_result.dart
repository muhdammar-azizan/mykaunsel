import 'app_user.dart';

/// What the server answered to a correct email + password.
sealed class SignInResult {
  const SignInResult();
}

/// Signed in: this token is the person's key on this phone.
class SignedInResult extends SignInResult {
  const SignedInResult({required this.token, required this.user});

  final String token;
  final AppUser user;
}

/// The account has two-step verification: nothing is signed in yet. The [challenge] must come back with the code
/// (it works for 10 minutes).
class TwoFactorRequired extends SignInResult {
  const TwoFactorRequired(this.challenge);

  final String challenge;
}
