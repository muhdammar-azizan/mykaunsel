import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';

import 'support/fakes.dart';

Future<void> open(
  WidgetTester tester, {
  FakePlatformStatusApi? statusApi,
  FakeAuthApi? authApi,
  FakeTokenStorage? storage,
}) async {
  await tester.pumpWidget(
    ProviderScope(overrides: testOverrides(statusApi: statusApi, authApi: authApi, storage: storage), child: const MyKaunselApp()),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('maintenance shows the Platform Admin\'s words and "try again" asks the server again', (tester) async {
    final statusApi = FakePlatformStatusApi(() async => status(maintenance: true, maintenanceMessage: 'Back at 6 PM tonight.'));

    await open(tester, statusApi: statusApi);

    expect(find.text('Down for maintenance'), findsOneWidget);
    expect(find.text('Back at 6 PM tonight.'), findsOneWidget);

    statusApi.handler = () async => status();
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();

    expect(find.text('Down for maintenance'), findsNothing);
    expect(find.text('Welcome back'), findsOneWidget);
    expect(statusApi.calls, 2);
  });

  testWidgets('an app that is too old can only go to the store', (tester) async {
    await open(tester, statusApi: FakePlatformStatusApi(() async => status(updateRequired: true, storeUrl: 'https://play.example/app')));

    expect(find.text('Please update the app'), findsOneWidget);
    expect(find.text('Please update.'), findsOneWidget);
    expect(find.text('Update now'), findsOneWidget);
  });

  testWidgets('without a store link there is no Update button yet', (tester) async {
    await open(tester, statusApi: FakePlatformStatusApi(() async => status(updateRequired: true)));

    expect(find.text('Please update the app'), findsOneWidget);
    expect(find.text('Update now'), findsNothing);
  });

  testWidgets('no connection at start-up says so and can be retried', (tester) async {
    final statusApi = FakePlatformStatusApi(() async => throw const NetworkFailure());

    await open(tester, statusApi: statusApi);

    expect(find.text('No connection'), findsOneWidget);

    statusApi.handler = () async => status();
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();

    expect(find.text('No connection'), findsNothing);
  });

  testWidgets('a server problem at start-up gets the general message', (tester) async {
    await open(tester, statusApi: FakePlatformStatusApi(() async => throw const ServerFailure(statusCode: 500)));

    expect(find.text('Something went wrong'), findsOneWidget);
  });

  testWidgets('a saved sign-in opens the home screen and signing out goes back', (tester) async {
    final storage = FakeTokenStorage('5|mkp_abc');

    await open(tester, storage: storage);

    expect(find.text('Signed in as Siti Aminah'), findsOneWidget);

    await tester.tap(find.byTooltip('Account'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Sign out'), 200, scrollable: find.byType(Scrollable).first); // the Account page is longer now than the small test screen
    await tester.tap(find.text('Sign out'));
    await tester.pumpAndSettle();

    expect(find.text('Signed in as Siti Aminah'), findsNothing);
    expect(find.text('Welcome back'), findsOneWidget);
    expect(storage.token, isNull);
  });

  testWidgets('an expired saved sign-in lands on sign-in with an explanation', (tester) async {
    await open(
      tester,
      storage: FakeTokenStorage('old'),
      authApi: FakeAuthApi(me: () async => throw const UnauthorizedFailure()),
    );

    expect(find.text('Your session has ended. Please sign in again.'), findsOneWidget);
  });

  testWidgets('the app uses the MyKaunsel title and theme', (tester) async {
    await open(tester);

    final app = tester.widget<MaterialApp>(find.byType(MaterialApp));
    expect(app.theme?.useMaterial3, isTrue);
    expect(app.theme?.textTheme.bodyLarge?.fontFamily ?? 'PlusJakartaSans', contains('PlusJakartaSans'));
  });
}
