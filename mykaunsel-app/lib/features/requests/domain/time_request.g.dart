// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'time_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_WorkingHoursLine _$WorkingHoursLineFromJson(Map<String, dynamic> json) =>
    _WorkingHoursLine(
      weekday: (json['weekday'] as num).toInt(),
      from: json['from'] as String,
      to: json['to'] as String,
      durationMinutes: (json['duration_minutes'] as num).toInt(),
      mode: $enumDecode(
        _$SessionModeEnumMap,
        json['mode'],
        unknownValue: SessionMode.unknown,
      ),
      location: json['location'] == null
          ? null
          : SlotLocation.fromJson(json['location'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$WorkingHoursLineToJson(_WorkingHoursLine instance) =>
    <String, dynamic>{
      'weekday': instance.weekday,
      'from': instance.from,
      'to': instance.to,
      'duration_minutes': instance.durationMinutes,
      'mode': _$SessionModeEnumMap[instance.mode]!,
      'location': instance.location,
    };

const _$SessionModeEnumMap = {
  SessionMode.online: 'online',
  SessionMode.physical: 'physical',
  SessionMode.unknown: '__unknown__',
};

_WorkingHours _$WorkingHoursFromJson(Map<String, dynamic> json) =>
    _WorkingHours(
      counselor: BookingCounselorRef.fromJson(
        json['counselor'] as Map<String, dynamic>,
      ),
      acceptsRequests: json['accepts_requests'] as bool? ?? false,
      hours:
          (json['hours'] as List<dynamic>?)
              ?.map((e) => WorkingHoursLine.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      bookingWindow: BookingWindow.fromJson(
        json['booking_window'] as Map<String, dynamic>,
      ),
      answerWithinHours: (json['answer_within_hours'] as num?)?.toInt() ?? 48,
    );

Map<String, dynamic> _$WorkingHoursToJson(_WorkingHours instance) =>
    <String, dynamic>{
      'counselor': instance.counselor,
      'accepts_requests': instance.acceptsRequests,
      'hours': instance.hours,
      'booking_window': instance.bookingWindow,
      'answer_within_hours': instance.answerWithinHours,
    };

_TimeRequest _$TimeRequestFromJson(Map<String, dynamic> json) => _TimeRequest(
  id: json['id'] as String,
  status: $enumDecode(
    _$RequestStateEnumMap,
    json['status'],
    unknownValue: RequestState.unknown,
  ),
  counselor: BookingCounselorRef.fromJson(
    json['counselor'] as Map<String, dynamic>,
  ),
  startsAt: DateTime.parse(json['starts_at'] as String),
  endsAt: DateTime.parse(json['ends_at'] as String),
  durationMinutes: (json['duration_minutes'] as num).toInt(),
  mode: $enumDecode(
    _$SessionModeEnumMap,
    json['mode'],
    unknownValue: SessionMode.unknown,
  ),
  location: json['location'] == null
      ? null
      : SlotLocation.fromJson(json['location'] as Map<String, dynamic>),
  message: json['message'] as String?,
  responseReason: json['response_reason'] as String?,
  expiresAt: DateTime.parse(json['expires_at'] as String),
  respondedAt: json['responded_at'] == null
      ? null
      : DateTime.parse(json['responded_at'] as String),
  bookingId: json['booking_id'] as String?,
);

Map<String, dynamic> _$TimeRequestToJson(_TimeRequest instance) =>
    <String, dynamic>{
      'id': instance.id,
      'status': _$RequestStateEnumMap[instance.status]!,
      'counselor': instance.counselor,
      'starts_at': instance.startsAt.toIso8601String(),
      'ends_at': instance.endsAt.toIso8601String(),
      'duration_minutes': instance.durationMinutes,
      'mode': _$SessionModeEnumMap[instance.mode]!,
      'location': instance.location,
      'message': instance.message,
      'response_reason': instance.responseReason,
      'expires_at': instance.expiresAt.toIso8601String(),
      'responded_at': instance.respondedAt?.toIso8601String(),
      'booking_id': instance.bookingId,
    };

const _$RequestStateEnumMap = {
  RequestState.pending: 'pending',
  RequestState.approved: 'approved',
  RequestState.rejected: 'rejected',
  RequestState.expired: 'expired',
  RequestState.withdrawn: 'withdrawn',
  RequestState.unknown: '__unknown__',
};
