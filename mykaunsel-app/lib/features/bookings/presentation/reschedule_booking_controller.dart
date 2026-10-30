import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../booking/domain/availability.dart';
import 'my_bookings_controller.dart';

/// What stopped the move, and so what the sheet says and offers.
enum RescheduleProblem {
  /// The time was taken by someone else, or can't be booked any more: the picker marks it and the person picks another.
  slotTaken,

  /// The counselor edited the time after the person saw it: the picker reloads and the person looks again.
  slotChanged,
  timeConflict,

  /// Too close to the start now (the server decided).
  closed,
  limitReached,
  notReschedulable,

  /// The booking, or the counselor, is not there for this person any more.
  gone,
  rateLimited,

  /// No answer: the booking may or may not have been moved. Trying again is SAFE (asking for the same time twice changes nothing more).
  network,
  server,
  other,
}

class RescheduleState {
  const RescheduleState({this.busy = false, this.problem, this.waitSeconds, this.hours, this.done = false});

  final bool busy;
  final RescheduleProblem? problem;
  final int? waitSeconds;

  /// The window the server named when it refused.
  final int? hours;
  final bool done;

  RescheduleState copyWith({bool? busy, RescheduleProblem? problem, bool clearProblem = false, int? waitSeconds, int? hours, bool? done}) {
    return RescheduleState(
      busy: busy ?? this.busy,
      problem: clearProblem ? null : (problem ?? this.problem),
      waitSeconds: clearProblem ? null : (waitSeconds ?? this.waitSeconds),
      hours: clearProblem ? null : (hours ?? this.hours),
      done: done ?? this.done,
    );
  }
}

final rescheduleBookingControllerProvider =
    NotifierProvider.autoDispose.family<RescheduleBookingController, RescheduleState, String>(RescheduleBookingController.new);

/// Moves one booking to a time the person picked. The server does the work and checks every rule; this only asks and reports. Asking again
/// for the same time is always safe, so any answer that does not arrive is simply retried by the person.
class RescheduleBookingController extends Notifier<RescheduleState> {
  RescheduleBookingController(this.bookingId);

  final String bookingId;

  @override
  RescheduleState build() => const RescheduleState();

  Future<void> reschedule(AvailabilitySlot slot) async {
    if (state.busy || state.done) return;

    state = state.copyWith(busy: true, clearProblem: true);

    try {
      await ref.read(myBookingsApiProvider).reschedule(
            bookingId,
            slotId: slot.id,
            expectedStartsAt: slot.startsAt,
            expectedDurationMinutes: slot.durationMinutes,
            expectedMode: slot.mode.name,
          );

      if (!ref.mounted) return;

      _refreshWhatIsShown();
      state = state.copyWith(busy: false, done: true);
    } on AppFailure catch (failure) {
      if (!ref.mounted) return;

      state = switch (failure) {
        ConflictFailure(code: 'slot_taken') => state.copyWith(busy: false, problem: RescheduleProblem.slotTaken),
        ConflictFailure(code: 'slot_changed') => state.copyWith(busy: false, problem: RescheduleProblem.slotChanged),
        ConflictFailure(code: 'time_conflict') => state.copyWith(busy: false, problem: RescheduleProblem.timeConflict),
        ConflictFailure(code: 'reschedule_closed', :final body) => _refreshed(RescheduleProblem.closed, hours: (body['cancellation_hours'] as num?)?.toInt()),
        ConflictFailure(code: 'reschedule_limit_reached') => _refreshed(RescheduleProblem.limitReached),
        ConflictFailure(code: 'not_reschedulable') => _refreshed(RescheduleProblem.notReschedulable),
        NotFoundFailure() => state.copyWith(busy: false, problem: RescheduleProblem.gone),
        RateLimitedFailure(:final retryAfterSeconds) => state.copyWith(busy: false, problem: RescheduleProblem.rateLimited, waitSeconds: retryAfterSeconds),
        NetworkFailure() => state.copyWith(busy: false, problem: RescheduleProblem.network),
        ServerFailure() => state.copyWith(busy: false, problem: RescheduleProblem.server),
        UnauthorizedFailure() => state.copyWith(busy: false), // the router takes the person to sign-in
        _ => state.copyWith(busy: false, problem: RescheduleProblem.other),
      };
    }
  }

  /// The booking's page now says what the server says (it can't be moved any more).
  RescheduleState _refreshed(RescheduleProblem problem, {int? hours}) {
    ref.invalidate(myBookingDetailProvider(bookingId));

    return state.copyWith(busy: false, problem: problem, hours: hours);
  }

  /// The booking's page and both lists must show the new time.
  void _refreshWhatIsShown() {
    ref.invalidate(myBookingDetailProvider(bookingId));
    ref.invalidate(myBookingsControllerProvider);
  }
}
