// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'booking_preview.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_BookingCounselorInfo _$BookingCounselorInfoFromJson(
  Map<String, dynamic> json,
) => _BookingCounselorInfo(
  id: json['id'] as String,
  displayName: json['display_name'] as String,
  verified: json['verified'] as bool? ?? true,
);

Map<String, dynamic> _$BookingCounselorInfoToJson(
  _BookingCounselorInfo instance,
) => <String, dynamic>{
  'id': instance.id,
  'display_name': instance.displayName,
  'verified': instance.verified,
};

_BookingDetails _$BookingDetailsFromJson(Map<String, dynamic> json) =>
    _BookingDetails(
      slot: json['slot'] as String,
      counselor: BookingCounselorInfo.fromJson(
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
      cancellationDeadlineAt: json['cancellation_deadline_at'] == null
          ? null
          : DateTime.parse(json['cancellation_deadline_at'] as String),
      cancellationHours: (json['cancellation_hours'] as num).toInt(),
      maxReschedules: (json['max_reschedules'] as num).toInt(),
      meetingLinkVisibleMinutes: (json['meeting_link_visible_minutes'] as num)
          .toInt(),
      upcomingSessions: (json['upcoming_sessions'] as num?)?.toInt(),
      maxUpcomingSessions: (json['max_upcoming_sessions'] as num?)?.toInt(),
    );

Map<String, dynamic> _$BookingDetailsToJson(_BookingDetails instance) =>
    <String, dynamic>{
      'slot': instance.slot,
      'counselor': instance.counselor,
      'starts_at': instance.startsAt.toIso8601String(),
      'ends_at': instance.endsAt.toIso8601String(),
      'duration_minutes': instance.durationMinutes,
      'mode': _$SessionModeEnumMap[instance.mode]!,
      'location': instance.location,
      'cancellation_deadline_at': instance.cancellationDeadlineAt
          ?.toIso8601String(),
      'cancellation_hours': instance.cancellationHours,
      'max_reschedules': instance.maxReschedules,
      'meeting_link_visible_minutes': instance.meetingLinkVisibleMinutes,
      'upcoming_sessions': instance.upcomingSessions,
      'max_upcoming_sessions': instance.maxUpcomingSessions,
    };

const _$SessionModeEnumMap = {
  SessionMode.online: 'online',
  SessionMode.physical: 'physical',
  SessionMode.unknown: '__unknown__',
};

_CreatedBooking _$CreatedBookingFromJson(Map<String, dynamic> json) =>
    _CreatedBooking(
      id: json['id'] as String,
      status: json['status'] as String,
      startsAt: DateTime.parse(json['starts_at'] as String),
      endsAt: DateTime.parse(json['ends_at'] as String),
      durationMinutes: (json['duration_minutes'] as num).toInt(),
      mode: $enumDecode(
        _$SessionModeEnumMap,
        json['mode'],
        unknownValue: SessionMode.unknown,
      ),
      counselorName: json['counselor_name'] as String,
      location: json['location'] == null
          ? null
          : SlotLocation.fromJson(json['location'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$CreatedBookingToJson(_CreatedBooking instance) =>
    <String, dynamic>{
      'id': instance.id,
      'status': instance.status,
      'starts_at': instance.startsAt.toIso8601String(),
      'ends_at': instance.endsAt.toIso8601String(),
      'duration_minutes': instance.durationMinutes,
      'mode': _$SessionModeEnumMap[instance.mode]!,
      'counselor_name': instance.counselorName,
      'location': instance.location,
    };
