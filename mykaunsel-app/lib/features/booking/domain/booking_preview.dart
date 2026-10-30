import 'package:freezed_annotation/freezed_annotation.dart';

import '../../counselors/domain/counselor.dart';
import 'availability.dart';

part 'booking_preview.freezed.dart';
part 'booking_preview.g.dart';

@freezed
abstract class BookingCounselorInfo with _$BookingCounselorInfo {
  const factory BookingCounselorInfo({
    required String id,
    required String displayName,
    @Default(true) bool verified,
  }) = _BookingCounselorInfo;

  factory BookingCounselorInfo.fromJson(Map<String, dynamic> json) => _$BookingCounselorInfoFromJson(json);
}

/// Everything the confirm screen shows about the session, as the server says it now (`GET /bookings/preview`, or the fresh details of a
/// changed slot). Every policy value comes from here: the screen holds no number of its own. No price, no link, no street address.
@freezed
abstract class BookingDetails with _$BookingDetails {
  const BookingDetails._();

  const factory BookingDetails({
    required String slot,
    required BookingCounselorInfo counselor,
    required DateTime startsAt,
    required DateTime endsAt,
    required int durationMinutes,
    @JsonKey(unknownEnumValue: SessionMode.unknown) required SessionMode mode,
    SlotLocation? location,

    /// Null when the deadline has already passed: the session cannot be cancelled after booking.
    DateTime? cancellationDeadlineAt,
    required int cancellationHours,
    required int maxReschedules,
    required int meetingLinkVisibleMinutes,

    /// Only said when the person holds the cap or one below it.
    int? upcomingSessions,
    int? maxUpcomingSessions,
  }) = _BookingDetails;

  factory BookingDetails.fromJson(Map<String, dynamic> json) => _$BookingDetailsFromJson(json);

  /// Would the person see something different from [other]? (When, how long, which kind, where.) A change of policy numbers alone is not
  /// something they chose, so it does not count.
  bool differsFrom(BookingDetails other) =>
      startsAt != other.startsAt || durationMinutes != other.durationMinutes || mode != other.mode || location != other.location;
}

/// The answer of the preview. [details] is missing only when the time is gone (`slot_taken` says nothing more, on purpose).
class BookingPreview {
  const BookingPreview({required this.valid, this.reason, this.details});

  factory BookingPreview.fromJson(Map<String, dynamic> json) => BookingPreview(
        valid: json['valid'] == true,
        reason: json['reason'] as String?,
        details: json.containsKey('starts_at') ? BookingDetails.fromJson(json) : null,
      );

  final bool valid;

  /// `slot_taken`, `time_conflict` or `upcoming_limit_reached` when [valid] is false.
  final String? reason;
  final BookingDetails? details;
}

/// A booking just made (`201`). The place is its name and city only; there is no meeting link and no address.
@freezed
abstract class CreatedBooking with _$CreatedBooking {
  const factory CreatedBooking({
    required String id,
    required String status,
    required DateTime startsAt,
    required DateTime endsAt,
    required int durationMinutes,
    @JsonKey(unknownEnumValue: SessionMode.unknown) required SessionMode mode,
    required String counselorName,
    SlotLocation? location,
  }) = _CreatedBooking;

  factory CreatedBooking.fromResponse(Map<String, dynamic> json) {
    final booking = Map<String, dynamic>.from(json['booking'] as Map);
    final counselor = booking['counselor'] as Map?;

    return _$CreatedBookingFromJson({...booking, 'counselor_name': counselor?['display_name'] ?? ''});
  }

  factory CreatedBooking.fromJson(Map<String, dynamic> json) => _$CreatedBookingFromJson(json);
}
