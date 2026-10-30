import '../../../core/error/app_failure.dart';
import '../../counselors/domain/counselor.dart';
import '../domain/availability.dart';

/// A short message the screen shows (and announces) after something changed under the person's feet.
enum PickSlotNotice {
  /// A refresh found that the time they had picked is gone.
  selectionLost,

  /// The confirm screen found that someone else took the time.
  slotTaken,
}

/// Everything the "Pick a slot" screen shows.
class PickSlotState {
  const PickSlotState({
    this.counselor,
    this.bookingWindow,
    this.days = const [],
    this.nextFrom,
    this.mode,
    this.selectedDate,
    this.selected,
    this.takenIds = const {},
    this.notice,
    this.loading = true,
    this.refreshing = false,
    this.loadingMore = false,
    this.failure,
    this.refreshFailure,
    this.loadedAt,
    this.retryUntil,
  });

  final AvailabilityCounselor? counselor;
  final BookingWindow? bookingWindow;

  /// Every day loaded so far, in order (14 days per request).
  final List<AvailabilityDay> days;

  /// The date to ask for next, or null when the booking horizon has been reached.
  final String? nextFrom;

  /// The mode the person is looking at (the toggle). Null until the first answer.
  final SessionMode? mode;
  final String? selectedDate;
  final AvailabilitySlot? selected;

  /// Times the confirm screen said someone else took, until the next answer says so itself.
  final Set<String> takenIds;
  final PickSlotNotice? notice;

  /// The first answer has not arrived yet.
  final bool loading;
  final bool refreshing;
  final bool loadingMore;

  /// Why there is nothing to show (the first load failed, or the counselor can no longer be booked).
  final AppFailure? failure;

  /// Why the last refresh failed; the screen keeps showing the last answer and says so.
  final AppFailure? refreshFailure;
  final DateTime? loadedAt;

  /// After "too many requests": nothing is asked again before this moment.
  final DateTime? retryUntil;

  bool get hasData => counselor != null;

  /// The modes the counselor offers that this app knows how to show.
  List<SessionMode> get modes => PickSlotRules.knownModes(counselor?.modesOffered ?? const []);

  bool get offersBoth => modes.length > 1;

  AvailabilityDay? get day => selectedDate == null ? null : days.where((d) => d.date == selectedDate).firstOrNull;

  /// The times of the chosen day in the chosen mode, booked ones included (a time someone just took shows as booked).
  List<AvailabilitySlot> get daySlots {
    final current = mode;

    return [
      for (final slot in day?.slots ?? const <AvailabilitySlot>[])
        if (current == null || slot.mode == current) PickSlotRules.asShown(slot, takenIds),
    ];
  }

  /// Is there any time anywhere in what has been loaded that can still be picked?
  bool get anyAvailable => PickSlotRules.nearestDate(days, null, takenIds) != null;

  bool dayHasAvailable(AvailabilityDay day) => PickSlotRules.dayHasAvailable(day, mode, takenIds);

  /// How many times of the chosen day are free in the OTHER mode (to say "Try Online").
  int availableOnSelectedDayIn(SessionMode other) =>
      day == null ? 0 : day!.slots.where((s) => s.mode == other && PickSlotRules.asShown(s, takenIds).isAvailable).length;

  PickSlotState copyWith({
    AvailabilityCounselor? counselor,
    BookingWindow? bookingWindow,
    List<AvailabilityDay>? days,
    String? nextFrom,
    bool clearNextFrom = false,
    SessionMode? mode,
    String? selectedDate,
    AvailabilitySlot? selected,
    bool clearSelected = false,
    Set<String>? takenIds,
    PickSlotNotice? notice,
    bool clearNotice = false,
    bool? loading,
    bool? refreshing,
    bool? loadingMore,
    AppFailure? failure,
    bool clearFailure = false,
    AppFailure? refreshFailure,
    bool clearRefreshFailure = false,
    DateTime? loadedAt,
    DateTime? retryUntil,
    bool clearRetryUntil = false,
  }) {
    return PickSlotState(
      counselor: counselor ?? this.counselor,
      bookingWindow: bookingWindow ?? this.bookingWindow,
      days: days ?? this.days,
      nextFrom: clearNextFrom ? null : (nextFrom ?? this.nextFrom),
      mode: mode ?? this.mode,
      selectedDate: selectedDate ?? this.selectedDate,
      selected: clearSelected ? null : (selected ?? this.selected),
      takenIds: takenIds ?? this.takenIds,
      notice: clearNotice ? null : (notice ?? this.notice),
      loading: loading ?? this.loading,
      refreshing: refreshing ?? this.refreshing,
      loadingMore: loadingMore ?? this.loadingMore,
      failure: clearFailure ? null : (failure ?? this.failure),
      refreshFailure: clearRefreshFailure ? null : (refreshFailure ?? this.refreshFailure),
      loadedAt: loadedAt ?? this.loadedAt,
      retryUntil: clearRetryUntil ? null : (retryUntil ?? this.retryUntil),
    );
  }
}

/// The rules of the screen (which date and mode to start on, what a tap clears), with no widgets and no network, so each one is tested alone.
abstract final class PickSlotRules {
  static List<SessionMode> knownModes(List<SessionMode> modes) =>
      [for (final mode in SessionMode.values) if (mode != SessionMode.unknown && modes.contains(mode)) mode];

  /// A time someone else took (said by the confirm screen) is shown as booked, whatever the last answer said.
  static AvailabilitySlot asShown(AvailabilitySlot slot, Set<String> taken) =>
      taken.contains(slot.id) && slot.isAvailable ? slot.copyWith(state: SlotState.booked) : slot;

  /// Does the day have a time that can be picked in [mode] (any mode when null)?
  static bool dayHasAvailable(AvailabilityDay day, SessionMode? mode, Set<String> taken) =>
      day.slots.any((slot) => (mode == null || slot.mode == mode) && asShown(slot, taken).isAvailable);

  /// The first day (in order) with a time that can be picked in [mode].
  static String? nearestDate(List<AvailabilityDay> days, SessionMode? mode, Set<String> taken) =>
      days.where((day) => dayHasAvailable(day, mode, taken)).firstOrNull?.date;

  /// With both modes on offer, start on the one with the earliest free time (online when they tie); otherwise the only one.
  static SessionMode? initialMode(List<AvailabilityDay> days, List<SessionMode> offered, Set<String> taken) {
    final modes = knownModes(offered);

    if (modes.isEmpty) return null;
    if (modes.length == 1) return modes.first;

    AvailabilitySlot? earliest;

    for (final day in days) {
      for (final slot in day.slots) {
        if (!modes.contains(slot.mode) || !asShown(slot, taken).isAvailable) continue;
        if (earliest == null || slot.startsAt.isBefore(earliest.startsAt)) earliest = slot;
      }
    }

    return earliest?.mode ?? modes.first;
  }

  /// The first answer: pick the mode and the nearest day. No time is pre-selected.
  static PickSlotState afterFirstLoad(PickSlotState state, Availability answer, {required DateTime now}) {
    final taken = state.takenIds;
    final mode = initialMode(answer.days, answer.counselor.modesOffered, taken);

    return state.copyWith(
      counselor: answer.counselor,
      bookingWindow: answer.bookingWindow,
      days: answer.days,
      nextFrom: answer.nextFrom,
      clearNextFrom: answer.nextFrom == null,
      mode: mode,
      selectedDate: nearestDate(answer.days, mode, taken) ?? answer.days.firstOrNull?.date,
      clearSelected: true,
      loading: false,
      refreshing: false,
      clearFailure: true,
      clearRefreshFailure: true,
      loadedAt: now,
    );
  }

  /// More days arrived (scrolling to the end). What is picked stays picked; a day is never listed twice.
  static PickSlotState withMoreDays(PickSlotState state, Availability answer) {
    final known = state.days.map((d) => d.date).toSet();
    final added = answer.days.where((d) => !known.contains(d.date)).toList();
    final days = [...state.days, ...added];

    return state.copyWith(
      days: days,
      nextFrom: answer.nextFrom,
      clearNextFrom: answer.nextFrom == null,
      selectedDate: state.selectedDate ?? nearestDate(days, state.mode, state.takenIds),
      loadingMore: false,
    );
  }

  /// A refresh: the answer replaces what was loaded. If the picked time is not free any more it is dropped and the person is told.
  static PickSlotState afterRefresh(PickSlotState state, Availability fresh, {required DateTime now, required List<AvailabilityDay> days}) {
    final picked = state.selected;
    final stillFree = picked == null || days.any((d) => d.slots.any((s) => s.id == picked.id && s.isAvailable && !state.takenIds.contains(s.id)));
    final mode = state.mode != null && knownModes(fresh.counselor.modesOffered).contains(state.mode) ? state.mode : initialMode(days, fresh.counselor.modesOffered, state.takenIds);

    final keepDate = state.selectedDate != null && days.any((d) => d.date == state.selectedDate && dayHasAvailable(d, mode, state.takenIds));
    final date = keepDate ? state.selectedDate : (nearestDate(days, mode, state.takenIds) ?? days.firstOrNull?.date);

    return state.copyWith(
      counselor: fresh.counselor,
      bookingWindow: fresh.bookingWindow,
      days: days,
      nextFrom: fresh.nextFrom,
      clearNextFrom: fresh.nextFrom == null,
      mode: mode,
      selectedDate: date,
      clearSelected: !stillFree || date != state.selectedDate,
      notice: stillFree ? null : PickSlotNotice.selectionLost,
      clearNotice: stillFree && state.notice == PickSlotNotice.selectionLost,
      refreshing: false,
      clearRefreshFailure: true,
      loadedAt: now,
    );
  }

  /// Tapping a date selects it and clears the picked time.
  static PickSlotState selectDate(PickSlotState state, String date) {
    if (!state.days.any((d) => d.date == date)) return state;

    return state.copyWith(selectedDate: date, clearSelected: true, clearNotice: true);
  }

  /// Changing the mode clears the picked time if it is not in the new mode, and moves off a day with nothing in that mode.
  static PickSlotState selectMode(PickSlotState state, SessionMode mode) {
    if (state.mode == mode || !state.modes.contains(mode)) return state;

    final current = state.day;
    final dayStillWorks = current != null && dayHasAvailable(current, mode, state.takenIds);
    final date = dayStillWorks ? state.selectedDate : (nearestDate(state.days, mode, state.takenIds) ?? state.selectedDate);
    final dropPick = state.selected != null && (state.selected!.mode != mode || date != state.selectedDate);

    return state.copyWith(mode: mode, selectedDate: date, clearSelected: dropPick, clearNotice: true);
  }

  /// One free time at a time. A booked time does nothing. The picked time stays picked when tapped again.
  static PickSlotState selectSlot(PickSlotState state, AvailabilitySlot slot) {
    final shown = asShown(slot, state.takenIds);

    if (!shown.isAvailable) return state;
    if (state.selected?.id == shown.id) return state;

    return state.copyWith(selected: shown, clearNotice: true);
  }

  /// The confirm screen said someone else took this time.
  static PickSlotState markTaken(PickSlotState state, String slotId) {
    return state.copyWith(takenIds: {...state.takenIds, slotId}, clearSelected: state.selected?.id == slotId, notice: PickSlotNotice.slotTaken);
  }
}
