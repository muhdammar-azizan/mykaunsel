import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/features/auth/domain/app_user.dart';
import 'package:mykaunsel/features/auth/domain/sign_in_result.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';
import 'package:mykaunsel/features/counselors/domain/counselor_query.dart';

import '../../support/fakes.dart';

/// Opens the app signed in, on the Home tab.
Future<void> open(WidgetTester tester, {FakeCounselorApi? api, FakeAuthApi? authApi, FakeTokenStorage? storage, double textScale = 1, Size size = const Size(400, 800)}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(counselorApi: api, authApi: authApi, storage: storage ?? FakeTokenStorage('5|mkp_abc')),
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> goToFind(WidgetTester tester) async {
  await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('Find')));
  await tester.pumpAndSettle();
}

Future<void> openFilters(WidgetTester tester) async {
  await tester.tap(find.byIcon(Icons.tune));
  await tester.pumpAndSettle();
}

void main() {
  group('the tabs', () {
    testWidgets('home offers the way to a counselor, and the bottom bar moves between the three tabs', (tester) async {
      await open(tester);

      expect(find.text('Browse verified counselors and see when they are free.'), findsOneWidget);

      await tester.tap(find.text('Find a counselor'));
      await tester.pumpAndSettle();
      expect(find.byType(TextField), findsOneWidget); // the search box

      await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('Account')));
      await tester.pumpAndSettle();
      expect(find.text('Sign out'), findsOneWidget);

      await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('Home')));
      await tester.pumpAndSettle();
      expect(find.text('Hello, Siti'), findsOneWidget);
    });

    testWidgets('a tab keeps its own place: the search and the results are still there on coming back', (tester) async {
      final api = FakeCounselorApi();
      await open(tester, api: api);
      await goToFind(tester);
      await tester.enterText(find.byType(TextField), 'aina');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();
      final calls = api.searches.length;

      await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('Home')));
      await tester.pumpAndSettle();
      await goToFind(tester);

      expect(find.widgetWithText(TextField, 'aina'), findsOneWidget);
      expect(api.searches.length, calls); // not fetched again
    });
  });

  group('the results', () {
    testWidgets('each counselor is a card with what a client needs to choose', (tester) async {
      final api = FakeCounselorApi()
        ..searchHandler = (q, page) async => aPage([
              aCounselor(
                specializations: ['Kemurungan', 'Stres', 'Trauma'],
                specializationsCount: 5,
                languages: ['English', 'Bahasa Malaysia'],
                modes: [SessionMode.online, SessionMode.physical],
                locations: [const CounselorLocation(city: 'Kuantan', state: 'Pahang')],
                contexts: [const CounselorContext(type: ContextType.organization, organization: 'Klinik Damai')],
                next: aSlot(days: 1, hour: 10),
              ),
            ]);
      await open(tester, api: api);
      await goToFind(tester);

      expect(find.text('Kaunselor Aina Syazwani'), findsOneWidget);
      expect(find.text('Verified registered counselor'), findsOneWidget);
      expect(find.text('Klinik Damai'), findsOneWidget);
      expect(find.text('Kemurungan'), findsOneWidget);
      expect(find.text('+2 more'), findsOneWidget);
      expect(findTextContaining('English, Bahasa Malaysia'), findsOneWidget);
      expect(findTextContaining('Online / In-person'), findsOneWidget);
      expect(findTextContaining('Kuantan, Pahang'), findsOneWidget);
      expect(findText('Next free time: Tomorrow, 10:00 AM'), findsOneWidget);
      expect(find.text('KA'), findsNothing); // "Kaunselor" is a title, not an initial
      expect(find.text('AS'), findsOneWidget);
      expect(find.text('1 counselor'), findsOneWidget);
    });

    testWidgets('a counselor with no free time says so; independent counselors are labelled', (tester) async {
      final api = FakeCounselorApi()..searchHandler = (q, page) async => aPage([aCounselor(withNext: false)]);
      await open(tester, api: api);
      await goToFind(tester);

      expect(find.text('No free times in the coming weeks'), findsOneWidget);
      expect(find.text('Independent'), findsOneWidget);
    });

    testWidgets('while the first answer is awaited there is a spinner', (tester) async {
      final gate = Completer<CounselorPage>();
      final api = FakeCounselorApi()..searchHandler = (q, page) => gate.future;
      await tester.binding.setSurfaceSize(const Size(400, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(ProviderScope(overrides: testOverrides(counselorApi: api, storage: FakeTokenStorage('t')), child: const MyKaunselApp()));
      await tester.pumpAndSettle();
      await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('Find')));
      await tester.pump(const Duration(milliseconds: 400)); // (a spinner never "settles")

      expect(find.byType(CircularProgressIndicator), findsOneWidget);

      gate.complete(aPage([aCounselor()]));
      await tester.pumpAndSettle();

      expect(find.text('Kaunselor Aina Syazwani'), findsOneWidget);
    });

    testWidgets('nobody at all, and nobody matching, are different messages', (tester) async {
      final api = FakeCounselorApi()..searchHandler = (q, page) async => aPage(const []);
      await open(tester, api: api);
      await goToFind(tester);

      expect(find.text('No counselors yet'), findsOneWidget);
      expect(find.text('Clear filters'), findsNothing);

      await tester.enterText(find.byType(TextField), 'zzz');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();

      expect(find.text('No counselors found'), findsOneWidget);
      expect(find.text('Try a different name, or fewer filters.'), findsOneWidget);

      await tester.tap(find.text('Clear filters'));
      await tester.pumpAndSettle();

      expect(find.widgetWithText(TextField, 'zzz'), findsNothing);
      expect(api.searches.last.query, const CounselorQuery());
    });

    testWidgets('a failure with nothing to show offers "try again"', (tester) async {
      final api = FakeCounselorApi()..searchHandler = (q, page) async => throw const NetworkFailure();
      await open(tester, api: api);
      await goToFind(tester);

      expect(find.text('Something went wrong'), findsOneWidget);
      expect(find.text('We could not reach MyKaunsel. Check your internet connection and try again.'), findsOneWidget);

      api.searchHandler = (q, page) async => aPage([aCounselor()]);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(find.text('Kaunselor Aina Syazwani'), findsOneWidget);
    });

    testWidgets('scrolling near the end loads the next page, once', (tester) async {
      final api = FakeCounselorApi()
        ..searchHandler = (q, page) async => aPage(
              [for (var i = 0; i < 20; i++) aCounselor(id: 'p$page-$i', name: 'Person $page-$i')],
              page: page,
              lastPage: 2,
              total: 40,
            );
      await open(tester, api: api);
      await goToFind(tester);
      expect(api.searches.map((s) => s.page), [1]);

      await tester.drag(find.byType(Scrollable).last, const Offset(0, -20000));
      await tester.pumpAndSettle();

      expect(api.searches.map((s) => s.page), [1, 2]);

      await tester.drag(find.byType(Scrollable).last, const Offset(0, -20000));
      await tester.pumpAndSettle();

      expect(api.searches.map((s) => s.page), [1, 2]); // nothing after the last page
      expect(find.text('Person 2-19'), findsOneWidget);
    });

    testWidgets('a failure while loading more says so, keeps the list, and can be retried', (tester) async {
      final api = FakeCounselorApi()
        ..searchHandler = (q, page) async => page == 1
            ? aPage([for (var i = 0; i < 20; i++) aCounselor(id: 'p$i', name: 'Person $i')], lastPage: 2, total: 21)
            : throw const NetworkFailure();
      await open(tester, api: api);
      await goToFind(tester);

      await tester.drag(find.byType(Scrollable).last, const Offset(0, -20000));
      await tester.pumpAndSettle();

      expect(find.text('Could not load more counselors.'), findsOneWidget);
      expect(find.text('Person 19'), findsOneWidget);

      api.searchHandler = (q, page) async => aPage([aCounselor(id: 'last', name: 'Last Person')], page: 2, lastPage: 2, total: 21);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      await tester.drag(find.byType(Scrollable).last, const Offset(0, -20000));
      await tester.pumpAndSettle();

      expect(find.text('Last Person'), findsOneWidget);
    });

    testWidgets('pulling down refreshes', (tester) async {
      final api = FakeCounselorApi();
      await open(tester, api: api);
      await goToFind(tester);

      await tester.fling(find.byType(Scrollable).last, const Offset(0, 400), 1000);
      await tester.pumpAndSettle();

      expect(api.searches, hasLength(2));
    });
  });

  group('search', () {
    testWidgets('waits until typing stops, and never searches for one letter', (tester) async {
      final api = FakeCounselorApi();
      await open(tester, api: api);
      await goToFind(tester);
      api.searches.clear();

      await tester.enterText(find.byType(TextField), 'a');
      await tester.pump(const Duration(seconds: 1));
      expect(api.searches, isEmpty);

      await tester.enterText(find.byType(TextField), 'ai');
      await tester.pump(const Duration(milliseconds: 200));
      await tester.enterText(find.byType(TextField), 'ain');
      await tester.pump(const Duration(milliseconds: 200));
      expect(api.searches, isEmpty); // still typing

      await tester.pump(const Duration(milliseconds: 300));
      await tester.pumpAndSettle();

      expect(api.searches.map((s) => s.query.q), ['ain']);
    });

    testWidgets('clearing the box shows everyone again', (tester) async {
      final api = FakeCounselorApi();
      await open(tester, api: api);
      await goToFind(tester);
      await tester.enterText(find.byType(TextField), 'aina');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();

      await tester.tap(find.byTooltip('Clear search'));
      await tester.pumpAndSettle();

      expect(api.searches.last.query.q, isNull);
      expect(find.byTooltip('Clear search'), findsNothing);
    });

    testWidgets('the keyboard search button searches at once', (tester) async {
      final api = FakeCounselorApi();
      await open(tester, api: api);
      await goToFind(tester);

      await tester.enterText(find.byType(TextField), 'tan');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();

      expect(api.searches.last.query.q, 'tan');
    });
  });

  group('filters', () {
    testWidgets('the sheet offers what is really on offer, and applying filters searches again', (tester) async {
      final api = FakeCounselorApi();
      await open(tester, api: api);
      await goToFind(tester);

      await openFilters(tester);

      expect(find.text('Session type'), findsOneWidget);
      expect(find.text('Focus area'), findsOneWidget);
      expect(find.text('Mandarin'), findsOneWidget);
      expect(find.text('Pahang'), findsOneWidget);

      await tester.tap(find.widgetWithText(ChoiceChip, 'In-person'));
      await tester.tap(find.widgetWithText(ChoiceChip, 'Trauma'));
      await tester.pump();
      await tester.tap(find.text('Show results'));
      await tester.pumpAndSettle();

      expect(api.searches.last.query, const CounselorQuery(mode: SessionMode.physical, specialization: 'Trauma'));
      expect(find.text('Filters (2)'), findsOneWidget);
    });

    testWidgets('a chosen chip can be un-chosen, and "clear all" resets the sheet but keeps the search words', (tester) async {
      final api = FakeCounselorApi();
      await open(tester, api: api);
      await goToFind(tester);
      await tester.enterText(find.byType(TextField), 'aina');
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pumpAndSettle();

      await openFilters(tester);
      await tester.tap(find.widgetWithText(ChoiceChip, 'English'));
      await tester.pump();
      expect(tester.widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'English')).selected, isTrue);
      await tester.tap(find.widgetWithText(ChoiceChip, 'English'));
      await tester.pump();
      expect(tester.widget<ChoiceChip>(find.widgetWithText(ChoiceChip, 'English')).selected, isFalse);

      await tester.tap(find.widgetWithText(ChoiceChip, 'Mandarin'));
      await tester.pump();
      await tester.tap(find.text('Clear all'));
      await tester.pump();
      await tester.tap(find.text('Show results'));
      await tester.pumpAndSettle();

      expect(api.searches.last.query, const CounselorQuery(q: 'aina'));
    });

    testWidgets('closing the sheet without applying changes nothing', (tester) async {
      final api = FakeCounselorApi();
      await open(tester, api: api);
      await goToFind(tester);
      final calls = api.searches.length;

      await openFilters(tester);
      await tester.tap(find.widgetWithText(ChoiceChip, 'English'));
      await tester.pump();
      await tester.tapAt(tester.getTopLeft(find.byType(ModalBarrier).last) + const Offset(10, 10)); // the dark area above the sheet
      await tester.pumpAndSettle();

      expect(api.searches.length, calls);
      expect(find.text('Filters'), findsOneWidget); // the button, no count
    });

    testWidgets('when the choices can not be loaded, the sheet says so and can retry', (tester) async {
      final api = FakeCounselorApi()..filtersHandler = () async => throw const NetworkFailure();
      await open(tester, api: api);
      await goToFind(tester);

      await openFilters(tester);
      expect(find.text('We could not reach MyKaunsel. Check your internet connection and try again.'), findsWidgets);

      api.filtersHandler = () async => const CounselorFilterOptions(languages: ['English']);
      await tester.tap(find.text('Try again').last);
      await tester.pumpAndSettle();

      expect(find.widgetWithText(ChoiceChip, 'English'), findsOneWidget);
    });

    testWidgets('a session type this app does not know is not offered', (tester) async {
      final api = FakeCounselorApi()..filtersHandler = () async => const CounselorFilterOptions(sessionModes: [SessionMode.online, SessionMode.unknown]);
      await open(tester, api: api);
      await goToFind(tester);

      await openFilters(tester);

      expect(find.byType(ChoiceChip), findsOneWidget);
      expect(find.text('Online'), findsOneWidget);
    });
  });

  group('a counselor\'s page', () {
    FakeCounselorApi withProfile() {
      return FakeCounselorApi()
        ..searchHandler = ((q, page) async => aPage([aCounselor(id: 'c-aina')]))
        ..detailHandler = (id) async => aCounselor(
              id: id,
              specializations: ['Kemurungan', 'Stres', 'Trauma', 'Kesedihan', 'Hubungan'],
              modes: [SessionMode.online, SessionMode.physical],
              locations: [const CounselorLocation(city: 'Kuantan', state: 'Pahang')],
              contexts: [const CounselorContext(type: ContextType.independent), const CounselorContext(type: ContextType.organization, organization: 'Klinik Damai')],
              acceptsRequests: true,
              upcoming: [
                aSlot(days: 1, hour: 10),
                aSlot(days: 1, hour: 14, mode: SessionMode.physical, city: 'Kuantan', state: 'Pahang', context: const CounselorContext(type: ContextType.organization, organization: 'Klinik Damai')),
                aSlot(days: 3, hour: 9),
              ],
            );
    }

    Future<void> openProfile(WidgetTester tester, FakeCounselorApi api) async {
      await open(tester, api: api, size: const Size(400, 2000)); // tall, so the whole page is built
      await goToFind(tester);
      await tester.tap(find.text('Kaunselor Aina Syazwani'));
      await tester.pumpAndSettle();
    }

    testWidgets('tapping a card opens the whole profile', (tester) async {
      final api = withProfile();
      await openProfile(tester, api);

      expect(api.details, ['c-aina']);
      expect(find.text('Verified registered counselor'), findsOneWidget);
      expect(find.text('Focus areas'), findsOneWidget);
      expect(find.text('Trauma'), findsOneWidget);
      expect(find.text('Hubungan'), findsOneWidget); // all of them, not just the first three
      expect(find.text('Languages'), findsOneWidget);
      expect(find.text('Online / In-person'), findsOneWidget);
      expect(find.text('Kuantan, Pahang'), findsWidgets);
      expect(find.text('Independent · Klinik Damai'), findsOneWidget);
      expect(find.text('Also takes session requests.'), findsOneWidget);
      expect(find.text('See available times'), findsOneWidget);
    });

    testWidgets('free times are grouped by day, in Malaysian time, with where in person ones are', (tester) async {
      await openProfile(tester, withProfile());

      expect(find.text('Tuesday 3 November'), findsOneWidget);
      expect(find.text('Thursday 5 November'), findsOneWidget);
      expect(findText('10:00 AM'), findsOneWidget);
      expect(findText('2:00 PM'), findsOneWidget);
      expect(findTextContaining('60 min  ·  In-person · Kuantan, Pahang  ·  Klinik Damai'), findsOneWidget);
      expect(findTextContaining('60 min  ·  Online  ·  Independent'), findsNWidgets(2));
      expect(find.text('All times are Malaysian time.'), findsOneWidget);
    });

    testWidgets('the back button returns to the same results', (tester) async {
      final api = withProfile();
      await openProfile(tester, api);
      final calls = api.searches.length;

      await tester.pageBack();
      await tester.pumpAndSettle();

      expect(find.text('Kaunselor Aina Syazwani'), findsOneWidget);
      expect(find.byType(TextField), findsOneWidget);
      expect(api.searches.length, calls);
    });

    testWidgets('a counselor who is not available (gone, or not for this person) says so plainly', (tester) async {
      final api = withProfile()..detailHandler = (id) async => throw const NotFoundFailure();
      await openProfile(tester, api);

      expect(find.text('Counselor not available'), findsOneWidget);
      expect(find.text('We could not find that. It may no longer be available.'), findsOneWidget);
      expect(find.text('Try again'), findsNothing);
    });

    testWidgets('another kind of failure offers "try again"', (tester) async {
      final api = withProfile()..detailHandler = (id) async => throw const NetworkFailure();
      await openProfile(tester, api);

      expect(find.text('Something went wrong'), findsOneWidget);

      api.detailHandler = (id) async => aCounselor(id: id, acceptsRequests: false, upcoming: const []);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(find.text('No free times right now. Please check again soon.'), findsOneWidget);
      expect(find.text('Also takes session requests.'), findsNothing);
    });
  });

  group("one person's results never reach the next person", () {
    testWidgets('after signing out and in as someone else, the list is fetched again and the old one is gone', (tester) async {
      var who = 'first';
      final api = FakeCounselorApi()
        ..searchHandler = (q, page) async => aPage([who == 'first' ? aCounselor(id: 'a', name: 'University Counselor') : aCounselor(id: 'b', name: 'Independent Counselor')]);
      final auth = FakeAuthApi()
        ..signInHandler = (email, password) async => const SignedInResult(token: '9|mkp_other', user: AppUser(id: 99, name: 'Someone Else', email: 'else@example.com', emailVerified: true));
      await open(tester, api: api, authApi: auth);
      await goToFind(tester);
      expect(find.text('University Counselor'), findsOneWidget);

      await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('Account')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sign out'));
      await tester.pumpAndSettle();
      expect(find.text('Welcome back'), findsOneWidget);

      who = 'second';
      await tester.enterText(find.widgetWithText(TextFormField, 'Email'), 'else@example.com');
      await tester.enterText(find.widgetWithText(TextFormField, 'Password'), 'Str0ng-Pass-123');
      await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
      await tester.pumpAndSettle();
      await goToFind(tester);

      expect(find.text('University Counselor'), findsNothing);
      expect(find.text('Independent Counselor'), findsOneWidget);
      expect(api.searches, hasLength(2));
    });
  });

  group('large text and small screens', () {
    for (final scale in [1.0, 2.0]) {
      testWidgets('cards, filters and the profile do not overflow at text size ×$scale', (tester) async {
        final api = FakeCounselorApi()
          ..searchHandler = ((q, page) async => aPage([
                aCounselor(
                  name: 'Kaunselor Nur Aisyah binti Abdul Rahman Al-Haj Ibrahim',
                  specializations: ['Keluarga & Perkahwinan', 'Stres & Kebimbangan', 'Kesedihan & Kehilangan'],
                  specializationsCount: 6,
                  languages: ['Bahasa Malaysia', 'English', 'Mandarin', 'Tamil'],
                  modes: [SessionMode.online, SessionMode.physical],
                  locations: [const CounselorLocation(city: 'Johor Bahru', state: 'Johor'), const CounselorLocation(city: 'Kuala Terengganu', state: 'Terengganu')],
                  contexts: [const CounselorContext(type: ContextType.organization, organization: 'Universiti Malaysia Pahang Al-Sultan Abdullah')],
                ),
              ]))
          ..detailHandler = (id) async => aCounselor(id: id, name: 'Kaunselor Nur Aisyah binti Abdul Rahman Al-Haj Ibrahim', acceptsRequests: true, upcoming: [aSlot(), aSlot(days: 2, hour: 15)]);
        await open(tester, api: api, textScale: scale, size: const Size(320, 568));
        await goToFind(tester);
        expect(tester.takeException(), isNull);

        await openFilters(tester);
        expect(tester.takeException(), isNull);
        await tester.tap(find.text('Show results'));
        await tester.pumpAndSettle();

        await tester.tap(find.textContaining('Nur Aisyah'));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      });
    }
  });
}
