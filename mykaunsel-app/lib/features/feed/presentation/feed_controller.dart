import 'dart:math' as math;

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../booking/presentation/confirm_booking_controller.dart' show idempotencyKeyFactoryProvider;
import '../../chat/domain/chat.dart';
import '../data/feed_api.dart';
import '../domain/feed.dart';

/// What happened to something the person wrote. When [failure] is null it was stored; [crisis] then says the server thinks the words may mean
/// the person is in danger (the app shows where to get help; the writing is kept either way).
class FeedSendOutcome {
  const FeedSendOutcome({this.failure, this.crisis = false, this.helplines = const []});

  final AppFailure? failure;
  final bool crisis;
  final List<Helpline> helplines;

  bool get ok => failure == null;
}

class FeedState {
  const FeedState({this.items = const [], this.nextCursor, this.loading = true, this.loadingMore = false, this.failure});

  final List<FeedPost> items;
  final String? nextCursor;

  /// The first page is on its way and nothing is shown yet.
  final bool loading;
  final bool loadingMore;
  final AppFailure? failure;

  bool get hasMore => nextCursor != null;

  FeedState copyWith({List<FeedPost>? items, String? nextCursor, bool clearCursor = false, bool? loading, bool? loadingMore, AppFailure? failure, bool clearFailure = false}) =>
      FeedState(
        items: items ?? this.items,
        nextCursor: clearCursor ? null : (nextCursor ?? this.nextCursor),
        loading: loading ?? this.loading,
        loadingMore: loadingMore ?? this.loadingMore,
        failure: clearFailure ? null : (failure ?? this.failure),
      );
}

/// The feed, newest first. It lives as long as the signed-in person does (leaving the tab keeps the place) and starts from nothing for the next.
final feedControllerProvider = NotifierProvider<FeedController, FeedState>(FeedController.new);

class FeedController extends Notifier<FeedState> {
  int _ticket = 0;
  final _liking = <String>{};

  @override
  FeedState build() {
    final userId = ref.watch(currentUserIdProvider);
    _ticket++;

    if (userId == null) return const FeedState(loading: false);

    Future.microtask(refresh);

    return const FeedState();
  }

  Future<void> refresh() => _fetch(null);

  Future<void> loadMore() async {
    if (!state.hasMore || state.loading || state.loadingMore) return;

    await _fetch(state.nextCursor);
  }

  Future<void> _fetch(String? cursor) async {
    final reset = cursor == null;

    if (reset) _ticket++;

    final ticket = _ticket;

    state = reset ? state.copyWith(loading: state.items.isEmpty, loadingMore: false, clearFailure: true) : state.copyWith(loadingMore: true, clearFailure: true);

    try {
      final page = await ref.read(feedApiProvider).feed(cursor: cursor);

      if (!ref.mounted || ticket != _ticket) return;

      final known = reset ? <String>{} : state.items.map((p) => p.id).toSet();

      state = state.copyWith(
        items: [...(reset ? const <FeedPost>[] : state.items), ...page.items.where((p) => known.add(p.id))],
        nextCursor: page.nextCursor,
        clearCursor: page.nextCursor == null,
        loading: false,
        loadingMore: false,
      );
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) return;

      state = state.copyWith(loading: false, loadingMore: false, failure: failure);
    }
  }

  /// A post the person just wrote goes to the top without asking the server again.
  void prepend(FeedPost post) => state = state.copyWith(items: [post, ...state.items.where((p) => p.id != post.id)], loading: false);

  void replace(FeedPost post) => state = state.copyWith(items: [for (final p in state.items) p.id == post.id ? post : p]);

  void remove(String id) => state = state.copyWith(items: state.items.where((p) => p.id != id).toList());

  /// Likes or takes the like back. The heart answers at once and goes back if the server says no; null means it worked.
  Future<AppFailure?> toggleLike(String id) async {
    final post = state.items.where((p) => p.id == id).firstOrNull;

    if (post == null || !_liking.add(id)) return null;

    try {
      return await likeWith(ref, post, replace);
    } finally {
      _liking.remove(id);
    }
  }
}

/// The part of liking both the feed and the post page share: shows the new heart at once, asks the server, and shows the server's own count.
Future<AppFailure?> likeWith(Ref ref, FeedPost post, void Function(FeedPost) show) async {
  final want = !post.reactions.mine;

  show(post.copyWith(reactions: FeedReactions(count: math.max(0, post.reactions.count + (want ? 1 : -1)), mine: want)));

  try {
    final reactions = await ref.read(feedApiProvider).react(post.id, on: want);

    if (ref.mounted) show(post.copyWith(reactions: reactions));

    return null;
  } on AppFailure catch (failure) {
    if (ref.mounted) show(post); // the heart goes back

    return failure;
  }
}

class PostPageState {
  const PostPageState({
    this.post,
    this.loading = true,
    this.failure,
    this.gone = false,
    this.comments = const [],
    this.nextCursor,
    this.commentsLoading = true,
    this.loadingMoreComments = false,
    this.commentsFailure,
    this.sending = false,
  });

  final FeedPost? post;

  /// Nothing to show yet (neither the post from the feed nor the server's answer).
  final bool loading;
  final AppFailure? failure;

  /// The server says the post is not there (any more): deleted, removed, or never there.
  final bool gone;
  final List<FeedComment> comments;
  final String? nextCursor;
  final bool commentsLoading;
  final bool loadingMoreComments;
  final AppFailure? commentsFailure;
  final bool sending;

  bool get hasMoreComments => nextCursor != null;

  PostPageState copyWith({
    FeedPost? post,
    bool? loading,
    AppFailure? failure,
    bool clearFailure = false,
    bool? gone,
    List<FeedComment>? comments,
    String? nextCursor,
    bool clearCursor = false,
    bool? commentsLoading,
    bool? loadingMoreComments,
    AppFailure? commentsFailure,
    bool clearCommentsFailure = false,
    bool? sending,
  }) =>
      PostPageState(
        post: post ?? this.post,
        loading: loading ?? this.loading,
        failure: clearFailure ? null : (failure ?? this.failure),
        gone: gone ?? this.gone,
        comments: comments ?? this.comments,
        nextCursor: clearCursor ? null : (nextCursor ?? this.nextCursor),
        commentsLoading: commentsLoading ?? this.commentsLoading,
        loadingMoreComments: loadingMoreComments ?? this.loadingMoreComments,
        commentsFailure: clearCommentsFailure ? null : (commentsFailure ?? this.commentsFailure),
        sending: sending ?? this.sending,
      );
}

/// One open post with its comments (oldest first). Leaving the page throws it away.
final feedPostControllerProvider = NotifierProvider.autoDispose.family<FeedPostController, PostPageState, String>(FeedPostController.new);

class FeedPostController extends Notifier<PostPageState> {
  FeedPostController(this.id);

  final String id;
  int _ticket = 0;
  bool _liking = false;

  // A comment that did not go is sent again with the SAME client id (nothing is stored twice), but only while the words are the same.
  String? _pendingText;
  String? _pendingKey;

  @override
  PostPageState build() {
    final userId = ref.watch(currentUserIdProvider);
    _ticket++;

    if (userId == null) return const PostPageState(loading: false, commentsLoading: false);

    final known = ref.read(feedControllerProvider).items.where((p) => p.id == id).firstOrNull;

    Future.microtask(load);

    return PostPageState(post: known, loading: known == null);
  }

  Future<void> load() async {
    final ticket = ++_ticket;
    final api = ref.read(feedApiProvider);

    state = state.copyWith(loading: state.post == null, commentsLoading: true, clearFailure: true, clearCommentsFailure: true, gone: false);

    final postDone = _loadPost(api, ticket);
    final commentsDone = _loadComments(api, ticket, null);

    await Future.wait([postDone, commentsDone]);
  }

  Future<void> _loadPost(FeedApi api, int ticket) async {
    try {
      final post = await api.post(id);

      if (!ref.mounted || ticket != _ticket) return;

      state = state.copyWith(post: post, loading: false);
      ref.read(feedControllerProvider.notifier).replace(post);
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) return;

      if (failure is NotFoundFailure) {
        ref.read(feedControllerProvider.notifier).remove(id);
        state = state.copyWith(loading: false, gone: true);
      } else {
        state = state.copyWith(loading: false, failure: state.post == null ? failure : null);
      }
    }
  }

  Future<void> _loadComments(FeedApi api, int ticket, String? cursor) async {
    final more = cursor != null;

    if (more) state = state.copyWith(loadingMoreComments: true, clearCommentsFailure: true);

    try {
      final page = await api.comments(id, cursor: cursor);

      if (!ref.mounted || ticket != _ticket) return;

      final known = more ? state.comments.map((c) => c.id).toSet() : <String>{};

      state = state.copyWith(
        comments: [...(more ? state.comments : const <FeedComment>[]), ...page.items.where((c) => known.add(c.id))],
        nextCursor: page.nextCursor,
        clearCursor: page.nextCursor == null,
        commentsLoading: false,
        loadingMoreComments: false,
      );
    } on AppFailure catch (failure) {
      if (!ref.mounted || ticket != _ticket) return;

      state = state.copyWith(commentsLoading: false, loadingMoreComments: false, commentsFailure: failure);
    }
  }

  Future<void> loadMoreComments() async {
    if (!state.hasMoreComments || state.commentsLoading || state.loadingMoreComments) return;

    await _loadComments(ref.read(feedApiProvider), _ticket, state.nextCursor);
  }

  void _show(FeedPost post) {
    if (!ref.mounted) return;

    state = state.copyWith(post: post);
    ref.read(feedControllerProvider.notifier).replace(post);
  }

  Future<AppFailure?> toggleLike() async {
    final post = state.post;

    if (post == null || _liking) return null;

    _liking = true;

    try {
      final failure = await likeWith(ref, post, _show);

      if (failure is NotFoundFailure && ref.mounted) state = state.copyWith(gone: true);

      return failure;
    } finally {
      _liking = false;
    }
  }

  /// The post was edited elsewhere in the app (the edit screen): show the new words.
  void edited(FeedPost post) => _show(post);

  /// Writes a comment. A failure leaves everything as it was, so the words can be sent again.
  Future<FeedSendOutcome> addComment(String raw) async {
    final text = raw.trim();

    if (text.isEmpty || state.sending) return const FeedSendOutcome();

    if (_pendingText != text) {
      _pendingText = text;
      _pendingKey = ref.read(idempotencyKeyFactoryProvider)();
    }

    state = state.copyWith(sending: true);

    try {
      final made = await ref.read(feedApiProvider).comment(id, text, clientId: _pendingKey!);

      if (!ref.mounted) return const FeedSendOutcome();

      _pendingText = null;
      _pendingKey = null;

      final known = state.comments.any((c) => c.id == made.item.id);
      final post = state.post;

      state = state.copyWith(comments: known ? state.comments : [...state.comments, made.item], sending: false);

      if (post != null && !known) _show(post.copyWith(commentsCount: post.commentsCount + 1));

      return FeedSendOutcome(crisis: made.crisis, helplines: made.helplines);
    } on AppFailure catch (failure) {
      if (!ref.mounted) return FeedSendOutcome(failure: failure);

      state = state.copyWith(sending: false, gone: failure is NotFoundFailure ? true : null);

      return FeedSendOutcome(failure: failure);
    }
  }

  Future<AppFailure?> deleteComment(String commentId) async {
    try {
      await ref.read(feedApiProvider).deleteComment(commentId);
    } on AppFailure catch (failure) {
      // Gone already is what was asked.
      if (failure is! NotFoundFailure) return failure;
    }

    if (!ref.mounted) return null;

    final post = state.post;
    final had = state.comments.any((c) => c.id == commentId);

    state = state.copyWith(comments: state.comments.where((c) => c.id != commentId).toList());

    if (post != null && had) _show(post.copyWith(commentsCount: math.max(0, post.commentsCount - 1)));

    return null;
  }
}
