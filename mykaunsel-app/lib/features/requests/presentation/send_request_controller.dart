import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../domain/request_times.dart';
import '../domain/time_request.dart';
import 'my_requests_controller.dart';

/// Why asking did not work, and so what the screen says and offers.
enum SendProblem {
  /// The time is not free on the counselor's calendar any more (the reason is private): pick another.
  slotTaken,
  timeConflict,
  limitReached,
  closed,

  /// The time is not inside the counselor's hours (they changed, or the clock moved on): pick another.
  invalidTime,
  counselorGone,
  emailNotVerified,
  rateLimited,

  /// No answer: the request may or may not have been sent. Asking again is SAFE (the same request is never made twice).
  network,
  server,
  other,
}

class SendRequestState {
  const SendRequestState({
    this.loading = true,
    this.failure,
    this.hours,
    this.days = const [],
    this.selectedDate,
    this.selected,
    this.note = '',
    this.busy = false,
    this.problem,
    this.waitSeconds,
    this.sent,
  });

  final bool loading;
  final AppFailure? failure;
  final WorkingHours? hours;
  final List<RequestDay> days;
  final String? selectedDate;
  final RequestTime? selected;
  final String note;
  final bool busy;
  final SendProblem? problem;
  final int? waitSeconds;

  /// Set once the request was made: the screen moves on to it.
  final TimeRequest? sent;

  RequestDay? get day => days.where((d) => d.date == selectedDate).firstOrNull;

  SendRequestState copyWith({
    bool? loading,
    AppFailure? failure,
    bool clearFailure = false,
    WorkingHours? hours,
    List<RequestDay>? days,
    String? selectedDate,
    RequestTime? selected,
    bool clearSelected = false,
    String? note,
    bool? busy,
    SendProblem? problem,
    bool clearProblem = false,
    int? waitSeconds,
    TimeRequest? sent,
  }) {
    return SendRequestState(
      loading: loading ?? this.loading,
      failure: clearFailure ? null : (failure ?? this.failure),
      hours: hours ?? this.hours,
      days: days ?? this.days,
      selectedDate: selectedDate ?? this.selectedDate,
      selected: clearSelected ? null : (selected ?? this.selected),
      note: note ?? this.note,
      busy: busy ?? this.busy,
      problem: clearProblem ? null : (problem ?? this.problem),
      waitSeconds: clearProblem ? null : (waitSeconds ?? this.waitSeconds),
      sent: sent ?? this.sent,
    );
  }
}

final sendRequestControllerProvider = NotifierProvider.autoDispose.family<SendRequestController, SendRequestState, String>(SendRequestController.new);

/// "Ask for a time": loads the counselor's weekly hours, keeps what the person picked, and asks. The server checks every rule; this only
/// offers what it could accept and reports its answer.
class SendRequestController extends Notifier<SendRequestState> {
  SendRequestController(this.counselorId);

  final String counselorId;
  int _ticket = 0;

  @override
  SendRequestState build() {
    ref.watch(currentUserIdProvider); // a different person starts again from nothing
    _ticket++;
    Future.microtask(_load);

    return const SendRequestState();
  }

  Future<void> _load() async {
    final ticket = ++_ticket;

    try {
      final hours = await ref.read(requestsApiProvider).workingHours(counselorId);

      if (!ref.mounted || ticket != _ticket) return;

      final days = requestDays(hours, now: ref.read(clockProvider)());

      state = state.copyWith(loading: false, clearFailure: true, hours: hours, days: days, selectedDate: days.firstOrNull?.date);
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) return;

      state = state.copyWith(loading: false, failure: failure);
    }
  }

  Future<void> retry() {
    state = state.copyWith(loading: true, clearFailure: true);

    return _load();
  }

  void selectDate(String date) {
    if (state.busy) return;

    state = state.copyWith(selectedDate: date, clearSelected: true, clearProblem: true);
  }

  void selectTime(RequestTime time) {
    if (state.busy) return;

    state = state.copyWith(selected: time, clearProblem: true);
  }

  void setNote(String note) => state = state.copyWith(note: note);

  Future<void> send() async {
    final time = state.selected;
    final date = state.selectedDate;

    if (state.busy || state.sent != null || time == null || date == null) return;

    state = state.copyWith(busy: true, clearProblem: true);

    try {
      final request = await ref.read(requestsApiProvider).send(counselorId, date: date, time: time.clock, message: state.note);

      if (!ref.mounted) return;

      ref.invalidate(myRequestsControllerProvider);
      state = state.copyWith(busy: false, sent: request);
    } on AppFailure catch (failure) {
      if (!ref.mounted) return;

      state = switch (failure) {
        ConflictFailure(code: 'slot_taken') => _pickAnother(SendProblem.slotTaken),
        ConflictFailure(code: 'time_conflict') => _pickAnother(SendProblem.timeConflict),
        ConflictFailure(code: 'request_limit_reached') => state.copyWith(busy: false, problem: SendProblem.limitReached),
        ConflictFailure(code: 'requests_closed') => state.copyWith(busy: false, problem: SendProblem.closed),
        ValidationFailure() => _pickAnother(SendProblem.invalidTime),
        NotFoundFailure() => state.copyWith(busy: false, problem: SendProblem.counselorGone),
        EmailNotVerifiedFailure() => state.copyWith(busy: false, problem: SendProblem.emailNotVerified),
        RateLimitedFailure(:final retryAfterSeconds) => state.copyWith(busy: false, problem: SendProblem.rateLimited, waitSeconds: retryAfterSeconds),
        NetworkFailure() => state.copyWith(busy: false, problem: SendProblem.network),
        ServerFailure() => state.copyWith(busy: false, problem: SendProblem.server),
        UnauthorizedFailure() => state.copyWith(busy: false), // the router takes the person to sign-in
        _ => state.copyWith(busy: false, problem: SendProblem.other),
      };
    }
  }

  /// The time can't be used: the pick is cleared so the person chooses again.
  SendRequestState _pickAnother(SendProblem problem) => state.copyWith(busy: false, problem: problem, clearSelected: true);
}
