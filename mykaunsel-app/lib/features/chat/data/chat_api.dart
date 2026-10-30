import '../../../core/network/api_client.dart';
import '../domain/chat.dart';

/// The chatbot (docs/CHAT.md on the server). The app only ever calls the server; the server calls the model. Throws only an [AppFailure]:
/// [ForbiddenFailure] with code `ai_consent_required` when the person has not agreed to AI processing, [ConflictFailure] `session_ended` /
/// `session_full`, [NotFoundFailure] for a conversation that is not theirs, [RateLimitedFailure] for the limits.
abstract class ChatApi {
  /// Sends a message ([sessionId] null starts a conversation) and returns the answer in the same call. [clientId] is made per message: sending
  /// the same message again with it (after a lost answer) stores nothing twice.
  Future<ChatSendResult> send(String text, {String? sessionId, required String clientId});

  /// Asks again for the answer to the last message, when the model could not answer. Safe to repeat.
  Future<ChatSendResult> reply(String sessionId);

  /// Ends a conversation (the server stores how it felt). Safe to repeat.
  Future<ChatSessionInfo> end(String sessionId);

  Future<ChatHistoryPage> history({int page = 1});

  Future<ChatSessionDetail> detail(String sessionId);

  /// Where to get help in a crisis. Needs no sign-in and no consent.
  Future<List<Helpline>> helplines();
}

class HttpChatApi implements ChatApi {
  HttpChatApi(this._client);

  final ApiClient _client;

  @override
  Future<ChatSendResult> send(String text, {String? sessionId, required String clientId}) async {
    final response = await _client.post('/chat/messages', data: {'text': text, 'client_id': clientId, 'session': ?sessionId});

    return ChatSendResult.fromJson(response.data);
  }

  @override
  Future<ChatSendResult> reply(String sessionId) async {
    final response = await _client.post('/chat/sessions/${Uri.encodeComponent(sessionId)}/reply');

    return ChatSendResult.fromJson(response.data);
  }

  @override
  Future<ChatSessionInfo> end(String sessionId) async {
    final response = await _client.post('/chat/sessions/${Uri.encodeComponent(sessionId)}/end');

    return ChatSessionInfo.fromJson(Map<String, dynamic>.from(response.data['session'] as Map));
  }

  @override
  Future<ChatHistoryPage> history({int page = 1}) async {
    final response = await _client.get('/chat/sessions', query: {if (page > 1) 'page': page});

    return ChatHistoryPage.fromJson(response.data);
  }

  @override
  Future<ChatSessionDetail> detail(String sessionId) async {
    final response = await _client.get('/chat/sessions/${Uri.encodeComponent(sessionId)}');

    return ChatSessionDetail.fromResponse(response.data);
  }

  @override
  Future<List<Helpline>> helplines() async {
    final response = await _client.get('/chat/helplines');

    return [for (final h in (response.data['helplines'] as List? ?? const [])) Helpline.fromJson(Map<String, dynamic>.from(h as Map))];
  }
}
