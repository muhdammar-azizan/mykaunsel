import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/features/platform_status/domain/platform_status.dart';

import '../support/fakes.dart';

Future<void> openChooser(
  WidgetTester tester, {
  FakeAuthApi? auth,
  Map<String, RegistrationStatus>? registration,
  Size size = const Size(400, 900),
  double textScale = 1,
}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(
        authApi: auth,
        statusApi: FakePlatformStatusApi(() async => status().copyWith(registration: registration ?? const {})),
      ),
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();
  await tester.ensureVisible(find.text('Create an account'));
  await tester.tap(find.text('Create an account'));
  await tester.pumpAndSettle();
}

void main() {
  group('the choice', () {
    testWidgets('three kinds, each with a line saying who it is for', (tester) async {
      await openChooser(tester);

      expect(find.text('Who is signing up?'), findsOneWidget);
      expect(find.text('Individual'), findsOneWidget);
      expect(find.text('Talk to a counselor, book sessions and use the chat.'), findsOneWidget);
      expect(find.text('Organization'), findsOneWidget);
      expect(find.text('Register a clinic, university or company.'), findsOneWidget);
      expect(find.text('Counselor'), findsOneWidget);
      expect(find.text('Join MyKaunsel as a registered counselor.'), findsOneWidget);
      expect(find.text('Closed for now'), findsNothing);
    });

    testWidgets('Individual goes straight to the form', (tester) async {
      await openChooser(tester);

      await tester.tap(find.text('Individual'));
      await tester.pumpAndSettle();

      expect(find.widgetWithText(TextFormField, 'Full name'), findsOneWidget);
    });

    testWidgets('a screen reader hears each card as one item with its words', (tester) async {
      final handle = tester.ensureSemantics();
      await openChooser(tester, registration: {'counselor': const RegistrationStatus(open: false, message: 'Closed.')});

      expect(find.bySemanticsLabel('Organization, Register a clinic, university or company.'), findsOneWidget);
      expect(find.bySemanticsLabel('Counselor, Join MyKaunsel as a registered counselor., Closed for now'), findsOneWidget);
      handle.dispose();
    });
  });

  group('Organization and Counselor', () {
    testWidgets('a sheet explains it is done on a computer and asks for the email, with words for the kind', (tester) async {
      await openChooser(tester);

      await tester.tap(find.text('Organization'));
      await tester.pumpAndSettle();
      expect(find.text('Sign up on a computer'), findsOneWidget);
      expect(find.textContaining('Organizations register on a computer'), findsOneWidget);

      await tester.tapAt(const Offset(10, 10));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Counselor'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Counselors sign up on a computer, with their documents'), findsOneWidget);
    });

    testWidgets('sends the kind and the tidied email once, and says the same thing whoever it is', (tester) async {
      final auth = FakeAuthApi();
      await openChooser(tester, auth: auth);
      await tester.tap(find.text('Organization'));
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextFormField), ' Admin@Klinik.Example ');
      await tester.tap(find.text('Send me the link'));
      await tester.pumpAndSettle();

      expect(auth.signupLinks, [(type: 'organization', email: 'admin@klinik.example')]);
      expect(find.text('Check your email'), findsOneWidget);
      expect(find.text('If that address can receive email, the link is on its way. Open it on a computer to continue.'), findsOneWidget);

      await tester.tap(find.text('Done'));
      await tester.pumpAndSettle();
      expect(find.text('Check your email'), findsNothing);
    });

    testWidgets('the counselor kind is sent as such', (tester) async {
      final auth = FakeAuthApi();
      await openChooser(tester, auth: auth);
      await tester.tap(find.text('Counselor'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField), 'aina@example.com');
      await tester.tap(find.text('Send me the link'));
      await tester.pumpAndSettle();

      expect(auth.signupLinks.single.type, 'counselor');
    });

    testWidgets('nothing is sent for an empty or odd email, and it says what to fix', (tester) async {
      final auth = FakeAuthApi();
      await openChooser(tester, auth: auth);
      await tester.tap(find.text('Organization'));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Send me the link'));
      await tester.pumpAndSettle();
      expect(find.text('Enter your email address.'), findsOneWidget);

      await tester.enterText(find.byType(TextFormField), 'not-an-email');
      await tester.tap(find.text('Send me the link'));
      await tester.pumpAndSettle();
      expect(find.text('Enter a valid email address.'), findsOneWidget);
      expect(auth.signupLinks, isEmpty);
    });

    testWidgets('while it works the button says so and a second tap sends nothing more', (tester) async {
      final auth = FakeAuthApi();
      final pending = <Future<void>>[];
      auth.signupLinkHandler = (type, email) {
        final done = Future<void>.delayed(const Duration(seconds: 1));
        pending.add(done);

        return done;
      };
      await openChooser(tester, auth: auth);
      await tester.tap(find.text('Organization'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField), 'a@b.co');

      await tester.tap(find.text('Send me the link'));
      await tester.pump();
      expect(find.text('Sending…'), findsOneWidget);
      await tester.tap(find.text('Sending…'), warnIfMissed: false);
      await tester.pump();

      expect(auth.signupLinks.length, 1);

      await tester.pump(const Duration(seconds: 2));
      await tester.pumpAndSettle();
      expect(find.text('Check your email'), findsOneWidget);
    });

    testWidgets('no connection: the words, and the button tries again', (tester) async {
      var tries = 0;
      final auth = FakeAuthApi()
        ..signupLinkHandler = ((type, email) async {
          if (++tries == 1) throw const NetworkFailure();
        });
      await openChooser(tester, auth: auth);
      await tester.tap(find.text('Organization'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField), 'a@b.co');
      await tester.tap(find.text('Send me the link'));
      await tester.pumpAndSettle();

      expect(find.text('We could not reach MyKaunsel. Check your internet connection and try again.'), findsOneWidget);

      await tester.tap(find.text('Send me the link'));
      await tester.pumpAndSettle();
      expect(find.text('Check your email'), findsOneWidget);
    });

    testWidgets('too many tries says how long to wait', (tester) async {
      final auth = FakeAuthApi()..signupLinkHandler = ((type, email) async => throw const RateLimitedFailure(retryAfterSeconds: 120));
      await openChooser(tester, auth: auth);
      await tester.tap(find.text('Organization'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField), 'a@b.co');
      await tester.tap(find.text('Send me the link'));
      await tester.pumpAndSettle();

      expect(find.textContaining('120'), findsOneWidget);
    });

    testWidgets('a kind closed in the meantime shows the admin\'s words from the server', (tester) async {
      final auth = FakeAuthApi()..signupLinkHandler = ((type, email) async => throw const ForbiddenFailure('Counselor sign-ups reopen soon.'));
      await openChooser(tester, auth: auth);
      await tester.tap(find.text('Counselor'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField), 'a@b.co');
      await tester.tap(find.text('Send me the link'));
      await tester.pumpAndSettle();

      expect(find.text('Counselor sign-ups reopen soon.'), findsOneWidget);
    });
  });

  group('closed kinds', () {
    testWidgets('are marked, open the admin\'s message and never ask for an email', (tester) async {
      final auth = FakeAuthApi();
      await openChooser(tester, auth: auth, registration: {'organization': const RegistrationStatus(open: false, message: 'Organizations reopen in March.')});

      expect(find.text('Closed for now'), findsOneWidget);

      await tester.tap(find.text('Organization'));
      await tester.pumpAndSettle();

      expect(find.text('Sign-up is closed for now'), findsOneWidget);
      expect(find.text('Organizations reopen in March.'), findsOneWidget);
      expect(find.byType(TextFormField), findsNothing);
      expect(auth.signupLinks, isEmpty);
    });

    testWidgets('closing one kind leaves the others open', (tester) async {
      await openChooser(tester, registration: {'organization': const RegistrationStatus(open: false, message: 'x')});

      await tester.tap(find.text('Counselor'));
      await tester.pumpAndSettle();
      expect(find.text('Sign up on a computer'), findsOneWidget);
    });

    testWidgets('individual closed: the message, not the form', (tester) async {
      await openChooser(tester, registration: {'individual': const RegistrationStatus(open: false, message: 'Back soon.')});

      await tester.tap(find.text('Individual'));
      await tester.pumpAndSettle();

      expect(find.text('Back soon.'), findsOneWidget);
      expect(find.widgetWithText(TextFormField, 'Full name'), findsNothing);
    });
  });

  group('large text and small screens', () {
    for (final scale in [1.0, 2.0]) {
      testWidgets('the choice and the sheet do not overflow at 320×640, text ×$scale', (tester) async {
        await openChooser(tester, size: const Size(320, 640), textScale: scale, registration: {'counselor': const RegistrationStatus(open: false, message: 'Closed.')});
        expect(tester.takeException(), isNull);

        await tester.ensureVisible(find.text('Organization'));
        await tester.tap(find.text('Organization'));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      });
    }
  });
}
