// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'my_booking.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$BookingCounselorRef {

/// The counselor's public id (for "Book again"); null if they no longer have a profile.
 String? get id; String get displayName;
/// Create a copy of BookingCounselorRef
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$BookingCounselorRefCopyWith<BookingCounselorRef> get copyWith => _$BookingCounselorRefCopyWithImpl<BookingCounselorRef>(this as BookingCounselorRef, _$identity);

  /// Serializes this BookingCounselorRef to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is BookingCounselorRef&&(identical(other.id, id) || other.id == id)&&(identical(other.displayName, displayName) || other.displayName == displayName));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,displayName);

@override
String toString() {
  return 'BookingCounselorRef(id: $id, displayName: $displayName)';
}


}

/// @nodoc
abstract mixin class $BookingCounselorRefCopyWith<$Res>  {
  factory $BookingCounselorRefCopyWith(BookingCounselorRef value, $Res Function(BookingCounselorRef) _then) = _$BookingCounselorRefCopyWithImpl;
@useResult
$Res call({
 String? id, String displayName
});




}
/// @nodoc
class _$BookingCounselorRefCopyWithImpl<$Res>
    implements $BookingCounselorRefCopyWith<$Res> {
  _$BookingCounselorRefCopyWithImpl(this._self, this._then);

  final BookingCounselorRef _self;
  final $Res Function(BookingCounselorRef) _then;

/// Create a copy of BookingCounselorRef
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = freezed,Object? displayName = null,}) {
  return _then(_self.copyWith(
id: freezed == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String?,displayName: null == displayName ? _self.displayName : displayName // ignore: cast_nullable_to_non_nullable
as String,
  ));
}

}


/// Adds pattern-matching-related methods to [BookingCounselorRef].
extension BookingCounselorRefPatterns on BookingCounselorRef {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _BookingCounselorRef value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _BookingCounselorRef() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _BookingCounselorRef value)  $default,){
final _that = this;
switch (_that) {
case _BookingCounselorRef():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _BookingCounselorRef value)?  $default,){
final _that = this;
switch (_that) {
case _BookingCounselorRef() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String? id,  String displayName)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _BookingCounselorRef() when $default != null:
return $default(_that.id,_that.displayName);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String? id,  String displayName)  $default,) {final _that = this;
switch (_that) {
case _BookingCounselorRef():
return $default(_that.id,_that.displayName);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String? id,  String displayName)?  $default,) {final _that = this;
switch (_that) {
case _BookingCounselorRef() when $default != null:
return $default(_that.id,_that.displayName);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _BookingCounselorRef implements BookingCounselorRef {
  const _BookingCounselorRef({this.id, this.displayName = ''});
  factory _BookingCounselorRef.fromJson(Map<String, dynamic> json) => _$BookingCounselorRefFromJson(json);

/// The counselor's public id (for "Book again"); null if they no longer have a profile.
@override final  String? id;
@override@JsonKey() final  String displayName;

/// Create a copy of BookingCounselorRef
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$BookingCounselorRefCopyWith<_BookingCounselorRef> get copyWith => __$BookingCounselorRefCopyWithImpl<_BookingCounselorRef>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$BookingCounselorRefToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _BookingCounselorRef&&(identical(other.id, id) || other.id == id)&&(identical(other.displayName, displayName) || other.displayName == displayName));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,displayName);

@override
String toString() {
  return 'BookingCounselorRef(id: $id, displayName: $displayName)';
}


}

/// @nodoc
abstract mixin class _$BookingCounselorRefCopyWith<$Res> implements $BookingCounselorRefCopyWith<$Res> {
  factory _$BookingCounselorRefCopyWith(_BookingCounselorRef value, $Res Function(_BookingCounselorRef) _then) = __$BookingCounselorRefCopyWithImpl;
@override @useResult
$Res call({
 String? id, String displayName
});




}
/// @nodoc
class __$BookingCounselorRefCopyWithImpl<$Res>
    implements _$BookingCounselorRefCopyWith<$Res> {
  __$BookingCounselorRefCopyWithImpl(this._self, this._then);

  final _BookingCounselorRef _self;
  final $Res Function(_BookingCounselorRef) _then;

/// Create a copy of BookingCounselorRef
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = freezed,Object? displayName = null,}) {
  return _then(_BookingCounselorRef(
id: freezed == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String?,displayName: null == displayName ? _self.displayName : displayName // ignore: cast_nullable_to_non_nullable
as String,
  ));
}


}


/// @nodoc
mixin _$MyBooking {

 String get id;@JsonKey(unknownEnumValue: BookingState.unknown) BookingState get status; bool get startingSoon; DateTime get startsAt; DateTime get endsAt; int get durationMinutes;@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode get mode; BookingCounselorRef get counselor; SlotLocation? get location;
/// Create a copy of MyBooking
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$MyBookingCopyWith<MyBooking> get copyWith => _$MyBookingCopyWithImpl<MyBooking>(this as MyBooking, _$identity);

  /// Serializes this MyBooking to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is MyBooking&&(identical(other.id, id) || other.id == id)&&(identical(other.status, status) || other.status == status)&&(identical(other.startingSoon, startingSoon) || other.startingSoon == startingSoon)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.counselor, counselor) || other.counselor == counselor)&&(identical(other.location, location) || other.location == location));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,status,startingSoon,startsAt,endsAt,durationMinutes,mode,counselor,location);

@override
String toString() {
  return 'MyBooking(id: $id, status: $status, startingSoon: $startingSoon, startsAt: $startsAt, endsAt: $endsAt, durationMinutes: $durationMinutes, mode: $mode, counselor: $counselor, location: $location)';
}


}

/// @nodoc
abstract mixin class $MyBookingCopyWith<$Res>  {
  factory $MyBookingCopyWith(MyBooking value, $Res Function(MyBooking) _then) = _$MyBookingCopyWithImpl;
@useResult
$Res call({
 String id,@JsonKey(unknownEnumValue: BookingState.unknown) BookingState status, bool startingSoon, DateTime startsAt, DateTime endsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode, BookingCounselorRef counselor, SlotLocation? location
});


$BookingCounselorRefCopyWith<$Res> get counselor;$SlotLocationCopyWith<$Res>? get location;

}
/// @nodoc
class _$MyBookingCopyWithImpl<$Res>
    implements $MyBookingCopyWith<$Res> {
  _$MyBookingCopyWithImpl(this._self, this._then);

  final MyBooking _self;
  final $Res Function(MyBooking) _then;

/// Create a copy of MyBooking
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? status = null,Object? startingSoon = null,Object? startsAt = null,Object? endsAt = null,Object? durationMinutes = null,Object? mode = null,Object? counselor = null,Object? location = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as BookingState,startingSoon: null == startingSoon ? _self.startingSoon : startingSoon // ignore: cast_nullable_to_non_nullable
as bool,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,endsAt: null == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,counselor: null == counselor ? _self.counselor : counselor // ignore: cast_nullable_to_non_nullable
as BookingCounselorRef,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,
  ));
}
/// Create a copy of MyBooking
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingCounselorRefCopyWith<$Res> get counselor {
  
  return $BookingCounselorRefCopyWith<$Res>(_self.counselor, (value) {
    return _then(_self.copyWith(counselor: value));
  });
}/// Create a copy of MyBooking
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


/// Adds pattern-matching-related methods to [MyBooking].
extension MyBookingPatterns on MyBooking {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _MyBooking value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _MyBooking() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _MyBooking value)  $default,){
final _that = this;
switch (_that) {
case _MyBooking():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _MyBooking value)?  $default,){
final _that = this;
switch (_that) {
case _MyBooking() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String id, @JsonKey(unknownEnumValue: BookingState.unknown)  BookingState status,  bool startingSoon,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  BookingCounselorRef counselor,  SlotLocation? location)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _MyBooking() when $default != null:
return $default(_that.id,_that.status,_that.startingSoon,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.counselor,_that.location);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String id, @JsonKey(unknownEnumValue: BookingState.unknown)  BookingState status,  bool startingSoon,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  BookingCounselorRef counselor,  SlotLocation? location)  $default,) {final _that = this;
switch (_that) {
case _MyBooking():
return $default(_that.id,_that.status,_that.startingSoon,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.counselor,_that.location);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String id, @JsonKey(unknownEnumValue: BookingState.unknown)  BookingState status,  bool startingSoon,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  BookingCounselorRef counselor,  SlotLocation? location)?  $default,) {final _that = this;
switch (_that) {
case _MyBooking() when $default != null:
return $default(_that.id,_that.status,_that.startingSoon,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.counselor,_that.location);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _MyBooking implements MyBooking {
  const _MyBooking({required this.id, @JsonKey(unknownEnumValue: BookingState.unknown) required this.status, this.startingSoon = false, required this.startsAt, required this.endsAt, required this.durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown) required this.mode, required this.counselor, this.location});
  factory _MyBooking.fromJson(Map<String, dynamic> json) => _$MyBookingFromJson(json);

@override final  String id;
@override@JsonKey(unknownEnumValue: BookingState.unknown) final  BookingState status;
@override@JsonKey() final  bool startingSoon;
@override final  DateTime startsAt;
@override final  DateTime endsAt;
@override final  int durationMinutes;
@override@JsonKey(unknownEnumValue: SessionMode.unknown) final  SessionMode mode;
@override final  BookingCounselorRef counselor;
@override final  SlotLocation? location;

/// Create a copy of MyBooking
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$MyBookingCopyWith<_MyBooking> get copyWith => __$MyBookingCopyWithImpl<_MyBooking>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$MyBookingToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _MyBooking&&(identical(other.id, id) || other.id == id)&&(identical(other.status, status) || other.status == status)&&(identical(other.startingSoon, startingSoon) || other.startingSoon == startingSoon)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.counselor, counselor) || other.counselor == counselor)&&(identical(other.location, location) || other.location == location));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,status,startingSoon,startsAt,endsAt,durationMinutes,mode,counselor,location);

@override
String toString() {
  return 'MyBooking(id: $id, status: $status, startingSoon: $startingSoon, startsAt: $startsAt, endsAt: $endsAt, durationMinutes: $durationMinutes, mode: $mode, counselor: $counselor, location: $location)';
}


}

/// @nodoc
abstract mixin class _$MyBookingCopyWith<$Res> implements $MyBookingCopyWith<$Res> {
  factory _$MyBookingCopyWith(_MyBooking value, $Res Function(_MyBooking) _then) = __$MyBookingCopyWithImpl;
@override @useResult
$Res call({
 String id,@JsonKey(unknownEnumValue: BookingState.unknown) BookingState status, bool startingSoon, DateTime startsAt, DateTime endsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode, BookingCounselorRef counselor, SlotLocation? location
});


@override $BookingCounselorRefCopyWith<$Res> get counselor;@override $SlotLocationCopyWith<$Res>? get location;

}
/// @nodoc
class __$MyBookingCopyWithImpl<$Res>
    implements _$MyBookingCopyWith<$Res> {
  __$MyBookingCopyWithImpl(this._self, this._then);

  final _MyBooking _self;
  final $Res Function(_MyBooking) _then;

/// Create a copy of MyBooking
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? status = null,Object? startingSoon = null,Object? startsAt = null,Object? endsAt = null,Object? durationMinutes = null,Object? mode = null,Object? counselor = null,Object? location = freezed,}) {
  return _then(_MyBooking(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as BookingState,startingSoon: null == startingSoon ? _self.startingSoon : startingSoon // ignore: cast_nullable_to_non_nullable
as bool,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,endsAt: null == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,counselor: null == counselor ? _self.counselor : counselor // ignore: cast_nullable_to_non_nullable
as BookingCounselorRef,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,
  ));
}

/// Create a copy of MyBooking
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingCounselorRefCopyWith<$Res> get counselor {
  
  return $BookingCounselorRefCopyWith<$Res>(_self.counselor, (value) {
    return _then(_self.copyWith(counselor: value));
  });
}/// Create a copy of MyBooking
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
mixin _$MeetingInfo {

@JsonKey(unknownEnumValue: MeetingState.unknown) MeetingState get state; DateTime get opensAt; DateTime get closesAt; String? get provider;/// Present only when [state] is `available`.
 String? get url;
/// Create a copy of MeetingInfo
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$MeetingInfoCopyWith<MeetingInfo> get copyWith => _$MeetingInfoCopyWithImpl<MeetingInfo>(this as MeetingInfo, _$identity);

  /// Serializes this MeetingInfo to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is MeetingInfo&&(identical(other.state, state) || other.state == state)&&(identical(other.opensAt, opensAt) || other.opensAt == opensAt)&&(identical(other.closesAt, closesAt) || other.closesAt == closesAt)&&(identical(other.provider, provider) || other.provider == provider)&&(identical(other.url, url) || other.url == url));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,state,opensAt,closesAt,provider,url);

@override
String toString() {
  return 'MeetingInfo(state: $state, opensAt: $opensAt, closesAt: $closesAt, provider: $provider, url: $url)';
}


}

/// @nodoc
abstract mixin class $MeetingInfoCopyWith<$Res>  {
  factory $MeetingInfoCopyWith(MeetingInfo value, $Res Function(MeetingInfo) _then) = _$MeetingInfoCopyWithImpl;
@useResult
$Res call({
@JsonKey(unknownEnumValue: MeetingState.unknown) MeetingState state, DateTime opensAt, DateTime closesAt, String? provider, String? url
});




}
/// @nodoc
class _$MeetingInfoCopyWithImpl<$Res>
    implements $MeetingInfoCopyWith<$Res> {
  _$MeetingInfoCopyWithImpl(this._self, this._then);

  final MeetingInfo _self;
  final $Res Function(MeetingInfo) _then;

/// Create a copy of MeetingInfo
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? state = null,Object? opensAt = null,Object? closesAt = null,Object? provider = freezed,Object? url = freezed,}) {
  return _then(_self.copyWith(
state: null == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as MeetingState,opensAt: null == opensAt ? _self.opensAt : opensAt // ignore: cast_nullable_to_non_nullable
as DateTime,closesAt: null == closesAt ? _self.closesAt : closesAt // ignore: cast_nullable_to_non_nullable
as DateTime,provider: freezed == provider ? _self.provider : provider // ignore: cast_nullable_to_non_nullable
as String?,url: freezed == url ? _self.url : url // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [MeetingInfo].
extension MeetingInfoPatterns on MeetingInfo {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _MeetingInfo value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _MeetingInfo() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _MeetingInfo value)  $default,){
final _that = this;
switch (_that) {
case _MeetingInfo():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _MeetingInfo value)?  $default,){
final _that = this;
switch (_that) {
case _MeetingInfo() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function(@JsonKey(unknownEnumValue: MeetingState.unknown)  MeetingState state,  DateTime opensAt,  DateTime closesAt,  String? provider,  String? url)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _MeetingInfo() when $default != null:
return $default(_that.state,_that.opensAt,_that.closesAt,_that.provider,_that.url);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function(@JsonKey(unknownEnumValue: MeetingState.unknown)  MeetingState state,  DateTime opensAt,  DateTime closesAt,  String? provider,  String? url)  $default,) {final _that = this;
switch (_that) {
case _MeetingInfo():
return $default(_that.state,_that.opensAt,_that.closesAt,_that.provider,_that.url);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function(@JsonKey(unknownEnumValue: MeetingState.unknown)  MeetingState state,  DateTime opensAt,  DateTime closesAt,  String? provider,  String? url)?  $default,) {final _that = this;
switch (_that) {
case _MeetingInfo() when $default != null:
return $default(_that.state,_that.opensAt,_that.closesAt,_that.provider,_that.url);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _MeetingInfo implements MeetingInfo {
  const _MeetingInfo({@JsonKey(unknownEnumValue: MeetingState.unknown) required this.state, required this.opensAt, required this.closesAt, this.provider, this.url});
  factory _MeetingInfo.fromJson(Map<String, dynamic> json) => _$MeetingInfoFromJson(json);

@override@JsonKey(unknownEnumValue: MeetingState.unknown) final  MeetingState state;
@override final  DateTime opensAt;
@override final  DateTime closesAt;
@override final  String? provider;
/// Present only when [state] is `available`.
@override final  String? url;

/// Create a copy of MeetingInfo
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$MeetingInfoCopyWith<_MeetingInfo> get copyWith => __$MeetingInfoCopyWithImpl<_MeetingInfo>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$MeetingInfoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _MeetingInfo&&(identical(other.state, state) || other.state == state)&&(identical(other.opensAt, opensAt) || other.opensAt == opensAt)&&(identical(other.closesAt, closesAt) || other.closesAt == closesAt)&&(identical(other.provider, provider) || other.provider == provider)&&(identical(other.url, url) || other.url == url));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,state,opensAt,closesAt,provider,url);

@override
String toString() {
  return 'MeetingInfo(state: $state, opensAt: $opensAt, closesAt: $closesAt, provider: $provider, url: $url)';
}


}

/// @nodoc
abstract mixin class _$MeetingInfoCopyWith<$Res> implements $MeetingInfoCopyWith<$Res> {
  factory _$MeetingInfoCopyWith(_MeetingInfo value, $Res Function(_MeetingInfo) _then) = __$MeetingInfoCopyWithImpl;
@override @useResult
$Res call({
@JsonKey(unknownEnumValue: MeetingState.unknown) MeetingState state, DateTime opensAt, DateTime closesAt, String? provider, String? url
});




}
/// @nodoc
class __$MeetingInfoCopyWithImpl<$Res>
    implements _$MeetingInfoCopyWith<$Res> {
  __$MeetingInfoCopyWithImpl(this._self, this._then);

  final _MeetingInfo _self;
  final $Res Function(_MeetingInfo) _then;

/// Create a copy of MeetingInfo
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? state = null,Object? opensAt = null,Object? closesAt = null,Object? provider = freezed,Object? url = freezed,}) {
  return _then(_MeetingInfo(
state: null == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as MeetingState,opensAt: null == opensAt ? _self.opensAt : opensAt // ignore: cast_nullable_to_non_nullable
as DateTime,closesAt: null == closesAt ? _self.closesAt : closesAt // ignore: cast_nullable_to_non_nullable
as DateTime,provider: freezed == provider ? _self.provider : provider // ignore: cast_nullable_to_non_nullable
as String?,url: freezed == url ? _self.url : url // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}


/// @nodoc
mixin _$BookingPlace {

 String get name; String get address; String get city; String get state; String get postcode; double? get latitude; double? get longitude;
/// Create a copy of BookingPlace
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$BookingPlaceCopyWith<BookingPlace> get copyWith => _$BookingPlaceCopyWithImpl<BookingPlace>(this as BookingPlace, _$identity);

  /// Serializes this BookingPlace to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is BookingPlace&&(identical(other.name, name) || other.name == name)&&(identical(other.address, address) || other.address == address)&&(identical(other.city, city) || other.city == city)&&(identical(other.state, state) || other.state == state)&&(identical(other.postcode, postcode) || other.postcode == postcode)&&(identical(other.latitude, latitude) || other.latitude == latitude)&&(identical(other.longitude, longitude) || other.longitude == longitude));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,name,address,city,state,postcode,latitude,longitude);

@override
String toString() {
  return 'BookingPlace(name: $name, address: $address, city: $city, state: $state, postcode: $postcode, latitude: $latitude, longitude: $longitude)';
}


}

/// @nodoc
abstract mixin class $BookingPlaceCopyWith<$Res>  {
  factory $BookingPlaceCopyWith(BookingPlace value, $Res Function(BookingPlace) _then) = _$BookingPlaceCopyWithImpl;
@useResult
$Res call({
 String name, String address, String city, String state, String postcode, double? latitude, double? longitude
});




}
/// @nodoc
class _$BookingPlaceCopyWithImpl<$Res>
    implements $BookingPlaceCopyWith<$Res> {
  _$BookingPlaceCopyWithImpl(this._self, this._then);

  final BookingPlace _self;
  final $Res Function(BookingPlace) _then;

/// Create a copy of BookingPlace
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? name = null,Object? address = null,Object? city = null,Object? state = null,Object? postcode = null,Object? latitude = freezed,Object? longitude = freezed,}) {
  return _then(_self.copyWith(
name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,address: null == address ? _self.address : address // ignore: cast_nullable_to_non_nullable
as String,city: null == city ? _self.city : city // ignore: cast_nullable_to_non_nullable
as String,state: null == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as String,postcode: null == postcode ? _self.postcode : postcode // ignore: cast_nullable_to_non_nullable
as String,latitude: freezed == latitude ? _self.latitude : latitude // ignore: cast_nullable_to_non_nullable
as double?,longitude: freezed == longitude ? _self.longitude : longitude // ignore: cast_nullable_to_non_nullable
as double?,
  ));
}

}


/// Adds pattern-matching-related methods to [BookingPlace].
extension BookingPlacePatterns on BookingPlace {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _BookingPlace value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _BookingPlace() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _BookingPlace value)  $default,){
final _that = this;
switch (_that) {
case _BookingPlace():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _BookingPlace value)?  $default,){
final _that = this;
switch (_that) {
case _BookingPlace() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String name,  String address,  String city,  String state,  String postcode,  double? latitude,  double? longitude)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _BookingPlace() when $default != null:
return $default(_that.name,_that.address,_that.city,_that.state,_that.postcode,_that.latitude,_that.longitude);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String name,  String address,  String city,  String state,  String postcode,  double? latitude,  double? longitude)  $default,) {final _that = this;
switch (_that) {
case _BookingPlace():
return $default(_that.name,_that.address,_that.city,_that.state,_that.postcode,_that.latitude,_that.longitude);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String name,  String address,  String city,  String state,  String postcode,  double? latitude,  double? longitude)?  $default,) {final _that = this;
switch (_that) {
case _BookingPlace() when $default != null:
return $default(_that.name,_that.address,_that.city,_that.state,_that.postcode,_that.latitude,_that.longitude);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _BookingPlace implements BookingPlace {
  const _BookingPlace({required this.name, required this.address, required this.city, required this.state, required this.postcode, this.latitude, this.longitude});
  factory _BookingPlace.fromJson(Map<String, dynamic> json) => _$BookingPlaceFromJson(json);

@override final  String name;
@override final  String address;
@override final  String city;
@override final  String state;
@override final  String postcode;
@override final  double? latitude;
@override final  double? longitude;

/// Create a copy of BookingPlace
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$BookingPlaceCopyWith<_BookingPlace> get copyWith => __$BookingPlaceCopyWithImpl<_BookingPlace>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$BookingPlaceToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _BookingPlace&&(identical(other.name, name) || other.name == name)&&(identical(other.address, address) || other.address == address)&&(identical(other.city, city) || other.city == city)&&(identical(other.state, state) || other.state == state)&&(identical(other.postcode, postcode) || other.postcode == postcode)&&(identical(other.latitude, latitude) || other.latitude == latitude)&&(identical(other.longitude, longitude) || other.longitude == longitude));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,name,address,city,state,postcode,latitude,longitude);

@override
String toString() {
  return 'BookingPlace(name: $name, address: $address, city: $city, state: $state, postcode: $postcode, latitude: $latitude, longitude: $longitude)';
}


}

/// @nodoc
abstract mixin class _$BookingPlaceCopyWith<$Res> implements $BookingPlaceCopyWith<$Res> {
  factory _$BookingPlaceCopyWith(_BookingPlace value, $Res Function(_BookingPlace) _then) = __$BookingPlaceCopyWithImpl;
@override @useResult
$Res call({
 String name, String address, String city, String state, String postcode, double? latitude, double? longitude
});




}
/// @nodoc
class __$BookingPlaceCopyWithImpl<$Res>
    implements _$BookingPlaceCopyWith<$Res> {
  __$BookingPlaceCopyWithImpl(this._self, this._then);

  final _BookingPlace _self;
  final $Res Function(_BookingPlace) _then;

/// Create a copy of BookingPlace
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? name = null,Object? address = null,Object? city = null,Object? state = null,Object? postcode = null,Object? latitude = freezed,Object? longitude = freezed,}) {
  return _then(_BookingPlace(
name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,address: null == address ? _self.address : address // ignore: cast_nullable_to_non_nullable
as String,city: null == city ? _self.city : city // ignore: cast_nullable_to_non_nullable
as String,state: null == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as String,postcode: null == postcode ? _self.postcode : postcode // ignore: cast_nullable_to_non_nullable
as String,latitude: freezed == latitude ? _self.latitude : latitude // ignore: cast_nullable_to_non_nullable
as double?,longitude: freezed == longitude ? _self.longitude : longitude // ignore: cast_nullable_to_non_nullable
as double?,
  ));
}


}


/// @nodoc
mixin _$MyBookingDetail {

 String get id;@JsonKey(unknownEnumValue: BookingState.unknown) BookingState get status; bool get startingSoon; DateTime get startsAt; DateTime get endsAt; int get durationMinutes;@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode get mode; BookingCounselorRef get counselor; SlotLocation? get location; int get cancellationHours;/// Null once the deadline has passed (or the booking is not confirmed).
 DateTime? get cancellationDeadlineAt; bool get canCancel; int get reschedulesLeft; MeetingInfo? get meeting; BookingPlace? get place;
/// Create a copy of MyBookingDetail
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$MyBookingDetailCopyWith<MyBookingDetail> get copyWith => _$MyBookingDetailCopyWithImpl<MyBookingDetail>(this as MyBookingDetail, _$identity);

  /// Serializes this MyBookingDetail to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is MyBookingDetail&&(identical(other.id, id) || other.id == id)&&(identical(other.status, status) || other.status == status)&&(identical(other.startingSoon, startingSoon) || other.startingSoon == startingSoon)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.counselor, counselor) || other.counselor == counselor)&&(identical(other.location, location) || other.location == location)&&(identical(other.cancellationHours, cancellationHours) || other.cancellationHours == cancellationHours)&&(identical(other.cancellationDeadlineAt, cancellationDeadlineAt) || other.cancellationDeadlineAt == cancellationDeadlineAt)&&(identical(other.canCancel, canCancel) || other.canCancel == canCancel)&&(identical(other.reschedulesLeft, reschedulesLeft) || other.reschedulesLeft == reschedulesLeft)&&(identical(other.meeting, meeting) || other.meeting == meeting)&&(identical(other.place, place) || other.place == place));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,status,startingSoon,startsAt,endsAt,durationMinutes,mode,counselor,location,cancellationHours,cancellationDeadlineAt,canCancel,reschedulesLeft,meeting,place);

@override
String toString() {
  return 'MyBookingDetail(id: $id, status: $status, startingSoon: $startingSoon, startsAt: $startsAt, endsAt: $endsAt, durationMinutes: $durationMinutes, mode: $mode, counselor: $counselor, location: $location, cancellationHours: $cancellationHours, cancellationDeadlineAt: $cancellationDeadlineAt, canCancel: $canCancel, reschedulesLeft: $reschedulesLeft, meeting: $meeting, place: $place)';
}


}

/// @nodoc
abstract mixin class $MyBookingDetailCopyWith<$Res>  {
  factory $MyBookingDetailCopyWith(MyBookingDetail value, $Res Function(MyBookingDetail) _then) = _$MyBookingDetailCopyWithImpl;
@useResult
$Res call({
 String id,@JsonKey(unknownEnumValue: BookingState.unknown) BookingState status, bool startingSoon, DateTime startsAt, DateTime endsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode, BookingCounselorRef counselor, SlotLocation? location, int cancellationHours, DateTime? cancellationDeadlineAt, bool canCancel, int reschedulesLeft, MeetingInfo? meeting, BookingPlace? place
});


$BookingCounselorRefCopyWith<$Res> get counselor;$SlotLocationCopyWith<$Res>? get location;$MeetingInfoCopyWith<$Res>? get meeting;$BookingPlaceCopyWith<$Res>? get place;

}
/// @nodoc
class _$MyBookingDetailCopyWithImpl<$Res>
    implements $MyBookingDetailCopyWith<$Res> {
  _$MyBookingDetailCopyWithImpl(this._self, this._then);

  final MyBookingDetail _self;
  final $Res Function(MyBookingDetail) _then;

/// Create a copy of MyBookingDetail
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? status = null,Object? startingSoon = null,Object? startsAt = null,Object? endsAt = null,Object? durationMinutes = null,Object? mode = null,Object? counselor = null,Object? location = freezed,Object? cancellationHours = null,Object? cancellationDeadlineAt = freezed,Object? canCancel = null,Object? reschedulesLeft = null,Object? meeting = freezed,Object? place = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as BookingState,startingSoon: null == startingSoon ? _self.startingSoon : startingSoon // ignore: cast_nullable_to_non_nullable
as bool,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,endsAt: null == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,counselor: null == counselor ? _self.counselor : counselor // ignore: cast_nullable_to_non_nullable
as BookingCounselorRef,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,cancellationHours: null == cancellationHours ? _self.cancellationHours : cancellationHours // ignore: cast_nullable_to_non_nullable
as int,cancellationDeadlineAt: freezed == cancellationDeadlineAt ? _self.cancellationDeadlineAt : cancellationDeadlineAt // ignore: cast_nullable_to_non_nullable
as DateTime?,canCancel: null == canCancel ? _self.canCancel : canCancel // ignore: cast_nullable_to_non_nullable
as bool,reschedulesLeft: null == reschedulesLeft ? _self.reschedulesLeft : reschedulesLeft // ignore: cast_nullable_to_non_nullable
as int,meeting: freezed == meeting ? _self.meeting : meeting // ignore: cast_nullable_to_non_nullable
as MeetingInfo?,place: freezed == place ? _self.place : place // ignore: cast_nullable_to_non_nullable
as BookingPlace?,
  ));
}
/// Create a copy of MyBookingDetail
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingCounselorRefCopyWith<$Res> get counselor {
  
  return $BookingCounselorRefCopyWith<$Res>(_self.counselor, (value) {
    return _then(_self.copyWith(counselor: value));
  });
}/// Create a copy of MyBookingDetail
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
}/// Create a copy of MyBookingDetail
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$MeetingInfoCopyWith<$Res>? get meeting {
    if (_self.meeting == null) {
    return null;
  }

  return $MeetingInfoCopyWith<$Res>(_self.meeting!, (value) {
    return _then(_self.copyWith(meeting: value));
  });
}/// Create a copy of MyBookingDetail
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingPlaceCopyWith<$Res>? get place {
    if (_self.place == null) {
    return null;
  }

  return $BookingPlaceCopyWith<$Res>(_self.place!, (value) {
    return _then(_self.copyWith(place: value));
  });
}
}


/// Adds pattern-matching-related methods to [MyBookingDetail].
extension MyBookingDetailPatterns on MyBookingDetail {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _MyBookingDetail value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _MyBookingDetail() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _MyBookingDetail value)  $default,){
final _that = this;
switch (_that) {
case _MyBookingDetail():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _MyBookingDetail value)?  $default,){
final _that = this;
switch (_that) {
case _MyBookingDetail() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String id, @JsonKey(unknownEnumValue: BookingState.unknown)  BookingState status,  bool startingSoon,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  BookingCounselorRef counselor,  SlotLocation? location,  int cancellationHours,  DateTime? cancellationDeadlineAt,  bool canCancel,  int reschedulesLeft,  MeetingInfo? meeting,  BookingPlace? place)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _MyBookingDetail() when $default != null:
return $default(_that.id,_that.status,_that.startingSoon,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.counselor,_that.location,_that.cancellationHours,_that.cancellationDeadlineAt,_that.canCancel,_that.reschedulesLeft,_that.meeting,_that.place);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String id, @JsonKey(unknownEnumValue: BookingState.unknown)  BookingState status,  bool startingSoon,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  BookingCounselorRef counselor,  SlotLocation? location,  int cancellationHours,  DateTime? cancellationDeadlineAt,  bool canCancel,  int reschedulesLeft,  MeetingInfo? meeting,  BookingPlace? place)  $default,) {final _that = this;
switch (_that) {
case _MyBookingDetail():
return $default(_that.id,_that.status,_that.startingSoon,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.counselor,_that.location,_that.cancellationHours,_that.cancellationDeadlineAt,_that.canCancel,_that.reschedulesLeft,_that.meeting,_that.place);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String id, @JsonKey(unknownEnumValue: BookingState.unknown)  BookingState status,  bool startingSoon,  DateTime startsAt,  DateTime endsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode,  BookingCounselorRef counselor,  SlotLocation? location,  int cancellationHours,  DateTime? cancellationDeadlineAt,  bool canCancel,  int reschedulesLeft,  MeetingInfo? meeting,  BookingPlace? place)?  $default,) {final _that = this;
switch (_that) {
case _MyBookingDetail() when $default != null:
return $default(_that.id,_that.status,_that.startingSoon,_that.startsAt,_that.endsAt,_that.durationMinutes,_that.mode,_that.counselor,_that.location,_that.cancellationHours,_that.cancellationDeadlineAt,_that.canCancel,_that.reschedulesLeft,_that.meeting,_that.place);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _MyBookingDetail extends MyBookingDetail {
  const _MyBookingDetail({required this.id, @JsonKey(unknownEnumValue: BookingState.unknown) required this.status, this.startingSoon = false, required this.startsAt, required this.endsAt, required this.durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown) required this.mode, required this.counselor, this.location, required this.cancellationHours, this.cancellationDeadlineAt, this.canCancel = false, this.reschedulesLeft = 0, this.meeting, this.place}): super._();
  factory _MyBookingDetail.fromJson(Map<String, dynamic> json) => _$MyBookingDetailFromJson(json);

@override final  String id;
@override@JsonKey(unknownEnumValue: BookingState.unknown) final  BookingState status;
@override@JsonKey() final  bool startingSoon;
@override final  DateTime startsAt;
@override final  DateTime endsAt;
@override final  int durationMinutes;
@override@JsonKey(unknownEnumValue: SessionMode.unknown) final  SessionMode mode;
@override final  BookingCounselorRef counselor;
@override final  SlotLocation? location;
@override final  int cancellationHours;
/// Null once the deadline has passed (or the booking is not confirmed).
@override final  DateTime? cancellationDeadlineAt;
@override@JsonKey() final  bool canCancel;
@override@JsonKey() final  int reschedulesLeft;
@override final  MeetingInfo? meeting;
@override final  BookingPlace? place;

/// Create a copy of MyBookingDetail
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$MyBookingDetailCopyWith<_MyBookingDetail> get copyWith => __$MyBookingDetailCopyWithImpl<_MyBookingDetail>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$MyBookingDetailToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _MyBookingDetail&&(identical(other.id, id) || other.id == id)&&(identical(other.status, status) || other.status == status)&&(identical(other.startingSoon, startingSoon) || other.startingSoon == startingSoon)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.counselor, counselor) || other.counselor == counselor)&&(identical(other.location, location) || other.location == location)&&(identical(other.cancellationHours, cancellationHours) || other.cancellationHours == cancellationHours)&&(identical(other.cancellationDeadlineAt, cancellationDeadlineAt) || other.cancellationDeadlineAt == cancellationDeadlineAt)&&(identical(other.canCancel, canCancel) || other.canCancel == canCancel)&&(identical(other.reschedulesLeft, reschedulesLeft) || other.reschedulesLeft == reschedulesLeft)&&(identical(other.meeting, meeting) || other.meeting == meeting)&&(identical(other.place, place) || other.place == place));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,status,startingSoon,startsAt,endsAt,durationMinutes,mode,counselor,location,cancellationHours,cancellationDeadlineAt,canCancel,reschedulesLeft,meeting,place);

@override
String toString() {
  return 'MyBookingDetail(id: $id, status: $status, startingSoon: $startingSoon, startsAt: $startsAt, endsAt: $endsAt, durationMinutes: $durationMinutes, mode: $mode, counselor: $counselor, location: $location, cancellationHours: $cancellationHours, cancellationDeadlineAt: $cancellationDeadlineAt, canCancel: $canCancel, reschedulesLeft: $reschedulesLeft, meeting: $meeting, place: $place)';
}


}

/// @nodoc
abstract mixin class _$MyBookingDetailCopyWith<$Res> implements $MyBookingDetailCopyWith<$Res> {
  factory _$MyBookingDetailCopyWith(_MyBookingDetail value, $Res Function(_MyBookingDetail) _then) = __$MyBookingDetailCopyWithImpl;
@override @useResult
$Res call({
 String id,@JsonKey(unknownEnumValue: BookingState.unknown) BookingState status, bool startingSoon, DateTime startsAt, DateTime endsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode, BookingCounselorRef counselor, SlotLocation? location, int cancellationHours, DateTime? cancellationDeadlineAt, bool canCancel, int reschedulesLeft, MeetingInfo? meeting, BookingPlace? place
});


@override $BookingCounselorRefCopyWith<$Res> get counselor;@override $SlotLocationCopyWith<$Res>? get location;@override $MeetingInfoCopyWith<$Res>? get meeting;@override $BookingPlaceCopyWith<$Res>? get place;

}
/// @nodoc
class __$MyBookingDetailCopyWithImpl<$Res>
    implements _$MyBookingDetailCopyWith<$Res> {
  __$MyBookingDetailCopyWithImpl(this._self, this._then);

  final _MyBookingDetail _self;
  final $Res Function(_MyBookingDetail) _then;

/// Create a copy of MyBookingDetail
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? status = null,Object? startingSoon = null,Object? startsAt = null,Object? endsAt = null,Object? durationMinutes = null,Object? mode = null,Object? counselor = null,Object? location = freezed,Object? cancellationHours = null,Object? cancellationDeadlineAt = freezed,Object? canCancel = null,Object? reschedulesLeft = null,Object? meeting = freezed,Object? place = freezed,}) {
  return _then(_MyBookingDetail(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as BookingState,startingSoon: null == startingSoon ? _self.startingSoon : startingSoon // ignore: cast_nullable_to_non_nullable
as bool,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,endsAt: null == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,counselor: null == counselor ? _self.counselor : counselor // ignore: cast_nullable_to_non_nullable
as BookingCounselorRef,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,cancellationHours: null == cancellationHours ? _self.cancellationHours : cancellationHours // ignore: cast_nullable_to_non_nullable
as int,cancellationDeadlineAt: freezed == cancellationDeadlineAt ? _self.cancellationDeadlineAt : cancellationDeadlineAt // ignore: cast_nullable_to_non_nullable
as DateTime?,canCancel: null == canCancel ? _self.canCancel : canCancel // ignore: cast_nullable_to_non_nullable
as bool,reschedulesLeft: null == reschedulesLeft ? _self.reschedulesLeft : reschedulesLeft // ignore: cast_nullable_to_non_nullable
as int,meeting: freezed == meeting ? _self.meeting : meeting // ignore: cast_nullable_to_non_nullable
as MeetingInfo?,place: freezed == place ? _self.place : place // ignore: cast_nullable_to_non_nullable
as BookingPlace?,
  ));
}

/// Create a copy of MyBookingDetail
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingCounselorRefCopyWith<$Res> get counselor {
  
  return $BookingCounselorRefCopyWith<$Res>(_self.counselor, (value) {
    return _then(_self.copyWith(counselor: value));
  });
}/// Create a copy of MyBookingDetail
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
}/// Create a copy of MyBookingDetail
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$MeetingInfoCopyWith<$Res>? get meeting {
    if (_self.meeting == null) {
    return null;
  }

  return $MeetingInfoCopyWith<$Res>(_self.meeting!, (value) {
    return _then(_self.copyWith(meeting: value));
  });
}/// Create a copy of MyBookingDetail
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingPlaceCopyWith<$Res>? get place {
    if (_self.place == null) {
    return null;
  }

  return $BookingPlaceCopyWith<$Res>(_self.place!, (value) {
    return _then(_self.copyWith(place: value));
  });
}
}

// dart format on
