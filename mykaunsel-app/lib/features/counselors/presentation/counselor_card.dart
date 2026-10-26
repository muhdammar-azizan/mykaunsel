import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/format/slot_time.dart';
import '../domain/counselor.dart';
import 'counselor_labels.dart';

/// One counselor in the search results.
class CounselorCard extends ConsumerWidget {
  const CounselorCard({super.key, required this.counselor, required this.onTap});

  final Counselor counselor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;

    final contexts = counselor.contexts.map((c) => c.label(l10n)).whereType<String>().toList();
    final modes = counselor.sessionModes.map((m) => m.label(l10n)).whereType<String>().toList();
    final more = counselor.specializationsCount - counselor.specializations.length;
    final next = counselor.nextAvailable;

    return Card(
      elevation: 0,
      margin: const EdgeInsets.only(bottom: 12),
      color: Colors.white,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20), side: BorderSide(color: scheme.onSurface.withValues(alpha: .1))),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ExcludeSemantics(
                    child: CircleAvatar(
                      radius: 24,
                      backgroundColor: scheme.primary.withValues(alpha: .12),
                      child: Text(counselor.initials, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800, color: scheme.primary)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(counselor.name, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                        if (counselor.verified)
                          Padding(
                            padding: const EdgeInsets.only(top: 2),
                            child: Row(
                              children: [
                                Icon(Icons.verified, size: 16, color: scheme.primary),
                                const SizedBox(width: 4),
                                Flexible(child: Text(l10n.verifiedLabel, style: text.bodySmall?.copyWith(color: scheme.primary, fontWeight: FontWeight.w600))),
                              ],
                            ),
                          ),
                        if (contexts.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 2), child: Text(contexts.join(' · '), style: text.bodySmall)),
                      ],
                    ),
                  ),
                ],
              ),
              if (counselor.specializations.isNotEmpty) ...[
                const SizedBox(height: 12),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    for (final s in counselor.specializations) _Chip(s),
                    if (more > 0) _Chip(l10n.moreFocusAreas(more), muted: true),
                  ],
                ),
              ],
              if (counselor.languages.isNotEmpty || modes.isNotEmpty || counselor.locations.isNotEmpty) ...[
                const SizedBox(height: 10),
                Text(
                  [
                    if (counselor.languages.isNotEmpty) counselor.languages.join(', '),
                    if (modes.isNotEmpty) modes.join(' / '),
                    if (counselor.locations.isNotEmpty) counselor.locations.map((l) => l.place).join('; '),
                  ].join('  ·  '),
                  style: text.bodySmall,
                ),
              ],
              const SizedBox(height: 12),
              Row(
                children: [
                  Icon(Icons.schedule, size: 18, color: next == null ? scheme.onSurface.withValues(alpha: .45) : scheme.primary),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      next == null
                          ? l10n.noUpcomingTimes
                          : l10n.nextAvailable(formatSlotStart(
                              next.startsAt,
                              now: ref.watch(clockProvider)(),
                              locale: Localizations.localeOf(context).toLanguageTag(),
                              today: l10n.today,
                              tomorrow: l10n.tomorrow,
                            )),
                      style: text.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip(this.label, {this.muted = false});

  final String label;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: muted ? scheme.onSurface.withValues(alpha: .06) : scheme.primary.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(label, style: Theme.of(context).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600)),
    );
  }
}
