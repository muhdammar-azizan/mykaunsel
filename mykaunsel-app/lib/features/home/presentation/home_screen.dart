import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/session/session_state.dart';
import '../../../l10n/app_localizations.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../bookings/domain/my_booking.dart';
import '../../bookings/presentation/booking_labels.dart';
import '../../bookings/presentation/my_bookings_controller.dart';
import '../../account/presentation/organization_controller.dart';
import '../../chat/presentation/help_lines.dart';
import '../../notifications/presentation/notification_bell.dart';

/// The home tab of a signed-in person. The first thing on it is the way to a counselor; more is added as features are built.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;
    final session = ref.watch(sessionControllerProvider);
    final fullName = session is SessionSignedIn ? session.user.name : '';
    final firstName = fullName.trim().split(RegExp(r'\s+')).first;

    return Scaffold(
      appBar: AppBar(title: Image.asset('assets/images/mykaunsel-logo.png', height: 30, semanticLabel: l10n.appName), actions: const [NotificationBell()]),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            Semantics(
              header: true,
              child: Text(l10n.homeGreeting(firstName), style: text.headlineMedium?.copyWith(fontWeight: FontWeight.w800)),
            ),
            const SizedBox(height: 4),
            Text(l10n.signedInAs(fullName), style: text.bodyMedium),
            const SizedBox(height: 24),
            const _UpcomingSession(),
            const _OrganizationHint(),
            Card(
              elevation: 0,
              color: Colors.white,
              clipBehavior: Clip.antiAlias,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20), side: BorderSide(color: Theme.of(context).colorScheme.onSurface.withValues(alpha: .1))),
              child: InkWell(
                onTap: () => context.go(AppRoutes.counselors),
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Row(
                    children: [
                      Icon(Icons.search, size: 32, color: Theme.of(context).colorScheme.primary),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(l10n.homeFindTitle, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                            const SizedBox(height: 4),
                            Text(l10n.homeFindBody, style: text.bodyMedium),
                          ],
                        ),
                      ),
                      const Icon(Icons.chevron_right),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            Card(
              elevation: 0,
              color: Colors.white,
              clipBehavior: Clip.antiAlias,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20), side: BorderSide(color: Theme.of(context).colorScheme.onSurface.withValues(alpha: .1))),
              child: InkWell(
                onTap: () => context.go(AppRoutes.chat),
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Row(
                    children: [
                      Icon(Icons.chat_bubble_outline, size: 32, color: Theme.of(context).colorScheme.primary),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(l10n.homeChatTitle, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                            const SizedBox(height: 4),
                            Text(l10n.homeChatBody, style: text.bodyMedium),
                          ],
                        ),
                      ),
                      const Icon(Icons.chevron_right),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            Card(
              elevation: 0,
              color: Colors.white,
              clipBehavior: Clip.antiAlias,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20), side: BorderSide(color: Theme.of(context).colorScheme.onSurface.withValues(alpha: .1))),
              child: InkWell(
                onTap: () => showHelpSheet(context),
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Row(
                    children: [
                      Icon(Icons.support_agent_outlined, size: 32, color: Theme.of(context).colorScheme.primary),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(l10n.homeHelpTitle, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                            const SizedBox(height: 4),
                            Text(l10n.homeHelpBody, style: text.bodyMedium),
                          ],
                        ),
                      ),
                      const Icon(Icons.chevron_right),
                    ],
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

/// The person's next confirmed session, above the other cards. It is simply left out while loading, when it failed to load, or when there
/// is nothing booked: Home never waits on it or shows an error for it.
class _UpcomingSession extends ConsumerWidget {
  const _UpcomingSession();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final next = ref
        .watch(myBookingsControllerProvider(BookingsScope.upcoming))
        .items
        .where((b) => b.status == BookingState.confirmed)
        .firstOrNull;

    if (next == null) {
      return const SizedBox.shrink();
    }

    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final when = bookingWhen(l10n, startsAt: next.startsAt, endsAt: next.endsAt, now: ref.watch(clockProvider)(), locale: locale);
    final type = bookingType(l10n, next.mode, next.location);

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.m),
      child: Material(
        color: scheme.slotSelectedFill,
        shape: RoundedRectangleBorder(borderRadius: AppRadii.cardRadius, side: BorderSide(color: scheme.slotSelectedBorder)),
        child: InkWell(
          borderRadius: AppRadii.cardRadius,
          onTap: () => context.go(AppRoutes.bookingPath(next.id)),
          child: Semantics(
            button: true,
            excludeSemantics: true,
            label: '${l10n.homeUpcomingTitle}: ${l10n.bookingItemLabel(when, next.counselor.displayName, type, next.status.label(l10n))}',
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: AppSizes.minTouch),
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.xl - AppSpacing.xs),
                child: Row(
                  children: [
                    Icon(Icons.event_available_outlined, size: 32, color: scheme.primary),
                    const SizedBox(width: AppSpacing.l),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(l10n.homeUpcomingTitle, style: text.labelLarge?.copyWith(color: scheme.primary, fontWeight: FontWeight.w700)),
                          const SizedBox(height: AppSpacing.xs),
                          Text(when, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                          Text(next.counselor.displayName, style: text.bodyLarge),
                          Text(type, style: text.bodyMedium),
                          if (next.startingSoon) ...[
                            const SizedBox(height: AppSpacing.s),
                            Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.schedule, size: AppSizes.icon),
                                const SizedBox(width: AppSpacing.xs),
                                Flexible(child: Text(l10n.bookingStartingSoon, style: text.labelLarge?.copyWith(fontWeight: FontWeight.w700))),
                              ],
                            ),
                          ],
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// A nudge towards Account > Your organization, because nothing else tells a new employee where a reference code is typed: an invitation waiting for
/// them, a request waiting for approval, or (until they hide it) "Have a code from your organization?". It says nothing once they belong to one,
/// while loading, or if it could not be loaded: Home never waits on it or shows an error for it.
class _OrganizationHint extends ConsumerWidget {
  const _OrganizationHint();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(organizationControllerProvider);
    final dismissed = ref.watch(orgHintDismissedProvider);

    if (state.loading || state.loadFailure != null) {
      return const SizedBox.shrink();
    }

    final l10n = AppLocalizations.of(context);
    final organization = state.organization;
    final invited = state.invitations.firstOrNull;

    final String title;
    final String body;
    var canHide = false;

    if (invited != null && (organization == null || organization.waiting)) {
      title = l10n.orgInvitedTitle(invited.organization);
      body = l10n.homeOrgInvitedBody;
    } else if (organization != null && organization.waiting) {
      title = l10n.orgWaitingTitle(organization.name);
      body = l10n.homeOrgWaitingBody;
    } else if (organization == null && !dismissed) {
      title = l10n.homeOrgCodeTitle;
      body = l10n.homeOrgCodeBody;
      canHide = true;
    } else {
      return const SizedBox.shrink();
    }

    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.m),
      child: Material(
        color: scheme.cardFill,
        shape: RoundedRectangleBorder(borderRadius: AppRadii.cardRadius, side: BorderSide(color: scheme.cardBorder)),
        child: Row(
          children: [
            Expanded(
              child: InkWell(
                borderRadius: AppRadii.cardRadius,
                onTap: () => context.push(AppRoutes.accountOrganization),
                child: Semantics(
                  button: true,
                  excludeSemantics: true,
                  label: '$title. $body',
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(minHeight: AppSizes.minTouch),
                    child: Padding(
                      padding: const EdgeInsets.all(AppSpacing.l),
                      child: Row(
                        children: [
                          Icon(Icons.business_outlined, size: 28, color: scheme.primary),
                          const SizedBox(width: AppSpacing.l),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(title, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                                const SizedBox(height: AppSpacing.xs),
                                Text(body, style: text.bodyMedium),
                              ],
                            ),
                          ),
                          if (!canHide) const Icon(Icons.chevron_right),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
            if (canHide)
              IconButton(
                tooltip: l10n.homeHintHide,
                constraints: const BoxConstraints(minWidth: AppSizes.minTouch, minHeight: AppSizes.minTouch),
                icon: const Icon(Icons.close),
                onPressed: () => ref.read(orgHintDismissedProvider.notifier).dismiss(),
              ),
          ],
        ),
      ),
    );
  }
}
