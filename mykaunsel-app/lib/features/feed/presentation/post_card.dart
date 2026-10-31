import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/feed.dart';
import 'feed_controller.dart';
import 'feed_support.dart';

/// The post's "more" menu: the owner edits or deletes, anyone else reports or mutes the author. [afterGone] runs when the post is no longer
/// there for this person (deleted, or its author muted), so the post page can close.
Future<void> openPostMenu(BuildContext context, WidgetRef ref, FeedPost post, {VoidCallback? afterGone}) async {
  final action = await showFeedMenu(context, mine: post.mine, isComment: false, authorName: post.author.name);

  if (action == null || !context.mounted) return;

  switch (action) {
    case FeedMenuAction.edit:
      await context.push(AppRoutes.newPost, extra: post);
    case FeedMenuAction.delete:
      if (await confirmDeletePost(context, ref, post.id)) afterGone?.call();
    case FeedMenuAction.report:
      await reportFlow(context, ref, id: post.id, isComment: false);
    case FeedMenuAction.mute:
      if (await muteFlow(context, ref, post)) afterGone?.call();
  }
}

/// One post in the feed. The whole card opens the post; the like, the comment count and the menu are their own buttons (each at least 44 tall).
class PostCard extends ConsumerWidget {
  const PostCard({super.key, required this.post});

  final FeedPost post;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.s),
      child: Material(
        color: scheme.cardFill,
        shape: RoundedRectangleBorder(borderRadius: AppRadii.cardRadius, side: BorderSide(color: scheme.cardBorder)),
        child: InkWell(
          borderRadius: AppRadii.cardRadius,
          onTap: () => context.push(AppRoutes.feedPostPath(post.id)),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(AppSpacing.l, AppSpacing.m, AppSpacing.xs, AppSpacing.xs),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(child: Padding(padding: const EdgeInsets.only(top: AppSpacing.s), child: AuthorLine(author: post.author, at: post.createdAt, edited: post.edited))),
                    IconButton(
                      tooltip: l10n.feedPostOptions,
                      constraints: const BoxConstraints(minWidth: AppSizes.minTouch, minHeight: AppSizes.minTouch),
                      icon: const Icon(Icons.more_horiz),
                      onPressed: () => openPostMenu(context, ref, post),
                    ),
                  ],
                ),
                Padding(padding: const EdgeInsets.only(right: AppSpacing.l, top: AppSpacing.xs, bottom: AppSpacing.xs), child: Text(post.text, style: text.bodyLarge)),
                Wrap(children: [LikeButton(post: post, onPressed: () => _like(context, ref)), CommentCount(count: post.commentsCount, onPressed: () => context.push(AppRoutes.feedPostPath(post.id)))]),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _like(BuildContext context, WidgetRef ref) async {
    final failure = await ref.read(feedControllerProvider.notifier).toggleLike(post.id);

    if (failure != null && context.mounted) showFeedFailure(context, failure);
  }
}

/// A heart with the count. Filled when liked AND said aloud as "on", so it never depends on colour.
class LikeButton extends StatelessWidget {
  const LikeButton({super.key, required this.post, required this.onPressed});

  final FeedPost post;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final mine = post.reactions.mine;
    final count = l10n.feedLikesCount(post.reactions.count);

    return Semantics(
      button: true,
      toggled: mine,
      excludeSemantics: true,
      label: '${mine ? l10n.feedUnlike : l10n.feedLike}, $count',
      onTap: onPressed,
      child: InkWell(
        borderRadius: AppRadii.chipRadius,
        onTap: onPressed,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: AppSizes.minTouch, minWidth: AppSizes.minTouch),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.m),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Icon(mine ? Icons.favorite : Icons.favorite_border, size: 20),
              const SizedBox(width: AppSpacing.xs),
              Text(count),
            ]),
          ),
        ),
      ),
    );
  }
}

class CommentCount extends StatelessWidget {
  const CommentCount({super.key, required this.count, required this.onPressed});

  final int count;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final label = AppLocalizations.of(context).feedCommentsCount(count);

    return Semantics(
      button: true,
      excludeSemantics: true,
      label: label,
      onTap: onPressed,
      child: InkWell(
        borderRadius: AppRadii.chipRadius,
        onTap: onPressed,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: AppSizes.minTouch, minWidth: AppSizes.minTouch),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.m),
            child: Row(mainAxisSize: MainAxisSize.min, children: [const Icon(Icons.chat_bubble_outline, size: 20), const SizedBox(width: AppSpacing.xs), Text(label)]),
          ),
        ),
      ),
    );
  }
}
