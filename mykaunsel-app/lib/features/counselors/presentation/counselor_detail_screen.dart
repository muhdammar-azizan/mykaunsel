import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/error/failure_message.dart';
import '../../../core/router/app_router.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/format/slot_time.dart';
import '../../booking/presentation/booking_args.dart';
import '../domain/counselor.dart';
import 'counselor_labels.dart';
import 'counselor_list_controller.dart';

/// One counselor's page. A counselor who is not there, or whom this person may not see, looks the same: "not available".
class CounselorDetailScreen extends ConsumerWidget {
  const CounselorDetailScreen({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final detail = ref.watch(counselorDetailProvider(id));

    return Scaffold(
      appBar: AppBar(),
      body: SafeArea(
        child: detail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) {
            final failure = error is AppFailure ? error : const ServerFailure();

            return Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.person_off_outlined, size: 48, color: Theme.of(context).colorScheme.onSurface.withValues(alpha: .4)),
                    const SizedBox(height: 16),
                    if (failure is NotFoundFailure)
                      Text(l10n.profileNotFoundTitle, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700))
                    else
                      Text(l10n.somethingWentWrongTitle, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),
                    Text(failureMessage(l10n, failure), textAlign: TextAlign.center),
                    if (failure is! NotFoundFailure) ...[
                      const SizedBox(height: 20),
                      FilledButton(onPressed: () => ref.invalidate(counselorDetailProvider(id)), child: Text(l10n.tryAgain)),
                    ],
                  ],
                ),
              ),
            );
          },
          data: (counselor) => _Profile(counselor: counselor),
        ),
      ),
    );
  }
}

class _Profile extends ConsumerWidget {
  const _Profile({required this.counselor});

  final Counselor counselor;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final locale = Localizations.localeOf(context).toLanguageTag();

    final contexts = counselor.contexts.map((c) => c.label(l10n)).whereType<String>().toList();
    final modes = counselor.sessionModes.map((m) => m.label(l10n)).whereType<String>().toList();

    // Free times grouped by day (Malaysian time).
    final days = <String, List<CounselorSlot>>{};
    for (final slot in counselor.upcomingSlots) {
      days.putIfAbsent(formatSlotDay(slot.startsAt, locale: locale), () => []).add(slot);
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
      children: [
        Row(
          children: [
            ExcludeSemantics(
              child: CircleAvatar(
                radius: 32,
                backgroundColor: scheme.primary.withValues(alpha: .12),
                child: Text(counselor.initials, style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800, color: scheme.primary)),
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Semantics(header: true, child: Text(counselor.name, style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800))),
                  if (counselor.verified)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Row(
                        children: [
                          Icon(Icons.verified, size: 18, color: scheme.primary),
                          const SizedBox(width: 4),
                          Flexible(child: Text(l10n.verifiedLabel, style: text.bodyMedium?.copyWith(color: scheme.primary, fontWeight: FontWeight.w600))),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
        if (contexts.isNotEmpty) _Section(title: l10n.profileWorksWith, child: Text(contexts.join(' · '))),
        if (counselor.specializations.isNotEmpty)
          _Section(
            title: l10n.profileFocusAreas,
            child: Wrap(spacing: 8, runSpacing: 8, children: [for (final s in counselor.specializations) Chip(label: Text(s))]),
          ),
        if (counselor.languages.isNotEmpty) _Section(title: l10n.profileLanguages, child: Text(counselor.languages.join(', '))),
        if (modes.isNotEmpty) _Section(title: l10n.profileSessionTypes, child: Text(modes.join(' / '))),
        if (counselor.locations.isNotEmpty) _Section(title: l10n.profileWhere, child: Text(counselor.locations.map((l) => l.place).join('\n'))),
        if (counselor.acceptsRequests == true) Padding(padding: const EdgeInsets.only(top: 20), child: Text(l10n.profileAcceptsRequests)),
        _Section(
          title: l10n.profileAvailability,
          child: counselor.upcomingSlots.isEmpty
              ? Text(l10n.profileNoTimes)
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    for (final entry in days.entries) ...[
                      Padding(
                        padding: const EdgeInsets.only(top: 8, bottom: 4),
                        child: Text(entry.key, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                      ),
                      for (final slot in entry.value) _SlotRow(slot: slot),
                    ],
                    const SizedBox(height: 8),
                    Text(l10n.malaysiaTimeNote, style: text.bodySmall),
                  ],
                ),
        ),
        const SizedBox(height: 24),
        FilledButton.icon(
          onPressed: () => context.push(AppRoutes.pickSlotPath(counselor.id), extra: BookingCounselorSummary.fromCounselor(counselor)),
          icon: const Icon(Icons.event_available_outlined),
          label: Text(l10n.seeAvailableTimes),
        ),
        // A counselor who takes requests can be asked for a time inside their weekly hours (the server checks everything).
        if (counselor.acceptsRequests == true) ...[
          const SizedBox(height: 12),
          Text(l10n.counselorAskTimeHint(counselor.name), style: text.bodySmall),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: () => context.push(AppRoutes.sendRequestPath(counselor.id), extra: BookingCounselorSummary.fromCounselor(counselor)),
            icon: const Icon(Icons.edit_calendar_outlined),
            label: Text(l10n.counselorAskTime),
          ),
        ],
      ],
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(header: true, child: Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800))),
          const SizedBox(height: 8),
          child,
        ],
      ),
    );
  }
}

class _SlotRow extends StatelessWidget {
  const _SlotRow({required this.slot});

  final CounselorSlot slot;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final text = Theme.of(context).textTheme;

    final mode = slot.sessionMode.label(l10n);
    final place = slot.city != null && slot.state != null ? l10n.inPersonIn('${slot.city}, ${slot.state}') : mode;
    final who = slot.context?.label(l10n);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 88, child: Text(formatSlotTime(slot.startsAt, locale: locale), style: text.bodyMedium?.copyWith(fontWeight: FontWeight.w700))),
          Expanded(
            child: Text(
              [l10n.minutesShort(slot.durationMinutes), ?place, ?who].join('  ·  '),
              style: text.bodyMedium,
            ),
          ),
        ],
      ),
    );
  }
}
