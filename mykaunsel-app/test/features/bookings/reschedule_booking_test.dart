import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/features/bookings/domain/my_booking.dart';

import '../../support/fakes.dart';

FakeAvailabilityApi times() => FakeAvailabilityApi()
  ..handler = ((id, from) async => anAvailability([
        anAvailabilityDay(3, [anAvailabilitySlot('n1', days: 3), anAvailabilitySlot('n2', days: 3, hour: 14)]),
      ]));

/// Opens the details of booking `bk1` (a confirmed online session tomorrow at 10:00).
Future<ProviderContainer> openDetails(
  WidgetTester tester,
  FakeMyBookingsApi api, {
  FakeAvailabilityApi? availability,
  double textScale = 1,
  Size size = const Size(400, 900),
}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(myBookingsApi: api, availabilityApi: availability ?? times(), storage: FakeTokenStorage('5|mkp_abc')),
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();

  final container = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
  container.read(appRouterProvider).go(AppRoutes.bookingPath('bk1'));
  await tester.pumpAndSettle();

  return container;
}

Finder rescheduleButton() => find.widgetWithText(FilledButton, 'Reschedule');

Finder verticalScrollable() => find.byWidgetPredicate((w) => w is Scrollable && w.axis == Axis.vertical).first;

Future<void> openPicker(WidgetTester tester) async {
  await tester.scrollUntilVisible(rescheduleButton(), 200, scrollable: verticalScrollable());
  await tester.pumpAndSettle();
  await tester.tap(rescheduleButton());
  await tester.pumpAndSettle();
}

/// From the details to the sheet: Reschedule, the 2:00 PM time of the day after tomorrow, Continue.
Future<void> openSheet(WidgetTester tester) async {
  await openPicker(tester);
  await tester.tap(findText('2:00 PM'));
  await tester.pumpAndSettle();
  await tester.tap(find.widgetWithText(FilledButton, 'Continue with Thu 5 Nov, 2:00 PM'));
  await tester.pumpAndSettle();
}

Finder sheetMove() => find.widgetWithText(FilledButton, 'Move booking');

void main() {
  group('the button', () {
    testWidgets('is there with the moves left, for a confirmed booking the server says can be moved', (tester) async {
      await openDetails(tester, FakeMyBookingsApi());

      expect(rescheduleButton(), findsOneWidget);
      expect(find.text('You can reschedule this booking 2 more times.'), findsOneWidget);
    });

    testWidgets('says "1 more time" when one is left', (tester) async {
      await openDetails(tester, FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(reschedulesLeft: 1)));

      expect(find.text('You can reschedule this booking 1 more time.'), findsOneWidget);
    });

    testWidgets('is not there when the server says no: the words say why', (tester) async {
      await openDetails(tester, FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(canReschedule: false, canCancel: false, withDeadline: false)));

      expect(rescheduleButton(), findsNothing);
      expect(find.text("This booking can't be rescheduled."), findsOneWidget);
    });

    testWidgets('with no moves left it says so', (tester) async {
      await openDetails(tester, FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(canReschedule: false, reschedulesLeft: 0)));

      expect(rescheduleButton(), findsNothing);
      expect(find.text('This booking has already been rescheduled the most times it can be.'), findsOneWidget);
    });

    testWidgets('is not there for a cancelled booking', (tester) async {
      await openDetails(tester, FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(status: BookingState.cancelled, canCancel: false, canReschedule: false, withDeadline: false)));

      expect(rescheduleButton(), findsNothing);
    });
  });

  group('picking the new time', () {
    testWidgets('the same picker, for the same counselor, titled for moving', (tester) async {
      final availability = times();
      await openDetails(tester, FakeMyBookingsApi(), availability: availability);
      await openPicker(tester);

      expect(find.text('Choose a new time'), findsOneWidget);
      expect(find.text('Pick a time'), findsNothing);
      expect(availability.calls.single.id, 'c1');
      expect(find.text('Kaunselor Aina Syazwani'), findsWidgets);
    });

    testWidgets('without the booking in the route (an old link) it goes back to the booking', (tester) async {
      final container = await openDetails(tester, FakeMyBookingsApi());
      container.read(appRouterProvider).go(AppRoutes.reschedulePath('bk1'));
      await tester.pumpAndSettle();

      expect(find.text('Choose a new time'), findsNothing);
      expect(find.text('Booking details'), findsOneWidget);
    });

    testWidgets('Continue opens the sheet from the time now to the time picked; "Keep current time" asks nothing', (tester) async {
      final api = FakeMyBookingsApi();
      await openDetails(tester, api);
      await openSheet(tester);

      expect(find.text('Move to this time?'), findsOneWidget);
      expect(
        find.textContaining('Your session with Kaunselor Aina Syazwani will move from Tomorrow, 10:00 AM – 10:50 AM to Thu 5 Nov, 2:00 PM – 3:00 PM. The time you have now will be released.'),
        findsOneWidget,
      );

      await tester.tap(find.text('Keep current time'));
      await tester.pumpAndSettle();

      expect(find.text('Move to this time?'), findsNothing);
      expect(find.text('Choose a new time'), findsOneWidget, reason: 'still on the picker');
      expect(api.reschedules, isEmpty);
    });
  });

  group('moving', () {
    testWidgets('asks once with what was picked, goes back to the booking, says so and shows the new time', (tester) async {
      var moved = false;
      final api = FakeMyBookingsApi()
        ..detailHandler = ((id) async => moved ? aBookingDetail(days: 3, hour: 14, reschedulesLeft: 1) : aBookingDetail())
        ..rescheduleHandler = ((id, slot) async {
          moved = true;

          return aBookingDetail(days: 3, hour: 14, reschedulesLeft: 1);
        });
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetMove());
      await tester.pumpAndSettle();

      expect(api.reschedules.length, 1);
      expect(api.reschedules.single.id, 'bk1');
      expect(api.reschedules.single.slotId, 'n2');
      expect(api.reschedules.single.startsAt, DateTime.utc(2026, 11, 5, 6));
      expect(api.reschedules.single.minutes, 60);
      expect(api.reschedules.single.mode, 'online');
      expect(find.text('Move to this time?'), findsNothing);
      expect(find.text('Choose a new time'), findsNothing);
      expect(find.text('Booking rescheduled.'), findsOneWidget);
      expect(find.text('You can reschedule this booking 1 more time.'), findsOneWidget);
    });

    testWidgets('the lists are asked for again after a move', (tester) async {
      final api = FakeMyBookingsApi();
      final container = await openDetails(tester, api);
      container.read(appRouterProvider).go(AppRoutes.bookings);
      await tester.pumpAndSettle();
      final before = api.lists.length;
      container.read(appRouterProvider).go(AppRoutes.bookingPath('bk1'));
      await tester.pumpAndSettle();

      await openSheet(tester);
      await tester.tap(sheetMove());
      await tester.pumpAndSettle();

      container.read(appRouterProvider).go(AppRoutes.bookings);
      await tester.pumpAndSettle();
      expect(api.lists.length, greaterThan(before));
    });

    testWidgets('while it works: "Moving…", nothing can close the sheet, and a second tap sends nothing more', (tester) async {
      final pending = Completer<MyBookingDetail>();
      final api = FakeMyBookingsApi()..rescheduleHandler = ((id, slot) => pending.future);
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetMove());
      await tester.pump();

      expect(find.text('Moving…'), findsOneWidget);
      expect(tester.widget<OutlinedButton>(find.widgetWithText(OutlinedButton, 'Keep current time')).onPressed, isNull);

      await tester.binding.handlePopRoute(); // the phone's Back
      await tester.pump();
      expect(find.text('Moving…'), findsOneWidget, reason: 'the sheet is still there');

      await tester.tap(find.text('Moving…'), warnIfMissed: false);
      await tester.pump();
      expect(api.reschedules.length, 1);

      pending.complete(aBookingDetail(days: 3, hour: 14));
      await tester.pumpAndSettle();
      expect(find.text('Move to this time?'), findsNothing);
    });
  });

  group('when the time can not be used', () {
    testWidgets('taken by someone else: the words, and "Choose another time" marks it and keeps the person on the picker', (tester) async {
      final api = FakeMyBookingsApi()..rescheduleHandler = ((id, slot) async => throw const ConflictFailure(code: 'slot_taken'));
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetMove());
      await tester.pumpAndSettle();

      expect(find.text('That time was just taken. Please choose another.'), findsOneWidget);
      expect(sheetMove(), findsNothing);

      await tester.tap(find.text('Choose another time'));
      await tester.pumpAndSettle();

      expect(find.text('Move to this time?'), findsNothing);
      expect(find.text('Choose a new time'), findsOneWidget);
      expect(find.text('Pick a time to continue'), findsOneWidget, reason: 'the taken time is no longer picked');
    });

    testWidgets('edited after it was shown: the times are asked for again', (tester) async {
      final availability = times();
      final api = FakeMyBookingsApi()..rescheduleHandler = ((id, slot) async => throw const ConflictFailure(code: 'slot_changed'));
      await openDetails(tester, api, availability: availability);
      await openSheet(tester);
      final before = availability.calls.length;
      await tester.tap(sheetMove());
      await tester.pumpAndSettle();

      expect(find.text('This time changed while you were looking. Please choose again.'), findsOneWidget);

      await tester.tap(find.text('Choose another time'));
      await tester.pumpAndSettle();

      expect(availability.calls.length, greaterThan(before));
      expect(find.text('Choose a new time'), findsOneWidget);
    });

    testWidgets('a clash with another session of the person', (tester) async {
      final api = FakeMyBookingsApi()..rescheduleHandler = ((id, slot) async => throw const ConflictFailure(code: 'time_conflict'));
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetMove());
      await tester.pumpAndSettle();

      expect(find.text('You already have a session at this time.'), findsOneWidget);

      await tester.tap(find.text('Choose another time'));
      await tester.pumpAndSettle();
      expect(find.text('Choose a new time'), findsOneWidget);
      expect(api.reschedules.length, 1);
    });
  });

  group('when the booking can not be moved any more', () {
    testWidgets('too close to the start: the explanation, one button, and back on a refreshed booking', (tester) async {
      var asked = 0;
      final api = FakeMyBookingsApi()
        ..detailHandler = ((id) async => ++asked == 1 ? aBookingDetail() : aBookingDetail(canReschedule: false, canCancel: false, withDeadline: false))
        ..rescheduleHandler = ((id, slot) async => throw const ConflictFailure(code: 'reschedule_closed', body: {'cancellation_hours': 48}));
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetMove());
      await tester.pumpAndSettle();

      expect(find.text("This booking can't be rescheduled"), findsOneWidget);
      expect(find.text("This session starts in under 48 hours, so it can't be rescheduled."), findsOneWidget);
      expect(sheetMove(), findsNothing);
      expect(asked, 2, reason: 'the booking page was asked for again');

      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();

      expect(find.text('Choose a new time'), findsNothing);
      expect(rescheduleButton(), findsNothing);
    });

    testWidgets('no moves left', (tester) async {
      final api = FakeMyBookingsApi()..rescheduleHandler = ((id, slot) async => throw const ConflictFailure(code: 'reschedule_limit_reached'));
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetMove());
      await tester.pumpAndSettle();

      expect(find.text('This booking has already been rescheduled the most times it can be.'), findsOneWidget);
    });

    testWidgets('already over or marked done', (tester) async {
      final api = FakeMyBookingsApi()..rescheduleHandler = ((id, slot) async => throw const ConflictFailure(code: 'not_reschedulable'));
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetMove());
      await tester.pumpAndSettle();

      expect(find.text("This booking can't be rescheduled"), findsOneWidget);
      expect(find.text("This booking can't be rescheduled any more."), findsOneWidget);
    });

    testWidgets('the booking or the counselor is not there for this person any more', (tester) async {
      final api = FakeMyBookingsApi()..rescheduleHandler = ((id, slot) async => throw const NotFoundFailure());
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetMove());
      await tester.pumpAndSettle();

      expect(find.text('This booking, or this time, is no longer available.'), findsOneWidget);
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      expect(find.text('Choose a new time'), findsNothing);
    });
  });

  group('when it goes wrong', () {
    testWidgets('no connection: the words, and "Try again" asks again for the same time (safe to repeat)', (tester) async {
      var tries = 0;
      final api = FakeMyBookingsApi()
        ..rescheduleHandler = ((id, slot) async {
          if (++tries == 1) throw const NetworkFailure(timedOut: true);

          return aBookingDetail(days: 3, hour: 14, reschedulesLeft: 1);
        });
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetMove());
      await tester.pumpAndSettle();

      expect(find.text('Move to this time?'), findsOneWidget);
      expect(find.widgetWithText(FilledButton, 'Try again'), findsOneWidget);

      await tester.tap(find.widgetWithText(FilledButton, 'Try again'));
      await tester.pumpAndSettle();

      expect(api.reschedules.map((r) => r.slotId), ['n2', 'n2']);
      expect(find.text('Booking rescheduled.'), findsOneWidget);
    });

    testWidgets('a server error says the booking may or may not have been moved, and offers "Try again"', (tester) async {
      final api = FakeMyBookingsApi()..rescheduleHandler = ((id, slot) async => throw const ServerFailure(statusCode: 500));
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetMove());
      await tester.pumpAndSettle();

      expect(find.textContaining('may or may not have been moved'), findsOneWidget);
      expect(find.widgetWithText(FilledButton, 'Try again'), findsOneWidget);
    });

    testWidgets('too many attempts says how long to wait', (tester) async {
      final api = FakeMyBookingsApi()..rescheduleHandler = ((id, slot) async => throw const RateLimitedFailure(retryAfterSeconds: 30));
      await openDetails(tester, api);
      await openSheet(tester);
      await tester.tap(sheetMove());
      await tester.pumpAndSettle();

      expect(find.text('Too many attempts. Please wait 30 seconds and try again.'), findsOneWidget);
    });
  });

  group('large text and small screens', () {
    for (final scale in [1.0, 2.0]) {
      testWidgets('the button, the picker and the sheet do not overflow at 320×640, text ×$scale', (tester) async {
        await openDetails(tester, FakeMyBookingsApi(), size: const Size(320, 640), textScale: scale);
        await openPicker(tester);
        await tester.scrollUntilVisible(findText('2:00 PM'), 200, scrollable: verticalScrollable());
        await tester.pumpAndSettle();
        await tester.tap(findText('2:00 PM'));
        await tester.pumpAndSettle();
        await tester.tap(find.byType(FilledButton).last);
        await tester.pumpAndSettle();

        expect(find.text('Move to this time?'), findsOneWidget);
        expect(tester.takeException(), isNull);
      });
    }
  });
}
