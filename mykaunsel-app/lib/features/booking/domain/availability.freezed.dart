// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'availability.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$SlotLocation {

 String get name; String get city;
/// Create a copy of SlotLocation
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$SlotLocationCopyWith<SlotLocation> get copyWith => _$SlotLocationCopyWithImpl<SlotLocation>(this as SlotLocation, _$identity);

  /// Serializes this SlotLocation to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is SlotLocation&&(identical(other.name, name) || other.name == name)&&(identical(other.city, city) || other.city == city));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,name,city);

@override
String toString() {
  return 'SlotLocation(name: $name, city: $city)';
}


}

/// @nodoc
abstract mixin class $SlotLocationCopyWith<$Res>  {
  factory $SlotLocationCopyWith(SlotLocation value, $Res Function(SlotLocation) _then) = _$SlotLocationCopyWithImpl;
@useResult
$Res call({
 String name, String city
});




}
/// @nodoc
class _$SlotLocationCopyWithImpl<$Res>
    implements $SlotLocationCopyWith<$Res> {
  _$SlotLocationCopyWithImpl(this._self, this._then);

  final SlotLocation _self;
  final $Res Function(SlotLocation) _then;

/// Create a copy of SlotLocation
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? name = null,Object? city = null,}) {
  return _then(_self.copyWith(
name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,city: null == city ? _self.city : city // ignore: cast_nullable_to_non_nullable
as String,
  ));
}

}


/// Adds pattern-matching-related methods to [SlotLocation].
extension SlotLocationPatterns on SlotLocation {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _SlotLocation value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _SlotLocation() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _SlotLocation value)  $default,){
final _that = this;
switch (_that) {
case _SlotLocation():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _SlotLocation value)?  $default,){
final _that = this;
switch (_that) {
case _SlotLocation() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String name,  String city)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _SlotLocation() when $default != null:
return $default(_that.name,_that.city);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String name,  String city)  $default,) {final _that = this;
switch (_that) {
case _SlotLocation():
return $default(_that.name,_that.city);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String name,  String city)?  $default,) {final _that = this;
switch (_that) {
case _SlotLocation() when $default != null:
return $default(_that.name,_that.city);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _SlotLocation implements SlotLocation {
  const _SlotLocation({required this.name, required this.city});
  factory _SlotLocation.fromJson(Map<String, dynamic> json) => _$SlotLocationFromJson(json);

@override final  String name;
@override final  String city;

/// Create a copy of SlotLocation
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$SlotLocationCopyWith<_SlotLocation> get copyWith => __$SlotLocationCopyWithImpl<_SlotLocation>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$SlotLocationToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _SlotLocation&&(identical(other.name, name) || other.name == name)&&(identical(other.city, city) || other.city == city));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,name,city);

@override
String toString() {
  return 'SlotLocation(name: $name, city: $city)';
}


}

/// @nodoc
abstract mixin class _$SlotLocationCopyWith<$Res> implements $SlotLocationCopyWith<$Res> {
  factory _$SlotLocationCopyWith(_SlotLocation value, $Res Function(_SlotLocation) _then) = __$SlotLocationCopyWithImpl;
@override @useResult
$Res call({
 String name, String city
});




}
/// @nodoc
class __$SlotLocationCopyWithImpl<$Res>
    implements _$SlotLocationCopyWith<$Res> {
  __$SlotLocationCopyWithImpl(this._self, this._then);

  final _SlotLocation _self;
  final $Res Function(_SlotLocation) _then;

/// Create a copy of SlotLocation
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? name = null,Object? city = null,}) {
  return _then(_SlotLocation(
name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,city: null == city ? _self.city : city // ignore: cast_nullable_to_non_nullable
as String,
  ));
}


}


/// @nodoc
mixin _$AvailabilitySlot {

 String get id; DateTime get startsAt; int get durationMinutes;@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode get mode;@JsonKey(unknownEnumValue: SlotState.unknown) SlotState get state; SlotLocation? get location;
/// Create a copy of AvailabilitySlot
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$AvailabilitySlotCopyWith<AvailabilitySlot> get copyWith => _$AvailabilitySlotCopyWithImpl<AvailabilitySlot>(this as AvailabilitySlot, _$identity);

  /// Serializes this AvailabilitySlot to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is AvailabilitySlot&&(identical(other.id, id) || other.id == id)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.state, state) || other.state == state)&&(identical(other.location, location) || other.location == location));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,startsAt,durationMinutes,mode,state,location);

@override
String toString() {
  return 'AvailabilitySlot(id: $id, startsAt: $startsAt, durationMinutes: $durationMinutes, mode: $mode, state: $state, location: $location)';
}


}

/// @nodoc
abstract mixin class $AvailabilitySlotCopyWith<$Res>  {
  factory $AvailabilitySlotCopyWith(AvailabilitySlot value, $Res Function(AvailabilitySlot) _then) = _$AvailabilitySlotCopyWithImpl;
@useResult
$Res call({
 String id, DateTime startsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode,@JsonKey(unknownEnumValue: SlotState.unknown) SlotState state, SlotLocation? location
});


$SlotLocationCopyWith<$Res>? get location;

}
/// @nodoc
class _$AvailabilitySlotCopyWithImpl<$Res>
    implements $AvailabilitySlotCopyWith<$Res> {
  _$AvailabilitySlotCopyWithImpl(this._self, this._then);

  final AvailabilitySlot _self;
  final $Res Function(AvailabilitySlot) _then;

/// Create a copy of AvailabilitySlot
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? startsAt = null,Object? durationMinutes = null,Object? mode = null,Object? state = null,Object? location = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,state: null == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as SlotState,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,
  ));
}
/// Create a copy of AvailabilitySlot
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


/// Adds pattern-matching-related methods to [AvailabilitySlot].
extension AvailabilitySlotPatterns on AvailabilitySlot {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _AvailabilitySlot value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _AvailabilitySlot() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _AvailabilitySlot value)  $default,){
final _that = this;
switch (_that) {
case _AvailabilitySlot():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _AvailabilitySlot value)?  $default,){
final _that = this;
switch (_that) {
case _AvailabilitySlot() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String id,  DateTime startsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode, @JsonKey(unknownEnumValue: SlotState.unknown)  SlotState state,  SlotLocation? location)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _AvailabilitySlot() when $default != null:
return $default(_that.id,_that.startsAt,_that.durationMinutes,_that.mode,_that.state,_that.location);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String id,  DateTime startsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode, @JsonKey(unknownEnumValue: SlotState.unknown)  SlotState state,  SlotLocation? location)  $default,) {final _that = this;
switch (_that) {
case _AvailabilitySlot():
return $default(_that.id,_that.startsAt,_that.durationMinutes,_that.mode,_that.state,_that.location);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String id,  DateTime startsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode mode, @JsonKey(unknownEnumValue: SlotState.unknown)  SlotState state,  SlotLocation? location)?  $default,) {final _that = this;
switch (_that) {
case _AvailabilitySlot() when $default != null:
return $default(_that.id,_that.startsAt,_that.durationMinutes,_that.mode,_that.state,_that.location);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _AvailabilitySlot extends AvailabilitySlot {
  const _AvailabilitySlot({required this.id, required this.startsAt, required this.durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown) required this.mode, @JsonKey(unknownEnumValue: SlotState.unknown) required this.state, this.location}): super._();
  factory _AvailabilitySlot.fromJson(Map<String, dynamic> json) => _$AvailabilitySlotFromJson(json);

@override final  String id;
@override final  DateTime startsAt;
@override final  int durationMinutes;
@override@JsonKey(unknownEnumValue: SessionMode.unknown) final  SessionMode mode;
@override@JsonKey(unknownEnumValue: SlotState.unknown) final  SlotState state;
@override final  SlotLocation? location;

/// Create a copy of AvailabilitySlot
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$AvailabilitySlotCopyWith<_AvailabilitySlot> get copyWith => __$AvailabilitySlotCopyWithImpl<_AvailabilitySlot>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$AvailabilitySlotToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _AvailabilitySlot&&(identical(other.id, id) || other.id == id)&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.state, state) || other.state == state)&&(identical(other.location, location) || other.location == location));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,startsAt,durationMinutes,mode,state,location);

@override
String toString() {
  return 'AvailabilitySlot(id: $id, startsAt: $startsAt, durationMinutes: $durationMinutes, mode: $mode, state: $state, location: $location)';
}


}

/// @nodoc
abstract mixin class _$AvailabilitySlotCopyWith<$Res> implements $AvailabilitySlotCopyWith<$Res> {
  factory _$AvailabilitySlotCopyWith(_AvailabilitySlot value, $Res Function(_AvailabilitySlot) _then) = __$AvailabilitySlotCopyWithImpl;
@override @useResult
$Res call({
 String id, DateTime startsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode mode,@JsonKey(unknownEnumValue: SlotState.unknown) SlotState state, SlotLocation? location
});


@override $SlotLocationCopyWith<$Res>? get location;

}
/// @nodoc
class __$AvailabilitySlotCopyWithImpl<$Res>
    implements _$AvailabilitySlotCopyWith<$Res> {
  __$AvailabilitySlotCopyWithImpl(this._self, this._then);

  final _AvailabilitySlot _self;
  final $Res Function(_AvailabilitySlot) _then;

/// Create a copy of AvailabilitySlot
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? startsAt = null,Object? durationMinutes = null,Object? mode = null,Object? state = null,Object? location = freezed,}) {
  return _then(_AvailabilitySlot(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,mode: null == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode,state: null == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as SlotState,location: freezed == location ? _self.location : location // ignore: cast_nullable_to_non_nullable
as SlotLocation?,
  ));
}

/// Create a copy of AvailabilitySlot
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
mixin _$AvailabilityDay {

 String get date; Map<String, int> get counts; List<AvailabilitySlot> get slots;
/// Create a copy of AvailabilityDay
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$AvailabilityDayCopyWith<AvailabilityDay> get copyWith => _$AvailabilityDayCopyWithImpl<AvailabilityDay>(this as AvailabilityDay, _$identity);

  /// Serializes this AvailabilityDay to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is AvailabilityDay&&(identical(other.date, date) || other.date == date)&&const DeepCollectionEquality().equals(other.counts, counts)&&const DeepCollectionEquality().equals(other.slots, slots));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,date,const DeepCollectionEquality().hash(counts),const DeepCollectionEquality().hash(slots));

@override
String toString() {
  return 'AvailabilityDay(date: $date, counts: $counts, slots: $slots)';
}


}

/// @nodoc
abstract mixin class $AvailabilityDayCopyWith<$Res>  {
  factory $AvailabilityDayCopyWith(AvailabilityDay value, $Res Function(AvailabilityDay) _then) = _$AvailabilityDayCopyWithImpl;
@useResult
$Res call({
 String date, Map<String, int> counts, List<AvailabilitySlot> slots
});




}
/// @nodoc
class _$AvailabilityDayCopyWithImpl<$Res>
    implements $AvailabilityDayCopyWith<$Res> {
  _$AvailabilityDayCopyWithImpl(this._self, this._then);

  final AvailabilityDay _self;
  final $Res Function(AvailabilityDay) _then;

/// Create a copy of AvailabilityDay
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? date = null,Object? counts = null,Object? slots = null,}) {
  return _then(_self.copyWith(
date: null == date ? _self.date : date // ignore: cast_nullable_to_non_nullable
as String,counts: null == counts ? _self.counts : counts // ignore: cast_nullable_to_non_nullable
as Map<String, int>,slots: null == slots ? _self.slots : slots // ignore: cast_nullable_to_non_nullable
as List<AvailabilitySlot>,
  ));
}

}


/// Adds pattern-matching-related methods to [AvailabilityDay].
extension AvailabilityDayPatterns on AvailabilityDay {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _AvailabilityDay value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _AvailabilityDay() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _AvailabilityDay value)  $default,){
final _that = this;
switch (_that) {
case _AvailabilityDay():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _AvailabilityDay value)?  $default,){
final _that = this;
switch (_that) {
case _AvailabilityDay() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String date,  Map<String, int> counts,  List<AvailabilitySlot> slots)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _AvailabilityDay() when $default != null:
return $default(_that.date,_that.counts,_that.slots);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String date,  Map<String, int> counts,  List<AvailabilitySlot> slots)  $default,) {final _that = this;
switch (_that) {
case _AvailabilityDay():
return $default(_that.date,_that.counts,_that.slots);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String date,  Map<String, int> counts,  List<AvailabilitySlot> slots)?  $default,) {final _that = this;
switch (_that) {
case _AvailabilityDay() when $default != null:
return $default(_that.date,_that.counts,_that.slots);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _AvailabilityDay extends AvailabilityDay {
  const _AvailabilityDay({required this.date, final  Map<String, int> counts = const {}, final  List<AvailabilitySlot> slots = const []}): _counts = counts,_slots = slots,super._();
  factory _AvailabilityDay.fromJson(Map<String, dynamic> json) => _$AvailabilityDayFromJson(json);

@override final  String date;
 final  Map<String, int> _counts;
@override@JsonKey() Map<String, int> get counts {
  if (_counts is EqualUnmodifiableMapView) return _counts;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableMapView(_counts);
}

 final  List<AvailabilitySlot> _slots;
@override@JsonKey() List<AvailabilitySlot> get slots {
  if (_slots is EqualUnmodifiableListView) return _slots;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_slots);
}


/// Create a copy of AvailabilityDay
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$AvailabilityDayCopyWith<_AvailabilityDay> get copyWith => __$AvailabilityDayCopyWithImpl<_AvailabilityDay>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$AvailabilityDayToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _AvailabilityDay&&(identical(other.date, date) || other.date == date)&&const DeepCollectionEquality().equals(other._counts, _counts)&&const DeepCollectionEquality().equals(other._slots, _slots));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,date,const DeepCollectionEquality().hash(_counts),const DeepCollectionEquality().hash(_slots));

@override
String toString() {
  return 'AvailabilityDay(date: $date, counts: $counts, slots: $slots)';
}


}

/// @nodoc
abstract mixin class _$AvailabilityDayCopyWith<$Res> implements $AvailabilityDayCopyWith<$Res> {
  factory _$AvailabilityDayCopyWith(_AvailabilityDay value, $Res Function(_AvailabilityDay) _then) = __$AvailabilityDayCopyWithImpl;
@override @useResult
$Res call({
 String date, Map<String, int> counts, List<AvailabilitySlot> slots
});




}
/// @nodoc
class __$AvailabilityDayCopyWithImpl<$Res>
    implements _$AvailabilityDayCopyWith<$Res> {
  __$AvailabilityDayCopyWithImpl(this._self, this._then);

  final _AvailabilityDay _self;
  final $Res Function(_AvailabilityDay) _then;

/// Create a copy of AvailabilityDay
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? date = null,Object? counts = null,Object? slots = null,}) {
  return _then(_AvailabilityDay(
date: null == date ? _self.date : date // ignore: cast_nullable_to_non_nullable
as String,counts: null == counts ? _self._counts : counts // ignore: cast_nullable_to_non_nullable
as Map<String, int>,slots: null == slots ? _self._slots : slots // ignore: cast_nullable_to_non_nullable
as List<AvailabilitySlot>,
  ));
}


}


/// @nodoc
mixin _$AvailabilityCounselor {

 String get id; String get displayName; String? get avatarUrl; bool get verified;@JsonKey(unknownEnumValue: SessionMode.unknown) List<SessionMode> get modesOffered; bool get acceptsRequests;
/// Create a copy of AvailabilityCounselor
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$AvailabilityCounselorCopyWith<AvailabilityCounselor> get copyWith => _$AvailabilityCounselorCopyWithImpl<AvailabilityCounselor>(this as AvailabilityCounselor, _$identity);

  /// Serializes this AvailabilityCounselor to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is AvailabilityCounselor&&(identical(other.id, id) || other.id == id)&&(identical(other.displayName, displayName) || other.displayName == displayName)&&(identical(other.avatarUrl, avatarUrl) || other.avatarUrl == avatarUrl)&&(identical(other.verified, verified) || other.verified == verified)&&const DeepCollectionEquality().equals(other.modesOffered, modesOffered)&&(identical(other.acceptsRequests, acceptsRequests) || other.acceptsRequests == acceptsRequests));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,displayName,avatarUrl,verified,const DeepCollectionEquality().hash(modesOffered),acceptsRequests);

@override
String toString() {
  return 'AvailabilityCounselor(id: $id, displayName: $displayName, avatarUrl: $avatarUrl, verified: $verified, modesOffered: $modesOffered, acceptsRequests: $acceptsRequests)';
}


}

/// @nodoc
abstract mixin class $AvailabilityCounselorCopyWith<$Res>  {
  factory $AvailabilityCounselorCopyWith(AvailabilityCounselor value, $Res Function(AvailabilityCounselor) _then) = _$AvailabilityCounselorCopyWithImpl;
@useResult
$Res call({
 String id, String displayName, String? avatarUrl, bool verified,@JsonKey(unknownEnumValue: SessionMode.unknown) List<SessionMode> modesOffered, bool acceptsRequests
});




}
/// @nodoc
class _$AvailabilityCounselorCopyWithImpl<$Res>
    implements $AvailabilityCounselorCopyWith<$Res> {
  _$AvailabilityCounselorCopyWithImpl(this._self, this._then);

  final AvailabilityCounselor _self;
  final $Res Function(AvailabilityCounselor) _then;

/// Create a copy of AvailabilityCounselor
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? displayName = null,Object? avatarUrl = freezed,Object? verified = null,Object? modesOffered = null,Object? acceptsRequests = null,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,displayName: null == displayName ? _self.displayName : displayName // ignore: cast_nullable_to_non_nullable
as String,avatarUrl: freezed == avatarUrl ? _self.avatarUrl : avatarUrl // ignore: cast_nullable_to_non_nullable
as String?,verified: null == verified ? _self.verified : verified // ignore: cast_nullable_to_non_nullable
as bool,modesOffered: null == modesOffered ? _self.modesOffered : modesOffered // ignore: cast_nullable_to_non_nullable
as List<SessionMode>,acceptsRequests: null == acceptsRequests ? _self.acceptsRequests : acceptsRequests // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [AvailabilityCounselor].
extension AvailabilityCounselorPatterns on AvailabilityCounselor {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _AvailabilityCounselor value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _AvailabilityCounselor() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _AvailabilityCounselor value)  $default,){
final _that = this;
switch (_that) {
case _AvailabilityCounselor():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _AvailabilityCounselor value)?  $default,){
final _that = this;
switch (_that) {
case _AvailabilityCounselor() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String id,  String displayName,  String? avatarUrl,  bool verified, @JsonKey(unknownEnumValue: SessionMode.unknown)  List<SessionMode> modesOffered,  bool acceptsRequests)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _AvailabilityCounselor() when $default != null:
return $default(_that.id,_that.displayName,_that.avatarUrl,_that.verified,_that.modesOffered,_that.acceptsRequests);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String id,  String displayName,  String? avatarUrl,  bool verified, @JsonKey(unknownEnumValue: SessionMode.unknown)  List<SessionMode> modesOffered,  bool acceptsRequests)  $default,) {final _that = this;
switch (_that) {
case _AvailabilityCounselor():
return $default(_that.id,_that.displayName,_that.avatarUrl,_that.verified,_that.modesOffered,_that.acceptsRequests);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String id,  String displayName,  String? avatarUrl,  bool verified, @JsonKey(unknownEnumValue: SessionMode.unknown)  List<SessionMode> modesOffered,  bool acceptsRequests)?  $default,) {final _that = this;
switch (_that) {
case _AvailabilityCounselor() when $default != null:
return $default(_that.id,_that.displayName,_that.avatarUrl,_that.verified,_that.modesOffered,_that.acceptsRequests);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _AvailabilityCounselor implements AvailabilityCounselor {
  const _AvailabilityCounselor({required this.id, required this.displayName, this.avatarUrl, this.verified = true, @JsonKey(unknownEnumValue: SessionMode.unknown) final  List<SessionMode> modesOffered = const [], this.acceptsRequests = false}): _modesOffered = modesOffered;
  factory _AvailabilityCounselor.fromJson(Map<String, dynamic> json) => _$AvailabilityCounselorFromJson(json);

@override final  String id;
@override final  String displayName;
@override final  String? avatarUrl;
@override@JsonKey() final  bool verified;
 final  List<SessionMode> _modesOffered;
@override@JsonKey(unknownEnumValue: SessionMode.unknown) List<SessionMode> get modesOffered {
  if (_modesOffered is EqualUnmodifiableListView) return _modesOffered;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_modesOffered);
}

@override@JsonKey() final  bool acceptsRequests;

/// Create a copy of AvailabilityCounselor
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$AvailabilityCounselorCopyWith<_AvailabilityCounselor> get copyWith => __$AvailabilityCounselorCopyWithImpl<_AvailabilityCounselor>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$AvailabilityCounselorToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _AvailabilityCounselor&&(identical(other.id, id) || other.id == id)&&(identical(other.displayName, displayName) || other.displayName == displayName)&&(identical(other.avatarUrl, avatarUrl) || other.avatarUrl == avatarUrl)&&(identical(other.verified, verified) || other.verified == verified)&&const DeepCollectionEquality().equals(other._modesOffered, _modesOffered)&&(identical(other.acceptsRequests, acceptsRequests) || other.acceptsRequests == acceptsRequests));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,displayName,avatarUrl,verified,const DeepCollectionEquality().hash(_modesOffered),acceptsRequests);

@override
String toString() {
  return 'AvailabilityCounselor(id: $id, displayName: $displayName, avatarUrl: $avatarUrl, verified: $verified, modesOffered: $modesOffered, acceptsRequests: $acceptsRequests)';
}


}

/// @nodoc
abstract mixin class _$AvailabilityCounselorCopyWith<$Res> implements $AvailabilityCounselorCopyWith<$Res> {
  factory _$AvailabilityCounselorCopyWith(_AvailabilityCounselor value, $Res Function(_AvailabilityCounselor) _then) = __$AvailabilityCounselorCopyWithImpl;
@override @useResult
$Res call({
 String id, String displayName, String? avatarUrl, bool verified,@JsonKey(unknownEnumValue: SessionMode.unknown) List<SessionMode> modesOffered, bool acceptsRequests
});




}
/// @nodoc
class __$AvailabilityCounselorCopyWithImpl<$Res>
    implements _$AvailabilityCounselorCopyWith<$Res> {
  __$AvailabilityCounselorCopyWithImpl(this._self, this._then);

  final _AvailabilityCounselor _self;
  final $Res Function(_AvailabilityCounselor) _then;

/// Create a copy of AvailabilityCounselor
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? displayName = null,Object? avatarUrl = freezed,Object? verified = null,Object? modesOffered = null,Object? acceptsRequests = null,}) {
  return _then(_AvailabilityCounselor(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,displayName: null == displayName ? _self.displayName : displayName // ignore: cast_nullable_to_non_nullable
as String,avatarUrl: freezed == avatarUrl ? _self.avatarUrl : avatarUrl // ignore: cast_nullable_to_non_nullable
as String?,verified: null == verified ? _self.verified : verified // ignore: cast_nullable_to_non_nullable
as bool,modesOffered: null == modesOffered ? _self._modesOffered : modesOffered // ignore: cast_nullable_to_non_nullable
as List<SessionMode>,acceptsRequests: null == acceptsRequests ? _self.acceptsRequests : acceptsRequests // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}


/// @nodoc
mixin _$BookingWindow {

 DateTime get earliestStart; String get lastDate; int get minLeadHours;
/// Create a copy of BookingWindow
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$BookingWindowCopyWith<BookingWindow> get copyWith => _$BookingWindowCopyWithImpl<BookingWindow>(this as BookingWindow, _$identity);

  /// Serializes this BookingWindow to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is BookingWindow&&(identical(other.earliestStart, earliestStart) || other.earliestStart == earliestStart)&&(identical(other.lastDate, lastDate) || other.lastDate == lastDate)&&(identical(other.minLeadHours, minLeadHours) || other.minLeadHours == minLeadHours));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,earliestStart,lastDate,minLeadHours);

@override
String toString() {
  return 'BookingWindow(earliestStart: $earliestStart, lastDate: $lastDate, minLeadHours: $minLeadHours)';
}


}

/// @nodoc
abstract mixin class $BookingWindowCopyWith<$Res>  {
  factory $BookingWindowCopyWith(BookingWindow value, $Res Function(BookingWindow) _then) = _$BookingWindowCopyWithImpl;
@useResult
$Res call({
 DateTime earliestStart, String lastDate, int minLeadHours
});




}
/// @nodoc
class _$BookingWindowCopyWithImpl<$Res>
    implements $BookingWindowCopyWith<$Res> {
  _$BookingWindowCopyWithImpl(this._self, this._then);

  final BookingWindow _self;
  final $Res Function(BookingWindow) _then;

/// Create a copy of BookingWindow
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? earliestStart = null,Object? lastDate = null,Object? minLeadHours = null,}) {
  return _then(_self.copyWith(
earliestStart: null == earliestStart ? _self.earliestStart : earliestStart // ignore: cast_nullable_to_non_nullable
as DateTime,lastDate: null == lastDate ? _self.lastDate : lastDate // ignore: cast_nullable_to_non_nullable
as String,minLeadHours: null == minLeadHours ? _self.minLeadHours : minLeadHours // ignore: cast_nullable_to_non_nullable
as int,
  ));
}

}


/// Adds pattern-matching-related methods to [BookingWindow].
extension BookingWindowPatterns on BookingWindow {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _BookingWindow value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _BookingWindow() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _BookingWindow value)  $default,){
final _that = this;
switch (_that) {
case _BookingWindow():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _BookingWindow value)?  $default,){
final _that = this;
switch (_that) {
case _BookingWindow() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( DateTime earliestStart,  String lastDate,  int minLeadHours)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _BookingWindow() when $default != null:
return $default(_that.earliestStart,_that.lastDate,_that.minLeadHours);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( DateTime earliestStart,  String lastDate,  int minLeadHours)  $default,) {final _that = this;
switch (_that) {
case _BookingWindow():
return $default(_that.earliestStart,_that.lastDate,_that.minLeadHours);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( DateTime earliestStart,  String lastDate,  int minLeadHours)?  $default,) {final _that = this;
switch (_that) {
case _BookingWindow() when $default != null:
return $default(_that.earliestStart,_that.lastDate,_that.minLeadHours);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _BookingWindow implements BookingWindow {
  const _BookingWindow({required this.earliestStart, required this.lastDate, required this.minLeadHours});
  factory _BookingWindow.fromJson(Map<String, dynamic> json) => _$BookingWindowFromJson(json);

@override final  DateTime earliestStart;
@override final  String lastDate;
@override final  int minLeadHours;

/// Create a copy of BookingWindow
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$BookingWindowCopyWith<_BookingWindow> get copyWith => __$BookingWindowCopyWithImpl<_BookingWindow>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$BookingWindowToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _BookingWindow&&(identical(other.earliestStart, earliestStart) || other.earliestStart == earliestStart)&&(identical(other.lastDate, lastDate) || other.lastDate == lastDate)&&(identical(other.minLeadHours, minLeadHours) || other.minLeadHours == minLeadHours));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,earliestStart,lastDate,minLeadHours);

@override
String toString() {
  return 'BookingWindow(earliestStart: $earliestStart, lastDate: $lastDate, minLeadHours: $minLeadHours)';
}


}

/// @nodoc
abstract mixin class _$BookingWindowCopyWith<$Res> implements $BookingWindowCopyWith<$Res> {
  factory _$BookingWindowCopyWith(_BookingWindow value, $Res Function(_BookingWindow) _then) = __$BookingWindowCopyWithImpl;
@override @useResult
$Res call({
 DateTime earliestStart, String lastDate, int minLeadHours
});




}
/// @nodoc
class __$BookingWindowCopyWithImpl<$Res>
    implements _$BookingWindowCopyWith<$Res> {
  __$BookingWindowCopyWithImpl(this._self, this._then);

  final _BookingWindow _self;
  final $Res Function(_BookingWindow) _then;

/// Create a copy of BookingWindow
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? earliestStart = null,Object? lastDate = null,Object? minLeadHours = null,}) {
  return _then(_BookingWindow(
earliestStart: null == earliestStart ? _self.earliestStart : earliestStart // ignore: cast_nullable_to_non_nullable
as DateTime,lastDate: null == lastDate ? _self.lastDate : lastDate // ignore: cast_nullable_to_non_nullable
as String,minLeadHours: null == minLeadHours ? _self.minLeadHours : minLeadHours // ignore: cast_nullable_to_non_nullable
as int,
  ));
}


}


/// @nodoc
mixin _$Availability {

 AvailabilityCounselor get counselor; String get timeZone; BookingWindow get bookingWindow; List<AvailabilityDay> get days; String? get nextFrom;
/// Create a copy of Availability
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$AvailabilityCopyWith<Availability> get copyWith => _$AvailabilityCopyWithImpl<Availability>(this as Availability, _$identity);

  /// Serializes this Availability to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is Availability&&(identical(other.counselor, counselor) || other.counselor == counselor)&&(identical(other.timeZone, timeZone) || other.timeZone == timeZone)&&(identical(other.bookingWindow, bookingWindow) || other.bookingWindow == bookingWindow)&&const DeepCollectionEquality().equals(other.days, days)&&(identical(other.nextFrom, nextFrom) || other.nextFrom == nextFrom));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,counselor,timeZone,bookingWindow,const DeepCollectionEquality().hash(days),nextFrom);

@override
String toString() {
  return 'Availability(counselor: $counselor, timeZone: $timeZone, bookingWindow: $bookingWindow, days: $days, nextFrom: $nextFrom)';
}


}

/// @nodoc
abstract mixin class $AvailabilityCopyWith<$Res>  {
  factory $AvailabilityCopyWith(Availability value, $Res Function(Availability) _then) = _$AvailabilityCopyWithImpl;
@useResult
$Res call({
 AvailabilityCounselor counselor, String timeZone, BookingWindow bookingWindow, List<AvailabilityDay> days, String? nextFrom
});


$AvailabilityCounselorCopyWith<$Res> get counselor;$BookingWindowCopyWith<$Res> get bookingWindow;

}
/// @nodoc
class _$AvailabilityCopyWithImpl<$Res>
    implements $AvailabilityCopyWith<$Res> {
  _$AvailabilityCopyWithImpl(this._self, this._then);

  final Availability _self;
  final $Res Function(Availability) _then;

/// Create a copy of Availability
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? counselor = null,Object? timeZone = null,Object? bookingWindow = null,Object? days = null,Object? nextFrom = freezed,}) {
  return _then(_self.copyWith(
counselor: null == counselor ? _self.counselor : counselor // ignore: cast_nullable_to_non_nullable
as AvailabilityCounselor,timeZone: null == timeZone ? _self.timeZone : timeZone // ignore: cast_nullable_to_non_nullable
as String,bookingWindow: null == bookingWindow ? _self.bookingWindow : bookingWindow // ignore: cast_nullable_to_non_nullable
as BookingWindow,days: null == days ? _self.days : days // ignore: cast_nullable_to_non_nullable
as List<AvailabilityDay>,nextFrom: freezed == nextFrom ? _self.nextFrom : nextFrom // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}
/// Create a copy of Availability
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AvailabilityCounselorCopyWith<$Res> get counselor {
  
  return $AvailabilityCounselorCopyWith<$Res>(_self.counselor, (value) {
    return _then(_self.copyWith(counselor: value));
  });
}/// Create a copy of Availability
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingWindowCopyWith<$Res> get bookingWindow {
  
  return $BookingWindowCopyWith<$Res>(_self.bookingWindow, (value) {
    return _then(_self.copyWith(bookingWindow: value));
  });
}
}


/// Adds pattern-matching-related methods to [Availability].
extension AvailabilityPatterns on Availability {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _Availability value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _Availability() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _Availability value)  $default,){
final _that = this;
switch (_that) {
case _Availability():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _Availability value)?  $default,){
final _that = this;
switch (_that) {
case _Availability() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( AvailabilityCounselor counselor,  String timeZone,  BookingWindow bookingWindow,  List<AvailabilityDay> days,  String? nextFrom)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _Availability() when $default != null:
return $default(_that.counselor,_that.timeZone,_that.bookingWindow,_that.days,_that.nextFrom);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( AvailabilityCounselor counselor,  String timeZone,  BookingWindow bookingWindow,  List<AvailabilityDay> days,  String? nextFrom)  $default,) {final _that = this;
switch (_that) {
case _Availability():
return $default(_that.counselor,_that.timeZone,_that.bookingWindow,_that.days,_that.nextFrom);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( AvailabilityCounselor counselor,  String timeZone,  BookingWindow bookingWindow,  List<AvailabilityDay> days,  String? nextFrom)?  $default,) {final _that = this;
switch (_that) {
case _Availability() when $default != null:
return $default(_that.counselor,_that.timeZone,_that.bookingWindow,_that.days,_that.nextFrom);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _Availability implements Availability {
  const _Availability({required this.counselor, required this.timeZone, required this.bookingWindow, final  List<AvailabilityDay> days = const [], this.nextFrom}): _days = days;
  factory _Availability.fromJson(Map<String, dynamic> json) => _$AvailabilityFromJson(json);

@override final  AvailabilityCounselor counselor;
@override final  String timeZone;
@override final  BookingWindow bookingWindow;
 final  List<AvailabilityDay> _days;
@override@JsonKey() List<AvailabilityDay> get days {
  if (_days is EqualUnmodifiableListView) return _days;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_days);
}

@override final  String? nextFrom;

/// Create a copy of Availability
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$AvailabilityCopyWith<_Availability> get copyWith => __$AvailabilityCopyWithImpl<_Availability>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$AvailabilityToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _Availability&&(identical(other.counselor, counselor) || other.counselor == counselor)&&(identical(other.timeZone, timeZone) || other.timeZone == timeZone)&&(identical(other.bookingWindow, bookingWindow) || other.bookingWindow == bookingWindow)&&const DeepCollectionEquality().equals(other._days, _days)&&(identical(other.nextFrom, nextFrom) || other.nextFrom == nextFrom));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,counselor,timeZone,bookingWindow,const DeepCollectionEquality().hash(_days),nextFrom);

@override
String toString() {
  return 'Availability(counselor: $counselor, timeZone: $timeZone, bookingWindow: $bookingWindow, days: $days, nextFrom: $nextFrom)';
}


}

/// @nodoc
abstract mixin class _$AvailabilityCopyWith<$Res> implements $AvailabilityCopyWith<$Res> {
  factory _$AvailabilityCopyWith(_Availability value, $Res Function(_Availability) _then) = __$AvailabilityCopyWithImpl;
@override @useResult
$Res call({
 AvailabilityCounselor counselor, String timeZone, BookingWindow bookingWindow, List<AvailabilityDay> days, String? nextFrom
});


@override $AvailabilityCounselorCopyWith<$Res> get counselor;@override $BookingWindowCopyWith<$Res> get bookingWindow;

}
/// @nodoc
class __$AvailabilityCopyWithImpl<$Res>
    implements _$AvailabilityCopyWith<$Res> {
  __$AvailabilityCopyWithImpl(this._self, this._then);

  final _Availability _self;
  final $Res Function(_Availability) _then;

/// Create a copy of Availability
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? counselor = null,Object? timeZone = null,Object? bookingWindow = null,Object? days = null,Object? nextFrom = freezed,}) {
  return _then(_Availability(
counselor: null == counselor ? _self.counselor : counselor // ignore: cast_nullable_to_non_nullable
as AvailabilityCounselor,timeZone: null == timeZone ? _self.timeZone : timeZone // ignore: cast_nullable_to_non_nullable
as String,bookingWindow: null == bookingWindow ? _self.bookingWindow : bookingWindow // ignore: cast_nullable_to_non_nullable
as BookingWindow,days: null == days ? _self._days : days // ignore: cast_nullable_to_non_nullable
as List<AvailabilityDay>,nextFrom: freezed == nextFrom ? _self.nextFrom : nextFrom // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

/// Create a copy of Availability
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AvailabilityCounselorCopyWith<$Res> get counselor {
  
  return $AvailabilityCounselorCopyWith<$Res>(_self.counselor, (value) {
    return _then(_self.copyWith(counselor: value));
  });
}/// Create a copy of Availability
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$BookingWindowCopyWith<$Res> get bookingWindow {
  
  return $BookingWindowCopyWith<$Res>(_self.bookingWindow, (value) {
    return _then(_self.copyWith(bookingWindow: value));
  });
}
}

// dart format on
