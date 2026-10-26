import 'package:freezed_annotation/freezed_annotation.dart';

part 'counselor.freezed.dart';
part 'counselor.g.dart';

/// How a session is held. `unknown` is what an older app makes of a kind the server adds later, so it never crashes.
@JsonEnum(fieldRename: FieldRename.snake)
enum SessionMode {
  online,
  physical,
  @JsonValue('__unknown__')
  unknown,
}

@JsonEnum(fieldRename: FieldRename.snake)
enum ContextType {
  independent,
  organization,
  @JsonValue('__unknown__')
  unknown,
}

/// Where a counselor works, as far as THIS person may know: independent, or an organization by name.
@freezed
abstract class CounselorContext with _$CounselorContext {
  const factory CounselorContext({
    @JsonKey(unknownEnumValue: ContextType.unknown) required ContextType type,
    String? organization,
  }) = _CounselorContext;

  factory CounselorContext.fromJson(Map<String, dynamic> json) => _$CounselorContextFromJson(json);
}

/// City and state only. The street address is never sent to the app.
@freezed
abstract class CounselorLocation with _$CounselorLocation {
  const factory CounselorLocation({required String city, required String state}) = _CounselorLocation;

  factory CounselorLocation.fromJson(Map<String, dynamic> json) => _$CounselorLocationFromJson(json);
}

/// One free time. [startsAt] carries Malaysian time's offset (+08:00).
@freezed
abstract class CounselorSlot with _$CounselorSlot {
  const factory CounselorSlot({
    required DateTime startsAt,
    required int durationMinutes,
    @JsonKey(unknownEnumValue: SessionMode.unknown) required SessionMode sessionMode,
    String? city,
    String? state,
    CounselorContext? context,
  }) = _CounselorSlot;

  factory CounselorSlot.fromJson(Map<String, dynamic> json) => _$CounselorSlotFromJson(json);
}

/// A counselor as the server lets this person see them: the search card, and (with the extra fields) the profile page.
/// See docs/PUBLIC_COUNSELOR_DATA.md on the server side for what is deliberately NOT here (KB/PA numbers, address…).
@freezed
abstract class Counselor with _$Counselor {
  const factory Counselor({
    required String id,
    required String name,
    @Default(true) bool verified,
    @Default([]) List<CounselorContext> contexts,
    @Default([]) List<String> specializations,
    @Default(0) int specializationsCount,
    @Default([]) List<String> languages,
    @JsonKey(unknownEnumValue: SessionMode.unknown) @Default([]) List<SessionMode> sessionModes,
    @Default([]) List<CounselorLocation> locations,
    CounselorSlot? nextAvailable,

    /// Profile page only.
    bool? acceptsRequests,
    @Default([]) List<CounselorSlot> upcomingSlots,
  }) = _Counselor;

  factory Counselor.fromJson(Map<String, dynamic> json) => _$CounselorFromJson(json);
}
