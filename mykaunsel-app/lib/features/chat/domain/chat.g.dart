// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'chat.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_ChatMessage _$ChatMessageFromJson(Map<String, dynamic> json) => _ChatMessage(
  id: json['id'] as String,
  sender: $enumDecode(
    _$ChatSenderEnumMap,
    json['sender'],
    unknownValue: ChatSender.unknown,
  ),
  text: json['text'] as String,
  sentAt: DateTime.parse(json['sent_at'] as String),
  crisis: json['crisis'] as bool? ?? false,
);

Map<String, dynamic> _$ChatMessageToJson(_ChatMessage instance) =>
    <String, dynamic>{
      'id': instance.id,
      'sender': _$ChatSenderEnumMap[instance.sender]!,
      'text': instance.text,
      'sent_at': instance.sentAt.toIso8601String(),
      'crisis': instance.crisis,
    };

const _$ChatSenderEnumMap = {
  ChatSender.user: 'user',
  ChatSender.assistant: 'assistant',
  ChatSender.unknown: '__unknown__',
};

_ChatSessionInfo _$ChatSessionInfoFromJson(Map<String, dynamic> json) =>
    _ChatSessionInfo(
      id: json['id'] as String,
      startedAt: DateTime.parse(json['started_at'] as String),
      endedAt: json['ended_at'] == null
          ? null
          : DateTime.parse(json['ended_at'] as String),
      ended: json['ended'] as bool? ?? false,
      dominantEmotion: json['dominant_emotion'] as String?,
      preview: json['preview'] as String?,
    );

Map<String, dynamic> _$ChatSessionInfoToJson(_ChatSessionInfo instance) =>
    <String, dynamic>{
      'id': instance.id,
      'started_at': instance.startedAt.toIso8601String(),
      'ended_at': instance.endedAt?.toIso8601String(),
      'ended': instance.ended,
      'dominant_emotion': instance.dominantEmotion,
      'preview': instance.preview,
    };

_Helpline _$HelplineFromJson(Map<String, dynamic> json) => _Helpline(
  key: json['key'] as String,
  name: json['name'] as String,
  number: json['number'] as String,
);

Map<String, dynamic> _$HelplineToJson(_Helpline instance) => <String, dynamic>{
  'key': instance.key,
  'name': instance.name,
  'number': instance.number,
};
