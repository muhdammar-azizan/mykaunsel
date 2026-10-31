import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure_message.dart';
import '../../../core/providers.dart';
import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../feed/presentation/feed_support.dart';
import '../domain/app_notification.dart';
import 'notifications_controller.dart';

/// The sentence for one notification, written from its type (so it can be translated). A type this version does not know yet, or one that arrived
/// without its name, shows the server's English title instead.
String notificationSentence(AppLocalizations l10n, AppNotification n) {
  final name = n.label;

  if (name == null || name.isEmpty) {
    return switch (n.type) {
      'feed.removed' => l10n.notifFeedRemoved,
      'feed.warned' => l10n.notifFeedWarned,
      _ => n.title,
    };
  }

  return switch (n.type) {
    'request.approved' => l10n.notifRequestApproved(name),
    'request.declined' => l10n.notifRequestDeclined(name),
    'request.expired' => l10n.notifRequestExpired(name),
    'booking.cancelled' => l10n.notifBookingCancelled(name),
    'invitation.received' => l10n.notifInvitationReceived(name),
    'membership.approved' => l10n.notifMembershipApproved(name),
    'membership.declined' => l10n.notifMembershipDeclined(name),
    'membership.ended' => l10n.notifMembershipEnded(name),
    'feed.removed' => l10n.notifFeedRemoved,
    'feed.warned' => l10n.notifFeedWarned,
    _ => n.title,
  };
}

IconData _icon(String type) {
  if (type.startsWith('request.')) return Icons.schedule_send_outlined;
  if (type.startsWith('booking.')) return Icons.event_busy_outlined;
  if (type.startsWith('invitation.') || type.startsWith('membership.')) return Icons.business_outlined;
  if (type.startsWith('feed.')) return Icons.forum_outlined;

  return Icons.notifications_none;
}

/// Where a notification leads. A booking or a request opens that page; the rest open the place they are about. Gone things (deleted after their
/// retention) lead to the list they belonged to.
void openNotification(BuildContext context, AppNotification n) {
  final target = n.target;

  if (target != null && target.kind == 'booking') {
    context.go(AppRoutes.bookingPath(target.id));
  } else if (target != null && target.kind == 'booking_request') {
    context.go(AppRoutes.requestPath(target.id));
  } else if (n.type.startsWith('booking.') || n.type.startsWith('request.')) {
    context.go(AppRoutes.bookings);
  } else if (n.type.startsWith('invitation.') || n.type.startsWith('membership.')) {
    context.go(AppRoutes.accountOrganization);
  } else if (n.type.startsWith('feed.')) {
    context.go(AppRoutes.feed);
  }
}

/// The bell's page: what happened that the person did not do themselves. Unread ones are marked with a dot AND bolder words AND the word "Unread"
/// for screen readers (never colour alone). Tapping one marks it read and opens what it is about.
class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.hasClients && _scroll.position.extentAfter < 300) ref.read(notificationsControllerProvider.notifier).loadMore();
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _markAll() async {
    final messenger = ScaffoldMessenger.of(context);
    final failed = AppLocalizations.of(context).notificationsMarkFailed;

    if (!await ref.read(notificationsControllerProvider.notifier).markAllRead()) {
      messenger.showSnackBar(SnackBar(content: Text(failed)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final state = ref.watch(notificationsControllerProvider);
    final controller = ref.read(notificationsControllerProvider.notifier);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final now = ref.watch(clockProvider)();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && _scroll.hasClients && _scroll.position.hasContentDimensions && _scroll.position.maxScrollExtent <= 0 && state.hasMore && !state.loadingMore && state.failure == null) controller.loadMore();
    });

    Widget body;

    if (state.loading) {
      body = const Center(child: CircularProgressIndicator());
    } else if (state.failure != null && state.items.isEmpty) {
      body = _Message(icon: Icons.cloud_off_outlined, title: l10n.somethingWentWrongTitle, body: failureMessage(l10n, state.failure!), action: FilledButton(onPressed: controller.refresh, child: Text(l10n.tryAgain)));
    } else if (state.items.isEmpty) {
      body = RefreshIndicator(
        onRefresh: controller.refresh,
        child: LayoutBuilder(
          builder: (context, box) => SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            child: SizedBox(height: box.maxHeight, child: _Message(icon: Icons.notifications_none, title: l10n.notificationsEmptyTitle, body: l10n.notificationsEmptyBody)),
          ),
        ),
      );
    } else {
      body = RefreshIndicator(
        onRefresh: controller.refresh,
        child: ListView(
          controller: _scroll,
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSpacing.l),
          children: [
            // Not in the app bar: at large text the title and this button do not fit side by side.
            if (state.unread > 0) Align(alignment: AlignmentDirectional.centerEnd, child: TextButton(onPressed: _markAll, child: Text(l10n.notificationsMarkAll))),
            for (final n in state.items) _Tile(notification: n, when: feedTimeLabel(l10n, n.occurredAt, now, locale), onTap: () => _open(n)),
            if (state.loadingMore) const Padding(padding: EdgeInsets.all(AppSpacing.l), child: Center(child: CircularProgressIndicator())),
            if (state.failure != null) Column(children: [Text(l10n.bookingsLoadMoreFailed), TextButton(onPressed: controller.loadMore, child: Text(l10n.tryAgain))]),
          ],
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: Text(l10n.notificationsTitle)),
      body: SafeArea(child: body),
    );
  }

  void _open(AppNotification n) {
    ref.read(notificationsControllerProvider.notifier).markRead(n);
    openNotification(context, n);
  }
}

class _Tile extends StatelessWidget {
  const _Tile({required this.notification, required this.when, required this.onTap});

  final AppNotification notification;
  final String when;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final sentence = notificationSentence(l10n, notification);
    final unread = !notification.read;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.s),
      child: Material(
        color: unread ? scheme.slotSelectedFill : scheme.cardFill,
        shape: RoundedRectangleBorder(borderRadius: AppRadii.cardRadius, side: BorderSide(color: unread ? scheme.slotSelectedBorder : scheme.cardBorder)),
        child: InkWell(
          borderRadius: AppRadii.cardRadius,
          onTap: onTap,
          child: Semantics(
            button: true,
            excludeSemantics: true,
            label: '${unread ? '${l10n.notificationUnread}. ' : ''}$sentence. $when',
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: AppSizes.minTouch),
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.l),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(_icon(notification.type), color: scheme.primary),
                    const SizedBox(width: AppSpacing.m),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(sentence, style: text.bodyLarge?.copyWith(fontWeight: unread ? FontWeight.w800 : FontWeight.w400)),
                          const SizedBox(height: AppSpacing.xs),
                          Text(when, style: text.bodySmall?.copyWith(color: scheme.mutedText)),
                        ],
                      ),
                    ),
                    if (unread) ...[
                      const SizedBox(width: AppSpacing.s),
                      Icon(Icons.circle, size: 10, color: scheme.primary),
                    ],
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

class _Message extends StatelessWidget {
  const _Message({required this.icon, required this.title, required this.body, this.action});

  final IconData icon;
  final String title;
  final String body;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xxl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: Theme.of(context).colorScheme.primary),
            const SizedBox(height: AppSpacing.l),
            Semantics(header: true, child: Text(title, textAlign: TextAlign.center, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
            const SizedBox(height: AppSpacing.s),
            Text(body, textAlign: TextAlign.center),
            if (action != null) ...[const SizedBox(height: AppSpacing.l), action!],
          ],
        ),
      ),
    );
  }
}
