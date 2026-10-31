import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/features/booking/domain/availability.dart' show SlotLocation;
import 'package:mykaunsel/features/bookings/domain/my_booking.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';

import '../../support/fakes.dart';

Future<FakeMyBookingsApi> openHome(WidgetTester tester, {List<MyBooking> upcoming = const [], Object? failWith, double textScale = 1}) async {
  tester.view.physicalSize = const Size(800, 1800);
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  final api = FakeMyBookingsApi()
    ..listHandler = (scope, page) async {
      if (failWith != null) throw failWith;

      return BookingsPage(items: scope == BookingsScope.upcoming ? upcoming : const [], page: 1, lastPage: 1);
    };

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(myBookingsApi: api, storage: FakeTokenStorage('5|mkp_abc')),
      child: MediaQuery(data: MediaQueryData(size: const Size(400, 900), textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();

  return api;
}

void main() {
  group('Home: the next session', () {
    testWidgets('shows the soonest confirmed session above the other cards', (tester) async {
      await openHome(tester, upcoming: [aMyBooking(id: 'a'), aMyBooking(id: 'b', days: 3, counselorName: 'Another Counselor')]);

      expect(find.text('Your next session'), findsOneWidget);
      expect(find.text('Kaunselor Aina Syazwani'), findsOneWidget);
      expect(find.text('Another Counselor'), findsNothing);
      expect(find.text('Find a counselor'), findsOneWidget);
      expect(tester.getTopLeft(find.text('Your next session')).dy, lessThan(tester.getTopLeft(find.text('Find a counselor')).dy));
    });

    testWidgets('says "Starting soon" in words when the server says so', (tester) async {
      await openHome(tester, upcoming: [aMyBooking(startingSoon: true)]);

      expect(find.text('Starting soon'), findsOneWidget);
    });

    testWidgets('is a button for screen readers, with the whole sentence', (tester) async {
      await openHome(tester, upcoming: [aMyBooking()]);

      expect(find.bySemanticsLabel(RegExp(r'^Your next session: .*Kaunselor Aina Syazwani.*Confirmed')), findsOneWidget);
    });

    testWidgets('tapping it opens that booking', (tester) async {
      final api = await openHome(tester, upcoming: [aMyBooking(id: 'bk9')]);

      await tester.tap(find.text('Your next session'));
      await tester.pumpAndSettle();

      expect(api.details, ['bk9']);
    });

    testWidgets('is left out when nothing is booked', (tester) async {
      await openHome(tester);

      expect(find.text('Your next session'), findsNothing);
      expect(find.text('Find a counselor'), findsOneWidget);
    });

    testWidgets('is left out, without an error, when the list could not load', (tester) async {
      await openHome(tester, failWith: const NetworkFailure());

      expect(find.text('Your next session'), findsNothing);
      expect(find.text('Find a counselor'), findsOneWidget);
      expect(find.text('Try again'), findsNothing);
    });

    testWidgets('does not overflow at the largest text size', (tester) async {
      await openHome(tester, upcoming: [aMyBooking(startingSoon: true, mode: SessionMode.physical, location: const SlotLocation(name: 'Klinik Damai Kuantan Besar', city: 'Kuantan'))], textScale: 2);

      expect(tester.takeException(), isNull);
      expect(find.text('Your next session'), findsOneWidget);
    });
  });
}
