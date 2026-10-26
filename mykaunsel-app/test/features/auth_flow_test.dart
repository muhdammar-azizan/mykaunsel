import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/features/auth/domain/sign_in_result.dart';

import '../support/fakes.dart';

Future<void> open(WidgetTester tester, {FakeAuthApi? authApi, FakeTokenStorage? storage}) async {
  tester.view.physicalSize = const Size(800, 1600);
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(ProviderScope(overrides: testOverrides(authApi: authApi, storage: storage), child: const MyKaunselApp()));
  await tester.pumpAndSettle();
}

Future<void> fillSignIn(WidgetTester tester, {String email = 'Siti@Example.com ', String password = 'Str0ng-Pass-123'}) async {
  await tester.enterText(find.widgetWithText(TextFormField, 'Email'), email);
  await tester.enterText(find.widgetWithText(TextFormField, 'Password'), password);
}

Future<void> tapSignIn(WidgetTester tester) async {
  await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
  await tester.pumpAndSettle();
}

void main() {
  group('sign in', () {
    testWidgets('a signed-out person lands on the sign-in form', (tester) async {
      await open(tester);

      expect(find.text('Welcome back'), findsOneWidget);
      expect(find.widgetWithText(TextFormField, 'Email'), findsOneWidget);
      expect(find.widgetWithText(FilledButton, 'Sign in'), findsOneWidget);
    });

    testWidgets('empty fields are caught before anything is sent', (tester) async {
      final api = FakeAuthApi();
      await open(tester, authApi: api);

      await tapSignIn(tester);

      expect(find.text('Enter your email address.'), findsOneWidget);
      expect(find.text('Enter your password.'), findsOneWidget);
      expect(api.signInCalls, isEmpty);
    });

    testWidgets('correct details sign in (email tidied up, device name sent) and open the home screen', (tester) async {
      final api = FakeAuthApi();
      final storage = FakeTokenStorage();
      await open(tester, authApi: api, storage: storage);

      await fillSignIn(tester);
      await tapSignIn(tester);

      expect(api.signInCalls.single.email, 'siti@example.com');
      expect(api.signInCalls.single.deviceName, 'Phone');
      expect(storage.token, '9|mkp_new');
      expect(find.text('Signed in as Siti Aminah'), findsOneWidget);
    });

    testWidgets('wrong details show the server\'s sentence and keep the form', (tester) async {
      final api = FakeAuthApi()
        ..signInHandler = (_, _) async => throw const ValidationFailure(
              'These credentials do not match our records.',
              fieldErrors: {'email': ['These credentials do not match our records.']},
            );
      await open(tester, authApi: api);

      await fillSignIn(tester);
      await tapSignIn(tester);

      expect(find.text('These credentials do not match our records.'), findsOneWidget); // under the field, not twice
      expect(find.widgetWithText(FilledButton, 'Sign in'), findsOneWidget);
    });

    testWidgets('too many attempts says how long to wait', (tester) async {
      final api = FakeAuthApi()..signInHandler = (_, _) async => throw const RateLimitedFailure(retryAfterSeconds: 45);
      await open(tester, authApi: api);

      await fillSignIn(tester);
      await tapSignIn(tester);

      expect(find.text('Too many attempts. Please try again in 45 seconds.'), findsOneWidget);
    });

    testWidgets('no connection is explained, and the person can try again', (tester) async {
      final api = FakeAuthApi()..signInHandler = (_, _) async => throw const NetworkFailure();
      await open(tester, authApi: api);

      await fillSignIn(tester);
      await tapSignIn(tester);

      expect(find.text('We could not reach MyKaunsel. Check your internet connection and try again.'), findsOneWidget);

      api.signInHandler = (_, _) async => const SignedInResult(token: 't', user: testUser);
      await tapSignIn(tester);

      expect(find.text('Signed in as Siti Aminah'), findsOneWidget);
    });

    testWidgets('the button waits while the server answers, so it cannot be sent twice', (tester) async {
      final api = FakeAuthApi();
      final gate = Future<void>.delayed(const Duration(seconds: 1));
      api.signInHandler = (_, _) async {
        await gate;

        return const SignedInResult(token: 't', user: testUser);
      };
      await open(tester, authApi: api);

      await fillSignIn(tester);
      await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
      await tester.pump();

      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      await tester.pump(const Duration(seconds: 2));
      await tester.pumpAndSettle();

      expect(api.signInCalls, hasLength(1));
    });

    testWidgets('the password can be shown and hidden', (tester) async {
      await open(tester);

      EditableText field() => tester.widget<EditableText>(find.descendant(of: find.widgetWithText(TextFormField, 'Password'), matching: find.byType(EditableText)));

      expect(field().obscureText, isTrue);
      await tester.tap(find.byTooltip('Show password'));
      await tester.pump();
      expect(field().obscureText, isFalse);
    });

    testWidgets('an expired session explains why the person is back here', (tester) async {
      await open(tester, storage: FakeTokenStorage('old'), authApi: FakeAuthApi(me: () async => throw const UnauthorizedFailure()));

      expect(find.text('Your session has ended. Please sign in again.'), findsOneWidget);
    });
  });

  group('email not confirmed', () {
    testWidgets('goes to "check your email" with the address, and can resend with a wait', (tester) async {
      final api = FakeAuthApi()..signInHandler = (_, _) async => throw const EmailNotVerifiedFailure('Confirm your email address first.');
      await open(tester, authApi: api);

      await fillSignIn(tester);
      await tapSignIn(tester);

      expect(find.text('Confirm your email'), findsOneWidget);
      expect(find.textContaining('siti@example.com'), findsOneWidget);

      await tester.tap(find.text('Send the email again'));
      await tester.pump();
      await tester.pump();

      expect(api.resendCalls, ['siti@example.com']);
      expect(find.text('Send again in 60s'), findsOneWidget);
      expect(find.text('If that address still needs confirming, we have sent the email again.'), findsOneWidget);

      await tester.pump(const Duration(seconds: 10));
      expect(find.text('Send again in 50s'), findsOneWidget);

      await tester.pump(const Duration(seconds: 50));
      expect(find.text('Send the email again'), findsOneWidget);
    });

    testWidgets('"back to sign in" returns to the form', (tester) async {
      final api = FakeAuthApi()..signInHandler = (_, _) async => throw const EmailNotVerifiedFailure('');
      await open(tester, authApi: api);

      await fillSignIn(tester);
      await tapSignIn(tester);
      await tester.tap(find.text('Back to sign in'));
      await tester.pumpAndSettle();

      expect(find.text('Welcome back'), findsOneWidget);
    });

    testWidgets('when the server says wait, the resend button waits as told', (tester) async {
      final api = FakeAuthApi()
        ..signInHandler = ((_, _) async => throw const EmailNotVerifiedFailure(''))
        ..resendHandler = ((_) async => throw const RateLimitedFailure(retryAfterSeconds: 120));
      await open(tester, authApi: api);

      await fillSignIn(tester);
      await tapSignIn(tester);
      await tester.tap(find.text('Send the email again'));
      await tester.pump();
      await tester.pump();

      expect(find.text('Send again in 120s'), findsOneWidget);
      expect(find.text('Too many attempts. Please try again in 120 seconds.'), findsOneWidget);
    });
  });

  group('two-step code', () {
    FakeAuthApi withTwoFactor() => FakeAuthApi()..signInHandler = (_, _) async => const TwoFactorRequired('chal-123');

    testWidgets('after the password, the code is asked for and a correct one signs in', (tester) async {
      final api = withTwoFactor();
      await open(tester, authApi: api);

      await fillSignIn(tester);
      await tapSignIn(tester);

      expect(find.text('Two-step verification'), findsOneWidget);

      await tester.enterText(find.widgetWithText(TextFormField, '6-digit code'), '123456');
      await tester.tap(find.widgetWithText(FilledButton, 'Verify'));
      await tester.pumpAndSettle();

      expect(api.twoFactorCalls.single, (challenge: 'chal-123', code: '123456', recoveryCode: null));
      expect(find.text('Signed in as Siti Aminah'), findsOneWidget);
    });

    testWidgets('a code that is not 6 digits is caught before sending', (tester) async {
      final api = withTwoFactor();
      await open(tester, authApi: api);
      await fillSignIn(tester);
      await tapSignIn(tester);

      await tester.enterText(find.widgetWithText(TextFormField, '6-digit code'), '12ab');
      await tester.tap(find.widgetWithText(FilledButton, 'Verify'));
      await tester.pump();

      expect(find.text('Enter the 6-digit code.'), findsOneWidget);
      expect(api.twoFactorCalls, isEmpty);
    });

    testWidgets('a wrong code shows the server\'s message and stays on the page', (tester) async {
      final api = withTwoFactor()
        ..twoFactorHandler = (_, _, _) async => throw const ValidationFailure('That code is not correct.', fieldErrors: {'code': ['That code is not correct.']});
      await open(tester, authApi: api);
      await fillSignIn(tester);
      await tapSignIn(tester);

      await tester.enterText(find.widgetWithText(TextFormField, '6-digit code'), '000000');
      await tester.tap(find.widgetWithText(FilledButton, 'Verify'));
      await tester.pumpAndSettle();

      expect(find.text('That code is not correct.'), findsOneWidget);
      expect(find.text('Two-step verification'), findsOneWidget);
    });

    testWidgets('a recovery code can be used instead', (tester) async {
      final api = withTwoFactor();
      await open(tester, authApi: api);
      await fillSignIn(tester);
      await tapSignIn(tester);

      await tester.tap(find.text('Use a recovery code instead'));
      await tester.pump();
      await tester.enterText(find.widgetWithText(TextFormField, 'Recovery code'), 'abcde-fghij');
      await tester.tap(find.widgetWithText(FilledButton, 'Verify'));
      await tester.pumpAndSettle();

      expect(api.twoFactorCalls.single, (challenge: 'chal-123', code: null, recoveryCode: 'abcde-fghij'));
      expect(find.text('Signed in as Siti Aminah'), findsOneWidget);
    });

    testWidgets('a challenge the server no longer accepts sends the person back to sign in', (tester) async {
      final api = withTwoFactor()
        ..twoFactorHandler = (_, _, _) async => throw const ValidationFailure('Your sign-in expired.', fieldErrors: {'challenge': ['Your sign-in expired.']});
      await open(tester, authApi: api);
      await fillSignIn(tester);
      await tapSignIn(tester);

      await tester.enterText(find.widgetWithText(TextFormField, '6-digit code'), '123456');
      await tester.tap(find.widgetWithText(FilledButton, 'Verify'));
      await tester.pumpAndSettle();

      expect(find.text('Welcome back'), findsOneWidget);
      expect(find.text('Your sign-in took too long. Please sign in again.'), findsOneWidget);
    });

    testWidgets('"back to sign in" drops the challenge', (tester) async {
      final api = withTwoFactor();
      await open(tester, authApi: api);
      await fillSignIn(tester);
      await tapSignIn(tester);

      await tester.tap(find.text('Back to sign in'));
      await tester.pumpAndSettle();

      expect(find.text('Welcome back'), findsOneWidget);
    });
  });
}
