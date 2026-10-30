import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/error/failure_message.dart';
import '../../../core/providers.dart';
import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/format/slot_time.dart';
import '../../auth/presentation/start_email_verification.dart';
import '../../counselors/domain/counselor.dart';
import '../../counselors/presentation/counselor_labels.dart';
import '../domain/availability.dart';
import 'booking_args.dart';
import 'pick_slot_controller.dart';
import 'pick_slot_state.dart';

/// The phone's own offset from UTC; a provider so a test can pretend the phone is somewhere else.
final deviceUtcOffsetProvider = Provider<Duration Function()>((ref) => () => DateTime.now().timeZoneOffset);

const _malaysiaUtcOffset = Duration(hours: 8);

/// "Pick a slot": choose a mode, a date and a time. Nothing is booked or held here; the next screen checks again.
class PickSlotScreen extends ConsumerStatefulWidget {
  const PickSlotScreen({super.key, required this.counselor});

  final BookingCounselorSummary counselor;

  @override
  ConsumerState<PickSlotScreen> createState() => _PickSlotScreenState();
}

class _PickSlotScreenState extends ConsumerState<PickSlotScreen> {
  final _dates = ScrollController();

  @override
  void initState() {
    super.initState();
    _dates.addListener(() {
      if (_dates.hasClients && _dates.position.extentAfter < 200) {
        ref.read(pickSlotControllerProvider(widget.counselor.id).notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _dates.dispose();
    super.dispose();
  }

  Future<void> _continue(PickSlotState state) async {
    final slot = state.selected;

    if (slot == null) return;

    final outcome = await context.push<ConfirmOutcome>(
      AppRoutes.confirmBookingPath(widget.counselor.id, slot.id),
      extra: ConfirmBookingArgs.from(widget.counselor, slot),
    );

    if (!mounted) return;

    if (outcome == ConfirmOutcome.slotTaken) {
      ref.read(pickSlotControllerProvider(widget.counselor.id).notifier).markTaken(slot.id);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final id = widget.counselor.id;
    final state = ref.watch(pickSlotControllerProvider(id));
    final controller = ref.read(pickSlotControllerProvider(id).notifier);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.pickSlotTitle)),
      body: SafeArea(
        child: Column(
          children: [
            _Header(counselor: widget.counselor, loaded: state.counselor),
            Expanded(child: _body(context, l10n, state, controller)),
            if (state.hasData && state.failure == null && state.anyAvailable)
              _ContinueBar(state: state, onContinue: () => _continue(state)),
          ],
        ),
      ),
    );
  }

  Widget _body(BuildContext context, AppLocalizations l10n, PickSlotState state, PickSlotController controller) {
    if (state.loading) return const _Skeleton();

    final failure = state.failure;

    if (failure != null) return _FailureView(failure: failure, state: state, onRetry: controller.retry, counselor: widget.counselor);
    if (!state.anyAvailable && state.nextFrom == null) return _EmptyView(onBack: () => context.pop());

    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(AppSpacing.l, AppSpacing.s, AppSpacing.l, AppSpacing.xl),
        children: [
          if (state.refreshing) Semantics(liveRegion: true, child: Text(l10n.pickSlotRefreshing, style: Theme.of(context).textTheme.bodySmall)),
          if (state.notice != null) _NoticeBanner(notice: state.notice!, onDismiss: controller.dismissNotice),
          if (state.refreshFailure != null) _InlineMessage(text: _refreshFailureText(l10n, state)),
          if (_timeZoneDiffers()) _InlineMessage(text: l10n.pickSlotTimeZoneNote),
          if (state.offersBoth) _ModeToggle(state: state, onSelect: controller.selectMode),
          const SizedBox(height: AppSpacing.l),
          Semantics(header: true, child: Text(l10n.pickSlotDatesLabel, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800))),
          const SizedBox(height: AppSpacing.s),
          _DateStrip(state: state, scroll: _dates, onSelect: controller.selectDate, onLoadMore: controller.loadMore),
          const SizedBox(height: AppSpacing.l),
          _Times(state: state, controller: controller),
        ],
      ),
    );
  }

  bool _timeZoneDiffers() => ref.read(deviceUtcOffsetProvider)() != _malaysiaUtcOffset;

  String _refreshFailureText(AppLocalizations l10n, PickSlotState state) {
    final failure = state.refreshFailure!;

    if (failure is RateLimitedFailure) {
      final until = state.retryUntil;
      final seconds = until == null ? (failure.retryAfterSeconds ?? 30) : until.difference(ref.read(clockProvider)()).inSeconds.clamp(1, 3600);

      return l10n.pickSlotWaitSeconds(seconds);
    }

    return state.loadingMore ? l10n.pickSlotLoadMoreFailed : l10n.pickSlotRefreshFailed;
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.counselor, required this.loaded});

  final BookingCounselorSummary counselor;
  final AvailabilityCounselor? loaded;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final name = loaded?.displayName ?? counselor.name;
    final verified = loaded?.verified ?? counselor.verified;
    final initials = name.trim().isEmpty ? '' : name.trim().split(RegExp(r'\s+')).take(2).map((p) => p.characters.first.toUpperCase()).join();

    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.l, AppSpacing.s, AppSpacing.l, AppSpacing.s),
      child: Row(
        children: [
          ExcludeSemantics(
            child: CircleAvatar(
              radius: AppSizes.avatar / 2,
              backgroundColor: scheme.primary.withValues(alpha: .12),
              child: Text(initials, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800, color: scheme.primary)),
            ),
          ),
          const SizedBox(width: AppSpacing.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Semantics(header: true, child: Text(name, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
                if (verified)
                  Row(
                    children: [
                      Icon(Icons.verified, size: AppSizes.icon - 4, color: scheme.primary),
                      const SizedBox(width: AppSpacing.xs),
                      Flexible(child: Text(l10n.verifiedLabel, style: text.bodySmall?.copyWith(color: scheme.primary, fontWeight: FontWeight.w600))),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ModeToggle extends StatelessWidget {
  const _ModeToggle({required this.state, required this.onSelect});

  final PickSlotState state;
  final ValueChanged<SessionMode> onSelect;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Semantics(
      container: true,
      label: l10n.pickSlotModeLabel,
      child: Wrap(
        spacing: AppSpacing.s,
        children: [
          for (final mode in state.modes)
            ChoiceChip(
              label: Text(mode.label(l10n) ?? ''),
              selected: state.mode == mode,
              materialTapTargetSize: MaterialTapTargetSize.padded,
              onSelected: (_) => onSelect(mode),
            ),
        ],
      ),
    );
  }
}

class _DateStrip extends StatelessWidget {
  const _DateStrip({required this.state, required this.scroll, required this.onSelect, required this.onLoadMore});

  final PickSlotState state;
  final ScrollController scroll;
  final ValueChanged<String> onSelect;
  final VoidCallback onLoadMore;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final scheme = Theme.of(context).colorScheme;
    final scale = MediaQuery.textScalerOf(context).scale(1);

    return SizedBox(
      height: AppSizes.dateCardHeight * scale.clamp(1, 2),
      child: ListView(
        controller: scroll,
        scrollDirection: Axis.horizontal,
        children: [
          for (final day in state.days)
            Builder(builder: (context) {
              final selected = day.date == state.selectedDate;
              final free = state.dayHasAvailable(day);
              final count = day.slots.where((s) => (state.mode == null || s.mode == state.mode) && PickSlotRules.asShown(s, state.takenIds).isAvailable).length;
              final fg = selected ? scheme.dateSelectedText : (free ? scheme.onSurface : scheme.disabledText);

              return Semantics(
                button: true,
                selected: selected,
                enabled: true,
                label: l10n.pickSlotDateCard(DateFormat('EEEE', locale).format(day.day), day.day.day.toString(), DateFormat('MMMM', locale).format(day.day), count),
                excludeSemantics: true,
                onTap: () => onSelect(day.date),
                child: Padding(
                  padding: const EdgeInsets.only(right: AppSpacing.s),
                  child: Material(
                    color: selected ? scheme.dateSelectedFill : scheme.cardFill,
                    shape: RoundedRectangleBorder(borderRadius: AppRadii.cardRadius, side: BorderSide(color: scheme.cardBorder)),
                    child: InkWell(
                      borderRadius: AppRadii.cardRadius,
                      onTap: () => onSelect(day.date),
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(minWidth: AppSizes.dateCardWidth, minHeight: AppSizes.minTouch),
                        child: Padding(
                          padding: const EdgeInsets.all(AppSpacing.s),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(DateFormat('EEE', locale).format(day.day), style: TextStyle(color: fg)),
                              Text(day.day.day.toString(), style: TextStyle(color: fg, fontWeight: FontWeight.w800, fontSize: 20)),
                              Text(DateFormat('MMM', locale).format(day.day), style: TextStyle(color: fg)),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              );
            }),
          if (state.nextFrom != null)
            Center(
              child: state.loadingMore
                  ? const Padding(padding: EdgeInsets.all(AppSpacing.m), child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2.5)))
                  : TextButton(onPressed: onLoadMore, child: Text(l10n.pickSlotLoadMore)),
            ),
        ],
      ),
    );
  }
}

class _Times extends StatelessWidget {
  const _Times({required this.state, required this.controller});

  final PickSlotState state;
  final PickSlotController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final text = Theme.of(context).textTheme;
    final day = state.day;

    if (day == null) return const SizedBox.shrink();

    final slots = state.daySlots;
    final other = state.modes.where((m) => m != state.mode).firstOrNull;
    final otherFree = other == null ? 0 : state.availableOnSelectedDayIn(other);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Semantics(
          header: true,
          child: Text(
            l10n.pickSlotTimesLabel(DateFormat('EEEE d MMMM', locale).format(day.day)),
            style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800),
          ),
        ),
        const SizedBox(height: AppSpacing.s),
        if (slots.isEmpty) ...[
          Text(state.offersBoth && state.mode != null ? l10n.pickSlotNoTimesDayMode((state.mode!.label(l10n) ?? '').toLowerCase()) : l10n.pickSlotNoTimesDay),
          if (other != null && otherFree > 0)
            TextButton(onPressed: () => controller.selectMode(other), child: Text(l10n.pickSlotTryMode(other.label(l10n) ?? ''))),
        ] else
          Wrap(
            spacing: AppSpacing.s,
            runSpacing: AppSpacing.s,
            children: [for (final slot in slots) _SlotChip(slot: slot, selected: state.selected?.id == slot.id, onTap: () => controller.selectSlot(slot))],
          ),
        const SizedBox(height: AppSpacing.m),
        Text(l10n.malaysiaTimeNote, style: text.bodySmall),
      ],
    );
  }
}

class _SlotChip extends StatelessWidget {
  const _SlotChip({required this.slot, required this.selected, required this.onTap});

  final AvailabilitySlot slot;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final available = slot.isAvailable;
    final time = formatSlotTime(slot.startsAt, locale: locale);
    final place = slot.location == null ? null : '${slot.location!.name}, ${slot.location!.city}';
    final detail = [l10n.minutesShort(slot.durationMinutes), ?place].join(' · ');

    return Semantics(
      button: true,
      selected: selected,
      enabled: available,
      excludeSemantics: true,
      label: [
        l10n.pickSlotSlotLabel(time, slot.durationMinutes, slot.mode.label(l10n) ?? ''),
        ?place,
        if (!available) l10n.pickSlotBooked,
        if (selected) l10n.pickSlotSelected,
      ].join(', '),
      onTap: available ? onTap : null,
      child: Material(
        color: selected ? scheme.slotSelectedFill : (available ? scheme.cardFill : scheme.inactiveFill),
        shape: RoundedRectangleBorder(
          borderRadius: AppRadii.chipRadius,
          side: BorderSide(
            color: selected ? scheme.slotSelectedBorder : scheme.cardBorder,
            width: selected ? AppSizes.borderSelected : AppSizes.borderThin,
          ),
        ),
        child: InkWell(
          borderRadius: AppRadii.chipRadius,
          onTap: available ? onTap : null,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minWidth: 104, minHeight: AppSizes.minTouch),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.m, vertical: AppSpacing.s),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (selected) ...[Icon(Icons.check_circle, size: AppSizes.icon - 4, color: scheme.slotSelectedBorder), const SizedBox(width: AppSpacing.xs)],
                      Text(
                        time,
                        style: text.bodyLarge?.copyWith(
                          fontWeight: FontWeight.w700,
                          color: available ? scheme.onSurface : scheme.inactiveText,
                          decoration: available ? null : TextDecoration.lineThrough,
                        ),
                      ),
                    ],
                  ),
                  Text(available ? detail : l10n.pickSlotBooked, style: text.bodySmall?.copyWith(color: available ? scheme.mutedText : scheme.inactiveText)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ContinueBar extends StatelessWidget {
  const _ContinueBar({required this.state, required this.onContinue});

  final PickSlotState state;
  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final selected = state.selected;
    final when = selected == null
        ? null
        : '${DateFormat('EEE d MMM', locale).format(malaysiaWallClock(selected.startsAt))}, ${formatSlotTime(selected.startsAt, locale: locale)}';

    return Material(
      elevation: 4,
      color: Theme.of(context).colorScheme.surface,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.l),
        child: SizedBox(
          width: double.infinity,
          child: FilledButton(
            onPressed: selected == null ? null : onContinue,
            child: Text(when == null ? l10n.pickSlotPickFirst : l10n.pickSlotContinueAt(when)),
          ),
        ),
      ),
    );
  }
}

class _NoticeBanner extends StatelessWidget {
  const _NoticeBanner({required this.notice, required this.onDismiss});

  final PickSlotNotice notice;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final text = switch (notice) {
      PickSlotNotice.selectionLost => l10n.pickSlotSelectionLost,
      PickSlotNotice.slotTaken => l10n.pickSlotTaken,
    };

    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.m),
      padding: const EdgeInsets.all(AppSpacing.m),
      decoration: BoxDecoration(color: Theme.of(context).colorScheme.bannerFill, borderRadius: AppRadii.chipRadius),
      child: Row(
        children: [
          Expanded(child: Semantics(liveRegion: true, child: Text(text))),
          IconButton(onPressed: onDismiss, icon: const Icon(Icons.close), tooltip: MaterialLocalizations.of(context).closeButtonTooltip),
        ],
      ),
    );
  }
}

class _InlineMessage extends StatelessWidget {
  const _InlineMessage({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.m),
      child: Semantics(liveRegion: true, child: Text(text, style: Theme.of(context).textTheme.bodySmall)),
    );
  }
}

class _Skeleton extends StatelessWidget {
  const _Skeleton();

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme.skeleton;
    Widget bar(double w, double h) => Container(width: w, height: h, decoration: BoxDecoration(color: color, borderRadius: AppRadii.chipRadius));

    return Semantics(
      label: AppLocalizations.of(context).loadingMore,
      child: ExcludeSemantics(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.l),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                physics: const NeverScrollableScrollPhysics(),
                child: Row(children: [for (var i = 0; i < 5; i++) Padding(padding: const EdgeInsets.only(right: AppSpacing.s), child: bar(AppSizes.dateCardWidth, AppSizes.dateCardHeight))]),
              ),
              const SizedBox(height: AppSpacing.xl),
              Wrap(spacing: AppSpacing.s, runSpacing: AppSpacing.s, children: [for (var i = 0; i < 6; i++) bar(104, AppSizes.minTouch + 12)]),
            ],
          ),
        ),
      ),
    );
  }
}

class _EmptyView extends StatelessWidget {
  const _EmptyView({required this.onBack});

  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xxl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.event_busy_outlined, size: 48, color: Theme.of(context).colorScheme.disabledText),
            const SizedBox(height: AppSpacing.l),
            Text(l10n.pickSlotNoneTitle, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: AppSpacing.s),
            Text(l10n.pickSlotNoneBody, textAlign: TextAlign.center),
            const SizedBox(height: AppSpacing.xl),
            OutlinedButton(onPressed: onBack, child: Text(l10n.pickSlotGoBack)),
          ],
        ),
      ),
    );
  }
}

class _FailureView extends ConsumerWidget {
  const _FailureView({required this.failure, required this.state, required this.onRetry, required this.counselor});

  final AppFailure failure;
  final PickSlotState state;
  final VoidCallback onRetry;
  final BookingCounselorSummary counselor;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;

    final (title, body) = switch (failure) {
      NotFoundFailure() => (l10n.pickSlotUnavailableTitle, l10n.pickSlotUnavailableBody),
      EmailNotVerifiedFailure() => (l10n.pickSlotVerifyTitle, l10n.pickSlotVerifyBody),
      _ => (l10n.somethingWentWrongTitle, failureMessage(l10n, failure)),
    };

    final until = state.retryUntil;
    final waiting = failure is RateLimitedFailure && until != null && ref.read(clockProvider)().isBefore(until);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xxl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Semantics(liveRegion: true, child: Text(title, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w700), textAlign: TextAlign.center)),
            const SizedBox(height: AppSpacing.s),
            Text(body, textAlign: TextAlign.center),
            const SizedBox(height: AppSpacing.xl),
            switch (failure) {
              NotFoundFailure() => OutlinedButton(onPressed: () => context.pop(), child: Text(l10n.pickSlotGoBack)),
              EmailNotVerifiedFailure() => FilledButton(onPressed: () => startEmailVerification(context, ref), child: Text(l10n.pickSlotVerifyButton)),
              _ => FilledButton(onPressed: waiting ? null : onRetry, child: Text(l10n.tryAgain)),
            },
          ],
        ),
      ),
    );
  }
}
