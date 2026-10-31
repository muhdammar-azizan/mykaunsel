import '../../../core/network/api_client.dart';
import '../../chat/domain/chat.dart';
import '../domain/feed.dart';

/// The wellness Feed (docs/FEED.md on the server). Throws only an [AppFailure]: [NotFoundFailure] for a post or comment that is gone (deleted,
/// removed, or never there), [RateLimitedFailure] for the limits (a long wait is the daily limit of posts), [ConflictFailure]
/// `cannot_report_own` / `cannot_mute_self`.
abstract class FeedApi {
  Future<FeedPage<FeedPost>> feed({String? cursor});

  Future<FeedPost> post(String id);

  /// [clientId] is made per post: sending the same post again with it (after a lost answer) stores nothing twice.
  Future<FeedWrite<FeedPost>> create(String text, {required String clientId});

  Future<FeedPost> edit(String id, String text);

  Future<void> delete(String id);

  /// Likes ([on]) or takes the like back. Safe to repeat.
  Future<FeedReactions> react(String id, {required bool on});

  Future<FeedPage<FeedComment>> comments(String postId, {String? cursor});

  Future<FeedWrite<FeedComment>> comment(String postId, String text, {required String clientId});

  Future<void> deleteComment(String id);

  Future<void> report({required String id, required bool isComment, required String reason});

  /// Hides the post's author from this person's feed and comments.
  Future<FeedMuted> muteAuthor(String postId);

  Future<List<FeedMuted>> mutes();

  Future<void> unmute(String muteId);
}

class HttpFeedApi implements FeedApi {
  HttpFeedApi(this._client);

  final ApiClient _client;

  static String _id(String id) => Uri.encodeComponent(id);

  static Map<String, dynamic> _map(Object? value) => Map<String, dynamic>.from(value as Map);

  static List<Helpline> _helplines(Map<String, dynamic> json) => [for (final h in (json['helplines'] as List? ?? const [])) Helpline.fromJson(_map(h))];

  @override
  Future<FeedPage<FeedPost>> feed({String? cursor}) async {
    final response = await _client.get('/feed', query: {'cursor': ?cursor});

    return FeedPage(items: [for (final p in (response.data['data'] as List? ?? const [])) FeedPost.fromJson(_map(p))], nextCursor: response.data['next_cursor'] as String?);
  }

  @override
  Future<FeedPost> post(String id) async {
    final response = await _client.get('/feed/posts/${_id(id)}');

    return FeedPost.fromJson(_map(response.data['post']));
  }

  @override
  Future<FeedWrite<FeedPost>> create(String text, {required String clientId}) async {
    final response = await _client.post('/feed/posts', data: {'text': text, 'client_id': clientId});

    return FeedWrite(item: FeedPost.fromJson(_map(response.data['post'])), crisis: response.data['crisis'] == true, helplines: _helplines(response.data));
  }

  @override
  Future<FeedPost> edit(String id, String text) async {
    final response = await _client.patch('/feed/posts/${_id(id)}', data: {'text': text});

    return FeedPost.fromJson(_map(response.data['post']));
  }

  @override
  Future<void> delete(String id) async {
    await _client.delete('/feed/posts/${_id(id)}');
  }

  @override
  Future<FeedReactions> react(String id, {required bool on}) async {
    final path = '/feed/posts/${_id(id)}/reaction';
    final response = on ? await _client.put(path) : await _client.delete(path);

    return FeedReactions.fromJson(_map(response.data['reactions']));
  }

  @override
  Future<FeedPage<FeedComment>> comments(String postId, {String? cursor}) async {
    final response = await _client.get('/feed/posts/${_id(postId)}/comments', query: {'cursor': ?cursor});

    return FeedPage(items: [for (final c in (response.data['data'] as List? ?? const [])) FeedComment.fromJson(_map(c))], nextCursor: response.data['next_cursor'] as String?);
  }

  @override
  Future<FeedWrite<FeedComment>> comment(String postId, String text, {required String clientId}) async {
    final response = await _client.post('/feed/posts/${_id(postId)}/comments', data: {'text': text, 'client_id': clientId});

    return FeedWrite(item: FeedComment.fromJson(_map(response.data['comment'])), crisis: response.data['crisis'] == true, helplines: _helplines(response.data));
  }

  @override
  Future<void> deleteComment(String id) async {
    await _client.delete('/feed/comments/${_id(id)}');
  }

  @override
  Future<void> report({required String id, required bool isComment, required String reason}) async {
    final kind = isComment ? 'comments' : 'posts';

    await _client.post('/feed/$kind/${_id(id)}/report', data: {'reason': reason});
  }

  @override
  Future<FeedMuted> muteAuthor(String postId) async {
    final response = await _client.post('/feed/posts/${_id(postId)}/mute-author');

    return FeedMuted.fromJson(_map(response.data['mute']));
  }

  @override
  Future<List<FeedMuted>> mutes() async {
    final response = await _client.get('/feed/mutes');

    return [for (final m in (response.data['data'] as List? ?? const [])) FeedMuted.fromJson(_map(m))];
  }

  @override
  Future<void> unmute(String muteId) async {
    await _client.delete('/feed/mutes/${_id(muteId)}');
  }
}
