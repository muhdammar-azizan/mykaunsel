import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../domain/time_request.dart';

class RequestListState {
  const RequestListState({
    this.items = const [],
    this.page = 0,
    this.lastPage = 1,
    this.loading = true,
    this.loadingMore = false,
    this.failure,
  });

  final List<TimeRequest> items;
  final int page;
  final int lastPage;
  final bool loading;
  final bool loadingMore;

  /// Why the last attempt failed; the list keeps what it already has.
  final AppFailure? failure;

  bool get hasMore => page < lastPage;

  RequestListState copyWith({List<TimeRequest>? items, int? page, int? lastPage, bool? loading, bool? loadingMore, AppFailure? failure, bool clearFailure = false}) {
    return RequestListState(
      items: items ?? this.items,
      page: page ?? this.page,
      lastPage: lastPage ?? this.lastPage,
      loading: loading ?? this.loading,
      loadingMore: loadingMore ?? this.loadingMore,
      failure: clearFailure ? null : (failure ?? this.failure),
    );
  }
}

/// The person's own requests, newest first. Rebuilt from nothing when the signed-in person changes. A slow answer to an old request is
/// thrown away.
final myRequestsControllerProvider = NotifierProvider.autoDispose<MyRequestsController, RequestListState>(MyRequestsController.new);

/// No automatic retry: a failure is shown with "Try again", and the person decides.
final myRequestProvider = FutureProvider.autoDispose.family<TimeRequest, String>(
  (ref, id) {
    ref.watch(currentUserIdProvider);

    return ref.read(requestsApiProvider).detail(id);
  },
  retry: (_, _) => null,
);

class MyRequestsController extends Notifier<RequestListState> {
  int _ticket = 0;

  @override
  RequestListState build() {
    final userId = ref.watch(currentUserIdProvider);
    _ticket++;

    if (userId == null) return const RequestListState(loading: false);

    Future.microtask(() => _fetch(1, reset: true));

    return const RequestListState();
  }

  Future<void> refresh() => _fetch(1, reset: true);

  Future<void> loadMore() async {
    if (!state.hasMore || state.loading || state.loadingMore) return;

    await _fetch(state.page + 1, reset: false);
  }

  Future<void> _fetch(int page, {required bool reset}) async {
    if (reset) _ticket++;

    final ticket = _ticket;

    state = reset ? state.copyWith(loading: state.items.isEmpty, loadingMore: false, clearFailure: true) : state.copyWith(loadingMore: true, clearFailure: true);

    try {
      final result = await ref.read(requestsApiProvider).list(page: page);

      if (!ref.mounted || ticket != _ticket) return;

      final known = reset ? <String>{} : state.items.map((r) => r.id).toSet();

      state = state.copyWith(
        items: [...(reset ? const <TimeRequest>[] : state.items), ...result.items.where((r) => known.add(r.id))],
        page: result.page,
        lastPage: result.lastPage,
        loading: false,
        loadingMore: false,
      );
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) return;

      state = state.copyWith(loading: false, loadingMore: false, failure: failure);
    }
  }
}
