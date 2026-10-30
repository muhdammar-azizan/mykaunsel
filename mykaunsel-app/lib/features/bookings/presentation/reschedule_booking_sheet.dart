import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../booking/domain/availability.dart';
import 'booking_labels.dart';
import 'reschedule_args.dart';
import 'reschedule_booking_controller.dart';

/// What the sheet tells the picker underneath it.
enum RescheduleOutcome {
  /// The booking was moved.
  moved,

  /// The picked time is gone: mark it and let the person pick another.
  taken,

  /// The picked time was edited meanwhile: load the times again.
  changed,

  /// Pick another (a clash with another session): nothing is wrong with the time itself.
  pickAnother,

  /// Nothing more to do here (it can't be moved any more, or it is gone): back to the booking.
  leave,

  /// The person closed it.
  dismissed,
}

Future<RescheduleOutcome> showRescheduleBookingSheet(BuildContext context, {required RescheduleTarget target, required AvailabilitySlot slot}) async {
  final outcome = await showModalBottomSheet<RescheduleOutcome>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (_) => RescheduleBookingSheet(target: target, slot: slot),
  );

  return outcome ?? RescheduleOutcome.dismissed;
}

/// "Move to this time?": from the time the booking has now to the time just picked. The server checks everything; a time that is gone, a clash
/// or a closed window are said here in plain words with one next step. While it works nothing can close it, and asking twice for the same
/// time is harmless, so any answer that does not arrive is simply asked again.
class RescheduleBookingSheet extends ConsumerWidget {
  const RescheduleBookingSheet({super.key, required this.target, required this.slot});

  final RescheduleTarget target;
  final AvailabilitySlot slot;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final provider = rescheduleBookingControllerProvider(target.bookingId);
    final state = ref.watch(provider);
    final controller = ref.read(provider.notifier);
    final now = ref.watch(clockProvider)();

    ref.listen(provider, (_, next) {
      if (next.done) Navigator.of(context).pop(RescheduleOutcome.moved);
    });

    final problem = state.problem;
    final ended = problem == RescheduleProblem.closed ||
        problem == RescheduleProblem.limitReached ||
        problem == RescheduleProblem.notReschedulable ||
        problem == RescheduleProblem.gone;
    final from = bookingWhen(l10n, startsAt: target.startsAt, endsAt: target.endsAt, now: now, locale: locale);
    final to = bookingWhen(l10n, startsAt: slot.startsAt, endsAt: slot.startsAt.add(Duration(minutes: slot.durationMinutes)), now: now, locale: locale);

    return PopScope(
      canPop: !state.busy,
      child: Padding(
        padding: EdgeInsets.fromLTRB(AppSpacing.xl, 0, AppSpacing.xl, AppSpacing.xl + MediaQuery.viewInsetsOf(context).bottom),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Semantics(
                header: true,
                liveRegion: true,
                child: Text(ended ? l10n.rescheduleEndedTitle : l10n.rescheduleSheetTitle, style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
              ),
              const SizedBox(height: AppSpacing.m),
              if (!ended) ...[
                Text(l10n.rescheduleSheetBody(target.counselor.name, from, to)),
                const SizedBox(height: AppSpacing.s),
                Text(
                  bookingType(l10n, slot.mode, slot.location),
                  style: text.bodySmall?.copyWith(color: scheme.mutedText),
                ),
              ],
              if (problem != null) ...[
                const SizedBox(height: AppSpacing.m),
                _Problem(problem: problem, waitSeconds: state.waitSeconds, hours: state.hours),
              ],
              const SizedBox(height: AppSpacing.xl),
              ..._buttons(context, l10n, scheme, state, controller, problem, ended),
            ],
          ),
        ),
      ),
    );
  }

  List<Widget> _buttons(BuildContext context, AppLocalizations l10n, ColorScheme scheme, RescheduleState state, RescheduleBookingController controller,
      RescheduleProblem? problem, bool ended) {
    void close(RescheduleOutcome outcome) => Navigator.of(context).pop(outcome);

    // Nothing more can be done with this booking: one button back to it.
    if (ended) {
      return [SizedBox(width: double.infinity, child: FilledButton(onPressed: () => close(RescheduleOutcome.leave), child: Text(l10n.bookingCancelOk)))];
    }

    // The picked time can't be used: pick another.
    if (problem == RescheduleProblem.slotTaken || problem == RescheduleProblem.slotChanged || problem == RescheduleProblem.timeConflict) {
      final outcome = switch (problem) {
        RescheduleProblem.slotTaken => RescheduleOutcome.taken,
        RescheduleProblem.slotChanged => RescheduleOutcome.changed,
        _ => RescheduleOutcome.pickAnother,
      };

      return [SizedBox(width: double.infinity, child: FilledButton(onPressed: () => close(outcome), child: Text(l10n.rescheduleChooseAnother)))];
    }

    final retrying = problem == RescheduleProblem.network || problem == RescheduleProblem.server;

    return [
      SizedBox(
        width: double.infinity,
        child: FilledButton(
          onPressed: state.busy ? null : () => controller.reschedule(slot),
          child: state.busy
              ? Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.5, color: scheme.onPrimary)),
                    const SizedBox(width: AppSpacing.s),
                    Text(l10n.rescheduleMoving),
                  ],
                )
              : Text(retrying ? l10n.tryAgain : l10n.rescheduleMove),
        ),
      ),
      const SizedBox(height: AppSpacing.s),
      SizedBox(
        width: double.infinity,
        child: OutlinedButton(onPressed: state.busy ? null : () => close(RescheduleOutcome.dismissed), child: Text(l10n.rescheduleKeepCurrent)),
      ),
    ];
  }
}

class _Problem extends StatelessWidget {
  const _Problem({required this.problem, required this.waitSeconds, required this.hours});

  final RescheduleProblem problem;
  final int? waitSeconds;
  final int? hours;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;

    final message = switch (problem) {
      RescheduleProblem.slotTaken => l10n.rescheduleSlotTaken,
      RescheduleProblem.slotChanged => l10n.rescheduleSlotChanged,
      RescheduleProblem.timeConflict => l10n.confirmErrTimeConflict,
      RescheduleProblem.closed => hours == null ? l10n.rescheduleNotPossible : l10n.rescheduleClosed(hours!),
      RescheduleProblem.limitReached => l10n.bookingRescheduleLimit,
      RescheduleProblem.notReschedulable => l10n.rescheduleNotPossible,
      RescheduleProblem.gone => l10n.rescheduleGone,
      RescheduleProblem.rateLimited => waitSeconds == null ? l10n.confirmErrWaitGeneric : l10n.confirmErrWait(waitSeconds!),
      RescheduleProblem.network => l10n.noConnectionBody,
      RescheduleProblem.server => l10n.rescheduleServer,
      RescheduleProblem.other => l10n.somethingWentWrongBody,
    };

    return Semantics(
      liveRegion: true,
      container: true,
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.m),
        decoration: BoxDecoration(color: scheme.errorContainer, borderRadius: AppRadii.chipRadius),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(Icons.error_outline, size: AppSizes.icon),
            const SizedBox(width: AppSpacing.s),
            Expanded(child: Text(message)),
          ],
        ),
      ),
    );
  }
}
