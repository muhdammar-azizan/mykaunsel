import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/error/failure_message.dart';
import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/format/slot_time.dart';
import '../../auth/presentation/start_email_verification.dart';
import '../../booking/presentation/booking_args.dart';
import '../../counselors/presentation/counselor_labels.dart';
import '../domain/request_times.dart';
import 'send_request_controller.dart';

/// "Ask for a time": for a counselor who takes requests, pick a day and a start inside their weekly hours, add an optional note and ask. Nothing
/// is booked; the counselor approves or declines, and the person follows it under Bookings, Requests.
class SendRequestScreen extends ConsumerWidget {
  const SendRequestScreen({super.key, required this.counselor});

  final BookingCounselorSummary counselor;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final provider = sendRequestControllerProvider(counselor.id);
    final state = ref.watch(provider);
    final controller = ref.read(provider.notifier);

    ref.listen(provider, (_, now) {
      final sent = now.sent;

      if (sent != null) context.go(AppRoutes.requestPath(sent.id));
    });

    return Scaffold(
      appBar: AppBar(title: Text(l10n.requestTitle)),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(child: _body(context, ref, l10n, state, controller)),
            if (state.hours != null && state.days.isNotEmpty) _SendBar(state: state, onSend: controller.send),
          ],
        ),
      ),
    );
  }

  Widget _body(BuildContext context, WidgetRef ref, AppLocalizations l10n, SendRequestState state, SendRequestController controller) {
    if (state.loading) return const Center(child: CircularProgressIndicator());

    final failure = state.failure;

    if (failure != null) {
      return Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.cloud_off_outlined, size: 48),
            const SizedBox(height: AppSpacing.m),
            Text(failureMessage(l10n, failure), textAlign: TextAlign.center),
            const SizedBox(height: AppSpacing.l),
            FilledButton(onPressed: controller.retry, child: Text(l10n.tryAgain)),
          ],
        ),
      );
    }

    final hours = state.hours!;
    final name = hours.counselor.displayName.isEmpty ? counselor.name : hours.counselor.displayName;
    final text = Theme.of(context).textTheme;

    if (!hours.acceptsRequests || hours.hours.isEmpty || state.days.isEmpty) {
      return Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.event_busy_outlined, size: 48),
            const SizedBox(height: AppSpacing.m),
            Text(!hours.acceptsRequests ? l10n.requestErrClosed : (hours.hours.isEmpty ? l10n.requestNoHours : l10n.requestNoTimes), textAlign: TextAlign.center),
            const SizedBox(height: AppSpacing.l),
            OutlinedButton(onPressed: () => context.pop(), child: Text(l10n.pickSlotGoBack)),
          ],
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.l),
      children: [
        Semantics(header: true, child: Text(name, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
        const SizedBox(height: AppSpacing.s),
        Text(l10n.requestIntro(name, hours.answerWithinHours)),
        if (state.problem != null) ...[
          const SizedBox(height: AppSpacing.m),
          _Problem(state: state, onVerify: () => startEmailVerification(context, ref)),
        ],
        const SizedBox(height: AppSpacing.l),
        Semantics(header: true, child: Text(l10n.pickSlotDatesLabel, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800))),
        const SizedBox(height: AppSpacing.s),
        _Days(state: state, onSelect: controller.selectDate),
        const SizedBox(height: AppSpacing.l),
        _Times(state: state, onSelect: controller.selectTime),
        const SizedBox(height: AppSpacing.l),
        Semantics(
          header: true,
          child: Text(l10n.requestNoteLabel, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
        ),
        const SizedBox(height: AppSpacing.s),
        TextField(
          onChanged: controller.setNote,
          maxLength: 500,
          maxLines: 4,
          minLines: 2,
          textCapitalization: TextCapitalization.sentences,
          decoration: InputDecoration(hintText: l10n.requestNoteHint, border: const OutlineInputBorder()),
        ),
        const SizedBox(height: AppSpacing.s),
        Text(l10n.malaysiaTimeNote, style: text.bodySmall),
      ],
    );
  }
}

class _Days extends StatelessWidget {
  const _Days({required this.state, required this.onSelect});

  final SendRequestState state;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    final locale = Localizations.localeOf(context).toLanguageTag();

    return Wrap(
      spacing: AppSpacing.s,
      runSpacing: AppSpacing.s,
      children: [
        for (final day in state.days)
          ChoiceChip(
            label: Text(DateFormat('EEE d MMM', locale).format(day.day)),
            tooltip: DateFormat('EEEE d MMMM', locale).format(day.day),
            selected: day.date == state.selectedDate,
            materialTapTargetSize: MaterialTapTargetSize.padded,
            onSelected: state.busy ? null : (_) => onSelect(day.date),
          ),
      ],
    );
  }
}

class _Times extends StatelessWidget {
  const _Times({required this.state, required this.onSelect});

  final SendRequestState state;
  final ValueChanged<RequestTime> onSelect;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final text = Theme.of(context).textTheme;
    final day = state.day;

    if (day == null) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Semantics(
          header: true,
          child: Text(l10n.pickSlotTimesLabel(DateFormat('EEEE d MMMM', locale).format(day.day)), style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
        ),
        const SizedBox(height: AppSpacing.s),
        Wrap(
          spacing: AppSpacing.s,
          runSpacing: AppSpacing.s,
          children: [
            for (final time in day.times)
              Builder(builder: (context) {
                final place = time.line.location == null ? null : '${time.line.location!.name}, ${time.line.location!.city}';
                final clock = formatSlotTime(time.startsAt, locale: locale);
                final detail = [l10n.minutesShort(time.line.durationMinutes), time.mode.label(l10n), place].whereType<String>().where((p) => p.isNotEmpty).join(' · ');

                return Semantics(
                  button: true,
                  selected: state.selected?.startsAt == time.startsAt,
                  excludeSemantics: true,
                  label: [l10n.pickSlotSlotLabel(clock, time.line.durationMinutes, time.mode.label(l10n) ?? ''), ?place, if (state.selected?.startsAt == time.startsAt) l10n.pickSlotSelected].join(', '),
                  onTap: state.busy ? null : () => onSelect(time),
                  child: ChoiceChip(
                    label: Column(mainAxisSize: MainAxisSize.min, children: [Text(clock), Text(detail, style: text.bodySmall)]),
                    selected: state.selected?.startsAt == time.startsAt,
                    materialTapTargetSize: MaterialTapTargetSize.padded,
                    onSelected: state.busy ? null : (_) => onSelect(time),
                  ),
                );
              }),
          ],
        ),
      ],
    );
  }
}

class _Problem extends StatelessWidget {
  const _Problem({required this.state, required this.onVerify});

  final SendRequestState state;
  final VoidCallback onVerify;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final problem = state.problem!;

    final message = switch (problem) {
      SendProblem.slotTaken => l10n.requestErrTaken,
      SendProblem.timeConflict => l10n.confirmErrTimeConflict,
      SendProblem.limitReached => l10n.requestErrLimit(3),
      SendProblem.closed => l10n.requestErrClosed,
      SendProblem.invalidTime => l10n.requestErrInvalid,
      SendProblem.counselorGone => l10n.confirmErrCounselor,
      SendProblem.emailNotVerified => l10n.confirmErrVerify,
      SendProblem.rateLimited => state.waitSeconds == null ? l10n.confirmErrWaitGeneric : l10n.confirmErrWait(state.waitSeconds!),
      SendProblem.network => l10n.noConnectionBody,
      SendProblem.server => l10n.requestErrServer,
      SendProblem.other => l10n.somethingWentWrongBody,
    };

    return Semantics(
      liveRegion: true,
      container: true,
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.m),
        decoration: BoxDecoration(color: scheme.errorContainer, borderRadius: AppRadii.chipRadius),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [const Icon(Icons.error_outline, size: AppSizes.icon), const SizedBox(width: AppSpacing.s), Expanded(child: Text(message))],
            ),
            if (problem == SendProblem.emailNotVerified) ...[
              const SizedBox(height: AppSpacing.s),
              OutlinedButton(onPressed: onVerify, child: Text(l10n.pickSlotVerifyButton)),
            ],
            if (problem == SendProblem.counselorGone) ...[
              const SizedBox(height: AppSpacing.s),
              OutlinedButton(onPressed: () => context.go(AppRoutes.counselors), child: Text(l10n.confirmFindAnother)),
            ],
          ],
        ),
      ),
    );
  }
}

class _SendBar extends StatelessWidget {
  const _SendBar({required this.state, required this.onSend});

  final SendRequestState state;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final scheme = Theme.of(context).colorScheme;
    final selected = state.selected;
    final retrying = state.problem == SendProblem.network || state.problem == SendProblem.server;
    final when = selected == null ? null : '${DateFormat('EEE d MMM', locale).format(malaysiaWallClock(selected.startsAt))}, ${formatSlotTime(selected.startsAt, locale: locale)}';

    return Material(
      elevation: 4,
      color: scheme.surface,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.l),
        child: SizedBox(
          width: double.infinity,
          child: FilledButton(
            onPressed: selected == null || state.busy ? null : onSend,
            child: state.busy
                ? Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.5, color: scheme.onPrimary)),
                      const SizedBox(width: AppSpacing.s),
                      Text(l10n.requestSending),
                    ],
                  )
                : Text(when == null ? l10n.requestPickFirst : (retrying ? l10n.tryAgain : l10n.requestSendAt(when))),
          ),
        ),
      ),
    );
  }
}
