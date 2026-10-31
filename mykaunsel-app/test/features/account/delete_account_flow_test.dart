import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/router/app_router.dart';

import '../../support/fakes.dart';

Future<FakeTokenStorage> openDelete(WidgetTester tester, FakeAccountApi account, {Size size = const Size(400, 900), double textScale = 1}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  final storage = FakeTokenStorage('5|mkp_abc');

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(accountApi: account, storage: storage),
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();

  ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp))).read(appRouterProvider).go(AppRoutes.account);
  await tester.pumpAndSettle();
  await tester.scrollUntilVisible(find.text('Delete account'), 200, scrollable: find.byType(Scrollable).first);
  await tester.drag(find.byType(Scrollable).first, const Offset(0, -200));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Delete account'));
  await tester.pumpAndSettle();

  return storage;
}

Finder get passwordField => find.byType(TextField).first;

Finder get wordField => find.byType(TextField).last;

FilledButton get deleteButton => (find.widgetWithText(FilledButton, 'Delete my account').evaluate().single.widget) as FilledButton;

/// The page is longer than the screen: bring the button into view, then press it.
Future<void> pressDelete(WidgetTester tester) async {
  final button = find.widgetWithText(FilledButton, 'Delete my account');
  await tester.ensureVisible(button);
  await tester.pumpAndSettle();
  await tester.tap(button);
  await tester.pumpAndSettle();
}

void main() {
  group('Delete account', () {
    testWidgets('is on the Account page and says what goes, what ends and what stays before asking for anything', (tester) async {
      await openDelete(tester, FakeAccountApi());

      expect(find.text('This can not be undone.'), findsOneWidget);
      expect(find.text('What is erased'), findsOneWidget);
      expect(find.text('What ends'), findsOneWidget);
      expect(find.text('What stays'), findsOneWidget);
      expect(find.textContaining('90 days'), findsOneWidget);
    });

    testWidgets('the button waits for the password AND the word DELETE in capitals', (tester) async {
      await openDelete(tester, FakeAccountApi());

      expect(deleteButton.onPressed, isNull);

      await tester.enterText(passwordField, 'secret-pass-123');
      await tester.pump();
      expect(deleteButton.onPressed, isNull, reason: 'the word is still missing');

      await tester.enterText(wordField, 'delet');
      await tester.pump();
      expect(deleteButton.onPressed, isNull);

      await tester.enterText(wordField, 'delete');
      await tester.pump();
      expect(find.text('DELETE'), findsOneWidget, reason: 'typed letters become capitals at once');
      expect(deleteButton.onPressed, isNotNull);

      await tester.enterText(passwordField, '');
      await tester.pump();
      expect(deleteButton.onPressed, isNull);
    });

    testWidgets('deleting asks the server once, signs this phone out and says the account is gone', (tester) async {
      final account = FakeAccountApi();
      final storage = await openDelete(tester, account);

      await tester.enterText(passwordField, 'secret-pass-123');
      await tester.enterText(wordField, 'DELETE');
      await tester.pump();
      await pressDelete(tester);

      expect(account.deleteCalls, ['secret-pass-123']);
      expect(await storage.read(), isNull, reason: 'this phone forgets the sign-in');
      expect(find.text('Welcome back'), findsOneWidget);
      expect(find.text('Your account has been deleted. We sent an e-mail to confirm.'), findsOneWidget);
    });

    testWidgets('a wrong password is shown under its field and the account stays', (tester) async {
      final account = FakeAccountApi()..deleteHandler = (_) async => throw const ValidationFailure('Invalid', fieldErrors: {'current_password': ['That password is not correct.']});
      await openDelete(tester, account);

      await tester.enterText(passwordField, 'nope');
      await tester.enterText(wordField, 'DELETE');
      await tester.pump();
      await pressDelete(tester);

      expect(find.text('That password is not correct.'), findsOneWidget);
      expect(find.text('Delete my account'), findsOneWidget, reason: 'still on the page, still signed in');
    });

    testWidgets('a counselor or administrator is told to write to support, using the server\'s words', (tester) async {
      final account = FakeAccountApi()
        ..deleteHandler = (_) async => throw const ForbiddenFailure('This account belongs to a counselor or an administrator, so it can not be deleted here. Please write to MyKaunsel support.', code: 'staff_account');
      await openDelete(tester, account);

      await tester.enterText(passwordField, 'secret-pass-123');
      await tester.enterText(wordField, 'DELETE');
      await tester.pump();
      await pressDelete(tester);

      expect(find.textContaining('write to MyKaunsel support'), findsOneWidget);
    });

    testWidgets('no connection and too many tries are said plainly and nothing is lost', (tester) async {
      final account = FakeAccountApi()..deleteHandler = (_) async => throw const NetworkFailure();
      final storage = await openDelete(tester, account);

      await tester.enterText(passwordField, 'secret-pass-123');
      await tester.enterText(wordField, 'DELETE');
      await tester.pump();
      await pressDelete(tester);

      expect(find.textContaining('connection'), findsWidgets);
      expect(await storage.read(), isNotNull, reason: 'still signed in');

      account.deleteHandler = (_) async => throw const RateLimitedFailure(retryAfterSeconds: 30);
      await pressDelete(tester);
      expect(find.textContaining('30'), findsWidgets);
    });

    for (final scale in [1.0, 2.0]) {
      testWidgets('does not overflow at ×$scale on 320×640', (tester) async {
        await openDelete(tester, FakeAccountApi(), size: const Size(320, 640), textScale: scale);
        expect(tester.takeException(), isNull);

        await tester.scrollUntilVisible(find.widgetWithText(FilledButton, 'Delete my account'), 100, scrollable: find.byType(Scrollable).first);
        expect(tester.takeException(), isNull);
      });
    }
  });
}
