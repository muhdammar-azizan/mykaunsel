import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../domain/counselor.dart';
import '../domain/counselor_query.dart';

/// Everything the search screen shows.
class CounselorListState {
  const CounselorListState({
    this.query = const CounselorQuery(),
    this.items = const [],
    this.page = 0,
    this.lastPage = 1,
    this.total = 0,
    this.loading = false,
    this.loadingMore = false,
    this.failure,
    this.loadedOnce = false,
  });

  final CounselorQuery query;
  final List<Counselor> items;
  final int page;
  final int lastPage;
  final int total;

  /// The first page (or a new search) is being fetched.
  final bool loading;

  /// The next page is being fetched.
  final bool loadingMore;

  /// Why the last attempt failed; the list keeps what it already has.
  final AppFailure? failure;

  /// Has at least one answer ever arrived (tells "empty" from "not asked yet").
  final bool loadedOnce;

  bool get hasMore => page < lastPage;

  CounselorListState copyWith({
    CounselorQuery? query,
    List<Counselor>? items,
    int? page,
    int? lastPage,
    int? total,
    bool? loading,
    bool? loadingMore,
    AppFailure? failure,
    bool clearFailure = false,
    bool? loadedOnce,
  }) {
    return CounselorListState(
      query: query ?? this.query,
      items: items ?? this.items,
      page: page ?? this.page,
      lastPage: lastPage ?? this.lastPage,
      total: total ?? this.total,
      loading: loading ?? this.loading,
      loadingMore: loadingMore ?? this.loadingMore,
      failure: clearFailure ? null : (failure ?? this.failure),
      loadedOnce: loadedOnce ?? this.loadedOnce,
    );
  }
}

/// Lives only while the signed-in app is on screen: leaving it (signing out) throws the whole list away.
final counselorListControllerProvider = NotifierProvider.autoDispose<CounselorListController, CounselorListState>(CounselorListController.new);

final counselorFilterOptionsProvider = FutureProvider.autoDispose<CounselorFilterOptions>((ref) {
  ref.watch(currentUserIdProvider); // the choices depend on who is asking

  return ref.read(counselorApiProvider).filters();
});

final counselorDetailProvider = FutureProvider.autoDispose.family<Counselor, String>((ref, id) {
  ref.watch(currentUserIdProvider);

  return ref.read(counselorApiProvider).detail(id);
});

/// The search: first page, "load more", a new search or filter, pull to refresh.
///
/// Rebuilt from nothing when the signed-in person changes, so one person's results are never shown to another (a member
/// of a closed organization sees counselors a stranger must not). A slow answer to an OLD search is thrown away.
class CounselorListController extends Notifier<CounselorListState> {
  int _ticket = 0;

  @override
  CounselorListState build() {
    final userId = ref.watch(currentUserIdProvider);
    _ticket++; // anything still in flight for the previous person is dropped

    if (userId == null) {
      return const CounselorListState();
    }

    Future.microtask(() => _fetch(1, reset: true));

    return const CounselorListState(loading: true);
  }

  /// A new search or filter: starts again from the first page.
  void setQuery(CounselorQuery query) {
    if (query == state.query) {
      return;
    }

    state = state.copyWith(query: query, items: const [], page: 0, lastPage: 1, total: 0, loadedOnce: false);
    _fetch(1, reset: true);
  }

  /// Pull to refresh: keeps what is on screen until the new answer arrives.
  Future<void> refresh() => _fetch(1, reset: true);

  /// Called when the person scrolls near the end.
  Future<void> loadMore() async {
    if (!state.hasMore || state.loading || state.loadingMore) {
      return;
    }

    await _fetch(state.page + 1, reset: false);
  }

  Future<void> _fetch(int page, {required bool reset}) async {
    if (reset) {
      _ticket++;
    }

    final ticket = _ticket;
    final query = state.query;

    state = reset
        ? state.copyWith(loading: true, loadingMore: false, clearFailure: true)
        : state.copyWith(loadingMore: true, clearFailure: true);

    try {
      final result = await ref.read(counselorApiProvider).search(query, page: page);

      if (!ref.mounted || ticket != _ticket) {
        return;
      }

      final known = reset ? <String>{} : state.items.map((c) => c.id).toSet();

      state = state.copyWith(
        // Paging while counselors change on the server can repeat one; each counselor is shown once.
        items: [...(reset ? const <Counselor>[] : state.items), ...result.items.where((c) => known.add(c.id))],
        page: result.page,
        lastPage: result.lastPage,
        total: result.total,
        loading: false,
        loadingMore: false,
        loadedOnce: true,
      );
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) {
        return;
      }

      state = state.copyWith(loading: false, loadingMore: false, failure: failure);
    }
  }
}
