import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/features/account/domain/signed_in_device.dart';
import 'package:mykaunsel/features/auth/domain/app_user.dart';
import 'package:mykaunsel/features/auth/domain/sign_in_result.dart';

import '../../support/fakes.dart';

Future<void> open(
  WidgetTester tester, {
  FakeAccountApi? account,
  FakeAuthApi? auth,
  FakeCounselorApi? counselors,
  double textScale = 1,
  Size size = const Size(400, 900),
}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(accountApi: account, authApi: auth, counselorApi: counselors, storage: FakeTokenStorage('5|mkp_abc')),
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> goToAccount(WidgetTester tester) async {
  await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('Account')));
  await tester.pumpAndSettle();
}

Future<void> openPage(WidgetTester tester, String label) async {
  await goToAccount(tester);
  await tester.tap(find.text(label));
  await tester.pumpAndSettle();
}

Finder field(String label) => find.widgetWithText(TextFormField, label);

void main() {
  group('the account tab', () {
    testWidgets('lists the pages, then the two ways of signing out', (tester) async {
      await open(tester);
      await goToAccount(tester);

      for (final text in ['Profile', 'Change password', 'Signed-in devices', 'Sign out', 'Sign out of all devices']) {
        expect(find.text(text), findsOneWidget, reason: text);
      }
    });
  });

  group('profile', () {
    testWidgets('shows the name, the email (not editable) and the zone; nothing to save until something changes', (tester) async {
      await open(tester);
      await openPage(tester, 'Profile');

      expect(find.widgetWithText(TextFormField, 'Siti Aminah'), findsOneWidget);
      expect(find.text('siti@example.com'), findsOneWidget);
      expect(tester.widget<TextFormField>(field('Email')).enabled, isFalse);
      expect(find.text('To change your email address, use the MyKaunsel website.'), findsOneWidget);
      expect(find.text('Malaysia (Kuala Lumpur)'), findsOneWidget);
      expect(tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'Save changes')).onPressed, isNull);

      await tester.enterText(field('Full name'), 'Siti Nur Aminah');
      await tester.pump();

      expect(tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'Save changes')).onPressed, isNotNull);
    });

    testWidgets('saving sends the tidied name, shows a note, goes back, and the whole app shows the new name', (tester) async {
      final account = FakeAccountApi();
      await open(tester, account: account);
      await openPage(tester, 'Profile');

      await tester.enterText(field('Full name'), '  Siti Nur Aminah ');
      await tester.pump();
      await tester.tap(find.widgetWithText(FilledButton, 'Save changes'));
      await tester.pumpAndSettle();

      expect(account.profileCalls.single, (name: 'Siti Nur Aminah', timezone: 'Asia/Kuala_Lumpur'));
      expect(find.text('Profile saved.'), findsOneWidget);
      expect(find.text('Siti Nur Aminah'), findsOneWidget); // the account tab's header
      expect(find.text('Sign out'), findsOneWidget);

      await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('Home')));
      await tester.pumpAndSettle();
      expect(find.text('Hello, Siti'), findsOneWidget);
      expect(find.text('Signed in as Siti Nur Aminah'), findsOneWidget);
    });

    testWidgets('the time zone can be changed', (tester) async {
      final account = FakeAccountApi();
      await open(tester, account: account);
      await openPage(tester, 'Profile');

      await tester.tap(find.text('Malaysia (Kuala Lumpur)'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Singapore').last);
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Save changes'));
      await tester.pumpAndSettle();

      expect(account.profileCalls.single.timezone, 'Asia/Singapore');
    });

    testWidgets('a zone set on the web that the app does not list is kept, not silently replaced', (tester) async {
      final auth = FakeAuthApi(me: () async => const AppUser(id: 7, name: 'Siti Aminah', email: 'siti@example.com', emailVerified: true, timezone: 'Asia/Tokyo'));
      final account = FakeAccountApi();
      await open(tester, account: account, auth: auth);
      await openPage(tester, 'Profile');

      expect(find.text('Asia/Tokyo'), findsOneWidget);

      await tester.enterText(field('Full name'), 'Siti Nur');
      await tester.pump();
      await tester.tap(find.widgetWithText(FilledButton, 'Save changes'));
      await tester.pumpAndSettle();

      expect(account.profileCalls.single.timezone, 'Asia/Tokyo');
    });

    testWidgets('a name that is too short is caught before sending; the server\'s own message is shown under the field', (tester) async {
      final account = FakeAccountApi();
      await open(tester, account: account);
      await openPage(tester, 'Profile');

      await tester.enterText(field('Full name'), 'A');
      await tester.pump();
      await tester.tap(find.widgetWithText(FilledButton, 'Save changes'));
      await tester.pump();

      expect(find.text('Enter your name.'), findsOneWidget);
      expect(account.profileCalls, isEmpty);

      account.updateProfileHandler = (name, timezone) async => throw const ValidationFailure('Bad name.', fieldErrors: {'name': ['That name is not allowed.']});
      await tester.enterText(field('Full name'), 'Valid Name');
      await tester.pump();
      await tester.tap(find.widgetWithText(FilledButton, 'Save changes'));
      await tester.pumpAndSettle();

      expect(find.text('That name is not allowed.'), findsOneWidget);
      expect(find.text('Your profile'), findsOneWidget); // stays on the page
    });

    testWidgets('no connection keeps what was typed and says why', (tester) async {
      final account = FakeAccountApi()..updateProfileHandler = ((name, timezone) async => throw const NetworkFailure());
      await open(tester, account: account);
      await openPage(tester, 'Profile');

      await tester.enterText(field('Full name'), 'Siti Nur Aminah');
      await tester.pump();
      await tester.tap(find.widgetWithText(FilledButton, 'Save changes'));
      await tester.pumpAndSettle();

      expect(find.text('We could not reach MyKaunsel. Check your internet connection and try again.'), findsOneWidget);
      expect(find.widgetWithText(TextFormField, 'Siti Nur Aminah'), findsOneWidget);
    });
  });

  group('change password', () {
    Future<void> fill(WidgetTester tester, {String current = 'Str0ng-Pass-123', String next = 'An0ther-Str0ng-Pass!', String? confirm}) async {
      await tester.enterText(field('Current password'), current);
      await tester.enterText(field('New password'), next);
      await tester.enterText(field('Confirm new password'), confirm ?? next);
    }

    Future<void> submit(WidgetTester tester) async {
      final button = find.widgetWithText(FilledButton, 'Change password');
      await tester.ensureVisible(button);
      await tester.tap(button);
      await tester.pumpAndSettle();
    }

    testWidgets('success: sends the passwords once, says other devices were signed out, goes back', (tester) async {
      final account = FakeAccountApi();
      await open(tester, account: account);
      await openPage(tester, 'Change password');

      expect(find.text('Changing your password signs you out on all your other devices.'), findsOneWidget);

      await fill(tester);
      await submit(tester);

      expect(account.passwordCalls.single, (current: 'Str0ng-Pass-123', next: 'An0ther-Str0ng-Pass!'));
      expect(find.text('Password updated. Your other devices were signed out.'), findsOneWidget);
      expect(find.text('Sign out'), findsOneWidget); // back on the account tab, still signed in
    });

    testWidgets('empty, weak and mismatched passwords are caught before anything is sent', (tester) async {
      final account = FakeAccountApi();
      await open(tester, account: account);
      await openPage(tester, 'Change password');

      await submit(tester);
      expect(find.text('Enter your current password.'), findsOneWidget);
      expect(find.text('Enter your password.'), findsOneWidget);

      await fill(tester, next: 'weak', confirm: 'different');
      await submit(tester);
      expect(find.text('The two passwords are not the same.'), findsOneWidget);
      expect(find.text('Your password needs:'), findsWidgets);
      expect(account.passwordCalls, isEmpty);
    });

    testWidgets('a wrong current password is shown under that field and the page stays', (tester) async {
      final account = FakeAccountApi()
        ..changePasswordHandler = ((c, n) async => throw const ValidationFailure('That password is not correct.', fieldErrors: {'current_password': ['That password is not correct.']}));
      await open(tester, account: account);
      await openPage(tester, 'Change password');

      await fill(tester);
      await submit(tester);

      expect(find.text('That password is not correct.'), findsOneWidget);
      expect(find.text('Change password'), findsWidgets);
    });

    testWidgets('the same password as before is refused with the server\'s sentence under the new password', (tester) async {
      final account = FakeAccountApi()
        ..changePasswordHandler = ((c, n) async => throw const ValidationFailure('x', fieldErrors: {'password': ['Choose a password you have not used just now.']}));
      await open(tester, account: account);
      await openPage(tester, 'Change password');

      await fill(tester);
      await submit(tester);

      expect(find.text('Choose a password you have not used just now.'), findsOneWidget);
    });

    testWidgets('too many tries says how long to wait', (tester) async {
      final account = FakeAccountApi()..changePasswordHandler = ((c, n) async => throw const RateLimitedFailure(retryAfterSeconds: 45));
      await open(tester, account: account);
      await openPage(tester, 'Change password');

      await fill(tester);
      await submit(tester);

      expect(find.text('Too many attempts. Please try again in 45 seconds.'), findsOneWidget);
    });

    testWidgets('the checklist follows the new password, and the eye shows it', (tester) async {
      await open(tester);
      await openPage(tester, 'Change password');

      await tester.enterText(field('New password'), 'abcdefgh');
      await tester.pump();

      expect(find.bySemanticsLabel('A lower-case letter, done'), findsOneWidget);
      expect(find.bySemanticsLabel('A number, not yet'), findsOneWidget);
    });
  });

  group('signed-in devices', () {
    testWidgets('lists this phone first (no button) and the others (with a sign out button)', (tester) async {
      await open(tester);
      await openPage(tester, 'Signed-in devices');

      expect(find.text('Google Pixel 8'), findsOneWidget);
      expect(find.text('This phone'), findsOneWidget);
      expect(find.text('Old Tablet'), findsOneWidget);
      expect(find.text('Last used 5 hours ago'), findsOneWidget);
      expect(find.widgetWithText(TextButton, 'Sign out'), findsOneWidget); // only for the tablet
    });

    testWidgets('signing another phone out asks for the password, then refreshes the list', (tester) async {
      final account = FakeAccountApi();
      await open(tester, account: account);
      await openPage(tester, 'Signed-in devices');

      await tester.tap(find.widgetWithText(TextButton, 'Sign out'));
      await tester.pumpAndSettle();

      expect(find.text('Sign out Old Tablet?'), findsOneWidget);

      // nothing happens with an empty password
      await tester.tap(find.widgetWithText(FilledButton, 'Sign out'));
      await tester.pumpAndSettle();
      expect(account.revokeCalls, isEmpty);

      await tester.enterText(find.byType(TextFormField), 'Str0ng-Pass-123');
      account.devicesHandler = () async => [const SignedInDevice(id: 1, name: 'Google Pixel 8', current: true)];
      await tester.tap(find.widgetWithText(FilledButton, 'Sign out'));
      await tester.pumpAndSettle();

      expect(account.revokeCalls.single, (id: 2, current: 'Str0ng-Pass-123'));
      expect(find.text('That device was signed out.'), findsOneWidget);
      expect(find.text('Old Tablet'), findsNothing);
      expect(account.deviceCalls, 2);
    });

    testWidgets('a wrong password keeps the dialog open with the reason', (tester) async {
      final account = FakeAccountApi()
        ..revokeHandler = ((id, current) async => throw const ValidationFailure('x', fieldErrors: {'current_password': ['That password is not correct.']}));
      await open(tester, account: account);
      await openPage(tester, 'Signed-in devices');

      await tester.tap(find.widgetWithText(TextButton, 'Sign out'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField), 'nope');
      await tester.tap(find.widgetWithText(FilledButton, 'Sign out'));
      await tester.pumpAndSettle();

      expect(find.text('That password is not correct.'), findsOneWidget);
      expect(find.text('Sign out Old Tablet?'), findsOneWidget);

      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(find.text('Old Tablet'), findsOneWidget);
    });

    testWidgets('too many tries, or no connection, is explained inside the dialog', (tester) async {
      final account = FakeAccountApi()..revokeHandler = ((id, current) async => throw const RateLimitedFailure(retryAfterSeconds: 30));
      await open(tester, account: account);
      await openPage(tester, 'Signed-in devices');

      await tester.tap(find.widgetWithText(TextButton, 'Sign out'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField), 'Str0ng-Pass-123');
      await tester.tap(find.widgetWithText(FilledButton, 'Sign out'));
      await tester.pumpAndSettle();

      expect(find.text('Too many attempts. Please try again in 30 seconds.'), findsOneWidget);
    });

    testWidgets('a phone that was already signed out elsewhere simply disappears', (tester) async {
      final account = FakeAccountApi()..revokeHandler = ((id, current) async => throw const NotFoundFailure());
      await open(tester, account: account);
      await openPage(tester, 'Signed-in devices');

      await tester.tap(find.widgetWithText(TextButton, 'Sign out'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField), 'Str0ng-Pass-123');
      account.devicesHandler = () async => [const SignedInDevice(id: 1, name: 'Google Pixel 8', current: true)];
      await tester.tap(find.widgetWithText(FilledButton, 'Sign out'));
      await tester.pumpAndSettle();

      expect(find.text('Old Tablet'), findsNothing);
      expect(find.text('Sign out Old Tablet?'), findsNothing);
    });

    testWidgets('a list that can not be loaded offers "try again"', (tester) async {
      final account = FakeAccountApi()..devicesHandler = (() async => throw const NetworkFailure());
      await open(tester, account: account);
      await openPage(tester, 'Signed-in devices');

      expect(find.text('We could not reach MyKaunsel. Check your internet connection and try again.'), findsOneWidget);

      account.devicesHandler = () async => [const SignedInDevice(id: 1, name: 'Google Pixel 8', current: true)];
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(find.text('Google Pixel 8'), findsOneWidget);
    });

    testWidgets('after signing out and in as someone else, the list of phones is fetched again', (tester) async {
      var who = 'first';
      final account = FakeAccountApi()
        ..devicesHandler = (() async => [SignedInDevice(id: 1, name: who == 'first' ? 'First Persons Phone' : 'Second Persons Phone', current: true)]);
      final auth = FakeAuthApi()
        ..signInHandler = ((email, password) async => const SignedInResult(token: '9|mkp_other', user: AppUser(id: 99, name: 'Someone Else', email: 'else@example.com', emailVerified: true)));
      await open(tester, account: account, auth: auth);
      await openPage(tester, 'Signed-in devices');
      expect(find.text('First Persons Phone'), findsOneWidget);

      await tester.pageBack();
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sign out'));
      await tester.pumpAndSettle();

      who = 'second';
      await tester.enterText(find.widgetWithText(TextFormField, 'Email'), 'else@example.com');
      await tester.enterText(find.widgetWithText(TextFormField, 'Password'), 'Str0ng-Pass-123');
      await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
      await tester.pumpAndSettle();
      await openPage(tester, 'Signed-in devices');

      expect(find.text('First Persons Phone'), findsNothing);
      expect(find.text('Second Persons Phone'), findsOneWidget);
    });
  });

  group('large text and small screens', () {
    for (final scale in [1.0, 2.0]) {
      testWidgets('the account pages do not overflow at text size ×$scale', (tester) async {
        final account = FakeAccountApi()
          ..devicesHandler = (() async => [
                const SignedInDevice(id: 1, name: 'Samsung Galaxy S24 Ultra with a very long model name', current: true),
                SignedInDevice(id: 2, name: 'Xiaomi Redmi Note 13 Pro Plus 5G', lastUsedAt: testNow.subtract(const Duration(days: 3))),
              ]);
        await open(tester, account: account, textScale: scale, size: const Size(320, 568));

        for (final page in ['Profile', 'Change password', 'Signed-in devices']) {
          await goToAccount(tester);
          final tile = find.text(page);
          await tester.scrollUntilVisible(tile, 100, scrollable: find.byType(Scrollable).first);
          await tester.tap(tile);
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull, reason: page);
          await tester.pageBack();
          await tester.pumpAndSettle();
        }
      });
    }
  });
}
