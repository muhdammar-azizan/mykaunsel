import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/config/app_config.dart';
import 'package:mykaunsel/core/config/app_info.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/network/api_client.dart';
import 'package:mykaunsel/features/auth/data/auth_api.dart';
import 'package:mykaunsel/features/auth/domain/sign_in_result.dart';

import '../support/fakes.dart';

/// The real HttpAuthApi against answers shaped exactly like the Laravel server's (docs/API.md).
void main() {
  HttpAuthApi build(FakeHttpAdapter adapter) {
    final client = ApiClient(
      config: const AppConfig(flavor: Flavor.dev, apiBaseUrl: 'http://test.local'),
      info: const AppInfo(version: '1.0.0', platform: 'android'),
      readToken: () async => null,
      onFailure: (_, {required hadToken}) {},
      dio: Dio()..httpClientAdapter = adapter,
    );

    return HttpAuthApi(client);
  }

  const user = {'id': 7, 'name': 'Siti Aminah', 'email': 'siti@example.com', 'email_verified': true, 'avatar_url': null, 'timezone': 'Asia/Kuala_Lumpur'};

  test('a correct sign-in gives the token and the person, and sends the device name', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'token': '3|mkp_abc', 'token_type': 'Bearer', 'user': user}));

    final result = await build(adapter).signIn(email: 'siti@example.com', password: 'Str0ng-Pass-123', deviceName: 'Google Pixel 8');

    expect(result, isA<SignedInResult>());
    expect((result as SignedInResult).token, '3|mkp_abc');
    expect(result.user.name, 'Siti Aminah');
    expect(adapter.requests.single.path, '/auth/login');
    expect(adapter.requests.single.data, {'email': 'siti@example.com', 'password': 'Str0ng-Pass-123', 'device_name': 'Google Pixel 8'});
  });

  test('a 202 means the code step: a challenge and no token', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(202, {'message': 'Enter the code.', 'code': 'two_factor_required', 'challenge': 'chal-123', 'expires_in': 600}));

    final result = await build(adapter).signIn(email: 'a@b.com', password: 'x', deviceName: 'p');

    expect(result, isA<TwoFactorRequired>());
    expect((result as TwoFactorRequired).challenge, 'chal-123');
  });

  test('wrong details are a ValidationFailure with the server\'s sentence on the email field', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(422, {'message': 'These credentials do not match our records.', 'errors': {'email': ['These credentials do not match our records.']}}));

    await expectLater(
      build(adapter).signIn(email: 'a@b.com', password: 'x', deviceName: 'p'),
      throwsA(isA<ValidationFailure>().having((f) => f.errorFor('email'), 'email error', 'These credentials do not match our records.')),
    );
  });

  test('an unconfirmed email is its own failure', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(403, {'message': 'Confirm your email address first.', 'code': 'email_not_verified'}));

    await expectLater(build(adapter).signIn(email: 'a@b.com', password: 'x', deviceName: 'p'), throwsA(isA<EmailNotVerifiedFailure>()));
  });

  test('the second step sends the challenge with the code, or with the recovery code, never both', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'token': '4|mkp_def', 'token_type': 'Bearer', 'user': user}));
    final api = build(adapter);

    await api.twoFactor(challenge: 'c', code: '123456', deviceName: 'p');
    await api.twoFactor(challenge: 'c', recoveryCode: 'abcde-fghij', deviceName: 'p');

    expect(adapter.requests[0].data, {'challenge': 'c', 'code': '123456', 'device_name': 'p'});
    expect(adapter.requests[1].data, {'challenge': 'c', 'recovery_code': 'abcde-fghij', 'device_name': 'p'});
  });

  test('resending the confirmation posts the address', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(202, {'message': 'If that address needs confirming, we have sent the email again.'}));

    await build(adapter).resendVerification('siti@example.com');

    expect(adapter.requests.single.path, '/auth/email/resend');
    expect(adapter.requests.single.data, {'email': 'siti@example.com'});
  });

  test('me reads the person from the user key', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'user': user}));

    expect((await build(adapter).me()).email, 'siti@example.com');
  });
}
