import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../counselors/domain/counselor.dart';
import '../domain/availability.dart';
import 'pick_slot_state.dart';

/// One "Pick a slot" screen per counselor; leaving the screen (or signing out) throws it away, so nothing outlives the person.
final pickSlotControllerProvider =
    NotifierProvider.autoDispose.family<PickSlotController, PickSlotState, String>(PickSlotController.new);

/// Loads the times, keeps what the person picked, and reconciles it with a refresh. It only reads: nothing is booked or held here.
class PickSlotController extends Notifier<PickSlotState> {
  PickSlotController(this.counselorId);

  final String counselorId;
  int _ticket = 0;

  @override
  PickSlotState build() {
    ref.watch(currentUserIdProvider); // a different person starts again from nothing
    _ticket++;

    Future.microtask(_loadFirst);

    return const PickSlotState();
  }

  DateTime get _now => ref.read(clockProvider)();

  bool get _coolingDown {
    final until = state.retryUntil;

    return until != null && _now.isBefore(until);
  }

  Future<void> _loadFirst() async {
    final ticket = ++_ticket;

    try {
      final answer = await ref.read(availabilityApiProvider).fetch(counselorId);

      if (!ref.mounted || ticket != _ticket) return;

      state = PickSlotRules.afterFirstLoad(state, answer, now: _now);
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) return;

      state = state.copyWith(loading: false, failure: failure, retryUntil: _retryUntil(failure));
    }
  }

  /// "Try again" after the first load failed.
  Future<void> retry() async {
    if (_coolingDown) return;

    state = state.copyWith(loading: true, clearFailure: true);
    await _loadFirst();
  }

  /// Pull to refresh (or coming back to the screen): the newest answer replaces what is shown. A failure keeps the last answer on screen.
  Future<void> refresh() async {
    if (!state.hasData) return retry();
    if (state.refreshing || _coolingDown) return;

    final ticket = ++_ticket;
    state = state.copyWith(refreshing: true, clearRefreshFailure: true);

    try {
      final answer = await ref.read(availabilityApiProvider).fetch(counselorId);

      if (!ref.mounted || ticket != _ticket) return;

      state = PickSlotRules.afterRefresh(state, answer, now: _now, days: answer.days);
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) return;

      if (failure is NotFoundFailure) {
        // The counselor can no longer be booked; the picked time goes with it.
        state = state.copyWith(refreshing: false, failure: failure, clearSelected: true);

        return;
      }

      state = state.copyWith(refreshing: false, refreshFailure: failure, retryUntil: _retryUntil(failure));
    }
  }

  /// Scrolling to the end of the loaded days: the next 14. Stops at the booking horizon (no next date).
  Future<void> loadMore() async {
    final from = state.nextFrom;

    if (from == null || state.loadingMore || state.loading || state.refreshing || _coolingDown) return;

    final ticket = _ticket;
    state = state.copyWith(loadingMore: true, clearRefreshFailure: true);

    try {
      final answer = await ref.read(availabilityApiProvider).fetch(counselorId, from: from);

      if (!ref.mounted || ticket != _ticket) return;

      state = PickSlotRules.withMoreDays(state, answer);
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) return;

      state = state.copyWith(loadingMore: false, refreshFailure: failure, retryUntil: _retryUntil(failure));
    }
  }

  void selectDate(String date) => state = PickSlotRules.selectDate(state, date);

  void selectMode(SessionMode mode) => state = PickSlotRules.selectMode(state, mode);

  void selectSlot(AvailabilitySlot slot) => state = PickSlotRules.selectSlot(state, slot);

  /// The confirm screen found that someone else took this time.
  void markTaken(String slotId) => state = PickSlotRules.markTaken(state, slotId);

  void dismissNotice() => state = state.copyWith(clearNotice: true);

  DateTime? _retryUntil(AppFailure failure) {
    if (failure is! RateLimitedFailure) return null;

    return _now.add(Duration(seconds: failure.retryAfterSeconds ?? 30));
  }
}
