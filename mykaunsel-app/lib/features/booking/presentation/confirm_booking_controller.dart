import 'dart:math';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../counselors/domain/counselor.dart';
import '../domain/availability.dart';
import '../domain/booking_preview.dart';
import 'booking_args.dart';

/// A new random key for one booking attempt (a version 4 UUID). Kept in memory only; a test replaces this to know the keys.
final idempotencyKeyFactoryProvider = Provider<String Function()>((ref) => _newUuid);

/// The waits between automatic retries of a booking whose answer did not arrive (same key each time). Empty = no retry. Tests shorten them.
final bookingRetryDelaysProvider = Provider<List<Duration>>((ref) => const [Duration(seconds: 1), Duration(seconds: 2), Duration(seconds: 4)]);

String _newUuid() {
  final random = Random.secure();
  final bytes = List<int>.generate(16, (_) => random.nextInt(256));
  bytes[6] = (bytes[6] & 0x0f) | 0x40; // version 4
  bytes[8] = (bytes[8] & 0x3f) | 0x80; // variant

  String hex(int from, int to) => bytes.sublist(from, to).map((b) => b.toRadixString(16).padLeft(2, '0')).join();

  return '${hex(0, 4)}-${hex(4, 6)}-${hex(6, 8)}-${hex(8, 10)}-${hex(10, 16)}';
}

/// Which booking this screen is about. Only the two ids make it the same screen; the [snapshot] (what "Pick a slot" already knew) is just
/// there so something shows at once. Without it (after a login redirect) the screen restores itself from the ids alone.
class ConfirmTarget {
  const ConfirmTarget({required this.counselorId, required this.slotId, this.snapshot});

  final String counselorId;
  final String slotId;
  final ConfirmBookingArgs? snapshot;

  @override
  bool operator ==(Object other) => other is ConfirmTarget && other.counselorId == counselorId && other.slotId == slotId;

  @override
  int get hashCode => Object.hash(counselorId, slotId);
}

/// The part of the session the summary card shows. Starts from the snapshot, then follows the server.
class ConfirmSummary {
  const ConfirmSummary({
    required this.counselorName,
    required this.verified,
    required this.startsAt,
    required this.endsAt,
    required this.durationMinutes,
    required this.mode,
    this.location,
  });

  factory ConfirmSummary.fromArgs(ConfirmBookingArgs args, {bool verified = true}) => ConfirmSummary(
        counselorName: args.counselorName,
        verified: verified,
        startsAt: args.startsAt,
        endsAt: args.startsAt.add(Duration(minutes: args.durationMinutes)),
        durationMinutes: args.durationMinutes,
        mode: args.mode,
        location: args.location,
      );

  factory ConfirmSummary.fromDetails(BookingDetails d) => ConfirmSummary(
        counselorName: d.counselor.displayName,
        verified: d.counselor.verified,
        startsAt: d.startsAt,
        endsAt: d.endsAt,
        durationMinutes: d.durationMinutes,
        mode: d.mode,
        location: d.location,
      );

  final String counselorName;
  final bool verified;
  final DateTime startsAt;
  final DateTime endsAt;
  final int durationMinutes;
  final SessionMode mode;
  final SlotLocation? location;

  bool differsFrom(BookingDetails d) =>
      startsAt != d.startsAt || durationMinutes != d.durationMinutes || mode != d.mode || location != d.location;
}

enum ConfirmBanner { none, checking, offline, detailsChanged }

/// Why the person cannot go on (or what to do next). The screen turns each into words and the right button.
enum ConfirmProblem {
  timeConflict,
  upcomingLimit,
  emailNotVerified,
  counselorUnavailable,
  rateLimited,

  /// A 5xx: "try again" with the SAME key, because the booking may have been made.
  serverError,

  /// No answer after the automatic retries: NEVER assumed to have failed.
  unknownOutcome,
  other,
}

class ConfirmState {
  const ConfirmState({
    this.summary,
    this.details,
    this.checking = true,
    this.offline = false,
    this.valid = false,
    this.detailsChanged = false,
    this.acknowledged = false,
    this.submitting = false,
    this.problem,
    this.problemMessage,
    this.maxUpcoming,
    this.waitSeconds,
    this.created,
    this.slotTaken = false,
  });

  final ConfirmSummary? summary;

  /// The policy numbers; null until the first preview has answered.
  final BookingDetails? details;
  final bool checking;
  final bool offline;

  /// The last check said the booking would be accepted.
  final bool valid;
  final bool detailsChanged;
  final bool acknowledged;
  final bool submitting;
  final ConfirmProblem? problem;
  final String? problemMessage;
  final int? maxUpcoming;
  final int? waitSeconds;

  /// Set once: the screen replaces itself with "Booking confirmed".
  final CreatedBooking? created;

  /// Set once: the screen goes back to the picker, which marks the time as taken.
  final bool slotTaken;

  ConfirmBanner get banner {
    if (checking) return ConfirmBanner.checking;
    if (offline) return ConfirmBanner.offline;
    if (detailsChanged) return ConfirmBanner.detailsChanged;

    return ConfirmBanner.none;
  }

  bool get canConfirm => acknowledged && valid && !checking && !offline && !submitting && problem == null && details != null;

  /// What to tell a screen reader about why the button is off (null when it is on).
  ConfirmDisabledReason? get disabledReason {
    if (canConfirm || submitting) return null;
    if (checking) return ConfirmDisabledReason.checking;
    if (offline) return ConfirmDisabledReason.offline;
    if (problem != null || !valid) return ConfirmDisabledReason.unavailable;

    return ConfirmDisabledReason.tick;
  }

  ConfirmState copyWith({
    ConfirmSummary? summary,
    BookingDetails? details,
    bool? checking,
    bool? offline,
    bool? valid,
    bool? detailsChanged,
    bool? acknowledged,
    bool? submitting,
    ConfirmProblem? problem,
    bool clearProblem = false,
    String? problemMessage,
    int? maxUpcoming,
    int? waitSeconds,
    CreatedBooking? created,
    bool? slotTaken,
  }) {
    return ConfirmState(
      summary: summary ?? this.summary,
      details: details ?? this.details,
      checking: checking ?? this.checking,
      offline: offline ?? this.offline,
      valid: valid ?? this.valid,
      detailsChanged: detailsChanged ?? this.detailsChanged,
      acknowledged: acknowledged ?? this.acknowledged,
      submitting: submitting ?? this.submitting,
      problem: clearProblem ? null : (problem ?? this.problem),
      problemMessage: clearProblem ? null : (problemMessage ?? this.problemMessage),
      maxUpcoming: clearProblem ? null : (maxUpcoming ?? this.maxUpcoming),
      waitSeconds: clearProblem ? null : (waitSeconds ?? this.waitSeconds),
      created: created ?? this.created,
      slotTaken: slotTaken ?? this.slotTaken,
    );
  }
}

enum ConfirmDisabledReason { checking, offline, unavailable, tick }

final confirmBookingControllerProvider =
    NotifierProvider.autoDispose.family<ConfirmBookingController, ConfirmState, ConfirmTarget>(ConfirmBookingController.new);

/// Checks the time with the server, keeps the person's tick, and makes the booking.
///
/// The time is NOT held while this screen is open: it is claimed by the server at the moment of confirming. The Idempotency-Key is made when
/// the screen opens and lives in memory only. It is REUSED for automatic retries (a timeout, a 5xx, "still processing"), because then the
/// booking may already exist; it is replaced only after a definite refusal (a 4xx), so the next tap is a new request.
class ConfirmBookingController extends Notifier<ConfirmState> {
  ConfirmBookingController(this.target);

  final ConfirmTarget target;

  late String _key;
  int _ticket = 0;

  @override
  ConfirmState build() {
    ref.watch(currentUserIdProvider); // a different person starts again from nothing
    _key = ref.read(idempotencyKeyFactoryProvider)();
    Future.microtask(_check);

    final snapshot = target.snapshot;

    return ConfirmState(summary: snapshot == null ? null : ConfirmSummary.fromArgs(snapshot));
  }

  // ---- the check (read only) -------------------------------------------------------------------------------------

  /// "Check again" (after no connection or an error) and coming back to the screen.
  Future<void> recheck() async {
    if (state.submitting) return;

    state = state.copyWith(checking: true, offline: false, clearProblem: true);
    await _check();
  }

  Future<void> _check() async {
    final ticket = ++_ticket;

    try {
      final preview = await ref.read(bookingApiProvider).preview(target.slotId);

      if (!ref.mounted || ticket != _ticket) return;

      final details = preview.details;

      if (!preview.valid && preview.reason == 'slot_taken') {
        state = state.copyWith(checking: false, valid: false, slotTaken: true);

        return;
      }

      if (details == null) {
        state = state.copyWith(checking: false, valid: false, problem: ConfirmProblem.other);

        return;
      }

      state = _withDetails(details, forceUntick: false).copyWith(
        checking: false,
        offline: false,
        valid: preview.valid,
        problem: switch (preview.reason) {
          'time_conflict' => ConfirmProblem.timeConflict,
          'upcoming_limit_reached' => ConfirmProblem.upcomingLimit,
          _ => null,
        },
        maxUpcoming: details.maxUpcomingSessions,
      );
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) return;

      state = _afterCheckFailure(failure);
    }
  }

  ConfirmState _afterCheckFailure(AppFailure failure) {
    final base = state.copyWith(checking: false, valid: false);

    return switch (failure) {
      NetworkFailure() => base.copyWith(offline: true),
      NotFoundFailure() => base.copyWith(problem: ConfirmProblem.counselorUnavailable),
      EmailNotVerifiedFailure() => base.copyWith(problem: ConfirmProblem.emailNotVerified),
      RateLimitedFailure(:final retryAfterSeconds) => base.copyWith(problem: ConfirmProblem.rateLimited, waitSeconds: retryAfterSeconds),
      UnauthorizedFailure() => base, // the session ends and the router takes the person to sign-in
      ServerFailure() => base.copyWith(problem: ConfirmProblem.serverError),
      _ => base.copyWith(problem: ConfirmProblem.other),
    };
  }

  /// Takes the server's details into the screen. If what the person SEES changed (time, length, kind, place), the tick is taken back and the
  /// banner says so; a change of policy numbers alone is shown quietly.
  ConfirmState _withDetails(BookingDetails details, {required bool forceUntick}) {
    final seen = state.summary;
    final changed = forceUntick || (seen != null && seen.differsFrom(details));

    return state.copyWith(
      summary: ConfirmSummary.fromDetails(details),
      details: details,
      acknowledged: changed ? false : state.acknowledged,
      detailsChanged: changed ? true : state.detailsChanged,
    );
  }

  // ---- the tick ----------------------------------------------------------------------------------------------------

  void setAcknowledged(bool value) {
    if (state.submitting) return;

    // Ticking again is the person reviewing the changed details: the banner has done its job.
    state = state.copyWith(acknowledged: value, detailsChanged: value ? false : state.detailsChanged);
  }

  // ---- the booking ---------------------------------------------------------------------------------------------------

  /// "Confirm booking". Does nothing unless the box is ticked and the check is good (the button is off then too).
  Future<void> confirm() async {
    if (!state.canConfirm) return;

    state = state.copyWith(submitting: true, clearProblem: true);
    await _submit();
  }

  /// After "We couldn't confirm" or a server error: asks again with the SAME key. If the booking was made, this returns it; if not, it makes it.
  Future<void> retrySubmit() async {
    if (state.submitting || state.details == null) return;

    state = state.copyWith(submitting: true, clearProblem: true);
    await _submit();
  }

  /// The app came back to the front and the last answer is unknown: resolve it with an idempotent retry.
  Future<void> resolveUnknown() async {
    if (state.problem == ConfirmProblem.unknownOutcome) await retrySubmit();
  }

  Future<void> _submit() async {
    final details = state.details!;
    final delays = ref.read(bookingRetryDelaysProvider);
    AppFailure? last;

    for (var attempt = 0; attempt <= delays.length; attempt++) {
      try {
        final created = await ref.read(bookingApiProvider).create(
              idempotencyKey: _key,
              slotId: target.slotId,
              expectedStartsAt: details.startsAt,
              expectedDurationMinutes: details.durationMinutes,
              expectedMode: details.mode.name,
            );

        if (ref.mounted) state = state.copyWith(submitting: false, created: created);

        return;
      } on AppFailure catch (failure) {
        if (!ref.mounted) return;

        last = failure;

        if (!_mayHaveBeenMade(failure)) {
          _refused(failure);

          return;
        }
      }

      if (attempt < delays.length) {
        await Future<void>.delayed(delays[attempt]);

        if (!ref.mounted) return;
      }
    }

    // Still no clear answer after the retries. A 5xx offers "Try again" (same key); anything else is UNKNOWN, never "failed".
    state = state.copyWith(
      submitting: false,
      problem: last is ServerFailure ? ConfirmProblem.serverError : ConfirmProblem.unknownOutcome,
    );
  }

  /// Could the server have made the booking even though no clear answer came back?
  bool _mayHaveBeenMade(AppFailure failure) => switch (failure) {
        NetworkFailure() || ServerFailure() => true,
        ConflictFailure(:final code) => code == 'request_in_progress',
        _ => false,
      };

  /// A definite refusal (a 4xx): nothing was booked. The next tap is a NEW request, so a new key.
  void _refused(AppFailure failure) {
    _key = ref.read(idempotencyKeyFactoryProvider)();
    final base = state.copyWith(submitting: false);

    state = switch (failure) {
      ConflictFailure(code: 'slot_taken') => base.copyWith(slotTaken: true),
      ConflictFailure(code: 'slot_changed', :final body) => _afterSlotChanged(base, body),
      ConflictFailure(code: 'time_conflict') => base.copyWith(problem: ConfirmProblem.timeConflict),
      ConflictFailure(code: 'upcoming_limit_reached', :final body) =>
        base.copyWith(problem: ConfirmProblem.upcomingLimit, maxUpcoming: (body['max_upcoming_sessions'] as num?)?.toInt()),
      NotFoundFailure() => base.copyWith(problem: ConfirmProblem.counselorUnavailable),
      EmailNotVerifiedFailure() => base.copyWith(problem: ConfirmProblem.emailNotVerified),
      RateLimitedFailure(:final retryAfterSeconds) => base.copyWith(problem: ConfirmProblem.rateLimited, waitSeconds: retryAfterSeconds),
      UnauthorizedFailure() => base, // the router takes the person to sign-in; they tap Confirm again after, nothing is resent
      ValidationFailure(:final message) => base.copyWith(problem: ConfirmProblem.other, problemMessage: message),
      _ => base.copyWith(problem: ConfirmProblem.other),
    };
  }

  ConfirmState _afterSlotChanged(ConfirmState base, Map<String, dynamic> body) {
    final fresh = body['slot'];

    if (fresh is! Map) return base.copyWith(problem: ConfirmProblem.other);

    state = base;

    return _withDetails(BookingDetails.fromJson(Map<String, dynamic>.from(fresh)), forceUntick: true).copyWith(valid: true);
  }
}
