// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'availability.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_SlotLocation _$SlotLocationFromJson(Map<String, dynamic> json) =>
    _SlotLocation(name: json['name'] as String, city: json['city'] as String);

Map<String, dynamic> _$SlotLocationToJson(_SlotLocation instance) =>
    <String, dynamic>{'name': instance.name, 'city': instance.city};

_AvailabilitySlot _$AvailabilitySlotFromJson(Map<String, dynamic> json) =>
    _AvailabilitySlot(
      id: json['id'] as String,
      startsAt: DateTime.parse(json['starts_at'] as String),
      durationMinutes: (json['duration_minutes'] as num).toInt(),
      mode: $enumDecode(
        _$SessionModeEnumMap,
        json['mode'],
        unknownValue: SessionMode.unknown,
      ),
      state: $enumDecode(
        _$SlotStateEnumMap,
        json['state'],
        unknownValue: SlotState.unknown,
      ),
      location: json['location'] == null
          ? null
          : SlotLocation.fromJson(json['location'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$AvailabilitySlotToJson(_AvailabilitySlot instance) =>
    <String, dynamic>{
      'id': instance.id,
      'starts_at': instance.startsAt.toIso8601String(),
      'duration_minutes': instance.durationMinutes,
      'mode': _$SessionModeEnumMap[instance.mode]!,
      'state': _$SlotStateEnumMap[instance.state]!,
      'location': instance.location,
    };

const _$SessionModeEnumMap = {
  SessionMode.online: 'online',
  SessionMode.physical: 'physical',
  SessionMode.unknown: '__unknown__',
};

const _$SlotStateEnumMap = {
  SlotState.available: 'available',
  SlotState.booked: 'booked',
  SlotState.unknown: '__unknown__',
};

_AvailabilityDay _$AvailabilityDayFromJson(Map<String, dynamic> json) =>
    _AvailabilityDay(
      date: json['date'] as String,
      counts:
          (json['counts'] as Map<String, dynamic>?)?.map(
            (k, e) => MapEntry(k, (e as num).toInt()),
          ) ??
          const {},
      slots:
          (json['slots'] as List<dynamic>?)
              ?.map((e) => AvailabilitySlot.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
    );

Map<String, dynamic> _$AvailabilityDayToJson(_AvailabilityDay instance) =>
    <String, dynamic>{
      'date': instance.date,
      'counts': instance.counts,
      'slots': instance.slots,
    };

_AvailabilityCounselor _$AvailabilityCounselorFromJson(
  Map<String, dynamic> json,
) => _AvailabilityCounselor(
  id: json['id'] as String,
  displayName: json['display_name'] as String,
  avatarUrl: json['avatar_url'] as String?,
  verified: json['verified'] as bool? ?? true,
  modesOffered:
      (json['modes_offered'] as List<dynamic>?)
          ?.map(
            (e) => $enumDecode(
              _$SessionModeEnumMap,
              e,
              unknownValue: SessionMode.unknown,
            ),
          )
          .toList() ??
      const [],
  acceptsRequests: json['accepts_requests'] as bool? ?? false,
);

Map<String, dynamic> _$AvailabilityCounselorToJson(
  _AvailabilityCounselor instance,
) => <String, dynamic>{
  'id': instance.id,
  'display_name': instance.displayName,
  'avatar_url': instance.avatarUrl,
  'verified': instance.verified,
  'modes_offered': instance.modesOffered
      .map((e) => _$SessionModeEnumMap[e]!)
      .toList(),
  'accepts_requests': instance.acceptsRequests,
};

_BookingWindow _$BookingWindowFromJson(Map<String, dynamic> json) =>
    _BookingWindow(
      earliestStart: DateTime.parse(json['earliest_start'] as String),
      lastDate: json['last_date'] as String,
      minLeadHours: (json['min_lead_hours'] as num).toInt(),
    );

Map<String, dynamic> _$BookingWindowToJson(_BookingWindow instance) =>
    <String, dynamic>{
      'earliest_start': instance.earliestStart.toIso8601String(),
      'last_date': instance.lastDate,
      'min_lead_hours': instance.minLeadHours,
    };

_Availability _$AvailabilityFromJson(Map<String, dynamic> json) =>
    _Availability(
      counselor: AvailabilityCounselor.fromJson(
        json['counselor'] as Map<String, dynamic>,
      ),
      timeZone: json['time_zone'] as String,
      bookingWindow: BookingWindow.fromJson(
        json['booking_window'] as Map<String, dynamic>,
      ),
      days:
          (json['days'] as List<dynamic>?)
              ?.map((e) => AvailabilityDay.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      nextFrom: json['next_from'] as String?,
    );

Map<String, dynamic> _$AvailabilityToJson(_Availability instance) =>
    <String, dynamic>{
      'counselor': instance.counselor,
      'time_zone': instance.timeZone,
      'booking_window': instance.bookingWindow,
      'days': instance.days,
      'next_from': instance.nextFrom,
    };
