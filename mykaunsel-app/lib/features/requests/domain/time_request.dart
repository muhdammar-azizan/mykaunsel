import 'package:freezed_annotation/freezed_annotation.dart';

import '../../booking/domain/availability.dart';
import '../../bookings/domain/my_booking.dart';
import '../../counselors/domain/counselor.dart';

part 'time_request.freezed.dart';
part 'time_request.g.dart';

/// The state of a time request. `unknown` is what an older app makes of a state the server adds later: shown plainly, with no actions.
/// The server calls "declined" `rejected`.
@JsonEnum(fieldRename: FieldRename.snake)
enum RequestState {
  pending,
  approved,
  rejected,
  expired,
  withdrawn,
  @JsonValue('__unknown__')
  unknown,
}

/// One line of a counselor's weekly hours: on [weekday] (1 = Monday ... 7 = Sunday) they see people from [from] to [to] ("HH:mm", Malaysian
/// time) in times of [durationMinutes].
@freezed
abstract class WorkingHoursLine with _$WorkingHoursLine {
  const WorkingHoursLine._();

  const factory WorkingHoursLine({
    required int weekday,
    required String from,
    required String to,
    required int durationMinutes,
    @JsonKey(unknownEnumValue: SessionMode.unknown) required SessionMode mode,
    SlotLocation? location,
  }) = _WorkingHoursLine;

  factory WorkingHoursLine.fromJson(Map<String, dynamic> json) => _$WorkingHoursLineFromJson(json);

  int get fromMinutes => _minutes(from);

  int get toMinutes => _minutes(to);

  static int _minutes(String time) => int.parse(time.substring(0, 2)) * 60 + int.parse(time.substring(3, 5));
}

/// What `GET /counselors/{id}/working-hours` says (docs/AVAILABILITY.md, "Time requests").
@freezed
abstract class WorkingHours with _$WorkingHours {
  const factory WorkingHours({
    required BookingCounselorRef counselor,
    @Default(false) bool acceptsRequests,
    @Default([]) List<WorkingHoursLine> hours,
    required BookingWindow bookingWindow,
    @Default(48) int answerWithinHours,
  }) = _WorkingHours;

  factory WorkingHours.fromJson(Map<String, dynamic> json) => _$WorkingHoursFromJson(json);
}

/// A request the person made. [startsAt] and [endsAt] carry Malaysian time's offset. [bookingId] is set once the counselor approved.
@freezed
abstract class TimeRequest with _$TimeRequest {
  const factory TimeRequest({
    required String id,
    @JsonKey(unknownEnumValue: RequestState.unknown) required RequestState status,
    required BookingCounselorRef counselor,
    required DateTime startsAt,
    required DateTime endsAt,
    required int durationMinutes,
    @JsonKey(unknownEnumValue: SessionMode.unknown) required SessionMode mode,
    SlotLocation? location,
    String? message,
    String? responseReason,
    required DateTime expiresAt,
    DateTime? respondedAt,
    String? bookingId,
  }) = _TimeRequest;

  factory TimeRequest.fromJson(Map<String, dynamic> json) => _$TimeRequestFromJson(json);

  factory TimeRequest.fromResponse(Map<String, dynamic> json) => TimeRequest.fromJson(Map<String, dynamic>.from(json['request'] as Map));
}

class RequestsPage {
  const RequestsPage({required this.items, required this.page, required this.lastPage});

  factory RequestsPage.fromJson(Map<String, dynamic> json) => RequestsPage(
        items: [for (final item in (json['data'] as List? ?? const [])) TimeRequest.fromJson(Map<String, dynamic>.from(item as Map))],
        page: (json['page'] as num?)?.toInt() ?? 1,
        lastPage: (json['last_page'] as num?)?.toInt() ?? 1,
      );

  final List<TimeRequest> items;
  final int page;
  final int lastPage;
}
