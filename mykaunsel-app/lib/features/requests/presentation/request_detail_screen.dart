import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/error/failure_message.dart';
import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/format/slot_time.dart';
import '../../booking/presentation/booking_args.dart';
import '../../bookings/presentation/booking_labels.dart';
import '../../counselors/domain/counselor.dart';
import '../domain/time_request.dart';
import 'my_requests_controller.dart';
import 'request_labels.dart';
import 'withdraw_request_sheet.dart';

/// One request: what was asked, its state in words and an icon, and what can be done now. A waiting request can be withdrawn; an approved one
/// opens its booking; a declined or expired one offers to ask for another time. The server decides every state; this only shows it.
class RequestDetailScreen extends ConsumerWidget {
  const RequestDetailScreen({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final request = ref.watch(myRequestProvider(id));

    return Scaffold(
      appBar: AppBar(title: Text(l10n.requestDetailTitle)),
      body: SafeArea(
        child: request.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) {
            final failure = error is AppFailure ? error : const ServerFailure();

            return Center(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.xxl),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Semantics(liveRegion: true, child: Text(failure is NotFoundFailure ? l10n.requestNotFound : failureMessage(l10n, failure), textAlign: TextAlign.center)),
                    if (failure is! NotFoundFailure) ...[
                      const SizedBox(height: AppSpacing.l),
                      FilledButton(onPressed: () => ref.invalidate(myRequestProvider(id)), child: Text(l10n.tryAgain)),
                    ],
                  ],
                ),
              ),
            );
          },
          data: (r) => _Body(request: r),
        ),
      ),
    );
  }
}

class _Body extends ConsumerWidget {
  const _Body({required this.request});

  final TimeRequest request;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final start = malaysiaWallClock(request.startsAt);
    final end = malaysiaWallClock(request.endsAt);
    final name = request.counselor.displayName;
    final note = switch (request.status) {
      RequestState.approved => l10n.requestApprovedNote,
      RequestState.rejected => l10n.requestDeclinedNote,
      RequestState.expired => l10n.requestExpiredNote,
      RequestState.withdrawn => l10n.requestWithdrawnNote,
      _ => null,
    };
    final until = malaysiaWallClock(request.expiresAt);

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.l),
      children: [
        Row(
          children: [
            Icon(request.status.icon, size: AppSizes.icon),
            const SizedBox(width: AppSpacing.s),
            Expanded(child: Semantics(header: true, child: Text(request.status.label(l10n), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800)))),
          ],
        ),
        if (note != null) ...[
          const SizedBox(height: AppSpacing.m),
          Semantics(
            liveRegion: true,
            child: Container(
              padding: const EdgeInsets.all(AppSpacing.m),
              decoration: BoxDecoration(color: scheme.bannerFill, borderRadius: AppRadii.chipRadius),
              child: Row(children: [const Icon(Icons.info_outline, size: AppSizes.icon), const SizedBox(width: AppSpacing.s), Expanded(child: Text(note))]),
            ),
          ),
        ],
        if (request.status == RequestState.pending) ...[
          const SizedBox(height: AppSpacing.m),
          Text(l10n.requestWaitUntil(name, '${DateFormat('EEE d MMM', locale).format(until)}, ${DateFormat.jm(locale).format(until)}')),
        ],
        const SizedBox(height: AppSpacing.l),
        Container(
          padding: const EdgeInsets.all(AppSpacing.l),
          decoration: BoxDecoration(color: scheme.cardFill, borderRadius: AppRadii.cardRadius, border: Border.all(color: scheme.cardBorder)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Semantics(header: true, child: Text(name, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
              const SizedBox(height: AppSpacing.m),
              _Fact(icon: Icons.calendar_today_outlined, label: l10n.confirmRowDate, value: bookingDate(request.startsAt, locale)),
              _Fact(
                icon: Icons.schedule,
                label: l10n.confirmRowTime,
                value: l10n.confirmTimeValue(DateFormat.jm(locale).format(start), DateFormat.jm(locale).format(end)),
                spoken: l10n.confirmTimeSpoken(DateFormat.jm(locale).format(start), DateFormat.jm(locale).format(end)),
              ),
              _Fact(icon: Icons.timelapse, label: l10n.confirmRowDuration, value: l10n.confirmDuration(request.durationMinutes)),
              _Fact(
                icon: request.mode == SessionMode.physical ? Icons.place_outlined : Icons.videocam_outlined,
                label: l10n.confirmRowType,
                value: bookingType(l10n, request.mode, request.location),
              ),
              if ((request.message ?? '').isNotEmpty) _Fact(icon: Icons.notes, label: l10n.requestYourNote, value: request.message!),
              if ((request.responseReason ?? '').isNotEmpty) _Fact(icon: Icons.chat_bubble_outline, label: l10n.requestTheirMessage, value: request.responseReason!),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.l),
        if (request.status == RequestState.approved && request.bookingId != null)
          FilledButton.icon(
            onPressed: () => context.go(AppRoutes.bookingPath(request.bookingId!)),
            icon: const Icon(Icons.event_available_outlined),
            label: Text(l10n.requestViewBooking),
          ),
        if (request.status == RequestState.pending)
          OutlinedButton.icon(
            onPressed: () async {
              final messenger = ScaffoldMessenger.of(context);
              final done = await showWithdrawRequestSheet(context, request);

              if (done) messenger.showSnackBar(SnackBar(content: Text(l10n.requestWithdrawnSnack)));
            },
            icon: const Icon(Icons.undo),
            label: Text(l10n.requestWithdraw),
          ),
        if ((request.status == RequestState.rejected || request.status == RequestState.expired || request.status == RequestState.withdrawn) && request.counselor.id != null)
          FilledButton.icon(
            onPressed: () => context.push(AppRoutes.sendRequestPath(request.counselor.id!), extra: BookingCounselorSummary(id: request.counselor.id!, name: name)),
            icon: const Icon(Icons.event_repeat_outlined),
            label: Text(l10n.requestChooseAnother),
          ),
        const SizedBox(height: AppSpacing.s),
        Text(l10n.malaysiaTimeNote, style: text.bodySmall),
      ],
    );
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
