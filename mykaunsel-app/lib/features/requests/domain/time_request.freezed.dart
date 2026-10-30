// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'time_request.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$WorkingHoursLine {

 int get weekday; String get from; String get to; int get durationMinutes;@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode get mode; SlotLocation? get location;
/// Create a copy of WorkingHoursLine
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$WorkingHoursLineCopyWith<WorkingHoursLine> get copyWith => _$WorkingHoursLineCopyWithImpl<WorkingHoursLine>(this as WorkingHoursLine, _$identity);

  /// Serializes this WorkingHoursLine to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is WorkingHoursLine&&(identical(other.weekday, weekday) || other.weekday == weekday)&&(identical(other.from, from) || other.from == from)&&(identical(other.to, to) || other.to == to)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.location, location) || other.location == location));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,weekday,from,to,durationMinutes,mode,location);

@override
String toString() {
  return 'WorkingHoursLine(weekday: $weekday, from: $from, to: $to, durationMinutes: $durationMinutes, mode: $mode, location: $location)';
}


}

/// @nodoc
abstract mixin class $WorkingHoursLineCopyWith<$Res>  {
  factory $WorkingHoursLineCopyWith(WorkingHoursLine value, $Res Function(WorkingHoursLine) _then) = _$WorkingHoursLineCopyWithImpl;
@useResult
$Res call({
 int weekday, String from, String to, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode, SlotLocation? location
});


$SlotLocationCopyWith<$Res>? get location;

}
/// @nodoc
class _$WorkingHoursLineCopyWithImpl<$Res>
    implements $WorkingHoursLineCopyWith<$Res> {
  _$WorkingHoursLineCopyWithImpl(this._self, this._then);

  final WorkingHoursLine _self;
  final $Res Function(WorkingHoursLine) _then;

/// Create a copy of WorkingHoursLine
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? weekday = null,Object? from = null,Object? to = null,Object? durationMinutes = null,Object? mode = null,Object? location = freezed,}) {
  return _then(_self.copyWith(
weekday: null == weekday ? _self.weekday : weekday // ignore: cast_nullable_to_non_nullable
as int,from: null == from ? _self.from : from // ignore: cast_nullable_to_non_nullable
as String,to: null == to ? _self.to : to // ignore: cast_nullable_to_non_nullable
as String,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,
  ));
}
/// Create a copy of WorkingHoursLine
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


/// Adds pattern-matching-related methods to [WorkingHoursLine].
extension WorkingHoursLinePatterns on WorkingHoursLine {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _WorkingHoursLine value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _WorkingHoursLine() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _WorkingHoursLine value)  $default,){
final _that = this;
switch (_that) {
case _WorkingHoursLine():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _WorkingHoursLine value)?  $default,){
final _that = this;
switch (_that) {
case _WorkingHoursLine() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int weekday,  String from,  String to,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  SlotLocation? location)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _WorkingHoursLine() when $default != null:
return $default(_that.weekday,_that.from,_that.to,_that.durationMinutes,_that.mode,_that.location);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int weekday,  String from,  String to,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  SlotLocation? location)  $default,) {final _that = this;
switch (_that) {
case _WorkingHoursLine():
return $default(_that.weekday,_that.from,_that.to,_that.durationMinutes,_that.mode,_that.location);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int weekday,  String from,  String to,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  SlotLocation? location)?  $default,) {final _that = this;
switch (_that) {
case _WorkingHoursLine() when $default != null:
return $default(_that.weekday,_that.from,_that.to,_that.durationMinutes,_that.mode,_that.location);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _WorkingHoursLine extends WorkingHoursLine {
  const _WorkingHoursLine({required this.weekday, required this.from, required this.to, required this.durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown) required this.mode, this.location}): super._();
  factory _WorkingHoursLine.fromJson(Map<String, dynamic> json) => _$WorkingHoursLineFromJson(json);

@override final  int weekday;
@override final  String from;
@override final  String to;
@override final  int durationMinutes;
@override@JsonKey(unknownEnumValue: SessionMode.unknown) final  SessionMode mode;
@override final  SlotLocation? location;

/// Create a copy of WorkingHoursLine
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$WorkingHoursLineCopyWith<_WorkingHoursLine> get copyWith => __$WorkingHoursLineCopyWithImpl<_WorkingHoursLine>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$WorkingHoursLineToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _WorkingHoursLine&&(identical(other.weekday, weekday) || other.weekday == weekday)&&(identical(other.from, from) || other.from == from)&&(identical(other.to, to) || other.to == to)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.location, location) || other.location == location));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,weekday,from,to,durationMinutes,mode,location);

@override
String toString() {
  return 'WorkingHoursLine(weekday: $weekday, from: $from, to: $to, durationMinutes: $durationMinutes, mode: $mode, location: $location)';
}


}

/// @nodoc
abstract mixin class _$WorkingHoursLineCopyWith<$Res> implements $WorkingHoursLineCopyWith<$Res> {
  factory _$WorkingHoursLineCopyWith(_WorkingHoursLine value, $Res Function(_WorkingHoursLine) _then) = __$WorkingHoursLineCopyWithImpl;
@override @useResult
$Res call({
 int weekday, String from, String to, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode, SlotLocation? location
});


@override $SlotLocationCopyWith<$Res>? get location;

}
/// @nodoc
class __$WorkingHoursLineCopyWithImpl<$Res>
    implements _$WorkingHoursLineCopyWith<$Res> {
  __$WorkingHoursLineCopyWithImpl(this._self, this._then);

  final _WorkingHoursLine _self;
  final $Res Function(_WorkingHoursLine) _then;

/// Create a copy of WorkingHoursLine
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? weekday = null,Object? from = null,Object? to = null,Object? durationMinutes = null,Object? mode = null,Object? location = freezed,}) {
  return _then(_WorkingHoursLine(
weekday: null == weekday ? _self.weekday : weekday // ignore: cast_nullable_to_non_nullable
as int,from: null == from ? _self.from : from // ignore: cast_nullable_to_non_nullable
as String,to: null == to ? _self.to : to // ignore: cast_nullable_to_non_nullable
as String,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,
  ));
}

/// Create a copy of WorkingHoursLine
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
mixin _$WorkingHours {

 BookingCounselorRef get counselor; bool get acceptsRequests; List<WorkingHoursLine> get hours; BookingWindow get bookingWindow; int get answerWithinHours;
/// Create a copy of WorkingHours
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$WorkingHoursCopyWith<WorkingHours> get copyWith => _$WorkingHoursCopyWithImpl<WorkingHours>(this as WorkingHours, _$identity);

  /// Serializes this WorkingHours to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is WorkingHours&&(identical(other.counselor, counselor) || other.counselor == counselor)&&(identical(other.acceptsRequests, acceptsRequests) || other.acceptsRequests == acceptsRequests)&&const DeepCollectionEquality().equals(other.hours, hours)&&(identical(other.bookingWindow, bookingWindow) || other.bookingWindow == bookingWindow)&&(identical(other.answerWithinHours, answerWithinHours) || other.answerWithinHours == answerWithinHours));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,counselor,acceptsRequests,const DeepCollectionEquality().hash(hours),bookingWindow,answerWithinHours);

@override
String toString() {
  return 'WorkingHours(counselor: $counselor, acceptsRequests: $acceptsRequests, hours: $hours, bookingWindow: $bookingWindow, answerWithinHours: $answerWithinHours)';
}


}

/// @nodoc
abstract mixin class $WorkingHoursCopyWith<$Res>  {
  factory $WorkingHoursCopyWith(WorkingHours value, $Res Function(WorkingHours) _then) = _$WorkingHoursCopyWithImpl;
@useResult
$Res call({
 BookingCounselorRef counselor, bool acceptsRequests, List<WorkingHoursLine> hours, BookingWindow bookingWindow, int answerWithinHours
});


$BookingCounselorRefCopyWith<$Res> get counselor;$BookingWindowCopyWith<$Res> get bookingWindow;

}
/// @nodoc
class _$WorkingHoursCopyWithImpl<$Res>
    implements $WorkingHoursCopyWith<$Res> {
  _$WorkingHoursCopyWithImpl(this._self, this._then);

  final WorkingHours _self;
  final $Res Function(WorkingHours) _then;

/// Create a copy of WorkingHours
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? counselor = null,Object? acceptsRequests = null,Object? hours = null,Object? bookingWindow = null,Object? answerWithinHours = null,}) {
  return _then(_self.copyWith(
counselor: null == counselor ? _self.counselor : counselor // ignore: cast_nullable_to_non_nullable
as BookingCounselorRef,acceptsRequests: null == acceptsRequests ? _self.acceptsRequests : acceptsRequests // ignore: cast_nullable_to_non_nullable
as bool,hours: null == hours ? _self.hours : hours // ignore: cast_nullable_to_non_nullable
as List<WorkingHoursLine>,bookingWindow: null == bookingWindow ? _self.bookingWindow : bookingWindow // ignore: cast_nullable_to_non_nullable
as BookingWindow,answerWithinHours: null == answerWithinHours ? _self.answerWithinHours : answerWithinHours // ignore: cast_nullable_to_non_nullable
as int,
  ));
}
/// Create a copy of WorkingHours
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingCounselorRefCopyWith<$Res> get counselor {
  
  return $BookingCounselorRefCopyWith<$Res>(_self.counselor, (value) {
    return _then(_self.copyWith(counselor: value));
  });
}/// Create a copy of WorkingHours
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingWindowCopyWith<$Res> get bookingWindow {
  
  return $BookingWindowCopyWith<$Res>(_self.bookingWindow, (value) {
    return _then(_self.copyWith(bookingWindow: value));
  });
}
}


/// Adds pattern-matching-related methods to [WorkingHours].
extension WorkingHoursPatterns on WorkingHours {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _WorkingHours value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _WorkingHours() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _WorkingHours value)  $default,){
final _that = this;
switch (_that) {
case _WorkingHours():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _WorkingHours value)?  $default,){
final _that = this;
switch (_that) {
case _WorkingHours() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( BookingCounselorRef counselor,  bool acceptsRequests,  List<WorkingHoursLine> hours,  BookingWindow bookingWindow,  int answerWithinHours)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _WorkingHours() when $default != null:
return $default(_that.counselor,_that.acceptsRequests,_that.hours,_that.bookingWindow,_that.answerWithinHours);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( BookingCounselorRef counselor,  bool acceptsRequests,  List<WorkingHoursLine> hours,  BookingWindow bookingWindow,  int answerWithinHours)  $default,) {final _that = this;
switch (_that) {
case _WorkingHours():
return $default(_that.counselor,_that.acceptsRequests,_that.hours,_that.bookingWindow,_that.answerWithinHours);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( BookingCounselorRef counselor,  bool acceptsRequests,  List<WorkingHoursLine> hours,  BookingWindow bookingWindow,  int answerWithinHours)?  $default,) {final _that = this;
switch (_that) {
case _WorkingHours() when $default != null:
return $default(_that.counselor,_that.acceptsRequests,_that.hours,_that.bookingWindow,_that.answerWithinHours);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _WorkingHours implements WorkingHours {
  const _WorkingHours({required this.counselor, this.acceptsRequests = false, final  List<WorkingHoursLine> hours = const [], required this.bookingWindow, this.answerWithinHours = 48}): _hours = hours;
  factory _WorkingHours.fromJson(Map<String, dynamic> json) => _$WorkingHoursFromJson(json);

@override final  BookingCounselorRef counselor;
@override@JsonKey() final  bool acceptsRequests;
 final  List<WorkingHoursLine> _hours;
@override@JsonKey() List<WorkingHoursLine> get hours {
  if (_hours is EqualUnmodifiableListView) return _hours;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_hours);
}

@override final  BookingWindow bookingWindow;
@override@JsonKey() final  int answerWithinHours;

/// Create a copy of WorkingHours
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$WorkingHoursCopyWith<_WorkingHours> get copyWith => __$WorkingHoursCopyWithImpl<_WorkingHours>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$WorkingHoursToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _WorkingHours&&(identical(other.counselor, counselor) || other.counselor == counselor)&&(identical(other.acceptsRequests, acceptsRequests) || other.acceptsRequests == acceptsRequests)&&const DeepCollectionEquality().equals(other._hours, _hours)&&(identical(other.bookingWindow, bookingWindow) || other.bookingWindow == bookingWindow)&&(identical(other.answerWithinHours, answerWithinHours) || other.answerWithinHours == answerWithinHours));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,counselor,acceptsRequests,const DeepCollectionEquality().hash(_hours),bookingWindow,answerWithinHours);

@override
String toString() {
  return 'WorkingHours(counselor: $counselor, acceptsRequests: $acceptsRequests, hours: $hours, bookingWindow: $bookingWindow, answerWithinHours: $answerWithinHours)';
}


}

/// @nodoc
abstract mixin class _$WorkingHoursCopyWith<$Res> implements $WorkingHoursCopyWith<$Res> {
  factory _$WorkingHoursCopyWith(_WorkingHours value, $Res Function(_WorkingHours) _then) = __$WorkingHoursCopyWithImpl;
@override @useResult
$Res call({
 BookingCounselorRef counselor, bool acceptsRequests, List<WorkingHoursLine> hours, BookingWindow bookingWindow, int answerWithinHours
});


@override $BookingCounselorRefCopyWith<$Res> get counselor;@override $BookingWindowCopyWith<$Res> get bookingWindow;

}
/// @nodoc
class __$WorkingHoursCopyWithImpl<$Res>
    implements _$WorkingHoursCopyWith<$Res> {
  __$WorkingHoursCopyWithImpl(this._self, this._then);

  final _WorkingHours _self;
  final $Res Function(_WorkingHours) _then;

/// Create a copy of WorkingHours
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? counselor = null,Object? acceptsRequests = null,Object? hours = null,Object? bookingWindow = null,Object? answerWithinHours = null,}) {
  return _then(_WorkingHours(
counselor: null == counselor ? _self.counselor : counselor // ignore: cast_nullable_to_non_nullable
as BookingCounselorRef,acceptsRequests: null == acceptsRequests ? _self.acceptsRequests : acceptsRequests // ignore: cast_nullable_to_non_nullable
as bool,hours: null == hours ? _self._hours : hours // ignore: cast_nullable_to_non_nullable
as List<WorkingHoursLine>,bookingWindow: null == bookingWindow ? _self.bookingWindow : bookingWindow // ignore: cast_nullable_to_non_nullable
as BookingWindow,answerWithinHours: null == answerWithinHours ? _self.answerWithinHours : answerWithinHours // ignore: cast_nullable_to_non_nullable
as int,
  ));
}

/// Create a copy of WorkingHours
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingCounselorRefCopyWith<$Res> get counselor {
  
  return $BookingCounselorRefCopyWith<$Res>(_self.counselor, (value) {
    return _then(_self.copyWith(counselor: value));
  });
}/// Create a copy of WorkingHours
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingWindowCopyWith<$Res> get bookingWindow {
  
  return $BookingWindowCopyWith<$Res>(_self.bookingWindow, (value) {
    return _then(_self.copyWith(bookingWindow: value));
  });
}
}


/// @nodoc
mixin _$TimeRequest {

 String get id;@JsonKey(unknownEnumValue: RequestState.unknown) RequestState get status; BookingCounselorRef get counselor; DateTime get startsAt; DateTime get endsAt; int get durationMinutes;@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode get mode; SlotLocation? get location; String? get message; String? get responseReason; DateTime get expiresAt; DateTime? get respondedAt; String? get bookingId;
/// Create a copy of TimeRequest
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$TimeRequestCopyWith<TimeRequest> get copyWith => _$TimeRequestCopyWithImpl<TimeRequest>(this as TimeRequest, _$identity);

  /// Serializes this TimeRequest to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is TimeRequest&&(identical(other.id, id) || other.id == id)&&(identical(other.status, status) || other.status == status)&&(identical(other.counselor, counselor) || other.counselor == counselor)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.location, location) || other.location == location)&&(identical(other.message, message) || other.message == message)&&(identical(other.responseReason, responseReason) || other.responseReason == responseReason)&&(identical(other.expiresAt, expiresAt) || other.expiresAt == expiresAt)&&(identical(other.respondedAt, respondedAt) || other.respondedAt == respondedAt)&&(identical(other.bookingId, bookingId) || other.bookingId == bookingId));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,status,counselor,startsAt,endsAt,durationMinutes,mode,location,message,responseReason,expiresAt,respondedAt,bookingId);

@override
String toString() {
  return 'TimeRequest(id: $id, status: $status, counselor: $counselor, startsAt: $startsAt, endsAt: $endsAt, durationMinutes: $durationMinutes, mode: $mode, location: $location, message: $message, responseReason: $responseReason, expiresAt: $expiresAt, respondedAt: $respondedAt, bookingId: $bookingId)';
}


}

/// @nodoc
abstract mixin class $TimeRequestCopyWith<$Res>  {
  factory $TimeRequestCopyWith(TimeRequest value, $Res Function(TimeRequest) _then) = _$TimeRequestCopyWithImpl;
@useResult
$Res call({
 String id,@JsonKey(unknownEnumValue: RequestState.unknown) RequestState status, BookingCounselorRef counselor, DateTime startsAt, DateTime endsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode, SlotLocation? location, String? message, String? responseReason, DateTime expiresAt, DateTime? respondedAt, String? bookingId
});


$BookingCounselorRefCopyWith<$Res> get counselor;$SlotLocationCopyWith<$Res>? get location;

}
/// @nodoc
class _$TimeRequestCopyWithImpl<$Res>
    implements $TimeRequestCopyWith<$Res> {
  _$TimeRequestCopyWithImpl(this._self, this._then);

  final TimeRequest _self;
  final $Res Function(TimeRequest) _then;

/// Create a copy of TimeRequest
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? status = null,Object? counselor = null,Object? startsAt = null,Object? endsAt = null,Object? durationMinutes = null,Object? mode = null,Object? location = freezed,Object? message = freezed,Object? responseReason = freezed,Object? expiresAt = null,Object? respondedAt = freezed,Object? bookingId = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as RequestState,counselor: null == counselor ? _self.counselor : counselor // ignore: cast_nullable_to_non_nullable
as BookingCounselorRef,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,endsAt: null == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,message: freezed == message ? _self.message : message // ignore: cast_nullable_to_non_nullable
as String?,responseReason: freezed == responseReason ? _self.responseReason : responseReason // ignore: cast_nullable_to_non_nullable
as String?,expiresAt: null == expiresAt ? _self.expiresAt : expiresAt // ignore: cast_nullable_to_non_nullable
as DateTime,respondedAt: freezed == respondedAt ? _self.respondedAt : respondedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,bookingId: freezed == bookingId ? _self.bookingId : bookingId // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}
/// Create a copy of TimeRequest
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingCounselorRefCopyWith<$Res> get counselor {
  
  return $BookingCounselorRefCopyWith<$Res>(_self.counselor, (value) {
    return _then(_self.copyWith(counselor: value));
  });
}/// Create a copy of TimeRequest
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


/// Adds pattern-matching-related methods to [TimeRequest].
extension TimeRequestPatterns on TimeRequest {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _TimeRequest value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _TimeRequest() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _TimeRequest value)  $default,){
final _that = this;
switch (_that) {
case _TimeRequest():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _TimeRequest value)?  $default,){
final _that = this;
switch (_that) {
case _TimeRequest() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String id, @JsonKey(unknownEnumValue: RequestState.unknown)  RequestState status,  BookingCounselorRef counselor,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  SlotLocation? location,  String? message,  String? responseReason,  DateTime expiresAt,  DateTime? respondedAt,  String? bookingId)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _TimeRequest() when $default != null:
return $default(_that.id,_that.status,_that.counselor,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.location,_that.message,_that.responseReason,_that.expiresAt,_that.respondedAt,_that.bookingId);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String id, @JsonKey(unknownEnumValue: RequestState.unknown)  RequestState status,  BookingCounselorRef counselor,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  SlotLocation? location,  String? message,  String? responseReason,  DateTime expiresAt,  DateTime? respondedAt,  String? bookingId)  $default,) {final _that = this;
switch (_that) {
case _TimeRequest():
return $default(_that.id,_that.status,_that.counselor,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.location,_that.message,_that.responseReason,_that.expiresAt,_that.respondedAt,_that.bookingId);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String id, @JsonKey(unknownEnumValue: RequestState.unknown)  RequestState status,  BookingCounselorRef counselor,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  SlotLocation? location,  String? message,  String? responseReason,  DateTime expiresAt,  DateTime? respondedAt,  String? bookingId)?  $default,) {final _that = this;
switch (_that) {
case _TimeRequest() when $default != null:
return $default(_that.id,_that.status,_that.counselor,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.location,_that.message,_that.responseReason,_that.expiresAt,_that.respondedAt,_that.bookingId);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _TimeRequest implements TimeRequest {
  const _TimeRequest({required this.id, @JsonKey(unknownEnumValue: RequestState.unknown) required this.status, required this.counselor, required this.startsAt, required this.endsAt, required this.durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown) required this.mode, this.location, this.message, this.responseReason, required this.expiresAt, this.respondedAt, this.bookingId});
  factory _TimeRequest.fromJson(Map<String, dynamic> json) => _$TimeRequestFromJson(json);

@override final  String id;
@override@JsonKey(unknownEnumValue: RequestState.unknown) final  RequestState status;
@override final  BookingCounselorRef counselor;
@override final  DateTime startsAt;
@override final  DateTime endsAt;
@override final  int durationMinutes;
@override@JsonKey(unknownEnumValue: SessionMode.unknown) final  SessionMode mode;
@override final  SlotLocation? location;
@override final  String? message;
@override final  String? responseReason;
@override final  DateTime expiresAt;
@override final  DateTime? respondedAt;
@override final  String? bookingId;

/// Create a copy of TimeRequest
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$TimeRequestCopyWith<_TimeRequest> get copyWith => __$TimeRequestCopyWithImpl<_TimeRequest>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$TimeRequestToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _TimeRequest&&(identical(other.id, id) || other.id == id)&&(identical(other.status, status) || other.status == status)&&(identical(other.counselor, counselor) || other.counselor == counselor)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.location, location) || other.location == location)&&(identical(other.message, message) || other.message == message)&&(identical(other.responseReason, responseReason) || other.responseReason == responseReason)&&(identical(other.expiresAt, expiresAt) || other.expiresAt == expiresAt)&&(identical(other.respondedAt, respondedAt) || other.respondedAt == respondedAt)&&(identical(other.bookingId, bookingId) || other.bookingId == bookingId));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,status,counselor,startsAt,endsAt,durationMinutes,mode,location,message,responseReason,expiresAt,respondedAt,bookingId);

@override
String toString() {
  return 'TimeRequest(id: $id, status: $status, counselor: $counselor, startsAt: $startsAt, endsAt: $endsAt, durationMinutes: $durationMinutes, mode: $mode, location: $location, message: $message, responseReason: $responseReason, expiresAt: $expiresAt, respondedAt: $respondedAt, bookingId: $bookingId)';
}


}

/// @nodoc
abstract mixin class _$TimeRequestCopyWith<$Res> implements $TimeRequestCopyWith<$Res> {
  factory _$TimeRequestCopyWith(_TimeRequest value, $Res Function(_TimeRequest) _then) = __$TimeRequestCopyWithImpl;
@override @useResult
$Res call({
 String id,@JsonKey(unknownEnumValue: RequestState.unknown) RequestState status, BookingCounselorRef counselor, DateTime startsAt, DateTime endsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode, SlotLocation? location, String? message, String? responseReason, DateTime expiresAt, DateTime? respondedAt, String? bookingId
});


@override $BookingCounselorRefCopyWith<$Res> get counselor;@override $SlotLocationCopyWith<$Res>? get location;

}
/// @nodoc
class __$TimeRequestCopyWithImpl<$Res>
    implements _$TimeRequestCopyWith<$Res> {
  __$TimeRequestCopyWithImpl(this._self, this._then);

  final _TimeRequest _self;
  final $Res Function(_TimeRequest) _then;

/// Create a copy of TimeRequest
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? status = null,Object? counselor = null,Object? startsAt = null,Object? endsAt = null,Object? durationMinutes = null,Object? mode = null,Object? location = freezed,Object? message = freezed,Object? responseReason = freezed,Object? expiresAt = null,Object? respondedAt = freezed,Object? bookingId = freezed,}) {
  return _then(_TimeRequest(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as RequestState,counselor: null == counselor ? _self.counselor : counselor // ignore: cast_nullable_to_non_nullable
as BookingCounselorRef,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,endsAt: null == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,message: freezed == message ? _self.message : message // ignore: cast_nullable_to_non_nullable
as String?,responseReason: freezed == responseReason ? _self.responseReason : responseReason // ignore: cast_nullable_to_non_nullable
as String?,expiresAt: null == expiresAt ? _self.expiresAt : expiresAt // ignore: cast_nullable_to_non_nullable
as DateTime,respondedAt: freezed == respondedAt ? _self.respondedAt : respondedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,bookingId: freezed == bookingId ? _self.bookingId : bookingId // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

/// Create a copy of TimeRequest
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingCounselorRefCopyWith<$Res> get counselor {
  
  return $BookingCounselorRefCopyWith<$Res>(_self.counselor, (value) {
    return _then(_self.copyWith(counselor: value));
  });
}/// Create a copy of TimeRequest
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
