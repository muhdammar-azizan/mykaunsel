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
import '../domain/chat.dart';

class ChatHistoryState {
  const ChatHistoryState({this.items = const [], this.page = 0, this.lastPage = 1, this.loading = true, this.loadingMore = false, this.failure});

  final List<ChatSessionInfo> items;
  final int page;
  final int lastPage;
  final bool loading;
  final bool loadingMore;
  final AppFailure? failure;

  bool get hasMore => page < lastPage;

  ChatHistoryState copyWith({List<ChatSessionInfo>? items, int? page, int? lastPage, bool? loading, bool? loadingMore, AppFailure? failure, bool clearFailure = false}) =>
      ChatHistoryState(
        items: items ?? this.items,
        page: page ?? this.page,
        lastPage: lastPage ?? this.lastPage,
        loading: loading ?? this.loading,
        loadingMore: loadingMore ?? this.loadingMore,
        failure: clearFailure ? null : (failure ?? this.failure),
      );
}

/// The person's own past conversations, newest first. Read only. Starts from nothing for the next person.
final chatHistoryControllerProvider = NotifierProvider.autoDispose<ChatHistoryController, ChatHistoryState>(ChatHistoryController.new);

/// No automatic retry: a failure is shown with "Try again".
final chatSessionDetailProvider = FutureProvider.autoDispose.family<ChatSessionDetail, String>(
  (ref, id) {
    ref.watch(currentUserIdProvider);

    return ref.read(chatApiProvider).detail(id);
  },
  retry: (_, _) => null,
);

class ChatHistoryController extends Notifier<ChatHistoryState> {
  int _ticket = 0;

  @override
  ChatHistoryState build() {
    final userId = ref.watch(currentUserIdProvider);
    _ticket++;

    if (userId == null) return const ChatHistoryState(loading: false);

    Future.microtask(() => _fetch(1, reset: true));

    return const ChatHistoryState();
  }

  Future<void> refresh() => _fetch(1, reset: true);

  Future<void> loadMore() async {
    if (!state.hasMore || state.loading || state.loadingMore) return;

    await _fetch(state.page + 1, reset: false);
  }

  Future<void> _fetch(int page, {required bool reset}) async {
    if (reset) _ticket++;

    final ticket = _ticket;

    state = reset ? state.copyWith(loading: state.items.isEmpty, loadingMore: false, clearFailure: true) : state.copyWith(loadingMore: true, clearFailure: true);

    try {
      final result = await ref.read(chatApiProvider).history(page: page);

      if (!ref.mounted || ticket != _ticket) return;

      final known = reset ? <String>{} : state.items.map((s) => s.id).toSet();

      state = state.copyWith(
        items: [...(reset ? const <ChatSessionInfo>[] : state.items), ...result.items.where((s) => known.add(s.id))],
        page: result.page,
        lastPage: result.lastPage,
        loading: false,
        loadingMore: false,
      );
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) return;

      state = state.copyWith(loading: false, loadingMore: false, failure: failure);
    }
  }
}

String emotionLabel(AppLocalizations l10n, String key) => switch (key) {
      'sadness' => l10n.chatEmotionSadness,
      'anxiety' => l10n.chatEmotionAnxiety,
      'stress' => l10n.chatEmotionStress,
      'anger' => l10n.chatEmotionAnger,
      'loneliness' => l10n.chatEmotionLoneliness,
      'fear' => l10n.chatEmotionFear,
      'guilt' => l10n.chatEmotionGuilt,
      'hope' => l10n.chatEmotionHope,
      'calm' => l10n.chatEmotionCalm,
      'joy' => l10n.chatEmotionJoy,
      'neutral' => l10n.chatEmotionNeutral,
      _ => key, // a feeling this version of the app has no word for yet: shown as the server sent it
    };

/// Past conversations grouped Today, This week, Earlier (Malaysian time). Read only: no delete.
class ChatHistoryScreen extends ConsumerStatefulWidget {
  const ChatHistoryScreen({super.key});

  @override
  ConsumerState<ChatHistoryScreen> createState() => _ChatHistoryScreenState();
}

class _ChatHistoryScreenState extends ConsumerState<ChatHistoryScreen> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.hasClients && _scroll.position.extentAfter < 300) ref.read(chatHistoryControllerProvider.notifier).loadMore();
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
    final state = ref.watch(chatHistoryControllerProvider);
    final controller = ref.read(chatHistoryControllerProvider.notifier);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final now = ref.watch(clockProvider)();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && _scroll.hasClients && _scroll.position.maxScrollExtent <= 0 && state.hasMore && !state.loadingMore && state.failure == null) controller.loadMore();
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
            child: SizedBox(height: box.maxHeight, child: _Message(icon: Icons.history, title: l10n.chatHistoryEmptyTitle, body: l10n.chatHistoryEmptyBody)),
          ),
        ),
      );
    } else {
      final rows = <Widget>[];
      String? group;

      for (final session in state.items) {
        final label = _group(l10n, session.startedAt, now);

        if (label != group) {
          group = label;
          rows.add(Padding(padding: const EdgeInsets.fromLTRB(0, AppSpacing.m, 0, AppSpacing.xs), child: Semantics(header: true, child: Text(label, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)))));
        }

        rows.add(_SessionTile(session: session, locale: locale));
      }

      body = RefreshIndicator(
        onRefresh: controller.refresh,
        child: ListView(
          controller: _scroll,
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(AppSpacing.l),
          children: [
            ...rows,
            if (state.loadingMore) const Padding(padding: EdgeInsets.all(AppSpacing.l), child: Center(child: CircularProgressIndicator())),
            if (state.failure != null) Column(children: [Text(l10n.bookingsLoadMoreFailed), TextButton(onPressed: controller.loadMore, child: Text(l10n.tryAgain))]),
          ],
        ),
      );
    }

    return Scaffold(appBar: AppBar(title: Text(l10n.chatHistoryTitle)), body: SafeArea(child: body));
  }

  String _group(AppLocalizations l10n, DateTime startedAt, DateTime now) {
    final day = malaysiaWallClock(startedAt);
    final today = malaysiaWallClock(now);
    final days = DateTime.utc(today.year, today.month, today.day).difference(DateTime.utc(day.year, day.month, day.day)).inDays;

    if (days <= 0) return l10n.chatGroupToday;
    if (days < 7) return l10n.chatGroupWeek;

    return l10n.chatGroupEarlier;
  }
}

class _SessionTile extends StatelessWidget {
  const _SessionTile({required this.session, required this.locale});

  final ChatSessionInfo session;
  final String locale;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final when = '${DateFormat('EEE d MMM', locale).format(malaysiaWallClock(session.startedAt))}, ${formatSlotTime(session.startedAt, locale: locale)}';
    final preview = (session.preview ?? '').isEmpty ? l10n.chatConversationTitle : session.preview!;
    final feeling = session.dominantEmotion == null ? null : l10n.chatEmotionMostly(emotionLabel(l10n, session.dominantEmotion!));

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.s),
      child: Material(
        color: scheme.cardFill,
        shape: RoundedRectangleBorder(borderRadius: AppRadii.cardRadius, side: BorderSide(color: scheme.cardBorder)),
        child: InkWell(
          borderRadius: AppRadii.cardRadius,
          onTap: () => context.push(AppRoutes.chatSessionPath(session.id)),
          child: Semantics(
            button: true,
            excludeSemantics: true,
            label: [preview, when, ?feeling].join(', '),
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: AppSizes.minTouch),
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.l),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(preview, maxLines: 2, overflow: TextOverflow.ellipsis, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                    const SizedBox(height: AppSpacing.xs),
                    Text(when, style: text.bodySmall?.copyWith(color: scheme.mutedText)),
                    if (feeling != null) Padding(padding: const EdgeInsets.only(top: AppSpacing.xs), child: Text(feeling, style: text.bodySmall)),
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

/// One finished conversation, to read. Nothing can be typed or deleted here.
class ChatSessionScreen extends ConsumerWidget {
  const ChatSessionScreen({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final detail = ref.watch(chatSessionDetailProvider(id));
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.chatConversationTitle)),
      body: SafeArea(
        child: detail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) {
            final failure = error is AppFailure ? error : const ServerFailure();

            return _Message(
              icon: Icons.cloud_off_outlined,
              title: l10n.somethingWentWrongTitle,
              body: failure is NotFoundFailure ? l10n.chatNotFound : failureMessage(l10n, failure),
              action: failure is NotFoundFailure ? null : FilledButton(onPressed: () => ref.invalidate(chatSessionDetailProvider(id)), child: Text(l10n.tryAgain)),
            );
          },
          data: (conversation) => ListView(
            padding: const EdgeInsets.all(AppSpacing.l),
            children: [
              Text(l10n.chatReadOnly, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: scheme.mutedText)),
              if (conversation.session.dominantEmotion != null)
                Padding(
                  padding: const EdgeInsets.only(top: AppSpacing.xs),
                  child: Text(l10n.chatEmotionMostly(emotionLabel(l10n, conversation.session.dominantEmotion!)), style: Theme.of(context).textTheme.bodySmall),
                ),
              const SizedBox(height: AppSpacing.m),
              for (final message in conversation.messages) _ReadBubble(message: message),
            ],
          ),
        ),
      ),
    );
  }
}

class _ReadBubble extends StatelessWidget {
  const _ReadBubble({required this.message});

  final ChatMessage message;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final mine = message.sender == ChatSender.user;

    return Align(
      alignment: mine ? AlignmentDirectional.centerEnd : AlignmentDirectional.centerStart,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .85),
          child: Semantics(
            container: true,
            label: mine ? l10n.chatYouSaid(message.text) : l10n.chatCompanionSaid(message.text),
            excludeSemantics: true,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.m, vertical: AppSpacing.s + 2),
              decoration: BoxDecoration(color: mine ? scheme.primary : scheme.cardFill, border: mine ? null : Border.all(color: scheme.cardBorder), borderRadius: AppRadii.cardRadius),
              child: SelectableText(message.text, style: TextStyle(color: mine ? scheme.onPrimary : scheme.onSurface)),
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
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xxl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48),
            const SizedBox(height: AppSpacing.m),
            Semantics(header: true, child: Text(title, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
            const SizedBox(height: AppSpacing.s),
            Text(body, textAlign: TextAlign.center),
            if (action != null) ...[const SizedBox(height: AppSpacing.l), action!],
          ],
        ),
      ),
    );
  }
}
