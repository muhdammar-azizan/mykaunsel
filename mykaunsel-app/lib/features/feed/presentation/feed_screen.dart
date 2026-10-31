import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure_message.dart';
import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import 'feed_controller.dart';
import 'post_card.dart';

/// The Feed tab: everyone's posts, newest first, under their own names. Pull to refresh; more load as you reach the end.
class FeedScreen extends ConsumerStatefulWidget {
  const FeedScreen({super.key});

  @override
  ConsumerState<FeedScreen> createState() => _FeedScreenState();
}

class _FeedScreenState extends ConsumerState<FeedScreen> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.hasClients && _scroll.position.extentAfter < 300) ref.read(feedControllerProvider.notifier).loadMore();
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
    final state = ref.watch(feedControllerProvider);
    final controller = ref.read(feedControllerProvider.notifier);

    // A short first page may not fill the screen, so nothing could be scrolled to load the next one.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && _scroll.hasClients && _scroll.position.hasContentDimensions && _scroll.position.maxScrollExtent <= 0 && state.hasMore && !state.loadingMore && state.failure == null) controller.loadMore();
    });

    Widget body;

    if (state.loading) {
      body = const Center(child: CircularProgressIndicator());
    } else if (state.failure != null && state.items.isEmpty) {
      body = _Message(
        icon: Icons.cloud_off_outlined,
        title: l10n.somethingWentWrongTitle,
        body: failureMessage(l10n, state.failure!),
        action: FilledButton(onPressed: controller.refresh, child: Text(l10n.tryAgain)),
      );
    } else if (state.items.isEmpty) {
      body = RefreshIndicator(
        onRefresh: controller.refresh,
        child: LayoutBuilder(
          builder: (context, box) => SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            child: SizedBox(
              height: box.maxHeight,
              child: _Message(
                icon: Icons.forum_outlined,
                title: l10n.feedEmptyTitle,
                body: l10n.feedEmptyBody,
                action: FilledButton(onPressed: () => context.push(AppRoutes.newPost), child: Text(l10n.feedNewPost)),
              ),
            ),
          ),
        ),
      );
    } else {
      body = RefreshIndicator(
        onRefresh: controller.refresh,
        child: ListView(
          controller: _scroll,
          physics: const AlwaysScrollableScrollPhysics(),
          // Room under the last post for the "New post" button.
          padding: const EdgeInsets.fromLTRB(AppSpacing.l, AppSpacing.l, AppSpacing.l, 96),
          children: [
            for (final post in state.items) PostCard(key: ValueKey(post.id), post: post),
            if (state.loadingMore) const Padding(padding: EdgeInsets.all(AppSpacing.l), child: Center(child: CircularProgressIndicator())),
            if (state.failure != null) Column(children: [Text(l10n.feedLoadMoreFailed), TextButton(onPressed: controller.loadMore, child: Text(l10n.tryAgain))]),
          ],
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.feedTitle),
        actions: [
          IconButton(
            tooltip: l10n.feedMutedPeople,
            constraints: const BoxConstraints(minWidth: AppSizes.minTouch, minHeight: AppSizes.minTouch),
            icon: const Icon(Icons.volume_off_outlined),
            onPressed: () => context.push(AppRoutes.feedMuted),
          ),
        ],
      ),
      body: SafeArea(child: body),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push(AppRoutes.newPost),
        icon: const Icon(Icons.edit_outlined),
        label: Text(l10n.feedNewPost),
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
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, size: 48, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: AppSpacing.m),
          Semantics(header: true, child: Text(title, textAlign: TextAlign.center, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
          const SizedBox(height: AppSpacing.s),
          Text(body, textAlign: TextAlign.center),
          if (action != null) ...[const SizedBox(height: AppSpacing.l), action!],
        ]),
      ),
    );
  }
}
