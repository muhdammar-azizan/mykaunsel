import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/features/booking/domain/availability.dart';
import 'package:mykaunsel/features/booking/presentation/pick_slot_screen.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';

import '../../support/fakes.dart';

/// Opens the app signed in and walks to "Pick a slot" the way a person does: Find → counselor → "See available times".
Future<void> openPicker(
  WidgetTester tester,
  FakeAvailabilityApi api, {
  double textScale = 1,
  Size size = const Size(400, 800),
  Duration deviceOffset = const Duration(hours: 8),
}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        ...testOverrides(availabilityApi: api, storage: FakeTokenStorage('5|mkp_abc')),
        deviceUtcOffsetProvider.overrideWithValue(() => deviceOffset),
      ],
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();
  await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('Find')));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Kaunselor Aina Syazwani'));
  await tester.pumpAndSettle();
  await tester.scrollUntilVisible(find.text('See available times'), 300, scrollable: find.byType(Scrollable).last);
  await tester.drag(find.byType(Scrollable).last, const Offset(0, -150)); // clear of the bottom bar on a small phone
  await tester.pumpAndSettle();
  await tester.tap(find.text('See available times'));
  await tester.pump();
}

FakeAvailabilityApi answering(Availability answer) => FakeAvailabilityApi()..handler = (_, _) async => answer;

Availability twoDays({List<SessionMode> modes = const [SessionMode.online]}) => anAvailability([
      anAvailabilityDay(1, [
        anAvailabilitySlot('a', days: 1),
        anAvailabilitySlot('b', days: 1, hour: 11, state: SlotState.booked),
        anAvailabilitySlot('p', days: 1, hour: 15, mode: SessionMode.physical, location: const SlotLocation(name: 'Klinik Damai', city: 'Kuantan')),
      ]),
      anAvailabilityDay(3, [anAvailabilitySlot('c', days: 3, hour: 9)]),
    ], modes: modes);

Finder continueButton() => find.byType(FilledButton);

void main() {
  group('the way in', () {
    testWidgets('the counselor page offers it, and the screen names the counselor', (tester) async {
      final api = answering(twoDays());
      await openPicker(tester, api);
      await tester.pumpAndSettle();

      expect(api.calls.single.id, 'c1');
      expect(api.calls.single.from, isNull);
      expect(find.text('Pick a time'), findsOneWidget);
      expect(find.text('Kaunselor Aina Syazwani'), findsWidgets);
    });

    testWidgets('while the first answer is awaited a skeleton shows, not a spinner-only screen', (tester) async {
      final pending = Completer<Availability>();
      final api = FakeAvailabilityApi()..handler = (_, _) => pending.future;
      await openPicker(tester, api);
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.bySemanticsLabel('Loading more…'), findsOneWidget);
      expect(find.text('Kaunselor Aina Syazwani'), findsWidgets); // the header is already there

      pending.complete(twoDays());
      await tester.pumpAndSettle();
      expect(find.bySemanticsLabel('Loading more…'), findsNothing);
    });
  });

  group('picking', () {
    testWidgets('starts on the nearest day with nothing picked and Continue switched off', (tester) async {
      await openPicker(tester, answering(twoDays()));
      await tester.pumpAndSettle();

      expect(find.text('Times on Tuesday 3 November'), findsOneWidget);
      expect(findText('10:00 AM'), findsOneWidget);
      expect(find.text('Pick a time to continue'), findsOneWidget);
      expect(tester.widget<FilledButton>(continueButton()).onPressed, isNull);
    });

    testWidgets('a booked time is shown as booked and cannot be picked', (tester) async {
      await openPicker(tester, answering(twoDays(modes: const [SessionMode.online, SessionMode.physical])));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Online'));
      await tester.pumpAndSettle();

      expect(find.text('Booked'), findsOneWidget);
      await tester.tap(findText('11:00 AM'));
      await tester.pumpAndSettle();
      expect(tester.widget<FilledButton>(continueButton()).onPressed, isNull);
    });

    testWidgets('picking a time shows it on the button, picking another replaces it, and a check mark marks the pick', (tester) async {
      await openPicker(tester, answering(twoDays(modes: const [SessionMode.online, SessionMode.physical])));
      await tester.pumpAndSettle();
      await tester.tap(findText('10:00 AM'));
      await tester.pumpAndSettle();

      expect(findTextContaining('Continue with Tue 3 Nov, 10:00 AM'), findsOneWidget);
      expect(find.byIcon(Icons.check_circle), findsOneWidget);

      await tester.tap(find.text('In-person'));
      await tester.pumpAndSettle();
      expect(find.text('Pick a time to continue'), findsOneWidget); // changing the mode cleared it

      await tester.tap(findText('3:00 PM'));
      await tester.pumpAndSettle();
      expect(findTextContaining('Continue with Tue 3 Nov, 3:00 PM'), findsOneWidget);
    });

    testWidgets('an in-person time shows where, by name and city only', (tester) async {
      await openPicker(tester, answering(twoDays(modes: const [SessionMode.online, SessionMode.physical])));
      await tester.pumpAndSettle();
      await tester.tap(find.text('In-person'));
      await tester.pumpAndSettle();

      expect(findTextContaining('Klinik Damai, Kuantan'), findsOneWidget);
    });

    testWidgets('choosing another date shows its times and clears the pick', (tester) async {
      await openPicker(tester, answering(twoDays()));
      await tester.pumpAndSettle();
      await tester.tap(findText('10:00 AM'));
      await tester.pumpAndSettle();
      await tester.tap(find.bySemanticsLabel(RegExp('^Thursday 5 November')));
      await tester.pumpAndSettle();

      expect(find.text('Times on Thursday 5 November'), findsOneWidget);
      expect(findText('9:00 AM'), findsOneWidget);
      expect(find.text('Pick a time to continue'), findsOneWidget);
    });

    testWidgets('Continue opens the next step with what was picked, and coming back keeps the pick', (tester) async {
      await openPicker(tester, answering(twoDays()));
      await tester.pumpAndSettle();
      await tester.tap(findText('10:00 AM'));
      await tester.pumpAndSettle();
      await tester.tap(continueButton());
      await tester.pumpAndSettle();

      expect(find.text('Confirm booking'), findsOneWidget);
      expect(find.text('Booking confirmation is coming next.'), findsOneWidget);

      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(findTextContaining('Continue with Tue 3 Nov, 10:00 AM'), findsOneWidget);
    });

    testWidgets('only one mode offered: no toggle', (tester) async {
      await openPicker(tester, answering(twoDays()));
      await tester.pumpAndSettle();

      expect(find.byType(ChoiceChip), findsNothing);
    });

    testWidgets('a mode with nothing on the day offers the other one', (tester) async {
      final answer = anAvailability([
        anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1), anAvailabilitySlot('p', days: 1, hour: 15, mode: SessionMode.physical)]),
        anAvailabilityDay(2, [anAvailabilitySlot('b', days: 2)]),
      ], modes: const [SessionMode.online, SessionMode.physical]);
      await openPicker(tester, answering(answer));
      await tester.pumpAndSettle();
      await tester.tap(find.bySemanticsLabel(RegExp('^Wednesday 4 November')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('In-person'));
      await tester.pumpAndSettle();

      // In-person has nothing on 4 Nov, so the screen moves to the nearest day that has something.
      expect(find.text('Times on Tuesday 3 November'), findsOneWidget);
    });
  });

  group('when it goes wrong', () {
    testWidgets('no free times at all says so and offers the way back', (tester) async {
      await openPicker(tester, answering(anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1, state: SlotState.booked)])])));
      await tester.pumpAndSettle();

      expect(find.text('No times available'), findsOneWidget);
      expect(find.text('Back to counselor'), findsOneWidget);
      expect(find.text('Pick a time to continue'), findsNothing);
    });

    testWidgets('a counselor who cannot be booked says so, without a reason', (tester) async {
      final api = FakeAvailabilityApi()..handler = (_, _) async => throw const NotFoundFailure();
      await openPicker(tester, api);
      await tester.pumpAndSettle();

      expect(find.text('Counselor not available'), findsOneWidget);
      expect(find.text('Try again'), findsNothing);
    });

    testWidgets('no connection offers Try again, and it asks again', (tester) async {
      var attempts = 0;
      final api = FakeAvailabilityApi()
        ..handler = (_, _) async {
          attempts++;
          if (attempts == 1) throw const NetworkFailure();

          return twoDays();
        };
      await openPicker(tester, api);
      await tester.pumpAndSettle();

      expect(find.text('Something went wrong'), findsOneWidget);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(findText('10:00 AM'), findsOneWidget);
      expect(api.calls.length, 2);
    });

    testWidgets('an unverified email says so and offers to verify', (tester) async {
      final api = FakeAvailabilityApi()..handler = (_, _) async => throw const EmailNotVerifiedFailure('Verify first.');
      await openPicker(tester, api);
      await tester.pumpAndSettle();

      expect(find.text('Verify your email first'), findsOneWidget);
      await tester.tap(find.text('Verify my email'));
      await tester.pumpAndSettle();
      expect(find.textContaining('siti@example.com'), findsWidgets); // the "check your email" page
    });

    testWidgets('too many requests holds Try again back until the wait is over', (tester) async {
      final api = FakeAvailabilityApi()..handler = (_, _) async => throw const RateLimitedFailure(retryAfterSeconds: 30);
      await openPicker(tester, api);
      await tester.pumpAndSettle();

      expect(tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'Try again')).onPressed, isNull);
      expect(api.calls.length, 1);
    });

    testWidgets('a failed refresh keeps the times on screen and says so', (tester) async {
      var attempts = 0;
      final api = FakeAvailabilityApi()
        ..handler = (_, _) async {
          attempts++;
          if (attempts == 1) return twoDays();
          throw const NetworkFailure();
        };
      await openPicker(tester, api);
      await tester.pumpAndSettle();
      await tester.tap(findText('10:00 AM'));
      await tester.pumpAndSettle();
      await tester.fling(find.byType(ListView).last, const Offset(0, 400), 1000);
      await tester.pumpAndSettle();

      expect(find.text('Could not refresh. Showing the last times we loaded.'), findsOneWidget);
      expect(findText('10:00 AM'), findsOneWidget);
      expect(findTextContaining('Continue with Tue 3 Nov, 10:00 AM'), findsOneWidget); // the pick survived
    });

    testWidgets('refresh that finds the picked time gone drops it and tells the person', (tester) async {
      var attempts = 0;
      final api = FakeAvailabilityApi()
        ..handler = (_, _) async {
          attempts++;
          if (attempts == 1) return twoDays();

          return anAvailability([
            anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1, state: SlotState.booked), anAvailabilitySlot('d', days: 1, hour: 13)]),
          ]);
        };
      await openPicker(tester, api);
      await tester.pumpAndSettle();
      await tester.tap(findText('10:00 AM'));
      await tester.pumpAndSettle();
      await tester.fling(find.byType(ListView).last, const Offset(0, 400), 1000);
      await tester.pumpAndSettle();

      expect(find.text('Your selected time is no longer available.'), findsOneWidget);
      expect(find.text('Pick a time to continue'), findsOneWidget);
    });

    testWidgets('loading later dates asks from the date the server named', (tester) async {
      final api = FakeAvailabilityApi()
        ..handler = (_, from) async => from == null
            ? anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1)])], nextFrom: '2026-11-16')
            : anAvailability([anAvailabilityDay(14, [anAvailabilitySlot('z', days: 14)])]);
      await openPicker(tester, api);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Show later dates'));
      await tester.pumpAndSettle();

      expect(api.calls.last.from, '2026-11-16');
      expect(find.text('Show later dates'), findsNothing); // the horizon is reached
    });
  });

  group('time zone', () {
    testWidgets('a phone in another zone is told the times are Malaysian time', (tester) async {
      await openPicker(tester, answering(twoDays()), deviceOffset: const Duration(hours: 1));
      await tester.pumpAndSettle();

      expect(findTextContaining('Times are in Malaysian time (UTC+8)'), findsOneWidget);
      expect(findText('10:00 AM'), findsOneWidget); // still the counselor's wall clock
    });

    testWidgets('a phone in Malaysia is not told', (tester) async {
      await openPicker(tester, answering(twoDays()));
      await tester.pumpAndSettle();

      expect(findTextContaining('Times are in Malaysian time (UTC+8)'), findsNothing);
    });
  });

  group('large text and small screens', () {
    for (final scale in [1.0, 2.0]) {
      testWidgets('does not overflow at text size ×$scale on a small phone', (tester) async {
        await openPicker(tester, answering(twoDays(modes: const [SessionMode.online, SessionMode.physical])), textScale: scale, size: const Size(320, 640));
        await tester.pumpAndSettle();
        await tester.scrollUntilVisible(findText('10:00 AM'), 200, scrollable: find.byWidgetPredicate((w) => w is Scrollable && w.axis == Axis.vertical));
        await tester.tap(findText('10:00 AM'));
        await tester.pumpAndSettle();

        expect(tester.takeException(), isNull);
        expect(continueButton(), findsOneWidget);
      });
    }
  });

  group('for screen readers', () {
    testWidgets('a time reads as time, length, type and state; a booked one says booked and is not tappable', (tester) async {
      final handle = tester.ensureSemantics();
      await openPicker(tester, answering(twoDays(modes: const [SessionMode.online, SessionMode.physical])));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Online'));
      await tester.pumpAndSettle();

      expect(find.bySemanticsLabel(RegExp(r'^10:00.AM, 60 minutes, Online$')), findsOneWidget);
      expect(find.bySemanticsLabel(RegExp(r'^11:00.AM, 60 minutes, Online, Booked$')), findsOneWidget);

      await tester.tap(findText('10:00 AM'));
      await tester.pumpAndSettle();
      expect(find.bySemanticsLabel(RegExp(r'^10:00.AM, 60 minutes, Online, Selected$')), findsOneWidget);
      handle.dispose();
    });

    testWidgets('a date reads as weekday, day, month and how many times', (tester) async {
      final handle = tester.ensureSemantics();
      await openPicker(tester, answering(twoDays()));
      await tester.pumpAndSettle();

      expect(find.bySemanticsLabel('Thursday 5 November, 1 time'), findsOneWidget);
      handle.dispose();
    });
  });
}
