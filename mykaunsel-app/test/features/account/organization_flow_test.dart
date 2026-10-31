import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/features/account/domain/my_organization.dart';

import '../../support/fakes.dart';

Future<void> openOrganization(WidgetTester tester, FakeAccountApi account, {Size size = const Size(400, 900), double textScale = 1}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(accountApi: account, storage: FakeTokenStorage('5|mkp_abc')),
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();

  ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp))).read(appRouterProvider).go(AppRoutes.account);
  await tester.pumpAndSettle();
  await tester.scrollUntilVisible(find.text('Your organization'), 200, scrollable: find.byType(Scrollable).first);
  await tester.ensureVisible(find.text('Your organization'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Your organization'));
  await tester.pumpAndSettle();
}

Future<void> typeCode(WidgetTester tester, String code) async {
  await tester.enterText(find.byType(TextField), code);
  await tester.pump();
}

void main() {
  group('Your organization: joining', () {
    testWidgets('is on the Account page and explains that a code is optional', (tester) async {
      await openOrganization(tester, FakeAccountApi());

      expect(find.textContaining('You can use MyKaunsel without one.'), findsOneWidget);
      expect(find.text('Reference code'), findsOneWidget);
      expect(find.text('Send my request'), findsOneWidget);
    });

    testWidgets('sends the code as typed, capitals only, and then says it is waiting', (tester) async {
      final account = FakeAccountApi();
      await openOrganization(tester, account);

      await typeCode(tester, 'k7m29-qxr4p');
      expect(find.text('K7M29-QXR4P'), findsOneWidget);

      await tester.tap(find.text('Send my request'));
      await tester.pumpAndSettle();

      expect(account.joinCalls, ['K7M29-QXR4P']);
      expect(find.text('Waiting for Acme Berhad'), findsOneWidget);
      expect(find.textContaining('has to approve you before you can book its counselors'), findsOneWidget);
      expect(find.text('Withdraw my request'), findsOneWidget);
      expect(find.byType(TextField), findsNothing);
    });

    testWidgets('an empty code asks nothing', (tester) async {
      final account = FakeAccountApi();
      await openOrganization(tester, account);

      await tester.tap(find.text('Send my request'));
      await tester.pumpAndSettle();

      expect(account.joinCalls, isEmpty);
    });

    testWidgets('a wrong code shows the server message and keeps the box for another try', (tester) async {
      final account = FakeAccountApi()..joinHandler = (_) async => throw const ValidationFailure('This code is not valid or has expired. Check it with your organization.');
      await openOrganization(tester, account);

      await typeCode(tester, 'WRONGCODE1');
      await tester.tap(find.text('Send my request'));
      await tester.pumpAndSettle();

      expect(find.text('This code is not valid or has expired. Check it with your organization.'), findsOneWidget);
      expect(find.byType(TextField), findsOneWidget);
      expect(tester.widget<TextField>(find.byType(TextField)).controller!.text, 'WRONGCODE1', reason: 'the code stays so a typing slip can be fixed');
    });

    testWidgets('already in another organization: the message says which one', (tester) async {
      final account = FakeAccountApi()
        ..joinHandler = (_) async => throw const ConflictFailure(code: 'in_other_organization', message: 'You are still a member of Beta Sdn Bhd. Leave it first to join another organization.');
      await openOrganization(tester, account);

      await typeCode(tester, 'ABCDE23456');
      await tester.tap(find.text('Send my request'));
      await tester.pumpAndSettle();

      expect(find.textContaining('Beta Sdn Bhd'), findsOneWidget);
    });

    testWidgets('no connection and too many tries are said plainly', (tester) async {
      final account = FakeAccountApi()..joinHandler = (_) async => throw const NetworkFailure();
      await openOrganization(tester, account);

      await typeCode(tester, 'ABCDE23456');
      await tester.tap(find.text('Send my request'));
      await tester.pumpAndSettle();
      expect(find.textContaining('connection'), findsWidgets);

      account.joinHandler = (_) async => throw const RateLimitedFailure(retryAfterSeconds: 30);
      await tester.tap(find.text('Send my request'));
      await tester.pumpAndSettle();
      expect(find.textContaining('30'), findsWidgets);
    });
  });

  group('Your organization: belonging and leaving', () {
    testWidgets('someone waiting can withdraw after a confirmation', (tester) async {
      final account = FakeAccountApi()..myOrganization = const MyOrganization(name: 'Acme Berhad', waiting: true);
      await openOrganization(tester, account);

      await tester.tap(find.text('Withdraw my request'));
      await tester.pumpAndSettle();
      expect(find.text('Withdraw your request?'), findsOneWidget);
      expect(account.leaveCalls, 0);

      await tester.tap(find.text('Withdraw'));
      await tester.pumpAndSettle();

      expect(account.leaveCalls, 1);
      expect(find.text('Reference code'), findsOneWidget);
    });

    testWidgets('a member is told what leaving does BEFORE confirming, then what it did', (tester) async {
      final account = FakeAccountApi()
        ..myOrganization = const MyOrganization(name: 'Acme Berhad', waiting: false, graceDays: 7)
        ..leaveHandler = () async => const LeftOrganization(name: 'Acme Berhad', cancelled: 2, kept: 1);
      await openOrganization(tester, account);

      expect(find.text('You belong to Acme Berhad'), findsOneWidget);

      await tester.tap(find.text('Leave organization'));
      await tester.pumpAndSettle();

      expect(find.text('Leave Acme Berhad?'), findsOneWidget);
      expect(find.textContaining('will be told that you left'), findsOneWidget);
      expect(find.textContaining('Sessions in the next 7 days still go ahead.'), findsOneWidget);

      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(account.leaveCalls, 0, reason: 'cancel changes nothing');

      await tester.tap(find.text('Leave organization'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Leave'));
      await tester.pumpAndSettle();

      expect(account.leaveCalls, 1);
      expect(find.text('You left Acme Berhad'), findsOneWidget);
      expect(find.text('2 later sessions were cancelled.'), findsOneWidget);
      expect(find.text('1 session in the next few days still goes ahead.'), findsOneWidget);
      expect(find.text('Reference code'), findsOneWidget, reason: 'they may join another organization now');
    });

    testWidgets('leaving with nothing cancelled says only that they left', (tester) async {
      final account = FakeAccountApi()..myOrganization = const MyOrganization(name: 'Acme Berhad', waiting: false);
      await openOrganization(tester, account);

      await tester.tap(find.text('Leave organization'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Leave'));
      await tester.pumpAndSettle();

      expect(find.text('You left Acme Berhad'), findsOneWidget);
      expect(find.textContaining('cancelled'), findsNothing);
    });

    testWidgets('a failed leave keeps them as members and says why', (tester) async {
      final account = FakeAccountApi()
        ..myOrganization = const MyOrganization(name: 'Acme Berhad', waiting: false)
        ..leaveHandler = () async => throw const NetworkFailure();
      await openOrganization(tester, account);

      await tester.tap(find.text('Leave organization'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Leave'));
      await tester.pumpAndSettle();

      expect(find.text('You belong to Acme Berhad'), findsOneWidget);
      expect(find.textContaining('connection'), findsWidgets);
    });
  });

  group('Your organization: large text and small screens', () {
    for (final scale in [1.0, 2.0]) {
      testWidgets('joining does not overflow at ×$scale on 320×640', (tester) async {
        await openOrganization(tester, FakeAccountApi(), size: const Size(320, 640), textScale: scale);

        expect(tester.takeException(), isNull);
        await tester.scrollUntilVisible(find.text('Send my request'), 100, scrollable: find.byType(Scrollable).first);
        expect(find.text('Reference code'), findsOneWidget);
      });

      testWidgets('belonging and the leave question do not overflow at ×$scale on 320×640', (tester) async {
        final account = FakeAccountApi()..myOrganization = const MyOrganization(name: 'Universiti Teknologi Contoh Berhad Malaysia', waiting: false);
        await openOrganization(tester, account, size: const Size(320, 640), textScale: scale);
        expect(tester.takeException(), isNull);

        await tester.scrollUntilVisible(find.text('Leave organization'), 100, scrollable: find.byType(Scrollable).first);
        await tester.tap(find.text('Leave organization'));
        await tester.pumpAndSettle();

        expect(tester.takeException(), isNull);
        expect(find.textContaining('will be told that you left'), findsOneWidget);
      });
    }
  });
}
