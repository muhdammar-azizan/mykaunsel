import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/providers.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/core/session/session_state.dart';
import 'package:mykaunsel/features/onboarding/presentation/onboarding_screen.dart';

import '../../support/fakes.dart';

Future<void> open(WidgetTester tester, {bool seen = false, String? token, double scale = 1}) async {
  tester.view.physicalSize = const Size(800, 1800);
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(onboardingSeen: seen, storage: FakeTokenStorage(token)),
      child: MediaQuery(
        data: MediaQueryData(size: const Size(400, 900), textScaler: TextScaler.linear(scale)),
        child: const MyKaunselApp(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  group('redirect rules', () {
    test('a signed-out person who has not seen it goes to the introduction, from anywhere', () {
      for (final place in [AppRoutes.splash, AppRoutes.signIn, AppRoutes.signUp, AppRoutes.home]) {
        expect(sessionRedirect(const SessionSignedOut(), place, onboardingSeen: false), AppRoutes.onboarding);
      }

      expect(sessionRedirect(const SessionSignedOut(), AppRoutes.onboarding, onboardingSeen: false), isNull);
    });

    test('once seen, the introduction is closed and sign-in is the way in', () {
      expect(sessionRedirect(const SessionSignedOut(), AppRoutes.onboarding), AppRoutes.signIn);
      expect(sessionRedirect(const SessionSignedOut(), AppRoutes.signIn), isNull);
    });

    test('a signed-in person never sits on it, and gates still win over it', () {
      expect(sessionRedirect(const SessionSignedIn(testUser), AppRoutes.onboarding, onboardingSeen: false), AppRoutes.home);
      expect(sessionRedirect(const SessionMaintenance('x'), AppRoutes.onboarding, onboardingSeen: false), AppRoutes.maintenance);
    });
  });

  group('the introduction', () {
    testWidgets('shows first to someone who has not seen it, with three steps', (tester) async {
      await open(tester);

      expect(find.text('Talk it through'), findsOneWidget);
      expect(find.text('Skip'), findsOneWidget);
      expect(find.text('Next'), findsOneWidget);
      expect(find.bySemanticsLabel('Step 1 of 3'), findsOneWidget);

      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();
      expect(find.text('Find a counselor'), findsOneWidget);
      expect(find.bySemanticsLabel('Step 2 of 3'), findsOneWidget);

      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();
      expect(find.text('Feel less alone'), findsOneWidget);
      expect(find.text('Next'), findsNothing);
      expect(find.text('Get started'), findsOneWidget);
    });

    testWidgets('"Get started" goes to sign-in and it is not shown again', (tester) async {
      await open(tester);
      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Get started'));
      await tester.pumpAndSettle();

      expect(find.text('Welcome back'), findsOneWidget);

      final container = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
      expect(container.read(onboardingStoreProvider).seen, isTrue);

      container.read(appRouterProvider).go(AppRoutes.onboarding);
      await tester.pumpAndSettle();
      expect(find.text('Welcome back'), findsOneWidget);
    });

    testWidgets('Skip on the first page also goes to sign-in and remembers it', (tester) async {
      await open(tester);
      await tester.tap(find.text('Skip'));
      await tester.pumpAndSettle();

      expect(find.text('Welcome back'), findsOneWidget);
      expect(ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp))).read(onboardingStoreProvider).seen, isTrue);
    });

    testWidgets('someone who has seen it goes straight to sign-in', (tester) async {
      await open(tester, seen: true);

      expect(find.text('Welcome back'), findsOneWidget);
      expect(find.text('Skip'), findsNothing);
    });

    testWidgets('a signed-in person is never shown it, even if it was never seen', (tester) async {
      await open(tester, token: '5|mkp_abc');

      expect(find.byType(OnboardingScreen), findsNothing);
      expect(find.byType(NavigationBar), findsOneWidget);
    });

    testWidgets('stays usable at the largest text size', (tester) async {
      await open(tester, scale: 2);

      expect(tester.takeException(), isNull);
      expect(find.text('Next'), findsOneWidget);
      expect(tester.getSize(find.widgetWithText(FilledButton, 'Next')).height, greaterThanOrEqualTo(44));
      expect(tester.getSize(find.widgetWithText(TextButton, 'Skip')).height, greaterThanOrEqualTo(44));
    });
  });
}
