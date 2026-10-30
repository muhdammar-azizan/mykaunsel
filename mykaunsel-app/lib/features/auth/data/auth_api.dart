import '../../../core/network/api_client.dart';
import '../domain/app_user.dart';
import '../domain/sign_in_result.dart';

/// The account calls (see docs/API.md on the server side). Every method throws only an [AppFailure].
abstract class AuthApi {
  /// Email + password. Throws [ValidationFailure] (wrong details), [EmailNotVerifiedFailure], [RateLimitedFailure].
  Future<SignInResult> signIn({required String email, required String password, required String deviceName});

  /// The second step: [challenge] from [signIn] plus either the 6-digit [code] or a [recoveryCode].
  Future<SignedInResult> twoFactor({required String challenge, String? code, String? recoveryCode, required String deviceName});

  /// Creates an individual account. No sign-in yet: a confirmation email is sent first. Throws [ValidationFailure]
  /// (email taken, weak password…) or [ForbiddenFailure] when sign-up is closed (the message is the Platform Admin's).
  /// [aiConsent] is the person's agreement to AI processing (the sign-up form will not send without it; the server refuses without it too).
  Future<void> register({required String name, required String email, required String password, required bool aiConsent});

  /// Sends the confirmation email again. The server answers the same for every address.
  Future<void> resendVerification(String email);

  /// Emails a link to choose a new password (the page itself opens in the browser). The server answers the same for
  /// every address, so nobody can find out who has an account.
  Future<void> forgotPassword(String email);

  /// Who the saved token belongs to. Throws [UnauthorizedFailure] when the token no longer works.
  Future<AppUser> me();

  /// Signs this device out on the server (the token stops working).
  Future<void> logout();

  /// Signs out EVERY device (lost phone, or a password worry).
  Future<void> logoutAll();
}

class HttpAuthApi implements AuthApi {
  HttpAuthApi(this._client);

  final ApiClient _client;

  @override
  Future<SignInResult> signIn({required String email, required String password, required String deviceName}) async {
    final response = await _client.post('/auth/login', data: {'email': email, 'password': password, 'device_name': deviceName});

    // 202 = correct password, but a two-step code is needed before anything is signed in.
    if (response.statusCode == 202 && response.data['code'] == 'two_factor_required') {
      return TwoFactorRequired(response.data['challenge'] as String);
    }

    return _signedIn(response);
  }

  @override
  Future<SignedInResult> twoFactor({required String challenge, String? code, String? recoveryCode, required String deviceName}) async {
    final response = await _client.post('/auth/two-factor', data: {
      'challenge': challenge,
      'code': ?code,
      'recovery_code': ?recoveryCode,
      'device_name': deviceName,
    });

    return _signedIn(response);
  }

  @override
  Future<void> register({required String name, required String email, required String password, required bool aiConsent}) async {
    await _client.post('/auth/register', data: {
      'ai_consent': aiConsent,
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': password,
    });
  }

  @override
  Future<void> forgotPassword(String email) async {
    await _client.post('/auth/forgot-password', data: {'email': email});
  }

  @override
  Future<void> resendVerification(String email) async {
    await _client.post('/auth/email/resend', data: {'email': email});
  }

  @override
  Future<AppUser> me() async {
    final response = await _client.get('/auth/me');

    return AppUser.fromJson(Map<String, dynamic>.from(response.data['user'] as Map));
  }

  @override
  Future<void> logout() async {
    await _client.post('/auth/logout');
  }

  @override
  Future<void> logoutAll() async {
    await _client.post('/auth/logout-all');
  }

  SignedInResult _signedIn(ApiResponse response) {
    return SignedInResult(
      token: response.data['token'] as String,
      user: AppUser.fromJson(Map<String, dynamic>.from(response.data['user'] as Map)),
    );
  }
}
