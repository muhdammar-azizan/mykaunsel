import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/features/platform_status/domain/platform_status.dart';

import '../support/fakes.dart';

Future<void> open(
  WidgetTester tester, {
  FakeAuthApi? authApi,
  FakeTokenStorage? storage,
  FakePlatformStatusApi? statusApi,
  double textScale = 1,
}) async {
  tester.view.physicalSize = const Size(800, 1600);
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(authApi: authApi, storage: storage, statusApi: statusApi),
      child: MediaQuery(
        data: MediaQueryData(size: const Size(400, 800), textScaler: TextScaler.linear(textScale)),
        child: const MyKaunselApp(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

/// Taps a link that may be below the visible part of the page (large text): the person would scroll to it.
Future<void> tapLink(WidgetTester tester, String text) async {
  await tester.ensureVisible(find.text(text));
  await tester.pumpAndSettle();
  await tester.tap(find.text(text));
  await tester.pumpAndSettle();
}

Future<void> goToSignUp(WidgetTester tester) => tapLink(tester, 'Create an account');

Future<void> fillSignUp(
  WidgetTester tester, {
  String name = ' Siti Aminah ',
  String email = 'Siti@Example.com ',
  String password = 'Str0ng-Pass-123',
  String? confirm,
  bool agree = true,
}) async {
  await tester.enterText(find.widgetWithText(TextFormField, 'Full name'), name);
  await tester.enterText(find.widgetWithText(TextFormField, 'Email'), email);
  await tester.enterText(find.widgetWithText(TextFormField, 'Password'), password);
  await tester.enterText(find.widgetWithText(TextFormField, 'Confirm password'), confirm ?? password);

  if (agree) {
    await tester.ensureVisible(find.byType(CheckboxListTile));
    await tester.tap(find.byType(CheckboxListTile));
    await tester.pump();
  }
}

Future<void> tapCreate(WidgetTester tester) async {
  final button = find.widgetWithText(FilledButton, 'Create account');
  await tester.ensureVisible(button);
  await tester.tap(button);
  await tester.pumpAndSettle();
}

FakePlatformStatusApi signUpStatus({required bool open, String? message}) {
  return FakePlatformStatusApi(() async => status().copyWith(registration: {'individual': RegistrationStatus(open: open, message: message)}));
}

void main() {
  group('sign up', () {
    testWidgets('is reached from sign-in, and the back button returns there', (tester) async {
      await open(tester);

      await goToSignUp(tester);

      expect(find.text('Create your account'), findsOneWidget);

      await tester.pageBack();
      await tester.pumpAndSettle();

      expect(find.text('Welcome back'), findsOneWidget);
    });

    testWidgets('a valid form creates the account, tidies name and email, and goes to "check your email"', (tester) async {
      final api = FakeAuthApi();
      await open(tester, authApi: api);
      await goToSignUp(tester);

      await fillSignUp(tester);
      await tapCreate(tester);

      expect(api.registerCalls.single, (name: 'Siti Aminah', email: 'siti@example.com', password: 'Str0ng-Pass-123'));
      expect(find.text('Confirm your email'), findsOneWidget);
      expect(find.textContaining('siti@example.com'), findsOneWidget);
    });

    testWidgets('the agreement to AI processing is asked for, sent with the account, and needed to go on', (tester) async {
      final api = FakeAuthApi();
      await open(tester, authApi: api);
      await goToSignUp(tester);

      await fillSignUp(tester, agree: false);
      await tapCreate(tester);

      expect(find.text('Please agree to continue.'), findsOneWidget);
      expect(api.registerCalls, isEmpty);

      await tester.tap(find.byType(CheckboxListTile));
      await tester.pump();
      await tapCreate(tester);

      expect(api.registerConsents, [true]);
      expect(find.text('Confirm your email'), findsOneWidget);
    });

    testWidgets('"What this means" explains it and saves nothing', (tester) async {
      final api = FakeAuthApi();
      await open(tester, authApi: api);
      await goToSignUp(tester);

      await tester.ensureVisible(find.text('What this means'));
      await tester.tap(find.text('What this means'));
      await tester.pumpAndSettle();

      expect(find.textContaining('It is an AI, not a counselor or a doctor'), findsOneWidget);
      expect(find.text('I agree'), findsNothing);

      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      expect(find.text('Create your account'), findsOneWidget);
      expect(api.registerCalls, isEmpty);
    });

    testWidgets('nothing is sent while the form has problems, and each says what to fix', (tester) async {
      final api = FakeAuthApi();
      await open(tester, authApi: api);
      await goToSignUp(tester);

      await fillSignUp(tester, name: '', email: 'not-an-email', password: 'weak', confirm: 'different');
      await tapCreate(tester);

      expect(find.text('Enter your name.'), findsOneWidget);
      expect(find.text('Enter a valid email address.'), findsOneWidget);
      expect(find.text('The two passwords are not the same.'), findsOneWidget);
      expect(find.text('Your password needs:'), findsWidgets);
      expect(api.registerCalls, isEmpty);
    });

    testWidgets('the checklist ticks off as the password is typed', (tester) async {
      await open(tester);
      await goToSignUp(tester);

      Finder rule(String text, {required bool met}) => find.bySemanticsLabel(met ? '$text, done' : '$text, not yet');

      expect(rule('At least 8 characters', met: false), findsOneWidget);

      await tester.enterText(find.widgetWithText(TextFormField, 'Password'), 'abcdefgh');
      await tester.pump();

      expect(rule('At least 8 characters', met: true), findsOneWidget);
      expect(rule('A lower-case letter', met: true), findsOneWidget);
      expect(rule('An upper-case letter', met: false), findsOneWidget);

      await tester.enterText(find.widgetWithText(TextFormField, 'Password'), 'Str0ng-Pass-123');
      await tester.pump();

      for (final text in ['At least 8 characters', 'An upper-case letter', 'A lower-case letter', 'A number', 'A symbol, such as ! or #']) {
        expect(rule(text, met: true), findsOneWidget, reason: text);
      }
    });

    testWidgets('an email that is already taken is shown under the email field', (tester) async {
      final api = FakeAuthApi()
        ..registerHandler = ((_, _, _) async => throw const ValidationFailure('The email has already been taken.', fieldErrors: {'email': ['The email has already been taken.']}));
      await open(tester, authApi: api);
      await goToSignUp(tester);

      await fillSignUp(tester);
      await tapCreate(tester);

      expect(find.text('The email has already been taken.'), findsOneWidget);
      expect(find.text('Create your account'), findsOneWidget);
    });

    testWidgets('if the server says sign-up closed while sending, its message is shown', (tester) async {
      final api = FakeAuthApi()..registerHandler = ((_, _, _) async => throw const ForbiddenFailure('We are not accepting new individual sign-ups right now.'));
      await open(tester, authApi: api);
      await goToSignUp(tester);

      await fillSignUp(tester);
      await tapCreate(tester);

      expect(find.text('We are not accepting new individual sign-ups right now.'), findsOneWidget);
    });

    testWidgets('no connection while sending keeps what was typed so the person can try again', (tester) async {
      final api = FakeAuthApi()..registerHandler = ((_, _, _) async => throw const NetworkFailure());
      await open(tester, authApi: api);
      await goToSignUp(tester);

      await fillSignUp(tester);
      await tapCreate(tester);

      expect(find.text('We could not reach MyKaunsel. Check your internet connection and try again.'), findsOneWidget);
      expect(find.text('Siti@Example.com '), findsOneWidget);

      api.registerHandler = (_, _, _) async {};
      await tapCreate(tester);

      expect(find.text('Confirm your email'), findsOneWidget);
    });
  });

  group('sign-up closed by the Platform Admin', () {
    testWidgets('shows the admin\'s own message instead of the form', (tester) async {
      await open(tester, statusApi: signUpStatus(open: false, message: 'Sign-ups reopen on 1 January.'));
      await goToSignUp(tester);

      expect(find.text('Sign-up is closed for now'), findsOneWidget);
      expect(find.text('Sign-ups reopen on 1 January.'), findsOneWidget);
      expect(find.widgetWithText(TextFormField, 'Full name'), findsNothing);

      await tester.tap(find.text('Back to sign in'));
      await tester.pumpAndSettle();

      expect(find.text('Welcome back'), findsOneWidget);
    });

    testWidgets('the app asks again each time the page opens, so reopening works without restarting', (tester) async {
      final statusApi = signUpStatus(open: false, message: 'Closed.');
      await open(tester, statusApi: statusApi);
      await goToSignUp(tester);
      expect(find.text('Sign-up is closed for now'), findsOneWidget);

      statusApi.handler = () async => status();
      await tester.tap(find.text('Back to sign in'));
      await tester.pumpAndSettle();
      await goToSignUp(tester);

      expect(find.text('Create your account'), findsOneWidget);
    });

    testWidgets('a failure to find out offers "try again"', (tester) async {
      final statusApi = FakePlatformStatusApi(() async => status());
      await open(tester, statusApi: statusApi);

      statusApi.handler = () async => throw const NetworkFailure();
      await goToSignUp(tester);

      expect(find.text('Something went wrong'), findsOneWidget);

      statusApi.handler = () async => status();
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(find.text('Create your account'), findsOneWidget);
    });
  });

  group('forgot password', () {
    Future<void> goToForgot(WidgetTester tester) => tapLink(tester, 'Forgot your password?');

    testWidgets('asks for the email, sends it tidied up, and shows the same page for every address', (tester) async {
      final api = FakeAuthApi();
      await open(tester, authApi: api);
      await goToForgot(tester);

      await tester.enterText(find.widgetWithText(TextFormField, 'Email'), ' Siti@Example.com ');
      await tester.tap(find.widgetWithText(FilledButton, 'Send the link'));
      await tester.pumpAndSettle();

      expect(api.forgotPasswordCalls, ['siti@example.com']);
      expect(find.text('Check your email'), findsOneWidget);
      expect(find.textContaining('If siti@example.com has an account'), findsOneWidget);
    });

    testWidgets('an empty email is caught before sending', (tester) async {
      final api = FakeAuthApi();
      await open(tester, authApi: api);
      await goToForgot(tester);

      await tester.tap(find.widgetWithText(FilledButton, 'Send the link'));
      await tester.pump();

      expect(find.text('Enter your email address.'), findsOneWidget);
      expect(api.forgotPasswordCalls, isEmpty);
    });

    testWidgets('"use a different email" brings the form back', (tester) async {
      await open(tester);
      await goToForgot(tester);
      await tester.enterText(find.widgetWithText(TextFormField, 'Email'), 'a@b.com');
      await tester.tap(find.widgetWithText(FilledButton, 'Send the link'));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Use a different email'));
      await tester.pumpAndSettle();

      expect(find.text('Forgot your password?'), findsWidgets);
      expect(find.widgetWithText(FilledButton, 'Send the link'), findsOneWidget);
    });

    testWidgets('too many requests says how long to wait', (tester) async {
      final api = FakeAuthApi()..forgotPasswordHandler = ((_) async => throw const RateLimitedFailure(retryAfterSeconds: 300));
      await open(tester, authApi: api);
      await goToForgot(tester);

      await tester.enterText(find.widgetWithText(TextFormField, 'Email'), 'a@b.com');
      await tester.tap(find.widgetWithText(FilledButton, 'Send the link'));
      await tester.pumpAndSettle();

      expect(find.text('Too many attempts. Please try again in 300 seconds.'), findsOneWidget);
    });
  });

  group('home and account', () {
    Future<void> openAccountTab(WidgetTester tester) async {
      await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('Account')));
      await tester.pumpAndSettle();
    }

    testWidgets('home greets by first name and offers the way to a counselor', (tester) async {
      await open(tester, storage: FakeTokenStorage('5|mkp_abc'));

      expect(find.text('Hello, Siti'), findsOneWidget);
      expect(find.text('Signed in as Siti Aminah'), findsOneWidget);
      expect(find.text('Find a counselor'), findsOneWidget);
    });

    testWidgets('the account tab shows who is signed in', (tester) async {
      await open(tester, storage: FakeTokenStorage('5|mkp_abc'));

      await openAccountTab(tester);

      expect(find.text('Siti Aminah'), findsOneWidget);
      expect(find.text('siti@example.com'), findsOneWidget);
      expect(find.text('SA'), findsOneWidget);
    });

    testWidgets('sign out from the account tab', (tester) async {
      final api = FakeAuthApi();
      final storage = FakeTokenStorage('5|mkp_abc');
      await open(tester, storage: storage, authApi: api);

      await openAccountTab(tester);
      await tester.tap(find.text('Sign out'));
      await tester.pumpAndSettle();

      expect(api.logoutCalls, 1);
      expect(storage.token, isNull);
      expect(find.text('Welcome back'), findsOneWidget);
    });

    testWidgets('sign out of all devices asks first, and "cancel" changes nothing', (tester) async {
      final api = FakeAuthApi();
      final storage = FakeTokenStorage('5|mkp_abc');
      await open(tester, storage: storage, authApi: api);

      await openAccountTab(tester);
      await tester.tap(find.text('Sign out of all devices'));
      await tester.pumpAndSettle();

      expect(find.text('Sign out of all devices?'), findsOneWidget);

      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();

      expect(api.logoutAllCalls, 0);
      expect(find.text('Siti Aminah'), findsOneWidget);

      await tester.tap(find.text('Sign out of all devices'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Sign out of all devices'));
      await tester.pumpAndSettle();

      expect(api.logoutAllCalls, 1);
      expect(storage.token, isNull);
      expect(find.text('Welcome back'), findsOneWidget);
    });
  });

  group('large text and small screens', () {
    for (final scale in [1.0, 2.0]) {
      testWidgets('sign-in, sign-up and forgot password do not overflow at text size ×$scale', (tester) async {
        tester.view.physicalSize = const Size(640, 1136);
        tester.view.devicePixelRatio = 2;
        addTearDown(tester.view.reset);

        await open(tester, textScale: scale);
        expect(tester.takeException(), isNull);

        await goToSignUp(tester);
        expect(tester.takeException(), isNull);
        await tester.pageBack();
        await tester.pumpAndSettle();

        await tapLink(tester, 'Forgot your password?');
        expect(tester.takeException(), isNull);
      });
    }
  });
}
