import 'package:freezed_annotation/freezed_annotation.dart';

import '../../booking/domain/availability.dart';
import '../../counselors/domain/counselor.dart';

part 'my_booking.freezed.dart';
part 'my_booking.g.dart';

/// The state of a booking. `unknown` is what an older app makes of a state the server adds later: shown plainly, with no actions.
@JsonEnum(fieldRename: FieldRename.snake)
enum BookingState {
  confirmed,
  completed,
  cancelled,
  noShow,
  @JsonValue('__unknown__')
  unknown,
}

/// Where the meeting link is. Only `available` carries the link itself, and only inside its window.
@JsonEnum(fieldRename: FieldRename.snake)
enum MeetingState {
  opensLater,
  available,
  notAdded,
  ended,
  @JsonValue('__unknown__')
  unknown,
}

@freezed
abstract class BookingCounselorRef with _$BookingCounselorRef {
  const factory BookingCounselorRef({
    /// The counselor's public id (for "Book again"); null if they no longer have a profile.
    String? id,
    @Default('') String displayName,
  }) = _BookingCounselorRef;

  factory BookingCounselorRef.fromJson(Map<String, dynamic> json) => _$BookingCounselorRefFromJson(json);
}

/// One line of the list. The place is its name and city only.
@freezed
abstract class MyBooking with _$MyBooking {
  const factory MyBooking({
    required String id,
    @JsonKey(unknownEnumValue: BookingState.unknown) required BookingState status,
    @Default(false) bool startingSoon,
    required DateTime startsAt,
    required DateTime endsAt,
    required int durationMinutes,
    @JsonKey(unknownEnumValue: SessionMode.unknown) required SessionMode mode,
    required BookingCounselorRef counselor,
    SlotLocation? location,
  }) = _MyBooking;

  factory MyBooking.fromJson(Map<String, dynamic> json) => _$MyBookingFromJson(json);
}

@freezed
abstract class MeetingInfo with _$MeetingInfo {
  const factory MeetingInfo({
    @JsonKey(unknownEnumValue: MeetingState.unknown) required MeetingState state,
    required DateTime opensAt,
    required DateTime closesAt,
    String? provider,

    /// Present only when [state] is `available`.
    String? url,
  }) = _MeetingInfo;

  factory MeetingInfo.fromJson(Map<String, dynamic> json) => _$MeetingInfoFromJson(json);
}

/// The full address of a confirmed in-person session. Only its owner ever receives this.
@freezed
abstract class BookingPlace with _$BookingPlace {
  const factory BookingPlace({
    required String name,
    required String address,
    required String city,
    required String state,
    required String postcode,
    double? latitude,
    double? longitude,
  }) = _BookingPlace;

  factory BookingPlace.fromJson(Map<String, dynamic> json) => _$BookingPlaceFromJson(json);
}

@freezed
abstract class MyBookingDetail with _$MyBookingDetail {
  const MyBookingDetail._();

  const factory MyBookingDetail({
    required String id,
    @JsonKey(unknownEnumValue: BookingState.unknown) required BookingState status,
    @Default(false) bool startingSoon,
    required DateTime startsAt,
    required DateTime endsAt,
    required int durationMinutes,
    @JsonKey(unknownEnumValue: SessionMode.unknown) required SessionMode mode,
    required BookingCounselorRef counselor,
    SlotLocation? location,
    required int cancellationHours,

    /// Null once the deadline has passed (or the booking is not confirmed).
    DateTime? cancellationDeadlineAt,
    @Default(false) bool canCancel,
    @Default(0) int reschedulesLeft,

    /// The same time limit as cancelling, and moves left (the server decides).
    @Default(false) bool canReschedule,
    MeetingInfo? meeting,
    BookingPlace? place,
  }) = _MyBookingDetail;

  factory MyBookingDetail.fromJson(Map<String, dynamic> json) => _$MyBookingDetailFromJson(json);

  factory MyBookingDetail.fromResponse(Map<String, dynamic> json) => MyBookingDetail.fromJson(Map<String, dynamic>.from(json['booking'] as Map));
}

/// A page of the list. Upcoming is always one page; Past is paged.
class BookingsPage {
  const BookingsPage({required this.items, required this.page, required this.lastPage});

  factory BookingsPage.fromJson(Map<String, dynamic> json) => BookingsPage(
        items: [for (final item in (json['data'] as List? ?? const [])) MyBooking.fromJson(Map<String, dynamic>.from(item as Map))],
        page: (json['page'] as num?)?.toInt() ?? 1,
        lastPage: (json['last_page'] as num?)?.toInt() ?? 1,
      );

  final List<MyBooking> items;
  final int page;
  final int lastPage;
}

enum BookingsScope { upcoming, past }
