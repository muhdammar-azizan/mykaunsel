import 'package:freezed_annotation/freezed_annotation.dart';

import '../../counselors/domain/counselor.dart';

part 'availability.freezed.dart';
part 'availability.g.dart';

/// Whether a time can still be picked. `unknown` is what an older app makes of a state the server adds later: it is shown as not pickable.
@JsonEnum(fieldRename: FieldRename.snake)
enum SlotState {
  available,
  booked,
  @JsonValue('__unknown__')
  unknown,
}

/// The place of an in-person time: its name and city only. The street address is never sent to the app.
@freezed
abstract class SlotLocation with _$SlotLocation {
  const factory SlotLocation({required String name, required String city}) = _SlotLocation;

  factory SlotLocation.fromJson(Map<String, dynamic> json) => _$SlotLocationFromJson(json);
}

/// One time on the screen. [startsAt] carries Malaysian time's offset (+08:00). [id] is random (never a counter).
@freezed
abstract class AvailabilitySlot with _$AvailabilitySlot {
  const AvailabilitySlot._();

  const factory AvailabilitySlot({
    required String id,
    required DateTime startsAt,
    required int durationMinutes,
    @JsonKey(unknownEnumValue: SessionMode.unknown) required SessionMode mode,
    @JsonKey(unknownEnumValue: SlotState.unknown) required SlotState state,
    SlotLocation? location,
  }) = _AvailabilitySlot;

  factory AvailabilitySlot.fromJson(Map<String, dynamic> json) => _$AvailabilitySlotFromJson(json);

  bool get isAvailable => state == SlotState.available;
}

/// One day of the range: its date ("2026-11-03", a Malaysian date) and its times.
@freezed
abstract class AvailabilityDay with _$AvailabilityDay {
  const AvailabilityDay._();

  const factory AvailabilityDay({
    required String date,
    @Default({}) Map<String, int> counts,
    @Default([]) List<AvailabilitySlot> slots,
  }) = _AvailabilityDay;

  factory AvailabilityDay.fromJson(Map<String, dynamic> json) => _$AvailabilityDayFromJson(json);

  /// The year, month and day of [date] as a plain value to read the weekday and day number from (it is NOT an instant).
  DateTime get day {
    final parts = date.split('-').map(int.parse).toList();

    return DateTime.utc(parts[0], parts[1], parts[2]);
  }
}

@freezed
abstract class AvailabilityCounselor with _$AvailabilityCounselor {
  const factory AvailabilityCounselor({
    required String id,
    required String displayName,
    String? avatarUrl,
    @Default(true) bool verified,
    @JsonKey(unknownEnumValue: SessionMode.unknown) @Default([]) List<SessionMode> modesOffered,
    @Default(false) bool acceptsRequests,
  }) = _AvailabilityCounselor;

  factory AvailabilityCounselor.fromJson(Map<String, dynamic> json) => _$AvailabilityCounselorFromJson(json);
}

@freezed
abstract class BookingWindow with _$BookingWindow {
  const factory BookingWindow({
    required DateTime earliestStart,
    required String lastDate,
    required int minLeadHours,
  }) = _BookingWindow;

  factory BookingWindow.fromJson(Map<String, dynamic> json) => _$BookingWindowFromJson(json);
}

/// What `GET /counselors/{id}/availability` says (docs/AVAILABILITY.md on the server side).
@freezed
abstract class Availability with _$Availability {
  const factory Availability({
    required AvailabilityCounselor counselor,
    required String timeZone,
    required BookingWindow bookingWindow,
    @Default([]) List<AvailabilityDay> days,
    String? nextFrom,
  }) = _Availability;

  factory Availability.fromJson(Map<String, dynamic> json) => _$AvailabilityFromJson(json);
}
