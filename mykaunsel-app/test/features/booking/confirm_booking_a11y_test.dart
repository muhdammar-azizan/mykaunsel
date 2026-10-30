import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/features/booking/domain/booking_preview.dart';
import 'package:mykaunsel/features/booking/presentation/confirm_booking_controller.dart';

import '../../support/fakes.dart';

const _tickText = 'I understand this session will not be recorded, per platform policy.';

Future<void> open(WidgetTester tester, FakeBookingApi api, {double textScale = 1, Size size = const Size(400, 800)}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        ...testOverrides(
          bookingApi: api,
          availabilityApi: FakeAvailabilityApi()..handler = ((id, from) async => anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('s1', days: 1)])])),
          storage: FakeTokenStorage('5|mkp_abc'),
        ),
        bookingRetryDelaysProvider.overrideWithValue(const [Duration.zero]),
      ],
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();
  ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp))).read(appRouterProvider).go(AppRoutes.confirmBookingPath('c1', 's1'));
  await tester.pump();
}

FakeBookingApi valid() => FakeBookingApi()
  ..previewHandler = ((slot) async => BookingPreview(valid: true, details: someDetails(slot: slot)))
  ..createHandler = ((key) async => aCreatedBooking());

Finder confirmButton() => find.widgetWithText(FilledButton, 'Confirm booking');

void main() {
  group('for screen readers', () {
    testWidgets('each summary row is its own labelled item: Date, Time, Duration, Type', (tester) async {
      final handle = tester.ensureSemantics();
      await open(tester, valid());
      await tester.pumpAndSettle();

      expect(find.bySemanticsLabel('Date: Tuesday, 3 November 2026'), findsOneWidget);
      expect(find.bySemanticsLabel(RegExp(r'^Time: 10:00.AM to 10:50.AM, Malaysia time$')), findsOneWidget);
      expect(find.bySemanticsLabel('Duration: 50 minutes'), findsOneWidget);
      expect(find.bySemanticsLabel('Type: Online'), findsOneWidget);
      handle.dispose();
    });

    testWidgets('the tick is one labelled checkbox whose state is announced, and the whole row taps', (tester) async {
      final handle = tester.ensureSemantics();
      await open(tester, valid());
      await tester.pumpAndSettle();

      expect(tester.getSemantics(find.text(_tickText)), isSemantics(label: _tickText, hasCheckedState: true, isChecked: false, hasTapAction: true));

      // Tapping the WORDS ticks it (the whole row is the target), and the announced state follows.
      await tester.tap(find.text(_tickText));
      await tester.pumpAndSettle();
      expect(tester.getSemantics(find.text(_tickText)), isSemantics(label: _tickText, hasCheckedState: true, isChecked: true));
      handle.dispose();
    });

    testWidgets('the off button says why: first "Checking availability", then "Tick the box to continue"', (tester) async {
      final handle = tester.ensureSemantics();
      final pending = Completer<BookingPreview>();
      final api = valid()..previewHandler = ((slot) => pending.future);
      await open(tester, api);
      await tester.pump(const Duration(milliseconds: 50));

      expect(tester.getSemantics(confirmButton()), isSemantics(hint: 'Checking availability', isButton: true));

      pending.complete(BookingPreview(valid: true, details: someDetails()));
      await tester.pumpAndSettle();
      expect(tester.getSemantics(confirmButton()), isSemantics(hint: 'Tick the box to continue', isButton: true));

      await tester.tap(find.text(_tickText));
      await tester.pumpAndSettle();
      expect(tester.getSemantics(confirmButton()), isNot(isSemantics(hint: 'Tick the box to continue')));
      handle.dispose();
    });

    testWidgets('with no connection the off button says so', (tester) async {
      final handle = tester.ensureSemantics();
      final api = valid()..previewHandler = ((slot) async => throw const NetworkFailure());
      await open(tester, api);
      await tester.pumpAndSettle();

      expect(tester.getSemantics(confirmButton()), isSemantics(hint: 'No internet connection'));
      handle.dispose();
    });

    testWidgets('banners and errors are live regions', (tester) async {
      final handle = tester.ensureSemantics();
      final pending = Completer<BookingPreview>();
      final api = valid()..previewHandler = ((slot) => pending.future);
      await open(tester, api);
      await tester.pump(const Duration(milliseconds: 50));

      expect(tester.getSemantics(find.text('Checking availability…')), isSemantics(isLiveRegion: true));
      handle.dispose();
      pending.complete(BookingPreview(valid: true, details: someDetails()));
      await tester.pumpAndSettle();
    });

    testWidgets('submitting is announced through the button\'s live region', (tester) async {
      final handle = tester.ensureSemantics();
      final pending = Completer<CreatedBooking>();
      final api = valid()..createHandler = ((key) => pending.future);
      await open(tester, api);
      await tester.pumpAndSettle();
      await tester.tap(find.text(_tickText));
      await tester.pumpAndSettle();
      await tester.tap(confirmButton());
      await tester.pump();

      expect(tester.getSemantics(find.text('Confirming…')), isSemantics(isLiveRegion: true));
      handle.dispose();
      pending.complete(aCreatedBooking());
      await tester.pumpAndSettle();
    });

    testWidgets('the cancellation warning is words, an icon and a spoken "Important", never colour alone', (tester) async {
      final handle = tester.ensureSemantics();
      await open(tester, valid()..previewHandler = ((slot) async => BookingPreview(valid: true, details: someDetails(insideWindow: true))));
      await tester.pumpAndSettle();

      expect(find.byIcon(Icons.warning_amber_rounded), findsOneWidget);
      expect(find.bySemanticsLabel("Important: This session starts in under 24 hours, so it can't be cancelled after booking."), findsOneWidget);
      handle.dispose();
    });
  });

  group('touch targets', () {
    testWidgets('the tick row, the link, the button and Back are at least 44 by 44, and 8 apart where they sit together', (tester) async {
      await open(tester, valid());
      await tester.pumpAndSettle();

      final tickRow = find.ancestor(of: find.text(_tickText), matching: find.byType(InkWell)).first;
      final link = find.widgetWithText(TextButton, 'Choose a different time');

      for (final target in [tickRow, link, confirmButton(), find.byType(BackButton)]) {
        final size = tester.getSize(target);
        expect(size.height, greaterThanOrEqualTo(44), reason: '$target height');
        expect(size.width, greaterThanOrEqualTo(44), reason: '$target width');
      }

      expect(tester.getTopLeft(confirmButton()).dy - tester.getBottomLeft(tickRow).dy, greaterThanOrEqualTo(8));
    });

    testWidgets('they stay that big at text size ×2.0', (tester) async {
      await open(tester, valid(), textScale: 2, size: const Size(320, 640));
      await tester.pumpAndSettle();

      final tickRow = find.ancestor(of: find.text(_tickText), matching: find.byType(InkWell)).first;

      expect(tester.getSize(tickRow).height, greaterThanOrEqualTo(44));
      expect(tester.getSize(confirmButton()).height, greaterThanOrEqualTo(44));
      expect(tester.takeException(), isNull);
    });
  });

  group('reduced motion', () {
    testWidgets('nothing on this screen animates on its own: no repeating animation controllers, only the spinner while submitting', (tester) async {
      await open(tester, valid());
      await tester.pumpAndSettle(); // would time out if something kept animating

      expect(find.byType(CircularProgressIndicator), findsNothing);
    });
  });

  group('the sources of the booking screens', () {
    final files = Directory('lib/features/booking').listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart') && !f.path.endsWith('.g.dart') && !f.path.endsWith('.freezed.dart')).toList();
    final presentation = files.where((f) => f.path.replaceAll(r'\', '/').contains('/presentation/')).toList();

    test('there are files to look at', () {
      expect(presentation, isNotEmpty);
    });

    test('no colour is written in a widget: they come from the theme (core/theme/app_tokens.dart)', () {
      for (final file in presentation) {
        final text = file.readAsStringSync();

        expect(RegExp(r'Color\(0x|Colors\.|Color\.fromARGB|Color\.fromRGBO').hasMatch(text), isFalse, reason: file.path);
      }
    });

    test('no sentence is written in a widget: every string goes through the localization layer', () {
      final literal = RegExp('''(Text|SelectableText)\\(\\s*['"]|(label|hint|tooltip|semanticLabel|semanticsLabel):\\s*['"]''');

      for (final file in presentation) {
        expect(literal.hasMatch(file.readAsStringSync()), isFalse, reason: file.path);
      }
    });

    test('nothing about a booking is stored on the phone, printed or logged', () {
      final forbidden = RegExp(r'shared_preferences|flutter_secure_storage|SharedPreferences|File\(|print\(|debugPrint\(|developer\.log|log\(');

      for (final file in files) {
        expect(forbidden.hasMatch(file.readAsStringSync()), isFalse, reason: file.path);
      }
    });
  });
}
