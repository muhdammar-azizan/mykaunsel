// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'my_booking.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_BookingCounselorRef _$BookingCounselorRefFromJson(Map<String, dynamic> json) =>
    _BookingCounselorRef(
      id: json['id'] as String?,
      displayName: json['display_name'] as String? ?? '',
    );

Map<String, dynamic> _$BookingCounselorRefToJson(
  _BookingCounselorRef instance,
) => <String, dynamic>{'id': instance.id, 'display_name': instance.displayName};

_MyBooking _$MyBookingFromJson(Map<String, dynamic> json) => _MyBooking(
  id: json['id'] as String,
  status: $enumDecode(
    _$BookingStateEnumMap,
    json['status'],
    unknownValue: BookingState.unknown,
  ),
  startingSoon: json['starting_soon'] as bool? ?? false,
  startsAt: DateTime.parse(json['starts_at'] as String),
  endsAt: DateTime.parse(json['ends_at'] as String),
  durationMinutes: (json['duration_minutes'] as num).toInt(),
  mode: $enumDecode(
    _$SessionModeEnumMap,
    json['mode'],
    unknownValue: SessionMode.unknown,
  ),
  counselor: BookingCounselorRef.fromJson(
    json['counselor'] as Map<String, dynamic>,
  ),
  location: json['location'] == null
      ? null
      : SlotLocation.fromJson(json['location'] as Map<String, dynamic>),
);

Map<String, dynamic> _$MyBookingToJson(_MyBooking instance) =>
    <String, dynamic>{
      'id': instance.id,
      'status': _$BookingStateEnumMap[instance.status]!,
      'starting_soon': instance.startingSoon,
      'starts_at': instance.startsAt.toIso8601String(),
      'ends_at': instance.endsAt.toIso8601String(),
      'duration_minutes': instance.durationMinutes,
      'mode': _$SessionModeEnumMap[instance.mode]!,
      'counselor': instance.counselor,
      'location': instance.location,
    };

const _$BookingStateEnumMap = {
  BookingState.confirmed: 'confirmed',
  BookingState.completed: 'completed',
  BookingState.cancelled: 'cancelled',
  BookingState.noShow: 'no_show',
  BookingState.unknown: '__unknown__',
};

const _$SessionModeEnumMap = {
  SessionMode.online: 'online',
  SessionMode.physical: 'physical',
  SessionMode.unknown: '__unknown__',
};

_MeetingInfo _$MeetingInfoFromJson(Map<String, dynamic> json) => _MeetingInfo(
  state: $enumDecode(
    _$MeetingStateEnumMap,
    json['state'],
    unknownValue: MeetingState.unknown,
  ),
  opensAt: DateTime.parse(json['opens_at'] as String),
  closesAt: DateTime.parse(json['closes_at'] as String),
  provider: json['provider'] as String?,
  url: json['url'] as String?,
);

Map<String, dynamic> _$MeetingInfoToJson(_MeetingInfo instance) =>
    <String, dynamic>{
      'state': _$MeetingStateEnumMap[instance.state]!,
      'opens_at': instance.opensAt.toIso8601String(),
      'closes_at': instance.closesAt.toIso8601String(),
      'provider': instance.provider,
      'url': instance.url,
    };

const _$MeetingStateEnumMap = {
  MeetingState.opensLater: 'opens_later',
  MeetingState.available: 'available',
  MeetingState.notAdded: 'not_added',
  MeetingState.ended: 'ended',
  MeetingState.unknown: '__unknown__',
};

_BookingPlace _$BookingPlaceFromJson(Map<String, dynamic> json) =>
    _BookingPlace(
      name: json['name'] as String,
      address: json['address'] as String,
      city: json['city'] as String,
      state: json['state'] as String,
      postcode: json['postcode'] as String,
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
    );

Map<String, dynamic> _$BookingPlaceToJson(_BookingPlace instance) =>
    <String, dynamic>{
      'name': instance.name,
      'address': instance.address,
      'city': instance.city,
      'state': instance.state,
      'postcode': instance.postcode,
      'latitude': instance.latitude,
      'longitude': instance.longitude,
    };

_MyBookingDetail _$MyBookingDetailFromJson(Map<String, dynamic> json) =>
    _MyBookingDetail(
      id: json['id'] as String,
      status: $enumDecode(
        _$BookingStateEnumMap,
        json['status'],
        unknownValue: BookingState.unknown,
      ),
      startingSoon: json['starting_soon'] as bool? ?? false,
      startsAt: DateTime.parse(json['starts_at'] as String),
      endsAt: DateTime.parse(json['ends_at'] as String),
      durationMinutes: (json['duration_minutes'] as num).toInt(),
      mode: $enumDecode(
        _$SessionModeEnumMap,
        json['mode'],
        unknownValue: SessionMode.unknown,
      ),
      counselor: BookingCounselorRef.fromJson(
        json['counselor'] as Map<String, dynamic>,
      ),
      location: json['location'] == null
          ? null
          : SlotLocation.fromJson(json['location'] as Map<String, dynamic>),
      cancellationHours: (json['cancellation_hours'] as num).toInt(),
      cancellationDeadlineAt: json['cancellation_deadline_at'] == null
          ? null
          : DateTime.parse(json['cancellation_deadline_at'] as String),
      canCancel: json['can_cancel'] as bool? ?? false,
      reschedulesLeft: (json['reschedules_left'] as num?)?.toInt() ?? 0,
      canReschedule: json['can_reschedule'] as bool? ?? false,
      meeting: json['meeting'] == null
          ? null
          : MeetingInfo.fromJson(json['meeting'] as Map<String, dynamic>),
      place: json['place'] == null
          ? null
          : BookingPlace.fromJson(json['place'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$MyBookingDetailToJson(_MyBookingDetail instance) =>
    <String, dynamic>{
      'id': instance.id,
      'status': _$BookingStateEnumMap[instance.status]!,
      'starting_soon': instance.startingSoon,
      'starts_at': instance.startsAt.toIso8601String(),
      'ends_at': instance.endsAt.toIso8601String(),
      'duration_minutes': instance.durationMinutes,
      'mode': _$SessionModeEnumMap[instance.mode]!,
      'counselor': instance.counselor,
      'location': instance.location,
      'cancellation_hours': instance.cancellationHours,
      'cancellation_deadline_at': instance.cancellationDeadlineAt
          ?.toIso8601String(),
      'can_cancel': instance.canCancel,
      'reschedules_left': instance.reschedulesLeft,
      'can_reschedule': instance.canReschedule,
      'meeting': instance.meeting,
      'place': instance.place,
    };
