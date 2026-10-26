// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'counselor_query.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_CounselorFilterOptions _$CounselorFilterOptionsFromJson(
  Map<String, dynamic> json,
) => _CounselorFilterOptions(
  specializations:
      (json['specializations'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList() ??
      const [],
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
  states:
      (json['states'] as List<dynamic>?)?.map((e) => e as String).toList() ??
      const [],
);

Map<String, dynamic> _$CounselorFilterOptionsToJson(
  _CounselorFilterOptions instance,
) => <String, dynamic>{
  'specializations': instance.specializations,
  'languages': instance.languages,
  'session_modes': instance.sessionModes
      .map((e) => _$SessionModeEnumMap[e]!)
      .toList(),
  'states': instance.states,
};

const _$SessionModeEnumMap = {
  SessionMode.online: 'online',
  SessionMode.physical: 'physical',
  SessionMode.unknown: '__unknown__',
};
