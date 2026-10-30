import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/features/booking/domain/booking_preview.dart';
import 'package:mykaunsel/features/booking/presentation/confirm_booking_controller.dart';

import '../../support/fakes.dart';

/// A first look at the Confirm booking screen with the main paths (the full set is the next step). It opens the screen the way a sign-in
/// redirect does: from the two ids in the address alone.
Future<ProviderContainer> openConfirm(WidgetTester tester, FakeBookingApi api, {List<String>? keys}) async {
  tester.view.physicalSize = const Size(800, 1600);
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  var n = 0;

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        ...testOverrides(
          bookingApi: api,
          // The picker sits under the confirm screen (the address has the counselor in it), so it loads too.
          availabilityApi: FakeAvailabilityApi()..handler = ((id, from) async => anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('s1', days: 1)])])),
          storage: FakeTokenStorage('5|mkp_abc'),
        ),
        idempotencyKeyFactoryProvider.overrideWithValue(() {
          final key = 'key-${++n}';
          keys?.add(key);

          return key;
        }),
        bookingRetryDelaysProvider.overrideWithValue(const [Duration.zero, Duration.zero]),
      ],
      child: const MyKaunselApp(),
    ),
  );
  await tester.pumpAndSettle();

  final container = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
  container.read(appRouterProvider).go(AppRoutes.confirmBookingPath('c1', 's1'));
  await tester.pump();

  return container;
}

FakeBookingApi valid({BookingDetails? details}) => FakeBookingApi()
  ..previewHandler = ((slot) async => BookingPreview(valid: true, details: details ?? someDetails(slot: slot)))
  ..createHandler = ((key) async => aCreatedBooking());

/// The tick sits below the rules, so on a normal phone it is reached by scrolling.
Future<void> tick(WidgetTester tester) async {
  final row = find.text('I understand this session will not be recorded, per platform policy.');

  await tester.ensureVisible(row);
  await tester.pumpAndSettle();
  await tester.tap(row);
  await tester.pumpAndSettle();
}

Finder confirmButton() => find.widgetWithText(FilledButton, 'Confirm booking');

void main() {
  testWidgets('restores itself from the ids, shows the summary and the server rules, and the button waits for the tick', (tester) async {
    final api = valid();
    await openConfirm(tester, api);
    await tester.pumpAndSettle();

    expect(api.previews, ['s1']);
    expect(find.text('Kaunselor Aina Syazwani'), findsOneWidget);
    expect(find.text('Tuesday, 3 November 2026'), findsOneWidget);
    expect(find.text('10:00 AM – 10:50 AM, Malaysia time'), findsOneWidget);
    expect(find.text('50 minutes'), findsOneWidget);
    expect(find.text('You can cancel up to 24 hours before the session.'), findsOneWidget);
    expect(find.text('You can reschedule up to 2 times.'), findsOneWidget);
    expect(find.text('Your meeting link will appear in your booking 15 minutes before the session.'), findsOneWidget);
    expect(tester.widget<FilledButton>(confirmButton()).onPressed, isNull);

    await tick(tester);
    await tester.pumpAndSettle();
    expect(tester.widget<FilledButton>(confirmButton()).onPressed, isNotNull);
  });

  testWidgets('a good booking replaces the screen: Back does not return to it', (tester) async {
    final api = valid();
    await openConfirm(tester, api);
    await tester.pumpAndSettle();
    await tick(tester);
    await tester.pumpAndSettle();
    await tester.tap(confirmButton());
    await tester.pumpAndSettle();

    expect(find.text('Booking confirmed'), findsWidgets);
    expect(api.creates.single.slot, 's1');
    expect(api.creates.single.mode, 'online');

    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.text('Hello, Siti'), findsOneWidget); // Home, not the confirm screen
  });

  testWidgets('a timeout is retried with the SAME key and, still unknown, is never reported as failed', (tester) async {
    final keys = <String>[];
    final api = valid()..createHandler = ((key) async => throw const NetworkFailure(timedOut: true));
    await openConfirm(tester, api, keys: keys);
    await tester.pumpAndSettle();
    await tick(tester);
    await tester.pumpAndSettle();
    await tester.tap(confirmButton());
    await tester.pumpAndSettle();

    expect(api.creates.map((c) => c.key).toSet(), {'key-1'}, reason: 'one key for every automatic try');
    expect(api.creates.length, 3);
    expect(find.text("We couldn't confirm your booking. Check My Bookings before trying again."), findsOneWidget);
    expect(find.text('Go to Home'), findsOneWidget);
  });

  testWidgets('after a definite refusal the next tap uses a NEW key', (tester) async {
    final api = valid()..createHandler = ((key) async => throw const ConflictFailure(code: 'time_conflict'));
    await openConfirm(tester, api);
    await tester.pumpAndSettle();
    await tick(tester);
    await tester.pumpAndSettle();
    await tester.tap(confirmButton());
    await tester.pumpAndSettle();

    expect(find.text('You already have a session at this time.'), findsOneWidget);
    expect(api.creates.single.key, 'key-1');
  });

  testWidgets('a slot the counselor changed updates the summary, takes the tick back and says so', (tester) async {
    final fresh = someDetails(hour: 11).toJson();
    fresh['counselor'] = {'id': 'c1', 'display_name': 'Kaunselor Aina Syazwani', 'verified': true};
    final api = valid()..createHandler = ((key) async => throw ConflictFailure(code: 'slot_changed', body: {'slot': fresh}));
    await openConfirm(tester, api);
    await tester.pumpAndSettle();
    await tick(tester);
    await tester.pumpAndSettle();
    await tester.tap(confirmButton());
    await tester.pumpAndSettle();

    expect(find.text('Details changed. Please review and confirm again.'), findsOneWidget);
    expect(find.text('11:00 AM – 11:50 AM, Malaysia time'), findsOneWidget);
    expect(tester.widget<FilledButton>(confirmButton()).onPressed, isNull);
  });

  testWidgets('no connection keeps the screen, shows the banner and keeps the button off', (tester) async {
    final api = FakeBookingApi()..previewHandler = ((slot) async => throw const NetworkFailure());
    await openConfirm(tester, api);
    await tester.pumpAndSettle();

    expect(find.text('No internet connection'), findsOneWidget);
    expect(tester.widget<FilledButton>(confirmButton()).onPressed, isNull);
  });

  testWidgets('a time that is gone goes back to the picker', (tester) async {
    final api = FakeBookingApi()..previewHandler = ((slot) async => const BookingPreview(valid: false, reason: 'slot_taken'));
    await openConfirm(tester, api);
    await tester.pumpAndSettle();

    expect(find.text('Pick a time'), findsOneWidget);
    expect(find.text('Confirm booking'), findsNothing);
  });
}
