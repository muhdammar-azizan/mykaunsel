// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'counselor.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_CounselorContext _$CounselorContextFromJson(Map<String, dynamic> json) =>
    _CounselorContext(
      type: $enumDecode(
        _$ContextTypeEnumMap,
        json['type'],
        unknownValue: ContextType.unknown,
      ),
      organization: json['organization'] as String?,
    );

Map<String, dynamic> _$CounselorContextToJson(_CounselorContext instance) =>
    <String, dynamic>{
      'type': _$ContextTypeEnumMap[instance.type]!,
      'organization': instance.organization,
    };

const _$ContextTypeEnumMap = {
  ContextType.independent: 'independent',
  ContextType.organization: 'organization',
  ContextType.unknown: '__unknown__',
};

_CounselorLocation _$CounselorLocationFromJson(Map<String, dynamic> json) =>
    _CounselorLocation(
      city: json['city'] as String,
      state: json['state'] as String,
    );

Map<String, dynamic> _$CounselorLocationToJson(_CounselorLocation instance) =>
    <String, dynamic>{'city': instance.city, 'state': instance.state};

_CounselorSlot _$CounselorSlotFromJson(Map<String, dynamic> json) =>
    _CounselorSlot(
      startsAt: DateTime.parse(json['starts_at'] as String),
      durationMinutes: (json['duration_minutes'] as num).toInt(),
      sessionMode: $enumDecode(
        _$SessionModeEnumMap,
        json['session_mode'],
        unknownValue: SessionMode.unknown,
      ),
      city: json['city'] as String?,
      state: json['state'] as String?,
      context: json['context'] == null
          ? null
          : CounselorContext.fromJson(json['context'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$CounselorSlotToJson(_CounselorSlot instance) =>
    <String, dynamic>{
      'starts_at': instance.startsAt.toIso8601String(),
      'duration_minutes': instance.durationMinutes,
      'session_mode': _$SessionModeEnumMap[instance.sessionMode]!,
      'city': instance.city,
      'state': instance.state,
      'context': instance.context,
    };

const _$SessionModeEnumMap = {
  SessionMode.online: 'online',
  SessionMode.physical: 'physical',
  SessionMode.unknown: '__unknown__',
};

_Counselor _$CounselorFromJson(Map<String, dynamic> json) => _Counselor(
  id: json['id'] as String,
  name: json['name'] as String,
  verified: json['verified'] as bool? ?? true,
  contexts:
      (json['contexts'] as List<dynamic>?)
          ?.map((e) => CounselorContext.fromJson(e as Map<String, dynamic>))
          .toList() ??
      const [],
  specializations:
      (json['specializations'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList() ??
      const [],
  specializationsCount: (json['specializations_count'] as num?)?.toInt() ?? 0,
  languages:
      (json['languages'] as List<dynamic>?)?.map((e) => e as String).toList() ??
      const [],
  sessionModes:
      (json['session_modes'] as List<dynamic>?)
          ?.map(
            (e) => $enumDecode(
              _$SessionModeEnumMap,
              e,
              unknownValue: SessionMode.unknown,
            ),
          )
          .toList() ??
      const [],
  locations:
      (json['locations'] as List<dynamic>?)
          ?.map((e) => CounselorLocation.fromJson(e as Map<String, dynamic>))
          .toList() ??
      const [],
  nextAvailable: json['next_available'] == null
      ? null
      : CounselorSlot.fromJson(json['next_available'] as Map<String, dynamic>),
  acceptsRequests: json['accepts_requests'] as bool?,
  upcomingSlots:
      (json['upcoming_slots'] as List<dynamic>?)
          ?.map((e) => CounselorSlot.fromJson(e as Map<String, dynamic>))
          .toList() ??
      const [],
);

Map<String, dynamic> _$CounselorToJson(_Counselor instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'verified': instance.verified,
      'contexts': instance.contexts,
      'specializations': instance.specializations,
      'specializations_count': instance.specializationsCount,
      'languages': instance.languages,
      'session_modes': instance.sessionModes
          .map((e) => _$SessionModeEnumMap[e]!)
          .toList(),
      'locations': instance.locations,
      'next_available': instance.nextAvailable,
      'accepts_requests': instance.acceptsRequests,
      'upcoming_slots': instance.upcomingSlots,
    };
