import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/core/session/session_controller.dart';
import 'package:mykaunsel/features/bookings/domain/my_booking.dart';

import '../../support/fakes.dart';

/// Opens the details of booking `bk1` (a confirmed online session tomorrow at 10:00).
Future<ProviderContainer> openDetails(WidgetTester tester, FakeMyBookingsApi api, {double textScale = 1, Size size = const Size(400, 900)}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(myBookingsApi: api, storage: FakeTokenStorage('5|mkp_abc')),
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();

  final container = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
  container.read(appRouterProvider).go(AppRoutes.bookingPath('bk1'));
  await tester.pumpAndSettle();

  return container;
}

Finder cancelButton() => find.widgetWithText(OutlinedButton, 'Cancel booking');

Finder sheetCancel() => find.widgetWithText(FilledButton, 'Cancel booking');

Future<void> openSheet(WidgetTester tester) async {
  await tester.scrollUntilVisible(cancelButton(), 200, scrollable: find.byWidgetPredicate((w) => w is Scrollable && w.axis == Axis.vertical).first);
  await tester.pumpAndSettle();
  await tester.tap(cancelButton());
  await tester.pumpAndSettle();
}

void main() {
  group('the button', () {
    testWidgets('is there for a confirmed booking still ahead, and only then', (tester) async {
      await openDetails(tester, FakeMyBookingsApi());
      expect(cancelButton(), findsOneWidget);
    });

    testWidgets('is not there for a cancelled booking', (tester) async {
      final api = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(status: BookingState.cancelled, canCancel: false, withDeadline: false));
      await openDetails(tester, api);

      expect(cancelButton(), findsNothing);
    });

    testWidgets('is not there for a completed one or a session already over', (tester) async {
      final done = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(status: BookingState.completed, days: -2));
      await openDetails(tester, done);
      expect(cancelButton(), findsNothing);
    });
  });

  group('cancelling', () {
    testWidgets('the sheet says what will happen and until when; "Keep booking" closes it and asks nothing', (tester) async {
      final api = FakeMyBookingsApi();
      await openDetails(tester, api);
      await openSheet(tester);

      expect(find.text('Cancel this booking?'), findsOneWidget);
      expect(find.textContaining('Your session with Kaunselor Aina Syazwani on Tomorrow, 10:00 AM – 10:50 AM will be cancelled and the time will be released.'), findsOneWidget);
      expect(find.text('You can cancel until Mon 2 Nov, 10:00 AM.'), findsWidgets);

      await tester.tap(find.text('Keep booking'));
      await tester.pumpAndSettle();
      expect(find.text('Cancel this booking?'), findsNothing);
      expect(api.cancels, isEmpty);
    });

    testWidgets('confirming cancels once, closes the sheet, says so and shows the booking as cancelled', (tester) async {
      final api = FakeMyBookingsApi();
      var cancelled = false;
      api.detailHandler = ((id) async => cancelled ? aBookingDetail(status: BookingState.cancelled, canCancel: false, withDeadline: false) : aBookingDetail());
      api.cancelHandler = ((id) async {
        cancelled = true;

        return aBookingDetail(status: BookingState.cancelled, canCancel: false, withDeadline: false);
      });
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetCancel());
      await tester.pumpAndSettle();

      expect(api.cancels, ['bk1']);
      expect(find.text('Cancel this booking?'), findsNothing);
      expect(find.text('Booking cancelled.'), findsOneWidget);
      expect(find.text('This booking was cancelled.'), findsOneWidget);
      expect(cancelButton(), findsNothing);
    });

    testWidgets('the lists are asked for again after a cancel', (tester) async {
      final api = FakeMyBookingsApi();
      final container = await openDetails(tester, api);
      container.read(appRouterProvider).go(AppRoutes.bookings);
      await tester.pumpAndSettle();
      final before = api.lists.length;
      container.read(appRouterProvider).go(AppRoutes.bookingPath('bk1'));
      await tester.pumpAndSettle();

      await openSheet(tester);
      await tester.tap(sheetCancel());
      await tester.pumpAndSettle();

      container.read(appRouterProvider).go(AppRoutes.bookings);
      await tester.pumpAndSettle();
      expect(api.lists.length, greaterThan(before));
    });

    testWidgets('while it works: "Cancelling…", nothing can close the sheet, and a second tap sends nothing more', (tester) async {
      final pending = Completer<MyBookingDetail>();
      final api = FakeMyBookingsApi()..cancelHandler = ((id) => pending.future);
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetCancel());
      await tester.pump();

      expect(find.text('Cancelling…'), findsOneWidget);
      expect(tester.widget<OutlinedButton>(find.widgetWithText(OutlinedButton, 'Keep booking')).onPressed, isNull);

      await tester.binding.handlePopRoute(); // the phone's Back
      await tester.pump();
      expect(find.text('Cancelling…'), findsOneWidget, reason: 'the sheet is still there');

      await tester.tap(find.text('Cancelling…'), warnIfMissed: false);
      await tester.pump();
      expect(api.cancels.length, 1);

      pending.complete(aBookingDetail(status: BookingState.cancelled, canCancel: false, withDeadline: false));
      await tester.pumpAndSettle();
      expect(find.text('Cancel this booking?'), findsNothing);
    });
  });

  group('too close to the start', () {
    testWidgets('the sheet is the plain explanation with one button, and asks nothing', (tester) async {
      final api = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(canCancel: false, withDeadline: false));
      await openDetails(tester, api);
      await openSheet(tester);

      expect(find.text("This booking can't be cancelled"), findsOneWidget);
      expect(find.text("This session starts in under 24 hours, so it can't be cancelled after booking."), findsOneWidget);
      expect(sheetCancel(), findsNothing);
      expect(find.text('Keep booking'), findsNothing);

      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      expect(api.cancels, isEmpty);
    });

    testWidgets('if the server says it is closed now, the sheet turns into the explanation and the page refreshes', (tester) async {
      var asked = 0;
      final api = FakeMyBookingsApi()
        ..detailHandler = ((id) async => ++asked == 1 ? aBookingDetail() : aBookingDetail(canCancel: false, withDeadline: false))
        ..cancelHandler = ((id) async => throw const ConflictFailure(code: 'cancellation_closed', body: {'cancellation_hours': 48}));
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetCancel());
      await tester.pumpAndSettle();

      expect(find.text("This booking can't be cancelled"), findsOneWidget);
      expect(find.text("This session starts in under 48 hours, so it can't be cancelled after booking."), findsOneWidget);
      expect(asked, 2, reason: 'the booking page was asked for again');
    });

    testWidgets('"not cancellable" (already over, or marked done) says so', (tester) async {
      final api = FakeMyBookingsApi()..cancelHandler = ((id) async => throw const ConflictFailure(code: 'not_cancellable'));
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetCancel());
      await tester.pumpAndSettle();

      expect(find.text("This booking can't be cancelled"), findsOneWidget);
    });
  });

  group('when it goes wrong', () {
    testWidgets('no connection: the words, and "Try again" asks again (it is safe to repeat)', (tester) async {
      var tries = 0;
      final api = FakeMyBookingsApi()
        ..cancelHandler = ((id) async {
          if (++tries == 1) throw const NetworkFailure(timedOut: true);

          return aBookingDetail(status: BookingState.cancelled, canCancel: false, withDeadline: false);
        });
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetCancel());
      await tester.pumpAndSettle();

      expect(find.text('Cancel this booking?'), findsOneWidget, reason: 'still open');
      expect(find.byIcon(Icons.error_outline), findsOneWidget);
      await tester.tap(find.widgetWithText(FilledButton, 'Try again'));
      await tester.pumpAndSettle();

      expect(api.cancels, ['bk1', 'bk1']);
      expect(find.text('Booking cancelled.'), findsOneWidget);
    });

    testWidgets('a server error says it may or may not have happened, and offers to try again', (tester) async {
      final api = FakeMyBookingsApi()..cancelHandler = ((id) async => throw const ServerFailure(statusCode: 500));
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetCancel());
      await tester.pumpAndSettle();

      expect(find.textContaining('may or may not have been cancelled'), findsOneWidget);
      expect(find.widgetWithText(FilledButton, 'Try again'), findsOneWidget);
    });

    testWidgets('429 respects the wait the server gave', (tester) async {
      final api = FakeMyBookingsApi()..cancelHandler = ((id) async => throw const RateLimitedFailure(retryAfterSeconds: 30));
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetCancel());
      await tester.pumpAndSettle();

      expect(find.text('Too many attempts. Please wait 30 seconds and try again.'), findsOneWidget);
    });

    testWidgets('404: "we couldn\'t find this booking", and the button is off', (tester) async {
      final api = FakeMyBookingsApi()..cancelHandler = ((id) async => throw const NotFoundFailure());
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetCancel());
      await tester.pumpAndSettle();

      expect(find.text("We couldn't find this booking."), findsWidgets);
      expect(tester.widget<FilledButton>(sheetCancel()).onPressed, isNull);
    });

    testWidgets('401 takes the person to sign-in and nothing more is sent', (tester) async {
      late ProviderContainer container;
      final api = FakeMyBookingsApi();
      api.cancelHandler = ((id) async {
        // The real client tells the session about every failed call; the fake does not, so do what it would.
        container.read(sessionControllerProvider.notifier).handleFailure(const UnauthorizedFailure(), hadToken: true);

        throw const UnauthorizedFailure();
      });
      container = await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetCancel());
      await tester.pumpAndSettle();

      expect(api.cancels.length, 1);
      expect(find.text('Sign in'), findsWidgets);
    });
  });

  group('for screen readers and large text', () {
    testWidgets('the sheet title is a live heading and the problem is announced', (tester) async {
      final handle = tester.ensureSemantics();
      final api = FakeMyBookingsApi()..cancelHandler = ((id) async => throw const NetworkFailure());
      await openDetails(tester, api);
      await openSheet(tester);

      expect(tester.getSemantics(find.text('Cancel this booking?')), isSemantics(isHeader: true, isLiveRegion: true));

      await tester.tap(sheetCancel());
      await tester.pumpAndSettle();
      expect(tester.getSemantics(find.text('We could not reach MyKaunsel. Check your internet connection and try again.')), isSemantics(isLiveRegion: true));
      handle.dispose();
    });

    for (final scale in [1.0, 2.0]) {
      testWidgets('nothing overflows at text size ×$scale on a small phone, with a problem showing', (tester) async {
        final api = FakeMyBookingsApi()..cancelHandler = ((id) async => throw const ServerFailure());
        await openDetails(tester, api, textScale: scale, size: const Size(320, 640));
        await openSheet(tester);
        await tester.tap(sheetCancel());
        await tester.pumpAndSettle();

        expect(tester.takeException(), isNull);
      });
    }

    testWidgets('the buttons are at least 44 high', (tester) async {
      await openDetails(tester, FakeMyBookingsApi());
      await openSheet(tester);

      expect(tester.getSize(sheetCancel()).height, greaterThanOrEqualTo(44));
      expect(tester.getSize(find.widgetWithText(OutlinedButton, 'Keep booking')).height, greaterThanOrEqualTo(44));
    });
  });
}
