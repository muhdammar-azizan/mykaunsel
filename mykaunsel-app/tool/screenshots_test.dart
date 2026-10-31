// Draws the main screens to PNG files so they can be LOOKED AT without a phone or an emulator.
//
//   flutter test tool/screenshots_test.dart --dart-define=SHOTS_DIR=C:/some/folder
//
// This is not part of the normal test run (it lives in tool/, not test/). The picture is the app's own widgets drawn by the test engine with
// the app's own bundled font: close to a phone, but not a phone (no real keyboard, no system bars, no TalkBack).
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/features/bookings/domain/my_booking.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/features/booking/domain/availability.dart' show SlotLocation;
import 'package:mykaunsel/features/chat/domain/chat.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';
import 'package:mykaunsel/features/platform_status/domain/platform_status.dart';
import 'package:mykaunsel/features/requests/domain/time_request.dart';

import '../test/support/fakes.dart';

const _dir = String.fromEnvironment('SHOTS_DIR', defaultValue: 'build/shots');
final _agreed = testUser.copyWith(aiConsent: true);

Future<ProviderContainer> start(
  WidgetTester tester, {
  bool signedIn = true,
  bool consent = true,
  FakeChatApi? chat,
  FakeMyBookingsApi? bookings,
  FakeRequestsApi? requests,
  FakeAvailabilityApi? availability,
  double textScale = 1,
  Size size = const Size(400, 800),
}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(
        chatApi: chat,
        myBookingsApi: bookings,
        requestsApi: requests,
        availabilityApi: availability,
        authApi: FakeAuthApi(me: () async => consent ? _agreed : testUser),
        statusApi: signedIn ? null : null,
        storage: signedIn ? FakeTokenStorage('5|mkp_abc') : FakeTokenStorage(),
      ),
      child: MediaQuery(
        data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)),
        child: RepaintBoundary(key: _boundary, child: const MyKaunselApp()),
      ),
    ),
  );
  await tester.pumpAndSettle();

  return ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
}

final _boundary = GlobalKey();

Future<void> go(WidgetTester tester, ProviderContainer c, String path) async {
  c.read(appRouterProvider).go(path);
  await tester.pumpAndSettle();
}

Future<void> shot(WidgetTester tester, String name) async {
  await tester.pump(const Duration(milliseconds: 400));
  await tester.runAsync(() async {
    final boundary = tester.renderObject<RenderRepaintBoundary>(find.byKey(_boundary));
    final ui.Image image = await boundary.toImage(pixelRatio: 1.5);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);

    await Directory(_dir).create(recursive: true);
    await File('$_dir/$name.png').writeAsBytes(bytes!.buffer.asUint8List());
  });
}

Future<void> say(WidgetTester tester, String text) async {
  await tester.enterText(find.byType(TextField), text);
  await tester.pump();
  await tester.tap(find.byTooltip('Send'));
  await tester.pumpAndSettle();
}

Future<void> scrollTo(WidgetTester tester, Finder finder) async {
  await tester.scrollUntilVisible(finder, 200, scrollable: find.byWidgetPredicate((w) => w is Scrollable && w.axis == Axis.vertical).first);
  await tester.pumpAndSettle();
}

FakeAvailabilityApi times() => FakeAvailabilityApi()
  ..handler = ((id, from) async => anAvailability([
        anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1), anAvailabilitySlot('b', days: 1, hour: 14), anAvailabilitySlot('p', days: 1, hour: 16, mode: SessionMode.physical, location: const SlotLocation(name: 'Klinik Damai', city: 'Kuantan'))]),
        anAvailabilityDay(3, [anAvailabilitySlot('c', days: 3, hour: 9), anAvailabilitySlot('d', days: 3, hour: 11)]),
      ], modes: const [SessionMode.online, SessionMode.physical]));

/// The test engine draws every letter as a block unless the fonts are loaded: the app's own, and the icons from the Flutter SDK.
Future<void> loadFonts() async {
  final app = FontLoader('PlusJakartaSans')..addFont(rootBundle.load('assets/fonts/PlusJakartaSans.ttf'));
  await app.load();

  final sdk = Platform.environment['FLUTTER_ROOT'] ?? 'C:/src/flutter';
  final icons = FontLoader('MaterialIcons')..addFont(File('$sdk/bin/cache/artifacts/material_fonts/materialicons-regular.otf').readAsBytes().then((b) => ByteData.view(b.buffer)));
  await icons.load();
}

void main() {
  setUpAll(loadFonts);

  testWidgets('home', (tester) async {
    await start(tester);
    await shot(tester, '01-home');
  });

  testWidgets('chat: the gate', (tester) async {
    final c = await start(tester, consent: false);
    await go(tester, c, AppRoutes.chat);
    await shot(tester, '02-chat-gate');
  });

  testWidgets('chat: empty, a conversation, a worrying message', (tester) async {
    final chat = FakeChatApi()
      ..sendHandler = ((text, session, id) async => text.contains('live')
          ? aChatResult(text: text, session: session, crisis: true, helplines: fallbackHelplines, reply: 'Thank you for telling me. You matter, and you do not have to face this alone. The phone lines are shown just below.', counselors: [aCounselor()])
          : aChatResult(text: text, session: session, reply: 'That sounds like a lot to carry. What worries you most about the exams?'));
    final c = await start(tester, chat: chat);
    await go(tester, c, AppRoutes.chat);
    await shot(tester, '03-chat-empty');
    await say(tester, 'I am so stressed about my exams next week.');
    await shot(tester, '04-chat-conversation');
    await say(tester, 'Sometimes I feel I do not want to live anymore.');
    await shot(tester, '05-chat-crisis-top');
    await scrollTo(tester, find.text('Continue chatting'));
    await shot(tester, '06-chat-crisis-bottom');
  });

  testWidgets('chat: failures', (tester) async {
    var n = 0;
    final chat = FakeChatApi()
      ..sendHandler = ((text, session, id) async {
        n++;
        if (n == 1) return aChatResult(text: text, session: session, unavailable: true);
        throw const ServerFailure(statusCode: 500);
      });
    final c = await start(tester, chat: chat);
    await go(tester, c, AppRoutes.chat);
    await say(tester, 'Hello, are you there?');
    await say(tester, 'Hello again');
    await shot(tester, '07-chat-failures');
  });

  testWidgets('chat: help sheet and history', (tester) async {
    final chat = FakeChatApi()
      ..historyHandler = ((page) async => ChatHistoryPage(items: [
            aChatSession(id: 'a', ended: true, preview: 'I am so stressed about my exams next week.', emotion: 'anxiety'),
            aChatSession(id: 'b', ended: true, preview: 'A rough week with my family', days: -3, emotion: 'sadness'),
            aChatSession(id: 'c', ended: true, preview: 'Feeling better today', days: -20, emotion: 'hope'),
          ], page: 1, lastPage: 1));
    final c = await start(tester, chat: chat);
    await go(tester, c, AppRoutes.chat);
    await tester.tap(find.byTooltip('Need help now?'));
    await tester.pumpAndSettle();
    await shot(tester, '08-chat-help-sheet');
    await tester.tapAt(const Offset(10, 10));
    await tester.pumpAndSettle();
    await go(tester, c, AppRoutes.chatHistory);
    await shot(tester, '09-chat-history');
  });

  testWidgets('account', (tester) async {
    final c = await start(tester);
    await go(tester, c, AppRoutes.account);
    await shot(tester, '10-account');
  });

  testWidgets('sign up', (tester) async {
    final c = await start(tester, signedIn: false);
    await go(tester, c, AppRoutes.signUp);
    await scrollTo(tester, find.byType(CheckboxListTile));
    await shot(tester, '11-signup-consent');
  });

  testWidgets('bookings: list, details, cancel, reschedule', (tester) async {
    final bookings = FakeMyBookingsApi()
      ..listHandler = ((scope, page) async => scope.name == 'upcoming'
          ? BookingsPage(items: [aMyBooking(id: 'bk1'), aMyBooking(id: 'bk2', days: 3, hour: 14, mode: SessionMode.physical, location: const SlotLocation(name: 'Klinik Damai', city: 'Kuantan'))], page: 1, lastPage: 1)
          : const BookingsPage(items: [], page: 1, lastPage: 1));
    final c = await start(tester, bookings: bookings, availability: times());
    await go(tester, c, AppRoutes.bookings);
    await shot(tester, '12-bookings-upcoming');
    await go(tester, c, AppRoutes.bookingPath('bk1'));
    await shot(tester, '13-booking-details-top');
    await scrollTo(tester, find.text('Cancel booking'));
    await shot(tester, '14-booking-details-actions');
    await tester.tap(find.widgetWithText(OutlinedButton, 'Cancel booking'));
    await tester.pumpAndSettle();
    await shot(tester, '15-cancel-sheet');
    await tester.tap(find.text('Keep booking'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Reschedule'));
    await tester.pumpAndSettle();
    await shot(tester, '16-reschedule-picker');
    await tester.tap(findText('2:00 PM'));
    await tester.pumpAndSettle();
    await tester.tap(find.byType(FilledButton).last);
    await tester.pumpAndSettle();
    await shot(tester, '17-reschedule-sheet');
  });

  testWidgets('requests: ask, list, details', (tester) async {
    final requests = FakeRequestsApi()
      ..listHandler = ((page) async => RequestsPage(items: [
            aTimeRequest(id: 'a', message: 'About exams'),
            aTimeRequest(id: 'b', status: RequestState.approved, days: 5, bookingId: 'bk9'),
            aTimeRequest(id: 'c', status: RequestState.rejected, days: 6, reason: 'I am away that week.'),
          ], page: 1, lastPage: 1))
      ..detailHandler = ((id) async => aTimeRequest(id: id, message: 'About exams, a little long so that it wraps on a small screen.'));
    final c = await start(tester, requests: requests);
    await go(tester, c, '/request/c1');
    await shot(tester, '18-ask-for-a-time');
    await go(tester, c, AppRoutes.bookings);
    await tester.tap(find.widgetWithText(Tab, 'Requests'));
    await tester.pumpAndSettle();
    await shot(tester, '19-requests-list');
    await go(tester, c, AppRoutes.requestPath('a'));
    await shot(tester, '20-request-details');
  });

  testWidgets('small phone, big text', (tester) async {
    final chat = FakeChatApi()
      ..sendHandler = ((text, session, id) async => aChatResult(text: text, session: session, crisis: true, helplines: fallbackHelplines, reply: 'Thank you for telling me. You matter.'));
    final c = await start(tester, chat: chat, size: const Size(360, 640), textScale: 1.6);
    await go(tester, c, AppRoutes.chat);
    await say(tester, 'I do not want to live anymore.');
    await scrollTo(tester, find.text('Continue chatting'));
    await shot(tester, '21-small-big-text-crisis');
  });

  testWidgets('choose account type, notifications and privacy', (tester) async {
    final c = await start(tester, signedIn: false);
    await go(tester, c, AppRoutes.chooseAccountType);
    await shot(tester, '22-choose-account-type');
    await tester.tap(find.text('Organization'));
    await tester.pumpAndSettle();
    await shot(tester, '23-signup-link-sheet');
  });

  testWidgets('choose account type: small phone, one kind closed', (tester) async {
    tester.view.physicalSize = const Size(320, 640) * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      ProviderScope(
        overrides: testOverrides(statusApi: FakePlatformStatusApi(() async => status().copyWith(registration: {'counselor': const RegistrationStatus(open: false, message: 'Closed for now.')}))),
        child: RepaintBoundary(key: _boundary, child: const MyKaunselApp()),
      ),
    );
    await tester.pumpAndSettle();
    final c = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
    await go(tester, c, AppRoutes.chooseAccountType);
    await shot(tester, '24-choose-small-closed');
  });

  testWidgets('notifications and privacy', (tester) async {
    final c = await start(tester);
    await go(tester, c, AppRoutes.accountNotifications);
    await shot(tester, '25-notifications');
    await go(tester, c, AppRoutes.accountPrivacy);
    await shot(tester, '26-privacy');
  });

  testWidgets('sign in on a small phone', (tester) async {
    await start(tester, signedIn: false, size: const Size(320, 640));
    await shot(tester, '27-sign-in-small');
  });

  testWidgets('sign in to the choice by tapping, small phone', (tester) async {
    await start(tester, signedIn: false, size: const Size(320, 640));
    await tester.ensureVisible(find.text('Create an account'));
    await tester.tap(find.text('Create an account'));
    await tester.pumpAndSettle();
    await shot(tester, '28-tapped-small');
    // ignore: avoid_print
    print('EXC: ${tester.takeException()}');
  });

  testWidgets('privacy with a support address on a small phone with big text', (tester) async {
    tester.view.physicalSize = const Size(320, 640) * 2;
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      ProviderScope(
        overrides: testOverrides(statusApi: FakePlatformStatusApi(() async => status().copyWith(supportEmail: 'help@mykaunsel.example')), storage: FakeTokenStorage('5|mkp_abc')),
        child: MediaQuery(data: const MediaQueryData(size: Size(320, 640), textScaler: TextScaler.linear(2)), child: RepaintBoundary(key: _boundary, child: const MyKaunselApp())),
      ),
    );
    await tester.pumpAndSettle();
    final c = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
    await go(tester, c, AppRoutes.accountPrivacy);
    await scrollTo(tester, find.text('help@mykaunsel.example'));
    await shot(tester, '29-privacy-small-big');
    // ignore: avoid_print
    print('EXC: ${tester.takeException()}');
  });
}
