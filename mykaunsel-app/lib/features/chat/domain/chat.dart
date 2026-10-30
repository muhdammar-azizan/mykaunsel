import 'package:freezed_annotation/freezed_annotation.dart';

import '../../counselors/domain/counselor.dart';

part 'chat.freezed.dart';
part 'chat.g.dart';

/// Who wrote a message. `unknown` is what an older app makes of a sender the server adds later: shown as the companion's.
@JsonEnum(fieldRename: FieldRename.snake)
enum ChatSender {
  user,
  assistant,
  @JsonValue('__unknown__')
  unknown,
}

/// One message as the server keeps it. [crisis] is the server's own check (not the model's) that the person may be in danger.
@freezed
abstract class ChatMessage with _$ChatMessage {
  const factory ChatMessage({
    required String id,
    @JsonKey(unknownEnumValue: ChatSender.unknown) required ChatSender sender,
    required String text,
    required DateTime sentAt,
    @Default(false) bool crisis,
  }) = _ChatMessage;

  factory ChatMessage.fromJson(Map<String, dynamic> json) => _$ChatMessageFromJson(json);
}

/// One conversation. [dominantEmotion] is set when it ended (a word from the server's list); [preview] only in the history list.
@freezed
abstract class ChatSessionInfo with _$ChatSessionInfo {
  const factory ChatSessionInfo({
    required String id,
    required DateTime startedAt,
    DateTime? endedAt,
    @Default(false) bool ended,
    String? dominantEmotion,
    String? preview,
  }) = _ChatSessionInfo;

  factory ChatSessionInfo.fromJson(Map<String, dynamic> json) => _$ChatSessionInfoFromJson(json);
}

/// A phone line for a crisis. [number] is dialled as it is.
@freezed
abstract class Helpline with _$Helpline {
  const factory Helpline({required String key, required String name, required String number}) = _Helpline;

  factory Helpline.fromJson(Map<String, dynamic> json) => _$HelplineFromJson(json);
}

/// Where to turn when the server cannot be reached: the same lines the server sends. The chat must never be without a way to ask for help.
/// ***Confirm every number with the owner before launch (docs/CHAT.md).***
const fallbackHelplines = [
  Helpline(key: 'heal', name: 'HEAL', number: '15555'),
  Helpline(key: 'talian_kasih', name: 'Talian Kasih', number: '15999'),
];

/// What `POST /chat/messages` and `POST /chat/sessions/{id}/reply` answer.
class ChatSendResult {
  const ChatSendResult({
    required this.session,
    required this.message,
    this.reply,
    this.assistantUnavailable = false,
    this.crisis = false,
    this.helplines = const [],
    this.counselors = const [],
  });

  factory ChatSendResult.fromJson(Map<String, dynamic> json) {
    final suggestion = json['suggestion'];

    return ChatSendResult(
      session: ChatSessionInfo.fromJson(Map<String, dynamic>.from(json['session'] as Map)),
      message: ChatMessage.fromJson(Map<String, dynamic>.from(json['message'] as Map)),
      reply: json['reply'] == null ? null : ChatMessage.fromJson(Map<String, dynamic>.from(json['reply'] as Map)),
      assistantUnavailable: json['assistant_unavailable'] == true,
      crisis: json['crisis'] == true,
      helplines: [for (final h in (json['helplines'] as List? ?? const [])) Helpline.fromJson(Map<String, dynamic>.from(h as Map))],
      counselors: suggestion is Map
          ? [for (final c in (suggestion['counselors'] as List? ?? const [])) Counselor.fromJson(Map<String, dynamic>.from(c as Map))]
          : const [],
    );
  }

  final ChatSessionInfo session;
  final ChatMessage message;
  final ChatMessage? reply;

  /// The message was kept but the model could not answer now: the person is told kindly and can ask again.
  final bool assistantUnavailable;

  /// The server flagged the person's message.
  final bool crisis;
  final List<Helpline> helplines;

  /// Counselors the person may book, offered when the message was flagged or asked for one.
  final List<Counselor> counselors;
}

class ChatHistoryPage {
  const ChatHistoryPage({required this.items, required this.page, required this.lastPage});

  factory ChatHistoryPage.fromJson(Map<String, dynamic> json) => ChatHistoryPage(
        items: [for (final s in (json['data'] as List? ?? const [])) ChatSessionInfo.fromJson(Map<String, dynamic>.from(s as Map))],
        page: (json['page'] as num?)?.toInt() ?? 1,
        lastPage: (json['last_page'] as num?)?.toInt() ?? 1,
      );

  final List<ChatSessionInfo> items;
  final int page;
  final int lastPage;
}

/// One whole conversation, read only.
class ChatSessionDetail {
  const ChatSessionDetail({required this.session, required this.messages});

  factory ChatSessionDetail.fromResponse(Map<String, dynamic> json) {
    final data = Map<String, dynamic>.from(json['session'] as Map);

    return ChatSessionDetail(
      session: ChatSessionInfo.fromJson(data),
      messages: [for (final m in (data['messages'] as List? ?? const [])) ChatMessage.fromJson(Map<String, dynamic>.from(m as Map))],
    );
  }

  final ChatSessionInfo session;
  final List<ChatMessage> messages;
}
