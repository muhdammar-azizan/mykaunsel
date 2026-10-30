import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/features/booking/domain/availability.dart' show SlotLocation;
import 'package:mykaunsel/features/counselors/domain/counselor.dart' show SessionMode;
import 'package:mykaunsel/features/requests/domain/time_request.dart';

import '../../support/fakes.dart';

/// Opens the app signed in and goes to "Ask for a time" for counselor c1.
Future<ProviderContainer> openAsk(
  WidgetTester tester,
  FakeRequestsApi api, {
  FakeMyBookingsApi? bookings,
  double textScale = 1,
  Size size = const Size(400, 900),
}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(requestsApi: api, myBookingsApi: bookings, storage: FakeTokenStorage('5|mkp_abc')),
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();

  final container = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
  container.read(appRouterProvider).go(AppRoutes.sendRequestPath('c1'));
  await tester.pumpAndSettle();

  return container;
}

/// Opens the Requests tab.
Future<ProviderContainer> openRequests(WidgetTester tester, FakeRequestsApi api, {FakeMyBookingsApi? bookings}) async {
  tester.view.physicalSize = const Size(400, 900) * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(requestsApi: api, myBookingsApi: bookings, storage: FakeTokenStorage('5|mkp_abc')),
      child: const MyKaunselApp(),
    ),
  );
  await tester.pumpAndSettle();

  final container = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
  container.read(appRouterProvider).go(AppRoutes.bookings);
  await tester.pumpAndSettle();
  await tester.tap(find.widgetWithText(Tab, 'Requests'));
  await tester.pumpAndSettle();

  return container;
}

Future<void> openDetail(WidgetTester tester, ProviderContainer container, [String id = 'rq1']) async {
  container.read(appRouterProvider).go(AppRoutes.requestPath(id));
  await tester.pumpAndSettle();
}

Finder sendButton() => find.byType(FilledButton).last;

Future<void> pickMonday(WidgetTester tester, {String time = '2:00 PM'}) async {
  await tester.tap(findText(time));
  await tester.pumpAndSettle();
}

void main() {
  group('the way in', () {
    testWidgets('a counselor who takes requests offers it on their page; one who does not, does not', (tester) async {
      tester.view.physicalSize = const Size(400, 1200) * 2;
      tester.view.devicePixelRatio = 2;
      addTearDown(tester.view.reset);

      Future<void> open(bool accepts) async {
        await tester.pumpWidget(
          ProviderScope(
            overrides: testOverrides(
              counselorApi: FakeCounselorApi()..detailHandler = ((id) async => aCounselor(id: id, acceptsRequests: accepts, upcoming: [aSlot()])),
              storage: FakeTokenStorage('5|mkp_abc'),
            ),
            child: const MyKaunselApp(),
          ),
        );
        await tester.pumpAndSettle();
        ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp))).read(appRouterProvider).go(AppRoutes.counselorPath('c1'));
        await tester.pumpAndSettle();
      }

      await open(true);
      await tester.scrollUntilVisible(find.text('Ask for a different time'), 200, scrollable: find.byType(Scrollable).last);
      expect(find.text('Ask for a different time'), findsOneWidget);
      await tester.tap(find.text('Ask for a different time'));
      await tester.pumpAndSettle();
      expect(find.text('Ask for a time'), findsOneWidget);

      await tester.pumpWidget(const SizedBox());
      await open(false);
      expect(find.text('Ask for a different time'), findsNothing);
    });
  });

  group('asking', () {
    testWidgets('shows the intro, the days with something to ask for, and the times of the first day with where and how long', (tester) async {
      final api = FakeRequestsApi();
      await openAsk(tester, api);

      expect(api.hoursCalls, ['c1']);
      expect(find.text('Ask for a time'), findsOneWidget);
      expect(find.textContaining('Choose a time inside Kaunselor Aina Syazwani\'s working hours. Kaunselor Aina Syazwani has 48 hours to answer'), findsOneWidget);
      expect(find.text('Mon 2 Nov'), findsOneWidget);
      expect(find.text('Thu 5 Nov'), findsOneWidget);
      expect(find.text('Fri 6 Nov'), findsNothing, reason: 'no hours on a Friday');
      expect(findText('2:00 PM'), findsOneWidget);
      expect(findText('3:30 PM'), findsOneWidget);
      expect(find.text('30 min · In-person · Klinik Damai, Kuantan'), findsWidgets);
      expect(find.text('Pick a time to ask for'), findsOneWidget);
      expect(tester.widget<FilledButton>(sendButton()).onPressed, isNull);
    });

    testWidgets('picking a time names it on the button; asking sends the date, the time and the note, then opens the request', (tester) async {
      final api = FakeRequestsApi()
        ..sendHandler = ((id, date, time) async => aTimeRequest(id: 'rq-new', days: 0, hour: 14, mode: SessionMode.physical, location: const SlotLocation(name: 'Klinik Damai', city: 'Kuantan')))
        ..detailHandler = ((id) async => aTimeRequest(id: id, days: 0, hour: 14, mode: SessionMode.physical, location: const SlotLocation(name: 'Klinik Damai', city: 'Kuantan'), message: 'About exams'));
      await openAsk(tester, api);
      await pickMonday(tester);

      expect(find.widgetWithText(FilledButton, 'Ask for Mon 2 Nov, 2:00 PM'), findsOneWidget);

      await tester.scrollUntilVisible(find.byType(TextField), 200, scrollable: find.byWidgetPredicate((w) => w is Scrollable && w.axis == Axis.vertical).first);
      await tester.enterText(find.byType(TextField), '  About exams ');
      await tester.pumpAndSettle();
      await tester.tap(sendButton());
      await tester.pumpAndSettle();

      expect(api.sends.length, 1);
      expect(api.sends.single.counselorId, 'c1');
      expect(api.sends.single.date, '2026-11-02');
      expect(api.sends.single.time, '14:00');
      expect(api.sends.single.message, '  About exams ', reason: 'the api trims it');
      expect(find.text('Ask for a time'), findsNothing);
      expect(find.text('Request'), findsOneWidget);
      expect(find.text('Waiting for an answer'), findsWidgets);
      expect(find.text('About exams'), findsOneWidget);
      expect(api.details, ['rq-new']);
    });

    testWidgets('another day shows its own times, and changing the day clears the pick', (tester) async {
      await openAsk(tester, FakeRequestsApi());
      await pickMonday(tester);
      await tester.tap(find.text('Thu 5 Nov'));
      await tester.pumpAndSettle();

      expect(findText('10:00 AM'), findsOneWidget);
      expect(findText('12:00 PM'), findsOneWidget);
      expect(find.text('60 min · Online'), findsWidgets);
      expect(find.text('Pick a time to ask for'), findsOneWidget);
    });

    testWidgets('while it works: "Sending…", and a second tap sends nothing more', (tester) async {
      final pending = Completer<TimeRequest>();
      final api = FakeRequestsApi()..sendHandler = ((id, date, time) => pending.future);
      await openAsk(tester, api);
      await pickMonday(tester);
      await tester.tap(sendButton());
      await tester.pump();

      expect(find.text('Sending…'), findsOneWidget);
      await tester.tap(find.text('Sending…'), warnIfMissed: false);
      await tester.pump();
      expect(api.sends.length, 1);

      pending.complete(aTimeRequest(id: 'rq-new'));
      await tester.pumpAndSettle();
      expect(find.text('Request'), findsOneWidget);
    });

    testWidgets('the requests list is asked for again after asking', (tester) async {
      final api = FakeRequestsApi();
      final container = await openRequests(tester, api);
      final before = api.lists.length;
      container.read(appRouterProvider).go(AppRoutes.sendRequestPath('c1'));
      await tester.pumpAndSettle();
      await pickMonday(tester);
      await tester.tap(sendButton());
      await tester.pumpAndSettle();
      container.read(appRouterProvider).go(AppRoutes.bookings);
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(Tab, 'Requests'));
      await tester.pumpAndSettle();

      expect(api.lists.length, greaterThan(before));
    });
  });

  group('when it can not be asked', () {
    testWidgets('a counselor who does not take requests', (tester) async {
      final api = FakeRequestsApi()..hoursHandler = ((id) async => aWorkingHours(accepts: false));
      await openAsk(tester, api);

      expect(find.text("This counselor isn't taking time requests right now."), findsOneWidget);
    });

    testWidgets('a counselor with no hours', (tester) async {
      final api = FakeRequestsApi()..hoursHandler = ((id) async => aWorkingHours(lines: const []));
      await openAsk(tester, api);

      expect(find.textContaining('has not set working hours yet'), findsOneWidget);
    });

    testWidgets('no connection while loading: the words and "Try again"', (tester) async {
      var tries = 0;
      final api = FakeRequestsApi()
        ..hoursHandler = ((id) async {
          if (++tries == 1) throw const NetworkFailure(timedOut: true);

          return aWorkingHours();
        });
      await openAsk(tester, api);

      expect(find.text('Try again'), findsOneWidget);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.text('Mon 2 Nov'), findsOneWidget);
    });
  });

  group('when the server says no', () {
    Future<void> tryWith(WidgetTester tester, AppFailure failure) async {
      final api = FakeRequestsApi()..sendHandler = ((id, date, time) async => throw failure);
      await openAsk(tester, api);
      await pickMonday(tester);
      await tester.tap(sendButton());
      await tester.pumpAndSettle();
    }

    testWidgets('the time is taken: the words, and the pick is cleared', (tester) async {
      await tryWith(tester, const ConflictFailure(code: 'slot_taken'));

      expect(find.text("That time isn't available. Please choose another."), findsOneWidget);
      expect(find.text('Pick a time to ask for'), findsOneWidget);
    });

    testWidgets('a clash with the person\'s own session', (tester) async {
      await tryWith(tester, const ConflictFailure(code: 'time_conflict'));

      expect(find.text('You already have a session at this time.'), findsOneWidget);
    });

    testWidgets('too many waiting', (tester) async {
      await tryWith(tester, const ConflictFailure(code: 'request_limit_reached', body: {'max_pending_requests': 3}));

      expect(find.textContaining('up to 3 requests waiting'), findsOneWidget);
    });

    testWidgets('the counselor stopped taking requests', (tester) async {
      await tryWith(tester, const ConflictFailure(code: 'requests_closed'));

      expect(find.text("This counselor isn't taking time requests right now."), findsOneWidget);
    });

    testWidgets('the time is outside the hours', (tester) async {
      await tryWith(tester, const ValidationFailure('x'));

      expect(find.textContaining("outside the counselor's working hours"), findsOneWidget);
    });

    testWidgets('the counselor can not be seen', (tester) async {
      await tryWith(tester, const NotFoundFailure());

      expect(find.text('This counselor is no longer available for booking.'), findsWidgets);
      expect(find.text('Find another counselor'), findsOneWidget);
    });

    testWidgets('an unconfirmed email offers to verify', (tester) async {
      await tryWith(tester, const EmailNotVerifiedFailure('x'));

      expect(find.text('Verify your email to book a session.'), findsOneWidget);
      expect(find.text('Verify my email'), findsOneWidget);
    });

    testWidgets('too many attempts says how long to wait', (tester) async {
      await tryWith(tester, const RateLimitedFailure(retryAfterSeconds: 30));

      expect(find.text('Too many attempts. Please wait 30 seconds and try again.'), findsOneWidget);
    });

    testWidgets('no connection: the words, and "Try again" asks again for the same time (it is never made twice)', (tester) async {
      var tries = 0;
      final api = FakeRequestsApi()
        ..sendHandler = ((id, date, time) async {
          if (++tries == 1) throw const NetworkFailure(timedOut: true);

          return aTimeRequest(id: 'rq-new');
        });
      await openAsk(tester, api);
      await pickMonday(tester);
      await tester.tap(sendButton());
      await tester.pumpAndSettle();

      expect(find.text('We could not reach MyKaunsel. Check your internet connection and try again.'), findsOneWidget);
      expect(find.widgetWithText(FilledButton, 'Try again'), findsOneWidget);

      await tester.tap(find.widgetWithText(FilledButton, 'Try again'));
      await tester.pumpAndSettle();

      expect(api.sends.map((s) => s.time), ['14:00', '14:00']);
      expect(find.text('Request'), findsOneWidget);
    });

    testWidgets('a server error says it may or may not have been sent', (tester) async {
      await tryWith(tester, const ServerFailure(statusCode: 500));

      expect(find.textContaining('may or may not have been sent'), findsOneWidget);
    });
  });

  group('the Requests tab', () {
    testWidgets('lists the requests with a word and an icon for each state, newest first', (tester) async {
      final api = FakeRequestsApi()
        ..listHandler = ((page) async => RequestsPage(
              items: [
                aTimeRequest(id: 'a'),
                aTimeRequest(id: 'b', status: RequestState.approved, bookingId: 'bk9'),
                aTimeRequest(id: 'c', status: RequestState.rejected),
                aTimeRequest(id: 'd', status: RequestState.expired),
                aTimeRequest(id: 'e', status: RequestState.withdrawn),
              ],
              page: 1,
              lastPage: 1,
            ));
      await openRequests(tester, api);

      for (final word in ['Waiting for an answer', 'Approved', 'Declined', 'Expired']) {
        expect(find.text(word), findsWidgets);
      }

      await tester.scrollUntilVisible(find.text('Withdrawn'), 200, scrollable: find.byWidgetPredicate((w) => w is Scrollable && w.axis == Axis.vertical).first);
      expect(find.text('Withdrawn'), findsWidgets);
      expect(find.text('Kaunselor Aina Syazwani'), findsWidgets);
    });

    testWidgets('an empty list says what this is', (tester) async {
      await openRequests(tester, FakeRequestsApi());

      expect(find.text('No requests yet'), findsOneWidget);
    });

    testWidgets('a failure shows "Try again"', (tester) async {
      var tries = 0;
      final api = FakeRequestsApi()
        ..listHandler = ((page) async {
          if (++tries == 1) throw const ServerFailure(statusCode: 500);

          return RequestsPage(items: [aTimeRequest()], page: 1, lastPage: 1);
        });
      await openRequests(tester, api);

      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.text('Waiting for an answer'), findsWidgets);
    });

    testWidgets('a request opens its details', (tester) async {
      final api = FakeRequestsApi()..listHandler = ((page) async => RequestsPage(items: [aTimeRequest(id: 'rq7')], page: 1, lastPage: 1));
      await openRequests(tester, api);
      await tester.tap(find.text('Waiting for an answer').first);
      await tester.pumpAndSettle();

      expect(api.details, ['rq7']);
      expect(find.text('Request'), findsOneWidget);
    });
  });

  group('one request', () {
    testWidgets('waiting: until when the counselor can answer, and a way to withdraw', (tester) async {
      final api = FakeRequestsApi()..detailHandler = ((id) async => aTimeRequest(id: id, message: 'About exams'));
      final container = await openRequests(tester, api);
      await openDetail(tester, container);

      expect(find.text('Waiting for an answer'), findsWidgets);
      expect(find.text('Kaunselor Aina Syazwani can answer until Wed 4 Nov, 9:00 AM.'), findsOneWidget);
      expect(find.text('Thursday, 5 November 2026'), findsOneWidget);
      expect(find.text('About exams'), findsOneWidget);
      expect(find.widgetWithText(OutlinedButton, 'Withdraw request'), findsOneWidget);
    });

    testWidgets('withdrawing asks first, then withdraws once, says so and shows it withdrawn', (tester) async {
      var withdrawn = false;
      final api = FakeRequestsApi()
        ..detailHandler = ((id) async => aTimeRequest(id: id, status: withdrawn ? RequestState.withdrawn : RequestState.pending))
        ..withdrawHandler = ((id) async {
          withdrawn = true;

          return aTimeRequest(id: id, status: RequestState.withdrawn);
        });
      final container = await openRequests(tester, api);
      await openDetail(tester, container);
      await tester.tap(find.widgetWithText(OutlinedButton, 'Withdraw request'));
      await tester.pumpAndSettle();

      expect(find.text('Withdraw this request?'), findsOneWidget);
      expect(api.withdraws, isEmpty);

      await tester.tap(find.widgetWithText(FilledButton, 'Withdraw request'));
      await tester.pumpAndSettle();

      expect(api.withdraws, ['rq1']);
      expect(find.text('Withdraw this request?'), findsNothing);
      expect(find.text('Request withdrawn.'), findsOneWidget);
      expect(find.text('You withdrew this request.'), findsOneWidget);
      expect(find.widgetWithText(OutlinedButton, 'Withdraw request'), findsNothing);
    });

    testWidgets('"Keep request" closes the sheet and asks nothing', (tester) async {
      final api = FakeRequestsApi();
      final container = await openRequests(tester, api);
      await openDetail(tester, container);
      await tester.tap(find.widgetWithText(OutlinedButton, 'Withdraw request'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Keep request'));
      await tester.pumpAndSettle();

      expect(find.text('Withdraw this request?'), findsNothing);
      expect(api.withdraws, isEmpty);
    });

    testWidgets('while it works the sheet can not be closed and a second tap sends nothing more', (tester) async {
      final pending = Completer<TimeRequest>();
      final api = FakeRequestsApi()..withdrawHandler = ((id) => pending.future);
      final container = await openRequests(tester, api);
      await openDetail(tester, container);
      await tester.tap(find.widgetWithText(OutlinedButton, 'Withdraw request'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Withdraw request'));
      await tester.pump();

      expect(find.text('Withdrawing…'), findsOneWidget);
      await tester.binding.handlePopRoute();
      await tester.pump();
      expect(find.text('Withdrawing…'), findsOneWidget);
      await tester.tap(find.text('Withdrawing…'), warnIfMissed: false);
      await tester.pump();
      expect(api.withdraws.length, 1);

      pending.complete(aTimeRequest(status: RequestState.withdrawn));
      await tester.pumpAndSettle();
      expect(find.text('Withdraw this request?'), findsNothing);
    });

    testWidgets('answered meanwhile: the sheet says so and the page refreshes', (tester) async {
      var asked = 0;
      final api = FakeRequestsApi()
        ..detailHandler = ((id) async => ++asked == 1 ? aTimeRequest(id: id) : aTimeRequest(id: id, status: RequestState.approved, bookingId: 'bk1'))
        ..withdrawHandler = ((id) async => throw const ConflictFailure(code: 'request_not_pending'));
      final container = await openRequests(tester, api);
      await openDetail(tester, container);
      await tester.tap(find.widgetWithText(OutlinedButton, 'Withdraw request'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Withdraw request'));
      await tester.pumpAndSettle();

      expect(find.text('This request was already answered or has closed.'), findsOneWidget);
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      expect(find.text('Approved. Your session is booked.'), findsOneWidget);
    });

    testWidgets('no connection while withdrawing: "Try again" asks again (safe to repeat)', (tester) async {
      var tries = 0;
      final api = FakeRequestsApi()
        ..withdrawHandler = ((id) async {
          if (++tries == 1) throw const NetworkFailure(timedOut: true);

          return aTimeRequest(id: id, status: RequestState.withdrawn);
        });
      final container = await openRequests(tester, api);
      await openDetail(tester, container);
      await tester.tap(find.widgetWithText(OutlinedButton, 'Withdraw request'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Withdraw request'));
      await tester.pumpAndSettle();

      await tester.tap(find.widgetWithText(FilledButton, 'Try again'));
      await tester.pumpAndSettle();

      expect(api.withdraws.length, 2);
      expect(find.text('Request withdrawn.'), findsOneWidget);
    });

    testWidgets('approved: the booking opens', (tester) async {
      final api = FakeRequestsApi()..detailHandler = ((id) async => aTimeRequest(id: id, status: RequestState.approved, bookingId: 'bk42'));
      final bookings = FakeMyBookingsApi();
      final container = await openRequests(tester, api, bookings: bookings);
      await openDetail(tester, container);

      expect(find.text('Approved. Your session is booked.'), findsOneWidget);
      expect(find.widgetWithText(OutlinedButton, 'Withdraw request'), findsNothing);

      await tester.tap(find.text('View booking'));
      await tester.pumpAndSettle();

      expect(bookings.details, ['bk42']);
      expect(find.text('Booking details'), findsOneWidget);
    });

    testWidgets('declined: the counselor\'s message is shown, and another time can be asked for', (tester) async {
      final api = FakeRequestsApi()..detailHandler = ((id) async => aTimeRequest(id: id, status: RequestState.rejected, reason: 'I am away that week.'));
      final container = await openRequests(tester, api);
      await openDetail(tester, container);

      expect(find.text("The counselor couldn't take this request."), findsOneWidget);
      expect(find.text('I am away that week.'), findsOneWidget);

      await tester.tap(find.text('Choose another time'));
      await tester.pumpAndSettle();

      expect(find.text('Ask for a time'), findsOneWidget);
      expect(api.hoursCalls, ['c1']);
    });

    testWidgets('expired: it says nobody answered in time', (tester) async {
      final api = FakeRequestsApi()..detailHandler = ((id) async => aTimeRequest(id: id, status: RequestState.expired));
      final container = await openRequests(tester, api);
      await openDetail(tester, container);

      expect(find.text('Nobody answered in time, so this request closed.'), findsOneWidget);
      expect(find.text('Choose another time'), findsOneWidget);
    });

    testWidgets('a request that is not there says so', (tester) async {
      final api = FakeRequestsApi()..detailHandler = ((id) async => throw const NotFoundFailure());
      final container = await openRequests(tester, api);
      await openDetail(tester, container);

      expect(find.text("We couldn't find this request."), findsOneWidget);
    });

    testWidgets('a failure shows "Try again"', (tester) async {
      var tries = 0;
      final api = FakeRequestsApi()
        ..detailHandler = ((id) async {
          if (++tries == 1) throw const ServerFailure(statusCode: 500);

          return aTimeRequest(id: id);
        });
      final container = await openRequests(tester, api);
      await openDetail(tester, container);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(find.text('Waiting for an answer'), findsWidgets);
    });
  });

  group('large text and small screens', () {
    for (final scale in [1.0, 2.0]) {
      testWidgets('asking, and the request page, do not overflow at 320×640, text ×$scale', (tester) async {
        final api = FakeRequestsApi()..detailHandler = ((id) async => aTimeRequest(id: id, message: 'A longer note that needs to wrap on a small screen with large text.', reason: 'I am away that week, so please try another day.', status: RequestState.rejected));
        final container = await openAsk(tester, api, size: const Size(320, 640), textScale: scale);
        await tester.scrollUntilVisible(findText('2:00 PM'), 200, scrollable: find.byWidgetPredicate((w) => w is Scrollable && w.axis == Axis.vertical).first);
        await tester.tap(findText('2:00 PM'));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);

        container.read(appRouterProvider).go(AppRoutes.requestPath('rq1'));
        await tester.pumpAndSettle();
        expect(find.text('Request'), findsOneWidget);
        expect(tester.takeException(), isNull);
      });
    }
  });
}
