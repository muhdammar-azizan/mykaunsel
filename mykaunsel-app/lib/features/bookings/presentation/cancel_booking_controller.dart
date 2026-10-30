import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import 'my_bookings_controller.dart';

/// What stopped the cancelling, and so what the sheet says and offers.
enum CancelProblem {
  /// Too close to the start now (the server decided): the sheet turns into the "can't be cancelled" explanation.
  closed,
  notCancellable,
  notFound,
  rateLimited,

  /// No answer: the booking may or may not have been cancelled. Trying again is SAFE (cancelling twice changes nothing more).
  network,
  server,
  other,
}

class CancelState {
  const CancelState({this.busy = false, this.problem, this.waitSeconds, this.hours, this.done = false});

  final bool busy;
  final CancelProblem? problem;
  final int? waitSeconds;

  /// The cancellation window the server named when it refused.
  final int? hours;
  final bool done;

  CancelState copyWith({bool? busy, CancelProblem? problem, bool clearProblem = false, int? waitSeconds, int? hours, bool? done}) {
    return CancelState(
      busy: busy ?? this.busy,
      problem: clearProblem ? null : (problem ?? this.problem),
      waitSeconds: clearProblem ? null : (waitSeconds ?? this.waitSeconds),
      hours: clearProblem ? null : (hours ?? this.hours),
      done: done ?? this.done,
    );
  }
}

final cancelBookingControllerProvider = NotifierProvider.autoDispose.family<CancelBookingController, CancelState, String>(CancelBookingController.new);

/// Cancels one booking. The server does the work and checks every rule; this only asks and reports. Asking again is always safe, so any
/// answer that does not arrive is simply retried by the person.
class CancelBookingController extends Notifier<CancelState> {
  CancelBookingController(this.bookingId);

  final String bookingId;

  @override
  CancelState build() => const CancelState();

  Future<void> cancel() async {
    if (state.busy || state.done) return;

    state = state.copyWith(busy: true, clearProblem: true);

    try {
      await ref.read(myBookingsApiProvider).cancel(bookingId);

      if (!ref.mounted) return;

      _refreshWhatIsShown();
      state = state.copyWith(busy: false, done: true);
    } on AppFailure catch (failure) {
      if (!ref.mounted) return;

      state = switch (failure) {
        ConflictFailure(code: 'cancellation_closed', :final body) => _closed((body['cancellation_hours'] as num?)?.toInt()),
        ConflictFailure(code: 'not_cancellable') => _refreshed(CancelProblem.notCancellable),
        NotFoundFailure() => state.copyWith(busy: false, problem: CancelProblem.notFound),
        RateLimitedFailure(:final retryAfterSeconds) => state.copyWith(busy: false, problem: CancelProblem.rateLimited, waitSeconds: retryAfterSeconds),
        NetworkFailure() => state.copyWith(busy: false, problem: CancelProblem.network),
        ServerFailure() => state.copyWith(busy: false, problem: CancelProblem.server),
        UnauthorizedFailure() => state.copyWith(busy: false), // the router takes the person to sign-in
        _ => state.copyWith(busy: false, problem: CancelProblem.other),
      };
    }
  }

  CancelState _closed(int? hours) {
    ref.invalidate(myBookingDetailProvider(bookingId)); // the booking's own page now says it can't be cancelled

    return state.copyWith(busy: false, problem: CancelProblem.closed, hours: hours);
  }

  CancelState _refreshed(CancelProblem problem) {
    _refreshWhatIsShown();

    return state.copyWith(busy: false, problem: problem);
  }

  /// The booking's page and both lists must show the new state.
  void _refreshWhatIsShown() {
    ref.invalidate(myBookingDetailProvider(bookingId));
    ref.invalidate(myBookingsControllerProvider);
  }
}
