import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/format/slot_time.dart';
import '../../auth/presentation/start_email_verification.dart';
import '../../counselors/domain/counselor.dart';
import '../../counselors/presentation/counselor_labels.dart';
import 'booking_args.dart';
import 'confirm_booking_controller.dart';
import 'slot_taken_signal.dart';

/// "Confirm booking": the summary, the policies, the no-recording tick and the button. The time is NOT held while this is open: the server
/// claims it at the moment of confirming, so the screen checks first (a preview that claims nothing) and the server checks again.
///
/// It can open from the two ids alone ([snapshot] only shows something at once), so it also works after a sign-in redirect.
class ConfirmBookingScreen extends ConsumerStatefulWidget {
  const ConfirmBookingScreen({
    super.key,
    required this.counselorId,
    required this.slotId,
    this.snapshot,
  });

  final String counselorId;
  final String slotId;
  final ConfirmBookingArgs? snapshot;

  @override
  ConsumerState<ConfirmBookingScreen> createState() =>
      _ConfirmBookingScreenState();
}

class _ConfirmBookingScreenState extends ConsumerState<ConfirmBookingScreen>
    with WidgetsBindingObserver {
  final _statusFocus = FocusNode(debugLabel: 'confirm status');

  ConfirmTarget get _target => ConfirmTarget(
    counselorId: widget.counselorId,
    slotId: widget.slotId,
    snapshot: widget.snapshot,
  );

  ConfirmBookingController get _controller =>
      ref.read(confirmBookingControllerProvider(_target).notifier);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _statusFocus.dispose();
    super.dispose();
  }

  /// Back from the background: if the answer to the booking is still unknown, ask again with the same key.
  @override
  void didChangeAppLifecycleState(AppLifecycleState lifecycle) {
    if (lifecycle == AppLifecycleState.resumed) _controller.resolveUnknown();
  }

  /// Back to the picker. When this screen was opened on its own (after a sign-in) there is nothing below it, so go to the picker.
  void _backToPicker() {
    if (context.canPop()) {
      context.pop();
    } else {
      context.go(AppRoutes.pickSlotPath(widget.counselorId));
    }
  }

  void _onChange(ConfirmState? before, ConfirmState now) {
    final created = now.created;

    if (created != null && before?.created == null) {
      // Replace everything: Back must not return to a booking that has been made.
      context.go(AppRoutes.bookingConfirmed, extra: created);

      return;
    }

    if (now.slotTaken && before?.slotTaken != true) {
      ref
          .read(slotTakenProvider.notifier)
          .say(widget.counselorId, widget.slotId);
      _backToPicker();

      return;
    }

    // Focus goes to the message when a problem appears, and stays on the same element while a banner changes.
    if (now.problem != null && before?.problem != now.problem) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _statusFocus.requestFocus();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(confirmBookingControllerProvider(_target), _onChange);

    final l10n = AppLocalizations.of(context);
    final state = ref.watch(confirmBookingControllerProvider(_target));

    return PopScope(
      canPop: !state.submitting,
      child: Scaffold(
        appBar: AppBar(
          title: Text(l10n.confirmBookingTitle),
          leading: BackButton(
            onPressed: state.submitting ? null : _backToPicker,
          ),
        ),
        body: SafeArea(
          child: Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.l,
                    AppSpacing.s,
                    AppSpacing.l,
                    AppSpacing.xl,
                  ),
                  children: [
                    _Status(
                      state: state,
                      focus: _statusFocus,
                      onCheckAgain: _controller.recheck,
                      onRetry: _controller.retrySubmit,
                      onChooseAnother: _backToPicker,
                    ),
                    _SummaryCard(summary: state.summary),
                    const SizedBox(height: AppSpacing.s),
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: TextButton(
                        onPressed: state.submitting ? null : _backToPicker,
                        style: TextButton.styleFrom(
                          minimumSize: const Size(
                            AppSizes.minTouch,
                            AppSizes.minTouch,
                          ),
                        ),
                        child: Text(l10n.confirmChooseOtherTime),
                      ),
                    ),
                    if (state.details != null) ...[
                      const SizedBox(height: AppSpacing.m),
                      _Policies(state: state),
                      if (state.details!.upcomingSessions != null &&
                          state.details!.maxUpcomingSessions != null) ...[
                        const SizedBox(height: AppSpacing.m),
                        Text(
                          l10n.confirmUpcomingCount(
                            state.details!.upcomingSessions!,
                            state.details!.maxUpcomingSessions!,
                          ),
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(
                                color: Theme.of(context).colorScheme.mutedText,
                              ),
                        ),
                      ],
                    ],
                  ],
                ),
              ),
              _ConfirmBar(
                state: state,
                onConfirm: _controller.confirm,
                onAcknowledge: _controller.setAcknowledged,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// =================================================================================================================
//  Status: the banner and the problem, in one place so a screen reader's focus stays put while they change
// =================================================================================================================

class _Status extends ConsumerWidget {
  const _Status({
    required this.state,
    required this.focus,
    required this.onCheckAgain,
    required this.onRetry,
    required this.onChooseAnother,
  });

  final ConfirmState state;
  final FocusNode focus;
  final VoidCallback onCheckAgain;
  final VoidCallback onRetry;
  final VoidCallback onChooseAnother;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final problem = state.problem;

    final (IconData icon, String text)? message = switch (state.banner) {
      _ when problem != null => (
        Icons.error_outline,
        _problemText(l10n, state),
      ),
      ConfirmBanner.checking => (Icons.sync, l10n.confirmBannerChecking),
      ConfirmBanner.offline => (Icons.wifi_off, l10n.confirmBannerOffline),
      ConfirmBanner.detailsChanged => (
        Icons.info_outline,
        l10n.confirmBannerChanged,
      ),
      ConfirmBanner.none => null,
    };

    if (message == null) {
      return Focus(focusNode: focus, child: const SizedBox.shrink());
    }

    final action = _action(context, ref, l10n);

    return Focus(
      focusNode: focus,
      child: Semantics(
        liveRegion: true,
        container: true,
        child: Container(
          margin: const EdgeInsets.only(bottom: AppSpacing.m),
          padding: const EdgeInsets.all(AppSpacing.m),
          decoration: BoxDecoration(
            color: problem != null ? scheme.errorContainer : scheme.bannerFill,
            borderRadius: AppRadii.chipRadius,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // An icon beside the words: a warning is never colour alone.
                  Icon(message.$1, size: AppSizes.icon),
                  const SizedBox(width: AppSpacing.s),
                  Expanded(child: Text(message.$2)),
                ],
              ),
              if (action != null) ...[
                const SizedBox(height: AppSpacing.s),
                action,
              ],
            ],
          ),
        ),
      ),
    );
  }

  String _problemText(
    AppLocalizations l10n,
    ConfirmState state,
  ) => switch (state.problem!) {
    ConfirmProblem.timeConflict => l10n.confirmErrTimeConflict,
    ConfirmProblem.upcomingLimit => l10n.confirmErrLimit(
      state.maxUpcoming ?? state.details?.maxUpcomingSessions ?? 3,
    ),
    ConfirmProblem.emailNotVerified => l10n.confirmErrVerify,
    ConfirmProblem.counselorUnavailable => l10n.confirmErrCounselor,
    ConfirmProblem.rateLimited =>
      state.waitSeconds == null
          ? l10n.confirmErrWaitGeneric
          : l10n.confirmErrWait(state.waitSeconds!),
    // Failing to CHECK is a plain error; failing to BOOK may have booked, so it says the careful thing.
    ConfirmProblem.serverError =>
      state.valid ? l10n.confirmErrServer : l10n.somethingWentWrongBody,
    ConfirmProblem.unknownOutcome => l10n.confirmErrUnknown,
    ConfirmProblem.other =>
      (state.problemMessage ?? '').isEmpty
          ? l10n.somethingWentWrongBody
          : state.problemMessage!,
  };

  Widget? _action(BuildContext context, WidgetRef ref, AppLocalizations l10n) {
    Widget button(String label, VoidCallback onPressed) => Align(
      alignment: AlignmentDirectional.centerStart,
      child: OutlinedButton(
        onPressed: onPressed,
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(AppSizes.minTouch, AppSizes.minTouch),
        ),
        child: Text(label),
      ),
    );

    return switch (state.problem) {
      ConfirmProblem.emailNotVerified => button(
        l10n.pickSlotVerifyButton,
        () => startEmailVerification(context, ref),
      ),
      ConfirmProblem.counselorUnavailable => button(
        l10n.confirmFindAnother,
        () => context.go(AppRoutes.counselors),
      ),
      ConfirmProblem.unknownOutcome => button(
        l10n.confirmGoHome,
        () => context.go(AppRoutes.home),
      ),
      ConfirmProblem.serverError when state.details != null && state.valid =>
        button(l10n.tryAgain, onRetry),
      ConfirmProblem.serverError ||
      ConfirmProblem.other ||
      ConfirmProblem.rateLimited => button(
        l10n.confirmCheckAgain,
        onCheckAgain,
      ),
      null when state.banner == ConfirmBanner.offline => button(
        l10n.confirmCheckAgain,
        onCheckAgain,
      ),
      _ => null,
    };
  }
}

// =================================================================================================================
//  The summary card
// =================================================================================================================

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});

  final ConfirmSummary? summary;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final s = summary;

    final name = s?.counselorName ?? '';
    final initials = name.trim().isEmpty
        ? ''
        : name
              .trim()
              .split(RegExp(r'\s+'))
              .take(2)
              .map((p) => p.characters.first.toUpperCase())
              .join();

    String? date, time, spoken, duration, kind;

    if (s != null) {
      final start = malaysiaWallClock(s.startsAt);
      final end = malaysiaWallClock(s.endsAt);
      final from = DateFormat.jm(locale).format(start);
      final to = DateFormat.jm(locale).format(end);

      date = DateFormat('EEEE, d MMMM y', locale).format(start);
      time = l10n.confirmTimeValue(from, to);
      spoken = l10n.confirmTimeSpoken(from, to);
      duration = l10n.confirmDuration(s.durationMinutes);
      kind = s.mode == SessionMode.physical && s.location != null
          ? l10n.inPersonIn('${s.location!.name}, ${s.location!.city}')
          : s.mode.label(l10n);
    }

    return Container(
      padding: const EdgeInsets.all(AppSpacing.l),
      decoration: BoxDecoration(
        color: scheme.cardFill,
        borderRadius: AppRadii.cardRadius,
        border: Border.all(color: scheme.cardBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              ExcludeSemantics(
                child: CircleAvatar(
                  radius: AppSizes.avatar / 2,
                  backgroundColor: scheme.primary.withValues(alpha: .12),
                  child: Text(
                    initials,
                    style: text.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                      color: scheme.primary,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.m),
              Expanded(
                child: s == null
                    ? const _Bar(width: 160)
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Semantics(
                            header: true,
                            child: Text(
                              name,
                              style: text.titleMedium?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          if (s.verified)
                            Row(
                              children: [
                                Icon(
                                  Icons.verified,
                                  size: AppSizes.icon - 4,
                                  color: scheme.primary,
                                ),
                                const SizedBox(width: AppSpacing.xs),
                                Flexible(
                                  child: Text(
                                    l10n.confirmVerifiedBadge,
                                    style: text.bodySmall?.copyWith(
                                      color: scheme.primary,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                        ],
                      ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.l),
          _Row(
            icon: Icons.calendar_today_outlined,
            label: l10n.confirmRowDate,
            value: date,
          ),
          _Row(
            icon: Icons.schedule,
            label: l10n.confirmRowTime,
            value: time,
            spoken: spoken,
          ),
          _Row(
            icon: Icons.timelapse,
            label: l10n.confirmRowDuration,
            value: duration,
          ),
          _Row(
            icon: s?.mode == SessionMode.physical
                ? Icons.place_outlined
                : Icons.videocam_outlined,
            label: l10n.confirmRowType,
            value: kind,
          ),
        ],
      ),
    );
  }
}

class _Bar extends StatelessWidget {
  const _Bar({required this.width});

  final double width;

  @override
  Widget build(BuildContext context) => ExcludeSemantics(
    child: Container(
      width: width,
      height: 16,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.skeleton,
        borderRadius: AppRadii.chipRadius,
      ),
    ),
  );
}

/// One labelled fact, announced as its own item ("Date: Tuesday, 16 September 2026").
class _Row extends StatelessWidget {
  const _Row({
    required this.icon,
    required this.label,
    required this.value,
    this.spoken,
  });

  final IconData icon;
  final String label;
  final String? value;
  final String? spoken;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;

    return Semantics(
      container: true,
      label: value == null
          ? label
          : l10n.confirmRowLabelled(label, spoken ?? value!),
      excludeSemantics: true,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
        child: ConstrainedBox(
          constraints: const BoxConstraints(
            minHeight: AppSizes.minTouch - AppSpacing.m,
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, size: AppSizes.icon, color: scheme.mutedText),
              const SizedBox(width: AppSpacing.m),
              Expanded(
                child: value == null
                    ? const Align(
                        alignment: AlignmentDirectional.centerStart,
                        child: _Bar(width: 200),
                      )
                    : Text(value!, style: text.bodyLarge),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// =================================================================================================================
//  "Before you confirm": the rules, all from the server
// =================================================================================================================

class _Policies extends StatelessWidget {
  const _Policies({required this.state});

  final ConfirmState state;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final details = state.details!;
    final inside = details.cancellationDeadlineAt == null;
    final online = details.mode != SessionMode.physical;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Semantics(
          header: true,
          child: Text(
            l10n.confirmBeforeTitle,
            style: Theme.of(
              context,
            ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
          ),
        ),
        const SizedBox(height: AppSpacing.s),
        _PolicyRow(
          icon: inside
              ? Icons.warning_amber_rounded
              : Icons.event_busy_outlined,
          // The warning is words AND an icon (and a spoken prefix), never colour alone.
          warning: inside,
          text: inside
              ? l10n.confirmPolicyNoCancel(details.cancellationHours)
              : l10n.confirmPolicyCancel(details.cancellationHours),
        ),
        _PolicyRow(
          icon: Icons.update,
          text: details.maxReschedules <= 0
              ? l10n.confirmPolicyNoReschedule
              : l10n.confirmPolicyReschedule(details.maxReschedules),
        ),
        _PolicyRow(
          icon: online ? Icons.link : Icons.place_outlined,
          text: online
              ? l10n.confirmPolicyLinkOnline(details.meetingLinkVisibleMinutes)
              : l10n.confirmPolicyAddressInPerson,
        ),
      ],
    );
  }
}

class _PolicyRow extends StatelessWidget {
  const _PolicyRow({
    required this.icon,
    required this.text,
    this.warning = false,
  });

  final IconData icon;
  final String text;
  final bool warning;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;

    return Semantics(
      container: true,
      label: warning ? '${l10n.confirmWarning}: $text' : text,
      excludeSemantics: true,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              icon,
              size: AppSizes.icon,
              color: warning ? scheme.error : scheme.mutedText,
            ),
            const SizedBox(width: AppSpacing.m),
            Expanded(
              child: Text(
                text,
                style: warning
                    ? const TextStyle(fontWeight: FontWeight.w700)
                    : null,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// =================================================================================================================
//  The tick and the button
// =================================================================================================================

class _AcknowledgeRow extends StatelessWidget {
  const _AcknowledgeRow({
    required this.value,
    required this.enabled,
    required this.onChanged,
  });

  final bool value;
  final bool enabled;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    // The whole row is the tap target, and a screen reader hears one labelled checkbox with its state.
    return Semantics(
      container: true,
      checked: value,
      enabled: enabled,
      label: l10n.confirmAcknowledge,
      excludeSemantics: true,
      onTap: enabled ? () => onChanged(!value) : null,
      child: InkWell(
        borderRadius: AppRadii.chipRadius,
        onTap: enabled ? () => onChanged(!value) : null,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: AppSizes.minTouch),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
            child: Row(
              children: [
                ExcludeSemantics(
                  child: Checkbox(
                    value: value,
                    onChanged: enabled ? (v) => onChanged(v ?? false) : null,
                  ),
                ),
                const SizedBox(width: AppSpacing.xs),
                Expanded(child: Text(l10n.confirmAcknowledge)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ConfirmBar extends StatelessWidget {
  const _ConfirmBar({
    required this.state,
    required this.onConfirm,
    required this.onAcknowledge,
  });

  final ConfirmState state;
  final VoidCallback onConfirm;
  final ValueChanged<bool> onAcknowledge;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    final why = switch (state.disabledReason) {
      ConfirmDisabledReason.checking => l10n.confirmWhyChecking,
      ConfirmDisabledReason.offline => l10n.confirmWhyOffline,
      ConfirmDisabledReason.unavailable => l10n.confirmWhyUnavailable,
      ConfirmDisabledReason.tick => l10n.confirmWhyTick,
      null => null,
    };

    return Material(
      elevation: 4,
      color: Theme.of(context).colorScheme.surface,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.l),
        // The tick lives WITH the button, so the reason the button is off is always on screen, whatever the phone's height.
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _AcknowledgeRow(
              value: state.acknowledged,
              enabled: !state.submitting,
              onChanged: onAcknowledge,
            ),
            const SizedBox(height: AppSpacing.s),
            // Merged into the button's own item, so a screen reader hears the button AND why it is off in one go.
            MergeSemantics(
              child: Semantics(
                // The button says WHY it is off, so it is never a silent dead end.
                hint: why,
                liveRegion: state.submitting,
                child: SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: state.canConfirm ? onConfirm : null,
                    child: state.submitting
                        ? Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.5,
                                ),
                              ),
                              const SizedBox(width: AppSpacing.s),
                              Text(l10n.confirmButtonBusy),
                            ],
                          )
                        : Text(l10n.confirmButton),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
