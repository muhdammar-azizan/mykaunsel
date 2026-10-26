import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/session/session_controller.dart';
import 'package:mykaunsel/core/session/session_state.dart';
import 'package:mykaunsel/features/auth/domain/app_user.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';
import 'package:mykaunsel/features/counselors/domain/counselor_query.dart';
import 'package:mykaunsel/features/counselors/presentation/counselor_list_controller.dart';

import '../../support/fakes.dart';

Future<void> pump() async {
  for (var i = 0; i < 20; i++) {
    await Future<void>.delayed(Duration.zero);
  }
}

void main() {
  late FakeCounselorApi api;
  late FakeTokenStorage storage;

  /// A container whose person is already signed in (the list only loads for a signed-in person).
  Future<ProviderContainer> signedIn() async {
    final container = ProviderContainer(overrides: testOverrides(counselorApi: api, storage: storage));
    addTearDown(container.dispose);

    container.read(sessionControllerProvider);
    await pump();
    expect(container.read(sessionControllerProvider), isA<SessionSignedIn>());

    // The search screen is what first reads the list, and it keeps watching it: listening here does the same, so the list
    // starts over by itself when the person changes (a provider nobody watches is only rebuilt when next read).
    final subscription = container.listen(counselorListControllerProvider, (_, _) {});
    addTearDown(subscription.close);

    return container;
  }

  CounselorListState state(ProviderContainer c) => c.read(counselorListControllerProvider);

  CounselorListController controller(ProviderContainer c) => c.read(counselorListControllerProvider.notifier);

  List<Counselor> people(int from, int count) => [for (var i = from; i < from + count; i++) aCounselor(id: 'id$i', name: 'Counselor $i')];

  setUp(() {
    api = FakeCounselorApi();
    storage = FakeTokenStorage('5|mkp_abc');
  });

  test('nothing is fetched while nobody is signed in', () async {
    storage.token = null;
    final container = await Future(() async {
      final c = ProviderContainer(overrides: testOverrides(counselorApi: api, storage: storage));
      addTearDown(c.dispose);
      c.read(sessionControllerProvider);
      await pump();
      c.read(counselorListControllerProvider);
      await pump();

      return c;
    });

    expect(state(container).items, isEmpty);
    expect(state(container).loading, isFalse);
    expect(api.searches, isEmpty);
  });

  test('the first page loads when a signed-in person first looks', () async {
    api.searchHandler = (q, page) async => aPage(people(1, 3), total: 3);
    final c = await signedIn();

    expect(state(c).loading, isTrue);
    await pump();

    expect(state(c).items.map((i) => i.id), ['id1', 'id2', 'id3']);
    expect(state(c).total, 3);
    expect(state(c).loading, isFalse);
    expect(state(c).loadedOnce, isTrue);
    expect(api.searches.single.query, const CounselorQuery());
    expect(api.searches.single.page, 1);
  });

  test('more pages are added at the end, one at a time, until there are no more', () async {
    api.searchHandler = (q, page) async => aPage(people((page - 1) * 20 + 1, 20), page: page, lastPage: 2, total: 40);
    final c = await signedIn();
    await pump();

    await Future.wait([controller(c).loadMore(), controller(c).loadMore()]); // asked twice at once
    await pump();

    expect(state(c).items, hasLength(40));
    expect(api.searches.map((s) => s.page), [1, 2]);
    expect(state(c).hasMore, isFalse);

    await controller(c).loadMore();
    expect(api.searches, hasLength(2)); // nothing more to ask for
  });

  test('a counselor who appears on two pages is shown once', () async {
    api.searchHandler = (q, page) async => page == 1 ? aPage(people(1, 3), lastPage: 2, total: 4) : aPage([aCounselor(id: 'id3'), aCounselor(id: 'id4')], page: 2, lastPage: 2, total: 4);
    final c = await signedIn();
    await pump();

    await controller(c).loadMore();

    expect(state(c).items.map((i) => i.id), ['id1', 'id2', 'id3', 'id4']);
  });

  test('a new search starts again from page 1 with what was typed', () async {
    final c = await signedIn();
    await pump();

    controller(c).setQuery(const CounselorQuery(q: 'aina', language: 'English'));
    await pump();

    expect(api.searches.last.query, const CounselorQuery(q: 'aina', language: 'English'));
    expect(api.searches.last.page, 1);
    expect(state(c).query.q, 'aina');
  });

  test('asking for the same search again does not ask the server again', () async {
    final c = await signedIn();
    await pump();

    controller(c).setQuery(const CounselorQuery());
    controller(c).setQuery(const CounselorQuery());
    await pump();

    expect(api.searches, hasLength(1));
  });

  test('a slow answer to an OLD search can not overwrite the newer one', () async {
    final slow = Completer<CounselorPage>();
    api.searchHandler = (q, page) => q.q == 'old' ? slow.future : Future.value(aPage([aCounselor(id: 'new', name: 'New result')]));
    final c = await signedIn();
    await pump();

    controller(c).setQuery(const CounselorQuery(q: 'old'));
    await pump();
    controller(c).setQuery(const CounselorQuery(q: 'new'));
    await pump();
    slow.complete(aPage([aCounselor(id: 'old', name: 'Old result')]));
    await pump();

    expect(state(c).items.map((i) => i.name), ['New result']);
    expect(state(c).query.q, 'new');
  });

  test('a failed first page says why, keeps the search, and "try again" works', () async {
    api.searchHandler = (q, page) async => throw const NetworkFailure();
    final c = await signedIn();
    await pump();

    expect(state(c).failure, isA<NetworkFailure>());
    expect(state(c).items, isEmpty);
    expect(state(c).loading, isFalse);

    api.searchHandler = (q, page) async => aPage(people(1, 2));
    await controller(c).refresh();

    expect(state(c).failure, isNull);
    expect(state(c).items, hasLength(2));
  });

  test('a failure while loading more keeps what is already shown', () async {
    api.searchHandler = (q, page) async => page == 1 ? aPage(people(1, 20), lastPage: 2, total: 40) : throw const RateLimitedFailure(retryAfterSeconds: 10);
    final c = await signedIn();
    await pump();

    await controller(c).loadMore();

    expect(state(c).items, hasLength(20));
    expect(state(c).failure, isA<RateLimitedFailure>());
    expect(state(c).loadingMore, isFalse);

    api.searchHandler = (q, page) async => aPage(people(21, 20), page: 2, lastPage: 2, total: 40);
    await controller(c).loadMore();

    expect(state(c).items, hasLength(40));
    expect(state(c).failure, isNull);
  });

  test('pull to refresh keeps the list on screen until the new answer arrives, then replaces it', () async {
    api.searchHandler = (q, page) async => aPage(people(1, 2));
    final c = await signedIn();
    await pump();

    final gate = Completer<CounselorPage>();
    api.searchHandler = (q, page) => gate.future;
    final refreshing = controller(c).refresh();

    expect(state(c).items, hasLength(2));
    expect(state(c).loading, isTrue);

    gate.complete(aPage([aCounselor(id: 'fresh', name: 'Fresh')]));
    await refreshing;

    expect(state(c).items.map((i) => i.id), ['fresh']);
  });

  group("one person's results never reach the next person", () {
    test('signing out clears the list and signing in as someone else asks the server again', () async {
      api.searchHandler = (q, page) async => aPage([aCounselor(id: 'closed-org', name: 'University Counselor')]);
      final c = await signedIn();
      await pump();
      expect(state(c).items.map((i) => i.name), ['University Counselor']);

      await c.read(sessionControllerProvider.notifier).signOut();
      await pump();

      expect(state(c).items, isEmpty);
      expect(state(c).loadedOnce, isFalse);

      // Another person, who may only see an independent counselor.
      api.searchHandler = (q, page) async => aPage([aCounselor(id: 'public', name: 'Independent Counselor')]);
      await c.read(sessionControllerProvider.notifier).signIn(
            token: '9|mkp_other',
            user: const AppUser(id: 99, name: 'Someone Else', email: 'else@example.com', emailVerified: true),
          );
      await pump();

      expect(state(c).items.map((i) => i.name), ['Independent Counselor']);
      expect(state(c).query, const CounselorQuery());
      expect(api.searches, hasLength(2));
    });

    test('an answer still on its way for the person who signed out is thrown away', () async {
      final late = Completer<CounselorPage>();
      api.searchHandler = (q, page) => late.future;
      final c = await signedIn();
      await pump();

      await c.read(sessionControllerProvider.notifier).signOut();
      await pump();
      late.complete(aPage([aCounselor(id: 'private', name: 'Private Result')]));
      await pump();

      expect(state(c).items, isEmpty);
    });

    test('the filter choices and a profile are fetched again for the next person too', () async {
      final c = await signedIn();
      final sub = c.listen(counselorFilterOptionsProvider, (_, _) {});
      addTearDown(sub.close);
      await c.read(counselorFilterOptionsProvider.future);
      expect(api.filterCalls, 1);

      await c.read(sessionControllerProvider.notifier).signOut();
      await c.read(sessionControllerProvider.notifier).signIn(
            token: '9|mkp_other',
            user: const AppUser(id: 99, name: 'Someone Else', email: 'else@example.com', emailVerified: true),
          );
      await pump();
      await c.read(counselorFilterOptionsProvider.future);

      expect(api.filterCalls, 2);
    });
  });
}
