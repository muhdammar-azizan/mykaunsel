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
import '../../chat/domain/chat.dart';
import '../../chat/presentation/help_lines.dart';
import '../domain/feed.dart';
import 'feed_controller.dart';

/// "Just now", "5 min ago", "3 h ago", then the date (Malaysian time). Read aloud as it is written.
String feedTimeLabel(AppLocalizations l10n, DateTime at, DateTime now, String locale) {
  final minutes = now.difference(at).inMinutes;

  if (minutes < 1) return l10n.feedJustNow;
  if (minutes < 60) return l10n.feedMinutesAgo(minutes);
  if (minutes < 24 * 60) return l10n.feedHoursAgo(minutes ~/ 60);

  return DateFormat('d MMM', locale).format(malaysiaWallClock(at));
}

/// The words for a failure in the feed: the daily limit of posts (a wait of hours) and a post that is gone get their own sentence.
String feedFailureText(AppLocalizations l10n, AppFailure failure) {
  return switch (failure) {
    RateLimitedFailure(:final retryAfterSeconds) when (retryAfterSeconds ?? 0) > 3600 => l10n.feedDailyLimit,
    NotFoundFailure() => l10n.feedGone,
    _ => failureMessage(l10n, failure),
  };
}

void showFeedFailure(BuildContext context, AppFailure failure) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(feedFailureText(AppLocalizations.of(context), failure))));
}

/// The author's name with the counselor badge (an icon AND the word, never colour alone) and the time.
class AuthorLine extends ConsumerWidget {
  const AuthorLine({super.key, required this.author, required this.at, this.edited = false});

  final FeedAuthor author;
  final DateTime at;
  final bool edited;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final now = ref.watch(clockProvider)();
    final when = feedTimeLabel(l10n, at, now, Localizations.localeOf(context).toLanguageTag());

    return Wrap(
      spacing: AppSpacing.s,
      runSpacing: AppSpacing.xs,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        Text(author.name, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
        if (author.counselor)
          DecoratedBox(
            decoration: BoxDecoration(color: scheme.bannerFill, borderRadius: AppRadii.pillRadius),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s, vertical: 2),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.verified_outlined, size: 14),
                const SizedBox(width: AppSpacing.xs),
                Text(l10n.feedCounselorBadge, style: text.labelSmall?.copyWith(fontWeight: FontWeight.w700)),
              ]),
            ),
          ),
        Text(edited ? '$when · ${l10n.feedEdited}' : when, style: text.bodySmall?.copyWith(color: scheme.mutedText)),
      ],
    );
  }
}

/// What the person can do with a post or a comment from its "more" button. null = they closed the menu.
enum FeedMenuAction { edit, delete, report, mute }

Future<FeedMenuAction?> showFeedMenu(BuildContext context, {required bool mine, required bool isComment, String? authorName}) {
  final l10n = AppLocalizations.of(context);

  return showModalBottomSheet<FeedMenuAction>(
    context: context,
    showDragHandle: true,
    useSafeArea: true,
    builder: (sheet) => SafeArea(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        if (mine && !isComment) _MenuTile(icon: Icons.edit_outlined, label: l10n.feedMenuEdit, action: FeedMenuAction.edit),
        if (mine) _MenuTile(icon: Icons.delete_outline, label: isComment ? l10n.feedMenuDeleteComment : l10n.feedMenuDelete, action: FeedMenuAction.delete),
        if (!mine) _MenuTile(icon: Icons.flag_outlined, label: isComment ? l10n.feedMenuReportComment : l10n.feedMenuReport, action: FeedMenuAction.report),
        if (!mine && !isComment && authorName != null) _MenuTile(icon: Icons.volume_off_outlined, label: l10n.feedMenuMute(authorName), action: FeedMenuAction.mute),
      ]),
    ),
  );
}

class _MenuTile extends StatelessWidget {
  const _MenuTile({required this.icon, required this.label, required this.action});

  final IconData icon;
  final String label;
  final FeedMenuAction action;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      minTileHeight: AppSizes.minTouch,
      leading: Icon(icon),
      title: Text(label),
      onTap: () => Navigator.of(context).pop(action),
    );
  }
}

Future<bool> _confirm(BuildContext context, {required String title, String? body, required String confirm}) async {
  final l10n = AppLocalizations.of(context);
  final yes = await showDialog<bool>(
    context: context,
    builder: (dialog) => AlertDialog(
      title: Text(title),
      content: body == null ? null : Text(body),
      actions: [
        TextButton(onPressed: () => Navigator.of(dialog).pop(false), child: Text(l10n.cancel)),
        FilledButton(onPressed: () => Navigator.of(dialog).pop(true), child: Text(confirm)),
      ],
    ),
  );

  return yes ?? false;
}

/// Asks, then deletes the person's own post. True when it is gone (a post already gone counts: that is what was asked).
Future<bool> confirmDeletePost(BuildContext context, WidgetRef ref, String id) async {
  final l10n = AppLocalizations.of(context);
  final messenger = ScaffoldMessenger.of(context);

  if (!await _confirm(context, title: l10n.feedDeleteTitle, body: l10n.feedDeleteBody, confirm: l10n.feedDeleteConfirm)) return false;

  try {
    await ref.read(feedApiProvider).delete(id);
  } on NotFoundFailure {
    // already gone
  } on AppFailure catch (failure) {
    messenger.showSnackBar(SnackBar(content: Text(feedFailureText(l10n, failure))));

    return false;
  }

  ref.read(feedControllerProvider.notifier).remove(id);
  messenger.showSnackBar(SnackBar(content: Text(l10n.feedDeleted)));

  return true;
}

Future<bool> confirmDeleteComment(BuildContext context, WidgetRef ref, String postId, String commentId) async {
  final l10n = AppLocalizations.of(context);
  final messenger = ScaffoldMessenger.of(context);

  if (!await _confirm(context, title: l10n.feedDeleteCommentTitle, body: l10n.feedDeleteBody, confirm: l10n.feedDeleteConfirm)) return false;

  final failure = await ref.read(feedPostControllerProvider(postId).notifier).deleteComment(commentId);

  messenger.showSnackBar(SnackBar(content: Text(failure == null ? l10n.feedDeleted : feedFailureText(l10n, failure))));

  return failure == null;
}

/// Asks for one of the five reasons, then tells the moderators. Reporting twice is harmless.
Future<void> reportFlow(BuildContext context, WidgetRef ref, {required String id, required bool isComment}) async {
  final l10n = AppLocalizations.of(context);
  final messenger = ScaffoldMessenger.of(context);
  final labels = {
    'spam': l10n.feedReportSpam,
    'harassment': l10n.feedReportHarassment,
    'self_harm': l10n.feedReportSelfHarm,
    'inappropriate': l10n.feedReportInappropriate,
    'other': l10n.feedReportOther,
  };

  final reason = await showModalBottomSheet<String>(
    context: context,
    showDragHandle: true,
    useSafeArea: true,
    isScrollControlled: true,
    builder: (sheet) => SafeArea(
      child: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(AppSpacing.xl, 0, AppSpacing.xl, AppSpacing.s),
            child: Semantics(header: true, child: Text(l10n.feedReportTitle, style: Theme.of(sheet).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
          ),
          for (final reason in feedReportReasons)
            ListTile(minTileHeight: AppSizes.minTouch, title: Text(labels[reason]!), onTap: () => Navigator.of(sheet).pop(reason)),
        ]),
      ),
    ),
  );

  if (reason == null) return;

  try {
    await ref.read(feedApiProvider).report(id: id, isComment: isComment, reason: reason);
    messenger.showSnackBar(SnackBar(content: Text(l10n.feedReported)));
  } on AppFailure catch (failure) {
    messenger.showSnackBar(SnackBar(content: Text(feedFailureText(l10n, failure))));
  }
}

/// Asks, then hides the post's author from this person's feed. True when done; the feed is then asked again.
Future<bool> muteFlow(BuildContext context, WidgetRef ref, FeedPost post) async {
  final l10n = AppLocalizations.of(context);
  final messenger = ScaffoldMessenger.of(context);

  if (!await _confirm(context, title: l10n.feedMuteTitle(post.author.name), body: l10n.feedMuteBody, confirm: l10n.feedMuteConfirm)) return false;

  try {
    await ref.read(feedApiProvider).muteAuthor(post.id);
  } on AppFailure catch (failure) {
    messenger.showSnackBar(SnackBar(content: Text(feedFailureText(l10n, failure))));

    return false;
  }

  messenger.showSnackBar(SnackBar(content: Text(l10n.feedMutedDone(post.author.name))));
  ref.read(feedControllerProvider.notifier).refresh();

  return true;
}

/// After a post or comment the server flagged: it IS shared, and this calm card says where to get help. Never blocks anything.
Future<void> showFeedCrisisSheet(BuildContext context, List<Helpline> helplines) {
  final l10n = AppLocalizations.of(context);

  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (sheet) {
      final scheme = Theme.of(sheet).colorScheme;
      final text = Theme.of(sheet).textTheme;

      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(AppSpacing.xl, 0, AppSpacing.xl, AppSpacing.xl),
        child: Semantics(
          container: true,
          liveRegion: true,
          child: Container(
            padding: const EdgeInsets.all(AppSpacing.l),
            decoration: BoxDecoration(color: scheme.crisisFill, borderRadius: AppRadii.cardRadius, border: Border.all(color: scheme.crisisBorder)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: [
                const Icon(Icons.favorite_border),
                const SizedBox(width: AppSpacing.s),
                Expanded(child: Semantics(header: true, child: Text(l10n.crisisTitle, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800)))),
              ]),
              const SizedBox(height: AppSpacing.s),
              Text(l10n.crisisBody),
              const SizedBox(height: AppSpacing.m),
              HelplineButtons(lines: helplines.isEmpty ? fallbackHelplines : helplines),
              Text(l10n.crisisEmergency, style: text.bodyMedium),
              const SizedBox(height: AppSpacing.m),
              OutlinedButton(
                onPressed: () {
                  Navigator.of(sheet).pop();
                  context.go(AppRoutes.counselors);
                },
                child: Text(l10n.crisisFindCounselor),
              ),
              const SizedBox(height: AppSpacing.s),
              TextButton(onPressed: () => Navigator.of(sheet).pop(), child: Text(l10n.feedCrisisContinue)),
            ]),
          ),
        ),
      );
    },
  );
}
