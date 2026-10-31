import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../domain/app_notification.dart';

/// The bell's number. Asked again whenever Home is opened or the app comes back; any failure is just "none to show": the bell never shows an error.
final unreadNotificationsProvider = FutureProvider.autoDispose<int>(
  (ref) async {
    if (ref.watch(currentUserIdProvider) == null) return 0;

    try {
      return await ref.read(notificationsApiProvider).unread();
    } on AppFailure {
      return 0;
    }
  },
  retry: (_, _) => null,
);

class NotificationsState {
  const NotificationsState({this.items = const [], this.unread = 0, this.page = 0, this.lastPage = 1, this.loading = true, this.loadingMore = false, this.failure});

  final List<AppNotification> items;
  final int unread;
  final int page;
  final int lastPage;
  final bool loading;
  final bool loadingMore;
  final AppFailure? failure;

  bool get hasMore => page < lastPage;

  NotificationsState copyWith({List<AppNotification>? items, int? unread, int? page, int? lastPage, bool? loading, bool? loadingMore, AppFailure? failure, bool clearFailure = false}) =>
      NotificationsState(
        items: items ?? this.items,
        unread: unread ?? this.unread,
        page: page ?? this.page,
        lastPage: lastPage ?? this.lastPage,
        loading: loading ?? this.loading,
        loadingMore: loadingMore ?? this.loadingMore,
        failure: clearFailure ? null : (failure ?? this.failure),
      );
}

/// The person's notifications, newest first. Starts from nothing for the next person who signs in. What the server says is what is shown: a mark
/// as read changes the row only once the server has answered, and the bell's number follows.
final notificationsControllerProvider = NotifierProvider.autoDispose<NotificationsController, NotificationsState>(NotificationsController.new);

class NotificationsController extends Notifier<NotificationsState> {
  int _ticket = 0;

  @override
  NotificationsState build() {
    final userId = ref.watch(currentUserIdProvider);
    _ticket++;

    if (userId == null) return const NotificationsState(loading: false);

    Future.microtask(() => _fetch(1, reset: true));

    return const NotificationsState();
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
      final result = await ref.read(notificationsApiProvider).list(page: page);

      if (!ref.mounted || ticket != _ticket) return;

      final known = reset ? <String>{} : state.items.map((n) => n.id).toSet();

      state = state.copyWith(
        items: [...(reset ? const <AppNotification>[] : state.items), ...result.items.where((n) => known.add(n.id))],
        unread: result.unread,
        page: result.page,
        lastPage: result.lastPage,
        loading: false,
        loadingMore: false,
      );
      ref.invalidate(unreadNotificationsProvider);
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) return;

      state = state.copyWith(loading: false, loadingMore: false, failure: failure);
    }
  }

  /// Opening one marks it read. A failure here is quiet: the person is taken to the page they asked for anyway, and the row simply stays unread.
  Future<void> markRead(AppNotification notification) async {
    if (notification.read) return;

    try {
      final unread = await ref.read(notificationsApiProvider).markRead(notification.id);

      if (!ref.mounted) return;

      state = state.copyWith(items: [for (final n in state.items) n.id == notification.id ? n.asRead() : n], unread: unread);
      ref.invalidate(unreadNotificationsProvider);
    } on AppFailure {
      // Not marked; nothing else changes.
    }
  }

  /// Returns false if the server could not be reached (the page then says so).
  Future<bool> markAllRead() async {
    try {
      final unread = await ref.read(notificationsApiProvider).markAllRead();

      if (!ref.mounted) return true;

      state = state.copyWith(items: [for (final n in state.items) n.asRead()], unread: unread);
      ref.invalidate(unreadNotificationsProvider);

      return true;
    } on AppFailure {
      return false;
    }
  }
}
