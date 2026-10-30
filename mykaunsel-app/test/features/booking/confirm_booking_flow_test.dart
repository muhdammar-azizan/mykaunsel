import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/core/session/session_controller.dart';
import 'package:mykaunsel/features/auth/presentation/pending_auth.dart';
import 'package:mykaunsel/features/booking/domain/availability.dart' show SlotLocation;
import 'package:mykaunsel/features/booking/domain/booking_preview.dart';
import 'package:mykaunsel/features/booking/presentation/booking_args.dart';
import 'package:mykaunsel/features/booking/presentation/confirm_booking_controller.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';

import '../../support/fakes.dart';

const _tickText = 'I understand this session will not be recorded, per platform policy.';

class Opened {
  Opened(this.container, this.keys);

  final ProviderContainer container;
  final List<String> keys;
}

/// Opens the app signed in and goes to the confirm screen by its address (the way a sign-in redirect does). [snapshot] is what "Pick a slot"
/// would have handed over; without it the screen restores itself from the two ids alone.
Future<Opened> openConfirm(
  WidgetTester tester,
  FakeBookingApi api, {
  ConfirmBookingArgs? snapshot,
  double textScale = 1,
  Size size = const Size(400, 800),
  FakeAuthApi? authApi,
  bool settle = true,
}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  final keys = <String>[];

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        ...testOverrides(
          bookingApi: api,
          authApi: authApi,
          // The picker sits under the confirm screen (the address has the counselor in it), so it loads too.
          availabilityApi: FakeAvailabilityApi()..handler = ((id, from) async => anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('s1', days: 1), anAvailabilitySlot('s2', days: 1, hour: 14)])])),
          storage: FakeTokenStorage('5|mkp_abc'),
        ),
        idempotencyKeyFactoryProvider.overrideWithValue(() {
          final key = 'key-${keys.length + 1}';
          keys.add(key);

          return key;
        }),
        bookingRetryDelaysProvider.overrideWithValue(const [Duration.zero, Duration.zero]),
      ],
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();

  final container = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
  container.read(appRouterProvider).go(AppRoutes.confirmBookingPath('c1', 's1'), extra: snapshot);
  await tester.pump();
  if (settle) await tester.pumpAndSettle();

  return Opened(container, keys);
}

FakeBookingApi valid({BookingDetails? details}) => FakeBookingApi()
  ..previewHandler = ((slot) async => BookingPreview(valid: true, details: details ?? someDetails(slot: slot)))
  ..createHandler = ((key) async => aCreatedBooking());

ConfirmBookingArgs snapshotOf(BookingDetails d) => ConfirmBookingArgs(
      counselorId: 'c1',
      counselorName: d.counselor.displayName,
      slotId: 's1',
      mode: d.mode,
      startsAt: d.startsAt,
      durationMinutes: d.durationMinutes,
      location: d.location,
    );

Finder confirmButton() => find.widgetWithText(FilledButton, 'Confirm booking');

Finder tickRow() => find.text(_tickText);

bool buttonOn(WidgetTester tester) => tester.widget<FilledButton>(confirmButton()).onPressed != null;

Future<void> tick(WidgetTester tester) async {
  await tester.tap(tickRow());
  await tester.pumpAndSettle();
}

Future<void> confirm(WidgetTester tester) async {
  await tick(tester);
  await tester.tap(confirmButton());
  await tester.pumpAndSettle();
}

ConflictFailure conflict(String code, [Map<String, dynamic> body = const {}]) => ConflictFailure(code: code, body: body);

void main() {
  // ==============================================================================================================
  group('opening', () {
    testWidgets('the snapshot shows at once while the check runs, the banner says so and the button is off', (tester) async {
      final pending = Completer<BookingPreview>();
      final details = someDetails();
      final api = valid()..previewHandler = ((slot) => pending.future);
      await openConfirm(tester, api, snapshot: snapshotOf(details), settle: false);
      await tester.pump(const Duration(milliseconds: 50));

      expect(find.text('Checking availability…'), findsOneWidget);
      expect(find.text('Tuesday, 3 November 2026'), findsOneWidget); // from the snapshot, before any answer
      expect(find.text('Kaunselor Aina Syazwani'), findsOneWidget);
      expect(find.text('Before you confirm'), findsNothing); // the rules come from the server, so not yet
      expect(buttonOn(tester), isFalse);

      pending.complete(BookingPreview(valid: true, details: details));
      await tester.pumpAndSettle();
      expect(find.text('Checking availability…'), findsNothing);
      expect(find.text('Before you confirm'), findsOneWidget);
    });

    testWidgets('restores itself from the two ids alone (after a sign-in), with skeleton rows until the answer', (tester) async {
      final pending = Completer<BookingPreview>();
      final api = valid()..previewHandler = ((slot) => pending.future);
      await openConfirm(tester, api, settle: false);
      await tester.pump(const Duration(milliseconds: 50));

      expect(find.text('Checking availability…'), findsOneWidget);
      expect(find.text('Tuesday, 3 November 2026'), findsNothing);

      pending.complete(BookingPreview(valid: true, details: someDetails()));
      await tester.pumpAndSettle();
      expect(api.previews, ['s1']);
      expect(find.text('Tuesday, 3 November 2026'), findsOneWidget);
      expect(find.text('Kaunselor Aina Syazwani'), findsOneWidget);
    });

    testWidgets('offline on open: the snapshot stays, the banner shows and the button is off; "Check again" recovers', (tester) async {
      var attempts = 0;
      final details = someDetails();
      final api = valid()
        ..previewHandler = ((slot) async {
          attempts++;
          if (attempts == 1) throw const NetworkFailure();

          return BookingPreview(valid: true, details: details);
        });
      await openConfirm(tester, api, snapshot: snapshotOf(details));

      expect(find.text('No internet connection'), findsOneWidget);
      expect(find.text('Tuesday, 3 November 2026'), findsOneWidget);
      expect(buttonOn(tester), isFalse);

      await tester.tap(find.text('Check again'));
      await tester.pumpAndSettle();
      expect(find.text('No internet connection'), findsNothing);
      await tick(tester);
      expect(buttonOn(tester), isTrue);
    });

    testWidgets('no booking is sent while offline', (tester) async {
      final api = valid()..previewHandler = ((slot) async => throw const NetworkFailure());
      await openConfirm(tester, api, snapshot: snapshotOf(someDetails()));
      await tick(tester);

      expect(buttonOn(tester), isFalse);
      expect(api.creates, isEmpty);
    });

    testWidgets('a time that is gone sends the person back to the picker, which says it was taken', (tester) async {
      final api = valid()..previewHandler = ((slot) async => const BookingPreview(valid: false, reason: 'slot_taken'));
      await openConfirm(tester, api);

      expect(find.text('Pick a time'), findsOneWidget);
      expect(find.text('That time was just taken. Please choose another.'), findsOneWidget);
      expect(find.text('Before you confirm'), findsNothing);
    });

    testWidgets('details that changed since the picker update the summary, say so, and take back a tick already given', (tester) async {
      final pending = Completer<BookingPreview>();
      final shown = someDetails(hour: 10);
      final api = valid()..previewHandler = ((slot) => pending.future);
      await openConfirm(tester, api, snapshot: snapshotOf(shown), settle: false);
      await tester.pump(const Duration(milliseconds: 50));
      await tick(tester); // ticked while still checking

      pending.complete(BookingPreview(valid: true, details: someDetails(hour: 14)));
      await tester.pumpAndSettle();

      expect(find.text('Details changed. Please review and confirm again.'), findsOneWidget);
      expect(find.text('2:00 PM – 2:50 PM, Malaysia time'), findsOneWidget);
      expect(buttonOn(tester), isFalse);
      expect(tester.widget<Checkbox>(find.byType(Checkbox)).value, isFalse);

      await tick(tester);
      expect(find.text('Details changed. Please review and confirm again.'), findsNothing);
      expect(buttonOn(tester), isTrue);
    });

    testWidgets('a quiet line says how many upcoming sessions the person has when near the cap', (tester) async {
      await openConfirm(tester, valid(details: someDetails(upcoming: 2, maxUpcoming: 3)));

      await tester.scrollUntilVisible(find.text('You have 2 of 3 upcoming sessions.'), 100, scrollable: find.byWidgetPredicate((w) => w is Scrollable && w.axis == Axis.vertical));
      expect(find.text('You have 2 of 3 upcoming sessions.'), findsOneWidget);
    });

    testWidgets('no upcoming line when the server does not send one', (tester) async {
      await openConfirm(tester, valid());

      expect(find.textContaining('upcoming sessions.'), findsNothing);
    });

    testWidgets('the check failing with a server error offers "Check again"', (tester) async {
      var attempts = 0;
      final api = valid()
        ..previewHandler = ((slot) async {
          if (++attempts == 1) throw const ServerFailure(statusCode: 500);

          return BookingPreview(valid: true, details: someDetails());
        });
      await openConfirm(tester, api);

      expect(find.text('Please try again in a moment.'), findsOneWidget);
      expect(find.textContaining('may or may not have been made'), findsNothing, reason: 'nothing was being booked');
      await tester.tap(find.text('Check again'));
      await tester.pumpAndSettle();
      expect(find.text('Before you confirm'), findsOneWidget);
    });
  });

  // ==============================================================================================================
  group('the policies come from the server', () {
    testWidgets('online: the cancel hours, the reschedules and the meeting-link wait are the server\'s numbers', (tester) async {
      await openConfirm(tester, valid(details: someDetails(hoursToCancel: 48, maxReschedules: 1, linkMinutes: 30)));

      expect(find.text('You can cancel up to 48 hours before the session.'), findsOneWidget);
      expect(find.text('You can reschedule up to 1 time.'), findsOneWidget);
      expect(find.text('Your meeting link will appear in your booking 30 minutes before the session.'), findsOneWidget);
    });

    testWidgets('inside the cancellation window: the warning variant, with an icon', (tester) async {
      await openConfirm(tester, valid(details: someDetails(hoursToCancel: 24, insideWindow: true)));

      expect(find.text("This session starts in under 24 hours, so it can't be cancelled after booking."), findsOneWidget);
      expect(find.text('You can cancel up to 24 hours before the session.'), findsNothing);
      expect(find.byIcon(Icons.warning_amber_rounded), findsOneWidget);
    });

    testWidgets('in person: the place is named in the summary and the address rule replaces the link rule', (tester) async {
      const place = SlotLocation(name: 'Klinik Damai', city: 'Kuantan');
      await openConfirm(tester, valid(details: someDetails(mode: SessionMode.physical, location: place)));

      expect(find.text('In-person · Klinik Damai, Kuantan'), findsOneWidget);
      expect(find.text('The full address is shown in your booking after you confirm.'), findsOneWidget);
      expect(find.textContaining('meeting link'), findsNothing);
    });

    testWidgets('no reschedules at all is said plainly', (tester) async {
      await openConfirm(tester, valid(details: someDetails(maxReschedules: 0)));

      expect(find.text("This session can't be rescheduled."), findsOneWidget);
    });
  });

  // ==============================================================================================================
  group('the button and Back', () {
    testWidgets('on only when the box is ticked AND the check is good; unticking turns it off again', (tester) async {
      await openConfirm(tester, valid());

      expect(buttonOn(tester), isFalse);
      await tick(tester);
      expect(buttonOn(tester), isTrue);
      await tick(tester);
      expect(buttonOn(tester), isFalse);
    });

    testWidgets('a check that says "not valid" keeps it off even with the box ticked', (tester) async {
      final api = valid()..previewHandler = ((slot) async => BookingPreview(valid: false, reason: 'time_conflict', details: someDetails()));
      await openConfirm(tester, api);
      await tick(tester);

      expect(find.text('You already have a session at this time.'), findsOneWidget);
      expect(buttonOn(tester), isFalse);
    });

    testWidgets('while submitting: "Confirming…", the tick is locked and Back does nothing', (tester) async {
      final pending = Completer<CreatedBooking>();
      final api = valid()..createHandler = ((key) => pending.future);
      await openConfirm(tester, api);
      await tick(tester);
      await tester.tap(confirmButton());
      await tester.pump();

      expect(find.text('Confirming…'), findsOneWidget);
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(tester.widget<BackButton>(find.byType(BackButton)).onPressed, isNull);

      await tester.binding.handlePopRoute(); // the phone's own back
      await tester.pump();
      expect(find.text('Confirming…'), findsOneWidget, reason: 'still here');

      // A second tap while waiting sends nothing more.
      await tester.tap(find.text('Confirming…'), warnIfMissed: false);
      await tester.pump();
      expect(api.creates.length, 1);

      pending.complete(aCreatedBooking());
      await tester.pumpAndSettle();
      expect(find.text('Booking confirmed'), findsWidgets);
    });

    testWidgets('"Choose a different time" goes back to the picker', (tester) async {
      await openConfirm(tester, valid());
      await tester.tap(find.text('Choose a different time'));
      await tester.pumpAndSettle();

      expect(find.text('Pick a time'), findsOneWidget);
    });
  });

  // ==============================================================================================================
  group('the Idempotency-Key', () {
    testWidgets('one key is made when the screen opens, and it is the one sent', (tester) async {
      final api = valid();
      final opened = await openConfirm(tester, api);
      await confirm(tester);

      expect(opened.keys, ['key-1']);
      expect(api.creates.single.key, 'key-1');
    });

    testWidgets('a timeout is retried automatically with the SAME key, and the booking is made once the answer comes', (tester) async {
      var tries = 0;
      final api = valid()
        ..createHandler = ((key) async {
          if (++tries < 3) throw const NetworkFailure(timedOut: true);

          return aCreatedBooking();
        });
      final opened = await openConfirm(tester, api);
      await confirm(tester);

      expect(api.creates.map((c) => c.key).toSet(), {'key-1'});
      expect(api.creates.length, 3);
      expect(opened.keys, ['key-1'], reason: 'no new key was made');
      expect(find.text('Booking confirmed'), findsWidgets);
    });

    testWidgets('a 5xx is retried with the same key too', (tester) async {
      var tries = 0;
      final api = valid()
        ..createHandler = ((key) async {
          if (++tries == 1) throw const ServerFailure(statusCode: 502);

          return aCreatedBooking();
        });
      await openConfirm(tester, api);
      await confirm(tester);

      expect(api.creates.map((c) => c.key), ['key-1', 'key-1']);
    });

    testWidgets('"still being processed" (409 request_in_progress) is retried with the same key, never a new one', (tester) async {
      var tries = 0;
      final api = valid()
        ..createHandler = ((key) async {
          if (++tries == 1) throw conflict('request_in_progress');

          return aCreatedBooking();
        });
      await openConfirm(tester, api);
      await confirm(tester);

      expect(api.creates.map((c) => c.key), ['key-1', 'key-1']);
      expect(find.text('Booking confirmed'), findsWidgets);
    });

    testWidgets('after a definite refusal (4xx) the NEXT tap is a new request with a NEW key', (tester) async {
      var tries = 0;
      final api = valid()
        ..createHandler = ((key) async {
          if (++tries == 1) throw const RateLimitedFailure(retryAfterSeconds: 5);

          return aCreatedBooking();
        });
      final opened = await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('Too many attempts. Please wait 5 seconds and try again.'), findsOneWidget);
      await tester.tap(find.text('Check again'));
      await tester.pumpAndSettle();
      await tester.tap(confirmButton());
      await tester.pumpAndSettle();

      expect(api.creates.map((c) => c.key), ['key-1', 'key-2']);
      expect(opened.keys, ['key-1', 'key-2']);
    });

    testWidgets('the keys are in memory only: nothing about the booking is written to the token storage', (tester) async {
      final storage = FakeTokenStorage('5|mkp_abc');
      final api = valid();
      tester.view.physicalSize = const Size(800, 1600);
      tester.view.devicePixelRatio = 2;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            ...testOverrides(bookingApi: api, storage: storage, availabilityApi: FakeAvailabilityApi()..handler = ((id, from) async => anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('s1', days: 1), anAvailabilitySlot('s2', days: 1, hour: 14)])]))),
          ],
          child: const MyKaunselApp(),
        ),
      );
      await tester.pumpAndSettle();
      ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp))).read(appRouterProvider).go(AppRoutes.confirmBookingPath('c1', 's1'));
      await tester.pumpAndSettle();
      await confirm(tester);

      expect(await storage.read(), '5|mkp_abc'); // untouched: still just the token
      expect(api.creates.single.key, matches(RegExp(r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')), reason: 'a real version 4 UUID');
    });
  });

  // ==============================================================================================================
  group('after the booking', () {
    testWidgets('success replaces the route: Back does not return to the confirm screen', (tester) async {
      final api = valid();
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('Booking confirmed'), findsWidgets);
      expect(find.text('Placeholder screen. The full booking page comes later.'), findsOneWidget);
      expect(api.creates.single.slot, 's1');
      expect(api.creates.single.mode, 'online');
      expect(api.creates.single.minutes, 50);
      expect(api.creates.single.startsAt, DateTime.utc(2026, 11, 3, 2));

      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.text('Hello, Siti'), findsOneWidget);
      expect(find.text('Confirm booking'), findsNothing);
    });

    testWidgets('the placeholder\'s button goes Home', (tester) async {
      await openConfirm(tester, valid());
      await confirm(tester);
      await tester.tap(find.text('Go to Home'));
      await tester.pumpAndSettle();

      expect(find.text('Hello, Siti'), findsOneWidget);
    });

    testWidgets('no answer after the retries: the message, a way Home, and never "failed"', (tester) async {
      final api = valid()..createHandler = ((key) async => throw const NetworkFailure(timedOut: true));
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text("We couldn't confirm your booking. Check My Bookings before trying again."), findsOneWidget);
      expect(find.text('Go to Home'), findsOneWidget);
      expect(find.textContaining('failed'), findsNothing);
      expect(buttonOn(tester), isFalse, reason: 'no blind second booking');
    });

    testWidgets('coming back to the front with an unknown result resolves it with the same key', (tester) async {
      var alive = false;
      final api = valid()
        ..createHandler = ((key) async {
          if (!alive) throw const NetworkFailure(timedOut: true);

          return aCreatedBooking();
        });
      await openConfirm(tester, api);
      await confirm(tester);
      expect(find.textContaining("We couldn't confirm"), findsOneWidget);

      alive = true;
      for (final lifecycle in [AppLifecycleState.inactive, AppLifecycleState.hidden, AppLifecycleState.paused, AppLifecycleState.hidden, AppLifecycleState.inactive, AppLifecycleState.resumed]) {
        tester.binding.handleAppLifecycleStateChanged(lifecycle);
      }
      await tester.pumpAndSettle();

      expect(find.text('Booking confirmed'), findsWidgets);
      expect(api.creates.map((c) => c.key).toSet(), {'key-1'});
    });

    testWidgets('coming back to the front when nothing is unknown does nothing', (tester) async {
      final api = valid();
      await openConfirm(tester, api);
      for (final lifecycle in [AppLifecycleState.inactive, AppLifecycleState.hidden, AppLifecycleState.paused, AppLifecycleState.hidden, AppLifecycleState.inactive, AppLifecycleState.resumed]) {
        tester.binding.handleAppLifecycleStateChanged(lifecycle);
      }
      await tester.pumpAndSettle();

      expect(api.creates, isEmpty);
    });
  });

  // ==============================================================================================================
  group('every refusal maps to the right words and the right way on', () {
    testWidgets('slot_taken: back to the picker, the time is dimmed and the person is asked to choose another', (tester) async {
      final api = valid()..createHandler = ((key) async => throw conflict('slot_taken'));
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('Pick a time'), findsOneWidget);
      expect(find.text('That time was just taken. Please choose another.'), findsOneWidget);
      expect(find.text('Booked'), findsOneWidget); // the time is now shown as booked
    });

    testWidgets('slot_changed: the summary follows the server, the tick is taken back and the banner says so', (tester) async {
      final fresh = someDetails(hour: 11).toJson();
      fresh['counselor'] = {'id': 'c1', 'display_name': 'Kaunselor Aina Syazwani', 'verified': true};
      final api = valid()..createHandler = ((key) async => throw conflict('slot_changed', {'slot': fresh}));
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('Details changed. Please review and confirm again.'), findsOneWidget);
      expect(find.text('11:00 AM – 11:50 AM, Malaysia time'), findsOneWidget);
      expect(buttonOn(tester), isFalse);
      expect(tester.widget<Checkbox>(find.byType(Checkbox)).value, isFalse);
    });

    testWidgets('slot_changed without usable details falls back to a plain message, never a crash', (tester) async {
      final api = valid()..createHandler = ((key) async => throw conflict('slot_changed', {'slot': 'nonsense'}));
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('Please try again in a moment.'), findsOneWidget);
    });

    testWidgets('time_conflict', (tester) async {
      final api = valid()..createHandler = ((key) async => throw conflict('time_conflict'));
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('You already have a session at this time.'), findsOneWidget);
    });

    testWidgets('upcoming_limit_reached names the limit the server sent', (tester) async {
      final api = valid()..createHandler = ((key) async => throw conflict('upcoming_limit_reached', {'max_upcoming_sessions': 4}));
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('You can have up to 4 upcoming sessions. Cancel or complete one to book another.'), findsOneWidget);
    });

    testWidgets('email_not_verified: the words and a button to verification', (tester) async {
      final api = valid()..createHandler = ((key) async => throw const EmailNotVerifiedFailure('x'));
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('Verify your email to book a session.'), findsOneWidget);
      await tester.tap(find.text('Verify my email'));
      await tester.pumpAndSettle();
      expect(find.textContaining('siti@example.com'), findsWidgets); // the "check your email" page
    });

    testWidgets('counselor_unavailable (404): the words and "Find another counselor"', (tester) async {
      final api = valid()..createHandler = ((key) async => throw const NotFoundFailure());
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('This counselor is no longer available for booking.'), findsOneWidget);
      await tester.tap(find.text('Find another counselor'));
      await tester.pumpAndSettle();
      expect(find.byType(TextField), findsOneWidget); // the search box of the Find tab
    });

    testWidgets('the same 404 on the check', (tester) async {
      final api = valid()..previewHandler = ((slot) async => throw const NotFoundFailure());
      await openConfirm(tester, api);

      expect(find.text('This counselor is no longer available for booking.'), findsOneWidget);
      expect(find.text('Find another counselor'), findsOneWidget);
    });

    testWidgets('429 respects the wait the server gave', (tester) async {
      final api = valid()..createHandler = ((key) async => throw const RateLimitedFailure(retryAfterSeconds: 42));
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('Too many attempts. Please wait 42 seconds and try again.'), findsOneWidget);
    });

    testWidgets('429 without a wait still says something kind', (tester) async {
      final api = valid()..createHandler = ((key) async => throw const RateLimitedFailure());
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('Too many attempts. Please wait a moment and try again.'), findsOneWidget);
    });

    testWidgets('a 5xx that does not clear: a generic message and "Try again" with the SAME key', (tester) async {
      var tries = 0;
      final api = valid()
        ..createHandler = ((key) async {
          if (++tries <= 3) throw const ServerFailure(statusCode: 500);

          return aCreatedBooking();
        });
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.textContaining('Your booking may or may not have been made'), findsOneWidget);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(api.creates.map((c) => c.key).toSet(), {'key-1'});
      expect(find.text('Booking confirmed'), findsWidgets);
    });

    testWidgets('503 (maintenance) is the global maintenance screen, not a message here', (tester) async {
      final api = valid()..createHandler = ((key) async => throw const MaintenanceFailure('Back soon.'));
      final opened = await openConfirm(tester, api);
      await tick(tester);
      // The client reports every failure to the session; the fake API does not, so do what it would.
      opened.container.read(sessionControllerProvider.notifier).handleFailure(const MaintenanceFailure('Back soon.'), hadToken: true);
      await tester.pumpAndSettle();

      expect(find.text('Back soon.'), findsOneWidget);
      expect(find.text('Confirm booking'), findsNothing);
    });

    testWidgets('a 422 shows the server\'s own words', (tester) async {
      final api = valid()..createHandler = ((key) async => throw const ValidationFailure('Please try again later.'));
      await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('Please try again later.'), findsOneWidget);
    });
  });

  // ==============================================================================================================
  group('401: sign in again and come back, without resending', () {
    testWidgets('the person goes to sign-in, then returns to THIS screen, and nothing is sent until they tap again', (tester) async {
      late Opened opened;
      final api = valid();
      api.createHandler = ((key) async {
        opened.container.read(sessionControllerProvider.notifier).handleFailure(const UnauthorizedFailure(), hadToken: true);

        throw const UnauthorizedFailure();
      });
      opened = await openConfirm(tester, api);
      await confirm(tester);

      expect(find.text('Sign in'), findsWidgets); // the sign-in page
      expect(opened.container.read(returnPathProvider), '/book/c1/confirm?slot=s1');
      expect(api.creates.length, 1, reason: 'nothing resent automatically');

      api.createHandler = ((key) async => aCreatedBooking());
      await opened.container.read(sessionControllerProvider.notifier).signIn(token: '9|mkp_new', user: testUser);
      await tester.pumpAndSettle();

      expect(find.text('Before you confirm'), findsOneWidget, reason: 'back on the confirm screen');
      expect(opened.container.read(returnPathProvider), isNull, reason: 'used once');
      expect(api.creates.length, 1, reason: 'still nothing resent');

      await confirm(tester);
      expect(api.creates.length, 2);
      expect(find.text('Booking confirmed'), findsWidgets);
    });

    testWidgets('a return destination that is not an in-app booking route is ignored: the person goes Home', (tester) async {
      final opened = await openConfirm(tester, valid());
      opened.container.read(returnPathProvider.notifier).set('https://evil.example/steal');
      await opened.container.read(sessionControllerProvider.notifier).signOut();
      await tester.pumpAndSettle();
      await opened.container.read(sessionControllerProvider.notifier).signIn(token: '9|mkp_new', user: testUser);
      await tester.pumpAndSettle();

      expect(find.text('Hello, Siti'), findsOneWidget);
    });

    testWidgets('the check getting a 401 also remembers where to come back to', (tester) async {
      late Opened opened;
      final answer = Completer<void>();
      final api = valid()
        ..previewHandler = ((slot) async {
          await answer.future; // the answer takes a moment, like a real network, so the screen is settled when it arrives
          opened.container.read(sessionControllerProvider.notifier).handleFailure(const UnauthorizedFailure(), hadToken: true);

          throw const UnauthorizedFailure();
        });
      opened = await openConfirm(tester, api, settle: false);
      await tester.pumpAndSettle();
      answer.complete();
      await tester.pumpAndSettle();

      expect(find.text('Sign in'), findsWidgets);
      expect(opened.container.read(returnPathProvider), '/book/c1/confirm?slot=s1');
    });
  });

  // ==============================================================================================================
  group('large text and small screens', () {
    for (final scale in [1.0, 2.0]) {
      testWidgets('nothing overflows at text size ×$scale on a small phone, with every state', (tester) async {
        await openConfirm(tester, valid(details: someDetails(upcoming: 2, maxUpcoming: 3, insideWindow: true)), textScale: scale, size: const Size(320, 640));

        expect(tester.takeException(), isNull);
        expect(find.byType(Checkbox), findsOneWidget);
        await tick(tester);
        expect(buttonOn(tester), isTrue);
        expect(tester.takeException(), isNull);
      });
    }

    testWidgets('a problem banner does not overflow at ×2.0 either', (tester) async {
      final api = valid()..createHandler = ((key) async => throw conflict('upcoming_limit_reached', {'max_upcoming_sessions': 3}));
      await openConfirm(tester, api, textScale: 2, size: const Size(320, 640));
      await confirm(tester);

      expect(tester.takeException(), isNull);
    });
  });
}
