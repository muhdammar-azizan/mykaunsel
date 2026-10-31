import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/providers.dart';
import 'package:mykaunsel/features/account/domain/my_organization.dart';

import '../../support/fakes.dart';

Future<ProviderContainer> openHome(WidgetTester tester, FakeAccountApi account, {bool dismissed = false, double textScale = 1, Size size = const Size(400, 900)}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(accountApi: account, storage: FakeTokenStorage('5|mkp_abc'), orgHintDismissed: dismissed),
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();

  return ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
}

void main() {
  group('Home: the organization hint', () {
    testWidgets('someone with no organization is asked whether they have a code, and the card opens the page', (tester) async {
      await openHome(tester, FakeAccountApi());

      expect(find.text('Have a code from your organization?'), findsOneWidget);

      await tester.tap(find.text('Have a code from your organization?'));
      await tester.pumpAndSettle();

      expect(find.text('Reference code'), findsOneWidget);
    });

    testWidgets('hiding it keeps it hidden', (tester) async {
      final container = await openHome(tester, FakeAccountApi());

      await tester.tap(find.byTooltip('Hide this'));
      await tester.pumpAndSettle();

      expect(find.text('Have a code from your organization?'), findsNothing);
      expect(container.read(onboardingStoreProvider).orgHintDismissed, isTrue);
    });

    testWidgets('it stays hidden on the next visit', (tester) async {
      await openHome(tester, FakeAccountApi(), dismissed: true);

      expect(find.text('Have a code from your organization?'), findsNothing);
    });

    testWidgets('an invitation is shown even after the code card was hidden, and it can not be hidden', (tester) async {
      await openHome(tester, FakeAccountApi()..invitations = [const OrgInvitation(id: 'i1', organization: 'Acme Berhad')], dismissed: true);

      expect(find.text('Acme Berhad invited you'), findsOneWidget);
      expect(find.byTooltip('Hide this'), findsNothing);
    });

    testWidgets('someone waiting for approval is told so', (tester) async {
      await openHome(tester, FakeAccountApi()..myOrganization = const MyOrganization(name: 'Acme Berhad', waiting: true), dismissed: true);

      expect(find.text('Waiting for Acme Berhad'), findsOneWidget);
      expect(find.text('Your organization has to approve you first.'), findsOneWidget);
    });

    testWidgets('a member sees nothing about it', (tester) async {
      await openHome(tester, FakeAccountApi()..myOrganization = const MyOrganization(name: 'Acme Berhad', waiting: false));

      expect(find.text('Have a code from your organization?'), findsNothing);
      expect(find.textContaining('Acme Berhad'), findsNothing);
    });

    testWidgets('if it could not be loaded Home shows no error and no card', (tester) async {
      final account = FakeAccountApi();
      // The page asks the server; a server that does not answer is not Home's problem.
      await openHome(tester, _Failing());

      expect(find.text('Have a code from your organization?'), findsNothing);
      expect(find.text('Try again'), findsNothing);
      expect(find.text('Find a counselor'), findsOneWidget);
      expect(account.joinCalls, isEmpty);
    });

    testWidgets('does not overflow at ×2 on 320×640', (tester) async {
      await openHome(tester, FakeAccountApi(), textScale: 2, size: const Size(320, 640));

      expect(tester.takeException(), isNull);
      expect(find.text('Have a code from your organization?'), findsOneWidget);
    });
  });
}

class _Failing extends FakeAccountApi {
  @override
  Future<OrganizationOverview> organization() async => throw const NetworkFailure();
}
