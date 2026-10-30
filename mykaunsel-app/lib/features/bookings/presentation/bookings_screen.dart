import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure_message.dart';
import '../../../core/providers.dart';
import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/my_booking.dart';
import 'booking_labels.dart';
import 'my_bookings_controller.dart';

/// The Bookings tab: Upcoming and Past. Read only; a booking opens its details.
class BookingsScreen extends StatelessWidget {
  const BookingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(l10n.bookingsTitle),
          bottom: TabBar(tabs: [Tab(text: l10n.bookingsTabUpcoming), Tab(text: l10n.bookingsTabPast)]),
        ),
        body: const SafeArea(
          child: TabBarView(children: [_BookingList(scope: BookingsScope.upcoming), _BookingList(scope: BookingsScope.past)]),
        ),
      ),
    );
  }
}

class _BookingList extends ConsumerStatefulWidget {
  const _BookingList({required this.scope});

  final BookingsScope scope;

  @override
  ConsumerState<_BookingList> createState() => _BookingListState();
}

class _BookingListState extends ConsumerState<_BookingList> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.hasClients && _scroll.position.extentAfter < 300) {
        ref.read(myBookingsControllerProvider(widget.scope).notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final state = ref.watch(myBookingsControllerProvider(widget.scope));
    final controller = ref.read(myBookingsControllerProvider(widget.scope).notifier);
    final upcoming = widget.scope == BookingsScope.upcoming;

    // A short page that does not fill the screen cannot be scrolled, so scrolling would never ask for the next one: ask for it here.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && _scroll.hasClients && _scroll.position.maxScrollExtent <= 0 && state.hasMore && !state.loadingMore && state.failure == null) {
        controller.loadMore();
      }
    });

    if (state.loading) return const _Skeleton();

    if (state.failure != null && state.items.isEmpty) {
      return _Message(
        icon: Icons.cloud_off_outlined,
        title: l10n.somethingWentWrongTitle,
        body: failureMessage(l10n, state.failure!),
        action: FilledButton(onPressed: controller.refresh, child: Text(l10n.tryAgain)),
      );
    }

    if (state.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: controller.refresh,
        child: LayoutBuilder(
          builder: (context, box) => SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            child: SizedBox(
              height: box.maxHeight,
              child: _Message(
                icon: upcoming ? Icons.event_available_outlined : Icons.history,
                title: upcoming ? l10n.bookingsEmptyUpcomingTitle : l10n.bookingsEmptyPastTitle,
                body: upcoming ? l10n.bookingsEmptyUpcomingBody : l10n.bookingsEmptyPastBody,
                action: upcoming ? FilledButton(onPressed: () => context.go(AppRoutes.counselors), child: Text(l10n.homeFindTitle)) : null,
              ),
            ),
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView.separated(
        controller: _scroll,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSpacing.l),
        itemCount: state.items.length + 1,
        separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.m),
        itemBuilder: (context, index) {
          if (index == state.items.length) return _Footer(state: state, onRetry: controller.loadMore);

          return _BookingCard(booking: state.items[index], showBookAgain: !upcoming);
        },
      ),
    );
  }
}

class _Footer extends StatelessWidget {
  const _Footer({required this.state, required this.onRetry});

  final BookingListState state;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    if (state.loadingMore) {
      return const Padding(padding: EdgeInsets.all(AppSpacing.l), child: Center(child: CircularProgressIndicator()));
    }

    if (state.failure != null) {
      return Column(
        children: [
          Semantics(liveRegion: true, child: Text(l10n.bookingsLoadMoreFailed)),
          TextButton(onPressed: onRetry, child: Text(l10n.tryAgain)),
        ],
      );
    }

    return const SizedBox.shrink();
  }
}

class _BookingCard extends ConsumerWidget {
  const _BookingCard({required this.booking, required this.showBookAgain});

  final MyBooking booking;
  final bool showBookAgain;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final when = bookingWhen(l10n, startsAt: booking.startsAt, endsAt: booking.endsAt, now: ref.watch(clockProvider)(), locale: locale);
    final type = bookingType(l10n, booking.mode, booking.location);
    final counselorId = booking.counselor.id;

    return Material(
      color: scheme.cardFill,
      shape: RoundedRectangleBorder(borderRadius: AppRadii.cardRadius, side: BorderSide(color: scheme.cardBorder)),
      child: Column(
        children: [
          InkWell(
            borderRadius: AppRadii.cardRadius,
            onTap: () => context.push(AppRoutes.bookingPath(booking.id)),
            child: Semantics(
              button: true,
              excludeSemantics: true,
              label: l10n.bookingItemLabel(when, booking.counselor.displayName, type, booking.status.label(l10n)),
              child: ConstrainedBox(
                constraints: const BoxConstraints(minHeight: AppSizes.minTouch),
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.l),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(when, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                      const SizedBox(height: AppSpacing.xs),
                      Text(booking.counselor.displayName, style: text.bodyLarge),
                      Text(type, style: text.bodyMedium?.copyWith(color: scheme.mutedText)),
                      const SizedBox(height: AppSpacing.s),
                      Wrap(
                        spacing: AppSpacing.s,
                        runSpacing: AppSpacing.xs,
                        children: [
                          _Badge(icon: booking.status.icon, label: booking.status.label(l10n)),
                          if (booking.startingSoon) _Badge(icon: Icons.schedule, label: l10n.bookingStartingSoon, emphasis: true),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          if (showBookAgain && counselorId != null)
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(AppSpacing.s, 0, AppSpacing.s, AppSpacing.s),
                child: TextButton(onPressed: () => context.go(AppRoutes.counselorPath(counselorId)), child: Text(l10n.bookAgain)),
              ),
            ),
        ],
      ),
    );
  }
}

/// A state as a word and a symbol.
class _Badge extends StatelessWidget {
  const _Badge({required this.icon, required this.label, this.emphasis = false});

  final IconData icon;
  final String label;
  final bool emphasis;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s, vertical: AppSpacing.xs),
      decoration: BoxDecoration(color: emphasis ? scheme.bannerFill : scheme.inactiveFill, borderRadius: AppRadii.pillRadius),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: AppSizes.icon - 4),
          const SizedBox(width: AppSpacing.xs),
          Flexible(child: Text(label, style: Theme.of(context).textTheme.labelMedium)),
        ],
      ),
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.icon, required this.title, required this.body, this.action});

  final IconData icon;
  final String title;
  final String body;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xxl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: Theme.of(context).colorScheme.disabledText),
            const SizedBox(height: AppSpacing.l),
            Semantics(header: true, child: Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700), textAlign: TextAlign.center)),
            const SizedBox(height: AppSpacing.s),
            Text(body, textAlign: TextAlign.center),
            if (action != null) ...[const SizedBox(height: AppSpacing.xl), action!],
          ],
        ),
      ),
    );
  }
}

class _Skeleton extends StatelessWidget {
  const _Skeleton();

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme.skeleton;

    return Semantics(
      label: AppLocalizations.of(context).loadingMore,
      child: ExcludeSemantics(
        child: ListView(
          physics: const NeverScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSpacing.l),
          children: [
            for (var i = 0; i < 3; i++)
              Container(
                height: 104,
                margin: const EdgeInsets.only(bottom: AppSpacing.m),
                decoration: BoxDecoration(color: color, borderRadius: AppRadii.cardRadius),
              ),
          ],
        ),
      ),
    );
  }
}
