import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/providers.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/features/platform_status/domain/platform_status.dart';

import '../support/fakes.dart';

Future<ProviderContainer> openAccount(
  WidgetTester tester, {
  FakeAccountApi? account,
  FakePlatformStatusApi? statusApi,
  List<Uri>? opened,
  bool opens = true,
  Size size = const Size(400, 900),
  double textScale = 1,
}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        ...testOverrides(accountApi: account, statusApi: statusApi, storage: FakeTokenStorage('5|mkp_abc')),
        urlOpenerProvider.overrideWithValue((uri) async {
          opened?.add(uri);

          return opens;
        }),
      ],
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();

  final container = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
  container.read(appRouterProvider).go(AppRoutes.account);
  await tester.pumpAndSettle();

  return container;
}

void main() {
  group('Account lists the new pages', () {
    testWidgets('Notifications and Data and privacy are there and open', (tester) async {
      await openAccount(tester, account: FakeAccountApi());

      expect(find.text('Notifications'), findsOneWidget);
      expect(find.text('Data and privacy'), findsOneWidget);

      await tester.tap(find.text('Notifications'));
      await tester.pumpAndSettle();
      expect(find.text('Choose which e-mails you get. What happens is always shown under the bell on Home. There are no push notifications yet.'), findsOneWidget);
    });
  });

  group('Help, Terms and the Privacy Policy', () {
    testWidgets('are on the Account page and open the website pages', (tester) async {
      final opened = <Uri>[];
      await openAccount(tester, account: FakeAccountApi(), opened: opened);

      for (final entry in {'Help': '/help', 'Terms of Use': '/terms', 'Privacy Policy': '/privacy'}.entries) {
        await tester.scrollUntilVisible(find.text(entry.key), 200, scrollable: find.byType(Scrollable).first);
        await tester.drag(find.byType(Scrollable).first, const Offset(0, -120));
        await tester.pumpAndSettle();
        await tester.tap(find.text(entry.key));
        await tester.pumpAndSettle();
        expect(opened.last.path, entry.value);
      }

      expect(opened.map((u) => u.host).toSet(), {'test.local'});
    });

    testWidgets('a page that can not be opened says so', (tester) async {
      await openAccount(tester, account: FakeAccountApi(), opens: false);

      await tester.scrollUntilVisible(find.text('Help'), 200, scrollable: find.byType(Scrollable).first);
      await tester.drag(find.byType(Scrollable).first, const Offset(0, -120));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Help'));
      await tester.pumpAndSettle();

      expect(find.text("We couldn't open that page."), findsOneWidget);
    });
  });

  group('Notifications', () {
    testWidgets('one switch for bookings, on by default, with what it covers', (tester) async {
      await openAccount(tester, account: FakeAccountApi());
      await tester.tap(find.text('Notifications'));
      await tester.pumpAndSettle();

      expect(find.widgetWithText(SwitchListTile, 'Bookings'), findsOneWidget);
      expect(find.text('Confirmations, answers to your time requests and changes to your sessions.'), findsOneWidget);
      expect(tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value, isTrue);
    });

    testWidgets('turning it off saves that one choice and the switch follows the server', (tester) async {
      final account = FakeAccountApi();
      await openAccount(tester, account: account);
      await tester.tap(find.text('Notifications'));
      await tester.pumpAndSettle();

      await tester.tap(find.byType(SwitchListTile));
      await tester.pumpAndSettle();

      expect(account.preferenceCalls, [(key: 'booking_emails', enabled: false)]);
      expect(tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value, isFalse);

      await tester.tap(find.byType(SwitchListTile));
      await tester.pumpAndSettle();

      expect(account.preferenceCalls.last, (key: 'booking_emails', enabled: true));
      expect(tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value, isTrue);
    });

    testWidgets('if the server does not answer, the switch stays as it was and says so', (tester) async {
      final account = FakeAccountApi()..preferenceHandler = ((key, enabled) async => throw const NetworkFailure());
      await openAccount(tester, account: account);
      await tester.tap(find.text('Notifications'));
      await tester.pumpAndSettle();

      await tester.tap(find.byType(SwitchListTile));
      await tester.pumpAndSettle();

      expect(find.text("We couldn't save that. Please try again."), findsOneWidget);
      expect(tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value, isTrue);
    });

    testWidgets('the choice is read back when the page is opened again', (tester) async {
      final account = FakeAccountApi();
      final container = await openAccount(tester, account: account);
      await tester.tap(find.text('Notifications'));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(SwitchListTile));
      await tester.pumpAndSettle();

      container.read(appRouterProvider).go(AppRoutes.home);
      await tester.pumpAndSettle();
      container.read(appRouterProvider).go(AppRoutes.accountNotifications);
      await tester.pumpAndSettle();

      expect(tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value, isFalse);
    });
  });

  group('Data and privacy', () {
    Future<void> openPrivacy(WidgetTester tester, {PlatformStatus? status, List<Uri>? opened, bool opens = true, Size size = const Size(400, 900), double textScale = 1}) async {
      await openAccount(tester, account: FakeAccountApi(), statusApi: FakePlatformStatusApi(() async => status ?? statusOf()), opened: opened, opens: opens, size: size, textScale: textScale);
      await tester.scrollUntilVisible(find.text('Data and privacy'), 200, scrollable: find.byType(Scrollable).first);
      await tester.tap(find.text('Data and privacy'));
      await tester.pumpAndSettle();
    }

    testWidgets('says how long the chat is kept, who can read it, and the law', (tester) async {
      await openPrivacy(tester);

      expect(find.text('Your chat conversations are deleted after 12 months.'), findsOneWidget);
      expect(find.text("Counselors and organizations can't read your chat."), findsOneWidget);
      expect(find.textContaining('Personal Data Protection Act (PDPA)'), findsOneWidget);
    });

    testWidgets('the Privacy Policy opens on the web, and if it can not it says so', (tester) async {
      final opened = <Uri>[];
      await openPrivacy(tester, opened: opened);

      await tester.tap(find.text('Privacy Policy'));
      await tester.pumpAndSettle();
      expect(opened.single.toString(), 'http://test.local/privacy');

      await tester.pumpWidget(const SizedBox());
      await openPrivacy(tester, opens: false);
      await tester.tap(find.text('Privacy Policy'));
      await tester.pumpAndSettle();
      expect(find.text("We couldn't open the Privacy Policy."), findsOneWidget);
    });

    testWidgets('without a support address from the server there is no line about it', (tester) async {
      await openPrivacy(tester);

      expect(find.text('A copy of your data'), findsNothing);
    });

    testWidgets('with one, it says where to write and opens the email app', (tester) async {
      final opened = <Uri>[];
      await openPrivacy(tester, status: statusOf().copyWith(supportEmail: 'help@mykaunsel.example'), opened: opened);

      expect(find.text('To ask for a copy of your data, write to help@mykaunsel.example.'), findsOneWidget);

      await tester.tap(find.widgetWithText(OutlinedButton, 'help@mykaunsel.example'));
      await tester.pumpAndSettle();

      expect(opened.single.toString(), 'mailto:help@mykaunsel.example');
    });

    testWidgets('if the email app can not open, the address is given in words', (tester) async {
      await openPrivacy(tester, status: statusOf().copyWith(supportEmail: 'help@mykaunsel.example'), opens: false);

      await tester.tap(find.widgetWithText(OutlinedButton, 'help@mykaunsel.example'));
      await tester.pumpAndSettle();

      expect(find.text("We couldn't open your email app. Please write to help@mykaunsel.example."), findsOneWidget);
    });

    testWidgets('a server that does not answer is not an error here: just no support line', (tester) async {
      // The app starts on the first answer; only the later asks (this page's) fail.
      var asked = 0;
      await openAccount(tester, account: FakeAccountApi(), statusApi: FakePlatformStatusApi(() async => ++asked == 1 ? statusOf() : throw const NetworkFailure()));
      await tester.tap(find.text('Data and privacy'));
      await tester.pumpAndSettle();

      expect(find.text('Your chat conversations are deleted after 12 months.'), findsOneWidget);
      expect(find.text('A copy of your data'), findsNothing);
    });

    testWidgets('does not overflow at 320×640, text ×2.0', (tester) async {
      // (The test engine draws letters wider than the real font, so a short address keeps this about the layout, not the font.)
      await openPrivacy(tester, status: statusOf().copyWith(supportEmail: 'a@my.co'), size: const Size(320, 640), textScale: 2);

      expect(tester.takeException(), isNull);
    });
  });
}

PlatformStatus statusOf() => status();
