import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../domain/my_booking.dart';

class BookingListState {
  const BookingListState({
    this.items = const [],
    this.page = 0,
    this.lastPage = 1,
    this.loading = true,
    this.loadingMore = false,
    this.failure,
    this.loadedOnce = false,
  });

  final List<MyBooking> items;
  final int page;
  final int lastPage;

  /// The first page (or a refresh with nothing yet on screen) is being fetched.
  final bool loading;
  final bool loadingMore;

  /// Why the last attempt failed; the list keeps what it already has.
  final AppFailure? failure;
  final bool loadedOnce;

  bool get hasMore => page < lastPage;

  BookingListState copyWith({
    List<MyBooking>? items,
    int? page,
    int? lastPage,
    bool? loading,
    bool? loadingMore,
    AppFailure? failure,
    bool clearFailure = false,
    bool? loadedOnce,
  }) {
    return BookingListState(
      items: items ?? this.items,
      page: page ?? this.page,
      lastPage: lastPage ?? this.lastPage,
      loading: loading ?? this.loading,
      loadingMore: loadingMore ?? this.loadingMore,
      failure: clearFailure ? null : (failure ?? this.failure),
      loadedOnce: loadedOnce ?? this.loadedOnce,
    );
  }
}

/// One list per tab (Upcoming, Past). Rebuilt from nothing when the signed-in person changes, so one person's bookings are never shown to
/// another. A slow answer to an old request is thrown away.
final myBookingsControllerProvider =
    NotifierProvider.autoDispose.family<MyBookingsController, BookingListState, BookingsScope>(MyBookingsController.new);

/// No automatic retry: a failure is shown with "Try again", and the person decides.
final myBookingDetailProvider = FutureProvider.autoDispose.family<MyBookingDetail, String>(
  (ref, id) {
    ref.watch(currentUserIdProvider);

    return ref.read(myBookingsApiProvider).detail(id);
  },
  retry: (_, _) => null,
);

class MyBookingsController extends Notifier<BookingListState> {
  MyBookingsController(this.scope);

  final BookingsScope scope;
  int _ticket = 0;

  @override
  BookingListState build() {
    final userId = ref.watch(currentUserIdProvider);
    _ticket++;

    if (userId == null) return const BookingListState(loading: false);

    Future.microtask(() => _fetch(1, reset: true));

    return const BookingListState();
  }

  /// Pull to refresh: keeps what is on screen until the new answer arrives.
  Future<void> refresh() => _fetch(1, reset: true);

  /// Called when the person scrolls near the end of Past.
  Future<void> loadMore() async {
    if (!state.hasMore || state.loading || state.loadingMore) return;

    await _fetch(state.page + 1, reset: false);
  }

  Future<void> _fetch(int page, {required bool reset}) async {
    if (reset) _ticket++;

    final ticket = _ticket;

    state = reset ? state.copyWith(loading: state.items.isEmpty, loadingMore: false, clearFailure: true) : state.copyWith(loadingMore: true, clearFailure: true);

    try {
      final result = await ref.read(myBookingsApiProvider).list(scope, page: page);

      if (!ref.mounted || ticket != _ticket) return;

      final known = reset ? <String>{} : state.items.map((b) => b.id).toSet();

      state = state.copyWith(
        // Paging while bookings change on the server can repeat one; each booking is shown once.
        items: [...(reset ? const <MyBooking>[] : state.items), ...result.items.where((b) => known.add(b.id))],
        page: result.page,
        lastPage: result.lastPage,
        loading: false,
        loadingMore: false,
        loadedOnce: true,
      );
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) return;

      state = state.copyWith(loading: false, loadingMore: false, failure: failure);
    }
  }
}
