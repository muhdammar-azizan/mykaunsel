import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/features/notifications/domain/app_notification.dart';

import '../../support/fakes.dart';

Future<void> openHome(
  WidgetTester tester,
  FakeNotificationsApi api, {
  FakeMyBookingsApi? bookings,
  FakeRequestsApi? requests,
  Size size = const Size(400, 900),
  double textScale = 1,
}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(notificationsApi: api, myBookingsApi: bookings, requestsApi: requests, storage: FakeTokenStorage('5|mkp_abc')),
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> openPanel(WidgetTester tester, FakeNotificationsApi api, {FakeMyBookingsApi? bookings, FakeRequestsApi? requests, Size size = const Size(400, 900), double textScale = 1}) async {
  await openHome(tester, api, bookings: bookings, requests: requests, size: size, textScale: textScale);
  await tester.tap(find.byType(Badge));
  await tester.pumpAndSettle();
}

void main() {
  group('the bell on Home', () {
    testWidgets('shows how many are unread as a number, and says so in words', (tester) async {
      final api = FakeNotificationsApi([aNotification('a'), aNotification('b'), aNotification('c', read: true)]);
      await openHome(tester, api);

      expect(find.descendant(of: find.byType(Badge), matching: find.text('2')), findsOneWidget);
      expect(find.byTooltip('Notifications, 2 unread'), findsOneWidget);
    });

    testWidgets('shows no number when everything is read', (tester) async {
      await openHome(tester, FakeNotificationsApi([aNotification('a', read: true)]));

      expect(find.byTooltip('Notifications'), findsOneWidget);
      expect(find.descendant(of: find.byType(Badge), matching: find.byType(Text)), findsNothing);
    });

    testWidgets('never shows an error if the number can not be had', (tester) async {
      final api = FakeNotificationsApi([aNotification('a')])..unreadHandler = () async => throw const NetworkFailure();
      await openHome(tester, api);

      expect(find.byTooltip('Notifications'), findsOneWidget);
      expect(find.text('Try again'), findsNothing);
      expect(find.text('Find a counselor'), findsOneWidget);
    });

    testWidgets('opens the page', (tester) async {
      await openPanel(tester, FakeNotificationsApi([aNotification('a')]));

      expect(find.text('Acme Berhad approved you. You can now book its counselors.'), findsOneWidget);
    });
  });

  group('the page', () {
    testWidgets('writes each sentence from the type, and marks unread ones in words as well as with a dot', (tester) async {
      final handle = tester.ensureSemantics();
      final api = FakeNotificationsApi([
        aNotification('a', type: 'request.approved', label: 'Aina'),
        aNotification('b', type: 'booking.cancelled', label: 'Budi', read: true),
      ]);
      await openPanel(tester, api);

      expect(find.text('Aina approved your time request.'), findsOneWidget);
      expect(find.text('Your session with Budi was cancelled.'), findsOneWidget);
      expect(find.byIcon(Icons.circle), findsOneWidget, reason: 'only the unread one has the dot');
      expect(find.bySemanticsLabel(RegExp(r'^Unread\. Aina approved your time request\.')), findsOneWidget);
      expect(find.bySemanticsLabel(RegExp(r'^Your session with Budi was cancelled\.')), findsOneWidget);
      handle.dispose();
    });

    testWidgets('every type has a sentence, and a type this version does not know shows the server title', (tester) async {
      final api = FakeNotificationsApi([
        aNotification('odd', type: 'something.new', label: 'Acme'),
        aNotification('feedRemoved', type: 'feed.removed', label: null),
        aNotification('feedWarned', type: 'feed.warned', label: null),
      ]);
      await openPanel(tester, api, size: const Size(400, 1400));

      expect(find.text('Server title for something.new'), findsOneWidget);
      expect(find.text('Something you wrote on the Feed was removed. Check your e-mail.'), findsOneWidget);
      expect(find.text('You received a warning about your writing on the Feed. Check your e-mail.'), findsOneWidget);
    });

    for (final entry in {
      'request.declined': 'Acme declined your time request.',
      'request.expired': 'Acme did not answer your time request in time.',
      'invitation.received': 'Acme invited you to join.',
      'membership.declined': 'Acme did not approve your request.',
      'membership.ended': 'Your membership of Acme has ended.',
    }.entries) {
      testWidgets('${entry.key} reads as a sentence with the name', (tester) async {
        await openPanel(tester, FakeNotificationsApi([aNotification('x', type: entry.key, label: 'Acme')]));

        expect(find.text(entry.value), findsOneWidget);
      });
    }

    testWidgets('tapping one marks it read and opens the booking it is about', (tester) async {
      final bookings = FakeMyBookingsApi();
      final api = FakeNotificationsApi([
        aNotification('n1', type: 'booking.cancelled', label: 'Aina', target: const NotificationTarget(kind: 'booking', id: 'bk77')),
        aNotification('n2', type: 'membership.approved', label: 'Acme'),
      ]);
      await openPanel(tester, api, bookings: bookings);

      await tester.tap(find.text('Your session with Aina was cancelled.'));
      await tester.pumpAndSettle();

      expect(api.readCalls, ['n1']);
      expect(bookings.details, ['bk77']);
    });

    testWidgets('a time request notification opens that request', (tester) async {
      final requests = FakeRequestsApi();
      final api = FakeNotificationsApi([
        aNotification('r', type: 'request.approved', label: 'Aina', target: const NotificationTarget(kind: 'booking_request', id: 'rq9')),
      ]);
      await openPanel(tester, api, requests: requests);

      await tester.tap(find.text('Aina approved your time request.'));
      await tester.pumpAndSettle();

      expect(requests.details, contains('rq9'));
    });

    testWidgets('an organization notification leads to Your organization', (tester) async {
      await openPanel(tester, FakeNotificationsApi([aNotification('o', type: 'membership.approved', label: 'Acme')]));

      await tester.tap(find.text('Acme approved you. You can now book its counselors.'));
      await tester.pumpAndSettle();

      expect(find.text('Your organization'), findsWidgets);
    });

    testWidgets('a notification whose booking is gone still opens the list it belonged to', (tester) async {
      final api = FakeNotificationsApi([aNotification('n1', type: 'booking.cancelled', label: 'Aina')]);
      await openPanel(tester, api);

      await tester.tap(find.text('Your session with Aina was cancelled.'));
      await tester.pumpAndSettle();

      expect(find.text('Upcoming'), findsWidgets, reason: 'the Bookings tab');
    });

    testWidgets('a mark that fails changes nothing and still opens the page', (tester) async {
      final api = FakeNotificationsApi([aNotification('n1', type: 'booking.cancelled', label: 'Aina')])..markHandler = () async => throw const NetworkFailure();
      await openPanel(tester, api);

      await tester.tap(find.text('Your session with Aina was cancelled.'));
      await tester.pumpAndSettle();

      expect(find.text('Upcoming'), findsWidgets);
    });

    testWidgets('"Mark all as read" clears the dots and the number, and is only offered while something is unread', (tester) async {
      final api = FakeNotificationsApi([aNotification('a'), aNotification('b')]);
      await openPanel(tester, api);

      await tester.tap(find.text('Mark all as read'));
      await tester.pumpAndSettle();

      expect(api.readAllCalls, 1);
      expect(find.byIcon(Icons.circle), findsNothing);
      expect(find.text('Mark all as read'), findsNothing);
    });

    testWidgets('a failing "mark all" says so and changes nothing', (tester) async {
      final api = FakeNotificationsApi([aNotification('a')])..markHandler = () async => throw const NetworkFailure();
      await openPanel(tester, api);

      await tester.tap(find.text('Mark all as read'));
      await tester.pumpAndSettle();

      expect(find.text("We couldn't mark them as read. Try again."), findsOneWidget);
      expect(find.byIcon(Icons.circle), findsOneWidget);
    });

    testWidgets('an empty page says what will show here', (tester) async {
      await openPanel(tester, FakeNotificationsApi());

      expect(find.text('No notifications yet'), findsOneWidget);
    });

    testWidgets('a page that can not load offers "Try again"', (tester) async {
      final api = FakeNotificationsApi([aNotification('a')]);
      await openHome(tester, api);
      api.listHandler = () async => throw const NetworkFailure();
      await tester.tap(find.byType(Badge));
      await tester.pumpAndSettle();

      expect(find.text('Try again'), findsOneWidget);

      api.listHandler = () async {};
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(find.text('Acme Berhad approved you. You can now book its counselors.'), findsOneWidget);
    });

    testWidgets('more are fetched as the end is reached, each shown once', (tester) async {
      final api = FakeNotificationsApi([for (var i = 1; i <= 5; i++) aNotification('n$i', label: 'Org $i', minutesAgo: i)]);
      await openPanel(tester, api, size: const Size(400, 500));
      await tester.scrollUntilVisible(find.textContaining('Org 5'), 200, scrollable: find.byType(Scrollable).first);
      await tester.pumpAndSettle();

      expect(api.listCalls.toSet(), {1, 2, 3});
      expect(find.textContaining('Org 5'), findsOneWidget);
    });

    for (final scale in [1.0, 2.0]) {
      testWidgets('does not overflow at ×$scale on 320×640', (tester) async {
        final api = FakeNotificationsApi([aNotification('a', type: 'request.expired', label: 'Universiti Teknologi Contoh Berhad Malaysia')]);
        await openPanel(tester, api, size: const Size(320, 640), textScale: scale);

        expect(tester.takeException(), isNull);
        expect(find.textContaining('did not answer your time request in time'), findsOneWidget);
      });
    }
  });
}
