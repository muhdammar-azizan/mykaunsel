import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/providers.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/features/booking/domain/availability.dart' show SlotLocation;
import 'package:mykaunsel/features/bookings/domain/my_booking.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';

import '../../support/fakes.dart';

class Opened {
  Opened(this.container, this.opened);

  final ProviderContainer container;
  final List<Uri> opened;
}

/// Opens the app signed in and goes to the Bookings tab.
Future<Opened> openBookings(
  WidgetTester tester,
  FakeMyBookingsApi api, {
  double textScale = 1,
  Size size = const Size(400, 800),
  bool opens = true,
  String path = AppRoutes.bookings,
}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  final opened = <Uri>[];

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        ...testOverrides(myBookingsApi: api, storage: FakeTokenStorage('5|mkp_abc')),
        urlOpenerProvider.overrideWithValue((uri) async {
          opened.add(uri);

          return opens;
        }),
      ],
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();

  final container = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
  container.read(appRouterProvider).go(path);
  await tester.pumpAndSettle();

  return Opened(container, opened);
}

FakeMyBookingsApi listing({List<MyBooking> upcoming = const [], List<MyBooking> past = const []}) => FakeMyBookingsApi()
  ..listHandler = ((scope, page) async => BookingsPage(items: scope == BookingsScope.upcoming ? upcoming : past, page: 1, lastPage: 1));

Future<void> showPast(WidgetTester tester) async {
  await tester.tap(find.widgetWithText(Tab, 'Past'));
  await tester.pumpAndSettle();
}

void main() {
  group('the tab', () {
    testWidgets('the bottom bar has Bookings between Chat and Account, and it opens the list', (tester) async {
      await openBookings(tester, listing(), path: AppRoutes.home);

      final labels = tester.widgetList<NavigationDestination>(find.byType(NavigationDestination)).map((d) => d.label).toList();
      expect(labels, ['Home', 'Find', 'Chat', 'Bookings', 'Account']);

      await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('Bookings')));
      await tester.pumpAndSettle();
      expect(find.widgetWithText(Tab, 'Upcoming'), findsOneWidget);
      expect(find.widgetWithText(Tab, 'Past'), findsOneWidget);
    });
  });

  group('upcoming', () {
    testWidgets('each booking shows when (Malaysian time), who, what kind and its state as a word and an icon', (tester) async {
      final api = listing(upcoming: [
        aMyBooking(id: 'a', days: 1),
        aMyBooking(id: 'b', days: 3, hour: 14, mode: SessionMode.physical, location: const SlotLocation(name: 'Klinik Damai', city: 'Kuantan'), startingSoon: true),
      ]);
      await openBookings(tester, api);

      expect(find.text('Tomorrow, 10:00 AM – 10:50 AM'), findsOneWidget);
      expect(find.text('Thu 5 Nov, 2:00 PM – 2:50 PM'), findsOneWidget);
      expect(find.text('Kaunselor Aina Syazwani'), findsNWidgets(2));
      expect(find.text('Online'), findsOneWidget);
      expect(find.text('In-person · Klinik Damai, Kuantan'), findsOneWidget);
      expect(find.text('Confirmed'), findsNWidgets(2));
      expect(find.byIcon(Icons.check_circle_outline), findsNWidgets(2));
      expect(find.text('Starting soon'), findsOneWidget);
      expect(api.lists.single.scope, BookingsScope.upcoming);
    });

    testWidgets('a card reads as one item to a screen reader, and opens the details', (tester) async {
      final handle = tester.ensureSemantics();
      final api = listing(upcoming: [aMyBooking(id: 'a')]);
      await openBookings(tester, api);

      expect(find.bySemanticsLabel(RegExp(r'^Tomorrow, 10:00.AM – 10:50.AM, Kaunselor Aina Syazwani, Online, Confirmed$')), findsOneWidget);

      await tester.tap(find.text('Kaunselor Aina Syazwani'));
      await tester.pumpAndSettle();
      expect(api.details, ['a']);
      expect(find.text('Booking details'), findsOneWidget);
      handle.dispose();
    });

    testWidgets('nothing yet: a plain message and a way to find a counselor', (tester) async {
      await openBookings(tester, listing());

      expect(find.text('No upcoming sessions'), findsOneWidget);
      await tester.tap(find.text('Find a counselor'));
      await tester.pumpAndSettle();
      expect(find.byType(TextField), findsOneWidget);
    });

    testWidgets('a failure shows the words and Try again, which asks again', (tester) async {
      var tries = 0;
      final api = FakeMyBookingsApi()
        ..listHandler = ((scope, page) async {
          if (scope == BookingsScope.upcoming && ++tries == 1) throw const NetworkFailure();

          return BookingsPage(items: [aMyBooking()], page: 1, lastPage: 1);
        });
      await openBookings(tester, api);

      expect(find.text('Something went wrong'), findsOneWidget);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.text('Tomorrow, 10:00 AM – 10:50 AM'), findsOneWidget);
    });

    testWidgets('pulling down asks again and keeps what is shown until the answer', (tester) async {
      final api = listing(upcoming: [aMyBooking()]);
      await openBookings(tester, api);
      final before = api.lists.length;

      await tester.fling(find.byType(ListView).first, const Offset(0, 400), 1000);
      await tester.pumpAndSettle();

      expect(api.lists.length, greaterThan(before));
      expect(find.text('Tomorrow, 10:00 AM – 10:50 AM'), findsOneWidget);
    });
  });

  group('past', () {
    testWidgets('shows the finished and cancelled ones with their state, and "Book again" opens that counselor', (tester) async {
      final api = listing(past: [
        aMyBooking(id: 'x', status: BookingState.cancelled, days: -3),
        aMyBooking(id: 'y', status: BookingState.completed, days: -9),
        aMyBooking(id: 'z', status: BookingState.noShow, days: -12),
      ]);
      await openBookings(tester, api);
      await showPast(tester);

      expect(find.text('Cancelled'), findsOneWidget);
      expect(find.text('Completed'), findsOneWidget);
      expect(find.text('No-show'), findsOneWidget);
      expect(find.byIcon(Icons.cancel_outlined), findsOneWidget);
      expect(find.text('Book again'), findsNWidgets(3));

      await tester.tap(find.text('Book again').first);
      await tester.pumpAndSettle();
      expect(find.text('Verified registered counselor'), findsOneWidget); // the counselor's own page
    });

    testWidgets('no "Book again" when the counselor no longer has a page to go to', (tester) async {
      final api = listing(past: [aMyBooking(status: BookingState.completed, days: -3, counselorId: null)]);
      await openBookings(tester, api);
      await showPast(tester);

      expect(find.text('Book again'), findsNothing);
    });

    testWidgets('nothing yet: a plain message', (tester) async {
      await openBookings(tester, listing());
      await showPast(tester);

      expect(find.text('No past sessions yet'), findsOneWidget);
    });

    testWidgets('scrolling to the end loads the next page once, and a booking is never shown twice', (tester) async {
      final first = [for (var i = 0; i < 20; i++) aMyBooking(id: 'p$i', status: BookingState.completed, days: -1 - i)];
      final second = [aMyBooking(id: 'p19', status: BookingState.completed, days: -20), aMyBooking(id: 'p20', status: BookingState.completed, days: -21)];
      final api = FakeMyBookingsApi()
        ..listHandler = ((scope, page) async {
          if (scope == BookingsScope.upcoming) return const BookingsPage(items: [], page: 1, lastPage: 1);

          return BookingsPage(items: page == 1 ? first : second, page: page, lastPage: 2);
        });
      await openBookings(tester, api);
      await showPast(tester);

      await tester.dragUntilVisible(find.text('Book again').last, find.byType(ListView).last, const Offset(0, -300));
      await tester.pumpAndSettle();
      await tester.fling(find.byType(ListView).last, const Offset(0, -3000), 3000);
      await tester.pumpAndSettle();

      final pages = api.lists.where((l) => l.scope == BookingsScope.past).map((l) => l.page).toList();
      expect(pages, [1, 2], reason: 'page 2 asked for once');
    });

    testWidgets('a failed next page keeps the list and offers to try again', (tester) async {
      final first = [for (var i = 0; i < 3; i++) aMyBooking(id: 'p$i', status: BookingState.completed, days: -1 - i)];
      final api = FakeMyBookingsApi()
        ..listHandler = ((scope, page) async {
          if (scope == BookingsScope.upcoming) return const BookingsPage(items: [], page: 1, lastPage: 1);
          if (page == 2) throw const NetworkFailure();

          return BookingsPage(items: first, page: 1, lastPage: 2);
        });
      await openBookings(tester, api, size: const Size(400, 1600));
      await showPast(tester);
      await tester.pump(); // the page that did not fill the screen asks for the next one after the frame
      await tester.pumpAndSettle();

      expect(find.text('Could not load more bookings.'), findsOneWidget);
      expect(find.text('Book again'), findsWidgets);
    });
  });

  group('the details', () {
    testWidgets('shows the reference, the facts, and a sentence for each part', (tester) async {
      final handle = tester.ensureSemantics();
      final api = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(id: '01hxbooking'));
      await openBookings(tester, api, path: AppRoutes.bookingPath('01hxbooking'));

      expect(find.text('Confirmed'), findsOneWidget);
      expect(find.text('Tuesday, 3 November 2026'), findsOneWidget);
      expect(find.text('10:00 AM – 10:50 AM, Malaysia time'), findsOneWidget);
      expect(find.text('50 minutes'), findsOneWidget);
      expect(find.text('01hxbooking'), findsOneWidget);
      expect(find.bySemanticsLabel('Reference: 01hxbooking'), findsOneWidget);
      expect(find.bySemanticsLabel('Date: Tuesday, 3 November 2026'), findsOneWidget);
      expect(find.text('You can cancel until Mon 2 Nov, 10:00 AM.'), findsOneWidget);
      handle.dispose();
    });

    testWidgets('online, before the window: when the link will appear, and no way to join', (tester) async {
      final start = DateTime.utc(2026, 11, 3, 2);
      final api = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(meeting: aMeeting(start, 50)));
      await openBookings(tester, api, path: AppRoutes.bookingPath('bk1'));

      expect(find.text('Session details'), findsOneWidget);
      expect(find.text('Your meeting link will appear at 9:45 AM.'), findsOneWidget);
      expect(find.text('Join session'), findsNothing);
    });

    testWidgets('online, inside the window with a link: "Join session" opens it', (tester) async {
      final start = DateTime.utc(2026, 11, 3, 2);
      final api = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(meeting: aMeeting(start, 50, state: MeetingState.available)));
      final opened = await openBookings(tester, api, path: AppRoutes.bookingPath('bk1'));

      await tester.tap(find.text('Join session'));
      await tester.pumpAndSettle();

      expect(opened.opened, [Uri.parse('https://meet.example/room-1')]);
    });

    testWidgets('a link that is not a web address is never opened', (tester) async {
      final start = DateTime.utc(2026, 11, 3, 2);
      for (final bad in ['javascript:alert(1)', 'file:///etc/passwd', 'intent://x#Intent;end', 'tel:999', '']) {
        final api = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(meeting: aMeeting(start, 50, state: MeetingState.available, url: bad)));
        final opened = await openBookings(tester, api, path: AppRoutes.bookingPath('bk1'));

        await tester.tap(find.text('Join session'));
        await tester.pumpAndSettle();

        expect(opened.opened, isEmpty, reason: bad);
        expect(find.text('Could not open the link.'), findsOneWidget, reason: bad);
        await tester.pumpWidget(const SizedBox());
      }
    });

    testWidgets('when nothing can open the link, the person is told', (tester) async {
      final start = DateTime.utc(2026, 11, 3, 2);
      final api = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(meeting: aMeeting(start, 50, state: MeetingState.available)));
      await openBookings(tester, api, path: AppRoutes.bookingPath('bk1'), opens: false);

      await tester.tap(find.text('Join session'));
      await tester.pumpAndSettle();

      expect(find.text('Could not open the link.'), findsOneWidget);
    });

    testWidgets('online, inside the window but no link yet: says so, no Join', (tester) async {
      final start = DateTime.utc(2026, 11, 3, 2);
      final api = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(meeting: aMeeting(start, 50, state: MeetingState.notAdded)));
      await openBookings(tester, api, path: AppRoutes.bookingPath('bk1'));

      expect(find.text('Your counselor has not added the meeting link yet. Please check again shortly.'), findsOneWidget);
      expect(find.text('Join session'), findsNothing);
    });

    testWidgets('online, over: says so', (tester) async {
      final start = DateTime.utc(2026, 11, 3, 2);
      final api = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(meeting: aMeeting(start, 50, state: MeetingState.ended)));
      await openBookings(tester, api, path: AppRoutes.bookingPath('bk1'));

      expect(find.text('This session has ended.'), findsOneWidget);
    });

    testWidgets('the Join button appears by itself when the window opens', (tester) async {
      // The server says "opens in two minutes"; the screen asks again at that moment and the link is there.
      final soon = testNow.add(const Duration(minutes: 17));
      var asked = 0;
      final api = FakeMyBookingsApi()
        ..detailHandler = ((id) async {
          asked++;
          final detail = aBookingDetail();

          return asked == 1
              ? detail.copyWith(startsAt: soon, endsAt: soon.add(const Duration(minutes: 50)), meeting: aMeeting(soon, 50))
              : detail.copyWith(startsAt: soon, endsAt: soon.add(const Duration(minutes: 50)), meeting: aMeeting(soon, 50, state: MeetingState.available));
        });
      await openBookings(tester, api, path: AppRoutes.bookingPath('bk1'));
      expect(find.text('Join session'), findsNothing);

      await tester.pump(const Duration(minutes: 2, seconds: 2));
      await tester.pumpAndSettle();

      expect(asked, 2);
      expect(find.text('Join session'), findsOneWidget);
    });

    testWidgets('in person: the full address, and "Get directions" opens a map search by coordinates', (tester) async {
      const place = BookingPlace(name: 'Klinik Damai', address: 'No 12, Jalan Besar', city: 'Kuantan', state: 'Pahang', postcode: '25300', latitude: 3.8077, longitude: 103.326);
      final api = FakeMyBookingsApi()
        ..detailHandler = ((id) async => aBookingDetail(mode: SessionMode.physical, location: const SlotLocation(name: 'Klinik Damai', city: 'Kuantan'), place: place));
      final opened = await openBookings(tester, api, path: AppRoutes.bookingPath('bk1'));

      expect(find.textContaining('No 12, Jalan Besar'), findsOneWidget);
      expect(find.textContaining('25300 Kuantan'), findsOneWidget);
      expect(find.text('Join session'), findsNothing);

      await tester.tap(find.text('Get directions'));
      await tester.pumpAndSettle();

      expect(opened.opened.single.host, 'www.google.com');
      expect(opened.opened.single.queryParameters['query'], '3.8077,103.326');
    });

    testWidgets('in person without coordinates: the map search uses the address', (tester) async {
      const place = BookingPlace(name: 'Klinik Damai', address: 'No 12, Jalan Besar', city: 'Kuantan', state: 'Pahang', postcode: '25300');
      final api = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(mode: SessionMode.physical, place: place));
      final opened = await openBookings(tester, api, path: AppRoutes.bookingPath('bk1'));

      await tester.tap(find.text('Get directions'));
      await tester.pumpAndSettle();

      expect(opened.opened.single.queryParameters['query'], contains('No 12, Jalan Besar'));
    });

    testWidgets('a cancelled booking says so and has no link, no address and no cancel line', (tester) async {
      final api = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(status: BookingState.cancelled, canCancel: false, withDeadline: false));
      await openBookings(tester, api, path: AppRoutes.bookingPath('bk1'));

      expect(find.text('This booking was cancelled.'), findsOneWidget);
      expect(find.text('Session details'), findsNothing);
      expect(find.textContaining('You can cancel'), findsNothing);
      expect(find.text('This booking can no longer be cancelled.'), findsNothing);
    });

    testWidgets('inside the cancellation window: it can no longer be cancelled', (tester) async {
      final api = FakeMyBookingsApi()..detailHandler = ((id) async => aBookingDetail(canCancel: false, withDeadline: false));
      await openBookings(tester, api, path: AppRoutes.bookingPath('bk1'));

      expect(find.text('This booking can no longer be cancelled.'), findsOneWidget);
    });

    testWidgets('not found says so plainly, with nothing to retry', (tester) async {
      final api = FakeMyBookingsApi()..detailHandler = ((id) async => throw const NotFoundFailure());
      await openBookings(tester, api, path: AppRoutes.bookingPath('nope'));

      expect(find.text("We couldn't find this booking."), findsOneWidget);
      expect(find.text('Try again'), findsNothing);
    });

    testWidgets('another failure offers Try again', (tester) async {
      var tries = 0;
      final api = FakeMyBookingsApi()
        ..detailHandler = ((id) async {
          if (++tries == 1) throw const NetworkFailure();

          return aBookingDetail();
        });
      await openBookings(tester, api, path: AppRoutes.bookingPath('bk1'));

      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.text('Tuesday, 3 November 2026'), findsOneWidget);
    });
  });

  group('after booking', () {
    testWidgets('"View booking" opens that booking and the list is asked for again', (tester) async {
      final api = listing();
      final opened = await openBookings(tester, api, path: AppRoutes.home);
      final before = api.lists.length;

      opened.container.read(appRouterProvider).go(AppRoutes.bookingConfirmed, extra: aCreatedBooking());
      await tester.pumpAndSettle();

      expect(find.text('Booking confirmed'), findsOneWidget);
      expect(find.text('Your session is booked.'), findsOneWidget);
      expect(find.text('Your session with Kaunselor Aina Syazwani'), findsOneWidget);
      expect(find.text('Tuesday, 3 November 2026'), findsOneWidget);
      expect(api.lists.length, before, reason: 'nothing asked for until the tab is opened');

      await tester.tap(find.text('View booking'));
      await tester.pumpAndSettle();
      expect(api.details, ['01bk']);
      expect(find.text('Booking details'), findsOneWidget);
    });

    testWidgets('the phone\'s Back goes Home, and so does "Back to home"', (tester) async {
      final opened = await openBookings(tester, listing(), path: AppRoutes.home);
      opened.container.read(appRouterProvider).go(AppRoutes.bookingConfirmed, extra: aCreatedBooking());
      await tester.pumpAndSettle();

      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.text('Hello, Siti'), findsOneWidget);
    });
  });

  group('large text and small screens', () {
    for (final scale in [1.0, 2.0]) {
      testWidgets('the list and the details do not overflow at text size ×$scale on a small phone', (tester) async {
        final api = listing(upcoming: [aMyBooking(id: 'a', startingSoon: true, mode: SessionMode.physical, location: const SlotLocation(name: 'Klinik Damai Kuantan Besar', city: 'Kuantan'))]);
        api.detailHandler = ((id) async => aBookingDetail(mode: SessionMode.physical, place: const BookingPlace(name: 'Klinik Damai', address: 'No 12, Jalan Besar', city: 'Kuantan', state: 'Pahang', postcode: '25300')));
        await openBookings(tester, api, textScale: scale, size: const Size(320, 640));

        expect(tester.takeException(), isNull);
        await tester.tap(find.byType(InkWell).first);
        await tester.pumpAndSettle();
        expect(find.text('Booking details'), findsOneWidget);
        expect(tester.takeException(), isNull);
      });
    }
  });

  group('the sources of the bookings screens', () {
    final files = Directory('lib/features/bookings').listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart') && !f.path.endsWith('.g.dart') && !f.path.endsWith('.freezed.dart')).toList();
    final presentation = files.where((f) => f.path.replaceAll(r'\', '/').contains('/presentation/')).toList();

    test('no colour is written in a widget', () {
      for (final file in presentation) {
        expect(RegExp(r'Color\(0x|Colors\.|Color\.fromARGB|Color\.fromRGBO').hasMatch(file.readAsStringSync()), isFalse, reason: file.path);
      }
    });

    test('no sentence is written in a widget', () {
      final literal = RegExp('''(Text|SelectableText)\\(\\s*['"]|(label|hint|tooltip|semanticLabel|semanticsLabel):\\s*['"]''');

      for (final file in presentation) {
        expect(literal.hasMatch(file.readAsStringSync()), isFalse, reason: file.path);
      }
    });

    test('nothing about a booking is stored on the phone, printed or logged', () {
      final forbidden = RegExp(r'shared_preferences|flutter_secure_storage|SharedPreferences|File\(|print\(|debugPrint\(|developer\.log');

      for (final file in files) {
        expect(forbidden.hasMatch(file.readAsStringSync()), isFalse, reason: file.path);
      }
    });
  });
}
