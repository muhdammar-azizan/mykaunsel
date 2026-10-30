import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/error/failure_message.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/format/slot_time.dart';
import '../../counselors/domain/counselor.dart';
import '../domain/my_booking.dart';
import 'booking_labels.dart';
import 'cancel_booking_sheet.dart';
import 'my_bookings_controller.dart';

/// One booking: what, when, with whom, its state, and the SESSION DETAILS: the meeting link (online, inside its window) or the full address
/// (in person). The server decides when the link exists; this screen only shows what it was sent, and asks again when the window opens.
class BookingDetailScreen extends ConsumerStatefulWidget {
  const BookingDetailScreen({super.key, required this.id});

  final String id;

  @override
  ConsumerState<BookingDetailScreen> createState() => _BookingDetailScreenState();
}

class _BookingDetailScreenState extends ConsumerState<BookingDetailScreen> with WidgetsBindingObserver {
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    _timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// Back from the background: the window may have opened or closed meanwhile.
  @override
  void didChangeAppLifecycleState(AppLifecycleState lifecycle) {
    if (lifecycle == AppLifecycleState.resumed) ref.invalidate(myBookingDetailProvider(widget.id));
  }

  /// Ask again at the moment the link window opens (and again when it closes), so "Join session" appears by itself.
  void _scheduleRefresh(MyBookingDetail detail) {
    _timer?.cancel();

    final meeting = detail.meeting;

    if (meeting == null) return;

    final next = switch (meeting.state) {
      MeetingState.opensLater => meeting.opensAt,
      MeetingState.available || MeetingState.notAdded => meeting.closesAt,
      _ => null,
    };

    if (next == null) return;

    final wait = next.difference(ref.read(clockProvider)());

    if (wait.isNegative || wait > const Duration(hours: 24)) return;

    _timer = Timer(wait + const Duration(seconds: 1), () {
      if (mounted) ref.invalidate(myBookingDetailProvider(widget.id));
    });
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final detail = ref.watch(myBookingDetailProvider(widget.id));

    ref.listen(myBookingDetailProvider(widget.id), (_, next) => next.whenData(_scheduleRefresh));

    return Scaffold(
      appBar: AppBar(title: Text(l10n.bookingDetailTitle)),
      body: SafeArea(
        child: detail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) {
            final failure = error is AppFailure ? error : const ServerFailure();

            return Center(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.xxl),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Semantics(
                      liveRegion: true,
                      child: Text(failure is NotFoundFailure ? l10n.bookingNotFound : failureMessage(l10n, failure), textAlign: TextAlign.center),
                    ),
                    if (failure is! NotFoundFailure) ...[
                      const SizedBox(height: AppSpacing.l),
                      FilledButton(onPressed: () => ref.invalidate(myBookingDetailProvider(widget.id)), child: Text(l10n.tryAgain)),
                    ],
                  ],
                ),
              ),
            );
          },
          data: (booking) => _Body(booking: booking),
        ),
      ),
    );
  }
}

class _Body extends ConsumerWidget {
  const _Body({required this.booking});

  final MyBookingDetail booking;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final now = ref.watch(clockProvider)();
    final start = malaysiaWallClock(booking.startsAt);
    final end = malaysiaWallClock(booking.endsAt);
    final cancelled = booking.status == BookingState.cancelled;
    final stillAhead = booking.endsAt.isAfter(now);

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.l),
      children: [
        Row(
          children: [
            Icon(booking.status.icon, size: AppSizes.icon),
            const SizedBox(width: AppSpacing.s),
            Expanded(child: Semantics(header: true, child: Text(booking.status.label(l10n), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800)))),
            if (booking.startingSoon) Text(l10n.bookingStartingSoon, style: text.labelLarge),
          ],
        ),
        if (cancelled) ...[
          const SizedBox(height: AppSpacing.m),
          Semantics(
            liveRegion: true,
            child: Container(
              padding: const EdgeInsets.all(AppSpacing.m),
              decoration: BoxDecoration(color: scheme.bannerFill, borderRadius: AppRadii.chipRadius),
              child: Row(children: [const Icon(Icons.info_outline, size: AppSizes.icon), const SizedBox(width: AppSpacing.s), Expanded(child: Text(l10n.bookingCancelledNote))]),
            ),
          ),
        ],
        const SizedBox(height: AppSpacing.l),
        Container(
          padding: const EdgeInsets.all(AppSpacing.l),
          decoration: BoxDecoration(color: scheme.cardFill, borderRadius: AppRadii.cardRadius, border: Border.all(color: scheme.cardBorder)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Semantics(header: true, child: Text(booking.counselor.displayName, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
              const SizedBox(height: AppSpacing.m),
              _Fact(icon: Icons.calendar_today_outlined, label: l10n.confirmRowDate, value: bookingDate(booking.startsAt, locale)),
              _Fact(
                icon: Icons.schedule,
                label: l10n.confirmRowTime,
                value: l10n.confirmTimeValue(DateFormat.jm(locale).format(start), DateFormat.jm(locale).format(end)),
                spoken: l10n.confirmTimeSpoken(DateFormat.jm(locale).format(start), DateFormat.jm(locale).format(end)),
              ),
              _Fact(icon: Icons.timelapse, label: l10n.confirmRowDuration, value: l10n.confirmDuration(booking.durationMinutes)),
              _Fact(icon: booking.mode == SessionMode.physical ? Icons.place_outlined : Icons.videocam_outlined, label: l10n.confirmRowType, value: bookingType(l10n, booking.mode, booking.location)),
              _Fact(icon: Icons.tag, label: l10n.bookingReference, value: booking.id),
            ],
          ),
        ),
        if (booking.meeting != null || booking.place != null) ...[
          const SizedBox(height: AppSpacing.l),
          Semantics(header: true, child: Text(l10n.bookingSessionDetails, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800))),
          const SizedBox(height: AppSpacing.s),
          if (booking.meeting != null) _Meeting(meeting: booking.meeting!, locale: locale),
          if (booking.place != null) _Place(place: booking.place!),
        ],
        if (booking.status == BookingState.confirmed && stillAhead) ...[
          const SizedBox(height: AppSpacing.l),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(booking.canCancel ? Icons.event_busy_outlined : Icons.info_outline, size: AppSizes.icon, color: scheme.mutedText),
              const SizedBox(width: AppSpacing.m),
              Expanded(
                child: Text(
                  booking.canCancel && booking.cancellationDeadlineAt != null
                      ? l10n.bookingCanCancelUntil(_deadline(booking.cancellationDeadlineAt!, locale))
                      : l10n.bookingCannotCancel,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.m),
          // Always there for a booking still ahead: the server decides what the sheet says (cancel, or why it is too late).
          OutlinedButton.icon(
            onPressed: () async {
              final messenger = ScaffoldMessenger.of(context);
              final cancelled = await showCancelBookingSheet(context, booking);

              if (cancelled) messenger.showSnackBar(SnackBar(content: Text(l10n.bookingCancelledSnack)));
            },
            icon: const Icon(Icons.event_busy_outlined),
            label: Text(l10n.bookingCancelButton),
          ),
        ],
        const SizedBox(height: AppSpacing.s),
        Text(l10n.malaysiaTimeNote, style: text.bodySmall),
      ],
    );
  }

  String _deadline(DateTime at, String locale) {
    final wall = malaysiaWallClock(at);

    return '${DateFormat('EEE d MMM', locale).format(wall)}, ${DateFormat.jm(locale).format(wall)}';
  }
}

/// One labelled fact, announced as its own item ("Date: Tuesday, 3 November 2026").
class _Fact extends StatelessWidget {
  const _Fact({required this.icon, required this.label, required this.value, this.spoken});

  final IconData icon;
  final String label;
  final String value;
  final String? spoken;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;

    return Semantics(
      container: true,
      label: l10n.confirmRowLabelled(label, spoken ?? value),
      excludeSemantics: true,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: AppSizes.minTouch - AppSpacing.m),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, size: AppSizes.icon, color: scheme.mutedText),
              const SizedBox(width: AppSpacing.m),
              Expanded(child: SelectableText(value, style: Theme.of(context).textTheme.bodyLarge)),
            ],
          ),
        ),
      ),
    );
  }
}

class _Meeting extends ConsumerWidget {
  const _Meeting({required this.meeting, required this.locale});

  final MeetingInfo meeting;
  final String locale;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);

    final (IconData icon, String message) = switch (meeting.state) {
      MeetingState.opensLater => (Icons.lock_clock, l10n.bookingMeetingOpensLater(DateFormat.jm(locale).format(malaysiaWallClock(meeting.opensAt)))),
      MeetingState.available => (Icons.videocam_outlined, l10n.bookingMeetingAvailable),
      MeetingState.notAdded => (Icons.hourglass_empty, l10n.bookingMeetingNotAdded),
      MeetingState.ended => (Icons.event_available_outlined, l10n.bookingMeetingEnded),
      MeetingState.unknown => (Icons.info_outline, l10n.bookingStatusUnknown),
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Semantics(
          liveRegion: true,
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, size: AppSizes.icon), const SizedBox(width: AppSpacing.m), Expanded(child: Text(message))]),
        ),
        if (meeting.state == MeetingState.available && meeting.url != null) ...[
          const SizedBox(height: AppSpacing.m),
          FilledButton.icon(
            onPressed: () => _open(context, ref, Uri.tryParse(meeting.url!), l10n),
            icon: const Icon(Icons.video_call_outlined),
            label: Text(l10n.bookingJoinSession),
          ),
        ],
      ],
    );
  }
}

class _Place extends ConsumerWidget {
  const _Place({required this.place});

  final BookingPlace place;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final lines = [place.name, place.address, '${place.postcode} ${place.city}', place.state];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Semantics(
          container: true,
          label: l10n.confirmRowLabelled(l10n.bookingAddress, lines.join(', ')),
          excludeSemantics: true,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.place_outlined, size: AppSizes.icon),
              const SizedBox(width: AppSpacing.m),
              Expanded(child: SelectableText(lines.join('\n'))),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.m),
        OutlinedButton.icon(
          onPressed: () => _open(context, ref, _directions(place), l10n),
          icon: const Icon(Icons.directions_outlined),
          label: Text(l10n.bookingGetDirections),
        ),
      ],
    );
  }

  /// A map search by coordinates when we have them, otherwise by the address.
  Uri _directions(BookingPlace place) {
    final query = place.latitude != null && place.longitude != null
        ? '${place.latitude},${place.longitude}'
        : '${place.name}, ${place.address}, ${place.postcode} ${place.city}, ${place.state}';

    return Uri.https('www.google.com', '/maps/search/', {'api': '1', 'query': query});
  }
}

/// Opens an address outside the app. Only a web address is ever opened (a link from a counselor is text from a person, not to be trusted to be one).
Future<void> _open(BuildContext context, WidgetRef ref, Uri? uri, AppLocalizations l10n) async {
  final messenger = ScaffoldMessenger.of(context);
  final allowed = uri != null && (uri.scheme == 'https' || uri.scheme == 'http') && uri.host.isNotEmpty;
  final opened = allowed && await ref.read(urlOpenerProvider)(uri);

  if (!opened) messenger.showSnackBar(SnackBar(content: Text(l10n.bookingLinkFailed)));
}
