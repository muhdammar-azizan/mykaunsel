import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/feed.dart';
import 'feed_controller.dart';
import 'feed_support.dart';
import 'new_post_screen.dart' show RuneLimitFormatter, feedMaxPostChars;
import 'post_card.dart';

/// One post in full, its comments oldest first, and the comment box that stays at the bottom.
class PostScreen extends ConsumerStatefulWidget {
  const PostScreen({super.key, required this.id});

  final String id;

  @override
  ConsumerState<PostScreen> createState() => _PostScreenState();
}

class _PostScreenState extends ConsumerState<PostScreen> {
  final _input = TextEditingController();

  /// Why the last comment did not go. Shown above the box (a pop-up message would cover the send button).
  String? _sendError;

  @override
  void dispose() {
    _input.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    setState(() => _sendError = null);

    final outcome = await ref.read(feedPostControllerProvider(widget.id).notifier).addComment(_input.text);

    if (!mounted) return;

    if (outcome.failure != null) {
      // The words stay in the box, so sending again is one tap.
      setState(() => _sendError = feedFailureText(AppLocalizations.of(context), outcome.failure!));
      return;
    }

    if (_input.text.trim().isEmpty) return;

    _input.clear();

    if (outcome.crisis) await showFeedCrisisSheet(context, outcome.helplines);
  }

  Future<void> _like() async {
    final failure = await ref.read(feedPostControllerProvider(widget.id).notifier).toggleLike();

    if (failure != null && mounted) showFeedFailure(context, failure);
  }

  Future<void> _commentMenu(FeedComment comment) async {
    final action = await showFeedMenu(context, mine: comment.mine, isComment: true);

    if (action == null || !mounted) return;

    if (action == FeedMenuAction.delete) {
      await confirmDeleteComment(context, ref, widget.id, comment.id);
    } else if (action == FeedMenuAction.report) {
      await reportFlow(context, ref, id: comment.id, isComment: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final state = ref.watch(feedPostControllerProvider(widget.id));
    final controller = ref.read(feedPostControllerProvider(widget.id).notifier);
    final post = state.post;

    Widget body;

    if (state.gone) {
      body = _Notice(icon: Icons.info_outline, title: l10n.feedPostGoneTitle, body: l10n.feedPostGoneBody);
    } else if (post == null && state.loading) {
      body = const Center(child: CircularProgressIndicator());
    } else if (post == null) {
      body = _Notice(
        icon: Icons.cloud_off_outlined,
        title: l10n.somethingWentWrongTitle,
        body: state.failure == null ? l10n.somethingWentWrongBody : feedFailureText(l10n, state.failure!),
        action: FilledButton(onPressed: controller.load, child: Text(l10n.tryAgain)),
      );
    } else {
      body = Column(children: [
        Expanded(child: RefreshIndicator(onRefresh: controller.load, child: _Thread(post: post, state: state, onLike: _like, onCommentMenu: _commentMenu))),
        _Composer(controller: _input, sending: state.sending, error: _sendError, onSend: _send),
      ]);
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.feedPostTitle),
        actions: [
          if (post != null && !state.gone)
            IconButton(
              tooltip: l10n.feedPostOptions,
              constraints: const BoxConstraints(minWidth: AppSizes.minTouch, minHeight: AppSizes.minTouch),
              icon: const Icon(Icons.more_horiz),
              onPressed: () => openPostMenu(context, ref, post, afterGone: () => context.pop()),
            ),
        ],
      ),
      body: SafeArea(child: body),
    );
  }
}

class _Thread extends StatelessWidget {
  const _Thread({required this.post, required this.state, required this.onLike, required this.onCommentMenu});

  final FeedPost post;
  final PostPageState state;
  final VoidCallback onLike;
  final void Function(FeedComment) onCommentMenu;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;

    return Consumer(
      builder: (context, ref, _) {
        final controller = ref.read(feedPostControllerProvider(post.id).notifier);

        return ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSpacing.l),
          children: [
            AuthorLine(author: post.author, at: post.createdAt, edited: post.edited),
            const SizedBox(height: AppSpacing.s),
            SelectableText(post.text, style: text.bodyLarge),
            const SizedBox(height: AppSpacing.xs),
            Wrap(children: [LikeButton(post: post, onPressed: onLike), CommentCount(count: post.commentsCount, onPressed: () {})]),
            const Divider(),
            Semantics(header: true, child: Text(l10n.feedCommentsHeader, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800))),
            const SizedBox(height: AppSpacing.s),
            if (state.commentsLoading)
              const Padding(padding: EdgeInsets.all(AppSpacing.l), child: Center(child: CircularProgressIndicator()))
            else if (state.commentsFailure != null && state.comments.isEmpty)
              Column(children: [Text(feedFailureText(l10n, state.commentsFailure!)), TextButton(onPressed: controller.load, child: Text(l10n.tryAgain))])
            else if (state.comments.isEmpty)
              Padding(padding: const EdgeInsets.symmetric(vertical: AppSpacing.l), child: Text(l10n.feedNoComments))
            else
              for (final comment in state.comments) _CommentTile(key: ValueKey(comment.id), comment: comment, onMenu: () => onCommentMenu(comment)),
            if (state.loadingMoreComments) const Padding(padding: EdgeInsets.all(AppSpacing.l), child: Center(child: CircularProgressIndicator())),
            if (state.hasMoreComments && !state.loadingMoreComments)
              Align(alignment: Alignment.centerLeft, child: TextButton(onPressed: controller.loadMoreComments, child: Text(state.commentsFailure != null ? l10n.tryAgain : l10n.feedShowMoreComments))),
          ],
        );
      },
    );
  }
}

class _CommentTile extends StatelessWidget {
  const _CommentTile({super.key, required this.comment, required this.onMenu});

  final FeedComment comment;
  final VoidCallback onMenu;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.s),
      child: DecoratedBox(
        decoration: BoxDecoration(color: scheme.cardFill, borderRadius: AppRadii.cardRadius, border: Border.all(color: scheme.cardBorder)),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(AppSpacing.l, AppSpacing.s, AppSpacing.xs, AppSpacing.m),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Padding(padding: const EdgeInsets.only(top: AppSpacing.s), child: AuthorLine(author: comment.author, at: comment.createdAt)),
                const SizedBox(height: AppSpacing.xs),
                SelectableText(comment.text),
              ]),
            ),
            IconButton(
              tooltip: l10n.feedCommentOptions,
              constraints: const BoxConstraints(minWidth: AppSizes.minTouch, minHeight: AppSizes.minTouch),
              icon: const Icon(Icons.more_horiz),
              onPressed: onMenu,
            ),
          ]),
        ),
      ),
    );
  }
}

/// The comment box. It stays at the bottom; the words stay in it when sending fails.
class _Composer extends StatelessWidget {
  const _Composer({required this.controller, required this.sending, required this.onSend, this.error});

  final TextEditingController controller;
  final bool sending;
  final String? error;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;

    return Material(
      color: scheme.surface,
      elevation: 3,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(AppSpacing.l, AppSpacing.s, AppSpacing.s, AppSpacing.s),
        child: ListenableBuilder(
          listenable: controller,
          builder: (context, _) {
            final words = controller.text.trim();
            final ready = words.isNotEmpty && words.runes.length <= feedMaxPostChars && !sending;

            return Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              if (error != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.s),
                  child: Semantics(liveRegion: true, child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Icon(Icons.error_outline, size: 20, color: scheme.error),
                    const SizedBox(width: AppSpacing.s),
                    Expanded(child: Text(error!, style: TextStyle(color: scheme.error))),
                  ])),
                ),
              Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Expanded(
                child: TextField(
                  controller: controller,
                  minLines: 1,
                  maxLines: 4,
                  textCapitalization: TextCapitalization.sentences,
                  keyboardType: TextInputType.multiline,
                  decoration: InputDecoration(hintText: l10n.feedCommentHint, counterText: controller.text.runes.length > feedMaxPostChars - 100 ? l10n.feedCharCount(controller.text.runes.length, feedMaxPostChars) : ''),
                  inputFormatters: const [RuneLimitFormatter(feedMaxPostChars)],
                ),
              ),
              sending
                  ? const Padding(padding: EdgeInsets.all(AppSpacing.m), child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 3)))
                  : IconButton(
                      tooltip: l10n.feedSendComment,
                      constraints: const BoxConstraints(minWidth: AppSizes.minTouch, minHeight: AppSizes.minTouch),
                      icon: const Icon(Icons.send),
                      onPressed: ready ? onSend : null,
                    ),
              ]),
            ]);
          },
        ),
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.icon, required this.title, required this.body, this.action});

  final IconData icon;
  final String title;
  final String body;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, size: 48, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: AppSpacing.m),
          Semantics(header: true, child: Text(title, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
          const SizedBox(height: AppSpacing.s),
          Text(body, textAlign: TextAlign.center),
          if (action != null) ...[const SizedBox(height: AppSpacing.l), action!],
        ]),
      ),
    );
  }
}
