// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'booking_preview.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$BookingCounselorInfo {

 String get id; String get displayName; bool get verified;
/// Create a copy of BookingCounselorInfo
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$BookingCounselorInfoCopyWith<BookingCounselorInfo> get copyWith => _$BookingCounselorInfoCopyWithImpl<BookingCounselorInfo>(this as BookingCounselorInfo, _$identity);

  /// Serializes this BookingCounselorInfo to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is BookingCounselorInfo&&(identical(other.id, id) || other.id == id)&&(identical(other.displayName, displayName) || other.displayName == displayName)&&(identical(other.verified, verified) || other.verified == verified));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,displayName,verified);

@override
String toString() {
  return 'BookingCounselorInfo(id: $id, displayName: $displayName, verified: $verified)';
}


}

/// @nodoc
abstract mixin class $BookingCounselorInfoCopyWith<$Res>  {
  factory $BookingCounselorInfoCopyWith(BookingCounselorInfo value, $Res Function(BookingCounselorInfo) _then) = _$BookingCounselorInfoCopyWithImpl;
@useResult
$Res call({
 String id, String displayName, bool verified
});




}
/// @nodoc
class _$BookingCounselorInfoCopyWithImpl<$Res>
    implements $BookingCounselorInfoCopyWith<$Res> {
  _$BookingCounselorInfoCopyWithImpl(this._self, this._then);

  final BookingCounselorInfo _self;
  final $Res Function(BookingCounselorInfo) _then;

/// Create a copy of BookingCounselorInfo
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? displayName = null,Object? verified = null,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,displayName: null == displayName ? _self.displayName : displayName // ignore: cast_nullable_to_non_nullable
as String,verified: null == verified ? _self.verified : verified // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [BookingCounselorInfo].
extension BookingCounselorInfoPatterns on BookingCounselorInfo {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _BookingCounselorInfo value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _BookingCounselorInfo() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _BookingCounselorInfo value)  $default,){
final _that = this;
switch (_that) {
case _BookingCounselorInfo():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _BookingCounselorInfo value)?  $default,){
final _that = this;
switch (_that) {
case _BookingCounselorInfo() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String id,  String displayName,  bool verified)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _BookingCounselorInfo() when $default != null:
return $default(_that.id,_that.displayName,_that.verified);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String id,  String displayName,  bool verified)  $default,) {final _that = this;
switch (_that) {
case _BookingCounselorInfo():
return $default(_that.id,_that.displayName,_that.verified);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String id,  String displayName,  bool verified)?  $default,) {final _that = this;
switch (_that) {
case _BookingCounselorInfo() when $default != null:
return $default(_that.id,_that.displayName,_that.verified);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _BookingCounselorInfo implements BookingCounselorInfo {
  const _BookingCounselorInfo({required this.id, required this.displayName, this.verified = true});
  factory _BookingCounselorInfo.fromJson(Map<String, dynamic> json) => _$BookingCounselorInfoFromJson(json);

@override final  String id;
@override final  String displayName;
@override@JsonKey() final  bool verified;

/// Create a copy of BookingCounselorInfo
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$BookingCounselorInfoCopyWith<_BookingCounselorInfo> get copyWith => __$BookingCounselorInfoCopyWithImpl<_BookingCounselorInfo>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$BookingCounselorInfoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _BookingCounselorInfo&&(identical(other.id, id) || other.id == id)&&(identical(other.displayName, displayName) || other.displayName == displayName)&&(identical(other.verified, verified) || other.verified == verified));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,displayName,verified);

@override
String toString() {
  return 'BookingCounselorInfo(id: $id, displayName: $displayName, verified: $verified)';
}


}

/// @nodoc
abstract mixin class _$BookingCounselorInfoCopyWith<$Res> implements $BookingCounselorInfoCopyWith<$Res> {
  factory _$BookingCounselorInfoCopyWith(_BookingCounselorInfo value, $Res Function(_BookingCounselorInfo) _then) = __$BookingCounselorInfoCopyWithImpl;
@override @useResult
$Res call({
 String id, String displayName, bool verified
});




}
/// @nodoc
class __$BookingCounselorInfoCopyWithImpl<$Res>
    implements _$BookingCounselorInfoCopyWith<$Res> {
  __$BookingCounselorInfoCopyWithImpl(this._self, this._then);

  final _BookingCounselorInfo _self;
  final $Res Function(_BookingCounselorInfo) _then;

/// Create a copy of BookingCounselorInfo
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? displayName = null,Object? verified = null,}) {
  return _then(_BookingCounselorInfo(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,displayName: null == displayName ? _self.displayName : displayName // ignore: cast_nullable_to_non_nullable
as String,verified: null == verified ? _self.verified : verified // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}


/// @nodoc
mixin _$BookingDetails {

 String get slot; BookingCounselorInfo get counselor; DateTime get startsAt; DateTime get endsAt; int get durationMinutes;@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode get mode; SlotLocation? get location;/// Null when the deadline has already passed: the session cannot be cancelled after booking.
 DateTime? get cancellationDeadlineAt; int get cancellationHours; int get maxReschedules; int get meetingLinkVisibleMinutes;/// Only said when the person holds the cap or one below it.
 int? get upcomingSessions; int? get maxUpcomingSessions;
/// Create a copy of BookingDetails
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$BookingDetailsCopyWith<BookingDetails> get copyWith => _$BookingDetailsCopyWithImpl<BookingDetails>(this as BookingDetails, _$identity);

  /// Serializes this BookingDetails to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is BookingDetails&&(identical(other.slot, slot) || other.slot == slot)&&(identical(other.counselor, counselor) || other.counselor == counselor)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.location, location) || other.location == location)&&(identical(other.cancellationDeadlineAt, cancellationDeadlineAt) || other.cancellationDeadlineAt == cancellationDeadlineAt)&&(identical(other.cancellationHours, cancellationHours) || other.cancellationHours == cancellationHours)&&(identical(other.maxReschedules, maxReschedules) || other.maxReschedules == maxReschedules)&&(identical(other.meetingLinkVisibleMinutes, meetingLinkVisibleMinutes) || other.meetingLinkVisibleMinutes == meetingLinkVisibleMinutes)&&(identical(other.upcomingSessions, upcomingSessions) || other.upcomingSessions == upcomingSessions)&&(identical(other.maxUpcomingSessions, maxUpcomingSessions) || other.maxUpcomingSessions == maxUpcomingSessions));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,slot,counselor,startsAt,endsAt,durationMinutes,mode,location,cancellationDeadlineAt,cancellationHours,maxReschedules,meetingLinkVisibleMinutes,upcomingSessions,maxUpcomingSessions);

@override
String toString() {
  return 'BookingDetails(slot: $slot, counselor: $counselor, startsAt: $startsAt, endsAt: $endsAt, durationMinutes: $durationMinutes, mode: $mode, location: $location, cancellationDeadlineAt: $cancellationDeadlineAt, cancellationHours: $cancellationHours, maxReschedules: $maxReschedules, meetingLinkVisibleMinutes: $meetingLinkVisibleMinutes, upcomingSessions: $upcomingSessions, maxUpcomingSessions: $maxUpcomingSessions)';
}


}

/// @nodoc
abstract mixin class $BookingDetailsCopyWith<$Res>  {
  factory $BookingDetailsCopyWith(BookingDetails value, $Res Function(BookingDetails) _then) = _$BookingDetailsCopyWithImpl;
@useResult
$Res call({
 String slot, BookingCounselorInfo counselor, DateTime startsAt, DateTime endsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode, SlotLocation? location, DateTime? cancellationDeadlineAt, int cancellationHours, int maxReschedules, int meetingLinkVisibleMinutes, int? upcomingSessions, int? maxUpcomingSessions
});


$BookingCounselorInfoCopyWith<$Res> get counselor;$SlotLocationCopyWith<$Res>? get location;

}
/// @nodoc
class _$BookingDetailsCopyWithImpl<$Res>
    implements $BookingDetailsCopyWith<$Res> {
  _$BookingDetailsCopyWithImpl(this._self, this._then);

  final BookingDetails _self;
  final $Res Function(BookingDetails) _then;

/// Create a copy of BookingDetails
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? slot = null,Object? counselor = null,Object? startsAt = null,Object? endsAt = null,Object? durationMinutes = null,Object? mode = null,Object? location = freezed,Object? cancellationDeadlineAt = freezed,Object? cancellationHours = null,Object? maxReschedules = null,Object? meetingLinkVisibleMinutes = null,Object? upcomingSessions = freezed,Object? maxUpcomingSessions = freezed,}) {
  return _then(_self.copyWith(
slot: null == slot ? _self.slot : slot // ignore: cast_nullable_to_non_nullable
as String,counselor: null == counselor ? _self.counselor : counselor // ignore: cast_nullable_to_non_nullable
as BookingCounselorInfo,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,endsAt: null == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,cancellationDeadlineAt: freezed == cancellationDeadlineAt ? _self.cancellationDeadlineAt : cancellationDeadlineAt // ignore: cast_nullable_to_non_nullable
as DateTime?,cancellationHours: null == cancellationHours ? _self.cancellationHours : cancellationHours // ignore: cast_nullable_to_non_nullable
as int,maxReschedules: null == maxReschedules ? _self.maxReschedules : maxReschedules // ignore: cast_nullable_to_non_nullable
as int,meetingLinkVisibleMinutes: null == meetingLinkVisibleMinutes ? _self.meetingLinkVisibleMinutes : meetingLinkVisibleMinutes // ignore: cast_nullable_to_non_nullable
as int,upcomingSessions: freezed == upcomingSessions ? _self.upcomingSessions : upcomingSessions // ignore: cast_nullable_to_non_nullable
as int?,maxUpcomingSessions: freezed == maxUpcomingSessions ? _self.maxUpcomingSessions : maxUpcomingSessions // ignore: cast_nullable_to_non_nullable
as int?,
  ));
}
/// Create a copy of BookingDetails
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingCounselorInfoCopyWith<$Res> get counselor {
  
  return $BookingCounselorInfoCopyWith<$Res>(_self.counselor, (value) {
    return _then(_self.copyWith(counselor: value));
  });
}/// Create a copy of BookingDetails
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SlotLocationCopyWith<$Res>? get location {
    if (_self.location == null) {
    return null;
  }

  return $SlotLocationCopyWith<$Res>(_self.location!, (value) {
    return _then(_self.copyWith(location: value));
  });
}
}


/// Adds pattern-matching-related methods to [BookingDetails].
extension BookingDetailsPatterns on BookingDetails {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _BookingDetails value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _BookingDetails() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _BookingDetails value)  $default,){
final _that = this;
switch (_that) {
case _BookingDetails():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _BookingDetails value)?  $default,){
final _that = this;
switch (_that) {
case _BookingDetails() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String slot,  BookingCounselorInfo counselor,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  SlotLocation? location,  DateTime? cancellationDeadlineAt,  int cancellationHours,  int maxReschedules,  int meetingLinkVisibleMinutes,  int? upcomingSessions,  int? maxUpcomingSessions)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _BookingDetails() when $default != null:
return $default(_that.slot,_that.counselor,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.location,_that.cancellationDeadlineAt,_that.cancellationHours,_that.maxReschedules,_that.meetingLinkVisibleMinutes,_that.upcomingSessions,_that.maxUpcomingSessions);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String slot,  BookingCounselorInfo counselor,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  SlotLocation? location,  DateTime? cancellationDeadlineAt,  int cancellationHours,  int maxReschedules,  int meetingLinkVisibleMinutes,  int? upcomingSessions,  int? maxUpcomingSessions)  $default,) {final _that = this;
switch (_that) {
case _BookingDetails():
return $default(_that.slot,_that.counselor,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.location,_that.cancellationDeadlineAt,_that.cancellationHours,_that.maxReschedules,_that.meetingLinkVisibleMinutes,_that.upcomingSessions,_that.maxUpcomingSessions);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String slot,  BookingCounselorInfo counselor,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  SlotLocation? location,  DateTime? cancellationDeadlineAt,  int cancellationHours,  int maxReschedules,  int meetingLinkVisibleMinutes,  int? upcomingSessions,  int? maxUpcomingSessions)?  $default,) {final _that = this;
switch (_that) {
case _BookingDetails() when $default != null:
return $default(_that.slot,_that.counselor,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.location,_that.cancellationDeadlineAt,_that.cancellationHours,_that.maxReschedules,_that.meetingLinkVisibleMinutes,_that.upcomingSessions,_that.maxUpcomingSessions);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _BookingDetails extends BookingDetails {
  const _BookingDetails({required this.slot, required this.counselor, required this.startsAt, required this.endsAt, required this.durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown) required this.mode, this.location, this.cancellationDeadlineAt, required this.cancellationHours, required this.maxReschedules, required this.meetingLinkVisibleMinutes, this.upcomingSessions, this.maxUpcomingSessions}): super._();
  factory _BookingDetails.fromJson(Map<String, dynamic> json) => _$BookingDetailsFromJson(json);

@override final  String slot;
@override final  BookingCounselorInfo counselor;
@override final  DateTime startsAt;
@override final  DateTime endsAt;
@override final  int durationMinutes;
@override@JsonKey(unknownEnumValue: SessionMode.unknown) final  SessionMode mode;
@override final  SlotLocation? location;
/// Null when the deadline has already passed: the session cannot be cancelled after booking.
@override final  DateTime? cancellationDeadlineAt;
@override final  int cancellationHours;
@override final  int maxReschedules;
@override final  int meetingLinkVisibleMinutes;
/// Only said when the person holds the cap or one below it.
@override final  int? upcomingSessions;
@override final  int? maxUpcomingSessions;

/// Create a copy of BookingDetails
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$BookingDetailsCopyWith<_BookingDetails> get copyWith => __$BookingDetailsCopyWithImpl<_BookingDetails>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$BookingDetailsToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _BookingDetails&&(identical(other.slot, slot) || other.slot == slot)&&(identical(other.counselor, counselor) || other.counselor == counselor)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.location, location) || other.location == location)&&(identical(other.cancellationDeadlineAt, cancellationDeadlineAt) || other.cancellationDeadlineAt == cancellationDeadlineAt)&&(identical(other.cancellationHours, cancellationHours) || other.cancellationHours == cancellationHours)&&(identical(other.maxReschedules, maxReschedules) || other.maxReschedules == maxReschedules)&&(identical(other.meetingLinkVisibleMinutes, meetingLinkVisibleMinutes) || other.meetingLinkVisibleMinutes == meetingLinkVisibleMinutes)&&(identical(other.upcomingSessions, upcomingSessions) || other.upcomingSessions == upcomingSessions)&&(identical(other.maxUpcomingSessions, maxUpcomingSessions) || other.maxUpcomingSessions == maxUpcomingSessions));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,slot,counselor,startsAt,endsAt,durationMinutes,mode,location,cancellationDeadlineAt,cancellationHours,maxReschedules,meetingLinkVisibleMinutes,upcomingSessions,maxUpcomingSessions);

@override
String toString() {
  return 'BookingDetails(slot: $slot, counselor: $counselor, startsAt: $startsAt, endsAt: $endsAt, durationMinutes: $durationMinutes, mode: $mode, location: $location, cancellationDeadlineAt: $cancellationDeadlineAt, cancellationHours: $cancellationHours, maxReschedules: $maxReschedules, meetingLinkVisibleMinutes: $meetingLinkVisibleMinutes, upcomingSessions: $upcomingSessions, maxUpcomingSessions: $maxUpcomingSessions)';
}


}

/// @nodoc
abstract mixin class _$BookingDetailsCopyWith<$Res> implements $BookingDetailsCopyWith<$Res> {
  factory _$BookingDetailsCopyWith(_BookingDetails value, $Res Function(_BookingDetails) _then) = __$BookingDetailsCopyWithImpl;
@override @useResult
$Res call({
 String slot, BookingCounselorInfo counselor, DateTime startsAt, DateTime endsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode, SlotLocation? location, DateTime? cancellationDeadlineAt, int cancellationHours, int maxReschedules, int meetingLinkVisibleMinutes, int? upcomingSessions, int? maxUpcomingSessions
});


@override $BookingCounselorInfoCopyWith<$Res> get counselor;@override $SlotLocationCopyWith<$Res>? get location;

}
/// @nodoc
class __$BookingDetailsCopyWithImpl<$Res>
    implements _$BookingDetailsCopyWith<$Res> {
  __$BookingDetailsCopyWithImpl(this._self, this._then);

  final _BookingDetails _self;
  final $Res Function(_BookingDetails) _then;

/// Create a copy of BookingDetails
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? slot = null,Object? counselor = null,Object? startsAt = null,Object? endsAt = null,Object? durationMinutes = null,Object? mode = null,Object? location = freezed,Object? cancellationDeadlineAt = freezed,Object? cancellationHours = null,Object? maxReschedules = null,Object? meetingLinkVisibleMinutes = null,Object? upcomingSessions = freezed,Object? maxUpcomingSessions = freezed,}) {
  return _then(_BookingDetails(
slot: null == slot ? _self.slot : slot // ignore: cast_nullable_to_non_nullable
as String,counselor: null == counselor ? _self.counselor : counselor // ignore: cast_nullable_to_non_nullable
as BookingCounselorInfo,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,endsAt: null == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,cancellationDeadlineAt: freezed == cancellationDeadlineAt ? _self.cancellationDeadlineAt : cancellationDeadlineAt // ignore: cast_nullable_to_non_nullable
as DateTime?,cancellationHours: null == cancellationHours ? _self.cancellationHours : cancellationHours // ignore: cast_nullable_to_non_nullable
as int,maxReschedules: null == maxReschedules ? _self.maxReschedules : maxReschedules // ignore: cast_nullable_to_non_nullable
as int,meetingLinkVisibleMinutes: null == meetingLinkVisibleMinutes ? _self.meetingLinkVisibleMinutes : meetingLinkVisibleMinutes // ignore: cast_nullable_to_non_nullable
as int,upcomingSessions: freezed == upcomingSessions ? _self.upcomingSessions : upcomingSessions // ignore: cast_nullable_to_non_nullable
as int?,maxUpcomingSessions: freezed == maxUpcomingSessions ? _self.maxUpcomingSessions : maxUpcomingSessions // ignore: cast_nullable_to_non_nullable
as int?,
  ));
}

/// Create a copy of BookingDetails
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingCounselorInfoCopyWith<$Res> get counselor {
  
  return $BookingCounselorInfoCopyWith<$Res>(_self.counselor, (value) {
    return _then(_self.copyWith(counselor: value));
  });
}/// Create a copy of BookingDetails
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SlotLocationCopyWith<$Res>? get location {
    if (_self.location == null) {
    return null;
  }

  return $SlotLocationCopyWith<$Res>(_self.location!, (value) {
    return _then(_self.copyWith(location: value));
  });
}
}


/// @nodoc
mixin _$CreatedBooking {

 String get id; String get status; DateTime get startsAt; DateTime get endsAt; int get durationMinutes;@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode get mode; String get counselorName; SlotLocation? get location;
/// Create a copy of CreatedBooking
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$CreatedBookingCopyWith<CreatedBooking> get copyWith => _$CreatedBookingCopyWithImpl<CreatedBooking>(this as CreatedBooking, _$identity);

  /// Serializes this CreatedBooking to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is CreatedBooking&&(identical(other.id, id) || other.id == id)&&(identical(other.status, status) || other.status == status)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.counselorName, counselorName) || other.counselorName == counselorName)&&(identical(other.location, location) || other.location == location));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,status,startsAt,endsAt,durationMinutes,mode,counselorName,location);

@override
String toString() {
  return 'CreatedBooking(id: $id, status: $status, startsAt: $startsAt, endsAt: $endsAt, durationMinutes: $durationMinutes, mode: $mode, counselorName: $counselorName, location: $location)';
}


}

/// @nodoc
abstract mixin class $CreatedBookingCopyWith<$Res>  {
  factory $CreatedBookingCopyWith(CreatedBooking value, $Res Function(CreatedBooking) _then) = _$CreatedBookingCopyWithImpl;
@useResult
$Res call({
 String id, String status, DateTime startsAt, DateTime endsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode, String counselorName, SlotLocation? location
});


$SlotLocationCopyWith<$Res>? get location;

}
/// @nodoc
class _$CreatedBookingCopyWithImpl<$Res>
    implements $CreatedBookingCopyWith<$Res> {
  _$CreatedBookingCopyWithImpl(this._self, this._then);

  final CreatedBooking _self;
  final $Res Function(CreatedBooking) _then;

/// Create a copy of CreatedBooking
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? status = null,Object? startsAt = null,Object? endsAt = null,Object? durationMinutes = null,Object? mode = null,Object? counselorName = null,Object? location = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,endsAt: null == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,counselorName: null == counselorName ? _self.counselorName : counselorName // ignore: cast_nullable_to_non_nullable
as String,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,
  ));
}
/// Create a copy of CreatedBooking
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SlotLocationCopyWith<$Res>? get location {
    if (_self.location == null) {
    return null;
  }

  return $SlotLocationCopyWith<$Res>(_self.location!, (value) {
    return _then(_self.copyWith(location: value));
  });
}
}


/// Adds pattern-matching-related methods to [CreatedBooking].
extension CreatedBookingPatterns on CreatedBooking {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _CreatedBooking value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _CreatedBooking() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _CreatedBooking value)  $default,){
final _that = this;
switch (_that) {
case _CreatedBooking():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _CreatedBooking value)?  $default,){
final _that = this;
switch (_that) {
case _CreatedBooking() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String id,  String status,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  String counselorName,  SlotLocation? location)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _CreatedBooking() when $default != null:
return $default(_that.id,_that.status,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.counselorName,_that.location);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String id,  String status,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  String counselorName,  SlotLocation? location)  $default,) {final _that = this;
switch (_that) {
case _CreatedBooking():
return $default(_that.id,_that.status,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.counselorName,_that.location);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String id,  String status,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  String counselorName,  SlotLocation? location)?  $default,) {final _that = this;
switch (_that) {
case _CreatedBooking() when $default != null:
return $default(_that.id,_that.status,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.counselorName,_that.location);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _CreatedBooking implements CreatedBooking {
  const _CreatedBooking({required this.id, required this.status, required this.startsAt, required this.endsAt, required this.durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown) required this.mode, required this.counselorName, this.location});
  factory _CreatedBooking.fromJson(Map<String, dynamic> json) => _$CreatedBookingFromJson(json);

@override final  String id;
@override final  String status;
@override final  DateTime startsAt;
@override final  DateTime endsAt;
@override final  int durationMinutes;
@override@JsonKey(unknownEnumValue: SessionMode.unknown) final  SessionMode mode;
@override final  String counselorName;
@override final  SlotLocation? location;

/// Create a copy of CreatedBooking
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$CreatedBookingCopyWith<_CreatedBooking> get copyWith => __$CreatedBookingCopyWithImpl<_CreatedBooking>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$CreatedBookingToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _CreatedBooking&&(identical(other.id, id) || other.id == id)&&(identical(other.status, status) || other.status == status)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.counselorName, counselorName) || other.counselorName == counselorName)&&(identical(other.location, location) || other.location == location));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,status,startsAt,endsAt,durationMinutes,mode,counselorName,location);

@override
String toString() {
  return 'CreatedBooking(id: $id, status: $status, startsAt: $startsAt, endsAt: $endsAt, durationMinutes: $durationMinutes, mode: $mode, counselorName: $counselorName, location: $location)';
}


}

/// @nodoc
abstract mixin class _$CreatedBookingCopyWith<$Res> implements $CreatedBookingCopyWith<$Res> {
  factory _$CreatedBookingCopyWith(_CreatedBooking value, $Res Function(_CreatedBooking) _then) = __$CreatedBookingCopyWithImpl;
@override @useResult
$Res call({
 String id, String status, DateTime startsAt, DateTime endsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode, String counselorName, SlotLocation? location
});


@override $SlotLocationCopyWith<$Res>? get location;

}
/// @nodoc
class __$CreatedBookingCopyWithImpl<$Res>
    implements _$CreatedBookingCopyWith<$Res> {
  __$CreatedBookingCopyWithImpl(this._self, this._then);

  final _CreatedBooking _self;
  final $Res Function(_CreatedBooking) _then;

/// Create a copy of CreatedBooking
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? status = null,Object? startsAt = null,Object? endsAt = null,Object? durationMinutes = null,Object? mode = null,Object? counselorName = null,Object? location = freezed,}) {
  return _then(_CreatedBooking(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,endsAt: null == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,counselorName: null == counselorName ? _self.counselorName : counselorName // ignore: cast_nullable_to_non_nullable
as String,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,
  ));
}

/// Create a copy of CreatedBooking
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SlotLocationCopyWith<$Res>? get location {
    if (_self.location == null) {
    return null;
  }

  return $SlotLocationCopyWith<$Res>(_self.location!, (value) {
    return _then(_self.copyWith(location: value));
  });
}
}

// dart format on
