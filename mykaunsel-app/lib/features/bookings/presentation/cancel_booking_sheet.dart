import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/format/slot_time.dart';
import '../domain/my_booking.dart';
import 'booking_labels.dart';
import 'cancel_booking_controller.dart';

/// Opens the cancel sheet. Resolves to true once the booking has been cancelled.
Future<bool> showCancelBookingSheet(BuildContext context, MyBookingDetail booking) async {
  final cancelled = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (_) => CancelBookingSheet(booking: booking),
  );

  return cancelled ?? false;
}

/// Two states, decided by the server (the booking's `can_cancel`, and the answer itself):
/// - allowed: what will happen, until when it could be done, "Cancel booking" and "Keep booking";
/// - too close to the start: the same plain explanation as on the confirm screen, and one button.
/// While it works nothing can close it, and cancelling twice is harmless, so any answer that does not arrive is simply asked again.
class CancelBookingSheet extends ConsumerWidget {
  const CancelBookingSheet({super.key, required this.booking});

  final MyBookingDetail booking;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final state = ref.watch(cancelBookingControllerProvider(booking.id));
    final controller = ref.read(cancelBookingControllerProvider(booking.id).notifier);

    ref.listen(cancelBookingControllerProvider(booking.id), (_, now) {
      if (now.done) Navigator.of(context).pop(true);
    });

    final closed = !booking.canCancel || state.problem == CancelProblem.closed || state.problem == CancelProblem.notCancellable;
    final when = bookingWhen(l10n, startsAt: booking.startsAt, endsAt: booking.endsAt, now: ref.watch(clockProvider)(), locale: locale);
    final hours = state.hours ?? booking.cancellationHours;

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
                child: Text(closed ? l10n.bookingCancelClosedTitle : l10n.bookingCancelSheetTitle, style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
              ),
              const SizedBox(height: AppSpacing.m),
              if (closed) ...[
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.info_outline, size: AppSizes.icon),
                    const SizedBox(width: AppSpacing.m),
                    Expanded(child: Text(l10n.confirmPolicyNoCancel(hours))),
                  ],
                ),
              ] else ...[
                Text(l10n.bookingCancelSheetBody(booking.counselor.displayName, when)),
                if (booking.cancellationDeadlineAt != null) ...[
                  const SizedBox(height: AppSpacing.s),
                  Text(l10n.bookingCanCancelUntil(_deadline(booking.cancellationDeadlineAt!, locale)), style: text.bodySmall?.copyWith(color: scheme.mutedText)),
                ],
              ],
              if (state.problem != null && !closed) ...[
                const SizedBox(height: AppSpacing.m),
                _Problem(problem: state.problem!, waitSeconds: state.waitSeconds),
              ],
              const SizedBox(height: AppSpacing.xl),
              if (closed)
                SizedBox(width: double.infinity, child: FilledButton(onPressed: () => Navigator.of(context).pop(false), child: Text(l10n.bookingCancelOk)))
              else ...[
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: state.busy || state.problem == CancelProblem.notFound ? null : controller.cancel,
                    style: FilledButton.styleFrom(backgroundColor: scheme.error, foregroundColor: scheme.onError),
                    child: state.busy
                        ? Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.5, color: scheme.onError)),
                              const SizedBox(width: AppSpacing.s),
                              Text(l10n.bookingCancelling),
                            ],
                          )
                        : Text(_retrying(state) ? l10n.tryAgain : l10n.bookingCancelButton),
                  ),
                ),
                const SizedBox(height: AppSpacing.s),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(onPressed: state.busy ? null : () => Navigator.of(context).pop(false), child: Text(l10n.bookingKeep)),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  /// After an answer that did not arrive, the button says "Try again" (it is safe: cancelling twice changes nothing more).
  bool _retrying(CancelState state) => state.problem == CancelProblem.network || state.problem == CancelProblem.server;

  String _deadline(DateTime at, String locale) {
    final wall = malaysiaWallClock(at);

    return '${DateFormat('EEE d MMM', locale).format(wall)}, ${DateFormat.jm(locale).format(wall)}';
  }
}

class _Problem extends StatelessWidget {
  const _Problem({required this.problem, required this.waitSeconds});

  final CancelProblem problem;
  final int? waitSeconds;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;

    final message = switch (problem) {
      CancelProblem.network => l10n.noConnectionBody,
      CancelProblem.server => l10n.bookingCancelServer,
      CancelProblem.rateLimited => waitSeconds == null ? l10n.confirmErrWaitGeneric : l10n.confirmErrWait(waitSeconds!),
      CancelProblem.notFound => l10n.bookingNotFound,
      CancelProblem.closed || CancelProblem.notCancellable => l10n.bookingCancelNotCancellable,
      CancelProblem.other => l10n.somethingWentWrongBody,
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
